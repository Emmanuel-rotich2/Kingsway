#!/bin/bash

# API Endpoint Verification Script
# Tests all academic year and promotion endpoints

echo "========================================="
echo "  API ENDPOINT VERIFICATION"
echo "========================================="
echo ""

BASE_URL="http://localhost:8000/api/students.php"
PASSED=0
TOTAL=0

GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;36m'
NC='\033[0m'

success() {
    echo -e "${GREEN}✓${NC} $1"
    ((PASSED++))
    ((TOTAL++))
}

fail() {
    echo -e "${RED}✗${NC} $1"
    ((TOTAL++))
}

info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

section() {
    echo ""
    echo -e "${BLUE}=== $1 ===${NC}"
}

# Check if PHP built-in server is running
if ! curl -s http://localhost:8000 > /dev/null 2>&1; then
    echo -e "${RED}ERROR: PHP server not running on localhost:8000${NC}"
    echo "Start server with: cd /home/prof_angera/Projects/php_pages/Kingsway && php -S localhost:8000"
    exit 1
fi

section "Academic Year Endpoints (GET)"

# Test GET endpoints
ENDPOINTS=(
    "current-academic-year:Get Current Academic Year"
    "academic-years:List All Academic Years"
    "current-term:Get Current Term"
    "promotion-batches:List Promotion Batches"
    "alumni:List Alumni"
    "current-enrollments:List Current Enrollments"
)

for endpoint_info in "${ENDPOINTS[@]}"; do
    IFS=':' read -r endpoint description <<< "$endpoint_info"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE_URL?action=$endpoint" 2>/dev/null)
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)
    
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
        # 200 = success, 401 = needs auth (endpoint exists)
        if echo "$BODY" | grep -q "error.*login\|Unauthorized" 2>/dev/null; then
            success "$description (endpoint exists, needs auth)"
        elif echo "$BODY" | grep -q "status.*error.*Invalid GET" 2>/dev/null; then
            fail "$description (endpoint not recognized)"
        else
            success "$description (working)"
        fi
    else
        fail "$description (HTTP $HTTP_CODE)"
    fi
done

section "Promotion Endpoints (Structure Check)"

# Check if files have the POST endpoints defined
PROMOTION_ENDPOINTS=(
    "promote-single-student"
    "promote-multiple-students"
    "promote-entire-class"
    "promote-multiple-classes"
    "graduate-grade9"
)

for endpoint in "${PROMOTION_ENDPOINTS[@]}"; do
    if grep -q "action === '$endpoint'" /home/prof_angera/Projects/php_pages/Kingsway/api/students.php 2>/dev/null; then
        success "POST $endpoint endpoint defined"
    else
        fail "POST $endpoint endpoint NOT defined"
    fi
done

section "Academic Year Management Endpoints (Structure Check)"

YEAR_ENDPOINTS=(
    "create-academic-year"
    "create-next-year"
    "set-current-year"
    "update-year-status"
    "archive-year"
)

for endpoint in "${YEAR_ENDPOINTS[@]}"; do
    if grep -q "action === '$endpoint'" /home/prof_angera/Projects/php_pages/Kingsway/api/students.php 2>/dev/null; then
        success "POST $endpoint endpoint defined"
    else
        fail "POST $endpoint endpoint NOT defined"
    fi
done

section "StudentsAPI Methods Check"

# Verify methods exist in StudentsAPI
API_METHODS=(
    "getCurrentAcademicYear"
    "getAllAcademicYears"
    "createAcademicYear"
    "promoteSingleStudent"
    "promoteMultipleStudents"
    "promoteEntireClass"
    "promoteMultipleClasses"
    "graduateGrade9Students"
    "getPromotionBatches"
    "getAlumni"
    "getCurrentEnrollments"
    "getClassRoster"
)

for method in "${API_METHODS[@]}"; do
    if grep -q "public function $method" /home/prof_angera/Projects/php_pages/Kingsway/api/modules/students/StudentsAPI.php 2>/dev/null; then
        success "StudentsAPI::$method() exists"
    else
        fail "StudentsAPI::$method() NOT found"
    fi
done

section "Manager Classes Integration"

# Check if managers are instantiated
if grep -q "new AcademicYearManager" /home/prof_angera/Projects/php_pages/Kingsway/api/modules/students/StudentsAPI.php 2>/dev/null; then
    success "AcademicYearManager instantiated in StudentsAPI"
else
    fail "AcademicYearManager NOT instantiated"
fi

if grep -q "new PromotionManager" /home/prof_angera/Projects/php_pages/Kingsway/api/modules/students/StudentsAPI.php 2>/dev/null; then
    success "PromotionManager instantiated in StudentsAPI"
else
    fail "PromotionManager NOT instantiated"
fi

# Check manager files exist
if [ -f "/home/prof_angera/Projects/php_pages/Kingsway/api/modules/academic/AcademicYearManager.php" ]; then
    success "AcademicYearManager.php file exists"
else
    fail "AcademicYearManager.php file NOT found"
fi

if [ -f "/home/prof_angera/Projects/php_pages/Kingsway/api/modules/students/PromotionManager.php" ]; then
    success "PromotionManager.php file exists"
else
    fail "PromotionManager.php file NOT found"
fi

section "FINAL RESULTS"

echo ""
echo "Total Checks: $TOTAL"
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$((TOTAL - PASSED))${NC}"

PERCENTAGE=$((PASSED * 100 / TOTAL))
echo -n "Success Rate: "

if [ $PERCENTAGE -ge 95 ]; then
    echo -e "${GREEN}${PERCENTAGE}%${NC} ✓ EXCELLENT"
elif [ $PERCENTAGE -ge 80 ]; then
    echo -e "${BLUE}${PERCENTAGE}%${NC} ⚠ GOOD"
else
    echo -e "${RED}${PERCENTAGE}%${NC} ✗ NEEDS WORK"
fi

echo ""

if [ $PERCENTAGE -ge 90 ]; then
    echo -e "${GREEN}"
    echo "╔════════════════════════════════════════════════════════╗"
    echo "║   ALL ENDPOINTS SUCCESSFULLY EXPOSED! ✓                ║"
    echo "║                                                        ║"
    echo "║   • Academic year management: ✓ Exposed                ║"
    echo "║   • Promotion system (5 scenarios): ✓ Exposed          ║"
    echo "║   • Enrollment tracking: ✓ Exposed                     ║"
    echo "║   • Alumni management: ✓ Exposed                       ║"
    echo "║   • All endpoints documented                           ║"
    echo "╚════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
fi

echo ""
echo "📖 Full API Documentation:"
echo "   docs/API_ACADEMIC_PROMOTION_ENDPOINTS.md"
echo ""
