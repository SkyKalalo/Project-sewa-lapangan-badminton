#!/bin/sh
set -eu
mkdir -p backups
docker compose exec -T postgres pg_dump -U "$DB_USERNAME" "$DB_DATABASE" | gzip > "backups/gor-smash-$(date +%F-%H%M).sql.gz"
