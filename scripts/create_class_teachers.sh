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

# Note: 'My Classes' menu is removed for Class Teacher via code changes (no DB modification).
if [ "$DRY_RUN" -eq 1 ]; then
  echo "DRY RUN: No DB changes for My Classes. Menu removed in code; set DRY_RUN=0 to perform creation of class teachers only."
else
  echo "No DB changes performed for My Classes; menu removal handled in code."
fi

# Fetch classes via API
echo "Fetching classes list from API..."
classes_resp=$(curl -sS -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/academic")

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

# Iterate classes and create test class teachers
summary_file="/tmp/create_class_teachers_results_$(date +%s).log"
: > "$summary_file"

for cls_b64 in $classes_list; do
  cls=$(echo "$cls_b64" | base64 --decode)
  class_id=$(echo "$cls" | jq -r '.id // .class_id // empty')
  class_name=$(echo "$cls" | jq -r '.name // .class_name // empty')
  if [ -z "$class_id" ] || [ -z "$class_name" ]; then
    echo "Skipping unexpected class item: $cls"; continue
  fi

  # build username slug
  slug=$(echo "$class_name" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/_/g' | sed -E 's/^_|_$//g')
  username="test_classteacher_${slug}"
  email="${username}@example.com"
  first_name="Test"
  last_name="${slug}_CT"

  staff_category_id=$(map_class_to_category "$class_name")

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
                 --arg staffno "CT_${class_id}" \
                 '{username:$username,email:$email,password:$pass,first_name:$fn,last_name:$ln,role_ids:$roles,staff_info:{department_id:$dept,staff_category_id:$staffcategory,position:$pos,employment_date:$empdate,date_of_birth:"1990-01-01",nssf_no:"N/A",kra_pin:"A000",nhif_no:"N/A",bank_account:"0000",salary:0.00,staff_no:$staffno}}')

  echo "\nCreating teacher for class: $class_name (id=$class_id) -> username: $username"
  if [ "$DRY_RUN" -eq 1 ]; then
    echo "DRY RUN: would POST /api/staff with payload:"
    echo "$payload" | jq '.'
    echo "---"
    echo "created: false" >> "$summary_file"
    continue
  fi

  resp=$(curl -sS -X POST "$BASE_URL/api/staff" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$payload")

  echo "create resp: " $(echo "$resp" | jq -r '.message // .error // empty')

  # Try to extract staff ID or user id from response
  staff_id=$(echo "$resp" | jq -r '.data.staff_id // .data.id // .data.user_id // .data.user.id // .data.user_id // .data.staff.id // empty')
  user_id=$(echo "$resp" | jq -r '.data.user.id // .data.user_id // .data.id // empty')
  if [ -z "$staff_id" ]; then
    # If we only have user id, try to resolve staff by user_id
    if [ -n "$user_id" ]; then
      staff_id=$(curl -sS -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/staff?user_id=$user_id" | jq -r '.data[0].id // empty')
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