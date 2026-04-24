# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# PostgreSQL 16 (Alpine) — primary relational datastore
# ---------------------------------------------------------------------------
FROM postgres:16-alpine

COPY conf/postgresql.conf /etc/postgresql/postgresql.conf

# Pass the custom config via the official entrypoint
ENV POSTGRES_INITDB_ARGS="--encoding=UTF-8 --locale=C"

HEALTHCHECK --interval=10s --timeout=5s --start-period=15s --retries=10 \
    CMD pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB" -h 127.0.0.1 || exit 1

EXPOSE 5432

# Use our config file. The official entrypoint forwards CMD as args to postgres.
CMD ["postgres", "-c", "config_file=/etc/postgresql/postgresql.conf"]
