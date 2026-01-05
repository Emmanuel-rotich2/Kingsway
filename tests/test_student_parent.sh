#!/usr/bin/env bash
set -euo pipefail

API_URL="http://localhost/Kingsway/api"
TOKEN="devtest"
MYSQL="/opt/lampp/bin/mysql -u root -padmin123"
DB_NAME="KingsWayAcademy"

echo "Running student+parent tests"

# Get an existing stream id (fallback to DB query)
STREAM_ID=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM class_streams LIMIT 1" | tr -d '\n')
if [ -z "$STREAM_ID" ]; then
  echo "No stream found in DB, aborting" >&2; exit 1;
fi

echo "Using stream id: $STREAM_ID"

# Build student payload with parent_info
TS=$(date +%s%N | cut -c1-12)
PARENT=$(jq -n --arg fn "Parent" --arg ln "Test" --arg phone "+2547000$TS" --arg em "parent_test_${TS}@example.com" '{first_name:$fn, last_name:$ln, phone_1:$phone, email:$em}')
ADMISSION_NO="S2025-$TS"
STUDENT_PAYLOAD=$(jq -n --arg adm "$ADMISSION_NO" --arg fn "Student" --arg ln "Seed" --arg dob "2015-01-02" --arg gender "male" --argjson sid $STREAM_ID --arg admd "2025-01-01" --argjson parent "$PARENT" '{admission_no:$adm, first_name:$fn, last_name:$ln, date_of_birth:$dob, gender:$gender, stream_id:($sid|tonumber), admission_date:$admd, parent_info:$parent}')

STUDENT_RESP=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$STUDENT_PAYLOAD" "$API_URL/students")
echo "STUDENT_RESP: $STUDENT_RESP" >&2
if [ "$(echo "$STUDENT_RESP" | jq -r '.status // empty')" != "success" ]; then
  echo "Student creation failed:" >&2
  echo "$STUDENT_RESP" | jq . >&2
  exit 1
fi
STUDENT_ID=$(echo "$STUDENT_RESP" | jq -r '.data.id // .data.data.id // .data.data.data.id // empty')
if [ -z "$STUDENT_ID" ]; then echo "No student id returned" >&2; exit 1; fi

echo "Created student id: $STUDENT_ID"

# Verify parent linked
PARENTS_RESP=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/students/parents-get/$STUDENT_ID")
if [ "$(echo "$PARENTS_RESP" | jq -r '.status // empty')" != "success" ]; then
  echo "Fetching parents failed" >&2; echo "$PARENTS_RESP" | jq . >&2; exit 1
fi
PARENT_COUNT=$(echo "$PARENTS_RESP" | jq '.data | length')
if [ "$PARENT_COUNT" -lt 1 ]; then echo "No parent linked" >&2; exit 1; fi

echo "Parent linked count: $PARENT_COUNT"

echo "Student+Parent tests passed"