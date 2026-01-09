<?php
/**
 * Manage Boarding Page - Router
 * Routes to role-specific templates based on user's role category
 * 
 * Role-based templates:
 * - admin: Full access (Director, Headteacher, System Admin)
 * - manager: Operational management (Boarding Master, Matron)
 * - operator: View student boarding (Teachers, Nurse)
 * - viewer: View own child's status (Parents)
 */

// Include permissions helper
require_once __DIR__ . '/../config/permissions.php';

// Get user's role category
session_start();
$userRole = $_SESSION['role'] ?? 'guest';
$roleCategory = getRoleCategory($userRole);

// Include role-specific template
$templatePath = __DIR__ . "/boarding/{$roleCategory}_boarding.php";

if (file_exists($templatePath)) {
    include $templatePath;
} else {
    // Fallback to viewer template
    include __DIR__ . '/boarding/viewer_boarding.php';
}