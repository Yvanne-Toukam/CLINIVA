#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "$SCRIPT_DIR/../.." && pwd)"
PHP_CLI_BIN="${PHP_CLI_BIN:-php}"

exec "$PHP_CLI_BIN" "$PROJECT_ROOT/bin/backup_database.php"