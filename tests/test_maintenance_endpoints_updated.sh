#!/bin/bash

# Test script for /api/maintenance endpoints
# Tests all maintenance API operations with equipment and vehicle maintenance

API_URL="http://localhost/Kingsway/api"
TEST_TOKEN="devtest"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║   MAINTENANCE API ENDPOINTS TEST                               ║"
echo "║   Equipment | Vehicle | Logs | Configuration | CRUD Operations║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Test 1: GET /api/maintenance/index
echo -e "${YELLOW}[1/10]${NC} Testing: GET /api/maintenance/index"
RESPONSE=$(curl -s -X GET "${API_URL}/maintenance/index" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
# Handle both double-encoded and normal JSON
STATUS=$(echo "$RESPONSE" | jq -r 'if type == "string" then . | fromjson | .status else .status end' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Maintenance API initialized"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: Response status: $STATUS"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 2: GET /api/maintenance/maintenance (Get all equipment maintenance)
echo -e "${YELLOW}[2/10]${NC} Testing: GET /api/maintenance/maintenance"
RESPONSE=$(curl -s -X GET "${API_URL}/maintenance/maintenance" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
echo "Response: $RESPONSE"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
RECORD_COUNT=$(echo "$RESPONSE" | jq '.data | length' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Retrieved $RECORD_COUNT equipment maintenance records"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 3: POST /api/maintenance/maintenance (Create new equipment maintenance record)
echo -e "${YELLOW}[3/10]${NC} Testing: POST /api/maintenance/maintenance (Create Equipment)"
MAINTENANCE_PAYLOAD='{
    "type": "equipment",
    "equipment_id": 1,
    "maintenance_type_id": 1,
    "status": "pending",
    "notes": "Test equipment maintenance record"
}'
RESPONSE=$(curl -s -X POST "${API_URL}/maintenance/maintenance" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d "$MAINTENANCE_PAYLOAD")
echo "Response: $RESPONSE"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
MAINT_ID=$(echo "$RESPONSE" | jq -r '.data.id // .id // empty' 2>/dev/null)
if [ "$STATUS" = "success" ] && [ -n "$MAINT_ID" ] && [ "$MAINT_ID" != "null" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Created equipment maintenance record ID: $MAINT_ID"
    PASSED=$((PASSED + 1))
    CREATED_MAINT_ID=$MAINT_ID
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
    CREATED_MAINT_ID=1
fi
echo ""

# Test 4: PUT /api/maintenance/maintenance (Update equipment maintenance record)
echo -e "${YELLOW}[4/10]${NC} Testing: PUT /api/maintenance/$CREATED_MAINT_ID (Update Equipment)"
UPDATE_PAYLOAD='{
    "status": "in_progress",
    "notes": "Updated maintenance notes"
}'
RESPONSE=$(curl -s -X PUT "${API_URL}/maintenance/maintenance/$CREATED_MAINT_ID" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d "$UPDATE_PAYLOAD")
echo "Response: $RESPONSE"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Updated equipment maintenance record"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 5: DELETE /api/maintenance/maintenance (Delete equipment maintenance record)
echo -e "${YELLOW}[5/10]${NC} Testing: DELETE /api/maintenance/$CREATED_MAINT_ID (Delete Equipment)"
RESPONSE=$(curl -s -X DELETE "${API_URL}/maintenance/maintenance/$CREATED_MAINT_ID" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
echo "Response: $RESPONSE"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Deleted equipment maintenance record"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 6: GET /api/maintenance/logs
echo -e "${YELLOW}[6/10]${NC} Testing: GET /api/maintenance/logs"
RESPONSE=$(curl -s -X GET "${API_URL}/maintenance/logs" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
echo "Response (truncated): $(echo "$RESPONSE" | jq -r '.status') - $(echo "$RESPONSE" | jq '.data | length' 2>/dev/null) records"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
LOG_COUNT=$(echo "$RESPONSE" | jq '.data | length' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Retrieved $LOG_COUNT maintenance log records"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 7: POST /api/maintenance/logs-clear
echo -e "${YELLOW}[7/10]${NC} Testing: POST /api/maintenance/logs-clear"
RESPONSE=$(curl -s -X POST "${API_URL}/maintenance/logs-clear" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d '{}')
echo "Response: $RESPONSE"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
MESSAGE=$(echo "$RESPONSE" | jq -r '.message' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: $MESSAGE"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 8: POST /api/maintenance/logs-archive
echo -e "${YELLOW}[8/10]${NC} Testing: POST /api/maintenance/logs-archive"
RESPONSE=$(curl -s -X POST "${API_URL}/maintenance/logs-archive" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d '{}')
echo "Response: $RESPONSE"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
MESSAGE=$(echo "$RESPONSE" | jq -r '.message' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: $MESSAGE"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 9: GET /api/maintenance/config
echo -e "${YELLOW}[9/10]${NC} Testing: GET /api/maintenance/config"
RESPONSE=$(curl -s -X GET "${API_URL}/maintenance/config" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
echo "Response (truncated): $(echo "$RESPONSE" | jq -r '.status') - $(echo "$RESPONSE" | jq '.data | length' 2>/dev/null) settings"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
CONFIG_COUNT=$(echo "$RESPONSE" | jq '.data | length' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Retrieved $CONFIG_COUNT school configuration settings"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 10: POST /api/maintenance/config (Update school config)
echo -e "${YELLOW}[10/10]${NC} Testing: POST /api/maintenance/config (Update Config)"
CONFIG_PAYLOAD='{
    "id": 1,
    "motto": "Maintaining excellence in operations"
}'
RESPONSE=$(curl -s -X POST "${API_URL}/maintenance/config" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d "$CONFIG_PAYLOAD")
echo "Response: $RESPONSE"
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
MESSAGE=$(echo "$RESPONSE" | jq -r '.message' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: $MESSAGE"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}"
    FAILED=$((FAILED + 1))
fi
echo ""

# Display summary
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║   TEST SUMMARY                                                 ║"
echo "╠════════════════════════════════════════════════════════════════╣"
echo "║  ${BLUE}Passed: $PASSED/10${NC}                                               ║"
echo "║  ${RED}Failed: $FAILED/10${NC}                                               ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests PASSED!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some endpoints failed. Please review the errors above.${NC}"
    exit 1
fi
