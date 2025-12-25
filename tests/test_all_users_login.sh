#!/bin/bash

# Test login and check sidebar items for all test users

API_URL="http://localhost/Kingsway/api"
TEST_TOKEN="devtest"

echo "=== Testing Login and Sidebar for All Test Users ==="
echo ""

# Array of test users
USERS=(
    "test_sysadmin:Pass123!@:System Administrator"
    "test_director:Pass123!@:Director"
    "test_headteacher:Pass123!@:Headteacher"
    "test_accountant:Pass123!@:Accountant"
)

for user_info in "${USERS[@]}"; do
    IFS=':' read -r username password role_name <<< "$user_info"
    
    echo "Testing $role_name ($username)..."
    
    # Login
    RESPONSE=$(curl -s -X POST "${API_URL}/auth/login" \
        -H "Content-Type: application/json" \
        -H "X-Test-Token: ${TEST_TOKEN}" \
        -d "{\"username\": \"$username\", \"password\": \"$password\"}")
    
    # Extract sidebar items count
    SIDEBAR_COUNT=$(echo "$RESPONSE" | jq '.data.sidebar_items | length' 2>/dev/null || echo "ERROR")
    DASHBOARD=$(echo "$RESPONSE" | jq '.data.dashboard.key' 2>/dev/null || echo "ERROR")
    ROLE=$(echo "$RESPONSE" | jq '.data.user.roles[0].name' 2>/dev/null || echo "ERROR")
    PERMISSIONS=$(echo "$RESPONSE" | jq '.data.user.permissions | length' 2>/dev/null || echo "ERROR")
    
    echo "  ✓ Role: $ROLE"
    echo "  ✓ Dashboard: $DASHBOARD"
    echo "  ✓ Sidebar Items: $SIDEBAR_COUNT"
    echo "  ✓ Permissions: $PERMISSIONS"
    echo ""
done

echo "Test completed!"
