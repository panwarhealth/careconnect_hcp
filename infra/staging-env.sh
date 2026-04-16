#!/usr/bin/env bash
# Shared variables for every staging-*.sh script. Source this first.
set -euo pipefail

export STAGING_RG="careconnect"
export STAGING_LOCATION="australiasoutheast"
export STAGING_DB_SERVER="hcp-staging-db"
export STAGING_DB_NAME="hcp_care"
export STAGING_DB_ADMIN="hcpadmin"
export STAGING_APP_PLAN="hcp-staging-plan"
export STAGING_APP_NAME="hcp-staging-app"
export STAGING_APP_SKU="B1"
export STAGING_DB_SKU="Standard_B1ms"
export STAGING_TABLE_PREFIX="tbstwp_"

export STAGING_URL="https://${STAGING_APP_NAME}.azurewebsites.net"

# Admin-password shared by Rob-Panwar and Panwar-education after seed.
# Change before running if desired.
export STAGING_ADMIN_PASS="staging123"

export INFRA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export SECRETS_FILE="${INFRA_DIR}/.staging-secrets"

require_tools() {
  for t in az openssl mysql zip curl sshpass; do
    command -v "$t" >/dev/null 2>&1 || { echo "ERROR: $t not installed"; exit 1; }
  done
}

require_login() {
  az account show >/dev/null 2>&1 || {
    echo "ERROR: not logged into az. Run: az login --scope https://management.core.windows.net//.default"
    exit 1
  }
}
