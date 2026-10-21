<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\redresshub_site\ResearchData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Buttons to add content, per group, for the content the user may add.
 */
#[Block(
  id: 'redresshub_quick_actions',
  admin_label: new TranslatableMarkup('Quick actions'),
  category: new TranslatableMarkup('RedressHub'),
)]
final class QuickActionsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  public function build(): array {
    $groups = [
      (string) $this->t('Website') => ['event', 'page'],
      (string) $this->t('Research data') => ResearchData::TYPES,
    ];
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $cacheability = (new CacheableMetadata())->addCacheContexts(['user.permissions'])->addCacheTags(['config:node_type_list']);
    $build = ['#type' => 'container', '#attributes' => ['class' => ['quick-actions']]];
    foreach ($groups as $label => $bundles) {
      $links = [];
      foreach ($bundles as $bundle) {
        if (!isset($types[$bundle])) {
          continue;
        }
        $url = Url::fromRoute('node.add', ['node_type' => $bundle]);
        $access = $url->access(NULL, TRUE);
        $cacheability->addCacheableDependency($access);
        if ($access->isAllowed()) {
          $links[$bundle] = [
            '#type' => 'link',
            '#title' => $types[$bundle]->label(),
            '#url' => $url,
            '#attributes' => ['class' => ['button', 'button--action', 'button--small']],
          ];
        }
      }
      if ($links) {
        $build[$label] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['quick-actions__group']],
          'label' => ['#markup' => '<h3 class="quick-actions__label">' . $label . '</h3>'],
        ] + $links;
      }
    }
    $build['#attached']['library'][] = 'redresshub_site/dashboard';
    $cacheability->applyTo($build);
    return $build;
  }

}
