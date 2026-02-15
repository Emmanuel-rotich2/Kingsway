<?php
/**
 * All Staff Page - Role-Based Router
 * Routes to role-specific templates based on user's role category
 * 
 * Role-based templates:
 * - admin:    Full access (Director, System Administrator, School Administrator)
 * - manager:  Department view (Deputy Heads, HODs, Headteacher)
 * - operator: Directory view (Class Teacher, Subject Teacher)
 * - viewer:   Key contacts only (Students, Parents)
 */

require_once __DIR__ . '/../config/permissions.php';

// Determine role from session
$role = '';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

// Map roles to template files
$adminRoles = ['director', 'director/owner', 'system administrator', 'school administrator', 'school administrative officer'];
$managerRoles = [
    'headteacher',
    'deputy head - academics',
    'deputy head - discipline',
    'deputy head - boarding',
    'hod - languages',
    'hod - sciences',
    'hod - humanities',
    'hod - mathematics',
    'hod - talent development',
    'hod - food & nutrition',
    'dean of studies'
];
$operatorRoles = ['class teacher', 'subject teacher', 'teacher', 'games teacher'];
// Everyone else gets viewer

if (in_array($role, $adminRoles)) {
    $templatePath = __DIR__ . '/staff/admin_staff.php';
} elseif (in_array($role, $managerRoles)) {
    $templatePath = __DIR__ . '/staff/manager_staff.php';
} elseif (in_array($role, $operatorRoles)) {
    $templatePath = __DIR__ . '/staff/operator_staff.php';
} else {
    $templatePath = __DIR__ . '/staff/viewer_staff.php';
}

include $templatePath;
?>