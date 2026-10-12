ARG PHP_VERSION
# =============================================================================
# Base Stage - Common dependencies and setup
# =============================================================================
FROM webdevops/php-apache:${PHP_VERSION} AS base

# Set apache document root and index file
ENV WEB_DOCUMENT_ROOT="/app/web"
ENV WEB_DOCUMENT_INDEX="index.php"

# Install common tools needed for Drupal
# CUSTOMIZATION: Add any additional packages your project needs here
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    mariadb-client \
    vim \
    wget \
    curl \
    nano \
    libmemcached-dev \
    iputils-ping \
    memcached \
    libmemcached-tools \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

RUN mkdir -p /app/web/sites/default/files
RUN chown -R application:application /app/web/sites/default/files
RUN chmod -R 775 /app/web/sites/default/files

# =============================================================================
# Development Stage
# =============================================================================

FROM base AS development

COPY scripts/startup-dev.sh /startup.sh
RUN chmod +x /startup.sh

EXPOSE 80

CMD ["/startup.sh"]

# =============================================================================
# Production Stage
# =============================================================================

FROM base AS production

# Copy application files
COPY --chown=application:application composer.json composer.lock /app/
COPY --chown=application:application ./web /app/web
COPY --chown=application:application ./config /app/config
COPY --chown=application:application ./assets /app/assets

RUN composer install --no-interaction --no-dev --optimize-autoloader
RUN ln -sf /app/vendor/bin/drush /usr/local/bin/drush

COPY scripts/startup-prod.sh /startup.sh
RUN chmod +x /startup.sh

EXPOSE 80

CMD ["/startup.sh"]
