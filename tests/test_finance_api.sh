#!/bin/bash

################################################################################
# Finance API Endpoint Test Suite
# Tests all 42 finance endpoints with proper authentication and payloads
################################################################################

# Configuration
API_BASE_URL="http://localhost/Kingsway/api/finance"
TEST_TOKEN="devtest"
OUTPUT_FILE="test_finance_results.txt"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Database configuration
DB_USER="root"
DB_PASS="admin123"
DB_NAME="KingsWayAcademy"
MYSQL_CMD="/opt/lampp/bin/mysql -u $DB_USER -p$DB_PASS -N -e"

################################################################################
# Helper Functions
################################################################################

print_header() {
    echo ""
    echo "╔══════════════════════════════════════════════════════════════════════════╗"
    echo "║                  FINANCE API ENDPOINT TEST SUITE                         ║"
    echo "╚══════════════════════════════════════════════════════════════════════════╝"
    echo ""
}

section() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "  ${BOLD}$1${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

test_endpoint() {
    local test_name="$1"
    local method="$2"
    local endpoint="$3"
    local payload="$4"
    local expected_status="${5:-200}"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo ""
    echo -e "${CYAN}📋 Test #${TOTAL_TESTS}: ${test_name}${NC}"
    echo "   Method: $method $endpoint"
    
    # Build curl command
    local curl_cmd="curl -s -w '\n%{http_code}' -X $method"
    curl_cmd="$curl_cmd -H 'Content-Type: application/json'"
    curl_cmd="$curl_cmd -H 'X-Test-Token: $TEST_TOKEN'"
    
    if [ -n "$payload" ] && [ "$payload" != '""' ]; then
        curl_cmd="$curl_cmd -d '$payload'"
    fi
    
    curl_cmd="$curl_cmd '$API_BASE_URL$endpoint'"
    
    # Execute request
    local response=$(eval $curl_cmd)
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n-1)
    
    # Check status
    if [ "$http_code" -eq "$expected_status" ]; then
        echo -e "   ${GREEN}✓ PASSED${NC} (HTTP $http_code)"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        echo "PASS: $test_name - $method $endpoint" >> "$OUTPUT_FILE"
    else
        echo -e "   ${RED}✗ FAILED${NC} (Expected: $expected_status, Got: $http_code)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo "FAIL: $test_name - $method $endpoint - Expected $expected_status, Got $http_code" >> "$OUTPUT_FILE"
        echo "Response: $body" >> "$OUTPUT_FILE"
    fi
}

print_summary() {
    local pass_rate=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    
    echo ""
    echo "================================================================================"
    echo "                              TEST SUMMARY"
    echo "================================================================================"
    echo "Total Tests:  $TOTAL_TESTS"
    echo "Passed:       $PASSED_TESTS"
    echo "Failed:       $FAILED_TESTS"
    echo "Pass Rate:    ${pass_rate}%"
    echo "================================================================================"
    echo ""
    echo "📄 Full test results saved to: $OUTPUT_FILE"
    echo ""
    
    # Exit with error if any tests failed
    if [ $FAILED_TESTS -gt 0 ]; then
        exit 1
    fi
}

################################################################################
# Main Test Suite
################################################################################

# Clear previous results
> "$OUTPUT_FILE"

print_header

# ============================================================================
# STEP 1: Fetch Test Data IDs
# ============================================================================

section "STEP 1: Fetching Test Data IDs"

echo "Querying database for test data..."

# Get IDs for testing
ACADEMIC_YEAR_ID=$($MYSQL_CMD "SELECT id FROM academic_years WHERE status='active' ORDER BY start_date DESC LIMIT 1" $DB_NAME)
STAFF_ID=$($MYSQL_CMD "SELECT id FROM staff WHERE status='active' LIMIT 1" $DB_NAME)
PAYROLL_ID=$($MYSQL_CMD "SELECT id FROM staff_payroll ORDER BY created_at DESC LIMIT 1" $DB_NAME)
DEPARTMENT_ID=$($MYSQL_CMD "SELECT id FROM departments LIMIT 1" $DB_NAME)
FEE_STRUCTURE_ID=$($MYSQL_CMD "SELECT id FROM fee_structures ORDER BY id DESC LIMIT 1" $DB_NAME)

echo "   Academic Year ID: ${ACADEMIC_YEAR_ID:-Not found}"
echo "   Staff ID: ${STAFF_ID:-Not found}"
echo "   Payroll ID: ${PAYROLL_ID:-Not found}"
echo "   Department ID: ${DEPARTMENT_ID:-Not found}"
echo "   Fee Structure ID: ${FEE_STRUCTURE_ID:-Not found}"

# ============================================================================
# STEP 2: Test Authentication Setup
# ============================================================================

section "STEP 2: Test Authentication Setup"
echo "🔐 Using test authentication token (X-Test-Token: devtest)"
echo "✓ Test authentication configured"

# ============================================================================
# STEP 3: BASE OPERATIONS
# ============================================================================

section "STEP 3: BASE CRUD OPERATIONS"

test_endpoint \
    "Index" \
    "GET" \
    "/index"

test_endpoint \
    "Get Finance Records" \
    "GET" \
    ""

test_endpoint \
    "Create Finance Record (POST)" \
    "POST" \
    "" \
    '{
        "type": "expense",
        "amount": 5000,
        "description": "Test expense",
        "expense_category": "operations",
        "expense_date": "2025-01-15"
    }'

test_endpoint \
    "Update Finance Record (PUT)" \
    "PUT" \
    "/1" \
    '{
        "description": "Updated expense",
        "amount": 15000
    }'

test_endpoint \
    "Delete Finance Record (DELETE)" \
    "DELETE" \
    "/99999" \
    "" \
    404

# ============================================================================
# STEP 4: DEPARTMENT BUDGETS
# ============================================================================

section "STEP 4: DEPARTMENT BUDGETS"

test_endpoint \
    "Get Department Budget Proposals" \
    "GET" \
    "/department-budgets-proposals"

test_endpoint \
    "Propose Department Budget" \
    "POST" \
    "/department-budgets-propose" \
    '{
        "department_id": '$DEPARTMENT_ID',
        "title": "Annual Operations Budget",
        "description": "Annual operations and activities",
        "amount_requested": 500000,
        "created_by": 1
    }'

test_endpoint \
    "Approve Department Budget" \
    "POST" \
    "/department-budgets-approve" \
    '{
        "proposal_id": 1,
        "status": "approved",
        "reviewed_by": 1
    }'

test_endpoint \
    "Allocate Department Budget" \
    "POST" \
    "/department-budgets-allocate" \
    '{
        "department_id": '$DEPARTMENT_ID',
        "amount": 500000,
        "allocated_by": 1
    }'

test_endpoint \
    "Request Funds from Budget" \
    "POST" \
    "/department-budgets-request-funds" \
    '{
        "department_id": '$DEPARTMENT_ID',
        "amount": 50000,
        "reason": "Office supplies",
        "requested_by": 1
    }'

# ============================================================================
# STEP 5: PAYROLL OPERATIONS
# ============================================================================

section "STEP 5: PAYROLL OPERATIONS"

test_endpoint \
    "Get Payroll List" \
    "GET" \
    "?type=payrolls"

if [ -n "$PAYROLL_ID" ]; then
    test_endpoint \
        "Get Payroll Details" \
        "GET" \
        "/payrolls-get?id=$PAYROLL_ID"
fi

test_endpoint \
    "Get Payroll Staff Payments" \
    "GET" \
    "?type=staff-payments&payroll_id=$PAYROLL_ID"

if [ -n "$PAYROLL_ID" ]; then
    test_endpoint \
        "Get Staff Payment Details" \
        "GET" \
        "/payrolls-staff-payments-get?id=$PAYROLL_ID"
fi

test_endpoint \
    "Get Payroll Status" \
    "GET" \
    "/payrolls-status?payroll_id=$PAYROLL_ID"

test_endpoint \
    "Get Payroll Summary" \
    "GET" \
    "/payrolls-summary?start_date=2025-01-01&end_date=2025-12-31"

test_endpoint \
    "Get Payroll History" \
    "GET" \
    "/payrolls-history?staff_id=$STAFF_ID"

test_endpoint \
    "Create Draft Payroll" \
    "POST" \
    "/payrolls-create-draft" \
    '{
        "month": "'$(date +%m)'",
        "year": "'$(date +%Y)'",
        "period_start": "'$(date +%Y-%m-01)'",
        "period_end": "'$(date +%Y-%m-%d)'"
    }'

test_endpoint \
    "Calculate Payroll" \
    "POST" \
    "/payrolls-calculate" \
    '{
        "payroll_id": '$PAYROLL_ID'
    }'

test_endpoint \
    "Recalculate Payroll" \
    "POST" \
    "/payrolls-recalculate" \
    '{
        "payroll_id": '$PAYROLL_ID'
    }'

test_endpoint \
    "Verify Payroll" \
    "POST" \
    "/payrolls-verify" \
    '{
        "payroll_id": '$PAYROLL_ID',
        "user_id": 1
    }'

test_endpoint \
    "Approve Payroll" \
    "POST" \
    "/payrolls-approve" \
    '{
        "payroll_id": '$PAYROLL_ID',
        "user_id": 1
    }'

test_endpoint \
    "Reject Payroll" \
    "POST" \
    "/payrolls-reject" \
    '{
        "payroll_id": '$PAYROLL_ID',
        "user_id": 1,
        "rejection_reason": "Needs review"
    }'

test_endpoint \
    "Process Payroll" \
    "POST" \
    "/payrolls-process" \
    '{
        "payroll_id": '$PAYROLL_ID',
        "user_id": 1
    }'

test_endpoint \
    "Disburse Payroll" \
    "POST" \
    "/payrolls-disburse" \
    '{
        "payroll_id": '$PAYROLL_ID',
        "user_id": 1,
        "payment_method": "bank_transfer"
    }'

test_endpoint \
    "Cancel Payroll" \
    "POST" \
    "/payrolls-cancel" \
    '{
        "payroll_id": '$PAYROLL_ID',
        "cancelled_by": 1,
        "reason": "Test cancellation"
    }'

# ============================================================================
# STEP 6: PAYMENT OPERATIONS
# ============================================================================

section "STEP 6: PAYMENT OPERATIONS"

test_endpoint \
    "Generate Payment Receipt" \
    "POST" \
    "/payments-generate-receipt" \
    '{
        "payment_id": '$PAYROLL_ID',
        "include_qr": true
    }'

test_endpoint \
    "Generate Payslip" \
    "POST" \
    "/payments-generate-payslip" \
    '{
        "staff_payment_id": '$PAYROLL_ID'
    }'

test_endpoint \
    "Send Payment Notification" \
    "POST" \
    "/payments-send-notification" \
    '{
        "payment_id": '$PAYROLL_ID',
        "recipient": "staff",
        "method": "email"
    }'

# ============================================================================
# STEP 7: FEE STRUCTURE MANAGEMENT
# ============================================================================

section "STEP 7: FEE STRUCTURE MANAGEMENT"

test_endpoint \
    "Get Fee Term Breakdown" \
    "GET" \
    "/fees-term-breakdown?academic_year_id=$ACADEMIC_YEAR_ID&term=1"

test_endpoint \
    "Get Pending Fee Reviews" \
    "GET" \
    "/fees-pending-reviews"

test_endpoint \
    "Get Fee Annual Summary" \
    "GET" \
    "/fees-annual-summary?academic_year_id=$ACADEMIC_YEAR_ID"

test_endpoint \
    "Get Student Payment History" \
    "GET" \
    "/students-payment-history?student_id=$STUDENT_ID"

test_endpoint \
    "Create Annual Fee Structure" \
    "POST" \
    "/fees-create-annual-structure" \
    '{
        "academic_year": 2025,
        "level_id": 1,
        "student_type_id": 1,
        "term_breakdown": [{"term_id": 1, "fee_type_id": 1, "amount": 50000}],
        "created_by": 1
    }'

test_endpoint \
    "Review Fee Structure" \
    "POST" \
    "/fees-review-structure" \
    '{
        "academic_year": 2025,
        "level_id": 1,
        "reviewed_by": 1,
        "review_notes": "Structure looks good"
    }'

test_endpoint \
    "Approve Fee Structure" \
    "POST" \
    "/fees-approve-structure" \
    '{
        "academic_year": 2025,
        "level_id": 1,
        "approved_by": 1
    }'

test_endpoint \
    "Activate Fee Structure" \
    "POST" \
    "/fees-activate-structure" \
    '{
        "academic_year": 2025,
        "level_id": 1
    }'

test_endpoint \
    "Rollover Fee Structure" \
    "POST" \
    "/fees-rollover-structure" \
    '{
        "source_year": 2024,
        "target_year": 2025
    }'

# ============================================================================
# STEP 8: FINANCIAL REPORTS
# ============================================================================

section "STEP 8: FINANCIAL REPORTS"

test_endpoint \
    "Generate Payroll Report" \
    "POST" \
    "/reports-generate-payroll" \
    '{
        "start_date": "2025-01-01",
        "end_date": "2025-12-31"
    }'

test_endpoint \
    "Compare Yearly Collections" \
    "GET" \
    "/reports-compare-yearly-collections?year1=2024&year2=2025"

# ============================================================================
# FINAL SUMMARY
# ============================================================================

print_summary
