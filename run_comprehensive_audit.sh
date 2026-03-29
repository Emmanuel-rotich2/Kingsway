#!/bin/bash
# COMPREHENSIVE SYSTEM AUDIT
# Tests all users and audits RBAC/workflow synchronization

PROJECT_DIR="/home/prof_angera/Projects/php_pages/Kingsway"
AUDIT_OUTPUT="$PROJECT_DIR/SYSTEM_AUDIT_RESULTS.txt"
API_RESPONSES="$PROJECT_DIR/API_RESPONSES_ALL_USERS.json"

echo "================================================================================"
echo "PHASE 1: FULL SYSTEM AUDIT - ALL USERS & RBAC SYNCHRONIZATION"
echo "================================================================================"
echo ""
echo "Start time: $(date)"
echo ""

# Delete old results
rm -f "$AUDIT_OUTPUT" "$API_RESPONSES"

# Function to test user login
test_user_login() {
    local username=$1
    local password="Pass123!@"

    echo "Testing user: $username..."

    response=$(curl -s -X POST http://localhost/Kingsway/api/auth/login \
        -H "Content-Type: application/json" \
        -d "{\"username\":\"$username\",\"password\":\"$password\"}")

    echo "$response"
}

# Function to format JSON output
extract_user_summary() {
    local username=$1
    local response=$2

    # Extract key information
    sidebar_count=$(echo "$response" | jq '.sidebar_items | length // 0')
    permission_count=$(echo "$response" | jq '.permissions | length // 0')
    token=$(echo "$response" | jq -r '.token // "NONE"')

    echo "User: $username | Sidebar Items: $sidebar_count | Permissions: $permission_count | Token: ${token:0:20}..."
}

echo "================================================================================"
echo "TESTING ALL ACTIVE USERS - API RESPONSES"
echo "================================================================================"
echo ""

# Test each user
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy -e "
SELECT u.username FROM users u WHERE u.status = 'active' ORDER BY u.id;
" | tail -n +2 | while read username; do
    echo "[$username]"
    response=$(test_user_login "$username")
    extract_user_summary "$username" "$response"

    # Save response
    echo "{\"user\":\"$username\",\"response\":$response}," >> "$API_RESPONSES"
    echo "$username|$(echo "$response" | jq '.sidebar_items | length // 0')|$(echo "$response" | jq '.permissions | length // 0')" >> "$AUDIT_OUTPUT"
    echo ""
done

echo ""
echo "================================================================================"
echo "DATABASE AUDIT - RBAC TABLE SYNCHRONIZATION"
echo "================================================================================"
echo ""

# Count table rows
AUDIT_QUERIES="
SELECT 'AUDIT: Table Row Counts' as check_type;

SELECT 'roles', COUNT(*) as count FROM roles;
SELECT 'users', COUNT(*) as count FROM users WHERE status = 'active';
SELECT 'permissions', COUNT(*) as count FROM permissions;
SELECT 'role_permissions', COUNT(*) as count FROM role_permissions;
SELECT 'user_permissions', COUNT(*) as count FROM user_permissions;
SELECT 'routes', COUNT(*) as count FROM routes;
SELECT 'route_permissions', COUNT(*) as count FROM route_permissions;
SELECT 'role_routes', COUNT(*) as count FROM role_routes;
SELECT 'sidebar_menu_items', COUNT(*) as count FROM sidebar_menu_items;
SELECT 'role_sidebar_menus', COUNT(*) as count FROM role_sidebar_menus;
SELECT 'dashboards', COUNT(*) as count FROM dashboards;
SELECT 'role_dashboards', COUNT(*) as count FROM role_dashboards;
SELECT 'workflow_definitions', COUNT(*) as count FROM workflow_definitions;
SELECT 'workflow_stages', COUNT(*) as count FROM workflow_stages;
SELECT 'workflow_instances', COUNT(*) as count FROM workflow_instances;

SELECT '' as blank;
SELECT 'AUDIT: Orphan/Invalid Records' as check_type;

-- Orphan role_permissions (permissions that don't exist)
SELECT 'Orphan role_permissions (permission_id not in permissions)', COUNT(*) as count
FROM role_permissions rp
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.id = rp.permission_id);

-- Orphan role_routes (routes that don't exist)
SELECT 'Orphan role_routes (route_id not in routes)', COUNT(*) as count
FROM role_routes rr
WHERE NOT EXISTS (SELECT 1 FROM routes r WHERE r.id = rr.route_id);

-- Sidebar items without routes
SELECT 'Sidebar items with null route_id', COUNT(*) as count
FROM sidebar_menu_items WHERE route_id IS NULL AND menu_type != 'dropdown';

-- Role sidebar menus without items
SELECT 'Orphan role_sidebar_menus (sidebar_menu_id not found)', COUNT(*) as count
FROM role_sidebar_menus rsm
WHERE NOT EXISTS (SELECT 1 FROM sidebar_menu_items smi WHERE smi.id = rsm.sidebar_menu_id);

-- Routes without any page permissions
SELECT 'Routes with no route_permissions', COUNT(*) as count
FROM routes r
WHERE NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id);

-- Workflow stages without responsible roles
SELECT 'Workflow stages with no responsible_role_ids', COUNT(*) as count
FROM workflow_stages WHERE responsible_role_ids IS NULL OR responsible_role_ids = '';

SELECT '' as blank;
SELECT 'AUDIT: Permission Distribution' as check_type;

-- Permissions by module
SELECT FORMAT('Permissions in module: %s', COALESCE(module, 'NO_MODULE')), COUNT(*) as count
FROM permissions
GROUP BY module
ORDER BY module;

SELECT '' as blank;
SELECT 'AUDIT: Role Coverage' as check_type;

-- Roles without any permissions
SELECT 'Roles with no role_permissions', COUNT(*) as count
FROM roles r
WHERE NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id);

-- Roles without any routes
SELECT 'Roles with no role_routes', COUNT(*) as count
FROM roles r
WHERE NOT EXISTS (SELECT 1 FROM role_routes rr WHERE rr.role_id = r.id);

-- Roles without any sidebar items
SELECT 'Roles with no sidebar assignments', COUNT(*) as count
FROM roles r
WHERE NOT EXISTS (SELECT 1 FROM role_sidebar_menus rsm WHERE rsm.role_id = r.id);

SELECT '' as blank;
SELECT 'AUDIT: Workflow Status' as check_type;

-- Workflows without stages
SELECT 'Workflows with no stages', COUNT(*) as count
FROM workflow_definitions wd
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages ws WHERE ws.workflow_id = wd.id);

-- Workflow instances without valid stage
SELECT 'Workflow instances with invalid current_stage_id', COUNT(*) as count
FROM workflow_instances wi
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages ws WHERE ws.id = wi.current_stage_id);
"

echo "$AUDIT_QUERIES" | /opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy >> "$AUDIT_OUTPUT"

echo "Results saved to:"
echo "  - Audit output: $AUDIT_OUTPUT"
echo "  - API responses: $API_RESPONSES"
echo ""
echo "End time: $(date)"
