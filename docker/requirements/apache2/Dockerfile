# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# Apache 2.4 (Alpine) — front web server, proxies PHP to php-fpm via FastCGI
# ---------------------------------------------------------------------------
FROM httpd:2.4-alpine

RUN apk add --no-cache bash curl tzdata \
    && rm -rf /var/cache/apk/*

# Drop-in configuration
COPY conf/httpd.conf       /usr/local/apache2/conf/httpd.conf
COPY conf/symfony.conf     /usr/local/apache2/conf/extra/symfony.conf

# Document root will be populated via shared volume
RUN mkdir -p /var/www/html/public \
    && chown -R www-data:www-data /var/www/html 2>/dev/null || true

HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
    CMD curl -fsS http://127.0.0.1/server-status?auto >/dev/null || exit 1

EXPOSE 80
CMD ["httpd-foreground"]
