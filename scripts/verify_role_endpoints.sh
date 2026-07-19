#!/usr/bin/env bash
# Role-based endpoint smoke test for the Academic module.
#
# For each active user this script:
#   1. logs in and captures a JWT,
#   2. hits the canonical academic endpoints the class-teacher / subject-teacher /
#      academic UIs depend on (the ones fixed in the JS→backend re-point work),
#   3. asserts each response is HONEST:
#        - a real endpoint returns HTTP 200 with a non-subjects payload, OR
#        - a genuinely absent endpoint returns HTTP 404 (never a silent 200
#          subjects-list, which was the original masking bug).
#
# Usage:  BASE_URL=https://localhost/Kingsway ./scripts/verify_role_endpoints.sh
# Requires: local LAMPP Apache (CLI php lacks pdo_mysql, so we test via Apache+curl).

set -uo pipefail
MYSQL="${MYSQL:-/opt/lampp/bin/mysql}"
DB="${DB:-KingsWayAcademy}"
DBU="${DBU:-root}"
DBP="${DBP:-admin123}"
BASE_URL="${BASE_URL:-https://localhost/Kingsway}"
PASS="${PASS:-Pass123!@}"

# Canonical GET endpoints the academic UIs consume (post-hoc re-pointed to real handlers).
ENDPOINTS=(
  "academic/assessments-list"
  "academic/performance-overview"
  "academic/results-analysis"
  "academic/class-students"
  "academic/terms-list"
  "academic/classes-list"
  "academic/grading-results"
  "academic/subjects-list"
)

# Endpoint that MUST now 404 (was a silently-masked dead slug).
DEAD_ENDPOINTS=(
  "academic/grading-status"
  "academic/comparative-reports"
  "academic/performance-analysis"
)

total=0; pass=0; fail=0
printf "%-28s %-22s %s\n" "USER" "ENDPOINT" "RESULT"
printf "%-28s %-22s %s\n" "----" "--------" "------"

# Limit to a representative set of roles if USERS is not provided, so the test
# finishes quickly. Override with USERS="u1 u2 ..." or ROLE_LIMIT=0 for all.
ROLE_LIMIT="${ROLE_LIMIT:-6}"
if [ -n "${USERS:-}" ]; then
  USER_LIST="$USERS"
else
  USER_LIST=$("$MYSQL" -u "$DBU" -p"$DBP" "$DB" -N -e "SELECT username FROM users WHERE status='active' ORDER BY id LIMIT ${ROLE_LIMIT};")
fi

echo "$USER_LIST" | while read -r u; do
  token=$(curl -sk -X POST "$BASE_URL/api/auth/login" -H "Content-Type: application/json" \
    -d "{\"username\":\"$u\",\"password\":\"$PASS\"}" \
    | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["data"]["token"]??"";')

  if [ -z "$token" ]; then
    printf "%-28s %-22s %s\n" "$u" "(login)" "FAIL: no token"
    continue
  fi

  for ep in "${ENDPOINTS[@]}"; do
    # Some endpoints require query params; else they legitimately 400.
    case "$ep" in
      academic/class-students) qp="?class_id=1" ;;
      academic/performance-overview) qp="?class_id=1" ;;
      academic/results-analysis) qp="?subject_id=1" ;;
      academic/assessments-list) qp="?subject_id=1" ;;
      *) qp="" ;;
    esac
    resp=$(curl -sk -w "\n%{http_code}" "$BASE_URL/api/$ep$qp" -H "Authorization: Bearer $token")
    code=$(echo "$resp" | tail -1)
    body=$(echo "$resp" | sed '$d')
    # A TRUE mask = the generic get() fallback returned the full learning_areas list
    # (the controller's list() of subjects). Those payloads are flat arrays of rows
    # having `code` + `levels` (learning_areas columns). assessments-list legitimately
    # embeds `learning_area_name` so it is NOT treated as a mask.
    is_mask=$(echo "$body" | php -r '$j=json_decode(stream_get_contents(STDIN),true); $d=$j["data"]??null; $mask=false; if(is_array($d)&&count($d)>10){foreach($d as $row){if(is_array($row)&&isset($row["code"])&&isset($row["levels"])&&!isset($row["title"])){$mask=true;break;}} } echo $mask?"1":"0";')
    # subjects-list / learning-areas-list legitimately return learning_areas rows
    # (code + levels) — that is the expected real payload, never a mask.
    if [ "$ep" = "academic/subjects-list" ] || [ "$ep" = "academic/learning-areas-list" ]; then
      is_mask="0"
    fi
    if [ "$code" = "200" ] && [ "$is_mask" = "0" ]; then
      printf "%-28s %-22s %s\n" "$u" "$ep" "PASS ($code, real payload)"
    elif [ "$code" = "200" ] && [ "$is_mask" = "1" ]; then
      printf "%-28s %-22s %s\n" "$u" "$ep" "FAIL ($code, MASKED as subjects-list)"
    else
      printf "%-28s %-22s %s\n" "$u" "$ep" "FAIL ($code)"
    fi
  done

  for ep in "${DEAD_ENDPOINTS[@]}"; do
    code=$(curl -sk -o /dev/null -w "%{http_code}" "$BASE_URL/api/$ep" -H "Authorization: Bearer $token")
    if [ "$code" = "404" ]; then
      printf "%-28s %-22s %s\n" "$u" "$ep" "PASS ($code, honest 404)"
    else
      printf "%-28s %-22s %s\n" "$u" "$ep" "FAIL ($code, expected 404)"
    fi
  done
done

echo "----"
echo "Done. Any FAIL line above is a contract break: either a real endpoint returned a"
echo "masked subjects-list, or a dead slug failed to return 404."
