<?php

declare(strict_types=1);

namespace Drupal\redresshub_migrate\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\redresshub_migrate\Nodegoat;

/**
 * Reads the objects of one Nodegoat type from its snapshot file.
 *
 * Each object becomes a flat row:
 * - id: the Nodegoat object ID.
 * - name: the object name.
 * - d{description ID}: the values of a description, always a list.
 * - r{description ID}: the referenced object IDs of a description, a list;
 *   r{description ID}_t{type ID} only has the references to that type.
 * - s{sub-object details ID}: the sub-objects, a list of
 *   [date_start, date_end, location_ref, geometry, d{sub description ID}].
 * - certain_{sub-object details ID}: FALSE when a sub-object of that kind has
 *   a "Certain" (boolean) value set to false.
 *
 * Text is in the configured language: "language: nl" returns the [[NL]] part
 * of multilingual values (falling back to English), and only the objects that
 * have a Dutch translation of their name or of a text description.
 *
 * With "distinct_reference", the rows are the objects referenced by a
 * description instead, e.g. the cities of the addresses:
 * @code
 * distinct_reference:
 *   description_id: 5954
 *   # "city": rows of "Brussels (BE)" with id, name "Brussels", country "BE".
 *   # "country": one row per country code, with id "BE", name "Belgium".
 *   part: city
 * @endcode
 *
 * Example:
 * @code
 * source:
 *   plugin: nodegoat_objects
 *   type_id: 1595
 *   language: en
 * @endcode
 */
#[MigrateSource(id: 'nodegoat_objects')]
final class NodegoatObjects extends SourcePluginBase {

  /**
   * Country names for the country codes in the shared Nodegoat city list.
   */
  private const COUNTRIES = [
    'BE' => 'Belgium',
    'CD' => 'Democratic Republic of the Congo',
    'RW' => 'Rwanda',
    'BI' => 'Burundi',
    'NL' => 'Netherlands',
    'FR' => 'France',
    'DE' => 'Germany',
    'GB' => 'United Kingdom',
    'LU' => 'Luxembourg',
  ];

  public function __toString(): string {
    return 'Nodegoat type ' . $this->configuration['type_id'];
  }

  public function getIds(): array {
    return ['id' => ['type' => 'string', 'max_length' => 64]];
  }

  public function fields(): array {
    return [
      'id' => $this->t('Nodegoat object ID'),
      'name' => $this->t('Name'),
    ];
  }

  protected function initializeIterator(): \ArrayIterator {
    $type_id = (int) ($this->configuration['type_id'] ?? 0);
    $file = Nodegoat::snapshotFile($type_id);
    if (!is_readable($file)) {
      throw new MigrateException("Snapshot $file is missing: run drush nodegoat:fetch first.");
    }
    $data = json_decode(file_get_contents($file), TRUE, flags: JSON_THROW_ON_ERROR);
    $objects = $data['data']['objects'] ?? [];

    if (!empty($this->configuration['distinct_reference'])) {
      return new \ArrayIterator($this->distinctReferences($objects));
    }

    $langcode = $this->configuration['language'] ?? Nodegoat::DEFAULT_LANGCODE;
    $rows = [];
    foreach ($objects as $object) {
      $row = $this->flatten($object, $langcode);
      if ($row !== NULL) {
        $rows[] = $row;
      }
    }
    return new \ArrayIterator($rows);
  }

  /**
   * Flattens an object, or returns NULL when it has no such translation.
   */
  private function flatten(array $object, string $langcode): ?array {
    $translated = FALSE;
    $text = function (string $value) use ($langcode, &$translated): string {
      $texts = Nodegoat::splitLanguages($value);
      if (isset($texts[$langcode])) {
        $translated = TRUE;
        return $texts[$langcode];
      }
      return $texts[Nodegoat::DEFAULT_LANGCODE] ?? $value;
    };

    $row = [
      'id' => (string) $object['object']['object_id'],
      'name' => $text($object['object']['object_name_plain'] ?? $object['object']['object_name'] ?? ''),
    ];

    // Empty definitions come as a list instead of an object.
    $definitions = is_array($object['object_definitions'] ?? NULL) ? $object['object_definitions'] : [];
    foreach ($definitions as $definition) {
      $id = $definition['object_description_id'] ?? NULL;
      if ($id === NULL) {
        continue;
      }
      $values = $definition['object_definition_value'] ?? NULL;
      $values = $values === NULL ? [] : (array) $values;
      $row["d$id"] = array_map(fn($value) => is_string($value) ? $text($value) : $value, $values);

      $row["r$id"] = [];
      $refs = $definition['object_definition_ref_object_id'] ?? NULL;
      // A single reference is a plain ID instead of a list.
      if (is_int($refs) || (is_string($refs) && $refs !== '')) {
        $refs = [$refs];
      }
      // Text with inline object links has a map of the linked objects
      // instead of a list of references.
      if (is_array($refs) && array_is_list($refs)) {
        foreach ($refs as $ref) {
          // References to more than one type come as "type_object".
          [$ref_type, $ref_id] = str_contains((string) $ref, '_') ? explode('_', (string) $ref, 2) : [NULL, $ref];
          $row["r$id"][] = (int) $ref_id;
          if ($ref_type !== NULL) {
            $row["r{$id}_t$ref_type"][] = (int) $ref_id;
          }
        }
      }
    }

    foreach ($object['object_subs'] ?? [] as $sub) {
      $details_id = $sub['object_sub']['object_sub_details_id'];
      $geometry = $sub['object_sub']['object_sub_location_geometry'] ?? NULL;
      $item = [
        'date_start' => $sub['object_sub']['object_sub_date_start'] ?? NULL,
        'date_end' => $sub['object_sub']['object_sub_date_end'] ?? NULL,
        'location_ref' => $sub['object_sub']['object_sub_location_ref_object_id'] ?: NULL,
        'geometry' => $geometry ?: NULL,
      ];
      $row["certain_$details_id"] ??= TRUE;
      foreach ($sub['object_sub_definitions'] ?? [] as $sub_definition) {
        $value = $sub_definition['object_sub_definition_value'] ?? NULL;
        $item['d' . $sub_definition['object_sub_description_id']] = $value;
        if ($value === FALSE) {
          $row["certain_$details_id"] = FALSE;
        }
      }
      $row["s$details_id"][] = $item;
    }

    if ($langcode !== Nodegoat::DEFAULT_LANGCODE && !$translated) {
      return NULL;
    }
    return $row;
  }

  /**
   * Returns the distinct objects referenced by a description.
   */
  private function distinctReferences(array $objects): array {
    $description_id = $this->configuration['distinct_reference']['description_id'];
    $part = $this->configuration['distinct_reference']['part'] ?? 'object';
    $rows = [];
    foreach ($objects as $object) {
      $definitions = is_array($object['object_definitions'] ?? NULL) ? $object['object_definitions'] : [];
      $definition = $definitions[$description_id] ?? NULL;
      if (!$definition) {
        continue;
      }
      $refs = (array) ($definition['object_definition_ref_object_id'] ?? []);
      $values = (array) ($definition['object_definition_value'] ?? []);
      foreach ($refs as $delta => $ref_id) {
        $label = (string) ($values[$delta] ?? '');
        // "Brussels (BE)": name and country code.
        preg_match('/^(.*?)\s*(?:\(([A-Z]{2})\))?$/', $label, $matches);
        $name = $matches[1] ?? $label;
        $country = $matches[2] ?? '';
        if ($part === 'country') {
          if ($country !== '') {
            $rows[$country] = ['id' => $country, 'name' => self::COUNTRIES[$country] ?? $country];
          }
        }
        else {
          $rows[$ref_id] = ['id' => (string) $ref_id, 'name' => $name, 'country' => $country];
        }
      }
    }
    return array_values($rows);
  }

}
