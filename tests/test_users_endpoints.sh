#!/bin/bash
# Test script for /api/users endpoints
# Logs all requests and responses to a text file

BASE_URL="http://localhost/Kingsway/api/users"
LOG_FILE="tests/users_test_results.txt"
COOKIE_JAR="tests/users_cookies.txt"

# Storage for test IDs
TEST_USER_ID=""
TEST_ROLE_ID=""
TEST_PERMISSION_ID=""

# Clean up previous logs
rm -f "$LOG_FILE" "$COOKIE_JAR"

echo "==== Testing /api/users endpoints ====" | tee -a "$LOG_FILE"

# Helper: Print and log
log() {
  echo -e "$1" | tee -a "$LOG_FILE"
}

# Helper: Extract ID from JSON response
extract_id() {
  local response="$1"
  local field="$2"
  echo "$response" | grep -o "\"$field\":[0-9]*" | grep -o '[0-9]*' | head -1
}

# Helper: Curl wrapper with authentication
TEST_TOKEN="devtest"
call_api() {
  local method="$1"
  local url="$2"
  local data="$3"
  if [[ "$method" == "GET" || "$method" == "DELETE" ]]; then
    resp=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X "$method" "$url" -H "X-Test-Token: $TEST_TOKEN" -b "$COOKIE_JAR")
  else
    resp=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X "$method" "$url" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d "$data" -b "$COOKIE_JAR")
  fi
  log "==== $method $url ===="
  log "$resp"
  echo "$resp"
}

# 1. /api/users/index
log "\n[1] Testing GET /api/users/index"
call_api GET "$BASE_URL/index" > /dev/null

# 2. /api/users/login
log "\n[2] Testing POST /api/users/login"
RESULT=$(call_api POST "$BASE_URL/login" '{"username":"updateduser2025","password":"testpass"}')
TEST_USER_ID=$(extract_id "$RESULT" "id")

# 3. /api/users (GET - List all users)
log "\n[3] Testing GET /api/users"
call_api GET "$BASE_URL" > /dev/null

# 4. /api/users (POST - Create user)
log "\n[4] Testing POST /api/users - Create User"
TS=$(date +%s)
RESULT=$(call_api POST "$BASE_URL" "{\"username\":\"testuser$TS\",\"email\":\"testuser$TS@school.com\",\"first_name\":\"Test\",\"last_name\":\"User\",\"password\":\"password123\",\"role_id\":1,\"status\":\"active\"}")
TEST_USER_ID=$(extract_id "$RESULT" "id")
if [ -n "$TEST_USER_ID" ]; then
  log "Captured User ID: $TEST_USER_ID"
fi

# 5. /api/users/{id} (GET - Get single user)
if [ -n "$TEST_USER_ID" ]; then
  log "\n[5] Testing GET /api/users/$TEST_USER_ID"
  call_api GET "$BASE_URL/$TEST_USER_ID" > /dev/null
fi

# 6. /api/users/{id} (PUT - Update user)
if [ -n "$TEST_USER_ID" ]; then
  log "\n[6] Testing PUT /api/users/$TEST_USER_ID - Update User"
  call_api PUT "$BASE_URL/$TEST_USER_ID" "{\"first_name\":\"Updated\",\"last_name\":\"TestUser\",\"status\":\"active\"}" > /dev/null
fi

# 7. /api/users/profile-get/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[7] Testing GET /api/users/profile-get/$TEST_USER_ID"
  call_api GET "$BASE_URL/profile-get/$TEST_USER_ID" > /dev/null
fi

# 8. /api/users/password-change/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[8] Testing PUT /api/users/password-change/$TEST_USER_ID"
  call_api PUT "$BASE_URL/password-change/$TEST_USER_ID" "{\"current_password\":\"password123\",\"new_password\":\"newpassword123\",\"confirm_password\":\"newpassword123\"}" > /dev/null
fi

# 9. /api/users/roles-get
log "\n[9] Testing GET /api/users/roles-get"
call_api GET "$BASE_URL/roles-get" > /dev/null

# 10. /api/users/role-assign/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[10] Testing POST /api/users/role-assign/$TEST_USER_ID"
  call_api POST "$BASE_URL/role-assign/$TEST_USER_ID" "{\"role_id\":1,\"role_name\":\"Headteacher\"}" > /dev/null
fi

# 11. /api/users/role-main/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[11] Testing GET /api/users/role-main/$TEST_USER_ID"
  call_api GET "$BASE_URL/role-main/$TEST_USER_ID" > /dev/null
fi

# 12. /api/users/role-extra/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[12] Testing GET /api/users/role-extra/$TEST_USER_ID"
  call_api GET "$BASE_URL/role-extra/$TEST_USER_ID" > /dev/null
fi

# 13. /api/users/with-role/{name}
log "\n[13] Testing GET /api/users/with-role/Headteacher"
call_api GET "$BASE_URL/with-role/Headteacher" > /dev/null

# 14. /api/users/with-multiple-roles
log "\n[14] Testing GET /api/users/with-multiple-roles"
call_api GET "$BASE_URL/with-multiple-roles" > /dev/null

# 15. /api/users/permissions-get
log "\n[15] Testing GET /api/users/permissions-get"
call_api GET "$BASE_URL/permissions-get" > /dev/null

# 16. /api/users/permissions-effective/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[16] Testing GET /api/users/permissions-effective/$TEST_USER_ID"
  call_api GET "$BASE_URL/permissions-effective/$TEST_USER_ID" > /dev/null
fi

# 17. /api/users/permissions-direct/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[17] Testing GET /api/users/permissions-direct/$TEST_USER_ID"
  call_api GET "$BASE_URL/permissions-direct/$TEST_USER_ID" > /dev/null
fi

# 18. /api/users/permissions-denied/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[18] Testing GET /api/users/permissions-denied/$TEST_USER_ID"
  call_api GET "$BASE_URL/permissions-denied/$TEST_USER_ID" > /dev/null
fi

# 19. /api/users/permissions-by-entity/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[19] Testing GET /api/users/permissions-by-entity/$TEST_USER_ID"
  call_api GET "$BASE_URL/permissions-by-entity/$TEST_USER_ID" > /dev/null
fi

# 20. /api/users/permissions-summary/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[20] Testing GET /api/users/permissions-summary/$TEST_USER_ID"
  call_api GET "$BASE_URL/permissions-summary/$TEST_USER_ID" > /dev/null
fi

# 21. /api/users/permissions-check/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[21] Testing POST /api/users/permissions-check/$TEST_USER_ID"
  call_api POST "$BASE_URL/permissions-check/$TEST_USER_ID" "{\"permission_code\":\"manage_students_view\"}" > /dev/null
fi

# 22. /api/users/permissions-check-multiple/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[22] Testing POST /api/users/permissions-check-multiple/$TEST_USER_ID"
  call_api POST "$BASE_URL/permissions-check-multiple/$TEST_USER_ID" "{\"permission_codes\":[\"manage_students_view\",\"manage_staff_view\"]}" > /dev/null
fi

# 23. /api/users/with-permission/{code}
log "\n[23] Testing GET /api/users/with-permission/manage_students_view"
call_api GET "$BASE_URL/with-permission/manage_students_view" > /dev/null

# 24. /api/users/permission-assign/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[24] Testing POST /api/users/permission-assign/$TEST_USER_ID"
  call_api POST "$BASE_URL/permission-assign/$TEST_USER_ID" "{\"permission_code\":\"view_financials\",\"permission_type\":\"grant\"}" > /dev/null
fi

# 25. /api/users/permissions-update/{id}
if [ -n "$TEST_USER_ID" ]; then
  log "\n[25] Testing PUT /api/users/permissions-update/$TEST_USER_ID"
  call_api PUT "$BASE_URL/permissions-update/$TEST_USER_ID" "{\"permissions\":[{\"permission_id\":1,\"permission_type\":\"grant\"},{\"permission_id\":2,\"permission_type\":\"deny\"}]}" > /dev/null
fi

# 26. /api/users/roles-bulk-assign-to-user
if [ -n "$TEST_USER_ID" ]; then
  log "\n[26] Testing POST /api/users/roles-bulk-assign-to-user"
  call_api POST "$BASE_URL/roles-bulk-assign-to-user" "{\"user_id\":$TEST_USER_ID,\"role_ids\":[1,2]}" > /dev/null
fi

# 27. /api/users/roles-bulk-revoke-from-user
if [ -n "$TEST_USER_ID" ]; then
  log "\n[27] Testing DELETE /api/users/roles-bulk-revoke-from-user"
  call_api DELETE "$BASE_URL/roles-bulk-revoke-from-user" "{\"user_id\":$TEST_USER_ID,\"role_ids\":[2]}" > /dev/null
fi

# 28. /api/users/permissions-bulk-assign-to-user
if [ -n "$TEST_USER_ID" ]; then
  log "\n[28] Testing POST /api/users/permissions-bulk-assign-to-user"
  call_api POST "$BASE_URL/permissions-bulk-assign-to-user" "{\"user_id\":$TEST_USER_ID,\"permissions\":[{\"permission_code\":\"manage_students_view\",\"permission_type\":\"grant\"},{\"permission_code\":\"manage_staff_view\",\"permission_type\":\"grant\"}]}" > /dev/null
fi

# 29. /api/users/permissions-bulk-revoke-from-user
if [ -n "$TEST_USER_ID" ]; then
  log "\n[29] Testing DELETE /api/users/permissions-bulk-revoke-from-user"
  call_api DELETE "$BASE_URL/permissions-bulk-revoke-from-user" "{\"user_id\":$TEST_USER_ID,\"permissions\":[\"manage_students_view\"]}" > /dev/null
fi

# 30. /api/users/with-temporary-permissions
log "\n[30] Testing GET /api/users/with-temporary-permissions"
call_api GET "$BASE_URL/with-temporary-permissions" > /dev/null

# 31. /api/users/sidebar-items
if [ -n "$TEST_USER_ID" ]; then
  log "\n[31] Testing GET /api/users/sidebar-items?user_id=$TEST_USER_ID"
  call_api GET "$BASE_URL/sidebar-items?user_id=$TEST_USER_ID" > /dev/null
fi

# 32. /api/users/{id} (DELETE - Delete user)
if [ -n "$TEST_USER_ID" ]; then
  log "\n[32] Testing DELETE /api/users/$TEST_USER_ID"
  call_api DELETE "$BASE_URL/$TEST_USER_ID" > /dev/null
fi

log "\n==== All /api/users endpoint tests completed ===="
