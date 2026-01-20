#!/usr/bin/env bash
set -euo pipefail

# Script: create_class_teachers.sh
# - Disables the redundant "My Classes" sidebar mapping (menu_item_id=400)
# - Creates class teacher user+staff for each class and assigns them to the class
# Usage:
#   ADMIN_USER=admin ADMIN_PASS='Pass123!@' DB_USER=root DB_PASS=admin123 ./scripts/create_class_teachers.sh

BASE_URL=${BASE_URL:-http://localhost/Kingsway}
ADMIN_USER=${ADMIN_USER:-admin}
ADMIN_PASS=${ADMIN_PASS:-Pass123!@}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-admin123}
DB_NAME=${DB_NAME:-KingsWayAcademy}

# Safety: dry run by default. Set DRY_RUN=0 to actually perform changes.
DRY_RUN=${DRY_RUN:-1}

jq_exec() { jq -n "$@"; }

echo "Using API base: $BASE_URL"

# Prefer TEST_TOKEN (for test/staging). If not provided, fall back to admin login.
if [ -n "${TEST_TOKEN:-}" ]; then
  echo "Using TEST_TOKEN for API auth"
  TOKEN="$TEST_TOKEN"
  AUTH_OPTIONS=( -H "X-Test-Token: $TEST_TOKEN" )
else
  echo "1) Obtain admin token..."
  login_resp=$(curl -sS -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"username\": \"$ADMIN_USER\", \"password\": \"$ADMIN_PASS\"}")

  # Try to extract token from common locations
  TOKEN=$(echo "$login_resp" | jq -r '.data.token // .data.token // .token // .data.token_string // empty')
  if [ -z "$TOKEN" ]; then
    TOKEN=$(echo "$login_resp" | jq -r '.data.token // .token // .data.token_value // empty')
  fi

  if [ -z "$TOKEN" ]; then
    echo "Failed to obtain token from login response:" >&2
    echo "$login_resp" | jq -C '.' >&2 || true
    exit 1
  fi

  echo "Admin token acquired (length: ${#TOKEN})"
  AUTH_OPTIONS=( -H "Authorization: Bearer $TOKEN" )
fi

# Note: 'My Classes' menu is removed for Class Teacher via code changes (no DB modification).
if [ "$DRY_RUN" -eq 1 ]; then
  echo "DRY RUN: No DB changes for My Classes. Menu removed in code; set DRY_RUN=0 to perform creation of class teachers only."
else
  echo "No DB changes performed for My Classes; menu removal handled in code."
fi

# Fetch classes via API
echo "Fetching classes list from API..."
# Use TEST_TOKEN header when provided, otherwise Authorization header
if [ -n "${TEST_TOKEN:-}" ]; then
  classes_resp=$(curl -sS -H "X-Test-Token: $TEST_TOKEN" "$BASE_URL/api/academic")
else
  classes_resp=$(curl -sS -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/academic")
fi

# Expecting array in .data or top-level array
classes_json=$(echo "$classes_resp" | jq -c '.data // .data.classes // .classes // . // empty')
if [ -z "$classes_json" ] || [ "$classes_json" = "null" ]; then
  echo "Failed to fetch classes or unexpected response:" >&2
  echo "$classes_resp" | jq -C '.' >&2 || true
  exit 1
fi

# Convert classes to a simple list: id and name
classes_list=$(echo "$classes_resp" | jq -r '.data[]? | @base64' )
if [ -z "$classes_list" ]; then
  # Try top-level array
  classes_list=$(echo "$classes_resp" | jq -r '.[]? | @base64')
fi

if [ -z "$classes_list" ]; then
  echo "No classes found, exiting."; exit 1;
fi

# Helper: map class name to staff_category_id
map_class_to_category() {
  local cname="$1"
  cname_lower=$(echo "$cname" | tr '[:upper:]' '[:lower:]')
  if echo "$cname_lower" | grep -q 'play'; then echo 1; return; fi
  if echo "$cname_lower" | grep -q 'pp'; then echo 2; return; fi
  if echo "$cname_lower" | grep -E 'grade (1|2|3)|grade1|grade2|grade3' -qi; then echo 3; return; fi
  if echo "$cname_lower" | grep -E 'grade (4|5|6)|grade4|grade5|grade6' -qi; then echo 4; return; fi
  if echo "$cname_lower" | grep -E 'grade (7|8|9)|grade7|grade8|grade9' -qi; then echo 5; return; fi
  # default to subject specialist (6)
  echo 6
}

# Configure staff number prefix and compute next number based on DB
STAFF_NO_PREFIX=${STAFF_NO_PREFIX:-KWPS}
START_NUMBER=${START_NUMBER:-34}
# Query DB for highest existing staff number with prefix
current_staff_no=$(/opt/lampp/bin/mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -N -e "SELECT staff_no FROM staff WHERE staff_no LIKE '${STAFF_NO_PREFIX}%' ORDER BY staff_no DESC LIMIT 1;" 2>/dev/null || true)
if [ -n "$current_staff_no" ]; then
  # strip prefix and leading zeros
  cur_num=$(echo "$current_staff_no" | sed -E "s/^${STAFF_NO_PREFIX}//" | sed 's/^0*//')
  cur_num=${cur_num:-0}
  NEXT_STAFF_NUM=$((cur_num+1))
else
  NEXT_STAFF_NUM=$START_NUMBER
fi

format_staff_no() {
  printf "%s%03d" "$STAFF_NO_PREFIX" "$1"
}

summary_file="/tmp/create_class_teachers_results_$(date +%s).log"
: > "$summary_file"

for cls_b64 in $classes_list; do
  cls=$(echo "$cls_b64" | base64 --decode)
  class_id=$(echo "$cls" | jq -r '.id // .class_id // empty')
  class_name=$(echo "$cls" | jq -r '.name // .class_name // empty')
  if [ -z "$class_id" ] || [ -z "$class_name" ]; then
    echo "Skipping unexpected class item: $cls"; continue
  fi

  # build username (short mapping like pg, g1..g9 when possible)
  map_class_to_short() {
    local cname="$1"
    local c=$(echo "$cname" | tr '[:upper:]' '[:lower:]')
    if echo "$c" | grep -q 'play\|pg'; then echo 'pg'; return; fi
    if echo "$c" | grep -q '\bpp\b'; then echo 'pp'; return; fi
    if echo "$c" | grep -E 'grade\s*1|grade1|g1' -qi; then echo 'g1'; return; fi
    if echo "$c" | grep -E 'grade\s*2|grade2|g2' -qi; then echo 'g2'; return; fi
    if echo "$c" | grep -E 'grade\s*3|grade3|g3' -qi; then echo 'g3'; return; fi
    if echo "$c" | grep -E 'grade\s*4|grade4|g4' -qi; then echo 'g4'; return; fi
    if echo "$c" | grep -E 'grade\s*5|grade5|g5' -qi; then echo 'g5'; return; fi
    if echo "$c" | grep -E 'grade\s*6|grade6|g6' -qi; then echo 'g6'; return; fi
    if echo "$c" | grep -E 'grade\s*7|grade7|g7' -qi; then echo 'g7'; return; fi
    if echo "$c" | grep -E 'grade\s*8|grade8|g8' -qi; then echo 'g8'; return; fi
    if echo "$c" | grep -E 'grade\s*9|grade9|g9' -qi; then echo 'g9'; return; fi
    # fallback slug
    echo "$c" | sed -E 's/[^a-z0-9]+/_/g' | sed -E 's/^_|_$//g'
  }

  short=$(map_class_to_short "$class_name")
  # Build username with truncation to meet validation limits (max 30 chars)
  slug_full="$short"
  # keep 12 chars for slug part so prefix fits: prefix 'test_classteacher_' (18) + 12 = 30
  slug_trunc=$(echo "$slug_full" | sed -E 's/[^a-z0-9]+/_/g' | sed -E 's/^_|_$//g' | cut -c1-12)

  # Ensure username uniqueness by appending suffix if needed
  ensure_unique_username() {
    local base="$1"
    local uname="$base"
    local i=1
    while /opt/lampp/bin/mysql -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -N -e "SELECT COUNT(*) FROM users WHERE username = '$uname'" | grep -qv "^0$"; do
      uname="${base}_$i"
      i=$((i+1))
    done
    echo "$uname"
  }

  username_base="test_classteacher_${slug_trunc}"
  username=$(ensure_unique_username "$username_base")
  email="${username}@example.com"
  first_name="Test"
  # Sanitize and format last name to only letters and spaces for validation
  clean_last=$(echo "$short" | sed -E 's/[^a-zA-Z]+/ /g' | sed -E 's/^\s+|\s+$//g')
  if [ -z "$clean_last" ]; then
    clean_last="ClassTeacher"
  fi
  last_name="${clean_last} CT"

  staff_category_id=$(map_class_to_category "$class_name")

  # Auto-generate staff_no using prefix and NEXT_STAFF_NUM
  staffno_var=$(format_staff_no $NEXT_STAFF_NUM)
  NEXT_STAFF_NUM=$((NEXT_STAFF_NUM+1))

  payload=$(jq -n --arg username "$username" \
                 --arg email "$email" \
                 --arg pass "Pass123!@" \
                 --arg fn "$first_name" \
                 --arg ln "$last_name" \
                 --argjson roles '[7]' \
                 --argjson dept 1 \
                 --argjson staffcategory "$staff_category_id" \
                 --arg pos "Class Teacher" \
                 --arg empdate "$(date +%F)" \
                 --arg staffno "$staffno_var" \
                 '{username:$username,email:$email,password:$pass,first_name:$fn,last_name:$ln,role_ids:$roles,department_id:$dept,position:$pos,employment_date:$empdate,staff_info:{department_id:$dept,staff_category_id:$staffcategory,position:$pos,employment_date:$empdate,date_of_birth:"1990-01-01",nssf_no:"N/A",kra_pin:"A000",nhif_no:"N/A",bank_account:"0000",salary:0.00,staff_no:$staffno}}')

  echo "\nCreating teacher for class: $class_name (id=$class_id) -> username: $username"
  if [ "$DRY_RUN" -eq 1 ]; then
    echo "DRY RUN: would POST /api/staff with payload:"
    echo "$payload" | jq '.'
    echo "---"
    echo "created: false" >> "$summary_file"
    continue
  fi

  if [ -n "${TEST_TOKEN:-}" ]; then
    resp=$(curl -sS -X POST "$BASE_URL/api/staff" \
      -H "Content-Type: application/json" \
      -H "X-Test-Token: $TEST_TOKEN" \
      -d "$payload")
  else
    resp=$(curl -sS -X POST "$BASE_URL/api/staff" \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer $TOKEN" \
      -d "$payload")
  fi

  # Inspect response for errors
  echo "create resp: " $(echo "$resp" | jq -r '.message // .error // empty')
  create_status=$(echo "$resp" | jq -r '.data.status // empty')
  if [ "$create_status" = "error" ]; then
    echo "Create failed for $username: "
    echo "$resp" | jq '.'
    echo "created: false" >> "$summary_file"
    continue
  fi

  # Try to extract staff ID or user id from response
  staff_id=$(echo "$resp" | jq -r '.data.staff_id // .data.id // .data.user_id // .data.user.id // .data.user_id // .data.staff.id // empty')
  user_id=$(echo "$resp" | jq -r '.data.user.id // .data.user_id // .data.id // empty')
  if [ -z "$staff_id" ]; then
    # If we only have user id, try to resolve staff by user_id (use TEST_TOKEN if provided)
    if [ -n "$user_id" ]; then
      if [ -n "${TEST_TOKEN:-}" ]; then
        staff_id=$(curl -sS -H "X-Test-Token: $TEST_TOKEN" "$BASE_URL/api/staff?user_id=$user_id" | jq -r '.data[0].id // empty')
      else
        staff_id=$(curl -sS -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/staff?user_id=$user_id" | jq -r '.data[0].id // empty')
      fi
    fi
  fi

  echo "Resolved staff_id=$staff_id user_id=$user_id"

  if [ -z "$staff_id" ]; then
    echo "Failed to determine staff id for username $username, response:" >&2
    echo "$resp" | jq '.' >&2 || true
    echo "created: false" >> "$summary_file"
    continue
  fi

  # Assign class to staff
  assign_payload=$(jq -n --argjson staffid "$staff_id" --argjson classid "$class_id" '{staff_id:$staffid, class_id:$classid}')
  assign_resp=$(curl -sS -X POST "$BASE_URL/api/staff/assign/class" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$assign_payload")

  echo "assign resp: " $(echo "$assign_resp" | jq -r '.message // .error // empty')

  echo "{\"class_id\":$class_id,\"class_name\":\"$class_name\",\"username\":\"$username\",\"staff_id\":$staff_id}" >> "$summary_file"
  echo "Completed for $username (staff_id=$staff_id)"
done


echo "\nSummary written to $summary_file"

if [ "$DRY_RUN" -eq 1 ]; then
  echo "DRY RUN was enabled; no server changes were made. To apply changes set DRY_RUN=0 and re-run."
else
  echo "All done. You can inspect created staff and assignments via admin UI or API."
fi