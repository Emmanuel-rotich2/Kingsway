#!/bin/bash

################################################################################
# Student API Endpoint Testing Script
# Tests all 76 student endpoints using curl with real payloads
# Output: test_student_results.txt
################################################################################

# Configuration
API_BASE="http://localhost/Kingsway/api"
OUTPUT_FILE="test_student_results.txt"
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

# Test data IDs (will be populated)
STUDENT_ID=""
CLASS_ID=""
STREAM_ID=""
ACADEMIC_YEAR_ID=""

################################################################################
# Helper Functions
################################################################################

# Initialize output file
init_output() {
    cat > "$OUTPUT_FILE" << EOF
================================================================================
                   STUDENT API ENDPOINT TEST RESULTS
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
echo "║                  STUDENT API ENDPOINT TEST SUITE                         ║"
echo "╚══════════════════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Initialize output file
init_output

# Step 1: Get test data IDs
section "STEP 1: Fetching Test Data IDs"
log "Querying database for test data..."

# Get academic year
ACADEMIC_YEAR_ID=$(/opt/lampp/bin/mysql -u root -padmin123 -N -e "SELECT id FROM academic_years WHERE status='active' ORDER BY start_date DESC LIMIT 1" KingsWayAcademy 2>/dev/null)
log "   Academic Year ID: ${ACADEMIC_YEAR_ID:-NOT FOUND}"

# Get class and stream
CLASS_ID=$(/opt/lampp/bin/mysql -u root -padmin123 -N -e "SELECT id FROM classes WHERE status='active' LIMIT 1" KingsWayAcademy 2>/dev/null)
log "   Class ID: ${CLASS_ID:-NOT FOUND}"

STREAM_ID=$(/opt/lampp/bin/mysql -u root -padmin123 -N -e "SELECT id FROM class_streams WHERE status='active' LIMIT 1" KingsWayAcademy 2>/dev/null)
log "   Stream ID: ${STREAM_ID:-NOT FOUND}"

# Step 2: Test Authentication
section "STEP 2: Test Authentication Setup"
log "${CYAN}🔐 Using test authentication token (X-Test-Token: devtest)${NC}"
log "${GREEN}✓ Test authentication configured${NC}"
log_file "Using AuthMiddleware test mode with X-Test-Token header"

# ============================================================================
# BASE CRUD OPERATIONS
# ============================================================================

section "STEP 3: BASE CRUD OPERATIONS"

test_endpoint \
    "Index" \
    "GET" \
    "/students/index"

test_endpoint \
    "Get Student (List)" \
    "GET" \
    "/students/student"

# ============================================================================
# STUDENT INFORMATION RETRIEVAL
# ============================================================================

section "STEP 4: STUDENT INFORMATION RETRIEVAL"

test_endpoint \
    "Get Profile" \
    "GET" \
    "/students/profile-get"

test_endpoint \
    "Get Attendance" \
    "GET" \
    "/students/attendance-get"

test_endpoint \
    "Get Performance" \
    "GET" \
    "/students/performance-get"

test_endpoint \
    "Get Fees" \
    "GET" \
    "/students/fees-get"

test_endpoint \
    "Get QR Info" \
    "GET" \
    "/students/qr-info-get"

test_endpoint \
    "Get Statistics" \
    "GET" \
    "/students/statistics-get"

# ============================================================================
# CLASS/STREAM OPERATIONS
# ============================================================================

section "STEP 5: CLASS/STREAM OPERATIONS"

if [ -n "$CLASS_ID" ]; then
    test_endpoint \
        "Get Students by Class" \
        "GET" \
        "/students/by-class-get?class_id=$CLASS_ID"
fi

if [ -n "$STREAM_ID" ]; then
    test_endpoint \
        "Get Students by Stream" \
        "GET" \
        "/students/by-stream-get?stream_id=$STREAM_ID"
fi

test_endpoint \
    "Get Class Roster" \
    "GET" \
    "/students/roster-get"

# ============================================================================
# ACADEMIC YEAR OPERATIONS
# ============================================================================

section "STEP 6: ACADEMIC YEAR OPERATIONS"

test_endpoint \
    "Get Current Academic Year" \
    "GET" \
    "/students/academic-year-current"

test_endpoint \
    "Get All Academic Years" \
    "GET" \
    "/students/academic-year-all"

if [ -n "$ACADEMIC_YEAR_ID" ]; then
    test_endpoint \
        "Get Academic Year" \
        "GET" \
        "/students/academic-year-get?id=$ACADEMIC_YEAR_ID"
    
    test_endpoint \
        "Get Academic Year Terms" \
        "GET" \
        "/students/academic-year-terms?academic_year_id=$ACADEMIC_YEAR_ID"
fi

test_endpoint \
    "Get Current Term" \
    "GET" \
    "/students/academic-year-current-term"

# ============================================================================
# ENROLLMENT & ALUMNI
# ============================================================================

section "STEP 7: ENROLLMENT & ALUMNI"

test_endpoint \
    "Get Current Enrollment" \
    "GET" \
    "/students/enrollment-current"

test_endpoint \
    "Get Alumni" \
    "GET" \
    "/students/alumni-get"

# ============================================================================
# PROMOTION OPERATIONS
# ============================================================================

section "STEP 8: PROMOTION OPERATIONS"

test_endpoint \
    "Get Promotion Batches" \
    "GET" \
    "/students/promotion-batches"

test_endpoint \
    "Get Promotion History" \
    "GET" \
    "/students/promotion-history"

# ============================================================================
# PARENT OPERATIONS
# ============================================================================

section "STEP 9: PARENT OPERATIONS"

test_endpoint \
    "Get Parents" \
    "GET" \
    "/students/parents-get"

# ============================================================================
# MEDICAL RECORDS
# ============================================================================

section "STEP 10: MEDICAL RECORDS"

test_endpoint \
    "Get Medical Records" \
    "GET" \
    "/students/medical-get"

# ============================================================================
# DISCIPLINE RECORDS
# ============================================================================

section "STEP 11: DISCIPLINE RECORDS"

test_endpoint \
    "Get Discipline Records" \
    "GET" \
    "/students/discipline-get"

# ============================================================================
# DOCUMENT MANAGEMENT
# ============================================================================

section "STEP 12: DOCUMENT MANAGEMENT"

test_endpoint \
    "Get Documents" \
    "GET" \
    "/students/documents-get"

# ============================================================================
# MEDIA OPERATIONS
# ============================================================================

section "STEP 13: MEDIA OPERATIONS"

test_endpoint \
    "Get Media" \
    "GET" \
    "/students/media"

# ============================================================================
# IMPORT OPERATIONS
# ============================================================================

section "STEP 14: IMPORT OPERATIONS"

test_endpoint \
    "Get Import Template" \
    "GET" \
    "/students/import-template"

# ============================================================================
# ADMISSION WORKFLOW
# ============================================================================

section "STEP 15: ADMISSION WORKFLOW"

test_endpoint \
    "Get Admission Workflow Status" \
    "GET" \
    "/students/admission-workflow-status"

# ============================================================================
# TRANSFER WORKFLOW
# ============================================================================

section "STEP 16: TRANSFER WORKFLOW"

test_endpoint \
    "Get Transfer Workflow Status" \
    "GET" \
    "/students/transfer-workflow-status"

test_endpoint \
    "Get Transfer History" \
    "GET" \
    "/students/transfer-history"

# ============================================================================
# CREATE/UPDATE/DELETE OPERATIONS
# ============================================================================

section "STEP 17: CRUD OPERATIONS"

# Create Student
test_endpoint \
    "Create Student (POST)" \
    "POST" \
    "/students/student" \
    '{
        "first_name": "Test",
        "last_name": "Student",
        "admission_no": "TEST'$(date +%s)'",
        "class_id": '$CLASS_ID',
        "gender": "male",
        "date_of_birth": "2010-01-01"
    }'

# Update Student
test_endpoint \
    "Update Student (PUT)" \
    "PUT" \
    "/students/student?id=1" \
    '{
        "first_name": "Updated",
        "last_name": "Student"
    }'

# Delete Student (using test endpoint to avoid deleting real data)
test_endpoint \
    "Delete Student (DELETE)" \
    "DELETE" \
    "/students/student?id=99999" \
    "" \
    404

# ============================================================================
# MEDIA OPERATIONS (POST/DELETE)
# ============================================================================

section "STEP 18: MEDIA OPERATIONS (POST/DELETE)"

test_endpoint \
    "Upload Media (POST)" \
    "POST" \
    "/students/media-upload" \
    '{
        "student_id": 1,
        "media_type": "photo",
        "file_data": "base64string"
    }'

test_endpoint \
    "Delete Media (POST)" \
    "POST" \
    "/students/media-delete" \
    '{"media_id": 99999}' \
    200

# ============================================================================
# BULK OPERATIONS
# ============================================================================

section "STEP 19: BULK OPERATIONS"

test_endpoint \
    "Bulk Create Students" \
    "POST" \
    "/students/bulk-create" \
    '{
        "students": [
            {
                "first_name": "Bulk1",
                "last_name": "Test",
                "admission_no": "BULK001",
                "class_id": '$CLASS_ID'
            }
        ]
    }'

test_endpoint \
    "Bulk Update Students" \
    "POST" \
    "/students/bulk-update" \
    '{
        "student_ids": [1],
        "updates": {"status": "active"}
    }'

test_endpoint \
    "Bulk Delete Students" \
    "POST" \
    "/students/bulk-delete" \
    '{"student_ids": [99999]}' \
    200

test_endpoint \
    "Bulk Promote Students" \
    "POST" \
    "/students/bulk-promote" \
    '{
        "student_ids": [1],
        "target_class_id": '$CLASS_ID',
        "academic_year_id": '$ACADEMIC_YEAR_ID'
    }'

# ============================================================================
# QR CODE & ID CARD GENERATION
# ============================================================================

section "STEP 20: QR CODE & ID CARD OPERATIONS"

test_endpoint \
    "Generate QR Code" \
    "POST" \
    "/students/qr-code-generate" \
    '{"student_id": 1}'

test_endpoint \
    "Generate Enhanced QR Code" \
    "POST" \
    "/students/qr-code-generate-enhanced" \
    '{"student_id": 1}'

test_endpoint \
    "Generate ID Card" \
    "POST" \
    "/students/id-card-generate" \
    '{"student_id": 1}'

if [ -n "$CLASS_ID" ]; then
    test_endpoint \
        "Generate ID Cards for Class" \
        "POST" \
        "/students/id-card-generate-class" \
        '{"class_id": '$CLASS_ID'}'
fi

test_endpoint \
    "Upload Photo" \
    "POST" \
    "/students/photo-upload" \
    '{"student_id": 1, "photo_data": "base64string"}'

# ============================================================================
# ADMISSION WORKFLOW (POST OPERATIONS)
# ============================================================================

section "STEP 21: ADMISSION WORKFLOW (POST)"

test_endpoint \
    "Start Admission Workflow" \
    "POST" \
    "/students/admission-start-workflow" \
    '{
        "first_name": "Admission",
        "last_name": "Test",
        "application_data": {}
    }'

test_endpoint \
    "Verify Admission Documents" \
    "POST" \
    "/students/admission-verify-documents" \
    '{"application_id": 1, "verified": true}'

test_endpoint \
    "Conduct Interview" \
    "POST" \
    "/students/admission-conduct-interview" \
    '{"application_id": 1, "interview_notes": "Good"}'

test_endpoint \
    "Approve Admission" \
    "POST" \
    "/students/admission-approve" \
    '{"application_id": 1, "approved": true}'

test_endpoint \
    "Complete Registration" \
    "POST" \
    "/students/admission-complete-registration" \
    '{"application_id": 1, "registration_complete": true}'

# ============================================================================
# TRANSFER WORKFLOW (POST OPERATIONS)
# ============================================================================

section "STEP 22: TRANSFER WORKFLOW (POST)"

test_endpoint \
    "Start Transfer Workflow" \
    "POST" \
    "/students/transfer-start-workflow" \
    '{
        "student_id": 1,
        "target_class_id": '$CLASS_ID',
        "reason": "Grade progression"
    }'

test_endpoint \
    "Verify Transfer Eligibility" \
    "POST" \
    "/students/transfer-verify-eligibility" \
    '{"transfer_id": 1, "eligible": true}'

test_endpoint \
    "Approve Transfer" \
    "POST" \
    "/students/transfer-approve" \
    '{"transfer_id": 1, "approved": true}'

test_endpoint \
    "Execute Transfer" \
    "POST" \
    "/students/transfer-execute" \
    '{"transfer_id": 1}'

# ============================================================================
# PROMOTION OPERATIONS (POST)
# ============================================================================

section "STEP 23: PROMOTION OPERATIONS (POST)"

test_endpoint \
    "Promote Single Student" \
    "POST" \
    "/students/promotion-single" \
    '{
        "student_id": 1,
        "target_class_id": '$CLASS_ID',
        "academic_year_id": '$ACADEMIC_YEAR_ID'
    }'

test_endpoint \
    "Promote Multiple Students" \
    "POST" \
    "/students/promotion-multiple" \
    '{
        "student_ids": [1],
        "target_class_id": '$CLASS_ID',
        "academic_year_id": '$ACADEMIC_YEAR_ID'
    }'

if [ -n "$CLASS_ID" ]; then
    test_endpoint \
        "Promote Entire Class" \
        "POST" \
        "/students/promotion-entire-class" \
        '{
            "class_id": '$CLASS_ID',
            "target_class_id": '$CLASS_ID',
            "academic_year_id": '$ACADEMIC_YEAR_ID'
        }'
    
    test_endpoint \
        "Promote Multiple Classes" \
        "POST" \
        "/students/promotion-multiple-classes" \
        '{
            "class_ids": ['$CLASS_ID'],
            "promotion_rules": {}
        }'
fi

test_endpoint \
    "Graduate Grade 9 Students" \
    "POST" \
    "/students/promotion-graduate-grade9" \
    '{"student_ids": [1]}'

# ============================================================================
# PARENT OPERATIONS (POST/PUT)
# ============================================================================

section "STEP 24: PARENT OPERATIONS (POST/PUT)"

test_endpoint \
    "Add Parent" \
    "POST" \
    "/students/parents-add" \
    '{
        "student_id": 1,
        "parent_name": "Test Parent",
        "relationship": "father",
        "phone": "0700000000"
    }'

test_endpoint \
    "Update Parent" \
    "PUT" \
    "/students/parents-update" \
    '{
        "parent_id": 1,
        "phone": "0700000001"
    }'

test_endpoint \
    "Remove Parent" \
    "POST" \
    "/students/parents-remove" \
    '{"parent_id": 99999}' \
    200

# ============================================================================
# MEDICAL RECORDS (POST/PUT)
# ============================================================================

section "STEP 25: MEDICAL RECORDS (POST/PUT)"

test_endpoint \
    "Add Medical Record" \
    "POST" \
    "/students/medical-add" \
    '{
        "student_id": 1,
        "condition": "Asthma",
        "notes": "Requires inhaler"
    }'

test_endpoint \
    "Update Medical Record" \
    "PUT" \
    "/students/medical-update" \
    '{
        "medical_id": 1,
        "notes": "Updated notes"
    }'

# ============================================================================
# DISCIPLINE RECORDS (POST/PUT)
# ============================================================================

section "STEP 26: DISCIPLINE RECORDS (POST/PUT)"

test_endpoint \
    "Record Discipline Incident" \
    "POST" \
    "/students/discipline-record" \
    '{
        "student_id": 1,
        "incident_type": "Late arrival",
        "description": "Late to class",
        "action_taken": "Warning issued"
    }'

test_endpoint \
    "Update Discipline Record" \
    "PUT" \
    "/students/discipline-update" \
    '{
        "discipline_id": 1,
        "status": "resolved"
    }'

test_endpoint \
    "Resolve Discipline Case" \
    "POST" \
    "/students/discipline-resolve" \
    '{
        "discipline_id": 1,
        "resolution_notes": "Case closed"
    }'

# ============================================================================
# DOCUMENT MANAGEMENT (POST/DELETE)
# ============================================================================

section "STEP 27: DOCUMENT MANAGEMENT (POST/DELETE)"

test_endpoint \
    "Upload Document" \
    "POST" \
    "/students/documents-upload" \
    '{
        "student_id": 1,
        "document_type": "birth_certificate",
        "file_data": "base64string"
    }'

test_endpoint \
    "Delete Document" \
    "DELETE" \
    "/students/documents-delete?document_id=99999" \
    "" \
    200

# ============================================================================
# ATTENDANCE OPERATIONS
# ============================================================================

section "STEP 28: ATTENDANCE MARKING"

test_endpoint \
    "Mark Attendance" \
    "POST" \
    "/students/attendance-mark" \
    '{
        "student_id": 1,
        "date": "'$(date +%Y-%m-%d)'",
        "status": "present"
    }'

# ============================================================================
# IMPORT OPERATIONS
# ============================================================================

section "STEP 29: IMPORT OPERATIONS"

test_endpoint \
    "Import Existing Students" \
    "POST" \
    "/students/import-existing" \
    '{
        "students": [
            {
                "admission_no": "IMP001",
                "first_name": "Import",
                "last_name": "Test"
            }
        ]
    }'

test_endpoint \
    "Add Existing Student" \
    "POST" \
    "/students/import-add-existing" \
    '{
        "admission_no": "EX001",
        "first_name": "Existing",
        "last_name": "Student"
    }'

test_endpoint \
    "Add Multiple Students" \
    "POST" \
    "/students/import-add-multiple" \
    '{
        "students": [
            {"admission_no": "MULT001", "first_name": "Multi1", "last_name": "Test"}
        ]
    }'

# ============================================================================
# ACADEMIC YEAR OPERATIONS (POST/PUT)
# ============================================================================

section "STEP 30: ACADEMIC YEAR MANAGEMENT (POST/PUT)"

test_endpoint \
    "Create Academic Year" \
    "POST" \
    "/students/academic-year-create" \
    '{
        "year_name": "2025/2026",
        "start_date": "2025-01-01",
        "end_date": "2025-12-31"
    }'

test_endpoint \
    "Create Next Academic Year" \
    "POST" \
    "/students/academic-year-create-next" \
    '{}'

test_endpoint \
    "Set Current Academic Year" \
    "POST" \
    "/students/academic-year-set-current" \
    '{"academic_year_id": '$ACADEMIC_YEAR_ID'}'

test_endpoint \
    "Update Academic Year Status" \
    "PUT" \
    "/students/academic-year-update-status" \
    '{
        "academic_year_id": '$ACADEMIC_YEAR_ID',
        "status": "active"
    }'

test_endpoint \
    "Archive Academic Year" \
    "POST" \
    "/students/academic-year-archive" \
    '{"academic_year_id": 99}'

# ============================================================================
# FINAL SUMMARY
# ============================================================================

print_summary

log ""
log "📄 Full test results saved to: $OUTPUT_FILE"
log ""

# Exit with error code if any tests failed
[ $FAILED -eq 0 ] && exit 0 || exit 1
