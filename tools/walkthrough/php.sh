#!/usr/bin/env bash
# Run one of this folder's PHP scripts inside the local wordpress container.
#   tools/walkthrough/php.sh reset.php Rob-Panwar
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(cd "$here/../.." && pwd)"
script="$1"; shift
cd "$root"
docker cp "$here/$script" careconnect_hcp-wordpress-1:/var/www/html/.walk-$script
docker compose exec -T wordpress php "/var/www/html/.walk-$script" "$@" 2>/dev/null | grep -v '^Deprecated' || true
docker compose exec -T wordpress rm -f "/var/www/html/.walk-$script"
