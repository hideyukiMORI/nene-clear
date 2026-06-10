#!/bin/sh
# Container entrypoint: apply DB schema, then serve SPA + API on :8384.
set -e

# The DB container is gated by compose depends_on: service_healthy, but keep a
# short retry so a slow first-boot (volume init) doesn't race the migration.
echo "Waiting for database (${DB_ADAPTER:-sqlite} @ ${DB_HOST:-127.0.0.1}:${DB_PORT:-})..."
i=0
until composer migrations:migrate; do
    i=$((i + 1))
    if [ "$i" -ge 20 ]; then
        echo "Migrations failed after $i attempts — aborting." >&2
        exit 1
    fi
    echo "  migrate attempt $i failed; retrying in 3s..."
    sleep 3
done

echo "Starting PHP server on :8384"
exec php -S 0.0.0.0:8384 -t public_html/
