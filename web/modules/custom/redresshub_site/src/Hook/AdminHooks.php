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

  /**
   * Basic site settings: whether the site is only for logged-in users.
   */
  #[Hook('form_system_site_information_settings_alter')]
  public function siteInformationFormAlter(array &$form): void {
    $form['site_access'] = [
      '#type' => 'details',
      '#title' => t('Access'),
      '#open' => TRUE,
      'redresshub_login_required' => [
        '#type' => 'checkbox',
        '#title' => t('Only for logged-in users'),
        '#description' => t('Visitors who are not logged in get the login form. Untick to make the site public.'),
        '#default_value' => (bool) \Drupal::config('redresshub_site.settings')->get('login_required'),
      ],
    ];
    $form['#submit'][] = [static::class, 'siteInformationFormSubmit'];
  }

  /**
   * Saves the access setting of the basic site settings.
   */
  public static function siteInformationFormSubmit(array &$form, FormStateInterface $form_state): void {
    \Drupal::configFactory()->getEditable('redresshub_site.settings')
      ->set('login_required', (bool) $form_state->getValue('redresshub_login_required'))
      ->save();
  }

}
