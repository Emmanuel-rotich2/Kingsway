#!/usr/bin/env bash
set -euo pipefail

# Apply script: create class teachers and assign classes via API
# Usage: TEST_TOKEN=devtest API_BASE_URL="http://localhost/Kingsway" DRY_RUN=0 bash scripts/apply_create_class_teachers.sh

API_BASE_URL=${API_BASE_URL:-http://localhost/Kingsway}
TEST_TOKEN=${TEST_TOKEN:-}
DRY_RUN=${DRY_RUN:-1}
PASSWORD=${PASSWORD:-Pass123!@}
LOG_FILE="/tmp/create_class_teachers_apply_$(date +%s).log"
: > "$LOG_FILE"

if [ -z "$TEST_TOKEN" ]; then
  echo "ERROR: TEST_TOKEN must be provided for safe run" >&2
  exit 1
fi

echo "Running apply_create_class_teachers.sh"
echo "API_BASE_URL=$API_BASE_URL" | tee -a "$LOG_FILE"
echo "DRY_RUN=$DRY_RUN" | tee -a "$LOG_FILE"

# Fetch classes (use classes list endpoint)
resp=$(curl -sS -H "X-Test-Token: $TEST_TOKEN" "$API_BASE_URL/api/academic/classes/list")
classes=$(echo "$resp" | jq -c '.data // . // empty')
if [ -z "$classes" ] || [ "$classes" = "null" ]; then
  echo "Failed to fetch classes or unexpected response from classes/list" | tee -a "$LOG_FILE"
  echo "$resp" | jq -C '.' | tee -a "$LOG_FILE" || true
  exit 1
fi

# Note: staff_no should be assigned by the system (database). We do NOT generate or send staff_no in payloads to avoid skipping or inconsistent values.
pad_num() { printf "%03d" "$1"; }

# Desired class shorts in order: pg, pp1, pp2, g1..g9
DESIRED_SHORTS=(pg pp1 pp2 g1 g2 g3 g4 g5 g6 g7 g8 g9)
# Build a map of short -> class_id by scanning classes
declare -A CLASS_MAP
for short in "${DESIRED_SHORTS[@]}"; do
  # Find matching class in the fetched classes list with more robust patterns
  case "$short" in
    pg)
      match_id=$(echo "$classes" | jq -r 'map(select((.name|ascii_downcase) | test("play|playgroup|^pg$"; "i"))) | .[0].id // empty') ;;
    pp1)
      match_id=$(echo "$classes" | jq -r 'map(select((.name|ascii_downcase) | test("pp1|pp 1|pre\\s*primary\\s*1|pre\\s*primary|preprimary\\s*1"; "i"))) | .[0].id // empty') ;;
    pp2)
      match_id=$(echo "$classes" | jq -r 'map(select((.name|ascii_downcase) | test("pp2|pp 2|pre\\s*primary\\s*2|preprimary\\s*2"; "i"))) | .[0].id // empty') ;;
    g[1-9])
      # Extract number
      num=$(echo "$short" | sed -E 's/^g([0-9])$/\1/')
      match_id=$(echo "$classes" | jq -r --arg num "$num" 'map(select((.name|ascii_downcase) | test("grade\\s*" + $num + "|grade" + $num + "|^g" + $num + "$|\\bg" + $num + "\\b"; "i"))) | .[0].id // empty') ;;
    *)
      match_id=$(echo "$classes" | jq -r --arg s "$short" 'map(select((.name|ascii_downcase) | test($s; "i"))) | .[0].id // empty') ;;
  esac

  if [ -n "$match_id" ]; then
    CLASS_MAP["$short"]=$match_id
  else
    echo "Warning: could not find class for short '$short'" | tee -a "$LOG_FILE"
  fi
done

# Prepare list of entries to create: short => display name
declare -A SHORT_LABEL
SHORT_LABEL[pg]="Playgroup"
SHORT_LABEL[pp1]="PP1"
SHORT_LABEL[pp2]="PP2"
for i in {1..9}; do SHORT_LABEL["g$i"]="Grade $i"; done


# map class name to short (optional)
map_class_to_short() {
  local cname="$1"
  local c=$(echo "$cname" | tr '[:upper:]' '[:lower:]')
  if echo "$c" | grep -E -qi 'play|pg'; then echo 'pg'; return; fi
  if echo "$c" | grep -E -qi '\bpp1?\b|pre\s*primary'; then 
    if echo "$c" | grep -E -qi 'pp2|pp 2|pre\s*primary\s*2'; then echo 'pp2'; else echo 'pp1'; fi
    return; fi
  if echo "$c" | grep -E -qi 'grade\s*1|grade1|\bg1\b'; then echo 'g1'; return; fi
  if echo "$c" | grep -E -qi 'grade\s*2|grade2|\bg2\b'; then echo 'g2'; return; fi
  if echo "$c" | grep -E -qi 'grade\s*3|grade3|\bg3\b'; then echo 'g3'; return; fi
  if echo "$c" | grep -E -qi 'grade\s*4|grade4|\bg4\b'; then echo 'g4'; return; fi
  if echo "$c" | grep -E -qi 'grade\s*5|grade5|\bg5\b'; then echo 'g5'; return; fi
  if echo "$c" | grep -E -qi 'grade\s*6|grade6|\bg6\b'; then echo 'g6'; return; fi
  if echo "$c" | grep -E -qi 'grade\s*7|grade7|\bg7\b'; then echo 'g7'; return; fi
  if echo "$c" | grep -E -qi 'grade\s*8|grade8|\bg8\b'; then echo 'g8'; return; fi
  if echo "$c" | grep -E -qi 'grade\s*9|grade9|\bg9\b'; then echo 'g9'; return; fi
  # fallback slug
  echo "$c" | sed -E 's/[^a-z0-9]+/_/g' | sed -E 's/^_|_$//g'
}

# Helper for POST
post_json() {
  local url="$1"; shift
  local payload="$1"; shift
  curl -sS -w '\n%{http_code}' -X POST "$url" -H "Content-Type: application/json" -H "X-Test-Token: $TEST_TOKEN" -d "$payload"
}

count=0; created=0; failed=0

# Iterate
echo "$classes" | jq -c '.[]' | while read -r cls; do
  class_id=$(echo "$cls" | jq -r '.id // .class_id // empty')
  class_name=$(echo "$cls" | jq -r '.name // .class_name // empty')
  if [ -z "$class_id" ] || [ -z "$class_name" ]; then
    echo "Skipping unexpected class item: $cls" | tee -a "$LOG_FILE"; continue
  fi

  # For targeted creation only: use DESIRED_SHORTS mapping
  short=$(map_class_to_short "$class_name")
  # If the short is not one of desired, skip
  if [[ ! " ${DESIRED_SHORTS[*]} " =~ " $short " ]]; then
    echo "Skipping class '$class_name' (short: $short) because it's not in desired list" | tee -a "$LOG_FILE"
    continue
  fi

  # Build username and staff_no (KWPS auto-increment series)
  if [ "$short" = "pg" ]; then uname_short="pg"; elif [[ "$short" =~ ^pp ]]; then uname_short="$short"; else uname_short="$short"; fi
  username="test_classteacher_${uname_short}"
  email="${username}@example.com"
  first_name="Test"
  # Build a validation-safe last_name (letters, spaces, hyphens, apostrophes only)
  label="${SHORT_LABEL[$short]// /}" # e.g., "Grade1" or "PP1"
  # Map digits to words to avoid numeric characters
  map_digits() {
    echo "$1" | sed -E 's/0/Zero/g; s/1/One/g; s/2/Two/g; s/3/Three/g; s/4/Four/g; s/5/Five/g; s/6/Six/g; s/7/Seven/g; s/8/Eight/g; s/9/Nine/g'
  }
  alpha_label=$(map_digits "$label")
  # Remove non-letter characters and make a spaced last name
  clean_label=$(echo "$alpha_label" | sed -E 's/[^a-zA-Z]+/ /g' | sed -E 's/^\s+|\s+$//g')
  last_name="ClassTeacher ${clean_label}"

  # staff_no intentionally omitted so the system assigns it automatically

  # ensure staff_id is defined to avoid 'set -u' errors
  staff_id=""

  # Roles: Class Teacher (7) + Subject Teacher (8)
  payload=$(jq -n --arg username "$username" --arg email "$email" --arg pass "$PASSWORD" --arg fn "$first_name" --arg ln "$last_name" --argjson roles '[7,8]' --argjson dept 1 --argjson staffcategory 6 --arg pos "Class Teacher" --arg empdate "$(date +%F)" '{username:$username,email:$email,password:$pass,first_name:$fn,last_name:$ln,role_ids:$roles,department_id:$dept,position:$pos,employment_date:$empdate,staff_info:{department_id:$dept,staff_category_id:$staffcategory,position:$pos,employment_date:$empdate,date_of_birth:"1990-01-01",nssf_no:"N/A",kra_pin:"A000",nhif_no:"N/A",bank_account:"0000",salary:1.00,tsc_no:"TSC000"}}')

  echo "Creating teacher for class: $class_name (id=$class_id) -> username: $username" | tee -a "$LOG_FILE"
  if [ "$DRY_RUN" -eq 1 ]; then
    echo "DRY RUN: would POST /api/staff with payload:" | tee -a "$LOG_FILE"
    echo "$payload" | jq . | tee -a "$LOG_FILE"
    count=$((count+1)); continue
  fi

  # Check if user already exists (idempotent)
  # Try to resolve existing user by username
  user_resp=$(curl -sS -H "X-Test-Token: $TEST_TOKEN" "$API_BASE_URL/api/users?username=$username") || true
  user_id=$(echo "$user_resp" | jq -r '[.data[] | select(.username=="'"$username"'" ) | .id] | .[0] // empty' 2>/dev/null || true)

  if [ -n "$user_id" ]; then
    # Lookup staff by user_id
    sresp=$(curl -sS -H "X-Test-Token: $TEST_TOKEN" "$API_BASE_URL/api/staff?user_id=$user_id") || true
    staff_id=$(echo "$sresp" | jq -r '[.data[] | select(.user_id=='"$user_id"') | .id // .staff_id] | .[0] // empty' 2>/dev/null || true)
    if [ -n "$staff_id" ]; then
      echo "User $username (user_id=$user_id) already has staff id=$staff_id" | tee -a "$LOG_FILE"
    else
      # If user exists but no staff row, we'll attempt to add staff by calling UsersAPI.update (not ideal) - instead, add minimal staff directly
      echo "User $username exists (user_id=$user_id) but has no staff record, will create staff row" | tee -a "$LOG_FILE"
      # Create staff record via direct staff endpoint by passing user info (the staff endpoint deduplicates by user email)
      create_resp=$(curl -sS -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -X POST "$API_BASE_URL/api/staff" -d "$payload") || true
      # try to extract staff id
      staff_id=$(echo "$create_resp" | jq -r '.. | .staff_id? // .id? // .user_id? // empty' 2>/dev/null | head -n1 | tr -d '\n' || true)
      if [ -n "$staff_id" ]; then
        echo "Created staff_id=$staff_id for existing user $username" | tee -a "$LOG_FILE"
      fi
    fi
  fi

  if [ -n "$staff_id" ]; then
    echo "User/staff $username already exists (staff_id=$staff_id), skipping create" | tee -a "$LOG_FILE"
  else
    # Create user/staff
    resp=$(post_json "$API_BASE_URL/api/staff" "$payload")
    http_code=$(echo "$resp" | tail -n1)
    body=$(echo "$resp" | head -n-1)

    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
      echo "Created $username (HTTP $http_code)" | tee -a "$LOG_FILE"
      created=$((created+1))

      # Resolve staff_id from response
      staff_id=$(echo "$body" | jq -r '.. | .staff_id? // .id? // .user_id? // .user?.id? // empty' 2>/dev/null | head -n1 | tr -d '\n' || true)

      # If still empty, try lookup by username
      if [ -z "$staff_id" ]; then
        sresp=$(curl -sS -H "X-Test-Token: $TEST_TOKEN" "$API_BASE_URL/api/staff?username=$username") || true
        staff_id=$(echo "$sresp" | jq -r '[.. | .id? // .staff_id? // .user_id? // empty] | .[0] // empty' 2>/dev/null || true)
      fi

    else
      echo "Failed to create $username (HTTP $http_code)" | tee -a "$LOG_FILE"
      echo "$body" | jq . | tee -a "$LOG_FILE" || true

      # If validation says username/email already exists, attempt to resolve staff id and continue
      if echo "$body" | jq -e '(.message|test("Username already exists|Email already exists"))' >/dev/null 2>&1; then
        sresp=$(curl -sS -H "X-Test-Token: $TEST_TOKEN" "$API_BASE_URL/api/staff?username=$username") || true
        staff_id=$(echo "$sresp" | jq -r '[.. | .id? // .staff_id? // .user_id? // empty] | .[0] // empty' 2>/dev/null || true)
        if [ -n "$staff_id" ]; then
          echo "Resolved existing staff id=$staff_id for $username; will attempt assignment" | tee -a "$LOG_FILE"
        fi
      fi

      failed=$((failed+1))
    fi
  fi

  if [ -n "$staff_id" ]; then
    assign_payload=$(jq -n --argjson staffid "$staff_id" --argjson classid "$class_id" '{staff_id:$staffid, class_id:$classid}')
    aresp=$(curl -sS -w '\n%{http_code}' -X POST "$API_BASE_URL/api/staff/assign/class" -H "Content-Type: application/json" -H "X-Test-Token: $TEST_TOKEN" -d "$assign_payload") || true
    a_http=$(echo "$aresp" | tail -n1)
    if [ "$a_http" = "200" ] || [ "$a_http" = "201" ]; then
      echo "Assigned staff_id=$staff_id to class_id=$class_id" | tee -a "$LOG_FILE"
    else
      echo "Failed to assign staff_id=$staff_id to class_id=$class_id; resp: $aresp" | tee -a "$LOG_FILE"
    fi
  else
    echo "Warning: could not determine staff_id for $username; response: $body" | tee -a "$LOG_FILE"
  fi
  count=$((count+1))
done

# Summary
echo "Summary:" | tee -a "$LOG_FILE"
echo "  Processed: $count" | tee -a "$LOG_FILE"
echo "  Created:   $created" | tee -a "$LOG_FILE"
echo "  Failed:    $failed" | tee -a "$LOG_FILE"

echo "Log written to $LOG_FILE" | tee -a "$LOG_FILE"

if [ "$failed" -gt 0 ]; then
  exit 2
fi

exit 0
