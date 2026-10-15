<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\views\filter;

use Drupal\Core\Database\Database;
use Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid;
use Drupal\views\Attribute\ViewsFilter;

/**
 * Filters nodes on a term of the nodes they are linked to by organisations.
 *
 * E.g. initiatives whose organisations have a sector, or organisations whose
 * initiatives have a type. Uses a subquery instead of a relationship, so the
 * results are not duplicated per linked node or translation.
 *
 * Definition keys (see redresshub_site_views_data_alter()):
 * - base_column: the column of node__field_organisations with the nid of the
 *   filtered node (entity_id for initiatives, the target for organisations).
 * - other_column: the column with the nid of the linked node.
 * - term_table, term_column: the term reference of the linked node.
 */
#[ViewsFilter('redresshub_related_term')]
final class RelatedTermFilter extends TaxonomyIndexTid {

  public function query() {
    $tids = array_filter(array_map('intval', (array) $this->value));
    if (!$tids) {
      return;
    }
    $definition = $this->definition;
    $subquery = Database::getConnection()->select('node__field_organisations', 'o');
    $subquery->addField('o', $definition['base_column']);
    $subquery->join($definition['term_table'], 't', "[t].[entity_id] = [o].[{$definition['other_column']}]");
    $subquery->condition("t.{$definition['term_column']}", $tids, 'IN');

    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.nid", $subquery, 'IN');
  }

}
