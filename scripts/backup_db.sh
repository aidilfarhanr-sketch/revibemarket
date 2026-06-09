#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/.env" 2>/dev/null || true
DB_HOST="${DB_HOST:-localhost}"; DB_PORT="${DB_PORT:-3306}"; DB_NAME="${DB_NAME:-revibe_market}"; DB_USER="${DB_USER:-root}"; DB_PASS="${DB_PASS:-}"
OUT_DIR="$ROOT/backups/database"; mkdir -p "$OUT_DIR"
FILE="$OUT_DIR/${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql"
if [ -n "$DB_PASS" ]; then PASS="-p$DB_PASS"; else PASS=""; fi
mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" $PASS --single-transaction --routines --triggers "$DB_NAME" > "$FILE"
gzip -f "$FILE"
echo "Backup DB dibuat: $FILE.gz"
