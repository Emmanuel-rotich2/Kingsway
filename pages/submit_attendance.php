<?php
/**
 * Submit Attendance Page
 * This page is now handled by mark_attendance.php API submission
 * Kept for backward compatibility - redirects to mark_attendance.php
 * All logic in js/pages/academic.js (markAttendanceController)
 */

// This page no longer processes direct form submissions
// All attendance submission is now done via REST API from mark_attendance.php
header('Location: /Kingsway/home.php?route=mark_attendance');
exit;
?>
