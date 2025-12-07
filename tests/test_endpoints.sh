#!/bin/bash

################################################################################
# User Endpoints Testing Script
# Tests all user API endpoints with real JSON payloads using curl
# 
# Usage: ./test_endpoints.sh [BASE_URL] [OUTPUT_FILE]
# Example: ./test_endpoints.sh http://localhost/kingsway/api/users results.txt
################################################################################

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="${1:-http://localhost/kingsway/api/users}"
OUTPUT_FILE="${2:-test_results.txt}"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
TEST_USER_ID=""
PASSED=0
FAILED=0
TOTAL=0

# Initialize output file
: > "$OUTPUT_FILE"

################################################################################
# HELPER FUNCTIONS
################################################################################

log_header() {
    local title="$1"
    echo -e "${CYAN}${BOLD}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}${BOLD}${title}${NC}"
    echo -e "${CYAN}${BOLD}═══════════════════════════════════════════════════════════${NC}"
    echo ""
    echo "═══════════════════════════════════════════════════════════" >> "$OUTPUT_FILE"
    echo "$title" >> "$OUTPUT_FILE"
    echo "═══════════════════════════════════════════════════════════" >> "$OUTPUT_FILE"
}

log_section() {
    local section="$1"
    echo -e "\n${CYAN}${BOLD}─── ${section} ───${NC}\n"
    echo "" >> "$OUTPUT_FILE"
    echo "─── $section ───" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
}

test_endpoint() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local payload="$4"
    local description="$5"

    TOTAL=$((TOTAL + 1))
    local url="${BASE_URL}${endpoint}"

    echo -e "${BOLD}Test $TOTAL: ${name}${NC}"
    echo "  Description: $description"
    echo "  Method: ${BOLD}${method}${NC}"
    echo "  URL: ${BOLD}${url}${NC}"

    # Log to file
    echo "" >> "$OUTPUT_FILE"
    echo "Test $TOTAL: $name" >> "$OUTPUT_FILE"
    echo "Description: $description" >> "$OUTPUT_FILE"
    echo "Method: $method" >> "$OUTPUT_FILE"
    echo "URL: $url" >> "$OUTPUT_FILE"

    # Display payload if provided
    if [ -n "$payload" ]; then
        echo "  Payload:"
        echo "$payload" | jq '.' 2>/dev/null || echo "$payload"
        echo "" >> "$OUTPUT_FILE"
        echo "Payload:" >> "$OUTPUT_FILE"
        echo "$payload" | jq '.' 2>/dev/null >> "$OUTPUT_FILE" || echo "$payload" >> "$OUTPUT_FILE"
    fi

    # Make the request
    echo "  Making request..."
    echo "" >> "$OUTPUT_FILE"
    echo "Response:" >> "$OUTPUT_FILE"

    local response
    local http_code
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -X GET "$url" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$url" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$payload")
    fi

    # Extract HTTP code and body
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')

    # Display formatted response
    echo "  HTTP Status: ${BOLD}$http_code${NC}"
    echo ""
    
    if [ -n "$body" ]; then
        echo "  Response Body:"
        echo "$body" | jq '.' 2>/dev/null || echo "    $body"
    else
        echo "  ${YELLOW}(No response body)${NC}"
    fi

    # Log response
    echo "HTTP Status: $http_code" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
    echo "Response Body:" >> "$OUTPUT_FILE"
    if [ -n "$body" ]; then
        echo "$body" | jq '.' 2>/dev/null >> "$OUTPUT_FILE" || echo "$body" >> "$OUTPUT_FILE"
    fi
    echo "" >> "$OUTPUT_FILE"

    # Check if successful
    if [[ "$http_code" =~ ^[2][0-9][0-9]$ ]]; then
        echo -e "  ${GREEN}✓ PASS${NC}\n"
        echo "Status: PASS" >> "$OUTPUT_FILE"
        PASSED=$((PASSED + 1))
        
        # Extract user ID if creating user
        if [[ "$endpoint" == "/user" ]] && [[ "$method" == "POST" ]]; then
            TEST_USER_ID=$(echo "$body" | jq -r '.data.id // empty' 2>/dev/null || echo "")
            if [ -n "$TEST_USER_ID" ]; then
                echo -e "  ${YELLOW}[CAPTURED] User ID: ${TEST_USER_ID}${NC}"
                echo "Captured User ID: $TEST_USER_ID" >> "$OUTPUT_FILE"
            fi
        fi
        
        return 0
    else
        echo -e "  ${RED}✗ FAIL${NC}\n"
        echo "Status: FAIL" >> "$OUTPUT_FILE"
        FAILED=$((FAILED + 1))
        return 1
    fi
}

print_summary() {
    local success_rate=0
    if [ $TOTAL -gt 0 ]; then
        success_rate=$((PASSED * 100 / TOTAL))
    fi

    echo -e "\n${CYAN}${BOLD}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}${BOLD}TEST SUMMARY${NC}"
    echo -e "${CYAN}${BOLD}═══════════════════════════════════════════════════════════${NC}"
    echo -e "Total Tests:    ${BOLD}$TOTAL${NC}"
    echo -e "Passed:         ${GREEN}${BOLD}$PASSED${NC}"
    echo -e "Failed:         ${RED}${BOLD}$FAILED${NC}"
    echo -e "Success Rate:   ${BOLD}${success_rate}%${NC}"
    echo ""

    # Log summary
    echo "" >> "$OUTPUT_FILE"
    echo "═══════════════════════════════════════════════════════════" >> "$OUTPUT_FILE"
    echo "TEST SUMMARY" >> "$OUTPUT_FILE"
    echo "═══════════════════════════════════════════════════════════" >> "$OUTPUT_FILE"
    echo "Total Tests: $TOTAL" >> "$OUTPUT_FILE"
    echo "Passed: $PASSED" >> "$OUTPUT_FILE"
    echo "Failed: $FAILED" >> "$OUTPUT_FILE"
    echo "Success Rate: ${success_rate}%" >> "$OUTPUT_FILE"
}

################################################################################
# MAIN TEST EXECUTION
################################################################################

log_header "USER ENDPOINTS - COMPREHENSIVE TEST SUITE"
echo "Base URL: $BASE_URL"
echo "Timestamp: $TIMESTAMP"
echo "Output File: $OUTPUT_FILE"
echo ""

echo "Base URL: $BASE_URL" >> "$OUTPUT_FILE"
echo "Timestamp: $TIMESTAMP" >> "$OUTPUT_FILE"
echo "Output File: $OUTPUT_FILE" >> "$OUTPUT_FILE"

# GROUP 1: Authentication
log_section "GROUP 1: Authentication & Login"

test_endpoint \
    "User Login" \
    "POST" \
    "/login" \
    '{
      "email": "admin@school.com",
      "password": "password123"
    }' \
    "Authenticate user with credentials"

# GROUP 2: User CRUD Operations
log_section "GROUP 2: User CRUD Operations"

test_endpoint \
    "Create User" \
    "POST" \
    "/user" \
    "{
      \"name\": \"Test User $(date +%s)\",
      \"email\": \"testuser$(date +%s)@school.com\",
      \"password\": \"password123\",
      \"phone\": \"0712345678\",
      \"status\": \"active\"
    }" \
    "Create a new user with complete details"

if [ -n "$TEST_USER_ID" ]; then
    test_endpoint \
        "Get User by ID" \
        "GET" \
        "/user/$TEST_USER_ID" \
        "" \
        "Retrieve specific user details"

    test_endpoint \
        "Update User" \
        "PUT" \
        "/user/$TEST_USER_ID" \
        '{
          "name": "Updated Test User",
          "phone": "0787654321",
          "status": "active"
        }' \
        "Update user information"

    test_endpoint \
        "Get User Profile" \
        "GET" \
        "/profile-get/$TEST_USER_ID" \
        "" \
        "Retrieve user profile information"
fi

test_endpoint \
    "List All Users" \
    "GET" \
    "/user" \
    "" \
    "List all users in the system"

# GROUP 3: Roles Management
log_section "GROUP 3: Roles Management"

test_endpoint \
    "Get All Roles" \
    "GET" \
    "/roles-get" \
    "" \
    "Retrieve all available roles"

if [ -n "$TEST_USER_ID" ]; then
    test_endpoint \
        "Assign Role to User" \
        "POST" \
        "/role-assign/$TEST_USER_ID" \
        '{
          "role_id": 1,
          "role_name": "Headteacher"
        }' \
        "Assign Headteacher role to user"

    test_endpoint \
        "Get User Main Role" \
        "GET" \
        "/role-main/$TEST_USER_ID" \
        "" \
        "Get user primary role"

    test_endpoint \
        "Get User Extra Roles" \
        "GET" \
        "/role-extra/$TEST_USER_ID" \
        "" \
        "Get user additional roles"
fi

test_endpoint \
    "Get Users with Specific Role" \
    "GET" \
    "/with-role/Headteacher" \
    "" \
    "Get all users with Headteacher role"

test_endpoint \
    "Get Users with Multiple Roles" \
    "GET" \
    "/with-multiple-roles" \
    "" \
    "Get users assigned to multiple roles"

# GROUP 4: Permissions - Query Operations
log_section "GROUP 4: Permissions - Query Operations"

test_endpoint \
    "Get All Permissions" \
    "GET" \
    "/permissions-get" \
    "" \
    "Retrieve all available permissions in system"

if [ -n "$TEST_USER_ID" ]; then
    test_endpoint \
        "Get User Effective Permissions" \
        "GET" \
        "/permissions-effective/$TEST_USER_ID" \
        "" \
        "Get all effective permissions (from roles + direct)"

    test_endpoint \
        "Get User Direct Permissions" \
        "GET" \
        "/permissions-direct/$TEST_USER_ID" \
        "" \
        "Get permissions directly assigned to user"

    test_endpoint \
        "Get User Denied Permissions" \
        "GET" \
        "/permissions-denied/$TEST_USER_ID" \
        "" \
        "Get explicitly denied permissions"

    test_endpoint \
        "Get Permissions by Entity" \
        "GET" \
        "/permissions-by-entity/$TEST_USER_ID" \
        "" \
        "Get permissions grouped by entity/module"

    test_endpoint \
        "Get Permissions Summary" \
        "GET" \
        "/permissions-summary/$TEST_USER_ID" \
        "" \
        "Get summary of user permissions"

    test_endpoint \
        "Check Single Permission" \
        "POST" \
        "/permissions-check/$TEST_USER_ID" \
        '{
          "permission_code": "manage_students_view"
        }' \
        "Check if user has specific permission"

    test_endpoint \
        "Check Multiple Permissions" \
        "POST" \
        "/permissions-check-multiple/$TEST_USER_ID" \
        '{
          "permission_codes": ["manage_students_view", "manage_staff_view"]
        }' \
        "Check multiple permissions at once"
fi

test_endpoint \
    "Get Users with Specific Permission" \
    "GET" \
    "/with-permission/manage_students_view" \
    "" \
    "Get all users with manage_students_view permission"

# GROUP 5: Permissions - Assignment Operations
log_section "GROUP 5: Permissions - Assignment Operations"

if [ -n "$TEST_USER_ID" ]; then
    test_endpoint \
        "Assign Permission to User" \
        "POST" \
        "/permission-assign/$TEST_USER_ID" \
        '{
          "permission_code": "view_financials",
          "permission_type": "grant"
        }' \
        "Directly assign permission to user"

    test_endpoint \
        "Update User Permissions" \
        "PUT" \
        "/permissions-update/$TEST_USER_ID" \
        '{
          "permissions": [
            {"permission_id": 1, "permission_type": "grant"},
            {"permission_id": 2, "permission_type": "deny"}
          ]
        }' \
        "Update multiple permissions for user"
fi

# GROUP 6: Bulk Operations
log_section "GROUP 6: Bulk Operations"

if [ -n "$TEST_USER_ID" ]; then
    test_endpoint \
        "Bulk Assign Roles to User" \
        "POST" \
        "/roles-bulk-assign-to-user" \
        "{
          \"user_id\": $TEST_USER_ID,
          \"role_ids\": [1, 2]
        }" \
        "Assign multiple roles to user at once"

    test_endpoint \
        "Bulk Revoke Roles from User" \
        "DELETE" \
        "/roles-bulk-revoke-from-user" \
        "{
          \"user_id\": $TEST_USER_ID,
          \"role_ids\": [2]
        }" \
        "Revoke multiple roles from user"

    test_endpoint \
        "Bulk Assign Permissions to User" \
        "POST" \
        "/permissions-bulk-assign-to-user" \
        "{
          \"user_id\": $TEST_USER_ID,
          \"permissions\": [
            {\"permission_code\": \"manage_students_view\", \"permission_type\": \"grant\"},
            {\"permission_code\": \"manage_staff_view\", \"permission_type\": \"grant\"}
          ]
        }" \
        "Bulk assign multiple permissions to user"

    test_endpoint \
        "Bulk Revoke Permissions from User" \
        "DELETE" \
        "/permissions-bulk-revoke-from-user" \
        "{
          \"user_id\": $TEST_USER_ID,
          \"permissions\": [\"manage_students_view\"]
        }" \
        "Bulk revoke permissions from user"
fi

# GROUP 7: Special Queries
log_section "GROUP 7: Special Queries"

test_endpoint \
    "Get Users with Temporary Permissions" \
    "GET" \
    "/with-temporary-permissions" \
    "" \
    "Get users with temporary/delegated permissions"

test_endpoint \
    "Get Sidebar Items" \
    "GET" \
    "/sidebar-items" \
    "" \
    "Get sidebar navigation items for user interface"

# GROUP 8: Password Operations
log_section "GROUP 8: Password Operations"

if [ -n "$TEST_USER_ID" ]; then
    test_endpoint \
        "Change User Password" \
        "PUT" \
        "/password-change/$TEST_USER_ID" \
        '{
          "current_password": "password123",
          "new_password": "newpassword123",
          "confirm_password": "newpassword123"
        }' \
        "Change user password"
fi

# GROUP 9: Cleanup
log_section "GROUP 9: Cleanup Operations"

if [ -n "$TEST_USER_ID" ]; then
    test_endpoint \
        "Delete Test User" \
        "DELETE" \
        "/user/$TEST_USER_ID" \
        "" \
        "Delete the test user created during testing"
fi

# Print and log summary
print_summary

echo -e "\n${BOLD}Results saved to: ${YELLOW}$OUTPUT_FILE${NC}\n"

exit 0
