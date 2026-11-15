# Generic PHP toolchain for Laravel-style apps. Trusted official bases only; no repository code.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip sqlite3 ca-certificates libicu-dev libzip-dev libsqlite3-dev libpng-dev libonig-dev \
  && docker-php-ext-install intl zip bcmath pdo_sqlite gd pcntl exif \
  && apt-get clean && find /var/lib/apt/lists -type f -delete
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN useradd -u 10001 -m -d /home/runner runner && mkdir -p /work /wt && chown runner:runner /work /wt
COPY proto /opt/wt/proto
ENV HOME=/tmp/home COMPOSER_HOME=/tmp/home/.composer
USER 10001
WORKDIR /work
LABEL wt.proxyrun="1"
