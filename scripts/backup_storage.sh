#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="$ROOT/backups/storage"; mkdir -p "$OUT_DIR"
FILE="$OUT_DIR/storage_$(date +%Y%m%d_%H%M%S).tar.gz"
tar --exclude="$ROOT/backups" -czf "$FILE" -C "$ROOT" storage uploads
 echo "Backup storage dibuat: $FILE"
