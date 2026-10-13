<?php

declare(strict_types=1);

namespace Drupal\redresshub_migrate\Plugin\migrate\process;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\MigrateStubInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Converts Nodegoat text to HTML.
 *
 * Blank lines become paragraphs, single newlines line breaks, and inline
 * object links ("[object=1595_2685771_1]label[/object]") links to the node
 * the linked object was migrated to:
 * @code
 * field_short_description/value:
 *   plugin: nodegoat_text
 *   source: d5920/0
 *   links:
 *     1595: nodegoat_initiative
 * @endcode
 */
#[MigrateProcess(id: 'nodegoat_text')]
final class NodegoatText extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MigrateLookupInterface $migrateLookup,
    private readonly MigrateStubInterface $migrateStub,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('migrate.lookup'),
      $container->get('migrate.stub'),
    );
  }

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): ?string {
    $text = trim(str_replace("\r\n", "\n", (string) $value));
    if ($text === '') {
      return NULL;
    }
    $html = Html::escape($text);

    $html = preg_replace_callback(
      '/\[object=(\d+)_(\d+)(?:_\d+)?\](.*?)\[\/object\]/s',
      fn(array $m) => $this->link((int) $m[1], $m[2], $m[3]),
      $html,
    );

    $paragraphs = preg_split('/\n\s*\n/', $html);
    return implode("\n", array_map(fn($p) => '<p>' . nl2br(trim($p), FALSE) . '</p>', $paragraphs));
  }

  /**
   * Returns a link to the node of a Nodegoat object, or the plain label.
   */
  private function link(int $type_id, string $object_id, string $label): string {
    $migration = $this->configuration['links'][$type_id] ?? NULL;
    if ($migration === NULL) {
      return $label;
    }
    $ids = $this->migrateLookup->lookup($migration, [$object_id]);
    // Not imported yet (e.g. a link to a later initiative): create a stub,
    // which the migration fills in when it gets there.
    $nid = $ids[0]['nid'] ?? $this->migrateStub->createStub($migration, [$object_id])['nid'] ?? NULL;
    return $nid ? sprintf('<a href="/node/%d">%s</a>', $nid, $label) : $label;
  }

}
