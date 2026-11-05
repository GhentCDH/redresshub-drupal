<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Hook;

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
      $form['#after_build'][] = [self::class, 'fragmentMediaTab'];
      $form['#attached']['library'][] = 'redresshub_site/tab_states';
    }
  }

  /**
   * Fragment form: the Media tab only for the types with media.
   *
   * The types image, audio and video (see node--fragment--teaser.html.twig).
   */
  public static function fragmentMediaTab(array $form): array {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'fragment_type']);
    $conditions = [];
    foreach ($terms as $term) {
      if (in_array(strtolower($term->getUntranslated()->label()), ['image', 'audio', 'video'], TRUE)) {
        $conditions[] = [':input[name="field_fragment_type"]' => ['value' => (string) $term->id()]];
      }
    }
    if ($conditions && isset($form['group_media'])) {
      // [cond, 'or', cond, …]: visible when one of them is true.
      $form['group_media']['#states']['visible'] = array_merge(...array_map(fn($c) => [$c, 'or'], $conditions));
      array_pop($form['group_media']['#states']['visible']);
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
