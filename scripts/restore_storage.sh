#!/usr/bin/env bash
set -euo pipefail
if [ $# -lt 1 ]; then echo "Usage: bash scripts/restore_storage.sh storage.tar.gz"; exit 1; fi
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
tar -xzf "$1" -C "$ROOT"
echo "Restore storage selesai."
