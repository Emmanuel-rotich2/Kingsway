#!/bin/bash

################################################################################
# COMPREHENSIVE API TEST SCRIPT FOR SCHEDULES AND SCHOOLCONFIG ENDPOINTS
################################################################################
# 
# Purpose: Test all REST endpoints for /api/schedules and /api/schoolconfig
# 
# Database Structure:
# - class_schedules: Stores class timetable (day_of_week, start_time, end_time, teacher, room, subject)
# - exam_schedules: Exam schedule (exam_date, start_time, end_time, room, invigilator)
# - activity_schedule: Co-curricular activities (activity_id, schedule_date, start_time, end_time, venue)
# - route_schedules: Transport schedules (route_id, day_of_week, direction, departure_time)
# - schedules: Generic schedule table (title, description, start_time, end_time)
# - school_configuration: School settings (school_name, school_code, logo_url, etc.)
#
# API Response Format (Unified):
# {
#   "status": "success|error",
#   "message": "Human-readable message",
#   "data": null|object|array,
#   "code": 200,
#   "timestamp": "2024-11-14T10:30:00Z",
#   "request_id": "req_12345"
# }
#
# Data Payloads:
# - Class Schedule: { class_id, day_of_week, start_time, end_time, subject_id, teacher_id, room_id, status }
# - Exam Schedule: { class_id, subject_id, exam_date, start_time, end_time, room_id, invigilator_id, status }
# - Activity Schedule: { activity_id, schedule_date, day_of_week, start_time, end_time, venue }
# - Route Schedule: { route_id, day_of_week, direction, departure_time, status }
# - School Config: { school_name, school_code, logo_url, favicon_url, motto, vision, mission, core_values }
#
################################################################################

# Configuration
# IMPORTANT: Update these values to match your server setup
BASE_URL="http://localhost/Kingsway"  # Base URL of your Kingsway installation
API_SCHEDULES_ENDPOINT="${BASE_URL}/api/schedules"
API_SCHOOLCONFIG_ENDPOINT="${BASE_URL}/api/schoolconfig"
TEST_TOKEN="devtest"  # Authentication token if required
TIMESTAMP=$(date +%s)
LOG_FILE="api_test_${TIMESTAMP}.log"
RESULTS_FILE="test_schedule_module_results_${TIMESTAMP}.txt"
JSON_RESULTS="api_test_results_${TIMESTAMP}.json"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0
TOTAL_TESTS=0

################################################################################
# HELPER FUNCTIONS
################################################################################

# Log function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Print header
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

# Test API endpoint with curl
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local test_name=$4
    local expected_status=$5
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    # Build curl command with token authentication
    local curl_cmd="curl -s -w '\n%{http_code}' -X $method"
    
    # Add authentication header if token is set
    if [ ! -z "$TEST_TOKEN" ]; then
        curl_cmd="$curl_cmd -H 'Authorization: Bearer $TEST_TOKEN'"
    fi
    
    if [ ! -z "$data" ]; then
        curl_cmd="$curl_cmd -H 'Content-Type: application/json' -d '$data'"
    fi
    
    curl_cmd="$curl_cmd '$BASE_URL/api/$endpoint'"
    
    # Execute request
    local response=$(eval "$curl_cmd")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | sed '$d')
    
    # Log request details
    log "Test #$TOTAL_TESTS: $test_name"
    log "  Method: $method | Endpoint: $endpoint"
    log "  HTTP Status: $http_code"
    
    # Check response
    if [ "$http_code" == "$expected_status" ] || [ -z "$expected_status" ]; then
        echo -e "${GREEN}✓ PASSED${NC}: $test_name (HTTP $http_code)" | tee -a "$LOG_FILE"
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        echo -e "${RED}✗ FAILED${NC}: $test_name (Expected: $expected_status, Got: $http_code)" | tee -a "$LOG_FILE"
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
    
    log "  Response: $body"
    echo "$body" >> "$RESULTS_FILE"
    log "---"
}

# Validate JSON response
validate_json_response() {
    local response=$1
    local has_status=$(echo "$response" | grep -o '"status"' | wc -l)
    local has_message=$(echo "$response" | grep -o '"message"' | wc -l)
    
    if [ $has_status -gt 0 ] && [ $has_message -gt 0 ]; then
        return 0
    else
        return 1
    fi
}

# Extract data from response
extract_json_field() {
    local json=$1
    local field=$2
    echo "$json" | grep -o "\"$field\":\"[^\"]*\"" | cut -d'"' -f4
}

################################################################################
# TEST INITIALIZATION
################################################################################

log "=== SCHEDULES & SCHOOLCONFIG API TEST SUITE STARTED ==="
log "Base URL: $BASE_URL"
log "API Endpoint: $API_SCHEDULES_ENDPOINT"
log "Authentication Token: $TEST_TOKEN"
log "Test Timestamp: $(date)"
log "Output Files: $LOG_FILE, $RESULTS_FILE, $JSON_RESULTS"

# Initialize results file
echo "{\"tests\": [" > "$JSON_RESULTS"

################################################################################
# SECTION 1: SCHEDULES - BASE CRUD OPERATIONS
################################################################################

print_header "SECTION 1: BASE CRUD OPERATIONS (/api/schedules)"

# 1.1 GET /api/schedules/index
test_endpoint "GET" "schedules/index" "" "GET Schedules Index" "200"

# 1.2 GET /api/schedules (List all schedules)
test_endpoint "GET" "schedules" "" "GET All Schedules" "200"

# 1.3 POST /api/schedules (Create new schedule)
SCHEDULE_PAYLOAD='{
    "title": "Class Session",
    "description": "Regular class session for Grade 6",
    "start_time": "2024-01-15 09:00:00",
    "end_time": "2024-01-15 10:00:00"
}'
test_endpoint "POST" "schedules" "$SCHEDULE_PAYLOAD" "POST Create Schedule" "201"

# 1.4 GET /api/schedules/{id} (Get single schedule)
test_endpoint "GET" "schedules/1" "" "GET Schedule by ID" "200"

# 1.5 PUT /api/schedules/{id} (Update schedule)
UPDATE_PAYLOAD='{
    "title": "Class Session Updated",
    "description": "Updated class session"
}'
test_endpoint "PUT" "schedules/1" "$UPDATE_PAYLOAD" "PUT Update Schedule" "200"

# 1.6 DELETE /api/schedules/{id} (Delete schedule)
test_endpoint "DELETE" "schedules/1" "" "DELETE Schedule" "204"

################################################################################
# SECTION 2: TIMETABLE OPERATIONS
################################################################################

print_header "SECTION 2: TIMETABLE OPERATIONS (/api/schedules/timetable)"

# 2.1 GET /api/schedules/timetable-get (Get timetable entries)
test_endpoint "GET" "schedules/timetable-get" "" "GET Timetable Entries" "200"

# 2.2 GET /api/schedules/timetable-get?id=1 (Get specific timetable)
test_endpoint "GET" "schedules/timetable-get?id=1" "" "GET Timetable by ID" "200"

# 2.3 POST /api/schedules/timetable-create (Create timetable entry)
TIMETABLE_PAYLOAD='{
    "class_id": 1,
    "day_of_week": "Monday",
    "start_time": "09:00:00",
    "end_time": "10:00:00",
    "subject_id": 5,
    "teacher_id": 10,
    "room_id": 3,
    "status": "active"
}'
test_endpoint "POST" "schedules/timetable-create" "$TIMETABLE_PAYLOAD" "POST Create Timetable Entry" "201"

################################################################################
# SECTION 3: EXAM SCHEDULES
################################################################################

print_header "SECTION 3: EXAM SCHEDULES (/api/schedules/exam)"

# 3.1 GET /api/schedules/exam-get (Get exam schedules)
test_endpoint "GET" "schedules/exam-get" "" "GET Exam Schedules" "200"

# 3.2 GET /api/schedules/exam-get?id=1 (Get specific exam schedule)
test_endpoint "GET" "schedules/exam-get?id=1" "" "GET Exam Schedule by ID" "200"

# 3.3 POST /api/schedules/exam-create (Create exam schedule)
EXAM_PAYLOAD='{
    "class_id": 1,
    "subject_id": 5,
    "exam_date": "2024-02-10",
    "start_time": "09:00:00",
    "end_time": "11:00:00",
    "room_id": 3,
    "invigilator_id": 15,
    "status": "scheduled"
}'
test_endpoint "POST" "schedules/exam-create" "$EXAM_PAYLOAD" "POST Create Exam Schedule" "201"

################################################################################
# SECTION 4: EVENTS
################################################################################

print_header "SECTION 4: EVENTS (/api/schedules/events)"

# 4.1 GET /api/schedules/events-get (Get events)
test_endpoint "GET" "schedules/events-get" "" "GET Events" "200"

# 4.2 GET /api/schedules/events-get?id=1 (Get specific event)
test_endpoint "GET" "schedules/events-get?id=1" "" "GET Event by ID" "200"

# 4.3 POST /api/schedules/events-create (Create event)
EVENT_PAYLOAD='{
    "title": "School Assembly",
    "description": "Weekly assembly for all students",
    "event_date": "2024-01-19",
    "start_time": "08:00:00",
    "end_time": "08:30:00",
    "venue": "School Grounds",
    "event_type": "assembly"
}'
test_endpoint "POST" "schedules/events-create" "$EVENT_PAYLOAD" "POST Create Event" "201"

################################################################################
# SECTION 5: ACTIVITY SCHEDULES
################################################################################

print_header "SECTION 5: ACTIVITY SCHEDULES (/api/schedules/activity)"

# 5.1 GET /api/schedules/activity-get (Get activity schedules)
test_endpoint "GET" "schedules/activity-get" "" "GET Activity Schedules" "200"

# 5.2 GET /api/schedules/activity-get?id=1 (Get specific activity schedule)
test_endpoint "GET" "schedules/activity-get?id=1" "" "GET Activity Schedule by ID" "200"

# 5.3 POST /api/schedules/activity-create (Create activity schedule)
ACTIVITY_PAYLOAD='{
    "activity_id": 2,
    "schedule_date": "2024-01-17",
    "day_of_week": "Wednesday",
    "start_time": "15:30:00",
    "end_time": "17:00:00",
    "venue": "School Hall"
}'
test_endpoint "POST" "schedules/activity-create" "$ACTIVITY_PAYLOAD" "POST Create Activity Schedule" "201"

################################################################################
# SECTION 6: ROOMS MANAGEMENT
################################################################################

print_header "SECTION 6: ROOMS MANAGEMENT (/api/schedules/rooms)"

# 6.1 GET /api/schedules/rooms-get (Get rooms)
test_endpoint "GET" "schedules/rooms-get" "" "GET Rooms" "200"

# 6.2 GET /api/schedules/rooms-get?id=1 (Get specific room)
test_endpoint "GET" "schedules/rooms-get?id=1" "" "GET Room by ID" "200"

# 6.3 POST /api/schedules/rooms-create (Create room)
ROOM_PAYLOAD='{
    "name": "Class 6A",
    "building": "Main Block",
    "floor": 1,
    "capacity": 40,
    "features": "Whiteboard, Projector, AC",
    "status": "active"
}'
test_endpoint "POST" "schedules/rooms-create" "$ROOM_PAYLOAD" "POST Create Room" "201"

################################################################################
# SECTION 7: SCHEDULED REPORTS
################################################################################

print_header "SECTION 7: SCHEDULED REPORTS (/api/schedules/reports)"

# 7.1 GET /api/schedules/reports-get (Get scheduled reports)
test_endpoint "GET" "schedules/reports-get" "" "GET Scheduled Reports" "200"

# 7.2 GET /api/schedules/reports-get?id=1 (Get specific report schedule)
test_endpoint "GET" "schedules/reports-get?id=1" "" "GET Report Schedule by ID" "200"

# 7.3 POST /api/schedules/reports-create (Create scheduled report)
REPORT_PAYLOAD='{
    "report_type": "timetable_conflict",
    "report_name": "Weekly Timetable Conflicts",
    "schedule_frequency": "weekly",
    "next_run_date": "2024-01-22",
    "recipients": ["admin@school.com", "principal@school.com"]
}'
test_endpoint "POST" "schedules/reports-create" "$REPORT_PAYLOAD" "POST Create Scheduled Report" "201"

################################################################################
# SECTION 8: ROUTE SCHEDULES (TRANSPORT)
################################################################################

print_header "SECTION 8: ROUTE SCHEDULES (/api/schedules/route)"

# 8.1 GET /api/schedules/route-get (Get route schedules)
test_endpoint "GET" "schedules/route-get" "" "GET Route Schedules" "200"

# 8.2 GET /api/schedules/route-get?id=1 (Get specific route schedule)
test_endpoint "GET" "schedules/route-get?id=1" "" "GET Route Schedule by ID" "200"

# 8.3 POST /api/schedules/route-create (Create route schedule)
ROUTE_PAYLOAD='{
    "route_id": 1,
    "day_of_week": "Monday",
    "direction": "pickup",
    "departure_time": "07:00:00",
    "status": "active"
}'
test_endpoint "POST" "schedules/route-create" "$ROUTE_PAYLOAD" "POST Create Route Schedule" "201"

################################################################################
# SECTION 9: ADVANCED SCHEDULE ENDPOINTS
################################################################################

print_header "SECTION 9: ADVANCED SCHEDULE ENDPOINTS"

# 9.1 GET /api/schedules/teacher-schedule (Get teacher schedule)
test_endpoint "GET" "schedules/teacher-schedule?teacher_id=10&term_id=1" "" "GET Teacher Schedule" "200"

# 9.2 GET /api/schedules/subject-teaching-load (Get subject teaching load)
test_endpoint "GET" "schedules/subject-teaching-load?subject_id=5&term_id=1" "" "GET Subject Teaching Load" "200"

# 9.3 GET /api/schedules/all-activity-schedules (Get all activity schedules)
test_endpoint "GET" "schedules/all-activity-schedules" "" "GET All Activity Schedules" "200"

# 9.4 GET /api/schedules/driver-schedule (Get driver schedule)
test_endpoint "GET" "schedules/driver-schedule?driver_id=20&term_id=1" "" "GET Driver Schedule" "200"

# 9.5 GET /api/schedules/staff-duty-schedule (Get staff duty schedule)
test_endpoint "GET" "schedules/staff-duty-schedule?staff_id=15&term_id=1" "" "GET Staff Duty Schedule" "200"

# 9.6 GET /api/schedules/master-schedule (Get master schedule)
test_endpoint "GET" "schedules/master-schedule" "" "GET Master Schedule" "200"

# 9.7 GET /api/schedules/analytics (Get schedule analytics)
test_endpoint "GET" "schedules/analytics" "" "GET Schedule Analytics" "200"

# 9.8 GET /api/schedules/student-schedules (Get student schedules)
test_endpoint "GET" "schedules/student-schedules?student_id=5&term_id=1" "" "GET Student Schedules" "200"

# 9.9 GET /api/schedules/staff-schedules (Get staff schedules)
test_endpoint "GET" "schedules/staff-schedules?staff_id=10&term_id=1" "" "GET Staff Schedules" "200"

# 9.10 GET /api/schedules/admin-term-overview (Get admin term overview)
test_endpoint "GET" "schedules/admin-term-overview?term_id=1" "" "GET Admin Term Overview" "200"

################################################################################
# SECTION 10: TERM & HOLIDAY WORKFLOW
################################################################################

print_header "SECTION 10: TERM & HOLIDAY WORKFLOW"

# 10.1 POST /api/schedules/define-term-dates (Define term dates)
TERM_PAYLOAD='{
    "term_number": 1,
    "academic_year": 2024,
    "start_date": "2024-01-08",
    "end_date": "2024-04-05",
    "school_reopens": "2024-01-08",
    "school_closes": "2024-04-05"
}'
test_endpoint "POST" "schedules/define-term-dates" "$TERM_PAYLOAD" "POST Define Term Dates" "201"

# 10.2 GET /api/schedules/review-term-dates (Review term dates)
test_endpoint "GET" "schedules/review-term-dates?term_id=1" "" "GET Review Term Dates" "200"

# 10.3 GET /api/schedules/check-resource-availability (Check resource availability)
test_endpoint "GET" "schedules/check-resource-availability?resource_type=room&resource_id=3&date=2024-01-15" "" "GET Check Resource Availability" "200"

# 10.4 GET /api/schedules/find-optimal-schedule (Find optimal schedule)
test_endpoint "GET" "schedules/find-optimal-schedule?class_id=1&subject_id=5&teacher_id=10" "" "GET Find Optimal Schedule" "200"

# 10.5 POST /api/schedules/detect-schedule-conflicts (Detect conflicts)
CONFLICTS_PAYLOAD='{
    "term_id": 1,
    "check_types": ["resource_conflict", "teacher_conflict", "room_conflict"]
}'
test_endpoint "POST" "schedules/detect-schedule-conflicts" "$CONFLICTS_PAYLOAD" "POST Detect Schedule Conflicts" "200"

# 10.6 GET /api/schedules/generate-master-schedule (Generate master schedule)
test_endpoint "GET" "schedules/generate-master-schedule?term_id=1&academic_year=2024" "" "GET Generate Master Schedule" "200"

# 10.7 GET /api/schedules/validate-schedule-compliance (Validate compliance)
test_endpoint "GET" "schedules/validate-schedule-compliance?term_id=1" "" "GET Validate Schedule Compliance" "200"

# 10.8 POST /api/schedules/start-scheduling-workflow (Start workflow)
WORKFLOW_PAYLOAD='{
    "term_id": 1,
    "academic_year": 2024,
    "workflow_type": "full_schedule_generation"
}'
test_endpoint "POST" "schedules/start-scheduling-workflow" "$WORKFLOW_PAYLOAD" "POST Start Scheduling Workflow" "201"

# 10.9 POST /api/schedules/advance-scheduling-workflow (Advance workflow)
ADVANCE_PAYLOAD='{
    "workflow_instance_id": 1,
    "action": "approve_master_schedule",
    "remarks": "Approved by admin"
}'
test_endpoint "POST" "schedules/advance-scheduling-workflow" "$ADVANCE_PAYLOAD" "POST Advance Scheduling Workflow" "200"

# 10.10 GET /api/schedules/scheduling-workflow-status (Get workflow status)
test_endpoint "GET" "schedules/scheduling-workflow-status?workflow_instance_id=1" "" "GET Scheduling Workflow Status" "200"

# 10.11 GET /api/schedules/list-scheduling-workflows (List workflows)
test_endpoint "GET" "schedules/list-scheduling-workflows?term_id=1" "" "GET List Scheduling Workflows" "200"

################################################################################
# SECTION 11: SCHOOLCONFIG ENDPOINTS
################################################################################

print_header "SECTION 11: SCHOOLCONFIG ENDPOINTS (/api/schoolconfig)"

# 11.1 GET /api/schoolconfig/index
test_endpoint "GET" "schoolconfig/index" "" "GET SchoolConfig Index" "200"

# 11.2 GET /api/schoolconfig (Get school configuration)
test_endpoint "GET" "schoolconfig" "" "GET School Configuration" "200"

# 11.3 POST /api/schoolconfig (Create/Update school config)
SCHOOLCONFIG_PAYLOAD='{
    "school_name": "Kingsway Academy",
    "school_code": "KWA001",
    "logo_url": "https://example.com/logo.png",
    "favicon_url": "https://example.com/favicon.ico",
    "motto": "Excellence in Education",
    "vision": "To develop confident and responsible global citizens",
    "mission": "Provide quality education",
    "core_values": "Integrity, Innovation, Inclusivity"
}'
test_endpoint "POST" "schoolconfig" "$SCHOOLCONFIG_PAYLOAD" "POST Create School Configuration" "201"

# 11.4 PUT /api/schoolconfig/{id} (Update school config by ID)
UPDATE_CONFIG_PAYLOAD='{
    "school_name": "Kingsway Academy - Updated",
    "motto": "Excellence in Teaching and Learning"
}'
test_endpoint "PUT" "schoolconfig/1" "$UPDATE_CONFIG_PAYLOAD" "PUT Update School Configuration" "200"

# 11.5 DELETE /api/schoolconfig/{id} (Delete school config - typically not supported)
test_endpoint "DELETE" "schoolconfig/1" "" "DELETE School Configuration" "400"

# 11.6 GET /api/schoolconfig/logs (Get logs)
test_endpoint "GET" "schoolconfig/logs" "" "GET School Config Logs" "200"

# 11.7 POST /api/schoolconfig/logs-clear (Clear logs)
test_endpoint "POST" "schoolconfig/logs-clear" "" "POST Clear Logs" "200"

# 11.8 POST /api/schoolconfig/logs-archive (Archive logs)
test_endpoint "POST" "schoolconfig/logs-archive" "" "POST Archive Logs" "200"

# 11.9 GET /api/schoolconfig/health (Health check)
test_endpoint "GET" "schoolconfig/health" "" "GET Health Check" "200"

################################################################################
# TEST SUMMARY & REPORT
################################################################################

print_header "TEST SUMMARY"

echo -e "${GREEN}Tests Passed: $TESTS_PASSED${NC}" | tee -a "$LOG_FILE"
echo -e "${RED}Tests Failed: $TESTS_FAILED${NC}" | tee -a "$LOG_FILE"
echo -e "Total Tests: $TOTAL_TESTS" | tee -a "$LOG_FILE"

PASS_PERCENTAGE=$((TESTS_PASSED * 100 / TOTAL_TESTS))
echo -e "Pass Percentage: ${PASS_PERCENTAGE}%" | tee -a "$LOG_FILE"

echo -e "\n${BLUE}Test Reports:${NC}"
echo -e "  - Log File: $LOG_FILE"
echo -e "  - Results File: $RESULTS_FILE"

# Finalize results file
echo "]}" >> "$RESULTS_FILE"

################################################################################
# NOTES ON API ARCHITECTURE
################################################################################

cat << 'EOF' | tee -a "$LOG_FILE"

=== API ARCHITECTURE NOTES ===

1. DATA FLOW (Database → API → Frontend):
   ┌─────────────────────────────────────────────────────────────┐
   │ Database (MySQL)                                            │
   │ ├─ class_schedules                                          │
   │ ├─ exam_schedules                                           │
   │ ├─ activity_schedule                                        │
   │ ├─ route_schedules                                          │
   │ └─ school_configuration                                     │
   └────────────────┬────────────────────────────────────────────┘
                    │
   ┌────────────────▼────────────────────────────────────────────┐
   │ API Module Layer                                            │
   │ ├─ SchedulesAPI (Coordinates operations)                   │
   │ ├─ SchedulesManager (Executes queries)                     │
   │ ├─ SchedulesWorkflow (Handles workflows)                   │
   │ └─ TermHolidayManager (Manages terms & holidays)           │
   └────────────────┬────────────────────────────────────────────┘
                    │
   ┌────────────────▼────────────────────────────────────────────┐
   │ Controller Layer (SchedulesController, SchoolConfigController)
   │ ├─ Request routing & validation                            │
   │ ├─ Response formatting                                     │
   │ └─ Error handling                                          │
   └────────────────┬────────────────────────────────────────────┘
                    │
   ┌────────────────▼────────────────────────────────────────────┐
   │ HTTP Response (JSON)                                        │
   │ {                                                           │
   │   "status": "success",                                      │
   │   "message": "...",                                         │
   │   "data": {...},                                            │
   │   "code": 200,                                              │
   │   "timestamp": "2024-...",                                  │
   │   "request_id": "req_..."                                   │
   │ }                                                           │
   └─────────────────────────────────────────────────────────────┘

2. KEY PAYLOAD STRUCTURES:

   a) Class Schedule:
      - class_id (FK → classes.id)
      - day_of_week (enum: Monday-Sunday)
      - start_time, end_time (TIME format)
      - subject_id, teacher_id, room_id (FKs)
      - status (active/inactive)

   b) Exam Schedule:
      - class_id, subject_id (FKs)
      - exam_date, start_time, end_time
      - room_id, invigilator_id (FKs)
      - status (scheduled/completed/cancelled)

   c) Activity Schedule:
      - activity_id (FK → activities.id)
      - schedule_date, day_of_week
      - start_time, end_time
      - venue

   d) Route Schedule:
      - route_id (FK → transport_routes.id)
      - day_of_week, direction (pickup/dropoff)
      - departure_time
      - status

   e) School Config:
      - school_name, school_code
      - logo_url, favicon_url
      - motto, vision, mission, core_values
      - Additional fields for address, phone, email, etc.

3. REQUEST/RESPONSE PATTERNS:

   GET endpoints:
   - Return 200 OK with data array
   - Support optional id parameter
   - Support filtering via query parameters

   POST endpoints:
   - Return 201 Created on success
   - Accept JSON body with required fields
   - Return created resource in data

   PUT endpoints:
   - Return 200 OK on success
   - Require resource ID
   - Return updated resource in data

   DELETE endpoints:
   - Return 204 No Content on success
   - Return 200 OK with message if not truly DELETE

4. ERROR HANDLING:
   - 400 Bad Request: Invalid input, missing required fields
   - 401 Unauthorized: Missing/invalid authentication
   - 403 Forbidden: Insufficient permissions
   - 404 Not Found: Resource not found
   - 409 Conflict: Schedule conflict detected
   - 500 Internal Server Error: Database or server error

5. WORKFLOW FEATURES:
   - Term date definition with validation
   - Master schedule generation
   - Conflict detection (resource, teacher, room)
   - Compliance validation
   - Workflow instance tracking
   - Stage-based progression (define → generate → validate → publish)

=== END OF NOTES ===

EOF

log "=== TEST SUITE COMPLETED ==="

# Exit with appropriate code
if [ $TESTS_FAILED -eq 0 ]; then
    exit 0
else
    exit 1
fi
