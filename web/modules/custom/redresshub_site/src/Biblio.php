<?php

declare(strict_types=1);

namespace Drupal\redresshub_site;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetches publications from UGent Biblio (biblio.ugent.be).
 *
 * Each record that could be fetched is kept, so a publication can still be
 * saved and shown when Biblio cannot be reached.
 */
final class Biblio {

  private const BASE_URL = 'https://biblio.ugent.be/publication/';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly KeyValueFactoryInterface $keyValue,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns the URL of a publication in Biblio.
   */
  public static function url(string $id): string {
    return self::BASE_URL . rawurlencode($id);
  }

  /**
   * Returns the Biblio ID in an ID or a Biblio URL, or NULL if there is none.
   */
  public static function idFrom(string $input): ?string {
    $input = trim($input);
    if (preg_match('#biblio\.ugent\.be/publication/([0-9A-Za-z]+)#', $input, $matches)) {
      return $matches[1];
    }
    return preg_match('/^[0-9A-Za-z]+$/', $input) ? $input : NULL;
  }

  /**
   * Fetches a publication from Biblio, and keeps a copy of it.
   *
   * @return array|null
   *   The publication (see get()), or NULL if Biblio could not be reached
   *   or does not know the ID.
   */
  public function fetch(string $id): ?array {
    $id = (string) self::idFrom($id);
    if ($id === '') {
      return NULL;
    }
    try {
      $response = $this->httpClient->request('GET', self::url($id) . '.json', ['timeout' => 10]);
      $publication = $this->normalize($id, json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR));
    }
    catch (\Throwable $e) {
      $this->logger->warning('Biblio publication @id could not be fetched: @message', ['@id' => $id, '@message' => $e->getMessage()]);
      return NULL;
    }
    $this->keyValue->get('redresshub_site.biblio')->set($id, $publication);
    return $publication;
  }

  /**
   * Returns the last copy of a publication that could be fetched, or NULL.
   */
  public function stored(string $id): ?array {
    return $this->keyValue->get('redresshub_site.biblio')->get($id);
  }

  /**
   * Keeps the fields shown on the site.
   */
  private function normalize(string $id, array $data): array {
    $outlet = (string) ($data['parent']['title'] ?? $data['publisher'] ?? '');
    // Biblio has journal titles in capitals ("BIG DATA & SOCIETY").
    if ($outlet !== '' && mb_strtoupper($outlet) === $outlet) {
      $outlet = mb_convert_case(mb_strtolower($outlet), MB_CASE_TITLE);
    }
    return [
      'id' => $id,
      'title' => (string) ($data['title'] ?? ''),
      'authors' => array_values(array_filter(array_map(fn($author) => $author['name'] ?? '', $data['author'] ?? []))),
      'outlet' => $outlet,
      'volume' => (string) ($data['volume'] ?? ''),
      'issue' => (string) ($data['issue'] ?? ''),
      'year' => (string) ($data['year'] ?? ''),
      'status' => (string) ($data['publication_status'] ?? ''),
      'doi' => (string) ($data['doi'][0] ?? ''),
      'type' => (string) ($data['type'] ?? ''),
    ];
  }

}
