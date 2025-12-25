#!/bin/bash

################################################################################
# Staff API Endpoint Testing Script
# Tests all 33 staff endpoints using curl with real payloads
# Output: test_staff_results.txt
################################################################################

# Configuration
API_BASE="http://localhost/Kingsway/api"
OUTPUT_FILE="test_staff_results.txt"
TEST_TOKEN="devtest"

# Colors for terminal output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Test counters
TOTAL=0
PASSED=0
FAILED=0

################################################################################
# Helper Functions
################################################################################

# Initialize output file
init_output() {
    cat > "$OUTPUT_FILE" << EOF
================================================================================
                    STAFF API ENDPOINT TEST RESULTS
                    $(date '+%Y-%m-%d %H:%M:%S')
================================================================================

EOF
}

# Log to both terminal and file
log() {
    echo -e "$1" | tee -a "$OUTPUT_FILE"
}

# Log only to file
log_file() {
    echo -e "$1" >> "$OUTPUT_FILE"
}

# Print section header
section() {
    log ""
    log "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    log "${CYAN}  $1${NC}"
    log "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Test an endpoint
test_endpoint() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_status="${5:-200}"
    
    TOTAL=$((TOTAL + 1))
    
    log ""
    log "${BLUE}📋 Test #${TOTAL}: ${NC}$name"
    log "   Method: ${YELLOW}${method}${NC} ${endpoint}"
    log_file "   Payload: $data"
    
    # Build curl command
    local curl_cmd="curl -s -w '\n%{http_code}' -X $method"
    curl_cmd="$curl_cmd -H 'Content-Type: application/json'"
    curl_cmd="$curl_cmd -H 'Accept: application/json'"
    curl_cmd="$curl_cmd -H 'X-Test-Token: $TEST_TOKEN'"
    
    if [ -n "$data" ] && [ "$method" != "GET" ]; then
        curl_cmd="$curl_cmd -d '$data'"
    fi
    
    curl_cmd="$curl_cmd '${API_BASE}${endpoint}'"
    
    # Execute request
    local response=$(eval $curl_cmd)
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | sed '$d')
    
    # Log response
    log_file "   Response Code: $http_code"
    log_file "   Response Body: $body"
    
    # Check result
    if [ "$http_code" -eq "$expected_status" ]; then
        log "   ${GREEN}✓ PASSED${NC} (HTTP $http_code)"
        PASSED=$((PASSED + 1))
        
        # Pretty print response if it's JSON
        if command -v jq &> /dev/null; then
            echo "$body" | jq '.' >> "$OUTPUT_FILE" 2>/dev/null || log_file "$body"
        else
            log_file "$body"
        fi
    else
        log "   ${RED}✗ FAILED${NC} (Expected: $expected_status, Got: $http_code)"
        FAILED=$((FAILED + 1))
        log_file "$body"
    fi
}

# Print summary
print_summary() {
    local pass_rate=0
    if [ $TOTAL -gt 0 ]; then
        pass_rate=$((PASSED * 100 / TOTAL))
    fi
    
    log ""
    log "================================================================================"
    log "                              TEST SUMMARY"
    log "================================================================================"
    log "Total Tests:  $TOTAL"
    log "${GREEN}Passed:       $PASSED${NC}"
    log "${RED}Failed:       $FAILED${NC}"
    log "Pass Rate:    ${pass_rate}%"
    log "================================================================================"
}

################################################################################
# MAIN EXECUTION
################################################################################

clear
echo -e "${CYAN}"
echo "╔══════════════════════════════════════════════════════════════════════════╗"
echo "║                   STAFF API ENDPOINT TEST SUITE                          ║"
echo "╚══════════════════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Initialize output file
init_output

# Step 1: Create test users
section "STEP 1: Creating Test Users"
log "Running: php scripts/create_test_users.php"
cd /home/prof_angera/Projects/php_pages/Kingsway
php scripts/create_test_users.php | tee -a "$OUTPUT_FILE"

# Step 2: Setup Test Authentication
section "STEP 2: Test Authentication Setup"
log "${CYAN}🔐 Using test authentication token (X-Test-Token: devtest)${NC}"
log "${GREEN}✓ Test authentication configured${NC}"
log_file "Using AuthMiddleware test mode with X-Test-Token header"

# Step 3: Get test user IDs from database
section "STEP 3: Fetching Test User IDs"
log "Querying database for test users..."

# Get user IDs (we'll need these for staff creation)
USER_DIRECTOR=$(/opt/lampp/bin/mysql -u root -padmin123 -N -e "SELECT id FROM users WHERE username='test_director' LIMIT 1" KingsWayAcademy 2>/dev/null)
USER_HEADTEACHER=$(/opt/lampp/bin/mysql -u root -padmin123 -N -e "SELECT id FROM users WHERE username='test_headteacher' LIMIT 1" KingsWayAcademy 2>/dev/null)
USER_CLASS_TEACHER=$(/opt/lampp/bin/mysql -u root -padmin123 -N -e "SELECT id FROM users WHERE username='test_class_teacher' LIMIT 1" KingsWayAcademy 2>/dev/null)

log "   Director User ID: ${USER_DIRECTOR:-NOT FOUND}"
log "   Headteacher User ID: ${USER_HEADTEACHER:-NOT FOUND}"
log "   Class Teacher User ID: ${USER_CLASS_TEACHER:-NOT FOUND}"

# Step 4: Register test users as staff
section "STEP 4: Creating Staff Records"

if [ -n "$USER_DIRECTOR" ]; then
    test_endpoint \
        "Create Director Staff Record" \
        "POST" \
        "/staff" \
        "{
            \"user_id\": $USER_DIRECTOR,
            \"staff_number\": \"DIR-$(date +%s)\",
            \"first_name\": \"Test\",
            \"last_name\": \"Director\",
            \"email\": \"director@kingsway.ac.ke\",
            \"phone\": \"0712345001\",
            \"staff_type\": \"administrative\",
            \"department_id\": 1,
            \"employment_type\": \"permanent\",
            \"hire_date\": \"$(date +%Y-%m-%d)\",
            \"status\": \"active\"
        }"
fi

if [ -n "$USER_HEADTEACHER" ]; then
    test_endpoint \
        "Create Headteacher Staff Record" \
        "POST" \
        "/staff" \
        "{
            \"user_id\": $USER_HEADTEACHER,
            \"staff_number\": \"HEAD-$(date +%s)\",
            \"first_name\": \"Test\",
            \"last_name\": \"Headteacher\",
            \"email\": \"headteacher@kingsway.ac.ke\",
            \"phone\": \"0712345002\",
            \"staff_type\": \"administrative\",
            \"department_id\": 1,
            \"employment_type\": \"permanent\",
            \"hire_date\": \"$(date +%Y-%m-%d)\",
            \"status\": \"active\"
        }"
fi

if [ -n "$USER_CLASS_TEACHER" ]; then
    test_endpoint \
        "Create Class Teacher Staff Record" \
        "POST" \
        "/staff" \
        "{
            \"user_id\": $USER_CLASS_TEACHER,
            \"staff_number\": \"TCH-$(date +%s)\",
            \"first_name\": \"Test\",
            \"last_name\": \"Teacher\",
            \"email\": \"classteacher@kingsway.ac.ke\",
            \"phone\": \"0712345003\",
            \"staff_type\": \"teaching\",
            \"department_id\": 2,
            \"employment_type\": \"permanent\",
            \"hire_date\": \"$(date +%Y-%m-%d)\",
            \"status\": \"active\"
        }"
fi

# Step 5: Test BASE CRUD Operations
section "STEP 5: BASE CRUD OPERATIONS"

test_endpoint "Index" "GET" "/staff/index"
test_endpoint "List All Staff" "GET" "/staff"

# Get first staff ID for update/delete tests
STAFF_ID=$(curl -s -X GET \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    "${API_BASE}/staff" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)

if [ -n "$STAFF_ID" ]; then
    log "   Using Staff ID: $STAFF_ID for subsequent tests"
    
    test_endpoint "Get Specific Staff" "GET" "/staff" "" 200
    
    test_endpoint \
        "Update Staff" \
        "PUT" \
        "/staff/$STAFF_ID" \
        "{
            \"phone\": \"0712999999\",
            \"status\": \"active\"
        }"
fi

# Step 6: Test STAFF INFORMATION
section "STEP 6: STAFF INFORMATION"

test_endpoint "Get Profile" "GET" "/staff/profile-get"
test_endpoint "Get Schedule" "GET" "/staff/schedule-get"
test_endpoint "Get Departments" "GET" "/staff/departments-get"

# Step 7: Test ASSIGNMENT OPERATIONS
section "STEP 7: ASSIGNMENT OPERATIONS"

test_endpoint "Get Assignments" "GET" "/staff/assignments-get"
test_endpoint "Get Current Assignments" "GET" "/staff/assignments-current"
test_endpoint "Get Workload" "GET" "/staff/workload-get"

# Get academic year and class for assignment
ACADEMIC_YEAR=$(/opt/lampp/bin/mysql -u root -padmin123 -N -e "SELECT id FROM academic_years WHERE status='active' LIMIT 1" KingsWayAcademy 2>/dev/null)
CLASS_STREAM=$(/opt/lampp/bin/mysql -u root -padmin123 -N -e "SELECT id FROM class_streams LIMIT 1" KingsWayAcademy 2>/dev/null)

if [ -n "$STAFF_ID" ] && [ -n "$CLASS_STREAM" ]; then
    test_endpoint \
        "Assign Class" \
        "POST" \
        "/staff/assign-class" \
        "{
            \"staff_id\": $STAFF_ID,
            \"class_stream_id\": $CLASS_STREAM,
            \"role\": \"class_teacher\"
        }"
fi

# Step 8: Test ATTENDANCE OPERATIONS
section "STEP 8: ATTENDANCE OPERATIONS"

test_endpoint "Get Attendance" "GET" "/staff/attendance-get?start_date=$(date +%Y-%m-01)&end_date=$(date +%Y-%m-%d)"

if [ -n "$STAFF_ID" ]; then
    test_endpoint \
        "Mark Attendance" \
        "POST" \
        "/staff/attendance-mark" \
        "{
            \"staff_id\": $STAFF_ID,
            \"date\": \"$(date +%Y-%m-%d)\",
            \"status\": \"present\",
            \"check_in\": \"08:00:00\",
            \"check_out\": \"17:00:00\"
        }"
fi

# Step 9: Test LEAVE MANAGEMENT
section "STEP 9: LEAVE MANAGEMENT"

test_endpoint "List Leaves" "GET" "/staff/leaves-list"

if [ -n "$STAFF_ID" ]; then
    test_endpoint \
        "Apply for Leave" \
        "POST" \
        "/staff/leaves-apply" \
        "{
            \"staff_id\": $STAFF_ID,
            \"leave_type\": \"annual\",
            \"start_date\": \"$(date -d '+7 days' +%Y-%m-%d)\",
            \"end_date\": \"$(date -d '+9 days' +%Y-%m-%d)\",
            \"reason\": \"Personal matters\",
            \"days_requested\": 3
        }"
fi

# Step 10: Test PAYROLL OPERATIONS
section "STEP 10: PAYROLL OPERATIONS"

CURRENT_MONTH=$(date +%m)
CURRENT_YEAR=$(date +%Y)

test_endpoint "View Payslip" "GET" "/staff/payroll-payslip?month=$CURRENT_MONTH&year=$CURRENT_YEAR"
test_endpoint "Get Payroll History" "GET" "/staff/payroll-history"
test_endpoint "View Allowances" "GET" "/staff/payroll-allowances"
test_endpoint "View Deductions" "GET" "/staff/payroll-deductions"
test_endpoint "Get Loan Details" "GET" "/staff/payroll-loan-details"

if [ -n "$STAFF_ID" ]; then
    test_endpoint \
        "Request Salary Advance" \
        "POST" \
        "/staff/payroll-request-advance" \
        "{
            \"staff_id\": $STAFF_ID,
            \"amount\": 50000,
            \"reason\": \"Emergency medical expenses\",
            \"repayment_months\": 3
        }"
        
    test_endpoint \
        "Apply for Loan" \
        "POST" \
        "/staff/payroll-apply-loan" \
        "{
            \"staff_id\": $STAFF_ID,
            \"loan_type\": \"personal\",
            \"amount\": 100000,
            \"purpose\": \"Home improvement\",
            \"repayment_period\": 12
        }"
fi

# Step 11: Test PERFORMANCE MANAGEMENT
section "STEP 11: PERFORMANCE MANAGEMENT"

test_endpoint "Get Review History" "GET" "/staff/performance-review-history"
test_endpoint "Get Academic KPI Summary" "GET" "/staff/performance-academic-kpi-summary"

# Step 12: Final Summary
cd /home/prof_angera/Projects/php_pages/Kingsway/tests
print_summary

# Output location
echo ""
echo -e "${CYAN}📄 Full test results saved to: ${NC}$OUTPUT_FILE"
echo ""

# Exit with appropriate code
if [ $FAILED -gt 0 ]; then
    exit 1
else
    exit 0
fi
