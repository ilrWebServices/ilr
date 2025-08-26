# This is a 'multi-stage' build. First, the caddy server is built with the
# `superviser` plugin using the official caddy xcaddy builder image. Then the
# binary is copied to the official php fpm alpine image.
# https://hub.docker.com/_/caddy/tags?name=builder-alpine
FROM docker.io/caddy:2.10.2-builder-alpine AS builder

RUN xcaddy build \
    --with github.com/baldinof/caddy-supervisor

# https://hub.docker.com/_/php/tags?page=1&name=fpm-alpine
FROM docker.io/php:8.3.24-fpm-alpine3.22

COPY --from=builder /usr/bin/caddy /usr/bin/caddy

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
  && apk add --update --no-cache libjpeg-turbo-dev libpng-dev imagemagick imagemagick-dev zlib-dev patch \
  && docker-php-ext-configure gd --with-jpeg \
  && docker-php-ext-install opcache gd pdo_mysql \
  && apk add --update --no-cache --virtual .build-dependencies $PHPIZE_DEPS \
  && pecl install apcu-5.1.26 \
  && docker-php-ext-enable apcu \
  && pecl install imagick-3.8.0 \
  && docker-php-ext-enable imagick \
  && pecl clear-cache \
  && apk del .build-dependencies

COPY config/Caddyfile /etc/Caddyfile

COPY config/zz-www.conf /usr/local/etc/php-fpm.d/zz-www.conf

RUN addgroup -g 1000 -S www && adduser -u 1000 -D -S -G www www

WORKDIR /app

RUN mkdir -p /run/php-fpm && chown -R www:www /app /run/php-fpm

# See .containerignore for skipped files and directories.
# COPY --chown=www:www . .

USER www

EXPOSE 8181

CMD ["/usr/bin/caddy", "run", "--config", "/etc/Caddyfile"]