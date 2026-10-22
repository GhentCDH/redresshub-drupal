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

  public const TYPES = ['initiative', 'organisation', 'location'];

  /**
   * What research data should have: content type, field and what is missing.
   *
   * For the "Empty fields" block on the dashboard; a check of a field that
   * does not exist (any more) is skipped.
   */
  public const CHECKS = [
    ['initiative', 'field_place', 'Initiatives without a place'],
    ['initiative', 'field_organisations', 'Initiatives without an organisation'],
    ['initiative', 'field_initiative_type', 'Initiatives without a type'],
    ['initiative', 'field_short_description', 'Initiatives without a short description'],
    ['organisation', 'field_sector', 'Organisations without a sector'],
    ['location', 'field_coordinates', 'Locations without coordinates'],
  ];

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
  ];

}
