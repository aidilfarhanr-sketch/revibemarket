#!/usr/bin/env bash
set -euo pipefail
if [ $# -lt 2 ]; then echo "Usage: bash scripts/restore_full.sh database.sql.gz storage.tar.gz"; exit 1; fi
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
echo "[restore] restoring database and storage. Pastikan app dalam maintenance mode dulu."
bash "$ROOT/scripts/restore_db.sh" "$1"
bash "$ROOT/scripts/restore_storage.sh" "$2"
echo "[restore] complete. Jalankan php scripts/run_migrations.php setelah restore jika perlu."
