<?php
/**
 * Manage Boarding Page - Stateless JWT-based Router
 *
 * Uses JavaScript to determine user role from JWT token and load appropriate template
 */

// Default template (will be overridden by JavaScript)
$templatePath = __DIR__ . '/boarding/manager_boarding.php'; // Default fallback

// Include the template (JavaScript will replace content based on role)
include $templatePath;
exit;
?>
}