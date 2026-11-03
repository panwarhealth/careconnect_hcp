#!/usr/bin/env bash
# Records the Contact page, assembles the PDF, copies it to Windows Downloads.
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
lib="$here/../lib"
name=Contact_Page_Wording
cd "$here"
node walk.mjs "$@"
python3 "$lib/assemble.py" --dir out --out "out/$name.pdf"
dest="/mnt/c/Users/User/Downloads/${name}_$(date +%Y-%m-%d).pdf"
cp "out/$name.pdf" "$dest" && echo "copied to $dest"
