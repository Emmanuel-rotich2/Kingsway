#!/bin/bash

# Complete end-to-end test
echo "=== END-TO-END LOGIN & DASHBOARD TEST ==="
echo ""

API_URL="http://localhost/Kingsway/api"
TEST_TOKEN="devtest"

# Step 1: Login
echo "Step 1: Logging in as test_sysadmin..."
RESPONSE=$(curl -s -X POST "${API_URL}/auth/login" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d '{"username":"test_sysadmin","password":"Pass123!@"}')

echo "Login Status: $(echo "$RESPONSE" | jq '.status')"
echo "Message: $(echo "$RESPONSE" | jq '.message')"

# Step 2: Check dashboard key
echo ""
echo "Step 2: Checking dashboard routing..."
DASHBOARD_KEY=$(echo "$RESPONSE" | jq -r '.data.dashboard.key')
echo "Dashboard Key: $DASHBOARD_KEY"
echo "Dashboard URL: $(echo "$RESPONSE" | jq -r '.data.dashboard.url')"

# Step 3: Check sidebar items
echo ""
echo "Step 3: Checking sidebar items..."
SIDEBAR_COUNT=$(echo "$RESPONSE" | jq '.data.sidebar_items | length')
echo "Sidebar Items Count: $SIDEBAR_COUNT"

if [ "$SIDEBAR_COUNT" -gt 0 ]; then
    echo "Sidebar Menu Structure:"
    echo "$RESPONSE" | jq '.data.sidebar_items[] | {label: .label, icon: .icon, url: .url, subitems: (.subitems | length // 0)}' | head -50
else
    echo "ERROR: No sidebar items returned!"
fi

# Step 4: Check user info
echo ""
echo "Step 4: User Information..."
echo "Username: $(echo "$RESPONSE" | jq -r '.data.user.username')"
echo "Role: $(echo "$RESPONSE" | jq -r '.data.user.roles[0].name')"
echo "Permissions Count: $(echo "$RESPONSE" | jq '.data.user.permissions | length')"

# Step 5: Test dashboard file exists
echo ""
echo "Step 5: Checking if dashboard file exists..."
DASHBOARD_FILE="/home/prof_angera/Projects/php_pages/Kingsway/components/dashboards/${DASHBOARD_KEY}.php"
if [ -f "$DASHBOARD_FILE" ]; then
    echo "✓ Dashboard file exists: $DASHBOARD_FILE"
else
    echo "✗ Dashboard file NOT found: $DASHBOARD_FILE"
fi

echo ""
echo "=== TEST COMPLETE ==="
