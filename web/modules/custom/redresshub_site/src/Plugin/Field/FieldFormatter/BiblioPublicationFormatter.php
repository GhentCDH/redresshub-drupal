<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\redresshub_site\Biblio;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows Biblio IDs as publications, with the data fetched from Biblio.
 */
#[FieldFormatter(
  id: 'redresshub_biblio_publication',
  label: new TranslatableMarkup('Biblio publication'),
  field_types: ['string'],
)]
final class BiblioPublicationFormatter extends FormatterBase {

  private Biblio $biblio;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->biblio = $container->get('redresshub_site.biblio');
    return $instance;
  }

  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $id = trim((string) $item->value);
      $publication = $this->biblio->get($id);
      if ($publication === NULL) {
        continue;
      }
      $elements[$delta] = [
        '#theme' => 'biblio_publication',
        '#publication' => $publication,
        '#url' => Biblio::url($id),
        '#cache' => ['max-age' => 86400, 'tags' => ['redresshub_biblio']],
      ];
    }
    return $elements;
  }

}
