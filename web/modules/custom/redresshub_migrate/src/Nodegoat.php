<?php

declare(strict_types=1);

namespace Drupal\redresshub_migrate;

/**
 * Helpers for the Nodegoat data.
 */
final class Nodegoat {

  /**
   * The language of the text before the first [[XX]] marker.
   */
  public const DEFAULT_LANGCODE = 'en';

  /**
   * Returns the absolute path of the snapshot directory.
   */
  public static function snapshotDir(): string {
    $dir = \Drupal::config('redresshub_migrate.settings')->get('snapshot_dir') ?: '../private/nodegoat';
    return str_starts_with($dir, '/') ? $dir : DRUPAL_ROOT . '/' . $dir;
  }

  /**
   * Returns the path of the snapshot file of a type.
   */
  public static function snapshotFile(int $type_id): string {
    return self::snapshotDir() . "/type-$type_id.json";
  }

  /**
   * Splits a multilingual Nodegoat value into its languages.
   *
   * Nodegoat stores translations in one value:
   * "English [[NL]] Nederlands [[FR]] Français", in any order.
   *
   * @return string[]
   *   The text per langcode, e.g. ['en' => 'English', 'nl' => 'Nederlands'].
   */
  public static function splitLanguages(string $value): array {
    $parts = preg_split('/\s*\[\[([A-Z]{2})\]\]\s*/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    $texts = [self::DEFAULT_LANGCODE => trim(array_shift($parts))];
    while ($parts) {
      $langcode = strtolower(array_shift($parts));
      $texts[$langcode] = trim((string) array_shift($parts));
    }
    return array_filter($texts, fn($text) => $text !== '');
  }

  /**
   * Returns the text of one language, or NULL if it has no such translation.
   */
  public static function translate(string $value, string $langcode): ?string {
    return self::splitLanguages($value)[$langcode] ?? NULL;
  }

}
