#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/.env"
[ -f "$ENV_FILE" ] && set -a && . "$ENV_FILE" && set +a
BACKUP_DIR="${BACKUP_DIR:-$ROOT/backups}"
STAMP="$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
echo "[backup] starting full backup $STAMP"
bash "$ROOT/scripts/backup_db.sh"
bash "$ROOT/scripts/backup_storage.sh"
find "$BACKUP_DIR" -type f -mtime +"${BACKUP_RETENTION_DAYS:-14}" -delete || true
if [ "${BACKUP_OFFSITE_DRIVER:-none}" != "none" ]; then
  echo "[backup] offsite driver ${BACKUP_OFFSITE_DRIVER} configured. Upload using your S3/R2 CLI credentials outside this script, or wire aws/s3cmd in cron. Secrets are not printed."
fi
echo "[backup] complete"
