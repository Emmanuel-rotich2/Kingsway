#!/bin/bash
# User Management System - Quick Test Script
# Run this to verify everything is working

echo "=================================="
echo "User Management System Test"
echo "=================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Check JavaScript files exist
echo "📁 Test 1: Checking JavaScript files..."
if [ -f "js/utils/form-validation.js" ]; then
    echo -e "${GREEN}✓${NC} form-validation.js exists"
else
    echo -e "${RED}✗${NC} form-validation.js NOT FOUND"
fi

if [ -f "js/pages/users.js" ]; then
    echo -e "${GREEN}✓${NC} users.js exists"
else
    echo -e "${RED}✗${NC} users.js NOT FOUND"
fi

echo ""

# Test 2: Check PHP backend files exist
echo "📁 Test 2: Checking PHP backend files..."
if [ -f "api/includes/ValidationHelper.php" ]; then
    echo -e "${GREEN}✓${NC} ValidationHelper.php exists"
else
    echo -e "${RED}✗${NC} ValidationHelper.php NOT FOUND"
fi

if [ -f "api/includes/AuditLogger.php" ]; then
    echo -e "${GREEN}✓${NC} AuditLogger.php exists"
else
    echo -e "${RED}✗${NC} AuditLogger.php NOT FOUND"
fi

echo ""

# Test 3: Check database tables
echo "🗄️  Test 3: Checking database tables..."
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy -e "
SELECT 
    CASE 
        WHEN COUNT(*) = 4 THEN 'All security tables exist'
        ELSE CONCAT(COUNT(*), ' tables found (expected 4)')
    END as status
FROM information_schema.tables 
WHERE table_schema = 'KingsWayAcademy' 
AND table_name IN ('audit_logs', 'password_history', 'login_attempts', 'user_sessions');" 2>&1 | grep -v "Using a password"

echo ""

# Test 4: Check manage_users.php has script includes
echo "📄 Test 4: Checking manage_users.php script includes..."
if grep -q "form-validation.js" pages/manage_users.php; then
    echo -e "${GREEN}✓${NC} form-validation.js is included"
else
    echo -e "${RED}✗${NC} form-validation.js NOT included in manage_users.php"
fi

if grep -q "users.js" pages/manage_users.php; then
    echo -e "${GREEN}✓${NC} users.js is included"
else
    echo -e "${RED}✗${NC} users.js NOT included in manage_users.php"
fi

echo ""

# Test 5: Test API endpoints
echo "🌐 Test 5: Testing API endpoints..."

# Test users index
RESPONSE=$(curl -s "http://localhost/Kingsway/api/users/index" 2>&1)
if echo "$RESPONSE" | grep -q '"success"\|"data"\|\['; then
    echo -e "${GREEN}✓${NC} /api/users/index responds"
else
    echo -e "${RED}✗${NC} /api/users/index failed"
fi

# Test roles endpoint
RESPONSE=$(curl -s "http://localhost/Kingsway/api/users/roles-get" 2>&1)
if echo "$RESPONSE" | grep -q '"success"\|"data"\|\['; then
    echo -e "${GREEN}✓${NC} /api/users/roles-get responds"
else
    echo -e "${RED}✗${NC} /api/users/roles-get failed"
fi

# Test permissions endpoint
RESPONSE=$(curl -s "http://localhost/Kingsway/api/users/permissions-get" 2>&1)
if echo "$RESPONSE" | grep -q '"success"\|"data"\|\['; then
    echo -e "${GREEN}✓${NC} /api/users/permissions-get responds"
else
    echo -e "${RED}✗${NC} /api/users/permissions-get failed"
fi

echo ""

# Test 6: Check page loads
echo "📄 Test 6: Checking manage_users page loads..."
RESPONSE=$(curl -s "http://localhost/Kingsway/home.php?route=manage_users" 2>&1)
if echo "$RESPONSE" | grep -q "<!DOCTYPE html"; then
    echo -e "${GREEN}✓${NC} Page loads HTML"
    
    if echo "$RESPONSE" | grep -q "form-validation.js"; then
        echo -e "${GREEN}✓${NC} form-validation.js is in page"
    else
        echo -e "${YELLOW}⚠${NC} form-validation.js might not be loading"
    fi
    
    if echo "$RESPONSE" | grep -q "users.js"; then
        echo -e "${GREEN}✓${NC} users.js is in page"
    else
        echo -e "${YELLOW}⚠${NC} users.js might not be loading"
    fi
else
    echo -e "${RED}✗${NC} Page does not load properly"
fi

echo ""
echo "=================================="
echo "Test Complete!"
echo "=================================="
echo ""
echo "To view the page, open:"
echo "  ${GREEN}http://localhost/Kingsway/home.php?route=manage_users${NC}"
echo ""
echo "To test API directly, open:"
echo "  ${GREEN}http://localhost/Kingsway/test_user_management.html${NC}"
echo ""
echo "To check browser console for errors:"
echo "  1. Open the page"
echo "  2. Press F12"
echo "  3. Go to Console tab"
echo "  4. Look for initialization messages"
echo ""
