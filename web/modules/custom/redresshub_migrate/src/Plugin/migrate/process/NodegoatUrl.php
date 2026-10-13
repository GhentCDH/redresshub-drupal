<?php

declare(strict_types=1);

namespace Drupal\redresshub_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Makes a Nodegoat URL absolute: "www.vrt.be" becomes "https://www.vrt.be".
 *
 * @code
 * field_website:
 *   - plugin: nodegoat_multiple
 *     source: d5933
 *   - plugin: nodegoat_url
 * @endcode
 */
#[MigrateProcess(id: 'nodegoat_url')]
final class NodegoatUrl extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): ?string {
    $url = trim((string) $value);
    if ($url === '') {
      return NULL;
    }
    return preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) ? $url : 'https://' . ltrim($url, '/');
  }

}
