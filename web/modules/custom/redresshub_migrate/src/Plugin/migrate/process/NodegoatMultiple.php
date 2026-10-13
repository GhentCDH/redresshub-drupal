<?php

declare(strict_types=1);

namespace Drupal\redresshub_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Marks a list as multiple values, so the next plugin handles each one.
 *
 * Like migrate_plus' "multiple_values", without depending on migrate_plus.
 * It takes the whole list (also when an earlier step already split it), so
 * the next plugin always gets one value at a time:
 * @code
 * field_keywords:
 *   - plugin: nodegoat_multiple
 *     source: r5922
 *   - plugin: migration_lookup
 *     migration: nodegoat_keywords
 * @endcode
 */
#[MigrateProcess(id: 'nodegoat_multiple', handle_multiples: TRUE)]
final class NodegoatMultiple extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): array {
    return array_values((array) $value);
  }

  public function multiple(): bool {
    return TRUE;
  }

}
