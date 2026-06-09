#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
find "$ROOT/backups" -type f -mtime +28 -delete 2>/dev/null || true
 echo "Cleanup backup lama selesai."
