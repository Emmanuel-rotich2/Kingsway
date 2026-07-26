<?php

declare(strict_types=1);

/**
 * Static verifier for the canonical Kingsway role-dashboard architecture.
 *
 * This script deliberately verifies composition through existing domain APIs
 * and rejects dashboard-specific backend services or operational *Full
 * endpoints. It does not connect to MySQL.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This verifier may only be run from the command line.\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

require_once $root . '/config/DashboardRouter.php';

use App\Config\DashboardRouter;

$expected = [
    2 => 'system_administrator_dashboard',
    3 => 'director_owner_dashboard',
    4 => 'school_administrative_officer_dashboard',
    5 => 'headteacher_dashboard',
    6 => 'deputy_head_academic_dashboard',
    7 => 'class_teacher_dashboard',
    8 => 'subject_teacher_dashboard',
    9 => 'intern_student_teacher_dashboard',
    10 => 'school_accountant_dashboard',
    14 => 'store_manager_dashboard',
    16 => 'catering_manager_cook_lead_dashboard',
    18 => 'matron_housemother_dashboard',
    21 => 'hod_talent_development_dashboard',
    23 => 'driver_dashboard',
    24 => 'school_counselor_chaplain_dashboard',
    32 => 'support_staff_dashboard',
    33 => 'support_staff_dashboard',
    34 => 'support_staff_dashboard',
    63 => 'deputy_head_discipline_dashboard',
    64 => 'support_staff_dashboard',
];

$approvedFrozen = [
    'system_administrator_dashboard',
    'director_owner_dashboard',
    'school_administrative_officer_dashboard',
    'headteacher_dashboard',
];

$rebuilt = [
    'deputy_head_academic_dashboard',
    'deputy_head_discipline_dashboard',
    'class_teacher_dashboard',
    'subject_teacher_dashboard',
    'intern_student_teacher_dashboard',
    'school_accountant_dashboard',
    'store_manager_dashboard',
    'catering_manager_cook_lead_dashboard',
    'matron_housemother_dashboard',
    'hod_talent_development_dashboard',
    'driver_dashboard',
    'school_counselor_chaplain_dashboard',
    'support_staff_dashboard',
];

$invalidDashboardServices = [
    'api/services/AccountantDashboardService.php',
    'api/services/BoardingMasterDashboardService.php',
    'api/services/CateringManagerDashboardService.php',
    'api/services/ChaplainDashboardService.php',
    'api/services/DashboardDataService.php',
    'api/services/DriverDashboardService.php',
    'api/services/InventoryManagerDashboardService.php',
    'api/services/SupportStaffDashboardService.php',
    'api/services/TalentDevelopmentDashboardService.php',
    'api/modules/staff/StaffSelfServiceManager.php',
];

$invalidOperationalFullMethods = [
    'getAccountantFull',
    'getInventoryManagerFull',
    'getCateringManagerFull',
    'getBoardingMasterFull',
    'getTalentDevelopmentFull',
    'getDriverFull',
    'getChaplainFull',
    'getSupportStaffFull',
];

$redundantFiles = [
    'components/dashboards/accountant_accounts_cash.php',
    'components/dashboards/accountant_assets.php',
    'components/dashboards/accountant_controls.php',
    'components/dashboards/accountant_mpesa.php',
    'components/dashboards/accountant_vendors.php',
    'js/dashboards/accountant_accounts_cash_dashboard.js',
    'js/dashboards/accountant_assets_dashboard.js',
    'js/dashboards/accountant_controls_dashboard.js',
    'js/dashboards/accountant_mpesa_dashboard.js',
    'js/dashboards/accountant_vendors_dashboard.js',
    'components/dashboards/teacher_dashboard.php',
    'js/dashboards/teacher_dashboard.js',
    'pages/dashboard.php',
    'js/dashboards/dashboard_router.js',
    'js/dashboards/director_dashboard.js',
    'pages/system_administrator_dashboard.php',
];

$errors = [];
$passes = [];

$assert = static function (bool $condition, string $success, string $failure) use (&$errors, &$passes): void {
    if ($condition) {
        $passes[] = $success;
    } else {
        $errors[] = $failure;
    }
};

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$hasMethod = static function (string $content, string $method): bool {
    return preg_match('/\bfunction\s+' . preg_quote($method, '/') . '\s*\(/', $content) === 1;
};

$hasJsMethod = static function (string $content, string $method): bool {
    return preg_match('/\b' . preg_quote($method, '/') . '\s*:\s*async\b/', $content) === 1;
};

$actual = DashboardRouter::getRoleDashboards();
$assert($actual === $expected, 'Fallback role mapping matches the approved 20-role registry.', 'DashboardRouter role mapping differs from the approved registry.');
$assert(count($actual) === 20, 'Exactly 20 production roles are mapped.', 'Expected exactly 20 production role mappings.');
$assert(count(array_unique($actual)) === 17, 'Exactly 17 canonical dashboard components are used.', 'Expected exactly 17 unique dashboard components.');
$assert(DashboardRouter::getDefaultDashboard() === 'dashboard_access_denied', 'Unknown roles use the access-denied dashboard.', 'Unknown-role fallback is not dashboard_access_denied.');

foreach (array_unique($expected) as $key) {
    $phpRelative = 'components/dashboards/' . $key . '.php';
    $jsRelative = 'js/dashboards/' . $key . '.js';
    $php = $read($phpRelative);

    $assert($php !== '', "Component exists: {$key}.php", "Missing dashboard component: {$key}.php");
    $assert($read($jsRelative) !== '', "Controller exists: {$key}.js", "Missing dashboard controller: {$key}.js");
    $loadsDirectly = strpos($php, '/js/dashboards/' . $key . '.js') !== false;
    $loadsThroughShell = preg_match(
        "/['\"]controller_file['\"]\s*=>\s*['\"]"
            . preg_quote($key . '.js', '/')
            . "['\"]/",
        $php
    ) === 1;
    $assert(
        $loadsDirectly || $loadsThroughShell,
        "Component loads its matching controller: {$key}",
        "Component does not load matching controller: {$key}"
    );
}

$assert(is_file($root . '/components/dashboards/dashboard_access_denied.php'), 'Access-denied dashboard component exists.', 'Missing dashboard_access_denied.php.');
$assert(is_file($root . '/components/dashboards/partials/role_dashboard_shell.php'), 'Shared role dashboard shell exists.', 'Missing shared role_dashboard_shell.php.');
$assert(is_file($root . '/js/dashboards/dashboard_base_controller.js'), 'Shared presentation-only dashboard controller factory exists.', 'Missing dashboard_base_controller.js.');

foreach ($approvedFrozen as $key) {
    $assert(
        !in_array($key, $rebuilt, true),
        "Approved dashboard is excluded from UI rebuild checks: {$key}",
        "Approved dashboard was incorrectly included in the rebuild list: {$key}"
    );
}

foreach ($rebuilt as $key) {
    $php = $read('components/dashboards/' . $key . '.php');
    $js = $read('js/dashboards/' . $key . '.js');

    $assert(stripos($php, '<style') === false, "No inline style block: {$key}.php", "Inline style block found in {$key}.php");
    $assert(stripos($php, '<script>') === false, "No loose inline script: {$key}.php", "Loose inline script found in {$key}.php");

    foreach (['fetch(', 'callAPI(', 'API.callAPI(', 'checkPermission: false'] as $forbidden) {
        $assert(strpos($js, $forbidden) === false, "No request bypass in {$key}.js: {$forbidden}", "Forbidden request bypass in {$key}.js: {$forbidden}");
    }

    $assert(strpos($js, 'Controller') !== false, "Named controller present: {$key}.js", "Named controller missing from {$key}.js");
    $assert(strpos($js, 'window.API.') !== false, "Canonical window.API used: {$key}.js", "Canonical window.API call missing from {$key}.js");
}

foreach ($invalidDashboardServices as $relative) {
    $assert(!file_exists($root . '/' . $relative), "Invalid page-specific backend file is absent: {$relative}", "Invalid page-specific backend file still exists: {$relative}");
}

foreach ($redundantFiles as $relative) {
    $assert(!file_exists($root . '/' . $relative), "Redundant file removed: {$relative}", "Redundant file still exists: {$relative}");
}

$apiJs = $read('js/api.js');
$dashboardController = $read('api/controllers/DashboardController.php');

$dashboardContracts = [
    'getDeputyAcademicFull' => 'getDeputyAcademicFull',
    'getDeputyDisciplineFull' => 'getDeputyDisciplineFull',
    'getClassTeacherFull' => 'getClassTeacherFull',
    'getSubjectTeacherFull' => 'getSubjectTeacherFull',
    'getInternTeacherFull' => 'getInternTeacherFull',
    'getAccountantFinancial' => 'getAccountantFinancial',
    'getAccountantPayments' => 'getAccountantPayments',
];

foreach ($dashboardContracts as $jsMethod => $controllerMethod) {
    $assert($hasJsMethod($apiJs, $jsMethod), "api.js exposes API.dashboard.{$jsMethod}().", "api.js is missing dashboard method {$jsMethod}().");
    $assert($hasMethod($dashboardController, $controllerMethod), "DashboardController exposes {$controllerMethod}().", "DashboardController is missing {$controllerMethod}().");
}

$domainContracts = [
    ['getDashboard', 'api/controllers/InventoryController.php', 'getDashboard', 'api/modules/inventory/InventoryAPI.php', 'getDashboard'],
    ['getStats', 'api/controllers/CateringController.php', 'getStats', 'api/modules/reports/MealReportManager.php', 'getStats'],
    ['getMenu', 'api/controllers/CateringController.php', 'getMenu', 'api/modules/reports/MealReportManager.php', 'getMenu'],
    ['getFoodStock', 'api/controllers/CateringController.php', 'getFoodStock', 'api/modules/reports/MealReportManager.php', 'getFoodStock'],
    ['getStats', 'api/controllers/BoardingController.php', 'getStats', null, null],
    ['getOccupancy', 'api/controllers/BoardingController.php', 'getOccupancy', null, null],
    ['getRollCalls', 'api/controllers/BoardingController.php', 'getRollCall', null, null],
    ['getExeats', 'api/controllers/BoardingController.php', 'getExeats', null, null],
    ['getSummary', 'api/controllers/ActivitiesController.php', 'getStatisticsGet', 'api/modules/activities/ActivitiesAPI.php', 'getActivityStatistics'],
    ['listSchedules', 'api/controllers/ActivitiesController.php', 'getSchedulesList', 'api/modules/activities/ActivitiesAPI.php', 'listSchedules'],
    ['getMyRoute', 'api/controllers/TransportController.php', 'getMyRoute', 'api/modules/transport/TransportAPI.php', 'getMyRoute'],
    ['getMyVehicle', 'api/controllers/TransportController.php', 'getMyVehicle', 'api/modules/transport/TransportAPI.php', 'getMyVehicle'],
    ['getRouteManifest', 'api/controllers/TransportController.php', 'getRouteManifest', 'api/modules/transport/TransportAPI.php', 'getRouteManifest'],
    ['getSummary', 'api/controllers/CounselingController.php', 'getSummary', 'api/modules/counseling/CounselingAPI.php', 'getSummary'],
    ['getDashboardSummary', 'api/controllers/MaintenanceController.php', 'getDashboardSummary', 'api/modules/maintenance/MaintenanceAPI.php', 'getDashboardSummary'],
];

foreach ($domainContracts as [$jsMethod, $controllerFile, $controllerMethod, $moduleFile, $moduleMethod]) {
    $assert($hasJsMethod($apiJs, $jsMethod), "api.js contains canonical domain method {$jsMethod}().", "api.js is missing canonical domain method {$jsMethod}().");
    $assert($hasMethod($read($controllerFile), $controllerMethod), "{$controllerFile} exposes {$controllerMethod}().", "{$controllerFile} is missing {$controllerMethod}().");
    if ($moduleFile !== null && $moduleMethod !== null) {
        $assert($hasMethod($read($moduleFile), $moduleMethod), "{$moduleFile} owns {$moduleMethod}().", "{$moduleFile} is missing {$moduleMethod}().");
    }
}

$staffController = $read('api/controllers/StaffController.php');
$staffApi = $read('api/modules/staff/StaffAPI.php');
$staffContracts = [
    ['getAccessContext', 'getAccessContext', null],
    ['getProfile', 'getProfileGet', 'getProfile'],
    ['getAttendance', 'getAttendanceGet', 'getAttendance'],
    ['getPayrollHistory', 'getPayrollHistory', 'getPayrollHistory'],
    ['getLeaveTypes', 'getLeaveTypes', null],
    ['getLeaveBalance', 'getLeaveBalance', null],
    ['getLeaveRequests', 'getLeaveRequests', null],
    ['createLeaveRequest', 'postLeaveRequests', null],
    ['downloadPayslip', 'getPayrollDownloadPayslip', null],
    ['downloadP9', 'getPayrollDownloadP9', null],
    ['getInternalOpportunities', 'getInternalOpportunities', 'listInternalOpportunities'],
    ['applyForInternalOpportunity', 'postInternalOpportunitiesApply', 'applyForInternalOpportunity'],
    ['getIncidentReports', 'getIncidents', 'listIncidentReports'],
    ['createIncidentReport', 'postIncidents', 'createIncidentReport'],
];

foreach ($staffContracts as [$jsMethod, $controllerMethod, $apiMethod]) {
    $assert($hasJsMethod($apiJs, $jsMethod), "api.js contains API.staff.{$jsMethod}().", "api.js is missing API.staff.{$jsMethod}().");
    $assert($hasMethod($staffController, $controllerMethod), "StaffController exposes {$controllerMethod}().", "StaffController is missing {$controllerMethod}().");
    if ($apiMethod !== null) {
        $assert($hasMethod($staffApi, $apiMethod), "StaffAPI owns {$apiMethod}().", "StaffAPI is missing {$apiMethod}().");
    }
}

foreach ($invalidOperationalFullMethods as $method) {
    $assert(strpos($apiJs, $method) === false, "Invalid API.dashboard.{$method}() is absent.", "Invalid API.dashboard.{$method}() still exists.");
    $assert(strpos($dashboardController, $method) === false, "Invalid DashboardController::{$method}() is absent.", "Invalid DashboardController::{$method}() still exists.");
}

$operationalControllers = [
    'school_accountant_dashboard' => ['getAccountantFinancial', 'getAccountantPayments'],
    'store_manager_dashboard' => ['window.API.inventory.getDashboard'],
    'catering_manager_cook_lead_dashboard' => ['window.API.catering.getStats', 'window.API.catering.getMenu', 'window.API.catering.getFoodStock'],
    'matron_housemother_dashboard' => ['window.API.boarding.getStats', 'window.API.boarding.getOccupancy', 'window.API.boarding.getRollCalls', 'window.API.boarding.getExeats'],
    'hod_talent_development_dashboard' => ['window.API.activities.getSummary', 'window.API.activities.list', 'window.API.activities.listSchedules'],
    'driver_dashboard' => ['window.API.transport.getMyRoute', 'window.API.transport.getMyVehicle', 'window.API.transport.getRouteManifest'],
    'school_counselor_chaplain_dashboard' => ['window.API.counseling.getSummary'],
    'support_staff_dashboard' => ['window.API.staff.getAccessContext', 'window.API.staff.getProfile', 'window.API.staff.getLeaveBalance'],
];

foreach ($operationalControllers as $key => $tokens) {
    $js = $read('js/dashboards/' . $key . '.js');
    foreach ($tokens as $token) {
        $assert(strpos($js, $token) !== false, "{$key}.js composes {$token}.", "{$key}.js is missing canonical composition call {$token}.");
    }
}

$migration = $read('database/migrations/2026_07_26_role_dashboard_architecture.sql');
$assert($migration !== '', 'Dashboard architecture migration exists.', 'Dashboard architecture migration is missing.');
$assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS `staff_incident_reports`') !== false, 'New School Domain incident capability is explicitly migrated.', 'staff_incident_reports migration is missing.');
$assert(strpos($migration, "'applicant_type'") !== false && strpos($migration, "'staff_id'") !== false, 'Existing job_applications workflow is extended for internal staff.', 'Internal applicant extension is missing.');
$assert(strpos($migration, 'tmp_role_dashboard_map') !== false, 'Database role-dashboard registry is synchronised.', 'Role-dashboard registry synchronisation is missing.');
$assert(strpos($migration, "attendance_boarding_edit") === false, 'Security Staff is not granted attendance_boarding_edit.', 'Security Staff receives an edit permission in the migration.');
$assert(strpos($migration, "attendance_boarding_approve") === false, 'Security Staff is not granted boarding approval permission.', 'Security Staff receives an approval permission in the migration.');

$cleanup = $read('scripts/apply_dashboard_cleanup.php');
$assert(strpos($cleanup, 'glob(') === false, 'Cleanup uses no wildcard deletion.', 'Cleanup script contains wildcard deletion.');
$assert(strpos($cleanup, 'RecursiveDirectoryIterator') === false, 'Cleanup uses no recursive iterator.', 'Cleanup script contains a recursive iterator.');
$assert(strpos($cleanup, 'rmdir(') === false, 'Cleanup deletes no directories.', 'Cleanup script attempts directory deletion.');

foreach ($passes as $message) {
    echo "[PASS] {$message}\n";
}
foreach ($errors as $message) {
    fwrite(STDERR, "[FAIL] {$message}\n");
}

printf("\nVerification summary: %d passed, %d failed.\n", count($passes), count($errors));
exit($errors ? 1 : 0);
