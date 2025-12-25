#!/bin/bash

# Comprehensive system verification script
# Tests all critical paths: authentication, roles, dashboards, sidebar, permissions

API_URL="http://localhost/Kingsway/api"
TEST_TOKEN="devtest"

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║   KINGSWAY SYSTEM VERIFICATION TEST                            ║"
echo "║   Authentication → Roles → Dashboard → Sidebar → Permissions  ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Test each role
ROLES=(
    "test_sysadmin|System Administrator|system_administrator_dashboard|13"
    "test_director|Director|director_owner_dashboard|4"
    "test_scholadmin|School Administrator|school_administrative_officer_dashboard|5"
    "test_headteacher|Headteacher|headteacher_dashboard|5"
    "test_accountant|Accountant|school_accountant_dashboard|6"
    "test_classteacher|Class Teacher|class_teacher_dashboard|5"
)

PASSED=0
FAILED=0

for role_spec in "${ROLES[@]}"; do
    IFS='|' read -r username role_name dashboard_key expected_items <<< "$role_spec"
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Testing: $role_name ($username)"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # Login
    RESPONSE=$(curl -s -X POST "${API_URL}/auth/login" \
        -H "Content-Type: application/json" \
        -H "X-Test-Token: ${TEST_TOKEN}" \
        -d "{\"username\": \"$username\", \"password\": \"Pass123!@\"}")
    
    # Verify login
    LOGIN_STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
    if [ "$LOGIN_STATUS" != "success" ]; then
        echo "  ✗ FAILED: Login failed"
        FAILED=$((FAILED + 1))
        continue
    fi
    echo "  ✓ Login successful"
    
    # Verify role
    ACTUAL_ROLE=$(echo "$RESPONSE" | jq -r '.data.user.roles[0].name' 2>/dev/null)
    if [ "$ACTUAL_ROLE" != "$role_name" ]; then
        echo "  ✗ FAILED: Expected role '$role_name', got '$ACTUAL_ROLE'"
        FAILED=$((FAILED + 1))
        continue
    fi
    echo "  ✓ Role: $ACTUAL_ROLE"
    
    # Verify dashboard routing
    ACTUAL_DASHBOARD=$(echo "$RESPONSE" | jq -r '.data.dashboard.key' 2>/dev/null)
    if [ "$ACTUAL_DASHBOARD" != "$dashboard_key" ]; then
        echo "  ✗ FAILED: Expected dashboard '$dashboard_key', got '$ACTUAL_DASHBOARD'"
        FAILED=$((FAILED + 1))
        continue
    fi
    echo "  ✓ Dashboard: $ACTUAL_DASHBOARD"
    
    # Verify dashboard file exists
    DASHBOARD_FILE="/home/prof_angera/Projects/php_pages/Kingsway/components/dashboards/${ACTUAL_DASHBOARD}.php"
    if [ ! -f "$DASHBOARD_FILE" ]; then
        echo "  ✗ FAILED: Dashboard file not found: $DASHBOARD_FILE"
        FAILED=$((FAILED + 1))
        continue
    fi
    echo "  ✓ Dashboard file exists"
    
    # Verify sidebar items
    ACTUAL_ITEMS=$(echo "$RESPONSE" | jq '.data.sidebar_items | length' 2>/dev/null)
    if [ -z "$ACTUAL_ITEMS" ] || [ "$ACTUAL_ITEMS" -eq 0 ]; then
        echo "  ✗ FAILED: No sidebar items returned (expected $expected_items)"
        FAILED=$((FAILED + 1))
        continue
    fi
    echo "  ✓ Sidebar items: $ACTUAL_ITEMS (expected: $expected_items)"
    
    # Verify first sidebar item is Dashboard
    FIRST_ITEM_LABEL=$(echo "$RESPONSE" | jq -r '.data.sidebar_items[0].label' 2>/dev/null)
    FIRST_ITEM_URL=$(echo "$RESPONSE" | jq -r '.data.sidebar_items[0].url' 2>/dev/null)
    if [ "$FIRST_ITEM_LABEL" != "Dashboard" ]; then
        echo "  ✗ FAILED: First sidebar item should be 'Dashboard', got '$FIRST_ITEM_LABEL'"
        FAILED=$((FAILED + 1))
        continue
    fi
    if [ "$FIRST_ITEM_URL" != "$dashboard_key" ]; then
        echo "  ✗ FAILED: Dashboard URL should be '$dashboard_key', got '$FIRST_ITEM_URL'"
        FAILED=$((FAILED + 1))
        continue
    fi
    echo "  ✓ First sidebar item: Dashboard → $FIRST_ITEM_URL"
    
    # Verify permissions
    PERMS=$(echo "$RESPONSE" | jq '.data.user.permissions | length' 2>/dev/null)
    if [ -z "$PERMS" ] || [ "$PERMS" -eq 0 ]; then
        echo "  ✗ FAILED: No permissions returned"
        FAILED=$((FAILED + 1))
        continue
    fi
    echo "  ✓ Permissions: $PERMS"
    
    echo "  ✓ PASSED"
    PASSED=$((PASSED + 1))
    echo ""
done

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║   TEST RESULTS                                                 ║"
echo "╠════════════════════════════════════════════════════════════════╣"
echo "║  Passed: $PASSED                                                    ║"
echo "║  Failed: $FAILED                                                    ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

if [ $FAILED -eq 0 ]; then
    echo "✓ All tests PASSED! System is ready for production."
    exit 0
else
    echo "✗ Some tests FAILED. Please review the errors above."
    exit 1
fi
