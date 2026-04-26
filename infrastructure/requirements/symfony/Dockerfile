# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# Symfony CLI utility image — bootstraps & manages the Symfony project.
# Alpine + prebuilt php83 packages (no compilation).
# ---------------------------------------------------------------------------
FROM alpine:3.20

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache

RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --no-cache \
        bash git curl unzip ca-certificates \
        php83 php83-phar php83-openssl php83-curl php83-mbstring \
        php83-tokenizer php83-ctype php83-iconv php83-session \
        php83-fileinfo php83-dom php83-xml php83-xmlreader \
        php83-xmlwriter php83-simplexml php83-intl php83-zip \
 && ln -sf /usr/bin/php83 /usr/local/bin/php

RUN curl -1sLf https://get.symfony.com/cli/installer | bash \
 && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony \
 && chmod +x /usr/local/bin/symfony

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY conf/init-symfony.sh /usr/local/bin/init-symfony
RUN chmod +x /usr/local/bin/init-symfony

WORKDIR /var/www/html

ENTRYPOINT ["symfony"]
CMD ["--version"]