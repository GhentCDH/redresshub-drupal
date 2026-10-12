<?php

/**
 * @file
 * Development settings, loaded by settings.php when DRUPAL_DEV_SETTINGS=true.
 *
 * Never enable these in production: they disable caching and show errors.
 */

// Twig debugging and the null cache backend.
$settings['container_yamls'][] = __DIR__ . '/dev.services.yml';

// Show all errors.
$config['system.logging']['error_level'] = 'verbose';

// No CSS/JS aggregation.
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// No render and page caches, so template changes show up without drush cr.
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

// Keep sites/default writable so settings files can be edited on the host.
$settings['skip_permissions_hardening'] = TRUE;
