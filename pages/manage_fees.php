<?php
/**
 * Manage Fees Page - Router
 * Routes to role-specific templates based on user's role category
 * 
 * Role-based templates:
 * - admin: Full access (Director, Headteacher, System Admin)
 * - manager: Payment recording (Accountant, Bursar)
 * - operator: Class fee status view (Teachers)
 * - viewer: Personal fee balance (Students, Parents)
 */

// Include permissions helper
require_once __DIR__ . '/../config/permissions.php';

// Get user's role category
session_start();
$userRole = $_SESSION['role'] ?? 'guest';
$roleCategory = getRoleCategory($userRole);

// Include role-specific template
$templatePath = __DIR__ . "/fees/{$roleCategory}_fees.php";

if (file_exists($templatePath)) {
    include $templatePath;
} else {
    // Fallback to viewer template
    include __DIR__ . '/fees/viewer_fees.php';
}