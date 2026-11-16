# Matomo 6 dev images, built from a git tag because no official matomo:6 image exists yet.
# TODO: switch the `matomo` service back to the official image (matomo:6-apache) once it is published.
ARG PHP_VERSION=8.4

# --- PHP + Apache runtime with the extensions Matomo needs -------------------------------
FROM php:${PHP_VERSION}-apache AS base
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip mariadb-client \
      libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" bcmath gd mysqli opcache pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite \
 && { echo 'memory_limit=512M'; echo 'max_execution_time=0'; } > /usr/local/etc/php/conf.d/matomo.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Test runner: `make test` mounts a Matomo git checkout at /matomo ---------------------
FROM base AS tests

# --- Matomo web app for `make up` ---------------------------------------------------------
FROM base AS matomo
ARG MATOMO_VERSION=6.0.0-rc1
RUN git clone --depth 1 --branch "${MATOMO_VERSION}" https://github.com/matomo-org/matomo.git /usr/src/matomo \
 && cd /usr/src/matomo \
 && composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
 && rm -rf .git
COPY matomo-entrypoint.sh /usr/local/bin/matomo-entrypoint.sh
ENTRYPOINT ["/bin/sh", "/usr/local/bin/matomo-entrypoint.sh"]
CMD ["apache2-foreground"]
