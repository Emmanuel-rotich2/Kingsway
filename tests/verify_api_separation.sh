#!/bin/bash

# API Endpoint Separation Verification Script
# Verifies that academic year endpoints are in academic.php
# and student-specific endpoints are in students.php

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║    API ENDPOINT SEPARATION VERIFICATION                   ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

PASSED=0
TOTAL=0

GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;36m'
YELLOW='\033[1;33m'
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
    echo -e "${YELLOW}=== $1 ===${NC}"
}

# ============================================
section "ACADEMIC YEAR ENDPOINTS (should be in academic.php)"
# ============================================

ACADEMIC_ENDPOINTS=(
    "academic-years"
    "academic-year"
    "current-academic-year"
    "year-terms"
    "current-term"
    "create-academic-year"
    "create-next-year"
    "set-current-year"
    "update-year-status"
    "archive-year"
)

for endpoint in "${ACADEMIC_ENDPOINTS[@]}"; do
    if grep -q "'$endpoint'" api/academic.php 2>/dev/null; then
        success "✓ $endpoint in academic.php"
    else
        fail "$endpoint NOT in academic.php"
    fi
done

# ============================================
section "VERIFY Academic Year Endpoints NOT in students.php"
# ============================================

SHOULD_NOT_BE_IN_STUDENTS=(
    "academic-years"
    "current-academic-year"
    "year-terms"
    "current-term"
    "create-academic-year"
    "create-next-year"
    "set-current-year"
    "update-year-status"
    "archive-year"
)

for endpoint in "${SHOULD_NOT_BE_IN_STUDENTS[@]}"; do
    if ! grep -q "'$endpoint'" api/students.php 2>/dev/null; then
        success "$endpoint removed from students.php"
    else
        fail "$endpoint STILL in students.php (should be removed)"
    fi
done

# ============================================
section "STUDENT-SPECIFIC ENDPOINTS (should be in students.php)"
# ============================================

STUDENT_ENDPOINTS=(
    "promote-single-student"
    "promote-multiple-students"
    "promote-entire-class"
    "promote-multiple-classes"
    "graduate-grade9"
    "promotion-batches"
    "alumni"
    "current-enrollments"
    "class-roster"
)

for endpoint in "${STUDENT_ENDPOINTS[@]}"; do
    if grep -q "'$endpoint'" api/students.php 2>/dev/null; then
        success "$endpoint in students.php"
    else
        fail "$endpoint NOT in students.php"
    fi
done

# ============================================
section "VERIFY Student Endpoints NOT in academic.php"
# ============================================

for endpoint in "${STUDENT_ENDPOINTS[@]}"; do
    if ! grep -q "'$endpoint'" api/academic.php 2>/dev/null; then
        success "$endpoint correctly NOT in academic.php"
    else
        fail "$endpoint incorrectly in academic.php"
    fi
done

# ============================================
section "MANAGER INTEGRATION CHECK"
# ============================================

# Check AcademicYearManager in academic.php
if grep -q "AcademicYearManager" api/academic.php; then
    success "AcademicYearManager imported in academic.php"
else
    fail "AcademicYearManager NOT imported in academic.php"
fi

if grep -q "new AcademicYearManager" api/academic.php; then
    success "AcademicYearManager instantiated in academic.php"
else
    fail "AcademicYearManager NOT instantiated in academic.php"
fi

# Check PromotionManager in StudentsAPI
if grep -q "PromotionManager" api/modules/students/StudentsAPI.php; then
    success "PromotionManager imported in StudentsAPI"
else
    fail "PromotionManager NOT imported in StudentsAPI"
fi

if grep -q "new PromotionManager" api/modules/students/StudentsAPI.php; then
    success "PromotionManager instantiated in StudentsAPI"
else
    fail "PromotionManager NOT instantiated in StudentsAPI"
fi

# ============================================
section "ENDPOINT RESPONSE FORMAT CHECK"
# ============================================

# Check academic.php returns proper format
if grep -q "echo json_encode.*'status'.*'success'" api/academic.php; then
    success "academic.php uses correct response format"
else
    fail "academic.php response format issues"
fi

# Check students.php response format
if grep -q "echo json_encode" api/students.php; then
    success "students.php uses json_encode"
else
    fail "students.php missing json_encode"
fi

# ============================================
section "FINAL RESULTS"
# ============================================

echo ""
echo "Total Tests: $TOTAL"
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$((TOTAL - PASSED))${NC}"

PERCENTAGE=$((PASSED * 100 / TOTAL))
echo -n "Success Rate: "

if [ $PERCENTAGE -ge 95 ]; then
    echo -e "${GREEN}${PERCENTAGE}%${NC} ✓ EXCELLENT"
elif [ $PERCENTAGE -ge 80 ]; then
    echo -e "${YELLOW}${PERCENTAGE}%${NC} ⚠ GOOD"
else
    echo -e "${RED}${PERCENTAGE}%${NC} ✗ NEEDS WORK"
fi

echo ""

# ============================================
section "ENDPOINT SUMMARY"
# ============================================

echo ""
echo "📍 ACADEMIC.PHP (Academic Year Management):"
echo "   GET  /api/academic.php?action=academic-years"
echo "   GET  /api/academic.php?action=current-academic-year"
echo "   GET  /api/academic.php?action=year-terms&year_id={id}"
echo "   GET  /api/academic.php?action=current-term"
echo "   POST /api/academic.php?action=create-academic-year"
echo "   POST /api/academic.php?action=create-next-year"
echo "   POST /api/academic.php?action=set-current-year&id={id}"
echo "   POST /api/academic.php?action=update-year-status&id={id}"
echo "   POST /api/academic.php?action=archive-year&id={id}"
echo ""
echo "👨‍🎓 STUDENTS.PHP (Student Operations & Promotions):"
echo "   GET  /api/students.php?action=promotion-batches"
echo "   GET  /api/students.php?action=alumni"
echo "   GET  /api/students.php?action=current-enrollments&year_id={id}"
echo "   GET  /api/students.php?action=class-roster&class_id={id}&stream_id={id}"
echo "   POST /api/students.php?action=promote-single-student"
echo "   POST /api/students.php?action=promote-multiple-students"
echo "   POST /api/students.php?action=promote-entire-class"
echo "   POST /api/students.php?action=promote-multiple-classes"
echo "   POST /api/students.php?action=graduate-grade9"
echo ""

if [ $PERCENTAGE -ge 95 ]; then
    echo -e "${GREEN}"
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║   ✓ API ENDPOINTS PROPERLY SEPARATED                      ║"
    echo "║                                                            ║"
    echo "║   • Academic year management → academic.php                ║"
    echo "║   • Student operations & promotions → students.php         ║"
    echo "║   • Clean separation of concerns ✓                         ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
fi

echo ""
