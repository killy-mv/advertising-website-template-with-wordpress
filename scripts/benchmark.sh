#!/usr/bin/env bash
# Load-test the site with k6 (Linux/macOS). Prints k6's summary; the full
# results table is produced by scripts/benchmark-suite.ps1 on Windows.
# Usage: scripts/benchmark.sh [--bypass] [URL]      (default URL: http://nginx)
set -euo pipefail
cd "$(dirname "$0")/.."
# Git Bash on Windows would rewrite container paths like /scripts/seed.php or
# the permalink /%postname%/ into C:/Program Files/Git/... (no effect elsewhere).
export MSYS_NO_PATHCONV=1
set -a
# shellcheck source=/dev/null
source .env
set +a

BYPASS=0
if [[ "${1:-}" == "--bypass" ]]; then BYPASS=1; shift; fi
URL="${1:-http://nginx}"
HOST_HEADER=""
[[ "$URL" == "http://nginx" ]] && HOST_HEADER="$(echo "$SITE_URL" | sed -E 's#^https?://##; s#/.*$##')"

docker compose run --rm -q -e BASE_URL="$URL" -e HOST_HEADER="$HOST_HEADER" -e BYPASS="$BYPASS" \
  -e VUS="${VUS:-20}" -e DURATION="${DURATION:-30s}" k6 run /bench/load.js
