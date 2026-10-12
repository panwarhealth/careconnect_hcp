#!/usr/bin/env bash
#
# publish.sh — prepare a video for web playback and upload it to the R2 bucket.
#
# Usage: tools/video/publish.sh <source-video> <slug> [poster.jpg]
#
# Sources that are already H.264 at <=1080p are copied as-is (just reordered so
# playback starts before the download finishes). Anything larger, or in another
# codec, is re-encoded to 1080p H.264/AAC. Without a poster, a frame at 2s is used.
#
# Uploads to videos/<slug>/<slug>.mp4 and videos/<slug>/poster.jpg and prints
# the public URLs. Needs ffmpeg/ffprobe and .secrets/cloudflare
# (CF_API_TOKEN, CF_ACCOUNT_ID, R2_BUCKET, R2_PUBLIC_HOST).

set -euo pipefail

if [[ $# -lt 2 ]]; then
	sed -n 3,13p "$0" | sed 's/^# \{0,1\}//'
	exit 1
fi

SRC="$1"
SLUG="$2"
POSTER="${3:-}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PATH="$HOME/bin:$PATH"

[[ -f "$SRC" ]] || { echo "Source not found: $SRC" >&2; exit 1; }
[[ "$SLUG" =~ ^[a-z0-9-]+$ ]] || { echo "Slug must be lowercase letters, digits and hyphens." >&2; exit 1; }

set -a
# shellcheck disable=SC1091
source "$ROOT/.secrets/cloudflare"
set +a
export CLOUDFLARE_API_TOKEN="$CF_API_TOKEN" CLOUDFLARE_ACCOUNT_ID="$CF_ACCOUNT_ID"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
OUT="$WORK/$SLUG.mp4"

IFS=, read -r CODEC WIDTH HEIGHT < <(ffprobe -v error -select_streams v:0 \
	-show_entries stream=codec_name,width,height -of csv=p=0 "$SRC")
SHORT_SIDE=$(( WIDTH < HEIGHT ? WIDTH : HEIGHT ))

if [[ "$CODEC" == "h264" && "$SHORT_SIDE" -le 1080 ]]; then
	echo "Copying ${WIDTH}x${HEIGHT} H.264 without re-encoding..."
	ffmpeg -nostdin -v error -i "$SRC" -c copy -map 0:v:0 -map 0:a:0? -movflags +faststart "$OUT"
else
	echo "Encoding ${WIDTH}x${HEIGHT} ${CODEC} to 1080p H.264..."
	ffmpeg -nostdin -v error -stats -i "$SRC" \
		-vf "scale='if(gt(iw,ih),-2,min(1080,iw))':'if(gt(iw,ih),min(1080,ih),-2)'" \
		-c:v libx264 -preset slow -crf 21 -profile:v high -pix_fmt yuv420p \
		-c:a aac -b:a 128k -ac 2 \
		-map 0:v:0 -map 0:a:0? -movflags +faststart "$OUT"
fi

if [[ -z "$POSTER" ]]; then
	POSTER="$WORK/poster.jpg"
	ffmpeg -nostdin -v error -ss 2 -i "$OUT" -frames:v 1 -q:v 3 "$POSTER"
fi

put() {
	npx -y wrangler@3.114.0 r2 object put "$R2_BUCKET/$1" --file "$2" --content-type "$3" \
		--cache-control "public, max-age=31536000, immutable" 2>&1 | grep -E "Upload complete|ERROR" || true
}

put "videos/$SLUG/$SLUG.mp4" "$OUT" video/mp4
put "videos/$SLUG/poster.jpg" "$POSTER" image/jpeg

DURATION=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$OUT")
echo "video:    https://$R2_PUBLIC_HOST/videos/$SLUG/$SLUG.mp4"
echo "poster:   https://$R2_PUBLIC_HOST/videos/$SLUG/poster.jpg"
printf 'duration: %d:%02d\n' "$(( ${DURATION%.*} / 60 ))" "$(( ${DURATION%.*} % 60 ))"
