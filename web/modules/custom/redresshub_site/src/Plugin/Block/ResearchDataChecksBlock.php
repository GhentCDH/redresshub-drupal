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
use Drupal\Core\Url;
use Drupal\redresshub_site\ResearchData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fields of the research data that are still empty (ResearchData::CHECKS),
 * with links to fill them in. Not always known: an invitation, not an error.
 */
#[Block(
  id: 'redresshub_research_data_checks',
  admin_label: new TranslatableMarkup('Empty fields in the research data'),
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
    $build = [
      '#cache' => ['tags' => ['node_list'], 'contexts' => ['user.permissions']],
      'intro' => ['#markup' => '<p class="research-data-checks__intro">' . $this->t('Not always known: fill them in when the information is available.') . '</p>'],
    ];
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
    if (count($build) === 2) {
      $build['intro'] = ['#markup' => '<p>' . $this->t('No empty fields.') . '</p>'];
    }
    $build['more'] = [
      '#type' => 'link',
      '#title' => $this->t('All research data'),
      '#url' => Url::fromRoute('view.content.research_data'),
      '#prefix' => '<div class="more-link">',
      '#suffix' => '</div>',
    ];
    return $build;
  }

}
