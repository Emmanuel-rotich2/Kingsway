#!/bin/bash

# Finance API Comprehensive Endpoint Test Suite
# Tests all 41 Finance endpoints with realistic payloads

BASE_URL="http://localhost/Kingsway/api"
AUTH_HEADER="X-Test-Token: devtest"
RESULTS_FILE="test_finance_results.txt"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Initialize results file
> "$RESULTS_FILE"

# Test counter
TOTAL=0
PASSED=0
FAILED=0

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    TOTAL=$((TOTAL + 1))
    
    if [ -z "$data" ]; then
        response=$(curl -s -X "$method" "$BASE_URL$endpoint" \
            -H "$AUTH_HEADER" \
            -H "Content-Type: application/json")
    else
        response=$(curl -s -X "$method" "$BASE_URL$endpoint" \
            -H "$AUTH_HEADER" \
            -H "Content-Type: application/json" \
            -d "$data")
    fi
    
    # Check if response contains error
    if echo "$response" | grep -q '"code":200'; then
        echo -e "${GREEN}✓${NC} $method $endpoint - $description"
        echo "✓ $method $endpoint - $description" >> "$RESULTS_FILE"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗${NC} $method $endpoint - $description"
        echo "✗ $method $endpoint - $description" >> "$RESULTS_FILE"
        echo "  Response: $response" >> "$RESULTS_FILE"
        FAILED=$((FAILED + 1))
    fi
    
    echo "$response" >> "$RESULTS_FILE"
}

echo "🧪 Finance API Endpoint Tests"
echo "================================"

# ==================== INDEX & CRUD ====================
echo -e "\n${YELLOW}Testing Index & Base CRUD${NC}"
test_endpoint "GET" "/finance/index" "" "Get finance index"
test_endpoint "GET" "/finance" "" "Get finance (CRUD)"
test_endpoint "POST" "/finance" '{"department_id":1,"amount":50000,"description":"General budget","fiscal_year":2025}' "Post finance"
test_endpoint "PUT" "/finance" '{"id":1,"amount":60000,"description":"Updated budget"}' "Put finance"
test_endpoint "DELETE" "/finance" '{"id":1}' "Delete finance"

# ==================== DEPARTMENT BUDGETS ====================
echo -e "\n${YELLOW}Testing Department Budgets${NC}"
test_endpoint "POST" "/finance/department-budgets-propose" '{"department_id":1,"proposed_amount":100000,"justification":"Staff expansion"}' "Propose department budget"
test_endpoint "GET" "/finance/department-budgets-proposals" "" "Get budget proposals"
test_endpoint "POST" "/finance/department-budgets-approve" '{"proposal_id":1,"approved_amount":95000,"comments":"Approved with slight adjustment"}' "Approve budget"
test_endpoint "POST" "/finance/department-budgets-allocate" '{"budget_id":1,"allocation_date":"2025-01-01","released_amount":50000}' "Allocate budget"
test_endpoint "POST" "/finance/department-budgets-request-funds" '{"department_id":1,"requested_amount":25000,"purpose":"Office supplies","urgency":"high"}' "Request funds"

# ==================== PAYROLL MANAGEMENT ====================
echo -e "\n${YELLOW}Testing Payroll Management${NC}"
test_endpoint "GET" "/finance/payrolls-list" "" "Get payrolls list"
test_endpoint "GET" "/finance/payrolls-get?payroll_id=1" "" "Get specific payroll"
test_endpoint "GET" "/finance/payrolls-staff-payments" "" "Get staff payments"
test_endpoint "POST" "/finance/payrolls-create-draft" '{"period":"December 2025","created_by":1}' "Create draft payroll"
test_endpoint "POST" "/finance/payrolls-calculate" '{"payroll_id":1,"include_overtime":true}' "Calculate payroll"
test_endpoint "POST" "/finance/payrolls-recalculate" '{"payroll_id":1,"reason":"Overtime adjustment"}' "Recalculate payroll"
test_endpoint "POST" "/finance/payrolls-verify" '{"payroll_id":1,"verified_by":2}' "Verify payroll"
test_endpoint "POST" "/finance/payrolls-approve" '{"payroll_id":1,"approved_by":3}' "Approve payroll"
test_endpoint "POST" "/finance/payrolls-reject" '{"payroll_id":1,"reason":"Discrepancy in calculations"}' "Reject payroll"
test_endpoint "POST" "/finance/payrolls-process" '{"payroll_id":1}' "Process payroll"
test_endpoint "POST" "/finance/payrolls-disburse" '{"payroll_id":1,"method":"bank_transfer"}' "Disburse payroll"
test_endpoint "POST" "/finance/payrolls-cancel" '{"payroll_id":1,"reason":"Duplicate entry"}' "Cancel payroll"
test_endpoint "GET" "/finance/payrolls-status" "" "Get payroll status"
test_endpoint "GET" "/finance/payrolls-staff-payments-get?staff_id=1" "" "Get staff payments"
test_endpoint "GET" "/finance/payrolls-summary" "" "Get payroll summary"
test_endpoint "GET" "/finance/payrolls-history" "" "Get payroll history"

# ==================== PAYMENT GENERATION ====================
echo -e "\n${YELLOW}Testing Payment Generation${NC}"
test_endpoint "POST" "/finance/payments-generate-receipt" '{"payroll_id":1,"staff_id":1}' "Generate receipt"
test_endpoint "POST" "/finance/payments-generate-payslip" '{"payroll_id":1,"staff_id":1}' "Generate payslip"
test_endpoint "POST" "/finance/payments-send-notification" '{"payroll_id":1,"notification_type":"email"}' "Send payment notification"

# ==================== FEE STRUCTURE ====================
echo -e "\n${YELLOW}Testing Fee Structure{{NC}"
test_endpoint "POST" "/finance/fees-create-annual-structure" '{"academic_year":2025,"created_by":1}' "Create annual fee structure"
test_endpoint "POST" "/finance/fees-review-structure" '{"structure_id":1,"reviewed_by":2}' "Review fee structure"
test_endpoint "POST" "/finance/fees-approve-structure" '{"structure_id":1,"approved_by":3}' "Approve fee structure"
test_endpoint "POST" "/finance/fees-activate-structure" '{"structure_id":1,"activation_date":"2025-01-01"}' "Activate fee structure"
test_endpoint "POST" "/finance/fees-rollover-structure" '{"from_year":2024,"to_year":2025}' "Rollover fee structure"
test_endpoint "GET" "/finance/fees-term-breakdown" "" "Get term breakdown"
test_endpoint "GET" "/finance/fees-pending-reviews" "" "Get pending reviews"
test_endpoint "GET" "/finance/fees-annual-summary" "" "Get annual summary"

# ==================== STUDENT PAYMENTS ====================
echo -e "\n${YELLOW}Testing Student Payments${NC}"
test_endpoint "GET" "/finance/students-payment-history" "" "Get student payment history"

# ==================== REPORTS ====================
echo -e "\n${YELLOW}Testing Reports${NC}"
test_endpoint "POST" "/finance/reports-generate-payroll" '{"month":"December","year":2025}' "Generate payroll report"
test_endpoint "GET" "/finance/reports-compare-yearly-collections" "" "Compare yearly collections"

# Print summary
echo ""
echo "================================"
echo -e "Total: $TOTAL | ${GREEN}Success: $PASSED${NC} | ${RED}Errors: $FAILED${NC}"
PERCENTAGE=$((PASSED * 100 / TOTAL))
echo "Success Rate: $PERCENTAGE%"
echo "✓ Finance endpoints test completed"
echo "✓ Results saved to: $RESULTS_FILE"
