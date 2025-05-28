<?php
// layouts/app_layout.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle logout before any output
if (isset($_GET['route']) && $_GET['route'] === 'logout') {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$user_role = $_SESSION['role'];

$sidebar_items_admin = [
    [
        'label' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => '?route=admin_dashboard'
    ],
    [
        'label' => 'Manage',
        'icon' => 'fas fa-users-cog',
        'subitems' => [
            ['label' => 'Students', 'icon' => 'fas fa-user-graduate', 'url' => '?route=manage_students'],
            ['label' => 'Teachers', 'icon' => 'fas fa-chalkboard-teacher', 'url' => '?route=manage_teachers'],
            ['label' => 'Non Teaching Staff', 'icon' => 'fas fa-users', 'url' => '?route=manage_non_teaching_staff'],
            ['label' => 'Parents', 'icon' => 'fas fa-user-friends', 'url' => '?route=manage_parents'],
        ]
    ],
    [
        'label' => 'Finance',
        'icon' => 'fas fa-money-bill',
        'subitems' => [
            ['label' => 'Fee Management', 'icon' => 'fas fa-money-bill-wave', 'url' => '?route=fees'],
            ['label' => 'Payroll', 'icon' => 'fas fa-money-check-alt', 'url' => '?route=payroll'],
        ]
    ],
    [
        'label' => 'Reports',
        'icon' => 'fas fa-chart-line',
        'subitems' => [
            ['label' => 'Student Performance', 'icon' => 'fas fa-graduation-cap', 'url' => '?route=student_performance'],
            ['label' => 'Attendance Reports', 'icon' => 'fas fa-calendar-check', 'url' => '?route=attendance_reports'],
            ['label' => 'Financial Reports', 'icon' => 'fas fa-file-invoice-dollar', 'url' => '?route=financial_reports'],
        ]
    ],
    [
        'label' => 'Settings',
        'icon' => 'fas fa-cog',
        'url' => '?route=settings'
    ],

];

$sidebar_items_teacher = [
    [
        'label' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => '?route=teacher_dashboard'
    ],
    [
        'label' => 'My Classes',
        'icon' => 'fas fa-chalkboard',
        'url' => '?route=myclasses'
    ],
    [
        'label' => 'Exam Results',
        'icon' => 'fas fa-poll',
        'url' => '?route=exams'
    ],
    [
        'label' => 'Attendance',
        'icon' => 'fas fa-calendar-check',
        'url' => '?route=attendance'
    ],
    [
        'label' => 'Resources',
        'icon' => 'fas fa-book',
        'url' => '?route=resources'
    ],
    [
        'label' => 'Settings',
        'icon' => 'fas fa-cog',
        'url' => '?route=settings'
    ],
];

$sidebar_items_accountant = [
    [
        'label' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => '?route=accounts_dashboard'
    ],
    [
        'label' => 'Fee Management',
        'icon' => 'fas fa-money-bill-wave',
        'url' => '?route=fees'
    ],
    [
        'label' => 'Payroll',
        'icon' => 'fas fa-money-check-alt',
        'url' => '?route=payroll'
    ],
    [
        'label' => 'Financial Reports',
        'icon' => 'fas fa-file-invoice-dollar',
        'url' => '?route=financial_reports'
    ],
    [
        'label' => 'Settings',
        'icon' => 'fas fa-cog',
        'url' => '?route=settings'
    ],
];

$sidebar_items_registrar = [
    [
        'label' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => '?route=admissions_dashboard'
    ],
    [
        'label' => 'Student Management',
        'icon' => 'fas fa-user-graduate',
        'subitems' => [
            ['label' => 'Admissions', 'icon' => 'fas fa-user-plus', 'url' => '?route=admissions'],
            ['label' => 'Student Records', 'icon' => 'fas fa-file-alt', 'url' => '?route=student_records'],
        ]
    ],
    [
        'label' => 'Teacher Management',
        'icon' => 'fas fa-chalkboard-teacher',
        'url' => '?route=manage_teachers'
    ],
    [
        'label' => 'Attendance Management',
        'icon' => 'fas fa-calendar-check',
        'url' => '?route=attendance_management'
    ],
    [
        'label' => 'Settings',
        'icon' => 'fas fa-cog',
        'url' => '?route=settings'
    ],
];

// Choose sidebar items and default dashboard route based on role
switch ($user_role) {
    case 'teacher':
        $sidebar_items = $sidebar_items_teacher;
        $default_dashboard = 'teacher_dashboard';
        break;
    case 'accountant':
        $sidebar_items = $sidebar_items_accountant;
        $default_dashboard = 'accounts_dashboard';
        break;
    case 'registrar':
        $sidebar_items = $sidebar_items_registrar;
        $default_dashboard = 'admissions_dashboard';
        break;
    case 'admin':
        $sidebar_items = $sidebar_items_admin;
        $default_dashboard = 'admin_dashboard';
        break;
    default:
        // Unknown or unauthorized role, redirect to index.php
        header('Location: ../index.php');
        exit;
}
// Fix: Set $route to the correct dashboard if not provided or invalid
$route = $_GET['route'] ?? $default_dashboard;

// Optionally, redirect to the dashboard if user lands on home with no route
if (!isset($_GET['route']) || empty($_GET['route']) || $_GET['route'] === 'dashboard') {
    header('Location: ?route=' . $default_dashboard);
    exit;
}
?>

<div class="app-layout d-flex">
    <!-- Sidebar (fixed left, full height) -->
    <?php
    $collapsed = false;
    include __DIR__ . '/../components/global/sidebar.php';
    ?>
    <!-- Main area: header at top, main in middle, footer at bottom -->
    <div class="main-flex-layout d-flex flex-column flex-grow-1 min-vh-100" style="margin-left:250px; transition:margin-left 0.3s;">
        <?php include __DIR__ . '/../components/global/header.php'; ?>
        <main class="main-content flex-grow-1" id="main">
            <div class="container-fluid py-3">
                <?php
                $page_file = __DIR__ . '/../pages/' . $route . '.php';
                $dash_file = __DIR__ . '/../components/dashboards/' . $route . '.php';

                if (isset($content_file) && file_exists($content_file)) {
                    include $content_file;
                } else if (file_exists($page_file)) {
                    include $page_file;
                } else if (file_exists($dash_file)) {
                    include $dash_file;
                } else {
                    echo "<div class='alert alert-warning'>Page not found.</div>";
                }
                ?>
            </div>
        </main>
        <?php include __DIR__ . '/../components/global/footer.php'; ?>
    </div>
</div>
<script src="../../js/index.js" type="text/js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>