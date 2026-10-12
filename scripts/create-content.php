<?php

/**
 * @file
 * Creates the RedressHub pages, menus and front page from initial-content/.
 *
 * Run with: just drush php:script /app/scripts/create-content.php
 *
 * Safe to run again: existing pages (found by their path alias) get the body
 * from initial-content/pages/<alias>.html again, so edits made in Drupal to
 * those pages are overwritten, and all links in the main and footer menus are
 * replaced.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;

$source = '/app/initial-content';

// Images used in the page bodies.
$file_system = \Drupal::service('file_system');
$directory = 'public://uncharted';
$file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
foreach (glob("$source/images/uncharted-*.jpg") as $image) {
  $file_system->copy($image, $directory . '/' . basename($image), FileExists::Replace);
}

/**
 * Loads the node with the given alias, or creates a new page.
 */
$load_or_create = function (string $alias, string $title): Node {
  $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias);
  if (preg_match('#^/node/(\d+)$#', $path, $matches) && ($node = Node::load($matches[1]))) {
    return $node;
  }
  return Node::create(['type' => 'page', 'title' => $title, 'uid' => 1]);
};

// Front page: the map itself lives in page--front.html.twig.
$front = $load_or_create('/map', 'Map');
$front->set('path', ['alias' => '/map']);
$front->save();
\Drupal::configFactory()->getEditable('system.site')
  ->set('page.front', '/node/' . $front->id())
  ->set('name', 'RedressHub')
  ->save();

$pages = [
  'about' => 'About',
  'examples' => 'Examples',
  'foundations' => 'Foundations with care',
  'uncharted' => 'Uncharted',
  'moments-ahead' => 'Moments ahead',
  'contact' => 'Contact',
];
$menu_titles = ['foundations' => 'Foundations'];

$nodes = [];
foreach ($pages as $alias => $title) {
  $node = $load_or_create("/$alias", $title);
  $node->set('title', $title);
  $node->set('body', [
    'value' => file_get_contents("$source/pages/$alias.html"),
    'format' => 'full_html',
  ]);
  $node->set('path', ['alias' => "/$alias"]);
  $node->setPublished();
  $node->save();
  $nodes[$alias] = $node;
  echo "Saved /$alias (node {$node->id()})\n";
}

// Menus: remove the links this script made before, then add them again.
$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$old = $storage->loadByProperties(['menu_name' => ['main', 'footer']]);
$storage->delete($old);

// The standard profile's "Home" link duplicates "Map".
\Drupal::service('plugin.manager.menu.link')->updateDefinition('standard.front_page', ['enabled' => FALSE]);

$weight = 0;
MenuLinkContent::create([
  'title' => 'Map',
  'link' => ['uri' => 'internal:/'],
  'menu_name' => 'main',
  'weight' => $weight++,
])->save();
foreach ($nodes as $alias => $node) {
  MenuLinkContent::create([
    'title' => $menu_titles[$alias] ?? $node->label(),
    'link' => ['uri' => 'entity:node/' . $node->id()],
    'menu_name' => 'main',
    'weight' => $weight++,
  ])->save();
}

$footer = [
  'Terms of use' => 'internal:/about#terms-of-use',
  // No pages yet for these two.
  'Privacy' => 'route:<nolink>',
  'Accessibility' => 'route:<nolink>',
];
$weight = 0;
foreach ($footer as $title => $uri) {
  MenuLinkContent::create([
    'title' => $title,
    'link' => ['uri' => $uri],
    'menu_name' => 'footer',
    'weight' => $weight++,
  ])->save();
}

echo "Done. Front page: /node/{$front->id()}\n";
