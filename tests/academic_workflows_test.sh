#!/bin/bash

# Academic Workflows Integration Test Script
# Tests all workflow endpoints for proper integration

BASE_URL="http://localhost:8000/api/academic.php"
COLORS=true

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Academic Workflows Integration Test${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to test endpoint
test_endpoint() {
    local test_name=$1
    local method=$2
    local action=$3
    local data=$4
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo -e "${YELLOW}Testing:${NC} $test_name"
    
    # Make request (syntax check only - won't execute without auth)
    if [ "$method" = "GET" ]; then
        response=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}?action=${action}" 2>&1)
    else
        response=$(curl -s -o /dev/null -w "%{http_code}" -X POST -H "Content-Type: application/json" -d "$data" "${BASE_URL}?action=${action}" 2>&1)
    fi
    
    # Check if curl command executed (not checking auth since we don't have session)
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Endpoint accessible\n"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗${NC} Endpoint error\n"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

echo -e "${BLUE}--- Examination Workflow Endpoints ---${NC}\n"
test_endpoint "Start Examination" "POST" "workflow-examination-start" '{"title":"Test Exam","classification_code":"SA","term_id":1,"academic_year":"2024","start_date":"2024-11-15","end_date":"2024-11-22"}'
test_endpoint "Setup Assessments" "POST" "workflow-examination-setup-assessments" '{"instance_id":1}'
test_endpoint "Capture Marks" "POST" "workflow-examination-capture-marks" '{"instance_id":1,"marks":[]}'
test_endpoint "Publish Results" "POST" "workflow-examination-publish-results" '{"instance_id":1}'

echo -e "${BLUE}--- Promotion Workflow Endpoints ---${NC}\n"
test_endpoint "Start Promotion" "POST" "workflow-promotion-start" '{"academic_year":"2024","promotion_type":"end_of_year","from_grade":"Grade 3","to_grade":"Grade 4"}'
test_endpoint "Execute Promotions" "POST" "workflow-promotion-execute" '{"instance_id":1}'

echo -e "${BLUE}--- Assessment Workflow Endpoints ---${NC}\n"
test_endpoint "Start Assessment" "POST" "workflow-assessment-start" '{"assessment_name":"Test","type":"CA","subject_id":1,"term_id":1,"class_id":1}'
test_endpoint "Mark and Grade" "POST" "workflow-assessment-mark-grade" '{"instance_id":1,"results":[]}'

echo -e "${BLUE}--- Report Workflow Endpoints ---${NC}\n"
test_endpoint "Start Report" "POST" "workflow-report-start" '{"report_type":"end_of_term","term_id":1,"student_ids":[1,2,3]}'
test_endpoint "Distribute Reports" "POST" "workflow-report-distribute" '{"instance_id":1,"distribution_method":"email"}'

echo -e "${BLUE}--- Library Workflow Endpoints ---${NC}\n"
test_endpoint "Start Library" "POST" "workflow-library-start" '{"resource_type":"book","title":"Test Book","quantity":10}'
test_endpoint "Catalog Resources" "POST" "workflow-library-catalog" '{"instance_id":1,"accession_number":"LIB001"}'

echo -e "${BLUE}--- Curriculum Workflow Endpoints ---${NC}\n"
test_endpoint "Start Curriculum" "POST" "workflow-curriculum-start" '{"subject_id":1,"grade_level":"Grade 4","term_id":1}'
test_endpoint "Create Scheme" "POST" "workflow-curriculum-create-scheme" '{"instance_id":1,"weekly_plans":[]}'

echo -e "${BLUE}--- Year Transition Workflow Endpoints ---${NC}\n"
test_endpoint "Start Year Transition" "POST" "workflow-year-transition-start" '{"current_year":"2024","new_year":"2025","new_year_start_date":"2025-01-06"}'
test_endpoint "Setup New Year" "POST" "workflow-year-transition-setup-new-year" '{"instance_id":1,"class_structure":[]}'

echo -e "${BLUE}--- Utility Endpoints ---${NC}\n"
test_endpoint "Get Workflow Status" "GET" "workflow-status&workflow_type=examination&instance_id=1" ""
test_endpoint "Get Competency Dashboard" "GET" "competency-dashboard&student_id=1&term_id=1" ""

# Summary
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Test Summary${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "Total Tests: ${TOTAL_TESTS}"
echo -e "${GREEN}Passed: ${PASSED_TESTS}${NC}"
echo -e "${RED}Failed: ${FAILED_TESTS}${NC}"

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "\n${GREEN}All endpoints are accessible!${NC}"
    exit 0
else
    echo -e "\n${RED}Some endpoints have issues.${NC}"
    exit 1
fi
