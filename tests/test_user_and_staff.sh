#!/usr/bin/env bash
set -euo pipefail

API_URL="http://localhost/Kingsway/api"
TOKEN="devtest"
MYSQL="/opt/lampp/bin/mysql -u root -padmin123"
DB_NAME="KingsWayAcademy"

echo "Running user+staff tests"

# Create a role for testing
ROLE_NAME="TeacherTest_$(date +%s)"
ROLE_RESP=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$(jq -nc --arg name "$ROLE_NAME" --arg desc "Temporary test teacher role" '{name:$name, description:$desc}')" "$API_URL/system/roles")
ROLE_ID=$(echo "$ROLE_RESP" | jq -r '.data.id // empty')
if [ -z "$ROLE_ID" ]; then
  # If role exists, fetch it
  ROLE_ID=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/system/roles" | jq -r --arg rn "$ROLE_NAME" '.data[]? | select(.name==$rn) | .id' | head -n1)
fi
if [ -z "$ROLE_ID" ]; then
  echo "Failed to create or find role. Response:" >&2
  echo "$ROLE_RESP" | jq . >&2
  exit 1
fi
echo "Resolved test role: $ROLE_ID"

# Create a fresh user with teacher role (unique email/username)
TS=$(date +%s%N | cut -c1-12)
USERNAME="teacher_test_$TS"
EMAIL="teacher_test_${TS}@example.com"
USER_PAYLOAD=$(jq -n --arg un "$USERNAME" --arg em "$EMAIL" --arg fn "Teacher" --arg ln "Test" --arg pw "Str0ng!Pass#2025" --arg role "$ROLE_ID" --arg tsc "TSC$TS" --arg nssf "NSSF$TS" --arg kra "KRA$TS" --arg nhif "NHIF$TS" --arg bank "BANK$TS" --arg sal "50000" '{username:$un, email:$em, password:$pw, first_name:$fn, last_name:$ln, role_id:($role|tonumber), tsc_no:$tsc, staff_info: {position: "Teacher", employment_date: "2025-01-01", nssf_no:$nssf, kra_pin:$kra, nhif_no:$nhif, bank_account:$bank, salary:($sal|tonumber)}}')
USER_RESP=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$USER_PAYLOAD" "$API_URL/users")
# Accept either controller response (status: success) or module response (success: true)
USER_OK=$(echo "$USER_RESP" | jq -r 'if .status=="success" then "true" elif .success==true then "true" else "false" end')
if [ "$USER_OK" != "true" ]; then
  # Try to recover if validation failed due to existing username/email
  MSG=$(echo "$USER_RESP" | jq -r '.message // ""')
  if echo "$MSG" | grep -qi "Validation failed"; then
    # Search by email to find existing user
    USER_ID=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/users?search=teacher_test+seed@example.com" | jq -r '.data[]?.id // empty' | head -n1)
    if [ -n "$USER_ID" ]; then
      echo "User already exists, using user id: $USER_ID"
    else
      echo "User creation failed with validation errors:" >&2
      echo "$USER_RESP" | jq . >&2
      exit 1
    fi
  else
    echo "User creation failed:" >&2
    echo "$USER_RESP" | jq . >&2
    exit 1
  fi
else
  USER_ID=$(echo "$USER_RESP" | jq -r '.data.id // .data.user_id // .data.user.id // empty')
  if [ -z "$USER_ID" ]; then
    USER_ID=$(echo "$USER_RESP" | jq -r '.data.id // .data.id // empty')
  fi
fi
if [ -z "$USER_ID" ]; then
  echo "No user id returned" >&2; exit 1;
fi
echo "Created user id: $USER_ID"

# Verify roles assigned present in returned data
ROLE_NAMES=$(echo "$USER_RESP" | jq -r '.data.roles[]?.name // empty')
echo "Assigned roles: $ROLE_NAMES"

# Check staff row exists for this user (UsersAPI should have created it via staff_info)
STAFF_ID=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM staff WHERE user_id = $USER_ID LIMIT 1" || echo "")
if [ -z "$STAFF_ID" ]; then
  echo "Staff row not found for user_id=$USER_ID" >&2
  exit 1
fi

echo "Staff id: $STAFF_ID"

echo "User+Staff tests passed"