#!/usr/bin/env bash
#
# staging-seed.sh — dump local DB, sanitize, import to Azure MySQL.
# Called by staging-up.sh automatically, but can be re-run standalone
# after pulling a fresh prod dump or resetting staging data.

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/staging-env.sh"

require_tools
require_login

[ -f "$SECRETS_FILE" ] || { echo "ERROR: ${SECRETS_FILE} missing. Run staging-up.sh first."; exit 1; }
# shellcheck source=/dev/null
source "$SECRETS_FILE"

DUMP_SRC="${1:-}"
DUMP_TMP="$(mktemp -u /tmp/staging-seed-XXXX).sql"

if [ -n "$DUMP_SRC" ] && [ -f "$DUMP_SRC" ]; then
  echo "[1/3] Using provided dump: $DUMP_SRC"
  cp "$DUMP_SRC" "$DUMP_TMP"
else
  echo "[1/3] Dumping local Docker DB..."
  (cd "${SCRIPT_DIR}/.." && docker compose exec -T db mysqldump --no-tablespaces -u root -prootdevpass hcp_care) > "$DUMP_TMP"
fi
echo "  dump size: $(du -h "$DUMP_TMP" | cut -f1)"

DB_HOST="${STAGING_DB_SERVER}.mysql.database.azure.com"

echo "[2/3] Importing to Azure MySQL..."
mysql -h "$DB_HOST" -u "$STAGING_DB_ADMIN" -p"$MYSQL_PASS" --ssl-mode=PREFERRED "$STAGING_DB_NAME" < "$DUMP_TMP" 2>&1 | grep -v 'Using a password' || true

echo "[3/3] Sanitizing PII + URLs + admin passwords..."
mysql -h "$DB_HOST" -u "$STAGING_DB_ADMIN" -p"$MYSQL_PASS" --ssl-mode=PREFERRED "$STAGING_DB_NAME" < "${SCRIPT_DIR}/staging-sanitize.sql" 2>&1 | grep -v 'Using a password' || true

# Serialized-safe URL replacement needs wp-cli search-replace or Better Search Replace plugin.
# The sanitize SQL handles home/siteurl but not embedded URLs — flag for user.
REMAINING=$(mysql -h "$DB_HOST" -u "$STAGING_DB_ADMIN" -p"$MYSQL_PASS" --ssl-mode=PREFERRED "$STAGING_DB_NAME" -N -e "
SELECT COUNT(*) FROM tbstwp_options WHERE option_value LIKE '%localhost:8080%';" 2>/dev/null | tail -1)

rm -f "$DUMP_TMP"

if [ "${REMAINING:-0}" -gt 0 ]; then
  cat <<EOF

⚠️  ${REMAINING} option rows still reference localhost:8080 (inside serialized data).
   Finish with: wp-admin → Tools → Better Search Replace
     Search:  http://localhost:8080
     Replace: ${STAGING_URL}
     All tables, uncheck dry run.
EOF
else
  echo "✅ Seed complete. Login: Rob-Panwar / ${STAGING_ADMIN_PASS}"
fi
