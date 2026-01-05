#!/usr/bin/env bash
set -euo pipefail

API_URL="http://localhost/Kingsway/api"
TOKEN="devtest"
MYSQL="/opt/lampp/bin/mysql -u root -padmin123"
DB_NAME="KingsWayAcademy"

echo "Running staff payroll validation tests"

# Create or find a Teacher role for testing
ROLE_NAME="TeacherTest_$(date +%s)"
ROLE_RESP=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$(jq -nc --arg name "$ROLE_NAME" --arg desc "Temporary test teacher role" '{name:$name, description:$desc}')" "$API_URL/system/roles")
ROLE_ID=$(echo "$ROLE_RESP" | jq -r '.data.id // empty')
if [ -z "$ROLE_ID" ]; then
  ROLE_ID=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/system/roles" | jq -r --arg rn "$ROLE_NAME" '.data[]? | select(.name==$rn) | .id' | head -n1)
fi
if [ -z "$ROLE_ID" ]; then
  echo "Failed to create or find role. Response:" >&2
  echo "$ROLE_RESP" | jq . >&2
  exit 1
fi

# Attempt to create a user with staff_info missing payroll fields
TS=$(date +%s%N | cut -c1-12)
USERNAME="teacher_paytest_${TS}"
EMAIL="teacher_paytest_${TS}@example.com"
USER_PAYLOAD=$(jq -n --arg un "$USERNAME" --arg em "$EMAIL" --arg fn "Teacher" --arg ln "PayTest" --arg pw "Str0ng!Pass#2025" --arg role "$ROLE_ID" '{username:$un, email:$em, password:$pw, first_name:$fn, last_name:$ln, role_id:($role|tonumber), staff_info: {position: "Teacher", employment_date: "2025-01-01"}}')
USER_RESP=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$USER_PAYLOAD" "$API_URL/users")

# Expect failure due to missing required payroll fields
USER_OK=$(echo "$USER_RESP" | jq -r 'if .status=="success" then "true" elif .success==true then "true" else "false" end')
if [ "$USER_OK" == "true" ]; then
  echo "ERROR: User creation unexpectedly succeeded despite missing payroll fields" >&2
  echo "$USER_RESP" | jq . >&2
  exit 1
fi

ERR_MSG=$(echo "$USER_RESP" | jq -r '.error // .message // ""')
if echo "$ERR_MSG" | grep -q "Missing required staff fields"; then
  echo "Validation correctly rejected missing payroll fields: $ERR_MSG"
else
  echo "Expected missing payroll fields error, but got: $ERR_MSG" >&2
  echo "$USER_RESP" | jq . >&2
  exit 1
fi

# Now create a user with complete staff_info (should succeed)
TS2=$(date +%s%N | cut -c1-12)
USERNAME2="teacher_payok_${TS2}"
EMAIL2="teacher_payok_${TS2}@example.com"
USER_PAYLOAD2=$(jq -n --arg un "$USERNAME2" --arg em "$EMAIL2" --arg fn "Teacher" --arg ln "PayOK" --arg pw "Str0ng!Pass#2025" --arg role "$ROLE_ID" --arg nssf "NSSF$TS2" --arg kra "KRA$TS2" --arg nhif "NHIF$TS2" --arg bank "BANK$TS2" --arg sal "60000" '{username:$un, email:$em, password:$pw, first_name:$fn, last_name:$ln, role_id:($role|tonumber), staff_info: {position: "Teacher", employment_date: "2025-01-01", nssf_no:$nssf, kra_pin:$kra, nhif_no:$nhif, bank_account:$bank, salary:($sal|tonumber), department_id:1, date_of_birth:"1990-01-01"}}')
USER_RESP2=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$USER_PAYLOAD2" "$API_URL/users")
USER_OK2=$(echo "$USER_RESP2" | jq -r 'if .status=="success" then "true" elif .success==true then "true" else "false" end')
if [ "$USER_OK2" != "true" ]; then
  echo "ERROR: Expected user creation to succeed when payroll fields provided" >&2
  echo "$USER_RESP2" | jq . >&2
  exit 1
fi
USER_ID2=$(echo "$USER_RESP2" | jq -r '.data.id // .data.user_id // empty')
if [ -z "$USER_ID2" ]; then
  echo "No user id returned for successful creation" >&2; exit 1;
fi
# Verify staff row exists
STAFF_ID2=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM staff WHERE user_id = $USER_ID2 LIMIT 1" || echo "")
if [ -z "$STAFF_ID2" ]; then
  echo "Staff row not created for user_id=$USER_ID2" >&2; exit 1;
fi

echo "Staff payroll validation tests passed"
