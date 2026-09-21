#!/usr/bin/env bash
# Full run: reset the test user, walk the audit, assemble the PDF, copy it to Windows Downloads.
#   tools/walkthrough/build.sh [--site http://localhost:8080] [--user Rob-Panwar] [--pass staging123]
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$here"
node walk.mjs "$@"
python3 note.py out/000_note.pdf
python3 - <<'PY'
import json
m = json.load(open('out/manifest.json'))
if not m or m[0]['file'] != '000_note.pdf':
    m.insert(0, {'file': '000_note.pdf', 'label': 'Note', 'group': None, 'section': None, 'url': None})
    json.dump(m, open('out/manifest.json', 'w'), indent=2)
PY
python3 assemble.py --dir out --out out/CAPH0150_Clinical_Audit_2026_Walkthrough.pdf --append out/certificate.pdf
dest="/mnt/c/Users/User/Downloads/CAPH0150_Clinical_Audit_2026_Walkthrough_$(date +%Y-%m-%d).pdf"
cp out/CAPH0150_Clinical_Audit_2026_Walkthrough.pdf "$dest" && echo "copied to $dest"
