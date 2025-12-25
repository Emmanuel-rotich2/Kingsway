#!/bin/bash

################################################################################
# User Cleanup and Test User Creation Script
# Clears all users/roles/permissions and creates fresh test users for each role
################################################################################

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Configuration
API_BASE_URL="http://localhost/Kingsway/api/users"
TEST_TOKEN="devtest"
DB_USER="root"
DB_PASS="admin123"
DB_NAME="KingsWayAcademy"
MYSQL_CMD="/opt/lampp/bin/mysql -u $DB_USER -p$DB_PASS -N -e"

# Counters
TOTAL_CREATED=0
FAILED_CREATED=0

################################################################################
# Helper Functions
################################################################################

print_header() {
    echo ""
    echo "╔══════════════════════════════════════════════════════════════════════════╗"
    echo "║           USER CLEANUP & TEST USER CREATION SCRIPT                      ║"
    echo "╚══════════════════════════════════════════════════════════════════════════╝"
    echo ""
}

section() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "  ${BOLD}$1${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

info() {
    echo -e "${CYAN}ℹ ${NC}$1"
}

success() {
    echo -e "${GREEN}✓ ${NC}$1"
}

error() {
    echo -e "${RED}✗ ${NC}$1"
}

warning() {
    echo -e "${YELLOW}⚠ ${NC}$1"
}

################################################################################
# STEP 1: Clear Database
################################################################################

section "STEP 1: CLEARING DATABASE"

info "Disabling foreign key checks..."
$MYSQL_CMD "SET FOREIGN_KEY_CHECKS=0;" $DB_NAME 2>/dev/null

info "Clearing user_permissions table..."
$MYSQL_CMD "DELETE FROM user_permissions;" $DB_NAME 2>/dev/null
if [ $? -eq 0 ]; then
    success "user_permissions table cleared"
else
    warning "Could not clear user_permissions table"
fi

info "Clearing user_roles table..."
$MYSQL_CMD "DELETE FROM user_roles;" $DB_NAME 2>/dev/null
if [ $? -eq 0 ]; then
    success "user_roles table cleared"
else
    warning "Could not clear user_roles table"
fi

info "Clearing users table..."
$MYSQL_CMD "DELETE FROM users;" $DB_NAME 2>/dev/null
if [ $? -eq 0 ]; then
    success "users table cleared"
else
    warning "Could not clear users table"
fi

info "Clearing staff table (for non-admin users)..."
$MYSQL_CMD "DELETE FROM staff;" $DB_NAME 2>/dev/null
if [ $? -eq 0 ]; then
    success "staff table cleared"
else
    warning "Could not clear staff table"
fi

info "Re-enabling foreign key checks..."
$MYSQL_CMD "SET FOREIGN_KEY_CHECKS=1;" $DB_NAME 2>/dev/null

################################################################################
# STEP 2: Fetch Available Roles
################################################################################

section "STEP 2: FETCHING AVAILABLE ROLES"

ROLES=$($MYSQL_CMD "SELECT CONCAT(id, '|', name) FROM roles ORDER BY id;" $DB_NAME)

if [ -z "$ROLES" ]; then
    error "No roles found in database!"
    exit 1
fi

info "Found available roles:"
echo "$ROLES" | while IFS='|' read ROLE_ID ROLE_NAME; do
    echo "   - [$ROLE_ID] $ROLE_NAME"
done

################################################################################
# STEP 3: Create Test Users
################################################################################

section "STEP 3: CREATING TEST USERS VIA API"

info "Using API endpoint: $API_BASE_URL"
info "Authentication token: $TEST_TOKEN"
echo ""

echo "$ROLES" | while IFS='|' read ROLE_ID ROLE_NAME; do
    # Convert role name to test user format (snake_case to lowercase with underscores)
    TEST_USERNAME="test_$(echo $ROLE_NAME | sed 's/ /_/g' | tr '[:upper:]' '[:lower:]')"
    TEST_EMAIL="${TEST_USERNAME}@kingsway.local"
    
    # Create JSON payload
    PAYLOAD=$(cat <<EOF
{
    "first_name": "Test",
    "last_name": "$(echo $ROLE_NAME | sed 's/_/ /g')",
    "email": "$TEST_EMAIL",
    "username": "$TEST_USERNAME",
    "password": "Test@123456",
    "phone": "0700000000",
    "role_ids": [$ROLE_ID],
    "status": "active"
}
EOF
)
    
    echo -e "${CYAN}Creating user: ${BOLD}$TEST_USERNAME${NC} (Role: ${ROLE_NAME}, ID: ${ROLE_ID})"
    echo "   Payload: $PAYLOAD"
    
    # Send API request
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -H "X-Test-Token: $TEST_TOKEN" \
        -d "$PAYLOAD" \
        "$API_BASE_URL")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)
    
    if [ "$HTTP_CODE" == "200" ] || [ "$HTTP_CODE" == "201" ]; then
        success "Created $TEST_USERNAME (HTTP $HTTP_CODE)"
        TOTAL_CREATED=$((TOTAL_CREATED + 1))
        echo "   Response: $BODY" | head -n 5
    else
        error "Failed to create $TEST_USERNAME (HTTP $HTTP_CODE)"
        FAILED_CREATED=$((FAILED_CREATED + 1))
        echo "   Response: $BODY"
    fi
    echo ""
done

################################################################################
# STEP 4: Summary
################################################################################

section "SUMMARY"

echo ""
echo "Database Cleanup:"
echo "  ✓ users table - CLEARED"
echo "  ✓ user_roles table - CLEARED"
echo "  ✓ user_permissions table - CLEARED"
echo "  ✓ staff table - CLEARED"
echo ""
echo "Test User Creation:"
echo "  Total Created:  $TOTAL_CREATED"
echo "  Failed:         $FAILED_CREATED"
echo ""

if [ $FAILED_CREATED -eq 0 ]; then
    success "All test users created successfully!"
    echo ""
    info "Next steps:"
    echo "  1. Test login with each user: test_director, test_classteacher, etc."
    echo "  2. Verify roles and permissions appear on login"
    echo "  3. Confirm sidebar loads based on roles"
else
    warning "$FAILED_CREATED user(s) failed to create. Check the API responses above."
fi

echo ""
