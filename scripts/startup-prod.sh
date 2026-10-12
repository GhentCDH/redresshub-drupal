#!/bin/sh
# Production startup script: never installs, only updates an existing site.
set -eu

FILES_DIR="/app/web/sites/default/files"

log() {
    printf '\n\033[32m==> %s\033[0m\n' "$*"
}

cd /app

mkdir -p "$FILES_DIR"

if [ "${DRUPAL_RUN_CONFIG_IMPORT:-false}" = true ]; then
    # updatedb + config:import + cache:rebuild + deploy hooks
    log "Running drush deploy"
    drush deploy -y
else
    log "Running database updates"
    drush updatedb -y
    drush deploy:hook -y
    drush cache:rebuild
fi

# drush runs as root: make sure the web server can write generated files
find "$FILES_DIR" ! -user application -exec chown -h application:application {} +

# keep search engines out of non-production sites (e.g. staging)
NOINDEX_CONF=/opt/docker/etc/httpd/conf.d/90-noindex.conf
if [ "${ROBOTS_NOINDEX:-false}" = true ]; then
    echo 'Header always set X-Robots-Tag "noindex, nofollow"' > "$NOINDEX_CONF"
else
    rm -f "$NOINDEX_CONF"
fi

log "Starting web server"
exec supervisord
