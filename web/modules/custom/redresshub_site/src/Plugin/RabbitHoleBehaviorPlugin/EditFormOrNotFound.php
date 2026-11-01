<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\RabbitHoleBehaviorPlugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\rabbit_hole\Plugin\RabbitHoleBehaviorPluginBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No page of its own: the edit form for who may edit, else page not found.
 *
 * For content that is only shown elsewhere (research data in the explorer,
 * terms): the title links in the admin lists still lead somewhere useful.
 *
 * @RabbitHoleBehaviorPlugin(
 *   id = "redresshub_edit_form_or_not_found",
 *   label = @Translation("Edit form (for who may edit), else page not found")
 * )
 */
final class EditFormOrNotFound extends RabbitHoleBehaviorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function performAction(EntityInterface $entity, ?Response $current_response = NULL) {
    if ($entity->hasLinkTemplate('edit-form') && $entity->access('update')) {
      return new RedirectResponse($entity->toUrl('edit-form')->toString());
    }
    throw new NotFoundHttpException();
  }

}
