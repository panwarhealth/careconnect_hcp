#!/usr/bin/env bash
# On-demand backup of prod DB + site essentials to Azure Blob (stcareconnect/backups).
# Run this after every prod deploy, or any time you want a safety snapshot.
#
# Usage: ./infra/prod-backup.sh
#
# Prerequisites:
#   - az login --scope https://management.core.windows.net//.default
#   - sshpass installed (apt install sshpass)
#   - infra/.prod-secrets exists (copy from .prod-secrets.example) with PROD_PASS set
#
# What it does:
#   1. SSH into prod and mysqldump hcp_care → gzip → upload to blob as db/YYYY-MM-DD-HHMM.sql.gz
#   2. SSH into prod and tar wp-content (excl. cache/upgrade/uploads) → upload as site-essentials/YYYY-MM-DD.tar.gz
#   3. Prunes: keeps last 7 DB dumps and last 4 site-essentials tarballs
#
# Restore:
#   DB:    az storage blob download ... → gunzip → mysql -h 127.0.0.1 -P 9306 -u hcp_care -p hcp_care < dump.sql
#   Files: az storage blob download ... → tar -xzf site-essentials.tar.gz -C /var/www/hcp.carepharma.com.au/httpdocs/

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ ! -f "$SCRIPT_DIR/.prod-secrets" ]; then
  echo "ERROR: $SCRIPT_DIR/.prod-secrets not found — copy .prod-secrets.example and set PROD_PASS." >&2
  exit 1
fi
# shellcheck source=/dev/null
source "$SCRIPT_DIR/.prod-secrets"

PROD_HOST="www9.stratus.ayuda.net.au"
PROD_PORT="9022"
PROD_USER="rob.hcp.carepharma.com.au"
PROD_WEB="/var/www/hcp.carepharma.com.au/httpdocs"

STORAGE_ACCOUNT="stcareconnect"
CONTAINER="backups"

TS=$(date +%Y-%m-%d-%H%M)
DATE=$(date +%Y-%m-%d)
# Windows az.exe (WSL interop) cannot read /tmp — stage on a Windows-visible path
# and hand it Windows-style paths (F:\...) via wslpath.
if [[ "$(command -v az)" == /mnt/* ]]; then
  TMP="/mnt/f/Github/.prod-backup-tmp-$$"
  mkdir -p "$TMP"
  az_path() { wslpath -w "$1"; }
else
  TMP=$(mktemp -d)
  az_path() { echo "$1"; }
fi

trap 'rm -rf "$TMP"' EXIT

echo "=== Prod backup — $TS ==="
echo ""

# ── 1. DB dump ────────────────────────────────────────────────────────────────
echo "[1/3] Dumping prod DB..."
DB_FILE="$TMP/prod-$TS.sql.gz"
sshpass -p "$PROD_PASS" ssh -p "$PROD_PORT" \
  -o StrictHostKeyChecking=accept-new \
  -o ConnectTimeout=30 \
  "$PROD_USER@$PROD_HOST" \
  "cd $PROD_WEB && php -d memory_limit=512M \$(which wp) db export - 2>/dev/null | gzip" \
  > "$DB_FILE"
echo "  DB dump: $(du -h "$DB_FILE" | cut -f1)"

echo "[2/3] Uploading DB dump to blob..."
az storage blob upload \
  --account-name "$STORAGE_ACCOUNT" \
  --container-name "$CONTAINER" \
  --name "db/prod-$TS.sql.gz" \
  --file "$(az_path "$DB_FILE")" \
  --auth-mode key \
  --overwrite \
  -o none
echo "  Uploaded: db/prod-$TS.sql.gz"

# ── 2. Site essentials tarball ────────────────────────────────────────────────
echo "[3/3] Creating site essentials tarball..."
SITE_FILE="$TMP/site-essentials-$DATE.tar.gz"
sshpass -p "$PROD_PASS" ssh -p "$PROD_PORT" \
  -o StrictHostKeyChecking=accept-new \
  -o ConnectTimeout=60 \
  "$PROD_USER@$PROD_HOST" \
  "tar czf - -C '$PROD_WEB' wp-content \
    --exclude='wp-content/cache' \
    --exclude='wp-content/upgrade' \
    --exclude='wp-content/wflogs' \
    --warning=no-file-changed 2>/dev/null; exit 0" \
  > "$SITE_FILE"
echo "  Tarball: $(du -h "$SITE_FILE" | cut -f1)"

echo "  Uploading site essentials to blob..."
az storage blob upload \
  --account-name "$STORAGE_ACCOUNT" \
  --container-name "$CONTAINER" \
  --name "site-essentials/site-essentials-$DATE.tar.gz" \
  --file "$(az_path "$SITE_FILE")" \
  --auth-mode key \
  --overwrite \
  -o none
echo "  Uploaded: site-essentials/site-essentials-$DATE.tar.gz"

# ── 3. Prune old backups ──────────────────────────────────────────────────────
echo ""
echo "Pruning old backups (keep 7 DB dumps, 4 site-essentials)..."

prune() {
  local prefix="$1" keep="$2"
  blobs=$(az storage blob list \
    --account-name "$STORAGE_ACCOUNT" \
    --container-name "$CONTAINER" \
    --prefix "$prefix" \
    --auth-mode key \
    --query "sort_by([].{name:name,modified:properties.lastModified},&modified) | [].name" \
    -o tsv 2>/dev/null)
  total=$(echo "$blobs" | grep -c . || true)
  delete_count=$(( total - keep ))
  if [ "$delete_count" -gt 0 ]; then
    echo "$blobs" | head -n "$delete_count" | while read -r blob; do
      az storage blob delete \
        --account-name "$STORAGE_ACCOUNT" \
        --container-name "$CONTAINER" \
        --name "$blob" \
        --auth-mode key \
        -o none 2>/dev/null
      echo "  Deleted: $blob"
    done
  else
    echo "  $prefix — nothing to prune ($total blobs)"
  fi
}

prune "db/" 7
prune "site-essentials/" 4

echo ""
echo "=== Backup complete ==="
echo ""
echo "Blobs in container:"
az storage blob list \
  --account-name "$STORAGE_ACCOUNT" \
  --container-name "$CONTAINER" \
  --auth-mode key \
  --query "[].{name:name,size:properties.contentLength,modified:properties.lastModified}" \
  -o table 2>/dev/null | grep -E "db/|site-essentials|Name"
