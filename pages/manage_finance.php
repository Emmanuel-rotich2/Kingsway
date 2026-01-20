<?php
/**
 * Manage Finance Page - Router
 * Routes to role-specific templates based on user's role category
 * 
 * Role-based templates:
 * - admin: Full access (Director, Headteacher, System Admin)
 * - manager: Create/Edit access (Accountant, Bursar)
 * - operator: View-only (Teachers - for budget awareness)
 * - viewer: Personal fees only (Students, Parents)
 */

// Include permissions helper
require_once __DIR__ . '/../config/permissions.php';

// Default template (will be overridden by JavaScript)
$templatePath = __DIR__ . '/finance/manager_finance.php'; // Default fallback

// Include the template (JavaScript will replace content based on role)
include $templatePath;
exit;

?>