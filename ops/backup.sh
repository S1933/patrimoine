#!/usr/bin/env sh
# Patrimoine — PostgreSQL backup script (cron-ready).
# Usage: ./ops/backup.sh
# Env: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, PGPASSWORD
# Outputs: ./backups/patrimoine_YYYYMMDD_HHMMSS.sql.gz (kept 14 days by default)

set -eu

BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-patrimoine}"
DB_USERNAME="${DB_USERNAME:-patrimoine}"

mkdir -p "$BACKUP_DIR"

TS=$(date +%Y%m%d_%H%M%S)
FILE="$BACKUP_DIR/patrimoine_${TS}.sql.gz"

echo "[backup] dumping $DB_DATABASE -> $FILE"
pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" --no-owner --clean --if-exists | gzip > "$FILE"

echo "[backup] pruning files older than ${RETENTION_DAYS} days"
find "$BACKUP_DIR" -name "patrimoine_*.sql.gz" -type f -mtime "+${RETENTION_DAYS}" -delete

echo "[backup] done"
