<?php

$databases = [];

/**
 * Location of the site configuration files.
 */
$settings['config_sync_directory'] = '../config/sync';

/**
 * Salt for one-time login links, cancel links, form tokens, etc.
 */
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'b4cih_Br6hrYFWn73_P5cMVIs__UnTn4vf9WhtJxeLrfvnr9L-vf9OnPw1HcAkxnnrxCM7cajA';

/**
 * Access control for update.php script.
 */
$settings['update_free_access'] = FALSE;

/**
 * Default mode for directories and files written by Drupal.
 * Value should be in PHP Octal Notation, with leading zero.
 */
$settings['file_chmod_directory'] = 0775;
$settings['file_chmod_file'] = 0664;

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

/**
 * The default list of directories that will be ignored by Drupal's file API.
 */
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

/**
 * The default number of entities to update in a batch process.
 * Decrease for lower memory consumption, increase for faster batch processing.
 */
$settings['entity_update_batch_size'] = 50;

/**
 * Entity update backup.
 *
 * This is used to inform the entity storage handler that the backup tables as
 * well as the original entity type and field storage definitions should be
 * retained after a successful entity update process.
 */
$settings['entity_update_backup'] = TRUE;

/**
 * State caching.
 *
 * State caching uses the cache collector pattern to cache all requested keys
 * from the state API in a single cache entry, which can greatly reduce the
 * amount of database queries. However, some sites may use state with a
 * lot of dynamic keys which could result in a very large cache.
 */
$settings['state_cache'] = TRUE;

/**
 * Public file base URL
 */
$settings['file_public_path'] = 'sites/default/files';

/**
 * Optimized assets path:
 */
$settings['file_assets_path'] = 'sites/default/files';

/**
 * Node migration type.
 *
 * This is used to force the migration system to use the classic node migrations
 * instead of the default complete node migrations. The migration system will
 * use the classic node migration only if there are existing migrate_map tables
 * for the classic node migrations and they contain data. These tables may not
 * exist if you are developing custom migrations and do not want to use the
 * complete node migrations. Set this to TRUE to force the use of the classic
 * node migrations.
 */
$settings['migrate_node_migrate_type_classic'] = FALSE;

$settings['locale_translation_auto_update'] = FALSE;

$settings['config_exclude_modules'] = ['devel', 'devel_generate'];

// DOMAIN is a comma separated list of host names, e.g. "localhost,127.0.0.1".
$settings['trusted_host_patterns'] = array_map(
  fn($domain) => '^' . preg_quote(trim($domain), '/') . '$',
  explode(',', getenv('DOMAIN') ?: 'localhost')
);

$config['system.site']['name'] = getenv('DRUPAL_SITE_NAME');

/**
 * Database connection settings.
 */
$databases['default']['default'] = [
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASSWORD'),
  'host' => getenv('DB_HOST'),
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'init_commands' => [
    'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
  ],
];

/**
 * Cache configuration.
 *
 * MEMCACHE_HOST points the memcache module to the memcached server. Memcache
 * is only used as the default cache backend when MEMCACHE_DISABLE is not
 * "true"; otherwise Drupal's database cache is used.
 */
if (getenv('MEMCACHE_HOST')) {
  $settings['memcache']['key_prefix'] = getenv('MEMCACHE_KEY_PREFIX') ?: '';
  $settings['memcache']['servers'] = [
    getenv('MEMCACHE_HOST') . ':' . (getenv('MEMCACHE_PORT') ?: '11211') => 'default',
  ];
  if (!filter_var(getenv('MEMCACHE_DISABLE'), FILTER_VALIDATE_BOOLEAN)) {
    $settings['cache']['default'] = 'cache.backend.memcache';
  }
}

/**
 * Elasticsearch configuration.
 */
if (getenv('ELASTIC_HOST')) {
  $elasticsearch_url = sprintf('%s://%s:%s',
    getenv('ELASTIC_SCHEME') ?: 'http',
    getenv('ELASTIC_HOST'),
    getenv('ELASTIC_PORT') ?: '9200'
  );
  $config['search_api.server.elasticsearchconnector']['backend_config']['connector_config']['url'] = $elasticsearch_url;

  if (getenv('ELASTIC_AUTH_USER') && getenv('ELASTIC_AUTH_PASSWORD')) {
    $config['search_api.server.elasticsearchconnector']['backend_config']['connector'] = 'basicauth';
    $config['search_api.server.elasticsearchconnector']['backend_config']['connector_config']['username'] = getenv('ELASTIC_AUTH_USER');
    $config['search_api.server.elasticsearchconnector']['backend_config']['connector_config']['password'] = getenv('ELASTIC_AUTH_PASSWORD');
  }
}


/**
 * Varnish configuration: where the purger sends its BAN requests, and trust
 * the X-Forwarded-For header Varnish sets.
 */
if (getenv('VARNISH_HOST')) {
  $config['varnish_purger.settings.varnish']['hostname'] = getenv('VARNISH_HOST');
  $config['varnish_purger.settings.varnish']['port'] = (int) (getenv('VARNISH_PURGE_PORT') ?: 80);
  // headers[1] is the X-VC-Purge-Key header
  $config['varnish_purger.settings.varnish']['headers'][1]['value'] = getenv('VARNISH_PURGE_KEY') ?: '';
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = array_filter([gethostbyname(getenv('VARNISH_HOST'))]);
}

/**
 * Reverse proxy configuration
 *
 * Since the application is behind a reverse proxy
 */
/* $settings['reverse_proxy'] = TRUE; */
/* $settings['reverse_proxy_addresses'] = [ */
/*   '172.18.56.0/24', */
/* ]; */

$settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO | \Symfony\Component\HttpFoundation\Request::HEADER_FORWARDED;

// Load development settings (Twig debug, no caches) when enabled.
if (filter_var(getenv('DRUPAL_DEV_SETTINGS'), FILTER_VALIDATE_BOOLEAN)) {
  include $app_root . '/' . $site_path . '/settings.dev.php';
}

// Load local settings if available.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
