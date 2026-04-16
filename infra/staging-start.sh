#!/usr/bin/env bash
#
# staging-start.sh — resume staging after staging-stop.sh paused it.
# MySQL start takes ~2 min. Web App is ~30 seconds.

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/staging-env.sh"

require_login

echo "Starting MySQL Flexible Server (takes ~2 min)..."
az mysql flexible-server start -g "$STAGING_RG" -n "$STAGING_DB_SERVER" -o none

echo "Starting Web App..."
az webapp start -g "$STAGING_RG" -n "$STAGING_APP_NAME" -o none

echo "Warming up..."
sleep 20
curl -sL -o /dev/null -w "Homepage: HTTP %{http_code}\n" "${STAGING_URL}/"

echo "✅ Staging running: ${STAGING_URL}"
