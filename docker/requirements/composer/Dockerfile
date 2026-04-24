# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# Composer — dedicated utility image (run via `docker compose run composer ...`)
# ---------------------------------------------------------------------------
FROM composer:2

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache

RUN apk add --no-cache git unzip bash

COPY conf/composer-config.json /tmp/composer/config.json

WORKDIR /var/www/html

ENTRYPOINT ["composer"]
CMD ["--version"]
