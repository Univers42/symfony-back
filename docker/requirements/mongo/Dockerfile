# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# MongoDB 7 — document datastore (auth enabled)
# ---------------------------------------------------------------------------
FROM mongo:7

COPY conf/mongod.conf /etc/mongod.conf

HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=10 \
    CMD mongosh --quiet --eval "db.adminCommand({ ping: 1 }).ok" | grep -q 1 || exit 1

EXPOSE 27017

CMD ["mongod", "--config", "/etc/mongod.conf"]
