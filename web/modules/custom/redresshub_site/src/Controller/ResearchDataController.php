<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\redresshub_site\ResearchData;
use Drupal\redresshub_site\WebContent;

/**
 * Pages of the research data and web content environments.
 */
final class ResearchDataController extends ControllerBase {

  /**
   * The choice lists (vocabularies) of the research data or of the web
   * content ($group: research or web), with links to their terms.
   */
  public function vocabularies(string $group = 'research'): array {
    $vocabularies = $group === 'web' ? WebContent::VOCABULARIES : ResearchData::VOCABULARIES;
    $cacheability = (new CacheableMetadata())->addCacheContexts(['user.permissions'])->addCacheTags(['config:taxonomy_vocabulary_list']);
    $rows = [];
    foreach ($this->entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple($vocabularies) as $vocabulary) {
      $links = [];
      $overview = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()]);
      if ($overview->access()) {
        $links['terms'] = ['title' => $this->t('Terms'), 'url' => $overview];
      }
      $add = Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $vocabulary->id()]);
      if ($add->access()) {
        $links['add'] = ['title' => $this->t('Add term'), 'url' => $add];
      }
      $rows[] = [
        $overview->access() ? ['data' => ['#type' => 'link', '#title' => $vocabulary->label(), '#url' => $overview]] : $vocabulary->label(),
        $vocabulary->getDescription(),
        ['data' => ['#type' => 'operations', '#links' => $links]],
      ];
    }
    $build = [
      '#type' => 'table',
      '#header' => [$this->t('Choice list'), $this->t('Description'), $this->t('Operations')],
      '#rows' => $rows,
      '#empty' => $this->t('No choice lists.'),
    ];
    $cacheability->applyTo($build);
    return $build;
  }

}
