#!/usr/bin/env bash
#
# staging-down.sh — delete every staging resource. Goes to $0 cost.
# Keeps the careconnect resource group (shared with prod storage).
# Deletes: Web App, App Service Plan, MySQL Flexible Server, .staging-secrets.
#
# Full tear-down. To pause without losing data, use staging-stop.sh instead.

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/staging-env.sh"

require_login

echo "This will DELETE:"
echo "  - Web App:          $STAGING_APP_NAME"
echo "  - App Service Plan: $STAGING_APP_PLAN"
echo "  - MySQL Server:     $STAGING_DB_SERVER (and its DB data)"
echo "  - Local secrets:    $SECRETS_FILE"
echo

if [ "${1:-}" != "--yes" ]; then
  read -r -p "Type 'yes' to confirm (or pass --yes): " CONFIRM
  [ "$CONFIRM" = "yes" ] || { echo "Aborted."; exit 0; }
fi

echo "Deleting Web App..."
az webapp delete -g "$STAGING_RG" -n "$STAGING_APP_NAME" --keep-empty-plan 2>/dev/null || true

echo "Deleting App Service Plan..."
az appservice plan delete -g "$STAGING_RG" -n "$STAGING_APP_PLAN" --yes 2>/dev/null || true

echo "Deleting MySQL Flexible Server..."
az mysql flexible-server delete -g "$STAGING_RG" -n "$STAGING_DB_SERVER" --yes 2>/dev/null || true

rm -f "$SECRETS_FILE"

echo "✅ Staging torn down. Cost: \$0/mo. Re-create with: ${SCRIPT_DIR}/staging-up.sh"
