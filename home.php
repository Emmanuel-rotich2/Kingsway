<?php
session_start();
require_once __DIR__ . '/api/includes/auth_middleware.php';

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

// Get user role from session
$main_role = $_SESSION['main_role'] ?? ($_SESSION['roles'][0] ?? 'admin');
$roles = $_SESSION['roles'] ?? [$main_role];
?>
<!DOCTYPE html>
<html lang="en">

<head>
<<<<<<< HEAD
  <meta charset="UTF-8">
  <title>Kingsway Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="king.css">
=======
    <meta charset="UTF-8">
    <title>Kingsway Preparatory Academy | <?php echo ucfirst($main_role); ?> Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="images/favicon/favicon.svg" />
    <link rel="shortcut icon" href="images/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="KingsWay Preparatory School Dashboard" />
    <link rel="manifest" href="images/favicon/site.webmanifest" />
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/Kingsway/king.css">
    
    <!-- JavaScript Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        window.USER_ROLES = <?php echo json_encode($roles); ?>;
        window.MAIN_ROLE = <?php echo json_encode($main_role); ?>;
        window.USERNAME = <?php echo json_encode($_SESSION['username'] ?? null); ?>;
        window.AUTH_TOKEN = <?php echo json_encode($_SESSION['token'] ?? null); ?>;
    </script>
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948
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
    <script src="/Kingsway/js/main.js"></script>
    <script src="/Kingsway/js/index.js"></script>
</body>

</html>