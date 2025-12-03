#!/bin/bash
# Test script for /api/auth endpoints
# Tests authentication operations

BASE_URL="http://localhost/Kingsway/api/auth"
LOG_FILE="tests/auth_test_results.txt"
COOKIE_JAR="tests/auth_cookies.txt"

# Storage for auth tokens
AUTH_TOKEN=""
REFRESH_TOKEN=""

# Clean up previous logs
rm -f "$LOG_FILE" "$COOKIE_JAR"

echo "==== Testing /api/auth endpoints ====" | tee -a "$LOG_FILE"

# Helper: Print and log
log() {
  echo -e "$1" | tee -a "$LOG_FILE"
}

# Helper: Extract token from JSON response
extract_token() {
  local response="$1"
  local field="$2"
  echo "$response" | grep -o "\"$field\":\"[^\"]*" | grep -o '[^"]*$' | head -1
}

# Helper: Curl wrapper with X-Test-Token header for authentication
TEST_TOKEN="devtest"
call_api() {
  local method="$1"
  local url="$2"
  local data="$3"
  local extra="$4"
  if [[ "$method" == "GET" || "$method" == "DELETE" ]]; then
    resp=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X "$method" "$url" -H "X-Test-Token: $TEST_TOKEN" -b "$COOKIE_JAR" -c "$COOKIE_JAR" $extra)
  else
    resp=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X "$method" "$url" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d "$data" -b "$COOKIE_JAR" -c "$COOKIE_JAR" $extra)
  fi
  log "==== $method $url ===="
  log "$resp"
  echo "$resp"  # Also return for processing
}

# 1. GET /api/auth/index
log "\n========== Testing GET /api/auth/index =========="
call_api GET "$BASE_URL/index" > /dev/null

# 2. POST /api/auth/login
log "\n========== Testing POST /api/auth/login =========="
# Test with valid credentials
response=$(call_api POST "$BASE_URL/login" '{
  "email": "admin@kingsway.edu",
  "password": "password123",
  "remember_me": true
}')
AUTH_TOKEN=$(extract_token "$response" "token")
REFRESH_TOKEN=$(extract_token "$response" "refresh_token")
log "Extracted AUTH_TOKEN: ${AUTH_TOKEN:0:20}..."
log "Extracted REFRESH_TOKEN: ${REFRESH_TOKEN:0:20}..."

# Test with invalid credentials
log "\n========== Testing POST /api/auth/login (Invalid Credentials) =========="
call_api POST "$BASE_URL/login" '{
  "email": "invalid@example.com",
  "password": "wrongpassword"
}' > /dev/null

# 3. POST /api/auth/forgot-password
log "\n========== Testing POST /api/auth/forgot-password =========="
call_api POST "$BASE_URL/forgot-password" '{
  "email": "admin@kingsway.edu"
}' > /dev/null

# 4. POST /api/auth/reset-password
log "\n========== Testing POST /api/auth/reset-password =========="
# Note: In a real scenario, you'd need a valid reset token from the forgot-password response
call_api POST "$BASE_URL/reset-password" '{
  "email": "admin@kingsway.edu",
  "reset_token": "fake_reset_token_for_testing",
  "new_password": "newpassword123",
  "confirm_password": "newpassword123"
}' > /dev/null

# 5. POST /api/auth/refresh-token
log "\n========== Testing POST /api/auth/refresh-token =========="
if [[ -n "$REFRESH_TOKEN" ]]; then
  call_api POST "$BASE_URL/refresh-token" "{
    \"refresh_token\": \"$REFRESH_TOKEN\"
  }" > /dev/null
else
  log "Skipping refresh-token: REFRESH_TOKEN not found from login response"
fi

# 6. POST /api/auth/logout
log "\n========== Testing POST /api/auth/logout =========="
if [[ -n "$AUTH_TOKEN" ]]; then
  call_api POST "$BASE_URL/logout" "{
    \"token\": \"$AUTH_TOKEN\"
  }" > /dev/null
else
  log "Skipping logout: AUTH_TOKEN not found from login response"
fi

log "\n========== Auth API Tests Complete ==========="
