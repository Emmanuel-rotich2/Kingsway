#!/bin/bash
# Test script for /api/admission endpoints
# Tests the complete admission workflow

BASE_URL="http://localhost/Kingsway/api/admission"
LOG_FILE="tests/admission_test_results.txt"
COOKIE_JAR="tests/admission_cookies.txt"

# Storage for application IDs from responses
APPLICATION_ID=""
DOCUMENT_ID=""
INTERVIEW_ID=""

# Clean up previous logs
rm -f "$LOG_FILE" "$COOKIE_JAR"

echo "==== Testing /api/admission endpoints ====" | tee -a "$LOG_FILE"

# Helper: Print and log
log() {
  echo -e "$1" | tee -a "$LOG_FILE"
}

# Helper: Extract ID from JSON response (handles both number and string)
extract_id() {
  local response="$1"
  local field="$2"
  # Try to extract from "field":"value" format first (string)
  echo "$response" | grep -o "\"$field\":\"[0-9]*\"" | grep -o '[0-9]*' | head -1 || \
  # Then try "field":value format (number)
  echo "$response" | grep -o "\"$field\":[0-9]*" | grep -o '[0-9]*' | head -1
}

# Helper: Curl wrapper with X-Test-Token header for authentication
TEST_TOKEN="devtest"
call_api() {
  local method="$1"
  local url="$2"
  local data="$3"
  local extra="$4"
  if [[ "$method" == "GET" || "$method" == "DELETE" ]]; then
    resp=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X "$method" "$url" -H "X-Test-Token: $TEST_TOKEN" -b "$COOKIE_JAR" $extra)
  else
    resp=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X "$method" "$url" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d "$data" -b "$COOKIE_JAR" $extra)
  fi
  log "==== $method $url ===="
  log "$resp"
  echo "$resp"  # Also return for processing
}

# 1. GET /api/admission/index
log "\n========== Testing /api/admission/index =========="
call_api GET "$BASE_URL/index" > /dev/null

# 2. POST /api/admission/submit-application
log "\n========== Testing POST /api/admission/submit-application =========="
# Use parent_id=1 (Jane Doe - created in database)
response=$(call_api POST "$BASE_URL/submit-application" '{
  "applicant_name": "John Paul Doe",
  "date_of_birth": "2010-05-15",
  "gender": "male",
  "grade_applying_for": "Grade1",
  "academic_year": 2025,
  "parent_id": 1,
  "previous_school": "Springfield Primary",
  "has_special_needs": 0,
  "special_needs_details": "",
  "started_by": 1
}')
APPLICATION_ID=$(extract_id "$response" "application_id")
if [[ -z "$APPLICATION_ID" ]]; then
  APPLICATION_ID=$(echo "$response" | grep -o '"id":[0-9]*' | grep -o '[0-9]*' | head -1)
fi
log "Extracted APPLICATION_ID: $APPLICATION_ID"

# 3. POST /api/admission/upload-document
log "\n========== Testing POST /api/admission/upload-document =========="
if [[ -n "$APPLICATION_ID" ]]; then
  # Create a test file
  echo "Test document content" > /tmp/test_document.txt
  call_api POST "$BASE_URL/upload-document" "{
    \"application_id\": $APPLICATION_ID,
    \"document_type\": \"birth_certificate\",
    \"file\": \"/tmp/test_document.txt\",
    \"started_by\": 1
  }" > /dev/null
else
  log "Skipping upload-document: APPLICATION_ID not found"
fi

# 4. POST /api/admission/verify-document
log "\n========== Testing POST /api/admission/verify-document =========="
if [[ -n "$APPLICATION_ID" ]]; then
  call_api POST "$BASE_URL/verify-document" "{
    \"document_id\": 1,
    \"status\": \"approved\",
    \"notes\": \"Document verified and authentic\",
    \"started_by\": 1
  }" > /dev/null
else
  log "Skipping verify-document: APPLICATION_ID not found"
fi

# 5. POST /api/admission/schedule-interview
log "\n========== Testing POST /api/admission/schedule-interview =========="
if [[ -n "$APPLICATION_ID" ]]; then
  call_api POST "$BASE_URL/schedule-interview" "{
    \"application_id\": $APPLICATION_ID,
    \"interview_date\": \"2025-12-10\",
    \"interview_time\": \"10:00:00\",
    \"venue\": \"Main Office\",
    \"started_by\": 1
  }" > /dev/null
else
  log "Skipping schedule-interview: APPLICATION_ID not found"
fi

# 6. POST /api/admission/record-interview-results
log "\n========== Testing POST /api/admission/record-interview-results =========="
if [[ -n "$APPLICATION_ID" ]]; then
  call_api POST "$BASE_URL/record-interview-results" "{
    \"application_id\": $APPLICATION_ID,
    \"assessment_data\": {
      \"communication_score\": 8,
      \"reasoning_score\": 7,
      \"overall_impression\": \"Good candidate, meets basic requirements\",
      \"interview_date\": \"2025-12-10\",
      \"interviewed_by\": 1
    },
    \"started_by\": 1
  }" > /dev/null
else
  log "Skipping record-interview-results: APPLICATION_ID not found"
fi

# 7. POST /api/admission/generate-placement-offer
log "\n========== Testing POST /api/admission/generate-placement-offer =========="
if [[ -n "$APPLICATION_ID" ]]; then
  call_api POST "$BASE_URL/generate-placement-offer" "{
    \"application_id\": $APPLICATION_ID,
    \"assigned_class_id\": 1,
    \"started_by\": 1
  }" > /dev/null
else
  log "Skipping generate-placement-offer: APPLICATION_ID not found"
fi

# 8. POST /api/admission/record-fee-payment
log "\n========== Testing POST /api/admission/record-fee-payment =========="
if [[ -n "$APPLICATION_ID" ]]; then
  call_api POST "$BASE_URL/record-fee-payment" "{
    \"application_id\": $APPLICATION_ID,
    \"payment_data\": {
      \"amount\": 50000,
      \"currency\": \"KES\",
      \"payment_method\": \"bank_transfer\",
      \"receipt_number\": \"BT-2025-001\",
      \"payment_date\": \"2025-12-05\",
      \"verified_by\": 1
    },
    \"started_by\": 1
  }" > /dev/null
else
  log "Skipping record-fee-payment: APPLICATION_ID not found"
fi

# 9. POST /api/admission/complete-enrollment
log "\n========== Testing POST /api/admission/complete-enrollment =========="
if [[ -n "$APPLICATION_ID" ]]; then
  call_api POST "$BASE_URL/complete-enrollment" "{
    \"application_id\": $APPLICATION_ID,
    \"started_by\": 1
  }" > /dev/null
else
  log "Skipping complete-enrollment: APPLICATION_ID not found"
fi

log "\n========== Admission API Tests Complete ==========="
