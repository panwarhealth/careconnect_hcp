#!/usr/bin/env bash
# Full run: reset the test user, walk the audit, assemble the PDF, copy it to Windows Downloads.
#   tools/walkthrough/caph0150/build.sh [--site http://localhost:8080] [--user Rob-Panwar] [--pass staging123]
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
lib="$here/../lib"
name=CAPH0150_Clinical_Audit_2026_Walkthrough
cd "$here"
node walk.mjs "$@"
python3 "$lib/note.py" out "The module chooser screen, shown only to users who have already interacted with the legacy Mini Clinical Audit."
python3 "$lib/assemble.py" --dir out --out "out/$name.pdf" --append out/certificate.pdf
dest="/mnt/c/Users/User/Downloads/${name}_$(date +%Y-%m-%d).pdf"
cp "out/$name.pdf" "$dest" && echo "copied to $dest"
