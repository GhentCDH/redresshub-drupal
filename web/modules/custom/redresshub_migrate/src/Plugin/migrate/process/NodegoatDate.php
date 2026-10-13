<?php

declare(strict_types=1);

namespace Drupal\redresshub_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts a Nodegoat date to Y-m-d.
 *
 * Nodegoat dates are "2019", "2019-05" or "2019-05-14"; "∞" means ongoing
 * and "0" means no date. A year or month is expanded to its first day for a
 * start date and to its last day for an end date:
 * @code
 * field_period/value:
 *   plugin: nodegoat_date
 *   source: period/date_start
 *   part: start
 * @endcode
 */
#[MigrateProcess(id: 'nodegoat_date')]
final class NodegoatDate extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): ?string {
    $value = trim((string) $value);
    if ($value === '' || $value === '0' || $value === '∞') {
      return NULL;
    }
    // A few dates start with a stray "-" (e.g. "-2014-12"): ignore it.
    if (str_starts_with($value, '-')) {
      $migrate_executable->saveMessage(sprintf('Date "%s" starts with "-"; imported as "%s".', $value, substr($value, 1)), 3);
      $value = substr($value, 1);
    }
    $is_end = ($this->configuration['part'] ?? 'start') === 'end';

    if (preg_match('/^\d{4}$/', $value)) {
      return $value . ($is_end ? '-12-31' : '-01-01');
    }
    if (preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) {
      return $is_end ? date('Y-m-t', mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1])) : "$value-01";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      return $value;
    }
    $migrate_executable->saveMessage(sprintf('Unknown date "%s", skipped.', $value), 2);
    return NULL;
  }

}
