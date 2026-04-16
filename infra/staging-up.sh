#!/usr/bin/env bash
#
# staging-up.sh — provision the entire staging environment from scratch.
# Idempotent: safe to re-run; existing resources will be reused.
# Total runtime ~15 min (MySQL provisioning dominates).
#
# Prereqs:
#   az login --scope https://management.core.windows.net//.default
#   Microsoft.DBforMySQL provider registered (one-time; automated below if not)
#
# Output:
#   - Resources live in the `careconnect` resource group
#   - MySQL admin password written to infra/.staging-secrets (gitignored)
#   - Staging URL printed at the end

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/staging-env.sh"

require_tools
require_login

echo "[1/9] Ensure MySQL resource provider registered..."
STATE=$(az provider show --namespace Microsoft.DBforMySQL --query registrationState -o tsv)
if [ "$STATE" != "Registered" ]; then
  az provider register --namespace Microsoft.DBforMySQL --wait
fi

echo "[2/9] Generate MySQL admin password and persist..."
if [ ! -f "$SECRETS_FILE" ]; then
  MYSQL_PASS="$(openssl rand -base64 16 | tr -d '/+=' | head -c 20)Az1!"
  echo "MYSQL_PASS=${MYSQL_PASS}" > "$SECRETS_FILE"
  chmod 600 "$SECRETS_FILE"
fi
# shellcheck source=/dev/null
source "$SECRETS_FILE"

echo "[3/9] Create MySQL Flexible Server (B1ms, 20GB)..."
if ! az mysql flexible-server show -g "$STAGING_RG" -n "$STAGING_DB_SERVER" >/dev/null 2>&1; then
  az mysql flexible-server create \
    --resource-group "$STAGING_RG" \
    --name "$STAGING_DB_SERVER" \
    --location "$STAGING_LOCATION" \
    --admin-user "$STAGING_DB_ADMIN" \
    --admin-password "$MYSQL_PASS" \
    --sku-name "$STAGING_DB_SKU" \
    --tier Burstable \
    --storage-size 20 \
    --version 8.0.21 \
    --public-access 0.0.0.0 \
    --yes \
    -o none
else
  echo "  ... already exists, reusing"
  az mysql flexible-server start -g "$STAGING_RG" -n "$STAGING_DB_SERVER" -o none 2>/dev/null || true
fi

echo "[4/9] Create DB + allow Azure services + current IP..."
az mysql flexible-server db create -g "$STAGING_RG" -s "$STAGING_DB_SERVER" -d "$STAGING_DB_NAME" -o none 2>/dev/null || true
az mysql flexible-server parameter set -g "$STAGING_RG" -s "$STAGING_DB_SERVER" --name require_secure_transport --value OFF -o none 2>/dev/null || true
az mysql flexible-server firewall-rule create -g "$STAGING_RG" -n "$STAGING_DB_SERVER" --rule-name AllowAzureServices --start-ip-address 0.0.0.0 --end-ip-address 0.0.0.0 -o none 2>/dev/null || true
MY_IP=$(curl -s ifconfig.me)
az mysql flexible-server firewall-rule create -g "$STAGING_RG" -n "$STAGING_DB_SERVER" --rule-name "AllowCurrentIP" --start-ip-address "$MY_IP" --end-ip-address "$MY_IP" -o none 2>/dev/null || true

echo "[5/9] Create App Service Plan + Web App..."
az appservice plan show -g "$STAGING_RG" -n "$STAGING_APP_PLAN" >/dev/null 2>&1 || \
  az appservice plan create -g "$STAGING_RG" -n "$STAGING_APP_PLAN" -l "$STAGING_LOCATION" --sku "$STAGING_APP_SKU" --is-linux -o none
az webapp show -g "$STAGING_RG" -n "$STAGING_APP_NAME" >/dev/null 2>&1 || \
  az webapp create -g "$STAGING_RG" --plan "$STAGING_APP_PLAN" -n "$STAGING_APP_NAME" --runtime "PHP|8.3" -o none

echo "[6/9] Configure App Service env vars..."
ENV_FILE="${SCRIPT_DIR}/../.env"
[ -f "$ENV_FILE" ] || { echo "ERROR: $ENV_FILE not found — needed for AHPRA_PIEWS_* values"; exit 1; }
AHPRA_USER=$(grep -E '^AHPRA_PIEWS_USER='     "$ENV_FILE" | cut -d= -f2-)
AHPRA_PASS=$(grep -E '^AHPRA_PIEWS_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)
[ -n "$AHPRA_USER" ] && [ -n "$AHPRA_PASS" ] || { echo "ERROR: AHPRA_PIEWS_USER / AHPRA_PIEWS_PASSWORD missing from $ENV_FILE"; exit 1; }

az webapp config appsettings set -g "$STAGING_RG" -n "$STAGING_APP_NAME" --settings \
  WORDPRESS_DB_NAME="$STAGING_DB_NAME" \
  WORDPRESS_DB_USER="$STAGING_DB_ADMIN" \
  "WORDPRESS_DB_PASSWORD=$MYSQL_PASS" \
  WORDPRESS_DB_HOST="${STAGING_DB_SERVER}.mysql.database.azure.com" \
  WORDPRESS_TABLE_PREFIX="$STAGING_TABLE_PREFIX" \
  WORDPRESS_DEBUG=1 \
  WORDPRESS_DEBUG_DISPLAY=0 \
  WORDPRESS_SCRIPT_DEBUG=0 \
  "AHPRA_PIEWS_USER=$AHPRA_USER" \
  "AHPRA_PIEWS_PASSWORD=$AHPRA_PASS" \
  WORDPRESS_AUTH_KEY=$(openssl rand -hex 32) \
  WORDPRESS_SECURE_AUTH_KEY=$(openssl rand -hex 32) \
  WORDPRESS_LOGGED_IN_KEY=$(openssl rand -hex 32) \
  WORDPRESS_NONCE_KEY=$(openssl rand -hex 32) \
  WORDPRESS_AUTH_SALT=$(openssl rand -hex 32) \
  WORDPRESS_SECURE_AUTH_SALT=$(openssl rand -hex 32) \
  WORDPRESS_LOGGED_IN_SALT=$(openssl rand -hex 32) \
  WORDPRESS_NONCE_SALT=$(openssl rand -hex 32) \
  -o none

az webapp config set -g "$STAGING_RG" -n "$STAGING_APP_NAME" --startup-file "/home/site/wwwroot/startup.sh" -o none

echo "[7/9] Build + deploy site zip (nginx-config-aware)..."
ZIP_TMP="$(mktemp -u /tmp/staging-deploy-XXXX).zip"
SITE_DIR="$(cd "${SCRIPT_DIR}/../site" && pwd)"
cp "${SCRIPT_DIR}/staging-default.conf" "${SITE_DIR}/default"
cp "${SCRIPT_DIR}/staging-startup.sh" "${SITE_DIR}/startup.sh"
chmod +x "${SITE_DIR}/startup.sh"
(cd "$SITE_DIR" && zip -qr "$ZIP_TMP" . \
  -x "wp-content/uploads/*" \
  -x "wp-content/cache/*" \
  -x "wp-content/upgrade/*" \
  -x "wp-content/plugins/wp-rocket/*" \
  -x "wp-content/plugins/wordfence/*" \
  -x "wp-content/plugins/object-sync-for-salesforce/*" \
  -x "wp-content/plugins/wp-mail-smtp-pro/*" \
  -x "wp-content/plugins/imagify/*" \
  -x "*.git*" -x "*.DS_Store")
rm -f "${SITE_DIR}/default" "${SITE_DIR}/startup.sh"
az webapp deploy -g "$STAGING_RG" -n "$STAGING_APP_NAME" --src-path "$ZIP_TMP" --type zip --async false -o none
rm -f "$ZIP_TMP"

echo "[8/9] Seed database from local dump + sanitize..."
"${SCRIPT_DIR}/staging-seed.sh"

echo "[9/9] Warm the app (first hit primes nginx config)..."
sleep 15
curl -sL -o /dev/null -w "  Homepage: HTTP %{http_code}\n" "${STAGING_URL}/"

cat <<EOF

✅ Staging ready:
   URL:         ${STAGING_URL}
   wp-admin:    ${STAGING_URL}/wp-login.php
   Login:       Rob-Panwar / ${STAGING_ADMIN_PASS}
                Panwar-education / ${STAGING_ADMIN_PASS}
   MySQL pass:  see ${SECRETS_FILE}

To stop compute between test sessions (saves ~\$27/mo):
   ${SCRIPT_DIR}/staging-stop.sh

To fully tear down and go to \$0:
   ${SCRIPT_DIR}/staging-down.sh
EOF
