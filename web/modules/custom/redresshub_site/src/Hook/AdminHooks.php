<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The admin side of RedressHub: the toolbar and where users land.
 */
final class AdminHooks {

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * The icons of the editorial menu.
   */
  #[Hook('toolbar_alter')]
  public function toolbarAlter(array &$items): void {
    if (isset($items['administration'])) {
      $items['administration']['#attached']['library'][] = 'redresshub_site/editorial_toolbar';
    }
  }

  /**
   * The admin pages in the colours of the site (css/admin.css).
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (\Drupal::service('router.admin_context')->isAdminRoute()) {
      $attachments['#attached']['library'][] = 'redresshub_site/admin';
    }
  }

  /**
   * Content forms: long lists of choices in columns, and only real languages.
   *
   * Translatable content is always in one of the site languages: no "Not
   * specified" or "Not applicable".
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form): void {
    $form['#attached']['library'][] = 'redresshub_site/forms';
    if (isset($form['langcode']['widget'][0]['value'])) {
      $form['langcode']['widget'][0]['value']['#languages'] = LanguageInterface::STATE_CONFIGURABLE;
    }
    if (isset($form['field_fragment_type'])) {
      $form['#after_build'][] = [self::class, 'fragmentGroups'];
      $form['#validate'][] = [self::class, 'fragmentMediaValidate'];
      // The media library: only the media types that fit the type.
      $types = [];
      foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'fragment_type']) as $term) {
        $types[$term->id()] = strtolower($term->getUntranslated()->label());
      }
      $form['#attached']['library'][] = 'redresshub_site/fragment_media';
      $form['#attached']['drupalSettings']['redresshubFragmentTypes'] = $types;
    }
  }

  /**
   * Fragment form: the media fits the type (an image for an image, …), and a
   * slideshow has at least two images.
   */
  public static function fragmentMediaValidate(array &$form, FormStateInterface $form_state): void {
    $allowed = [
      'image' => ['image'],
      'audio' => ['audio'],
      'video' => ['video', 'remote_video'],
    ];
    $messages = [
      'image' => t('An image fragment needs an image.'),
      'audio' => t('An audio fragment needs an audio file.'),
      'video' => t('A video fragment needs a video.'),
    ];
    $node = $form_state->getFormObject()->buildEntity($form, $form_state);
    $type = $node->get('field_fragment_type')->entity;
    if (!$type) {
      return;
    }
    $key = strtolower($type->getUntranslated()->label());
    if ($key === 'slideshow') {
      if ($node->hasField('field_slides') && $node->get('field_slides')->count() < 2) {
        $form_state->setErrorByName('field_slides', t('A slideshow needs at least two images.'));
      }
      return;
    }
    $media = $node->get('field_media')->entity;
    if ($media && isset($allowed[$key]) && !in_array($media->bundle(), $allowed[$key], TRUE)) {
      $form_state->setErrorByName('field_media', $messages[$key]);
    }
  }

  /**
   * Fragment form: the parts of the form only for the types that use them.
   *
   * Image, video and audio: one media; a slideshow: its images; both with a
   * caption and a credit. Quote and text: the text (see
   * node--fragment--teaser.html.twig).
   */
  public static function fragmentGroups(array $form): array {
    $parts = [
      'group_media' => ['image', 'audio', 'video', 'slideshow'],
      'field_media' => ['image', 'audio', 'video'],
      'field_slides' => ['slideshow'],
      'group_content' => ['text', 'quote'],
    ];
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'fragment_type']);
    foreach ($parts as $part => $types) {
      $conditions = [];
      foreach ($terms as $term) {
        if (in_array(strtolower($term->getUntranslated()->label()), $types, TRUE)) {
          // [cond, 'or', cond, …]: visible when one of them is true.
          $conditions[] = 'or';
          $conditions[] = [':input[name="field_fragment_type"]' => ['value' => (string) $term->id()]];
        }
      }
      if ($conditions && isset($form[$part])) {
        $form[$part]['#states']['visible'] = array_slice($conditions, 1);
      }
    }
    return $form;
  }

  /**
   * After logging in, the front page (not the dashboard, as Dashboard does).
   *
   * Runs before Dashboard: it leaves a destination that is set already.
   */
  #[Hook('user_login', order: Order::First)]
  public function userLogin(): void {
    $query = $this->requestStack->getCurrentRequest()->query;
    if ($this->routeMatch->getRouteName() !== 'user.reset.login' && !$query->get('destination')) {
      $query->set('destination', Url::fromRoute('<front>')->toString());
    }
  }

}
