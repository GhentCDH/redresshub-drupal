<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the fields of the explorer that Search API cannot index by itself.
 *
 * - redresshub_initiative_types, redresshub_initiative_keywords: for an
 *   organisation, the types and keywords of the initiatives that link to it
 *   (a reverse reference).
 * - redresshub_translations: the titles and texts of all translations, so
 *   the search finds Dutch and French words too. The index only has the
 *   original language of each item (one item per node).
 *
 * @see redresshub_site_node_update()
 */
#[SearchApiProcessor(
  id: 'redresshub_explorer_fields',
  label: new TranslatableMarkup('RedressHub explorer fields'),
  description: new TranslatableMarkup('Terms of the initiatives of an organisation, and the text of all translations.'),
  stages: ['add_properties' => 0],
)]
final class ExplorerFields extends ProcessorPluginBase {

  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if (!$datasource || $datasource->getEntityTypeId() !== 'node') {
      return [];
    }
    return [
      'redresshub_initiative_types' => new ProcessorProperty([
        'label' => $this->t('Types of the initiatives (organisations)'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]),
      'redresshub_initiative_keywords' => new ProcessorProperty([
        'label' => $this->t('Keywords of the initiatives (organisations)'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]),
      'redresshub_translations' => new ProcessorProperty([
        'label' => $this->t('Text of all translations'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]),
    ];
  }

  public function addFieldValues(ItemInterface $item): void {
    $node = $item->getOriginalObject()->getValue();
    if (!$node instanceof NodeInterface) {
      return;
    }
    $fields = $item->getFields(FALSE);

    if ($node->bundle() === 'organisation') {
      $initiatives = $this->initiativesOf($node);
      $values = [
        'redresshub_initiative_types' => 'field_initiative_type',
        'redresshub_initiative_keywords' => 'field_keywords',
      ];
      foreach ($values as $property => $field_name) {
        $tids = [];
        foreach ($initiatives as $initiative) {
          foreach ($initiative->get($field_name) as $reference) {
            $tids[(int) $reference->target_id] = TRUE;
          }
        }
        foreach ($this->getFieldsHelper()->filterForPropertyPath($fields, $item->getDatasourceId(), $property) as $field) {
          foreach (array_keys($tids) as $tid) {
            $field->addValue($tid);
          }
        }
      }
    }

    $texts = [];
    foreach ($node->getTranslationLanguages() as $language) {
      $translation = $node->getTranslation($language->getId());
      $texts[] = $translation->label();
      if ($translation->hasField('field_short_description')) {
        $texts[] = strip_tags((string) $translation->get('field_short_description')->value);
      }
    }
    foreach ($this->getFieldsHelper()->filterForPropertyPath($fields, $item->getDatasourceId(), 'redresshub_translations') as $field) {
      $field->addValue(implode("\n", array_unique(array_filter($texts))));
    }
  }

  /**
   * Returns the published initiatives that link to an organisation.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function initiativesOf(NodeInterface $organisation): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'initiative')
      ->condition('status', 1)
      ->condition('field_organisations', $organisation->id())
      ->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

}
