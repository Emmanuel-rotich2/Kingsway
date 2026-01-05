<?php
/**
 * Universal Dashboard - Permission-Aware Entry Point
 * 
 * This page automatically routes users to their role-specific dashboard
 * based on their role(s) in the system.
 * 
 * Architecture:
 * 1. Page includes dashboard_router.js
 * 2. Router detects user role on document ready
 * 3. Router loads appropriate dashboard script
 * 4. Role-specific dashboard initializes
 * 5. User sees correct content for their role
 * 
 * Access Control:
 * - Authentication check in auth middleware
 * - Role detection in JavaScript
 * - No business logic in PHP (stateless)
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /Kingsway/index.php');
    exit;
}

$pageTitle = 'Dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Kingsway Academy</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/font/bootstrap-icons.min.css">

    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/Kingsway/king.css">

    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.3rem;
            color: white !important;
        }

        .navbar .btn-outline-secondary {
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
        }

        .navbar .btn-outline-secondary:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: white;
        }

        .dropdown-menu {
            min-width: 300px;
        }

        .dropdown-item.active {
            background-color: #667eea;
        }

        main {
            padding: 2rem 0;
        }

        .loading-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
        }

        .loading-spinner .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        #cardsContainer {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .alert {
            margin: 2rem;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="/Kingsway/home.php">
                <i class="bi bi-speedometer2"></i> Kingsway Academy
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/Kingsway/home.php">
                            <i class="bi bi-house-door"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Kingsway/me.php">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Kingsway/api/index.php?action=logout">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="mainContent" class="container-fluid" data-dashboard-page>
        <!-- Loading state -->
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading dashboard...</span>
            </div>
        </div>

        <!-- Content will be injected here by dashboard controller -->
    </main>

    <!-- Footer -->
    <footer class="bg-light text-center py-4 mt-5">
        <div class="container-fluid">
            <small class="text-muted">
                Kingsway Academy Management System | Last Refresh: <span id="lastRefreshTime">--:--:--</span>
            </small>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <!-- Authentication utilities -->
    <script src="/Kingsway/js/auth-utils.js"></script>

    <!-- API client -->
    <script src="/Kingsway/js/api.js"></script>

    <!-- Dashboard Router (permission-aware routing) -->
    <script src="/Kingsway/js/dashboards/dashboard_router.js"></script>

    <!-- System Administrator Dashboard (loaded dynamically via router) -->
    <script src="/Kingsway/js/dashboards/system_administrator_dashboard.js"></script>

    <!-- Other dashboards loaded dynamically as needed -->

    <script>
        // Utility functions for dashboards

        /**
         * Format large numbers with commas
         */
        function formatNumber(num) {
            if (num === null || num === undefined) return '-';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        /**
         * Escape HTML to prevent XSS
         */
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        /**
         * Show notification
         */
        function showNotification(message, type = 'info') {
            const alertClass = `alert-${type}`;
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            const mainContent = document.getElementById('mainContent');
            if (mainContent) {
                mainContent.insertAdjacentHTML('beforeend', alertHtml);

                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    const alert = mainContent.querySelector('.alert');
                    if (alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            }
        }

        /**
         * Format large currency amounts
         */
        function formatCurrency(amount) {
            if (amount === null || amount === undefined) return 'KES 0';
            return new Intl.NumberFormat('en-KE', {
                style: 'currency',
                currency: 'KES'
            }).format(amount);
        }

        /**
         * Format percentage
         */
        function formatPercentage(value, decimals = 1) {
            if (value === null || value === undefined) return '-';
            return parseFloat(value).toFixed(decimals) + '%';
        }
    </script>
</body>

</html>