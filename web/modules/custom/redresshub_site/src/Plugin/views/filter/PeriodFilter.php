<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters on the year a date range overlaps with.
 *
 * "from" keeps items that end in or after the year (or are ongoing), "to"
 * keeps items that start in or before the year. Together they select the
 * items whose period overlaps with from–to. Items without a start date are
 * left out when the filter is used.
 */
#[ViewsFilter('redresshub_period')]
final class PeriodFilter extends FilterPluginBase {

  public function adminSummary() {
    return $this->value ? (string) $this->value : $this->t('any year');
  }

  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'number',
      '#title' => $this->definition['boundary'] === 'from' ? $this->t('From') : $this->t('To'),
      '#min' => 1800,
      '#max' => 2100,
      '#size' => 6,
      '#default_value' => $this->value,
    ];
  }

  public function acceptExposedInput($input) {
    $identifier = $this->options['expose']['identifier'] ?? NULL;
    $value = $identifier ? ($input[$identifier] ?? '') : '';
    if (!preg_match('/^\d{4}$/', (string) $value)) {
      return FALSE;
    }
    $this->value = (int) $value;
    return TRUE;
  }

  public function query() {
    if (!$this->value) {
      return;
    }
    $this->ensureMyTable();
    $start = "$this->tableAlias.{$this->definition['start_column']}";
    $end = "$this->tableAlias.{$this->definition['end_column']}";
    $year = (int) $this->value;
    if ($this->definition['boundary'] === 'from') {
      $this->query->addWhereExpression($this->options['group'], "$start IS NOT NULL AND ($end IS NULL OR $end >= :redresshub_from_$year)", [":redresshub_from_$year" => sprintf('%04d-01-01', $year)]);
    }
    else {
      $this->query->addWhereExpression($this->options['group'], "$start <= :redresshub_to_$year", [":redresshub_to_$year" => sprintf('%04d-12-31T23:59:59', $year)]);
    }
  }

}
