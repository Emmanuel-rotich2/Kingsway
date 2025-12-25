#!/bin/bash

# Test login and sidebar items loading for System Administrator

API_URL="http://localhost/Kingsway/api"
TEST_TOKEN="devtest"

echo "=== Testing System Administrator Login ==="
echo ""

# Login with test_sysadmin credentials
RESPONSE=$(curl -s -X POST "${API_URL}/auth/login" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d '{
        "username": "test_sysadmin",
        "password": "Pass123!@"
    }')

echo "Login Response:"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"

# Extract sidebar items from response
echo ""
echo "=== Sidebar Items ==="
SIDEBAR_ITEMS=$(echo "$RESPONSE" | jq '.data.sidebar_items | length' 2>/dev/null)
echo "Total sidebar menu items: $SIDEBAR_ITEMS"

echo ""
echo "=== Menu Structure ==="
echo "$RESPONSE" | jq '.data.sidebar_items[] | {label, icon, url, subitems: (.subitems | length)}' 2>/dev/null || echo "Failed to parse"

echo ""
echo "=== Dashboard Info ==="
echo "$RESPONSE" | jq '.data.dashboard' 2>/dev/null || echo "Failed to parse"

echo ""
echo "=== User Info ==="
echo "$RESPONSE" | jq '.data.user | {id, username, email, roles}' 2>/dev/null || echo "Failed to parse"

echo ""
echo "Test completed!"
