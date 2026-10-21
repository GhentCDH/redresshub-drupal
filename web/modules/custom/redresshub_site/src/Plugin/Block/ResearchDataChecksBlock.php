<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\redresshub_site\ResearchData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Research data that misses something (ResearchData::CHECKS), with links to
 * edit it.
 */
#[Block(
  id: 'redresshub_research_data_checks',
  admin_label: new TranslatableMarkup('Incomplete research data'),
  category: new TranslatableMarkup('RedressHub'),
)]
final class ResearchDataChecksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * How many items to list per check.
   */
  private const LIMIT = 10;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  protected function blockAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'access content overview');
  }

  public function build(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $build = ['#cache' => ['tags' => ['node_list'], 'contexts' => ['user.permissions']]];
    foreach (ResearchData::CHECKS as [$bundle, $field, $label]) {
      $query = fn() => $storage->getQuery()->accessCheck(TRUE)->condition('type', $bundle)->notExists($field);
      $total = (int) $query()->count()->execute();
      if (!$total) {
        continue;
      }
      $items = [];
      foreach ($storage->loadMultiple($query()->sort('title')->range(0, self::LIMIT)->execute()) as $node) {
        $items[] = ['#type' => 'link', '#title' => $node->label(), '#url' => $node->toUrl('edit-form')];
      }
      if ($total > self::LIMIT) {
        $items[] = ['#markup' => $this->t('and @count more', ['@count' => $total - self::LIMIT])];
      }
      $build[$bundle . '_' . $field] = [
        '#type' => 'details',
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        '#title' => $this->t('@count · @label', ['@count' => $total, '@label' => $this->t($label)]),
        'items' => ['#theme' => 'item_list', '#items' => $items],
      ];
    }
    if (count($build) === 1) {
      $build['complete'] = ['#markup' => '<p>' . $this->t('Nothing is missing.') . '</p>'];
    }
    return $build;
  }

}
