#!/usr/bin/env bash
set -Eeuo pipefail

# PostgreSQL connection settings. Keep dbPass empty to use ~/.pgpass.
dbName="grompilc_test"
dbUser="grompilc_test_app"
dbPass="${GROMPIL_DB_PASS:-}"
dbHost="127.0.0.1"
dbPort="5432"
sslMode="disable"

# Change this directory if you want backups stored elsewhere.
backupDir="$HOME/pg_backups"

# Keep newly created backup folders/files private to this user.
umask 077

if ! command -v pg_dump >/dev/null 2>&1; then
    echo "ERROR: pg_dump was not found in PATH." >&2
    exit 1
fi

if [[ ! -d "$backupDir" ]]; then
    mkdir -p -- "$backupDir"
fi

timeStamp="$(date '+%Y%m%d_%H%M%S')"
fileNameOut="$backupDir/${dbName}_${timeStamp}.dump"

# Optional password override. Prefer ~/.pgpass over an environment password.
if [[ -n "$dbPass" ]]; then
    export PGPASSWORD="$dbPass"
fi
export PGSSLMODE="$sslMode"

echo "Creating PostgreSQL backup: $fileNameOut"
if ! pg_dump --host="$dbHost" --port="$dbPort" \
    --username="$dbUser" --dbname="$dbName" \
    --format=custom --file="$fileNameOut"; then
    rm -f -- "$fileNameOut"
    echo "ERROR: pg_dump failed." >&2
    exit 1
fi

echo "Backup completed successfully: $fileNameOut"
