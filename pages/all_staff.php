<?php
/**
 * All Staff Page - Router
 * Routes to role-specific templates based on user's role category
 * 
 * Role-based templates:
 * - admin: Full access (Director, Headteacher, System Admin)
 * - manager: Department view (Deputy Heads, HODs)
 * - operator: Directory view (Teachers)
 * - viewer: Key contacts only (Students, Parents)
 */

// Include permissions helper
require_once __DIR__ . '/../config/permissions.php';

// Get user's role category
session_start();
$userRole = $_SESSION['role'] ?? 'guest';
$roleCategory = getRoleCategory($userRole);

// Include role-specific template
$templatePath = __DIR__ . "/staff/{$roleCategory}_staff.php";

if (file_exists($templatePath)) {
    include $templatePath;
} else {
    // Fallback to viewer template
    include __DIR__ . '/staff/viewer_staff.php';
}
?>