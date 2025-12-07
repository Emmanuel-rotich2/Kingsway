<?php
session_start();
require_once __DIR__ . '/config/DashboardRouter.php';

// Verify authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle logout
if (isset($_GET['route']) && $_GET['route'] === 'logout') {
    session_destroy();
    header('Location: ./index.php');
    exit;
}

// If no route specified, redirect to user's default dashboard
if (!isset($_GET['route']) || empty($_GET['route'])) {
    DashboardRouter::redirectToDefaultDashboard(true);
}

// Get user role from session
$main_role = $_SESSION['main_role'] ?? ($_SESSION['roles'][0] ?? 'admin');
$roles = $_SESSION['roles'] ?? [$main_role];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Kingsway Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="king.css">
</head>

<body>
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content notification-info">
                <div class="modal-body d-flex align-items-center">
                    <span class="notification-icon me-3"><i class="bi bi-info-circle"></i></span>
                    <span class="notification-message"></span>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/layouts/app_layout.php'; ?>

    <!-- Application Scripts -->
    <script src="/Kingsway/js/api.js"></script>
    <script src="/Kingsway/js/sidebar.js"></script>
    <script src="/Kingsway/js/main.js"></script>
    <script src="/Kingsway/js/index.js"></script>
</body>

</html>