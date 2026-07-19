<?php
/**
 * Basic Authentication Check
 * 
 * This file checks if the user is authenticated and redirects to login if not.
 * It's used for protected pages like the student portal.
 */

session_start();

// Check if user is authenticated
if (!isset($_SESSION['user']) && !isset($_SERVER['auth_user'])) {
    // Redirect to login page
    header('Location: /index.php');
    exit;
}

// Set auth_user from session if not already set
if (!isset($_SERVER['auth_user']) && isset($_SESSION['user'])) {
    $_SERVER['auth_user'] = $_SESSION['user'];
}
