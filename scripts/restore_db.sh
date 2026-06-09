#!/usr/bin/env bash
set -euo pipefail
if [ $# -lt 1 ]; then echo "Usage: bash scripts/restore_db.sh backup.sql[.gz]"; exit 1; fi
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; source "$ROOT/.env" 2>/dev/null || true
DB_HOST="${DB_HOST:-localhost}"; DB_PORT="${DB_PORT:-3306}"; DB_NAME="${DB_NAME:-revibe_market}"; DB_USER="${DB_USER:-root}"; DB_PASS="${DB_PASS:-}"
if [ -n "$DB_PASS" ]; then PASS="-p$DB_PASS"; else PASS=""; fi
FILE="$1"
if [[ "$FILE" == *.gz ]]; then gunzip -c "$FILE" | mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" $PASS "$DB_NAME"; else mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" $PASS "$DB_NAME" < "$FILE"; fi
 echo "Restore DB selesai."
