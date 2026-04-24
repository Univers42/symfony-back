# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# MySQL 8.0 — pinned, with custom configuration & UTF-8 by default
# ---------------------------------------------------------------------------
FROM mysql:8.0

COPY conf/my.cnf /etc/mysql/conf.d/zz-app.cnf

# Ensure the file is readable by mysqld (root in the official image)
RUN chmod 0644 /etc/mysql/conf.d/zz-app.cnf

HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=10 \
    CMD mysqladmin ping -h 127.0.0.1 -uroot -p"$(cat /run/secrets/mysql_root_password 2>/dev/null || echo "$MYSQL_ROOT_PASSWORD")" --silent || exit 1

EXPOSE 3306
