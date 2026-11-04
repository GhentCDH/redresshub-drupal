<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * The footer logos: one page with only these logos, to add, change or
 * remove them, without the rest of the media.
 */
final class FooterLogosController extends ControllerBase {

  /**
   * The media type of the footer logos.
   */
  public const MEDIA_TYPE = 'partner_logo';

  /**
   * The page: an "Add a logo" button and the logos, in the order of the
   * footer.
   */
  public function page(): array {
    $storage = $this->entityTypeManager()->getStorage('media');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('bundle', self::MEDIA_TYPE)
      ->sort('field_weight')->sort('name')->execute();
    $cacheability = (new CacheableMetadata())
      ->addCacheTags(['media_list:' . self::MEDIA_TYPE])
      ->addCacheContexts(['user.permissions']);

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $media) {
      $cacheability->addCacheableDependency($media);
      $image = $media->get('field_media_image')->first();
      $link = $media->get('field_link')->first();
      $buttons = [];
      foreach (['edit-form' => $this->t('Edit'), 'delete-form' => $this->t('Remove')] as $rel => $label) {
        $url = $media->toUrl($rel);
        if ($url->access()) {
          $buttons[$rel] = [
            '#type' => 'link',
            '#title' => $label,
            '#url' => $url,
            '#attributes' => ['class' => ['button', 'button--small', $rel === 'delete-form' ? 'button--danger' : '']],
          ];
        }
      }
      $rows[] = [
        ['data' => $image?->entity ? [
          '#theme' => 'image',
          '#uri' => $image->entity->getFileUri(),
          '#alt' => $image->alt ?? '',
          '#attributes' => ['class' => ['footer-logos__image']],
        ] : ''],
        $media->label(),
        $link ? ['data' => ['#type' => 'link', '#title' => preg_replace('#^https?://(www\.)?#', '', rtrim($link->getUrl()->toString(), '/')), '#url' => $link->getUrl()]] : '',
        $media->get('field_weight')->value ?? 0,
        $media->isPublished() ? $this->t('Yes') : $this->t('No (hidden)'),
        ['data' => $buttons],
      ];
    }

    $build['intro'] = [
      '#markup' => '<p>' . $this->t('These logos are shown at the bottom of every page of the website, in this order.') . '</p>',
    ];
    $add = Url::fromRoute('entity.media.add_form', ['media_type' => self::MEDIA_TYPE]);
    if ($add->access()) {
      $build['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add a logo'),
        '#url' => $add,
        '#attributes' => ['class' => ['button', 'button--primary', 'button--action']],
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }
    $build['logos'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Logo'),
        $this->t('Name'),
        $this->t('Link'),
        $this->t('Order'),
        $this->t('Shown'),
        $this->t('Change'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('There are no logos yet.'),
      '#attributes' => ['class' => ['footer-logos']],
    ];
    $build['#attached']['library'][] = 'redresshub_site/footer_logos';
    $cacheability->applyTo($build);
    return $build;
  }

}
