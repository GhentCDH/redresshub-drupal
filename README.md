# RedressHub Drupal Site

## Getting Started

```sh
docker compose up -d --build
docker logs -f redresshub-drupal
```

On the first start the container:

1. resolves the composer dependencies and writes `composer.lock` (**commit this file**),
2. installs Drupal, because the database is empty, enables the modules listed
   in `DRUPAL_MODULES` and applies the core recipes listed in `DRUPAL_RECIPES`
   (e.g. `page_content_type`, `article_content_type`) in `.env`.

Restarting the container is safe: an installed site is never reinstalled,
only `drush updatedb` runs. The site is online on [localhost:8080](http://localhost:8080)
when the log shows `Drupal is ready`.

### Configuration management

Once the site is set up, export the configuration and commit it:

```sh
just drush config:export -y
```

When `config/sync` contains a full export, a fresh install uses
`drush site:install --existing-config`, so every developer gets the same site.
Set `DRUPAL_RUN_CONFIG_IMPORT=true` to run `drush deploy` (updatedb, config
import, deploy hooks) on every start.

### Starting over

`just rebuild` removes all volumes (database included) and installs a new site.
Alternatively, set `DRUPAL_FORCE_REINSTALL=true` to reinstall on the next start
(set it back to `false` afterwards).

## Services

| Service | URL |
|---|---|
| Drupal | [localhost:8080](http://localhost:8080) |
| Adminer (database UI) | [localhost:8081](http://localhost:8081) |
| Varnish (Drupal behind the cache) | [localhost:8088](http://localhost:8088) |
| MySQL | `localhost:3306` |

To run several projects at the same time, change `WEB_PORT`, `ADMINER_PORT`,
`DB_PORT`, `VARNISH_PORT` in `.env`.

## Varnish

Varnish (`varnish/default.vcl`) caches pages for anonymous visitors. Drupal
itself stays reachable without the cache on port 8080.

When content changes, Drupal queues the affected cache tags; the purge queue is
processed on cron and sends `BAN` requests to Varnish. To process it right away:

```sh
just drush p:queue-work
```

Useful checks: `just drush p:diagnostics` and the `X-Varnish-Cache: HIT/MISS`
response header. In production, point `VARNISH_HOST`/`VARNISH_PURGE_PORT` to
your Varnish server and set `VARNISH_PURGE_KEY` to the key its VCL expects in
the `X-VC-Purge-Key` header.

## Development settings

With `DRUPAL_DEV_SETTINGS=true` (the default in `dev.env`),
`web/sites/default/settings.dev.php` is loaded: Twig debugging is on and the
render and page caches are off, so template changes show up without `drush cr`.
Personal overrides go in `web/sites/default/settings.local.php` (not committed).

`ROBOTS_NOINDEX=true` sends an `X-Robots-Tag: noindex` header, so search engines
skip the site. Extra `robots.txt` rules go in `assets/robots.txt.append`.

## Logging in

The admin user and password for the Drupal site are by default 'admin' and 'admin'.
You can log in at [localhost:8080/user/login](http://localhost:8080/user/login)

## Initializing the Database

If you want to start the services with content in the database,
put the database dump containing the content in `./initdb`.

## Helpful commands

If you have [`just`](https://github.com/casey/just) installed, these are some useful recipes:

- `just drush <drush arguments>`: runs the provided drush command in the container
- `just rebuild`: start from scratch website again (no content if no database dump in `initdb`)
- `just enter`: opens a bash session in the drupal container

## Template

This repository was generated using [this cookiecutter template](https://github.com/ghentcdh/drupal_template)
