<?php

declare(strict_types=1);

namespace Drupal\redresshub_migrate\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\redresshub_migrate\Nodegoat;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Downloads the Nodegoat data, so the migrations can run from local files.
 *
 * The Nodegoat API allows only 25 requests per 15 minutes, so the migrations
 * never call it directly: this command makes one request per type.
 */
final class NodegoatCommands extends DrushCommands {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('http_client'),
      $container->get('config.factory'),
    );
  }

  /**
   * Downloads the model and all objects of the Nodegoat types.
   */
  #[CLI\Command(name: 'nodegoat:fetch')]
  #[CLI\Usage(name: 'drush nodegoat:fetch', description: 'Download the snapshots, then run: drush migrate:import --tag=Nodegoat --update')]
  public function fetch(): void {
    $api = rtrim((string) getenv('NODEGOAT_API'), '/');
    $token = (string) getenv('NODEGOAT_TOKEN');
    if ($api === '' || $token === '') {
      throw new \RuntimeException('Set NODEGOAT_API and NODEGOAT_TOKEN in .env (and recreate the container).');
    }

    $config = $this->configFactory->get('redresshub_migrate.settings');
    $project = (int) $config->get('project_id');
    $type_ids = array_map('intval', $config->get('type_ids') ?? []);

    $dir = Nodegoat::snapshotDir();
    if (!is_dir($dir) && !mkdir($dir, 0700, TRUE)) {
      throw new \RuntimeException("Cannot create $dir.");
    }

    $requests = [
      'model.json' => "$api/project/$project/model/type/" . implode(',', $type_ids),
    ];
    foreach ($type_ids as $type_id) {
      $requests["type-$type_id.json"] = "$api/project/$project/data/type/$type_id/object?limit=0&total=1";
    }

    foreach ($requests as $file => $url) {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => ['Authorization' => "Bearer $token"],
        'timeout' => 120,
      ]);
      $body = (string) $response->getBody();
      $data = json_decode($body, TRUE, flags: JSON_THROW_ON_ERROR);
      if (!empty($data['error'])) {
        throw new \RuntimeException("$file: {$data['error']} {$data['error_description']}");
      }
      file_put_contents("$dir/$file", $body);
      $count = $data['data']['count'] ?? count($data['data']['types'] ?? []);
      $this->logger()->success("$file: $count");
    }
    $this->logger()->notice(sprintf('%d requests made; the API allows 25 per 15 minutes.', count($requests)));
  }

}
