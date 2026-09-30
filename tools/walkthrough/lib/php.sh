#!/usr/bin/env bash
# Run a PHP script inside the local wordpress container.
#   tools/walkthrough/lib/php.sh caph0150/reset.php Rob-Panwar
# site/ is bind-mounted as the webroot, so the script is staged there for the duration of the run.
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(cd "$here/../../.." && pwd)"
script="$(realpath "$1")"; shift
name=".walk-$(basename "$script")"
cd "$root"
cp "$script" "site/$name"
trap 'rm -f "$root/site/$name"' EXIT
docker compose exec -T wordpress php "/var/www/html/$name" "$@" 2>/dev/null | grep -v '^Deprecated' || true
