#!/usr/bin/env bash
# Full run: walk the case study, assemble the review PDF, copy it to Windows Downloads.
#   tools/walkthrough/caph0148/build.sh [--site http://localhost:8080] [--user Rob-Panwar] [--pass staging123]
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
lib="$here/../lib"
name=CAPH0148_Travel_Diabetes_Case_Study_v2_Walkthrough
cd "$here"
node walk.mjs "$@"
python3 crop.py out
python3 "$lib/note.py" out "Travelling with diabetes case study, version 2. Changes from the v1 review:

- New hero image and patient photo.
- Step names: Identify the risks, Decide the discussion, Prepare the plan.
- Green replaced with the Care Connect bright blue; orange matched to Hydralyte.
- Pop-ups have no close cross; Continue is the only way on.
- Sorting: a card in the right column turns blue with a tick; a card in the wrong column is marked and returns to the list. No Check button or hint. Continue shows the Nicely done! pop-up.
- Prepare the plan: all questions show at once; copy and answer edits as marked up.
- Bolding and copy edits in the pop-ups as marked up.
- Related resources in the Care Connect card style; Clinical Bites has a play button and opens the Diabetes Clinical Bites page.

Pages after this one: each stage as it appears, every pop-up, then the completed page top to bottom."
python3 "$lib/assemble.py" --dir out --out "out/$name.pdf"
dest="/mnt/c/Users/User/Downloads/${name}_$(date +%Y-%m-%d).pdf"
cp "out/$name.pdf" "$dest" && echo "copied to $dest"
