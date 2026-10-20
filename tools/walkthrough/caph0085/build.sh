#!/usr/bin/env bash
# Full run: walk the consent changes, assemble the PDF, copy it to Windows Downloads.
#   tools/walkthrough/caph0085/build.sh [--user Rob-Panwar] [--pass staging123]
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
lib="$here/../lib"
name=CAPH0085_Consent_and_Legal_Pages_Walkthrough
cd "$here"
node walk.mjs "$@"
python3 "$lib/assemble.py" --dir out --out "out/$name.pdf"
dest="/mnt/c/Users/User/Downloads/${name}_$(date +%Y-%m-%d).pdf"
cp "out/$name.pdf" "$dest" && echo "copied to $dest"
