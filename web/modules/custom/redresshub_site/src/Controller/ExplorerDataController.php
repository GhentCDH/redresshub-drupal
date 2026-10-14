<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * The data of the explorer on the front page (map, list and network).
 *
 * Returns all published initiatives and organisations, in the current
 * language, with the terms to filter on. The explorer filters in the
 * browser, so this is one request per language (and cached).
 */
final class ExplorerDataController implements ContainerInjectionInterface {

  /**
   * Vocabulary of each filter.
   */
  private const VOCABULARIES = [
    'types' => 'initiative_type',
    'un' => 'un_category',
    'keywords' => 'keywords',
    'sectors' => 'sector',
  ];

  private string $langcode;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
  ) {
    $this->langcode = $languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
    );
  }

  public function __invoke(): CacheableJsonResponse {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $load = fn(string $bundle) => $node_storage->loadByProperties(['type' => $bundle, 'status' => 1]);

    $locations = [];
    foreach ($load('location') as $location) {
      $point = $location->get('field_coordinates')->first();
      if ($point && $point->lat !== NULL) {
        $locations[$location->id()] = [
          'name' => $this->translated($location)->label(),
          'lat' => round((float) $point->lat, 6),
          'lng' => round((float) $point->lon, 6),
        ];
      }
    }
    $places = fn(NodeInterface $node, string $field) => array_values(array_filter(array_map(
      fn($item) => $locations[$item->target_id] ?? NULL,
      iterator_to_array($node->get($field)),
    )));

    $initiatives = [];
    $updated = 0;
    foreach ($load('initiative') as $node) {
      $t = $this->translated($node);
      $period = $node->get('field_period')->first();
      $initiatives[] = [
        'id' => (int) $node->id(),
        'title' => $t->label(),
        'url' => $t->toUrl()->toString(),
        'types' => $this->ids($node, 'field_initiative_type'),
        'un' => $this->ids($node, 'field_un_category'),
        'keywords' => $this->ids($node, 'field_keywords'),
        'organisations' => $this->ids($node, 'field_organisations'),
        'start' => $period?->value,
        'end' => $period?->end_value,
        'certain' => (bool) $node->get('field_period_certain')->value,
        'places' => $places($node, 'field_place'),
        'text' => (string) ($t->get('field_short_description')->processed ?? ''),
        'resources' => array_values(array_map(fn($item) => $item->getUrl()->toString(), iterator_to_array($node->get('field_resources')))),
      ];
      $updated = max($updated, $node->getChangedTime());
    }

    $organisations = [];
    foreach ($load('organisation') as $node) {
      $t = $this->translated($node);
      $period = $node->get('field_period')->first();
      $website = $node->get('field_website')->first();
      $organisations[] = [
        'id' => (int) $node->id(),
        'title' => $t->label(),
        'url' => $t->toUrl()->toString(),
        'sectors' => $this->ids($node, 'field_sector'),
        'status' => $this->ids($node, 'field_organisation_status'),
        'website' => $website?->getUrl()->toString(),
        'start' => $period?->value,
        'end' => $period?->end_value,
        'places' => $places($node, 'field_locations'),
      ];
      $updated = max($updated, $node->getChangedTime());
    }

    $terms = [];
    foreach (self::VOCABULARIES + ['status' => 'organisation_status'] as $key => $vid) {
      $terms[$key] = [];
      $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]);
      foreach ($entities as $term) {
        $terms[$key][] = [
          'id' => (int) $term->id(),
          'name' => $this->translated($term)->label(),
          // The name in the original language, for links with term names.
          'original' => $term->getUntranslated()->label(),
        ];
      }
      usort($terms[$key], fn($a, $b) => strcasecmp($a['name'], $b['name']));
    }

    $response = new CacheableJsonResponse([
      'updated' => $updated ? date('Y-m-d', $updated) : NULL,
      'terms' => $terms,
      'initiatives' => $initiatives,
      'organisations' => $organisations,
    ]);
    $response->addCacheableDependency((new CacheableMetadata())
      ->setCacheTags(['node_list', 'taxonomy_term_list'])
      ->setCacheContexts(['languages:language_content']));
    return $response;
  }

  /**
   * Returns the entity in the current language, if it is translated.
   */
  private function translated(ContentEntityInterface $entity): ContentEntityInterface {
    return $entity->hasTranslation($this->langcode) ? $entity->getTranslation($this->langcode) : $entity;
  }

  /**
   * Returns the referenced IDs of a field.
   */
  private function ids(ContentEntityInterface $entity, string $field): array {
    return array_values(array_map(fn($item) => (int) $item->target_id, iterator_to_array($entity->get($field))));
  }

}
