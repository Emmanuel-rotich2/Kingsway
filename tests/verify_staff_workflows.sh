#!/bin/bash

# Test Staff Workflows Integration
# This script verifies all staff workflow handlers, managers, and API integration

BASE_URL="http://localhost:8000/api"
TOKEN=""  # Add your test JWT token here

echo "========================================="
echo "Staff Workflows Integration Test"
echo "========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Helper function to test endpoint
test_endpoint() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    
    echo -n "Testing $name... "
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -X GET \
            -H "Authorization: Bearer $TOKEN" \
            "$BASE_URL$endpoint")
    else
        response=$(curl -s -w "\n%{http_code}" -X POST \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -d "$data" \
            "$BASE_URL$endpoint")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ "$http_code" -eq 200 ] || [ "$http_code" -eq 201 ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $http_code)"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} (HTTP $http_code)"
        echo "Response: $body"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

echo "========================================="
echo "1. PAYROLL OPERATIONS"
echo "========================================="

# Test payslip view
test_endpoint "View Payslip" "GET" "/staff.php?id=1&resource=payroll&sub_action=payslip&month=1&year=2024"

# Test payroll history
test_endpoint "Get Payroll History" "GET" "/staff.php?id=1&resource=payroll&sub_action=history"

# Test allowances
test_endpoint "View Allowances" "GET" "/staff.php?id=1&resource=payroll&sub_action=allowances"

# Test deductions
test_endpoint "View Deductions" "GET" "/staff.php?id=1&resource=payroll&sub_action=deductions"

# Test loan details
test_endpoint "Get Loan Details" "GET" "/staff.php?id=1&resource=payroll&sub_action=loans"

# Test request advance
advance_data='{
    "amount": 50000,
    "reason": "Medical emergency",
    "repayment_months": 3
}'
test_endpoint "Request Salary Advance" "POST" "/staff.php?id=1&resource=payroll&sub_action=advance" "$advance_data"

# Test apply for loan
loan_data='{
    "amount": 200000,
    "purpose": "School fees",
    "repayment_months": 12,
    "guarantor_1": "John Doe",
    "guarantor_2": "Jane Smith"
}'
test_endpoint "Apply for Loan" "POST" "/staff.php?id=1&resource=payroll&sub_action=loan" "$loan_data"

# Test P9 download
test_endpoint "Download P9 Form" "GET" "/staff.php?id=1&resource=payroll&sub_action=p9&year=2024"

echo ""
echo "========================================="
echo "2. PERFORMANCE OPERATIONS"
echo "========================================="

# Test review history
test_endpoint "Get Review History" "GET" "/staff.php?id=1&resource=performance&sub_action=reviews"

# Test performance report
test_endpoint "Generate Performance Report" "GET" "/staff.php?id=1&resource=performance&sub_action=report&review_id=1"

# Test academic KPI summary
test_endpoint "Get Academic KPI Summary" "GET" "/staff.php?id=1&resource=performance&sub_action=kpi"

echo ""
echo "========================================="
echo "3. ASSIGNMENT OPERATIONS"
echo "========================================="

# Test staff assignments
test_endpoint "Get Staff Assignments" "GET" "/staff.php?id=1&resource=assignments&sub_action=list"

# Test workload summary
test_endpoint "Get Staff Workload" "GET" "/staff.php?id=1&resource=assignments&sub_action=workload"

# Test current assignments
test_endpoint "Get Current Assignments" "GET" "/staff.php?id=1&resource=assignments&sub_action=current"

echo ""
echo "========================================="
echo "4. LEAVE WORKFLOW"
echo "========================================="

# Test initiate leave request
leave_data='{
    "leave_type_id": 1,
    "start_date": "2024-02-01",
    "end_date": "2024-02-05",
    "reason": "Family vacation",
    "contact_during_leave": "+254700123456"
}'
test_endpoint "Initiate Leave Request" "POST" "/staff.php?id=1&resource=workflows&sub_action=leave" "$leave_data"

echo ""
echo "========================================="
echo "5. ASSIGNMENT WORKFLOW"
echo "========================================="

# Test initiate assignment
assignment_data='{
    "class_stream_id": 1,
    "academic_year_id": 1,
    "subject_id": 2,
    "role": "subject_teacher",
    "effective_date": "2024-01-15"
}'
test_endpoint "Initiate Assignment" "POST" "/staff.php?id=1&resource=workflows&sub_action=assignment" "$assignment_data"

echo ""
echo "========================================="
echo "TEST SUMMARY"
echo "========================================="
echo -e "Tests Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Tests Failed: ${RED}$TESTS_FAILED${NC}"
echo "Total Tests: $((TESTS_PASSED + TESTS_FAILED))"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Please review the output above.${NC}"
    exit 1
fi
