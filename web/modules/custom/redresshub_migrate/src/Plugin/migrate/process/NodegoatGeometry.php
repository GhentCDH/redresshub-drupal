<?php

declare(strict_types=1);

namespace Drupal\redresshub_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts a Nodegoat GeoJSON geometry to WKT for a Geofield.
 *
 * @code
 * field_coordinates:
 *   plugin: nodegoat_geometry
 *   source: geometry
 * @endcode
 */
#[MigrateProcess(id: 'nodegoat_geometry')]
final class NodegoatGeometry extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): ?string {
    if (empty($value)) {
      return NULL;
    }
    $geometry = \Drupal::service('geofield.geophp')->load($value, 'json');
    if (!$geometry) {
      $migrate_executable->saveMessage(sprintf('Invalid geometry: %s', $value), 2);
      return NULL;
    }
    return $geometry->out('wkt');
  }

}
