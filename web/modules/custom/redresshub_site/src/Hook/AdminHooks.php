<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
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
