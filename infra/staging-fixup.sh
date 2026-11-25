#!/usr/bin/env bash
#
# staging-fixup.sh: post-seed fixes, then a plugin-by-plugin parity check against prod.
# Called by staging-up.sh after the seed; safe to re-run on its own.
#
# Fixes (via a token-guarded runner uploaded to wwwroot and deleted afterwards):
#   - localhost:8080 URLs left in serialized options, post content and postmeta
#   - Formidable Pro licence (blanked by the local sanitize, which turns Pro off)
#   - Formidable's generated stylesheet (built while Pro was off, so missing Pro rules)
# Then compares staging's core + plugin versions with prod's and lists any
# difference that is not on the expected list below.

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/staging-env.sh"
require_login

PROD_SECRETS="${SCRIPT_DIR}/.prod-secrets"
[ -f "$PROD_SECRETS" ] || { echo "ERROR: $PROD_SECRETS missing"; exit 1; }
set -a; source "$PROD_SECRETS"; set +a

# Present or active on prod but deliberately absent or inactive on staging (no mail,
# no live third-party calls). Anything else that differs is reported as a problem.
EXPECTED_DIFFS="wordfence wp-mail-smtp-pro imagify object-sync-for-salesforce wp-rocket wp-optimize advanced-custom-fields formidable-mailchimp formidable-zapier advanced-cache.php maintenance.php"

SCM="https://${STAGING_APP_NAME}.scm.azurewebsites.net"
WORK="$(mktemp -d)"
trap 'rm -rf "${WORK:?}"' EXIT

# Unique filename per run: opcache keeps serving an old runner (and its old token) under a reused name.
TOKEN="$(openssl rand -hex 20)"
NAME="r-$(openssl rand -hex 6).php"
sed "s/__TOKEN__/${TOKEN}/" "${SCRIPT_DIR}/staging-fixup-runner.php" > "$WORK/$NAME"

AZ_TOKEN="$(az account get-access-token --query accessToken -o tsv | tr -d '\r')"
AUTH="Authorization: Bearer ${AZ_TOKEN}"

echo "  ... uploading fix-up runner"
curl -sf -o /dev/null -X PUT -H "If-Match: *" -H "$AUTH" --data-binary @"$WORK/$NAME" "$SCM/api/vfs/site/wwwroot/$NAME"
sleep 3

echo "  ... running fix-ups"
printf 'frm_key=%s' "${LIC_FORMIDABLE_KEY:-}" | \
  curl -s -m 300 --data-binary @- -H 'Content-Type: application/x-www-form-urlencoded' \
  "${STAGING_URL}/${NAME}?token=${TOKEN}" > "$WORK/out.txt" || true

curl -s -o /dev/null -X DELETE -H "If-Match: *" -H "$AUTH" "$SCM/api/vfs/site/wwwroot/$NAME"
GONE=$(curl -s -o /dev/null -w "%{http_code}" -H "$AUTH" "$SCM/api/vfs/site/wwwroot/$NAME")
[ "$GONE" = "404" ] || echo "WARNING: runner $NAME may still be on staging (VFS returned $GONE); delete it by hand"

if ! grep -q '^--plugins' "$WORK/out.txt"; then
  echo "ERROR: fix-up runner did not complete. Output:"
  head -c 2000 "$WORK/out.txt"; echo
  exit 1
fi
sed -n '1,/^--plugins/p' "$WORK/out.txt" | grep -v '^--plugins' | sed 's/^/  /'

echo "  ... comparing with prod"
export SSHPASS="$PROD_PASS"
sshpass -e ssh -p "$PROD_PORT" -o StrictHostKeyChecking=accept-new "$PROD_USER@$PROD_HOST" \
  "cd /var/www/hcp-carepharma/httpdocs && echo core,\$(wp core version 2>/dev/null) && wp plugin list --fields=name,status,version --format=csv 2>/dev/null | tail -n +2" \
  > "$WORK/prod.csv"
{ grep '^core,' "$WORK/out.txt"; sed -n '/^--plugins/,$p' "$WORK/out.txt" | tail -n +2; } > "$WORK/stg.csv"

PROBLEMS=0
while IFS=, read -r name pstat pver sstat sver; do
  case " $EXPECTED_DIFFS " in *" $name "*) continue ;; esac
  # Inactive on both sides and only the version differs: not worth flagging.
  if [ "$pstat" = "$sstat" ] && [ "$pver" = "$sver" ]; then continue; fi
  echo "  DIFF $name  prod: ${pstat} ${pver}  staging: ${sstat} ${sver}"
  PROBLEMS=$((PROBLEMS + 1))
done < <(join -t, -a1 -a2 -e MISSING -o 0,1.2,1.3,2.2,2.3 \
  <(awk -F, 'NF==2{print $1",-,"$2; next}{print}' "$WORK/prod.csv" | sort) \
  <(awk -F, 'NF==2{print $1",-,"$2; next}{print}' "$WORK/stg.csv" | sort))

if [ "$PROBLEMS" -gt 0 ]; then
  echo "⚠️  Staging differs from prod in ${PROBLEMS} place(s) above. Fix before handing staging over."
  exit 1
fi
echo "✅ Staging matches prod (core + plugins), apart from the expected staging-only differences."
