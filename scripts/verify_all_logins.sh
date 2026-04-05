#!/usr/bin/env bash
# Summarize login API responses for all active users (requires local PHP server + Kingsway URL).
# Usage: BASE_URL=http://localhost/Kingsway ./scripts/verify_all_logins.sh

set -euo pipefail
MYSQL="${MYSQL:-/opt/lampp/bin/mysql}"
DB="${DB:-KingsWayAcademy}"
DBU="${DBU:-root}"
DBP="${DBP:-admin123}"
BASE_URL="${BASE_URL:-http://localhost/Kingsway}"

"$MYSQL" -u "$DBU" -p"$DBP" "$DB" -N -e "SELECT username FROM users WHERE status='active' ORDER BY id;" | while read -r u; do
  resp=$(curl -s -X POST "$BASE_URL/api/auth/login" -H "Content-Type: application/json" \
    -d "{\"username\":\"$u\",\"password\":\"Pass123!@\"}")
  perms=$(echo "$resp" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo count($j["data"]["user"]["permissions"]??[]);')
  sb=$(echo "$resp" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo count($j["data"]["sidebar_items"]??[]);')
  st=$(echo "$resp" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["status"]??"fail";')
  printf "%-28s status=%-7s perms=%4s sidebar=%3s\n" "$u" "$st" "$perms" "$sb"
done
