# syntax=docker/dockerfile:1
#
# The PHP image: php-fpm for the web, the same image for artisan and Composer.
#
#   target: base   extensions, Composer, the app user. The local stack stops
#                  here and bind mounts apps/api, so an edit is live.
#
# A staging or production image would add a stage after base that copies the
# application in and runs `composer install --no-dev`.

ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-fpm-alpine AS base

# Match the host user, so files written into the bind mount are owned by you.
ARG UID=1000
ARG GID=1000

# The first group stays in the image and .build-deps is deleted at the end of
# the layer. A shared library an extension loads at run time must be in the
# first group, or php fails at start with "Error loading shared library".
RUN set -eux; \
    apk add --no-cache \
        git \
        icu-data-full \
        icu-libs \
        libzip \
        unzip; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pdo_mysql \
        zip; \
    apk del .build-deps; \
    rm -rf /tmp/pear /usr/src/php.tar.xz*

# opcache is not installed here: the 8.5 image compiles it in, and
# conf.d/zz-local.ini only tunes it.

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    addgroup -g ${GID} app; \
    adduser -D -u ${UID} -G app app

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm", "-F"]
