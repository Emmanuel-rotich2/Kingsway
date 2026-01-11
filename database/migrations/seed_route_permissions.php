<?php
/**
 * Seeder: Route Permissions
 * 
 * Seeds the route_permissions table by mapping routes to required permissions.
 * This creates the relationship between routes and the permissions needed to access them.
 * 
 * Run: php database/migrations/seed_route_permissions.php
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/database/Database.php';

use App\Database\Database;

$db = Database::getInstance()->getConnection();

echo "=== Seeding Route Permissions ===\n\n";

// First, let's check what permission codes exist that match our route_permissions patterns
$stmt = $db->query("SELECT id, code FROM permissions ORDER BY code");
$permissions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $permissions[$row['code']] = $row['id'];
}
echo "Found " . count($permissions) . " permissions in database\n";

// Get all routes
$stmt = $db->query("SELECT id, name FROM routes ORDER BY id");
$routes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $routes[$row['name']] = $row['id'];
}
echo "Found " . count($routes) . " routes in database\n\n";

// Route to permission mapping (from route_permissions.php)
// We'll map to the closest matching permission codes
$routePermissionMap = [
    // System/Admin - map to system permissions
    'system_administrator_dashboard' => ['system_view'],
    'system_health' => ['system_view'],
    'error_logs' => ['system_view'],
    'authentication_logs' => ['system_view'],
    'activity_audit_logs' => ['system_view'],
    'system_settings' => ['system_view'],
    'module_management' => ['system_view'],
    'maintenance_mode' => ['system_view'],
    'api_explorer' => ['system_view'],
    'job_queue_monitor' => ['system_view'],
    'cache_monitor' => ['system_view'],
    'db_health_monitor' => ['system_view'],
    'manage_users' => ['users_view'],
    'manage_roles' => ['users_view'],
    'manage_permissions' => ['users_view'],
    'delegated_permissions' => ['users_view'],

    // Director / Reports / Finance
    'director_owner_dashboard' => ['reports_view', 'finance_view'],
    'finance_reports' => ['finance_view'],
    'budget_overview' => ['finance_view'],
    'finance_approvals' => ['finance_view'],
    'financial_reports' => ['reports_view'],
    'enrollment_reports' => ['reports_view'],

    // Students & Academic
    'manage_students' => ['students_view'],
    'manage_students_admissions' => ['admission_view'],
    'mark_attendance' => ['attendance_view'],
    'view_attendance' => ['attendance_view'],
    'manage_academics' => ['academic_view'],
    'manage_classes' => ['academic_view'],
    'manage_timetable' => ['schedules_view'],
    'view_results' => ['academic_view'],
    'add_results' => ['academic_update'],
    'submit_results' => ['academic_update', 'academic_view'],
    'student_performance' => ['academic_view'],
    'manage_assessments' => ['academic_view'],
    'manage_lesson_plans' => ['academic_view'],
    'manage_subjects' => ['academic_view'],
    'myclasses' => ['academic_view'],

    // Staff
    'manage_staff' => ['staff_view'],
    'staff_attendance' => ['attendance_view'],
    'staff_performance' => ['staff_view'],

    // Finance / Payments / Payroll / Fees
    'manage_payments' => ['finance_view'],
    'payroll' => ['finance_view'],
    'manage_payrolls' => ['finance_view'],
    'manage_fees' => ['finance_view'],
    'manage_fee_structure' => ['finance_view'],
    'student_fees' => ['finance_view'],
    'manage_expenses' => ['finance_view'],
    'manage_finance' => ['finance_view'],

    // Inventory / Requisitions
    'manage_inventory' => ['inventory_view'],
    'manage_stock' => ['inventory_view'],
    'manage_requisitions' => ['inventory_view'],

    // Kitchen / Food
    'catering_manager_cook_lead_dashboard' => ['inventory_view'],
    'menu_planning' => ['inventory_view'],
    'food_store' => ['inventory_view'],

    // Boarding
    'matron_housemother_dashboard' => ['boarding_view'],
    'manage_boarding' => ['boarding_view'],

    // Activities & Talent
    'hod_talent_development_dashboard' => ['activities_view'],
    'manage_activities' => ['activities_view'],

    // Transport
    'driver_dashboard' => ['transport_view'],
    'my_routes' => ['transport_view'],
    'my_vehicle' => ['transport_view'],

    // Chaplain / Counseling
    'school_counselor_chaplain_dashboard' => ['chapel_view'],
    'chapel_services' => ['chapel_view'],
    'student_counseling' => ['chapel_view'],

    // Dashboards
    'class_teacher_dashboard' => ['academic_view'],
    'subject_teacher_dashboard' => ['academic_view'],
    'intern_student_teacher_dashboard' => ['academic_view'],
    'headteacher_dashboard' => ['academic_view'],
    'deputy_head_academic_dashboard' => ['academic_view'],
    'deputy_head_discipline_dashboard' => ['students_discipline_view'],
    'school_administrative_officer_dashboard' => ['academic_view', 'staff_view'],
    'school_accountant_dashboard' => ['finance_view'],
    'store_manager_dashboard' => ['inventory_view'],

    // Communications
    'manage_communications' => ['communications_view'],
    'manage_sms' => ['communications_view'],
    'manage_email' => ['communications_view'],
    'manage_announcements' => ['communications_view'],

    // Shared routes
    'home' => [],
    'me' => [],
];

// Find or create missing permission codes
$missingPermissions = [];
foreach ($routePermissionMap as $route => $perms) {
    foreach ($perms as $perm) {
        if (!isset($permissions[$perm]) && !in_array($perm, $missingPermissions)) {
            $missingPermissions[] = $perm;
        }
    }
}

if (!empty($missingPermissions)) {
    echo "Creating " . count($missingPermissions) . " missing permission codes:\n";
    $insertStmt = $db->prepare("INSERT INTO permissions (code, description, entity, action, created_at) VALUES (?, ?, ?, ?, NOW())");

    foreach ($missingPermissions as $perm) {
        // Parse permission code (e.g., 'academic_view' -> entity='academic', action='view')
        $parts = explode('_', $perm);
        $action = array_pop($parts);
        $entity = implode('_', $parts);
        $description = ucfirst(str_replace('_', ' ', $perm));

        try {
            $insertStmt->execute([$perm, $description, $entity, $action]);
            $permissions[$perm] = $db->lastInsertId();
            echo "  Created: $perm (ID: {$permissions[$perm]})\n";
        } catch (PDOException $e) {
            echo "  WARN: Could not create $perm: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

// Clear existing route_permissions
$db->exec("TRUNCATE TABLE route_permissions");
echo "Cleared route_permissions table\n\n";

// Insert route permissions
$insertStmt = $db->prepare(
    "INSERT INTO route_permissions (route_id, permission_id, access_type, is_required, created_at) 
     VALUES (?, ?, 'view', 1, NOW())"
);

$insertCount = 0;
$skipCount = 0;

foreach ($routePermissionMap as $routeName => $permCodes) {
    if (!isset($routes[$routeName])) {
        // echo "  SKIP: Route '$routeName' not found in database\n";
        $skipCount++;
        continue;
    }

    $routeId = $routes[$routeName];

    if (empty($permCodes)) {
        // Route with no permission requirements (public routes)
        continue;
    }

    foreach ($permCodes as $permCode) {
        if (!isset($permissions[$permCode])) {
            echo "  WARN: Permission '$permCode' not found for route '$routeName'\n";
            continue;
        }

        $permId = $permissions[$permCode];
        try {
            $insertStmt->execute([$routeId, $permId]);
            $insertCount++;
        } catch (PDOException $e) {
            echo "  ERROR: Could not insert ($routeName, $permCode): " . $e->getMessage() . "\n";
        }
    }
}

echo "\nInserted $insertCount route-permission mappings\n";
echo "Skipped $skipCount routes (not found in database)\n";

// Verify
$stmt = $db->query("SELECT COUNT(*) as cnt FROM route_permissions");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nTotal route_permissions records: {$result['cnt']}\n";

echo "\n=== Route Permissions Seeding Complete ===\n";
