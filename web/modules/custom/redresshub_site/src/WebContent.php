<?php

declare(strict_types=1);

namespace Drupal\redresshub_site;

/**
 * The web content: everything of the website that is not research data
 * (pages, events, publications, fragments, examples; the home page apart).
 *
 * It has its own environment under /admin/web-content.
 */
final class WebContent {

  /**
   * The choice lists (vocabularies) of the web content.
   */
  public const VOCABULARIES = ['fragment_type'];

  /**
   * The content types of the web content, without the home page.
   *
   * @return string[]
   */
  public static function types(): array {
    $types = array_keys(\Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple());
    return array_values(array_diff($types, ResearchData::TYPES, ['home']));
  }

}
