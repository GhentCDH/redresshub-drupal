#!/bin/sh
# Development startup script. Safe to run on every container start:
# - installs Drupal only when the database is empty (or DRUPAL_FORCE_REINSTALL=true)
# - otherwise only runs database updates (and config import when enabled)
set -eu

FILES_DIR="/app/web/sites/default/files"
IMPORTED_IMG_PATH="$FILES_DIR/imported"
IMPORTED_IMG_SOURCE="/app/initial-content/imported"

log() {
    printf '\n\033[32m==> %s\033[0m\n' "$*"
}

is_true() {
    [ "${1:-false}" = true ]
}

cd /app

# -----------------------------------------------------------------------------
# Composer
# -----------------------------------------------------------------------------
if is_true "${DRUPAL_RUN_COMPOSER:-true}"; then
    if [ -s composer.lock ]; then
        log "Installing composer dependencies"
        composer install --no-interaction
    else
        # a freshly generated project has an empty lock file: resolve versions once
        log "No composer.lock yet, resolving dependencies (commit composer.lock afterwards)"
        composer update --no-interaction
    fi
fi
ln -sf /app/vendor/bin/drush /usr/local/bin/drush

# -----------------------------------------------------------------------------
# Files directory
# -----------------------------------------------------------------------------
mkdir -p "$FILES_DIR"

# link imported images from initial-content, if there are any
if [ -d "$IMPORTED_IMG_SOURCE" ]; then
    if [ "$(readlink "$IMPORTED_IMG_PATH" 2>/dev/null)" != "$IMPORTED_IMG_SOURCE" ]; then
        rm -rf "$IMPORTED_IMG_PATH"
        ln -s "$IMPORTED_IMG_SOURCE" "$IMPORTED_IMG_PATH"
    fi
fi

if [ ! -f "$FILES_DIR/.htaccess" ] && [ -f /app/web/sites/default/.htaccess ]; then
    cp /app/web/sites/default/.htaccess "$FILES_DIR/.htaccess"
fi

# -----------------------------------------------------------------------------
# Wait for services
# -----------------------------------------------------------------------------
if [ -n "${ELASTIC_HOST:-}" ]; then
    log "Waiting for Elasticsearch at $ELASTIC_HOST:${ELASTIC_PORT:-9200}"
    i=0
    until curl -sf "${ELASTIC_SCHEME:-http}://$ELASTIC_HOST:${ELASTIC_PORT:-9200}/_cluster/health" >/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "Elasticsearch is not reachable, continuing without it"
            break
        fi
        sleep 2
    done
fi

# -----------------------------------------------------------------------------
# Install or update Drupal
# -----------------------------------------------------------------------------
site_installed() {
    [ "$(drush status --field=bootstrap 2>/dev/null)" = "Successful" ]
}

has_full_config() {
    [ -f /app/config/sync/core.extension.yml ]
}

has_partial_config() {
    ls /app/config/sync/*.yml >/dev/null 2>&1
}

install_site() {
    if has_full_config; then
        log "Installing Drupal from existing configuration in config/sync"
        drush site:install --existing-config -y \
            --account-name="$DRUPAL_ADMIN_USER" \
            --account-pass="$DRUPAL_ADMIN_PW"
    else
        profile="${DRUPAL_INSTALL_PROFILE:-standard}"
        log "Installing Drupal with profile: $profile"
        drush site:install "$profile" -y \
            --account-name="$DRUPAL_ADMIN_USER" \
            --account-pass="$DRUPAL_ADMIN_PW" \
            --site-name="$DRUPAL_SITE_NAME"

        if [ -n "${DRUPAL_MODULES:-}" ]; then
            log "Enabling modules: $DRUPAL_MODULES"
            # shellcheck disable=SC2086 # word splitting is intended
            drush pm:install -y $DRUPAL_MODULES
        fi

        # Drupal 11 profiles no longer create content types; core ships them as recipes
        for recipe in ${DRUPAL_RECIPES:-}; do
            log "Applying recipe: $recipe"
            drush recipe -y "/app/web/core/recipes/$recipe"
        done

        # the Navigation module disables the Toolbar that admin_toolbar builds on
        case " ${DRUPAL_MODULES:-} " in
            *" admin_toolbar "*)
                if drush pm:list --status=enabled --field=name 2>/dev/null | grep -qx navigation; then
                    log "Uninstalling navigation in favour of admin_toolbar"
                    drush pm:uninstall -y navigation
                fi
                ;;
        esac

        # re-save view displays so they include extra fields added by the new
        # modules (e.g. search_api_excerpt); otherwise the exported config never
        # matches the site after an import
        drush php:eval 'foreach (\Drupal::entityTypeManager()->getStorage("entity_view_display")->loadMultiple() as $display) { $display->save(); }'

        # import the config files shipped with the template (e.g. search server)
        if has_partial_config; then
            log "Importing initial configuration from config/sync"
            drush config:import --partial -y
        fi

        # Varnish only caches pages that Drupal marks as cacheable
        case " ${DRUPAL_MODULES:-} " in
            *" varnish_purger "*)
                log "Setting page cache max-age to 1 hour for Varnish"
                drush config:set -y system.performance cache.page.max_age 3600
                ;;
        esac
    fi
}

if is_true "${DRUPAL_FORCE_REINSTALL:-false}"; then
    log "DRUPAL_FORCE_REINSTALL=true: reinstalling, all content will be lost"
    install_site
elif site_installed; then
    if is_true "${DRUPAL_RUN_CONFIG_IMPORT:-false}"; then
        if has_full_config; then
            log "Running drush deploy"
            drush deploy -y
        else
            echo "DRUPAL_RUN_CONFIG_IMPORT=true but config/sync has no full export (run 'drush config:export' first), skipping"
            drush updatedb -y
        fi
    else
        log "Running database updates"
        drush updatedb -y
    fi
else
    install_site
fi

drush cache:rebuild

# drush runs as root: make sure the web server (and the host user) own generated files
find /app/web/sites/default /app/config ! -user application -exec chown -h application:application {} +

# keep search engines out of non-production sites
NOINDEX_CONF=/opt/docker/etc/httpd/conf.d/90-noindex.conf
if is_true "${ROBOTS_NOINDEX:-false}"; then
    echo 'Header always set X-Robots-Tag "noindex, nofollow"' > "$NOINDEX_CONF"
else
    rm -f "$NOINDEX_CONF"
fi

log "Drupal is ready, starting web server"
cat <<EOF

  Drupal   http://localhost:${WEB_PORT:-8080}   (login: $DRUPAL_ADMIN_USER / $DRUPAL_ADMIN_PW)
  Adminer  http://localhost:${ADMINER_PORT:-8081}   (server: db, user: $DB_USER)
EOF
if [ -n "${MAILPIT_PORT:-}" ]; then
    echo "  Mailpit  http://localhost:$MAILPIT_PORT   (all mail sent by Drupal)"
fi
if [ -n "${VARNISH_HOST:-}" ]; then
    echo "  Varnish  http://localhost:${VARNISH_PORT:-8088}   (Drupal behind the Varnish cache)"
fi
cat <<EOF
  MySQL    localhost:${DB_PORT:-3306}

EOF
exec supervisord
