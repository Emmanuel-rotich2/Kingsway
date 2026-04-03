<?php
/**
 * Submit Results Page
 * This page is now handled by enter_results.php API submission
 * Kept for backward compatibility - redirects to enter_results.php
 * All logic in js/pages/academic.js (enterResultsController)
 */

// This page no longer processes direct form submissions
// All result submission is now done via REST API from enter_results.php
$base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
header('Location: ' . $base . '/home.php?route=enter_results');
exit;
?>

