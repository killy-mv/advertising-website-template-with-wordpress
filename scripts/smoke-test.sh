#!/usr/bin/env bash
# End-to-end checks against a running stack (used by CI; works locally too).
# Usage: scripts/smoke-test.sh [base-url]     default http://localhost:8080
set -euo pipefail
cd "$(dirname "$0")/.."
BASE="${1:-http://localhost:8080}"
failures=0

ok()   { echo "  ok   $*"; }
fail() { echo "  FAIL $*"; failures=$((failures + 1)); }

status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
cache()  { curl -s -o /dev/null -D - "$@" | tr -d '\r' | awk -F': ' 'tolower($1)=="x-cache-status"{print $2}'; }
expect() {
  local want=$1 got=$2 what=$3
  if [[ "$got" == "$want" ]]; then ok "$what ($got)"; else fail "$what: expected $want, got '$got'"; fi
}
check() {  # check "<description>" command...
  local what=$1; shift
  if "$@"; then ok "$what"; else fail "$what"; fi
}

echo "Pages"
for path in / /menu/ /about/ /news/ /visit/ /reservations/ /dish/pho-ga/; do
  expect 200 "$(status "$BASE$path")" "GET $path"
done
expect 404 "$(status "$BASE/no-such-page/")" "unknown page"

echo "Security"
expect 403 "$(status "$BASE/wp-config.php")" "wp-config.php blocked"
expect 403 "$(status "$BASE/xmlrpc.php")" "xmlrpc.php blocked"
headers=$(curl -s -o /dev/null -D - "$BASE/" | tr -d '\r')
for h in X-Content-Type-Options X-Frame-Options Referrer-Policy Content-Security-Policy; do
  check "header $h" grep -qi "^$h:" <<<"$headers"
done

echo "Page cache"
cache "$BASE/about/" >/dev/null
expect HIT    "$(cache "$BASE/about/")" "second anonymous request"
expect BYPASS "$(cache -b 'wordpress_logged_in_x=1' "$BASE/about/")" "logged-in cookie"
expect BYPASS "$(cache "$BASE/about/?q=1")" "query string"

echo "Invalidation"
id=$(docker compose run --rm -q wpcli post list --post_type=menu_item --name=pho-ga --field=ID | tr -d '\r')
old=$(docker compose run --rm -q wpcli post meta get "$id" rc_price | tr -d '\r')
cache "$BASE/menu/" >/dev/null; cache "$BASE/menu/" >/dev/null
docker compose run --rm -q wpcli post meta update "$id" rc_price 99.99 >/dev/null
menu=$(curl -s "$BASE/menu/")
check "new price visible immediately" grep -qF "\$99.99" <<<"$menu"
docker compose run --rm -q wpcli post meta update "$id" rc_price "$old" >/dev/null

echo "Object cache"
redis=$(docker compose run --rm -q wpcli redis status)
check "Redis connected" grep -q 'Status: Connected' <<<"$redis"

echo
if (( failures )); then echo "$failures check(s) failed"; exit 1; fi
echo "All checks passed"
