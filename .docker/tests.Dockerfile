# PHP runtime of the official Matomo image + composer, used to run the Matomo test suite
ARG MATOMO_VERSION=5.14
FROM matomo:${MATOMO_VERSION}-apache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends git unzip mariadb-client && rm -rf /var/lib/apt/lists/*
