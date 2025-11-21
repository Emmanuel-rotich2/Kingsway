#!/bin/bash

# Quick Integration Check
# Verifies that all staff module files are properly integrated

echo "========================================="
echo "Staff Module Integration Check"
echo "========================================="
echo ""

PASSED=0
FAILED=0

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

check_file() {
    local file="$1"
    local description="$2"
    
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $description"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗${NC} $description (NOT FOUND: $file)"
        FAILED=$((FAILED + 1))
    fi
}

check_method() {
    local file="$1"
    local method="$2"
    local description="$3"
    
    if grep -q "$method" "$file" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $description"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗${NC} $description (Method not found: $method)"
        FAILED=$((FAILED + 1))
    fi
}

echo -e "${BLUE}Checking Manager Files...${NC}"
check_file "api/modules/staff/StaffPayrollManager.php" "StaffPayrollManager exists"
check_file "api/modules/staff/StaffOnboardingManager.php" "StaffOnboardingManager exists"
check_file "api/modules/staff/StaffPerformanceManager.php" "StaffPerformanceManager exists"
check_file "api/modules/staff/StaffLeaveManager.php" "StaffLeaveManager exists"
check_file "api/modules/staff/StaffAssignmentManager.php" "StaffAssignmentManager exists"
echo ""

echo -e "${BLUE}Checking Workflow Files...${NC}"
check_file "api/modules/staff/LeaveWorkflow.php" "LeaveWorkflow exists"
check_file "api/modules/staff/AssignmentWorkflow.php" "AssignmentWorkflow exists"
check_file "api/modules/staff/OnboardingWorkflow.php" "OnboardingWorkflow exists"
check_file "api/modules/staff/EvaluationWorkflow.php" "EvaluationWorkflow exists"
echo ""

echo -e "${BLUE}Checking Integration Layer...${NC}"
check_file "api/modules/staff/StaffService.php" "StaffService exists"
check_method "api/modules/staff/StaffService.php" "getPayrollManager" "StaffService has getPayrollManager()"
check_method "api/modules/staff/StaffService.php" "getLeaveWorkflow" "StaffService has getLeaveWorkflow()"
echo ""

echo -e "${BLUE}Checking API Integration...${NC}"
check_file "api/modules/staff/StaffAPI.php" "StaffAPI exists"
check_method "api/modules/staff/StaffAPI.php" "private \$service" "StaffAPI has service property"
check_method "api/modules/staff/StaffAPI.php" "public function viewPayslip" "StaffAPI has viewPayslip()"
check_method "api/modules/staff/StaffAPI.php" "public function requestAdvance" "StaffAPI has requestAdvance()"
check_method "api/modules/staff/StaffAPI.php" "public function initiateLeaveRequest" "StaffAPI has initiateLeaveRequest()"
check_method "api/modules/staff/StaffAPI.php" "public function getStaffWorkload" "StaffAPI has getStaffWorkload()"
echo ""

echo -e "${BLUE}Checking REST Endpoints...${NC}"
check_file "api/staff.php" "REST endpoint file exists"
check_method "api/staff.php" "resource.*payroll" "REST has payroll resource routes"
check_method "api/staff.php" "resource.*performance" "REST has performance resource routes"
check_method "api/staff.php" "resource.*assignments" "REST has assignment resource routes"
check_method "api/staff.php" "resource.*workflows" "REST has workflow resource routes"
echo ""

echo -e "${BLUE}Checking Documentation...${NC}"
check_file "docs/STAFF_WORKFLOWS_COMPLETE_REFERENCE.md" "Complete reference documentation"
check_file "docs/STAFF_MODULE_COMPLETION_SUMMARY.md" "Completion summary"
check_file "docs/STAFF_MODULE_ARCHITECTURE.md" "Architecture documentation"
echo ""

echo -e "${BLUE}Checking Test Files...${NC}"
check_file "tests/verify_staff_workflows.sh" "Workflow verification script"
echo ""

echo "========================================="
echo "INTEGRATION CHECK SUMMARY"
echo "========================================="
echo -e "Checks Passed: ${GREEN}$PASSED${NC}"
echo -e "Checks Failed: ${RED}$FAILED${NC}"
echo "Total Checks: $((PASSED + FAILED))"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All integration checks passed!${NC}"
    echo "The staff module is fully integrated and ready for testing."
    exit 0
else
    echo -e "${RED}✗ Some checks failed. Please review the output above.${NC}"
    exit 1
fi
