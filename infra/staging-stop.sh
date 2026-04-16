#!/usr/bin/env bash
#
# staging-stop.sh — pause compute to minimise cost between test sessions.
# Storage (DB disk, App Service artefacts) persists. Restart with staging-start.sh.
# Expected idle cost: ~$1/day (down from ~$28/mo running).

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/staging-env.sh"

require_login

echo "Stopping MySQL Flexible Server..."
az mysql flexible-server stop -g "$STAGING_RG" -n "$STAGING_DB_SERVER" -o none 2>&1 | tail -3 || true

echo "Stopping Web App..."
az webapp stop -g "$STAGING_RG" -n "$STAGING_APP_NAME" -o none 2>&1 | tail -3 || true

echo "✅ Staging paused. Restart with: ${SCRIPT_DIR}/staging-start.sh"
