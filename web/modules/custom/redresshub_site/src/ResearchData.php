<?php

declare(strict_types=1);

namespace Drupal\redresshub_site;

/**
 * The research data: the content types and vocabularies of the database.
 *
 * They have their own environment under /admin/research-data; the content
 * overview shows the other content (pages, events, the home page).
 */
final class ResearchData {

  public const TYPES = ['initiative', 'organisation', 'location', 'fragment'];

  public const VOCABULARIES = [
    'initiative_type',
    'injustice_type',
    'keywords',
    'sector',
    'organisation_status',
    'location_type',
    'places',
    'historical_era',
    'un_category',
    'fragment_type',
  ];

}
