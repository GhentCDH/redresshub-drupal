<?php

declare(strict_types=1);

namespace Drupal\redresshub_site;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetches publications from UGent Biblio (biblio.ugent.be).
 *
 * Records are cached for a day. The last record that could be fetched is
 * also kept permanently, so the page still shows it when Biblio is down.
 */
final class Biblio {

  private const BASE_URL = 'https://biblio.ugent.be/publication/';

  private const CACHE_LIFETIME = 86400;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
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
   * Returns a publication, or NULL if it cannot be found.
   *
   * @return array{id: string, title: string, authors: string[], outlet: string, volume: string, issue: string, year: string, status: string, doi: string}|null
   */
  public function get(string $id): ?array {
    $id = trim($id);
    if (!preg_match('/^[0-9A-Za-z]+$/', $id)) {
      return NULL;
    }
    $cid = "redresshub:biblio:$id";
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $store = $this->keyValue->get('redresshub_site.biblio');
    try {
      $response = $this->httpClient->request('GET', self::url($id) . '.json', ['timeout' => 10]);
      $publication = $this->normalize($id, json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR));
      $store->set($id, $publication);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Biblio publication @id could not be fetched: @message', ['@id' => $id, '@message' => $e->getMessage()]);
      $publication = $store->get($id);
    }
    $this->cache->set($cid, $publication, time() + self::CACHE_LIFETIME, ['redresshub_biblio']);
    return $publication;
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
    ];
  }

}
