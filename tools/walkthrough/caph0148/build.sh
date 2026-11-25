#!/usr/bin/env bash
# Walk the case study and build the review PDF in the v1 layout, then copy it to Windows Downloads.
#   tools/walkthrough/caph0148/build.sh [--site http://localhost:8080] [--user Rob-Panwar] [--pass staging123]
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
name="CAPH0148 Travel Diabetes Case Study v2.pdf"
cd "$here"
node walk.mjs "$@"
python3 merge.py out "out/$name"
cp "out/$name" "/mnt/c/Users/User/Downloads/$name" && echo "copied to Downloads/$name"
