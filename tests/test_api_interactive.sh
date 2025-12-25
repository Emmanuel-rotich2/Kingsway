#!/bin/bash

################################################################################
# INTERACTIVE API ENDPOINT TESTER
# Shows actual endpoint outputs with your configuration
################################################################################

# YOUR CONFIGURATION
BASE_URL="http://localhost/Kingsway"
TEST_TOKEN="devtest"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}SCHEDULES API ENDPOINT TESTER${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo "Configuration:"
echo "  Base URL: $BASE_URL"
echo "  Token: $TEST_TOKEN"
echo ""

# Function to test endpoint and show output
test_api_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$description${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${GREEN}Request:${NC}"
    echo "  Method: $method"
    echo "  URL: $BASE_URL/api/$endpoint"
    if [ ! -z "$data" ]; then
        echo "  Payload:"
        echo "$data" | jq . 2>/dev/null || echo "$data"
    fi
    echo ""
    
    echo -e "${GREEN}Response:${NC}"
    
    # Build curl command
    local curl_cmd="curl -s -w '\n\n=== HTTP Status: %{http_code} ===\n' -X $method"
    
    if [ ! -z "$TEST_TOKEN" ]; then
        curl_cmd="$curl_cmd -H 'Authorization: Bearer $TEST_TOKEN'"
    fi
    
    if [ ! -z "$data" ]; then
        curl_cmd="$curl_cmd -H 'Content-Type: application/json' -d '$data'"
    fi
    
    curl_cmd="$curl_cmd '$BASE_URL/api/$endpoint'"
    
    # Execute and format response
    eval "$curl_cmd" | jq . 2>/dev/null || eval "$curl_cmd"
    
    echo ""
}

################################################################################
# TEST 1: SCHEDULES INDEX
################################################################################
test_api_endpoint "GET" "schedules/index" "" "TEST 1: Get Schedules API Status"

################################################################################
# TEST 2: LIST ALL SCHEDULES
################################################################################
test_api_endpoint "GET" "schedules" "" "TEST 2: List All Schedules"

################################################################################
# TEST 3: CREATE NEW SCHEDULE
################################################################################
CREATE_SCHEDULE_PAYLOAD='{
    "title": "Math Class Session",
    "description": "Regular class session for Grade 6",
    "start_time": "2025-01-15 09:00:00",
    "end_time": "2025-01-15 10:00:00"
}'
test_api_endpoint "POST" "schedules" "$CREATE_SCHEDULE_PAYLOAD" "TEST 3: Create New Schedule"

################################################################################
# TEST 4: GET TIMETABLE ENTRIES
################################################################################
test_api_endpoint "GET" "schedules/timetable-get" "" "TEST 4: Get All Timetable Entries"

################################################################################
# TEST 5: CREATE TIMETABLE ENTRY
################################################################################
CREATE_TIMETABLE_PAYLOAD='{
    "class_id": 1,
    "day_of_week": "Monday",
    "start_time": "09:00:00",
    "end_time": "10:00:00",
    "subject_id": 5,
    "teacher_id": 10,
    "room_id": 3,
    "status": "active"
}'
test_api_endpoint "POST" "schedules/timetable-create" "$CREATE_TIMETABLE_PAYLOAD" "TEST 5: Create Timetable Entry"

################################################################################
# TEST 6: GET EXAM SCHEDULES
################################################################################
test_api_endpoint "GET" "schedules/exam-get" "" "TEST 6: Get Exam Schedules"

################################################################################
# TEST 7: CREATE EXAM SCHEDULE
################################################################################
CREATE_EXAM_PAYLOAD='{
    "class_id": 1,
    "subject_id": 5,
    "exam_date": "2025-02-10",
    "start_time": "09:00:00",
    "end_time": "11:00:00",
    "room_id": 3,
    "invigilator_id": 15,
    "status": "scheduled"
}'
test_api_endpoint "POST" "schedules/exam-create" "$CREATE_EXAM_PAYLOAD" "TEST 7: Create Exam Schedule"

################################################################################
# TEST 8: GET TEACHER SCHEDULE
################################################################################
test_api_endpoint "GET" "schedules/teacher-schedule?teacher_id=10&term_id=1" "" "TEST 8: Get Teacher Schedule"

################################################################################
# TEST 9: GET MASTER SCHEDULE
################################################################################
test_api_endpoint "GET" "schedules/master-schedule" "" "TEST 9: Get Master Schedule (Admin View)"

################################################################################
# TEST 10: GET SCHEDULE ANALYTICS
################################################################################
test_api_endpoint "GET" "schedules/analytics" "" "TEST 10: Get Schedule Analytics"

################################################################################
# TEST 11: SCHOOLCONFIG - GET CURRENT CONFIG
################################################################################
test_api_endpoint "GET" "schoolconfig" "" "TEST 11: Get School Configuration"

################################################################################
# TEST 12: SCHOOLCONFIG - UPDATE SCHOOL CONFIG
################################################################################
UPDATE_CONFIG_PAYLOAD='{
    "school_name": "Kingsway Academy",
    "school_code": "KWA001",
    "motto": "Excellence in Education",
    "vision": "Develop confident global citizens"
}'
test_api_endpoint "POST" "schoolconfig" "$UPDATE_CONFIG_PAYLOAD" "TEST 12: Update School Configuration"

################################################################################
# TEST 13: SCHOOLCONFIG - HEALTH CHECK
################################################################################
test_api_endpoint "GET" "schoolconfig/health" "" "TEST 13: System Health Check"

################################################################################
# TEST 14: GET ACTIVITY SCHEDULES
################################################################################
test_api_endpoint "GET" "schedules/activity-get" "" "TEST 14: Get Activity Schedules"

################################################################################
# TEST 15: CREATE ACTIVITY SCHEDULE
################################################################################
CREATE_ACTIVITY_PAYLOAD='{
    "activity_id": 2,
    "schedule_date": "2025-01-17",
    "day_of_week": "Wednesday",
    "start_time": "15:30:00",
    "end_time": "17:00:00",
    "venue": "School Hall"
}'
test_api_endpoint "POST" "schedules/activity-create" "$CREATE_ACTIVITY_PAYLOAD" "TEST 15: Create Activity Schedule"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}TESTING COMPLETE${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Tips:"
echo "  1. Check if HTTP Status is 200 (success) or 201 (created)"
echo "  2. Look for 'status: success' in the JSON response"
echo "  3. HTTP 000 means server is not reachable"
echo "  4. Update BASE_URL if your server is different"
echo "  5. Remove TEST_TOKEN if your API doesn't require authentication"
echo ""
