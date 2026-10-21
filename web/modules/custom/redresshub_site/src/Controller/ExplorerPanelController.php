<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * The side panel of the explorer for an item of the network.
 *
 * The list and the map have the panel of each item in the page; the network
 * fetches it when a node is clicked.
 */
final class ExplorerPanelController extends ControllerBase {

  public function panel(NodeInterface $node): CacheableResponse {
    $build = $this->entityTypeManager()->getViewBuilder('node')->view($node, 'panel');
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $response = new CacheableResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build)->addCacheContexts(['languages:language_content']));
    return $response;
  }

}
