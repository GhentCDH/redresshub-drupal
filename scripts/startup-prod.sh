#!/bin/sh
# Production startup script: never installs, only updates an existing site.
set -eu

FILES_DIR="/app/web/sites/default/files"

log() {
    printf '\n\033[32m==> %s\033[0m\n' "$*"
}

fail() {
    printf '\n\033[31m==> %s\033[0m\n' "$*" >&2
    exit 1
}

cd /app

# The files directory is a volume: it may be new and empty, or owned by
# someone else.
mkdir -p "$FILES_DIR"
chown application:application "$FILES_DIR"
chmod 775 "$FILES_DIR"
# Never run code from uploaded files (Drupal writes this file too, but only
# once it gets to it).
if [ ! -f "$FILES_DIR/.htaccess" ]; then
    cp /app/web/sites/default/.htaccess "$FILES_DIR/.htaccess"
    chown application:application "$FILES_DIR/.htaccess"
    chmod 644 "$FILES_DIR/.htaccess"
fi

# Without a database drush only says "Bootstrap failed": say why, and wait
# a little for a database that is (re)starting.
for var in DB_HOST DB_NAME DB_USER DB_PASSWORD; do
    eval "value=\${$var:-}"
    [ -n "$value" ] || fail "$var is not set: check the environment of the job (Vault)"
done
log "Waiting for the database at $DB_HOST"
tries=0
until error="$(php -r '
    try {
        $pdo = new PDO(sprintf("mysql:host=%s;port=3306;dbname=%s", getenv("DB_HOST"), getenv("DB_NAME")), getenv("DB_USER"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 5]);
        $tables = $pdo->query("SHOW TABLES LIKE \"config\"")->fetchColumn();
        if (!$tables) { echo "the database is empty: import the dump first"; exit(1); }
    }
    catch (PDOException $e) { echo $e->getMessage(); exit(1); }
' 2>&1)"; do
    tries=$((tries + 1))
    [ "$tries" -lt 12 ] || fail "No database: $error"
    echo "  not yet ($error), retrying in 5 seconds"
    sleep 5
done

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
