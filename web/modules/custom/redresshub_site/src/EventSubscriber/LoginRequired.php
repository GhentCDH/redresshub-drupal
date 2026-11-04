<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The site only for logged-in users (redresshub_site.settings:login_required).
 *
 * Visitors who are not logged in go to the login form, and back to the page
 * they asked for once logged in. Logged-in users see the site as a visitor
 * would once it is public.
 */
final class LoginRequired implements EventSubscriberInterface {

  /**
   * Routes a visitor needs to log in.
   */
  private const OPEN_ROUTES = [
    'user.login',
    'user.login.http',
    'user.pass',
    'user.pass.http',
    'user.reset',
    'user.reset.form',
    'user.reset.login',
    'system.css_asset',
    'system.js_asset',
    'image.style_public',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountInterface $currentUser,
    private readonly RouteMatchInterface $routeMatch,
    private readonly KillSwitch $pageCacheKillSwitch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After routing (32), before the dynamic page cache (27).
    $events[KernelEvents::REQUEST][] = ['onRequest', 31];
    $events[ConfigEvents::SAVE][] = ['onConfigSave'];
    return $events;
  }

  /**
   * Sends a visitor who is not logged in to the login form.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()
      || !$this->currentUser->isAnonymous()
      || !$this->configFactory->get('redresshub_site.settings')->get('login_required')
      || in_array($this->routeMatch->getRouteName(), self::OPEN_ROUTES, TRUE)) {
      return;
    }
    // Not for the page cache: the page itself is what a visitor gets once
    // the site is public.
    $this->pageCacheKillSwitch->trigger();
    $request = $event->getRequest();
    $options = [];
    $path = $request->getPathInfo();
    if ($path !== '/' && $path !== '') {
      $options['query']['destination'] = $request->getRequestUri();
    }
    elseif ($query = $request->getQueryString()) {
      $options['query']['destination'] = '/?' . $query;
    }
    $url = Url::fromRoute('user.login', [], $options)->toString();
    $event->setResponse(new RedirectResponse($url));
  }

  /**
   * Switching it: pages cached for visitors no longer hold.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'redresshub_site.settings' && $event->isChanged('login_required')) {
      Cache::invalidateTags(['http_response']);
    }
  }

}
