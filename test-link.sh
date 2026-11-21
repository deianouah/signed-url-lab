#!/usr/bin/env bash
# Usage: ./test-link.sh [BASE_URL] [--wait-expiry]
# Simulates a download tool against the signed-link system. Needs only curl.
BASE="${1:-http://localhost/stream-lab}"
WAIT="${2:-}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
show() { # label actual expected
  if [ "$2" = "$3" ]; then mark="OK "; else mark="?? "; fi
  printf '%s %-58s HTTP %s (expected %s)\n' "$mark" "$1" "$2" "$3"
}
login() { : > "$JAR"; curl -s -o /dev/null -c "$JAR" -d "username=$1" -d "password=$2" "$BASE/login.php"; }

echo "Target: $BASE"
echo

: > "$JAR"
show "link.php without login" "$(code "$BASE/link.php?f=video.mp4")" 401

login free free123
show "link.php as NON-subscriber (free)" "$(code -b "$JAR" "$BASE/link.php?f=video.mp4")" 403

login sub sub123
RESP="$(curl -s -b "$JAR" "$BASE/link.php?f=video.mp4")"
URL="$(printf '%s' "$RESP" | sed -n 's/.*"url":"\([^"]*\)".*/\1/p')"
show "link.php as subscriber (sub)" "$(code -b "$JAR" "$BASE/link.php?f=video.mp4")" 200

if [ -z "$URL" ]; then echo "Could not extract signed URL. Response was: $RESP"; exit 1; fi
echo "Signed URL: $URL"
echo

show "download with the link, NO cookies (like a download tool)" "$(code "$URL")" 200
show "Range request, first 100 bytes (video seeking)" "$(code -r 0-99 "$URL")" 206
show "tampered signature" "$(code "${URL}0")" 403
show "media.php with no token" "$(code "$BASE/media.php?f=video.mp4")" 403
show "direct access to /private/video.mp4 (Apache .htaccess)" "$(code "$BASE/private/video.mp4")" 403

if [ "$WAIT" = "--wait-expiry" ]; then
  echo "Waiting 65s for the link to expire..."
  sleep 65
  show "same link after expiry" "$(code "$URL")" 403
fi
