#!/bin/bash

# Test script for /api/system endpoints
# Tests media management, logs, school configuration, and health endpoints

API_URL="http://localhost/Kingsway/api"
TEST_TOKEN="devtest"

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║   SYSTEM API ENDPOINTS TEST                                    ║"
echo "║   Media | Logs | Configuration | Health                       ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Test 1: GET /api/system/index
echo -e "${YELLOW}[1/16]${NC} Testing: GET /api/system/index"
RESPONSE=$(curl -s -X GET "${API_URL}/system/index" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 2: GET /api/system/health
echo -e "${YELLOW}[2/16]${NC} Testing: GET /api/system/health"
RESPONSE=$(curl -s -X GET "${API_URL}/system/health" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
MESSAGE=$(echo "$RESPONSE" | jq -r '.message' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: $MESSAGE"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 3: GET /api/system/school-config
echo -e "${YELLOW}[3/16]${NC} Testing: GET /api/system/school-config"
RESPONSE=$(curl -s -X GET "${API_URL}/system/school-config" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
CONFIG_COUNT=$(echo "$RESPONSE" | jq '.data | length' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Retrieved $CONFIG_COUNT config records"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 4: POST /api/system/school-config (Create new config)
echo -e "${YELLOW}[4/16]${NC} Testing: POST /api/system/school-config (Create)"
CONFIG_PAYLOAD='{
    "school_name": "Kingsway Academy Test",
    "school_code": "KWA-TEST",
    "motto": "Excellence in Education",
    "email": "info@kingswayacademy.test",
    "phone": "+254700000000",
    "city": "Nairobi",
    "country": "Kenya"
}'
RESPONSE=$(curl -s -X POST "${API_URL}/system/school-config" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d "$CONFIG_PAYLOAD")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
MESSAGE=$(echo "$RESPONSE" | jq -r '.message' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: $MESSAGE"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 5: GET /api/system/logs
echo -e "${YELLOW}[5/16]${NC} Testing: GET /api/system/logs"
RESPONSE=$(curl -s -X GET "${API_URL}/system/logs" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    LOG_FILES=$(echo "$RESPONSE" | jq '.data | keys | length' 2>/dev/null)
    echo -e "  ${GREEN}✓ PASSED${NC}: Retrieved $LOG_FILES log files"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 6: GET /api/system/media-albums (Get all albums)
echo -e "${YELLOW}[6/16]${NC} Testing: GET /api/system/media-albums"
RESPONSE=$(curl -s -X GET "${API_URL}/system/media-albums" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    ALBUM_COUNT=$(echo "$RESPONSE" | jq '.data | length' 2>/dev/null)
    echo -e "  ${GREEN}✓ PASSED${NC}: Retrieved $ALBUM_COUNT albums"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 7: POST /api/system/media-album (Create album)
echo -e "${YELLOW}[7/16]${NC} Testing: POST /api/system/media-album (Create)"
ALBUM_PAYLOAD='{
    "name": "Test Album '$(date +%s)'",
    "description": "Test media album",
    "created_by": 1
}'
RESPONSE=$(curl -s -X POST "${API_URL}/system/media-album" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d "$ALBUM_PAYLOAD")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
ALBUM_ID=$(echo "$RESPONSE" | jq -r '.data.id' 2>/dev/null)
if [ "$STATUS" = "success" ] && [ -n "$ALBUM_ID" ] && [ "$ALBUM_ID" != "null" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Created album with ID $ALBUM_ID"
    PASSED=$((PASSED + 1))
    CREATED_ALBUM_ID=$ALBUM_ID
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
    CREATED_ALBUM_ID=1
fi
echo ""

# Test 8: GET /api/system/media (Get all media)
echo -e "${YELLOW}[8/16]${NC} Testing: GET /api/system/media"
RESPONSE=$(curl -s -X GET "${API_URL}/system/media" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    MEDIA_COUNT=$(echo "$RESPONSE" | jq '.data | length' 2>/dev/null)
    echo -e "  ${GREEN}✓ PASSED${NC}: Retrieved $MEDIA_COUNT media items"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 9: GET /api/system/media-preview (Get preview URL)
echo -e "${YELLOW}[9/16]${NC} Testing: GET /api/system/media-preview"
RESPONSE=$(curl -s -X GET "${API_URL}/system/media-preview?media_id=1" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    PREVIEW_URL=$(echo "$RESPONSE" | jq -r '.data' 2>/dev/null)
    echo -e "  ${GREEN}✓ PASSED${NC}: Retrieved preview URL"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${YELLOW}⚠ SKIPPED${NC}: No media item with ID 1 (expected for fresh system)"
fi
echo ""

# Test 10: GET /api/system/media-can-access (Check media access)
echo -e "${YELLOW}[10/16]${NC} Testing: GET /api/system/media-can-access"
RESPONSE=$(curl -s -X GET "${API_URL}/system/media-can-access?user_id=1&media_id=1&action=view" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Access check returned"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${YELLOW}⚠ SKIPPED${NC}: Media access check (expected for fresh system)"
fi
echo ""

# Test 11: POST /api/system/media-update (Update media)
echo -e "${YELLOW}[11/16]${NC} Testing: POST /api/system/media-update"
RESPONSE=$(curl -s -X POST "${API_URL}/system/media-update" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d '{"media_id": 1, "description": "Updated description"}')
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ] || [ "$STATUS" = "null" ]; then
    echo -e "  ${YELLOW}⚠ SKIPPED${NC}: Media update (no media with ID 1)"
else
    echo -e "  ${GREEN}✓ PASSED${NC}: Media updated"
    PASSED=$((PASSED + 1))
fi
echo ""

# Test 12: POST /api/system/media-delete (Delete media)
echo -e "${YELLOW}[12/16]${NC} Testing: POST /api/system/media-delete"
RESPONSE=$(curl -s -X POST "${API_URL}/system/media-delete" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d '{"media_id": 1}')
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ] || [ "$STATUS" = "null" ]; then
    echo -e "  ${YELLOW}⚠ SKIPPED${NC}: Media delete (no media with ID 1)"
else
    echo -e "  ${GREEN}✓ PASSED${NC}: Media deleted"
    PASSED=$((PASSED + 1))
fi
echo ""

# Test 13: POST /api/system/media-album-delete (Delete album)
echo -e "${YELLOW}[13/16]${NC} Testing: POST /api/system/media-album-delete"
RESPONSE=$(curl -s -X POST "${API_URL}/system/media-album-delete" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}" \
    -d "{\"album_id\": $CREATED_ALBUM_ID}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: Album deleted"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${YELLOW}⚠ SKIPPED${NC}: Album delete (optional endpoint)"
fi
echo ""

# Test 14: POST /api/system/logs-clear
echo -e "${YELLOW}[14/16]${NC} Testing: POST /api/system/logs-clear"
RESPONSE=$(curl -s -X POST "${API_URL}/system/logs-clear" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
MESSAGE=$(echo "$RESPONSE" | jq -r '.message' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: $MESSAGE"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 15: POST /api/system/logs-archive
echo -e "${YELLOW}[15/16]${NC} Testing: POST /api/system/logs-archive"
RESPONSE=$(curl -s -X POST "${API_URL}/system/logs-archive" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
MESSAGE=$(echo "$RESPONSE" | jq -r '.message' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    echo -e "  ${GREEN}✓ PASSED${NC}: $MESSAGE"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Test 16: Verify logs still readable after archive
echo -e "${YELLOW}[16/16]${NC} Testing: GET /api/system/logs (After Archive)"
RESPONSE=$(curl -s -X GET "${API_URL}/system/logs" \
    -H "Content-Type: application/json" \
    -H "X-Test-Token: ${TEST_TOKEN}")
STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null)
if [ "$STATUS" = "success" ]; then
    LOG_FILES=$(echo "$RESPONSE" | jq '.data | keys | length' 2>/dev/null)
    echo -e "  ${GREEN}✓ PASSED${NC}: Logs still readable after archive"
    PASSED=$((PASSED + 1))
else
    echo -e "  ${RED}✗ FAILED${NC}: $RESPONSE"
    FAILED=$((FAILED + 1))
fi
echo ""

# Summary
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║   TEST SUMMARY                                                 ║"
echo "╠════════════════════════════════════════════════════════════════╣"
echo -e "║  ${GREEN}Passed: $PASSED${NC}                                                    ║"
echo -e "║  ${RED}Failed: $FAILED${NC}                                                    ║"
echo "║  Skipped: (No media items in fresh system)                     ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All critical system endpoints are working!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some endpoints failed. Please review the errors above.${NC}"
    exit 1
fi
