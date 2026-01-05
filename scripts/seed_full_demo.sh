#!/usr/bin/env bash
set -euo pipefail

# Full demo seeder (v3) - focuses on staff (users->roles->permissions->staff) first
# Usage: bash scripts/seed_full_demo.sh

API_URL="http://localhost/Kingsway/api"
TOKEN="devtest"
DB_NAME="KingsWayAcademy"
MYSQL="/opt/lampp/bin/mysql -u root -padmin123"

# Quick configuration
ACADEMIC_YEAR="2026"
CURRENT_TERM="Term 1"

# Staff distribution (as requested)
TEACHERS=20
SCHOOL_ADMIN=1
DIRECTORS=2
HEADTEACHER=1
DEPUTY_HEADS=2

# Ensure jq exists
if ! command -v jq >/dev/null 2>&1; then
  echo "jq is required for this script" >&2
  exit 1
fi

echo "Starting full demo seeder (phase: staff). Using API $API_URL (token devtest)"

# Helpers
api_post() {
  local endpoint="$1"; shift
  local data="$1"; shift || true
  curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$data" "$API_URL/$endpoint"
}

api_get() {
  local endpoint="$1"; shift
  curl -s -H "X-Test-Token: $TOKEN" "$API_URL/$endpoint"
}

get_role_id() {
  local role_name="$1"
  # Try roles endpoint
  local resp
  resp=$(api_get "roles/list")
  if echo "$resp" | jq -e . >/dev/null 2>&1; then
    echo "$resp" | jq -r --arg rn "$role_name" '.data[]? | select(.name==($rn)) | .id' | head -n1
  else
    echo "" 
  fi
}

create_user_if_missing() {
  local email="$1"; local first="$2"; local last="$3"; local role_id="$4"
  # Search existing via API list (may not support email filtering); fallback to DB query by email for exact match
  local resp
  resp=$(api_get "users")
  if echo "$resp" | jq -e . >/dev/null 2>&1; then
    local uid
    uid=$(echo "$resp" | jq -r --arg em "$email" '.data[]? | select(.email==($em)) | .id' | head -n1)
    if [ -n "$uid" ]; then
      echo "$uid"
      return
    fi
  fi

  # DB fallback: direct query by email
  uid=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM users WHERE email = '$(echo $email | sed "s/'/''/g")' LIMIT 1" || echo "")
  if [ -n "$uid" ]; then
    echo "$uid"; return;
  fi
  # Create user (deterministic password for dev use)
  local payload
  if [ "$role_id" = "$ROLE_TEACHER" ]; then
    payload=$(jq -n --arg em "$email" --arg fn "$first" --arg ln "$last" --arg role "$role_id" --arg pos "Teacher" --arg emp "$(date +%Y-%m-%d)" --arg tsc "TSC-$(date +%s%N | cut -c1-8)" '{email:$em, first_name:$fn, last_name:$ln, password:"Password123!", role_id: ($role|tonumber), tsc_no:$tsc, staff_info: {position: $pos, employment_date: $emp}}')
  else
    payload=$(jq -n --arg em "$email" --arg fn "$first" --arg ln "$last" --arg role "$role_id" '{email:$em, first_name:$fn, last_name:$ln, password:"Password123!", role_id: ($role|tonumber)}')
  fi
  local create_resp
  create_resp=$(api_post "users" "$payload")
  if echo "$create_resp" | jq -e . >/dev/null 2>&1; then
    echo "$create_resp" | jq -r '.data.id // empty'
  else
    echo "" 
  fi
}

# Create staff entry for a user
create_staff_for_user() {
  local user_id="$1"; local dept_id="$2"; local position="$3"; local dob="$4"

  # If user exists, fetch their first/last/email and role to satisfy staff API and determine whether to skip system admins
  local user_row
  user_row=$($MYSQL $DB_NAME -N -s -e "SELECT first_name, last_name, email, role_id FROM users WHERE id = $user_id LIMIT 1" || echo "")
  local fn
  local ln
  local em
  local role_id
  if [ -n "$user_row" ]; then
    # Use read to reliably parse whitespace-containing fields
    IFS=$'\t\n' read -r fn ln em role_id <<< "$user_row"
    fn=${fn:-}
    ln=${ln:-}
    em=${em:-}
    role_id=${role_id:-}
  fi

  # Normalize role_name and check admin by name or id
  if [ -n "$role_id" ]; then
    role_name=$($MYSQL $DB_NAME -N -s -e "SELECT name FROM roles WHERE id = $role_id LIMIT 1" || echo "")
    role_name_trimmed=$(echo "$role_name" | tr -d '\r' | sed -e 's/^\s*//' -e 's/\s*$//')
    if [ "$role_name_trimmed" = "System Administrator" ] || [ "$role_id" = "2" ]; then
      echo "Skipping staff creation for system admin user_id=$user_id (role: $role_name_trimmed)"
      echo ""; return;
    fi
  fi

  local payload
  payload=$(jq -n --argjson uid "$user_id" --arg fn "$fn" --arg ln "$ln" --arg em "$em" --argjson did "$dept_id" --arg pos "$position" --arg dob "$dob" '{user_id:$uid, first_name:$fn, last_name:$ln, email:$em, department_id:$did, position:$pos, employment_date: "'$(date +%Y-%m-%d)'", date_of_birth:$dob}')

  local resp
  resp=$(api_post "staff" "$payload")
  if echo "$resp" | jq -e . >/dev/null 2>&1; then
    local sid
    sid=$(echo "$resp" | jq -r '.data.id // empty')
    if [ -n "$sid" ]; then
      echo "$sid";
      return;
    fi
    # If API did not return staff id, but response may include an error - fallthrough to DB fallback
    echo "";
  else
    # Non-JSON response - fallthrough to DB fallback
    echo "";
  fi

  # DB fallback: insert staff directly (non-destructive if staff exists)
  existing=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM staff WHERE user_id = $user_id LIMIT 1" || echo "")
  if [ -n "$existing" ]; then
    echo "$existing"; return;
  fi

  # Insert directly
  $MYSQL $DB_NAME -e "INSERT INTO staff (staff_no, user_id, department_id, position, employment_date, date_of_birth, status, first_name, last_name, email, nssf_no, kra_pin, nhif_no, bank_account, salary) VALUES ('STF-$(date +%s%N | cut -c1-12)', $user_id, $dept_id, '$(echo $position | sed "s/'/''/g")', '$(date +%Y-%m-%d)', '$dob', 'active', '$(echo $fn | sed "s/'/''/g")', '$(echo $ln | sed "s/'/''/g")', '$(echo $em | sed "s/'/''/g")', 'NSSF-$(date +%s%N | cut -c1-8)', 'KRA-$(date +%s%N | cut -c1-8)', 'NHIF-$(date +%s%N | cut -c1-8)', 'BANK-$(date +%s%N | cut -c1-8)', 50000)"
  newid=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM staff WHERE user_id = $user_id LIMIT 1" || echo "")
  echo "$newid"
}

# Get department id (Academics fallback to first available)
get_department_id() {
  local q
  q=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM departments WHERE name LIKE '%Academ%' LIMIT 1" || echo "")
  if [ -n "$q" ]; then echo "$q"; return; fi
  local resp
  resp=$(api_get "staff/departments-get")
  if echo "$resp" | jq -e . >/dev/null 2>&1; then
    echo "$resp" | jq -r '.data.data? // .data? | map(select(.name|test("academ","i")))[0].id // .[0].id // empty'
  else
    echo ""
  fi
}

# Create roles if missing (simple mapping)
ensure_role() {
  local name="$1"; local code="$2"
  local rid
  rid=$(get_role_id "$name")
  if [ -n "$rid" ]; then echo "$rid"; return; fi
  local payload
  payload=$(jq -n --arg name "$name" --arg code "$code" '{name:$name, code:$code}')
  local resp
  resp=$(api_post "roles" "$payload")
  if echo "$resp" | jq -e . >/dev/null 2>&1; then
    local rid
    rid=$(echo "$resp" | jq -r '.data.id // empty')
    if [ -n "$rid" ]; then
      echo "$rid"; return;
    fi
  fi

  # API failed or returned no id; fallback to direct DB insert
  existing=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM roles WHERE name = '$name' LIMIT 1" || echo "")
  if [ -n "$existing" ]; then
    echo "$existing"; return;
  fi
  $MYSQL $DB_NAME -e "INSERT INTO roles (name, description, created_at) VALUES ('$(echo $name | sed "s/'/''/g")', 'Created by seeder', NOW())"
  newrid=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM roles WHERE name = '$name' LIMIT 1" || echo "")
  echo "$newrid"
}

# Start staff seeding
DEPT_ACAD=$(get_department_id)
if [ -z "$DEPT_ACAD" ]; then echo "Failed to find Academics department" >&2; exit 1; fi

# Ensure role ids (names may vary in your system; adjust as needed)
ROLE_TEACHER=$(ensure_role "Teacher" "teacher")
ROLE_ADMIN=$(ensure_role "School Admin" "school_admin")
ROLE_DIRECTOR=$(ensure_role "Director" "director")
ROLE_HEAD=$(ensure_role "Headteacher" "headteacher")
ROLE_DEPUTY=$(ensure_role "Deputy Headteacher" "deputy_head")

echo "Roles resolved: Teacher=$ROLE_TEACHER, Admin=$ROLE_ADMIN, Director=$ROLE_DIRECTOR, Head=$ROLE_HEAD, Deputy=$ROLE_DEPUTY"

# Create directors
for i in $(seq 1 $DIRECTORS); do
  email="director${i}@example.com"
  uid=$(create_user_if_missing "$email" "Director${i}" "Demo" "$ROLE_DIRECTOR")
  sid=$(create_staff_for_user "$uid" "$DEPT_ACAD" "Director" "$(date -d '-45 years' +%F)")
  echo "Director created: user_id=$uid staff_id=$sid"
done

# Create headteacher
uid=$(create_user_if_missing "headteacher@example.com" "Head" "Demo" "$ROLE_HEAD")
create_staff_for_user "$uid" "$DEPT_ACAD" "Headteacher" "$(date -d '-40 years' +%F)"

# Create deputy headteachers
for i in $(seq 1 $DEPUTY_HEADS); do
  email="deputy${i}@example.com"
  uid=$(create_user_if_missing "$email" "Deputy${i}" "Demo" "$ROLE_DEPUTY")
  create_staff_for_user "$uid" "$DEPT_ACAD" "Deputy Headteacher" "$(date -d '-38 years' +%F)"
done

# Create school admin
for i in $(seq 1 $SCHOOL_ADMIN); do
  email="admin${i}@example.com"
  uid=$(create_user_if_missing "$email" "Admin${i}" "Demo" "$ROLE_ADMIN")
  create_staff_for_user "$uid" "$DEPT_ACAD" "School Admin" "$(date -d '-35 years' +%F)"
done

# Create teachers and assign generic subjects (subject assignment later)
for i in $(seq 1 $TEACHERS); do
  email="teacher${i}@example.com"
  uid=$(create_user_if_missing "$email" "Teacher${i}" "Demo" "$ROLE_TEACHER")
  create_staff_for_user "$uid" "$DEPT_ACAD" "Teacher" "$(date -d '-30 years' +%F)"
  echo "Teacher created: user=$email"
done

# Phase complete: staff created
echo "Staff seeding phase complete. Next: academics, students, finance, MPESA, uniform (to be implemented)."

# TODO: implement remaining phases (academics, students, finance, mpesa, uniforms)

exit 0
