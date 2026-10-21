<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\redresshub_site\ResearchData;

/**
 * Pages of the research data environment.
 */
final class ResearchDataController extends ControllerBase {

  /**
   * The vocabularies of the research data, with links to their terms.
   */
  public function vocabularies(): array {
    $cacheability = (new CacheableMetadata())->addCacheContexts(['user.permissions'])->addCacheTags(['config:taxonomy_vocabulary_list']);
    $rows = [];
    foreach ($this->entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple(ResearchData::VOCABULARIES) as $vocabulary) {
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
      '#header' => [$this->t('Vocabulary'), $this->t('Description'), $this->t('Operations')],
      '#rows' => $rows,
      '#empty' => $this->t('No vocabularies.'),
    ];
    $cacheability->applyTo($build);
    return $build;
  }

}
