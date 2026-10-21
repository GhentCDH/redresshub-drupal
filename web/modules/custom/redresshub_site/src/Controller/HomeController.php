<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * "Edit the home page": the edit form of the home node, whatever its ID.
 */
final class HomeController extends ControllerBase {

  public function edit(): RedirectResponse {
    $home = self::home();
    if (!$home) {
      throw new NotFoundHttpException();
    }
    return $this->redirect('entity.node.edit_form', ['node' => $home->id()]);
  }

  public static function access(AccountInterface $account): AccessResultInterface {
    $home = self::home();
    return $home ? $home->access('update', $account, TRUE) : AccessResult::forbidden()->addCacheTags(['node_list:home']);
  }

  private static function home(): ?NodeInterface {
    $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'home')->range(0, 1)->execute();
    return $ids ? \Drupal::entityTypeManager()->getStorage('node')->load(reset($ids)) : NULL;
  }

}
