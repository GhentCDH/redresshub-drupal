<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The editorial menu as cards: a card per group, its "add" links as buttons.
 *
 * The same menu as the toolbar (redresshub_site.links.menu.yml), so both
 * stay the same. Links the user may not open are left out.
 */
#[Block(
  id: 'redresshub_menu_cards',
  admin_label: new TranslatableMarkup('Editorial menu as cards'),
  category: new TranslatableMarkup('RedressHub'),
)]
final class MenuCardsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Routes whose links are shown as buttons, labelled with what they add.
   */
  private const ADD_ROUTES = ['node.add', 'user.admin_create'];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MenuLinkTreeInterface $menuTree,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('menu.link_tree'), $container->get('entity_type.manager'));
  }

  public function build(): array {
    $tree = $this->menuTree->load('editorial', (new MenuTreeParameters())->setMaxDepth(3)->onlyEnabledLinks());
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);
    // The cacheability of the access checks. The cards in a container of
    // their own: the block takes the attributes of its content.
    $build = array_intersect_key($this->menuTree->build($tree), ['#cache' => TRUE]);
    $build['#attached']['library'][] = 'redresshub_site/dashboard';
    $build['cards'] = ['#type' => 'container', '#attributes' => ['class' => ['menu-cards']]];
    $cards = &$build['cards'];

    foreach ($tree as $element) {
      $link = $element->link;
      if (!$element->access?->isAllowed() || $link->getRouteName() === 'dashboard') {
        continue;
      }
      $buttons = $links = [];
      foreach ($element->subtree as $child) {
        if (!$child->access?->isAllowed()) {
          continue;
        }
        $grandchildren = array_filter($child->subtree, fn(MenuLinkTreeElement $item) => $item->access?->isAllowed());
        $adds = array_filter($grandchildren, fn(MenuLinkTreeElement $item) => in_array($item->link->getRouteName(), self::ADD_ROUTES, TRUE));
        if ($adds && count($adds) === count($grandchildren)) {
          // An "Add" submenu: its links as buttons.
          foreach ($adds as $add) {
            $buttons[] = $this->button($add);
          }
        }
        elseif (in_array($child->link->getRouteName(), self::ADD_ROUTES, TRUE)) {
          $buttons[] = $this->button($child);
        }
        else {
          $links[] = $this->link($child);
        }
      }
      $cards[$link->getPluginId()] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['menu-cards__card']],
        'title' => ['#type' => 'link', '#title' => $link->getTitle(), '#url' => $link->getUrlObject(), '#prefix' => '<h3 class="menu-cards__title">', '#suffix' => '</h3>'],
        'description' => $link->getDescription() ? ['#markup' => '<p class="menu-cards__description">' . $link->getDescription() . '</p>'] : [],
        'buttons' => $buttons ? ['#type' => 'container', '#attributes' => ['class' => ['menu-cards__buttons']]] + $buttons : [],
        'links' => $links ? ['#theme' => 'item_list', '#items' => $links, '#attributes' => ['class' => ['menu-cards__links']]] : [],
      ];
    }
    return $build;
  }

  /**
   * Returns a link, with its hint ("card_hint" in the options of the menu
   * link: edit, overview) after it.
   */
  private function link(MenuLinkTreeElement $element): array {
    $link = ['#type' => 'link', '#title' => $element->link->getTitle(), '#url' => $element->link->getUrlObject()];
    $hint = $element->link->getOptions()['card_hint'] ?? NULL;
    $hints = ['edit' => $this->t('edit'), 'overview' => $this->t('overview')];
    if (isset($hints[$hint])) {
      $link = ['link' => $link, 'hint' => ['#markup' => ' <span class="menu-cards__hint">(' . $hints[$hint] . ')</span>']];
    }
    return $link;
  }

  /**
   * Returns an "add" link as a button, labelled with what it adds.
   */
  private function button(MenuLinkTreeElement $element): array {
    $parameters = $element->link->getRouteParameters();
    $label = isset($parameters['node_type'])
      ? $this->entityTypeManager->getStorage('node_type')->load($parameters['node_type'])?->label()
      : $this->t('User');
    return [
      '#type' => 'link',
      '#title' => $label ?? $element->link->getTitle(),
      '#url' => $element->link->getUrlObject(),
      '#attributes' => ['class' => ['button', 'button--action', 'button--small']],
    ];
  }

}
