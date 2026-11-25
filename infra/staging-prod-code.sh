#!/usr/bin/env bash
#
# staging-prod-code.sh: build the staging deploy tree from PROD's code.
#
# Local site/ lags prod (core, licensed plugins, branches deployed but not yet
# merged), so staging built from local is not a faithful copy of prod. This
# pulls prod's webroot over SSH (no uploads), overlays the staging-only files
# from the repo, strips licence key files and checks for paths that collide on
# Azure's case-insensitive file share.
#
# Usage: staging-prod-code.sh <output-dir>
# Needs infra/.prod-secrets (PROD_HOST, PROD_PORT, PROD_USER, PROD_PASS).

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT="${1:?usage: staging-prod-code.sh <output-dir>}"
PROD_SECRETS="${SCRIPT_DIR}/.prod-secrets"
PROD_WEB="/var/www/hcp-carepharma/httpdocs"

[ -f "$PROD_SECRETS" ] || { echo "ERROR: $PROD_SECRETS missing"; exit 1; }
set -a; source "$PROD_SECRETS"; set +a
export SSHPASS="$PROD_PASS"
SSH="sshpass -e ssh -p $PROD_PORT -o StrictHostKeyChecking=accept-new"

mkdir -p "$OUT"

echo "  ... pulling prod code (no uploads) into $OUT"
# Deploy-zip exclusions in staging-up.sh still apply on top of these.
rsync -a --delete -e "$SSH" \
  --exclude='/wp-config.php' \
  --exclude='/.htaccess' \
  --exclude='/wp-cli.phar' \
  --exclude='/wp-content/uploads/' \
  --exclude='/wp-content/cache/' \
  --exclude='/wp-content/upgrade/' \
  --exclude='/wp-content/wflogs/' \
  --exclude='/wp-content/themes/wp-spinnr-child/documents/*.csv' \
  --exclude='*.log' \
  --exclude='*.sql' --exclude='*.sql.gz' \
  --exclude='*.bak' --exclude='*.bak-*' --exclude='*-bak-*' \
  --exclude='tbst-pma/' \
  "$PROD_USER@$PROD_HOST:$PROD_WEB/" "$OUT/" 2>&1 | grep -v 'failed to set times' || true

echo "  ... overlaying staging-only files"
cp "${SCRIPT_DIR}/../site/wp-config.php" "$OUT/wp-config.php"
cp "${SCRIPT_DIR}/staging-default.conf" "$OUT/default"
cp "${SCRIPT_DIR}/staging-startup.sh" "$OUT/startup.sh"
chmod +x "$OUT/startup.sh"
mkdir -p "$OUT/wp-content/mu-plugins"
cp "${SCRIPT_DIR}/staging-no-autoupdate.php" "$OUT/wp-content/mu-plugins/staging-no-autoupdate.php"

echo "  ... stripping licence key files"
# LearnDash downloads embed the 1-site key; it must never reach staging.
find "$OUT/wp-content/plugins" -name 'LWSW_KEY.php' -print -delete

echo "  ... checking for case-insensitive path collisions"
# Azure Files merges paths that differ only by case (e.g. php/settings vs php/Settings),
# which silently mixes old and new plugin files. Replace any affected wordpress.org
# plugin with its clean official zip of the same version; fail on anything else.
collisions() { (cd "$OUT" && find . | tr 'A-Z' 'a-z' | sort | uniq -d); }
BAD="$(collisions)"
if [ -n "$BAD" ]; then
  for slug in $(echo "$BAD" | sed -n 's#^\./wp-content/plugins/\([^/]*\)/.*#\1#p' | sort -u); do
    main=$(grep -lE '^[[:space:]/*#]*Plugin Name:' "$OUT/wp-content/plugins/$slug"/*.php 2>/dev/null | head -1)
    ver=$(grep -m1 -E '^[[:space:]/*#]*Version:' "$main" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')
    tmp="$(mktemp -d)"
    if curl -sfL -o "$tmp/p.zip" "https://downloads.wordpress.org/plugin/${slug}.${ver}.zip"; then
      echo "  ... $slug $ver had stale case-colliding files; replaced with the official zip"
      rm -rf "${OUT:?}/wp-content/plugins/${slug:?}"
      (cd "$OUT/wp-content/plugins" && unzip -q "$tmp/p.zip")
    fi
    rm -rf "${tmp:?}"
  done
  BAD="$(collisions)"
  if [ -n "$BAD" ]; then
    echo "ERROR: paths below collide on Azure's case-insensitive share; fix before deploying:"
    echo "$BAD" | head -20
    exit 1
  fi
fi

echo "  ... prod code ready: core $(grep -oE "wp_version = '[^']+" "$OUT/wp-includes/version.php" | cut -d"'" -f2), $(ls "$OUT/wp-content/plugins" | wc -l) plugin dirs"
