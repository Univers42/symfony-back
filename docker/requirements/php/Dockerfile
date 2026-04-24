# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# PHP-FPM 8.3 — Symfony runtime
# Base: alpine:3.20 with PREBUILT php83 packages from Alpine community repo.
# No on-host compilation = full image builds in ~15s on a warm cache.
# ---------------------------------------------------------------------------
FROM alpine:3.20 AS base

ARG APP_UID=1000
ARG APP_GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    PHP_INI_DIR=/etc/php83

RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --no-cache \
        bash git curl unzip ca-certificates tzdata acl shadow fcgi \
        postgresql16-client \
        php83 php83-fpm php83-phar php83-openssl php83-curl \
        php83-mbstring php83-tokenizer php83-ctype php83-iconv \
        php83-session php83-fileinfo php83-dom php83-xml php83-xmlreader \
        php83-xmlwriter php83-simplexml php83-pdo php83-pdo_pgsql php83-pgsql \
        php83-intl php83-zip php83-bcmath php83-sodium php83-sysvsem \
        php83-pecl-apcu php83-opcache php83-pecl-mongodb \
 && mkdir -p /usr/local/sbin \
 && ln -sf /usr/bin/php83 /usr/local/bin/php \
 && ln -sf /usr/sbin/php-fpm83 /usr/local/sbin/php-fpm

# Composer (single static phar, copied from official image)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Symfony CLI (single static binary)
RUN curl -1sLf https://get.symfony.com/cli/installer | bash \
 && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony \
 && chmod +x /usr/local/bin/symfony

# Application configuration
COPY conf/php.ini      ${PHP_INI_DIR}/conf.d/zz-app.ini
COPY conf/opcache.ini  ${PHP_INI_DIR}/conf.d/zz-opcache.ini
COPY conf/www.conf     ${PHP_INI_DIR}/php-fpm.d/zz-www.conf

# www-data user aligned with host UID/GID for bind-mount writes
RUN set -eux; \
    if getent group www-data >/dev/null; then groupmod -g ${APP_GID} www-data; \
    else addgroup -g ${APP_GID} -S www-data; fi; \
    if getent passwd www-data >/dev/null; then usermod -u ${APP_UID} -g ${APP_GID} www-data; \
    else adduser -S -D -H -u ${APP_UID} -G www-data www-data; fi; \
    mkdir -p /var/www/html /var/log/php83 /run/php; \
    chown -R www-data:www-data /var/www/html /var/log/php83 /run/php

WORKDIR /var/www/html

HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F", "-O"]