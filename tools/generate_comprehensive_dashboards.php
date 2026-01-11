<?php
/**
 * COMPREHENSIVE DASHBOARD GENERATOR
 * 
 * This generator analyzes:
 * 1. All REST API controllers and their endpoints
 * 2. Database structure and relationships
 * 3. Kenyan primary school role responsibilities
 * 4. Creates hierarchical sidebar menus with sub-items
 * 
 * Usage: php tools/generate_comprehensive_dashboards.php
 */

// Database connection
$pdo = new PDO("mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=KingsWayAcademy", 'root', 'admin123');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "==============================================\n";
echo "COMPREHENSIVE DASHBOARD GENERATOR\n";
echo "==============================================\n\n";

// Fetch all roles from database
$rolesStmt = $pdo->query("
    SELECT id, name, description 
    FROM roles 
    ORDER BY id
");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($roles) . " roles\n\n";

/**
 * COMPREHENSIVE SIDEBAR MENU DEFINITIONS
 * Based on REST API endpoints and Kenyan school role responsibilities
 */
$dashboardDefinitions = [
    // SYSTEM ADMINISTRATOR (ID: 2) - Full system access
    2 => [
        'role_name' => 'System Administrator',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Overview', 'route' => '/dashboard/overview'],
                    ['label' => 'System Health', 'route' => '/dashboard/system-health'],
                    ['label' => 'Analytics', 'route' => '/dashboard/analytics']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'route' => null,
                'subitems' => [
                    ['label' => 'All Students', 'route' => '/students'],
                    ['label' => 'Admissions', 'route' => '/students/admissions'],
                    ['label' => 'Attendance', 'route' => '/students/attendance'],
                    ['label' => 'Promotions', 'route' => '/students/promotions'],
                    ['label' => 'Transfers', 'route' => '/students/transfers'],
                    ['label' => 'Discipline', 'route' => '/students/discipline']
                ]
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'route' => null,
                'subitems' => [
                    ['label' => 'Academic Years', 'route' => '/academic/years'],
                    ['label' => 'Terms', 'route' => '/academic/terms'],
                    ['label' => 'Classes & Streams', 'route' => '/academic/classes'],
                    ['label' => 'Subjects', 'route' => '/academic/subjects'],
                    ['label' => 'Curriculum', 'route' => '/academic/curriculum'],
                    ['label' => 'Timetable', 'route' => '/academic/timetable'],
                    ['label' => 'Assessments', 'route' => '/academic/assessments'],
                    ['label' => 'Exams', 'route' => '/academic/exams'],
                    ['label' => 'Lesson Plans', 'route' => '/academic/lesson-plans']
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'route' => null,
                'subitems' => [
                    ['label' => 'All Staff', 'route' => '/staff'],
                    ['label' => 'Attendance', 'route' => '/staff/attendance'],
                    ['label' => 'Leave Management', 'route' => '/staff/leaves'],
                    ['label' => 'Performance', 'route' => '/staff/performance'],
                    ['label' => 'Payroll', 'route' => '/staff/payroll'],
                    ['label' => 'Onboarding', 'route' => '/staff/onboarding']
                ]
            ],
            [
                'label' => 'Finance',
                'icon' => 'fas fa-coins',
                'route' => null,
                'subitems' => [
                    ['label' => 'Fee Structure', 'route' => '/finance/fees'],
                    ['label' => 'Payments', 'route' => '/finance/payments'],
                    ['label' => 'Invoices', 'route' => '/finance/invoices'],
                    ['label' => 'Expenses', 'route' => '/finance/expenses'],
                    ['label' => 'Bank Reconciliation', 'route' => '/finance/reconciliation'],
                    ['label' => 'Financial Reports', 'route' => '/finance/reports']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => null,
                'subitems' => [
                    ['label' => 'Messages', 'route' => '/communications/messages'],
                    ['label' => 'Announcements', 'route' => '/communications/announcements'],
                    ['label' => 'SMS', 'route' => '/communications/sms'],
                    ['label' => 'Parent Portal', 'route' => '/communications/parent-portal'],
                    ['label' => 'Templates', 'route' => '/communications/templates']
                ]
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fas fa-boxes',
                'route' => null,
                'subitems' => [
                    ['label' => 'Items', 'route' => '/inventory/items'],
                    ['label' => 'Stock Management', 'route' => '/inventory/stock'],
                    ['label' => 'Requisitions', 'route' => '/inventory/requisitions'],
                    ['label' => 'Purchase Orders', 'route' => '/inventory/purchase-orders'],
                    ['label' => 'Suppliers', 'route' => '/inventory/suppliers'],
                    ['label' => 'Stock Count', 'route' => '/inventory/stock-count']
                ]
            ],
            [
                'label' => 'Transport',
                'icon' => 'fas fa-bus',
                'route' => null,
                'subitems' => [
                    ['label' => 'Routes', 'route' => '/transport/routes'],
                    ['label' => 'Vehicles', 'route' => '/transport/vehicles'],
                    ['label' => 'Drivers', 'route' => '/transport/drivers'],
                    ['label' => 'Maintenance', 'route' => '/transport/maintenance']
                ]
            ],
            [
                'label' => 'Activities',
                'icon' => 'fas fa-running',
                'route' => null,
                'subitems' => [
                    ['label' => 'Sports', 'route' => '/activities/sports'],
                    ['label' => 'Clubs', 'route' => '/activities/clubs'],
                    ['label' => 'Events', 'route' => '/activities/events'],
                    ['label' => 'Competitions', 'route' => '/activities/competitions']
                ]
            ],
            [
                'label' => 'Boarding',
                'icon' => 'fas fa-bed',
                'route' => null,
                'subitems' => [
                    ['label' => 'Students', 'route' => '/boarding/students'],
                    ['label' => 'Room Allocation', 'route' => '/boarding/rooms'],
                    ['label' => 'Health Records', 'route' => '/boarding/health'],
                    ['label' => 'Welfare', 'route' => '/boarding/welfare']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Academic Reports', 'route' => '/reports/academic'],
                    ['label' => 'Financial Reports', 'route' => '/reports/financial'],
                    ['label' => 'Student Reports', 'route' => '/reports/students'],
                    ['label' => 'Staff Reports', 'route' => '/reports/staff'],
                    ['label' => 'Custom Reports', 'route' => '/reports/custom']
                ]
            ],
            [
                'label' => 'Users & Access',
                'icon' => 'fas fa-users-cog',
                'route' => null,
                'subitems' => [
                    ['label' => 'Users', 'route' => '/users'],
                    ['label' => 'Roles', 'route' => '/users/roles'],
                    ['label' => 'Permissions', 'route' => '/users/permissions'],
                    ['label' => 'Audit Logs', 'route' => '/users/audit']
                ]
            ],
            [
                'label' => 'System',
                'icon' => 'fas fa-cogs',
                'route' => null,
                'subitems' => [
                    ['label' => 'Settings', 'route' => '/system/settings'],
                    ['label' => 'Configuration', 'route' => '/system/config'],
                    ['label' => 'Backups', 'route' => '/system/backups'],
                    ['label' => 'Maintenance', 'route' => '/system/maintenance'],
                    ['label' => 'System Logs', 'route' => '/system/logs']
                ]
            ]
        ]
    ],

    // DIRECTOR (ID: 3) - Financial oversight
    3 => [
        'role_name' => 'Director',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Executive Summary', 'route' => '/dashboard/executive'],
                    ['label' => 'KPIs', 'route' => '/dashboard/kpis']
                ]
            ],
            [
                'label' => 'Finance',
                'icon' => 'fas fa-coins',
                'route' => null,
                'subitems' => [
                    ['label' => 'Financial Reports', 'route' => '/finance/reports'],
                    ['label' => 'Budget Overview', 'route' => '/finance/budget'],
                    ['label' => 'Approvals', 'route' => '/finance/approvals'],
                    ['label' => 'Cash Flow', 'route' => '/finance/cashflow']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-line',
                'route' => null,
                'subitems' => [
                    ['label' => 'Financial', 'route' => '/reports/financial'],
                    ['label' => 'Enrollment', 'route' => '/reports/enrollment'],
                    ['label' => 'Performance', 'route' => '/reports/performance']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => null,
                'subitems' => [
                    ['label' => 'Messages', 'route' => '/communications/messages'],
                    ['label' => 'Announcements', 'route' => '/communications/announcements']
                ]
            ]
        ]
    ],

    // SCHOOL ADMINISTRATOR (ID: 4) - Operational management
    4 => [
        'role_name' => 'School Administrator',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Overview', 'route' => '/dashboard/overview'],
                    ['label' => 'Today\'s Summary', 'route' => '/dashboard/today']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'route' => null,
                'subitems' => [
                    ['label' => 'All Students', 'route' => '/students'],
                    ['label' => 'Admissions', 'route' => '/students/admissions'],
                    ['label' => 'Attendance', 'route' => '/students/attendance'],
                    ['label' => 'Discipline', 'route' => '/students/discipline']
                ]
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'route' => null,
                'subitems' => [
                    ['label' => 'Classes', 'route' => '/academic/classes'],
                    ['label' => 'Timetable', 'route' => '/academic/timetable'],
                    ['label' => 'Exams', 'route' => '/academic/exams'],
                    ['label' => 'Results', 'route' => '/academic/results']
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'route' => null,
                'subitems' => [
                    ['label' => 'All Staff', 'route' => '/staff'],
                    ['label' => 'Attendance', 'route' => '/staff/attendance'],
                    ['label' => 'Leaves', 'route' => '/staff/leaves']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => null,
                'subitems' => [
                    ['label' => 'Messages', 'route' => '/communications/messages'],
                    ['label' => 'Announcements', 'route' => '/communications/announcements'],
                    ['label' => 'Parent Communication', 'route' => '/communications/parents']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Academic', 'route' => '/reports/academic'],
                    ['label' => 'Students', 'route' => '/reports/students'],
                    ['label' => 'Staff', 'route' => '/reports/staff'],
                    ['label' => 'Attendance', 'route' => '/reports/attendance']
                ]
            ],
            [
                'label' => 'Users & Access',
                'icon' => 'fas fa-users-cog',
                'route' => null,
                'subitems' => [
                    ['label' => 'Manage Users', 'route' => '/users'],
                    ['label' => 'Roles', 'route' => '/users/roles']
                ]
            ]
        ]
    ],

    // HEADTEACHER (ID: 5) - Academic leadership
    5 => [
        'role_name' => 'Headteacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'School Overview', 'route' => '/dashboard/overview'],
                    ['label' => 'Today\'s Schedule', 'route' => '/dashboard/schedule']
                ]
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'route' => null,
                'subitems' => [
                    ['label' => 'Academic Years', 'route' => '/academic/years'],
                    ['label' => 'Terms', 'route' => '/academic/terms'],
                    ['label' => 'Classes', 'route' => '/academic/classes'],
                    ['label' => 'Timetable', 'route' => '/academic/timetable'],
                    ['label' => 'Exams', 'route' => '/academic/exams'],
                    ['label' => 'Results', 'route' => '/academic/results']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'route' => null,
                'subitems' => [
                    ['label' => 'All Students', 'route' => '/students'],
                    ['label' => 'Admissions', 'route' => '/students/admissions'],
                    ['label' => 'Discipline', 'route' => '/students/discipline'],
                    ['label' => 'Performance', 'route' => '/students/performance']
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'route' => null,
                'subitems' => [
                    ['label' => 'All Staff', 'route' => '/staff'],
                    ['label' => 'Performance', 'route' => '/staff/performance']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => null,
                'subitems' => [
                    ['label' => 'Messages', 'route' => '/communications/messages'],
                    ['label' => 'Announcements', 'route' => '/communications/announcements'],
                    ['label' => 'Parent Communication', 'route' => '/communications/parents']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Academic', 'route' => '/reports/academic'],
                    ['label' => 'Student Performance', 'route' => '/reports/performance'],
                    ['label' => 'Staff', 'route' => '/reports/staff']
                ]
            ]
        ]
    ],

    // DEPUTY HEAD - ACADEMIC (ID: 6)
    6 => [
        'role_name' => 'Deputy Head - Academic',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Academic Overview', 'route' => '/dashboard/academic']
                ]
            ],
            [
                'label' => 'Timetable',
                'icon' => 'fas fa-calendar-alt',
                'route' => null,
                'subitems' => [
                    ['label' => 'Create', 'route' => '/timetable/create'],
                    ['label' => 'View', 'route' => '/timetable/view'],
                    ['label' => 'Modify', 'route' => '/timetable/edit']
                ]
            ],
            [
                'label' => 'Exams',
                'icon' => 'fas fa-file-alt',
                'route' => null,
                'subitems' => [
                    ['label' => 'Schedule', 'route' => '/exams/schedule'],
                    ['label' => 'Results Entry', 'route' => '/exams/results'],
                    ['label' => 'Analysis', 'route' => '/exams/analysis']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'route' => null,
                'subitems' => [
                    ['label' => 'Promotion', 'route' => '/students/promotion'],
                    ['label' => 'Performance', 'route' => '/students/performance'],
                    ['label' => 'Reports', 'route' => '/students/reports']
                ]
            ],
            [
                'label' => 'Classes',
                'icon' => 'fas fa-door-open',
                'route' => null,
                'subitems' => [
                    ['label' => 'Management', 'route' => '/classes/manage'],
                    ['label' => 'Assignments', 'route' => '/classes/assignments']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Academic', 'route' => '/reports/academic'],
                    ['label' => 'Student Reports', 'route' => '/reports/students']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ]
        ]
    ],

    // DEPUTY HEAD - DISCIPLINE (ID: 63)
    63 => [
        'role_name' => 'Deputy Head - Discipline',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Discipline Overview', 'route' => '/dashboard/discipline']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'route' => null,
                'subitems' => [
                    ['label' => 'All Students', 'route' => '/students'],
                    ['label' => 'Admissions', 'route' => '/students/admissions'],
                    ['label' => 'Discipline Cases', 'route' => '/students/discipline']
                ]
            ],
            [
                'label' => 'Discipline',
                'icon' => 'fas fa-gavel',
                'route' => null,
                'subitems' => [
                    ['label' => 'Cases', 'route' => '/discipline/cases'],
                    ['label' => 'Sanctions', 'route' => '/discipline/sanctions'],
                    ['label' => 'Counseling', 'route' => '/discipline/counseling']
                ]
            ],
            [
                'label' => 'Attendance',
                'icon' => 'fas fa-clipboard-check',
                'route' => null,
                'subitems' => [
                    ['label' => 'View', 'route' => '/attendance/view'],
                    ['label' => 'Trends', 'route' => '/attendance/trends']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => null,
                'subitems' => [
                    ['label' => 'Messages', 'route' => '/communications/messages'],
                    ['label' => 'Parent Communication', 'route' => '/communications/parents']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Discipline', 'route' => '/reports/discipline'],
                    ['label' => 'Attendance', 'route' => '/reports/attendance']
                ]
            ]
        ]
    ],

    // CLASS TEACHER (ID: 7)
    7 => [
        'role_name' => 'Class Teacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Today\'s Schedule', 'route' => '/dashboard/schedule']
                ]
            ],
            // 'My Classes' menu removed for Class Teacher (id: 7) by request

            [
                'label' => 'Attendance',
                'icon' => 'fas fa-clipboard-check',
                'route' => null,
                'subitems' => [
                    ['label' => 'Mark Attendance', 'route' => '/attendance/mark'],
                    ['label' => 'History', 'route' => '/attendance/history']
                ]
            ],
            [
                'label' => 'Assessments',
                'icon' => 'fas fa-file-alt',
                'route' => null,
                'subitems' => [
                    ['label' => 'Create', 'route' => '/assessments/create'],
                    ['label' => 'Enter Results', 'route' => '/assessments/results'],
                    ['label' => 'View', 'route' => '/assessments/view']
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'route' => null,
                'subitems' => [
                    ['label' => 'Create', 'route' => '/lessons/create'],
                    ['label' => 'My Plans', 'route' => '/lessons/my-plans'],
                    ['label' => 'Calendar', 'route' => '/lessons/calendar']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => null,
                'subitems' => [
                    ['label' => 'Messages', 'route' => '/communications/messages'],
                    ['label' => 'Parent Communication', 'route' => '/communications/parents']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Class Reports', 'route' => '/reports/class'],
                    ['label' => 'Student Reports', 'route' => '/reports/students']
                ]
            ]
        ]
    ],

    // SUBJECT TEACHER (ID: 8)
    8 => [
        'role_name' => 'Subject Teacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'My Subjects', 'route' => '/dashboard/my-subjects'],
                    ['label' => 'Schedule', 'route' => '/dashboard/schedule']
                ]
            ],
            [
                'label' => 'My Teaching',
                'icon' => 'fas fa-chalkboard-teacher',
                'route' => null,
                'subitems' => [
                    ['label' => 'Classes', 'route' => '/teaching/classes'],
                    ['label' => 'Students', 'route' => '/teaching/students'],
                    ['label' => 'Schedule', 'route' => '/teaching/schedule']
                ]
            ],
            [
                'label' => 'Assessments',
                'icon' => 'fas fa-file-alt',
                'route' => null,
                'subitems' => [
                    ['label' => 'Create', 'route' => '/assessments/create'],
                    ['label' => 'Enter Results', 'route' => '/assessments/results'],
                    ['label' => 'Analysis', 'route' => '/assessments/analysis']
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'route' => null,
                'subitems' => [
                    ['label' => 'Create', 'route' => '/lessons/create'],
                    ['label' => 'My Plans', 'route' => '/lessons/my-plans']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Subject Performance', 'route' => '/reports/subject-performance']
                ]
            ]
        ]
    ],

    // INTERN/STUDENT TEACHER (ID: 9)
    9 => [
        'role_name' => 'Intern/Student Teacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => []
            ],
            [
                'label' => 'My Teaching',
                'icon' => 'fas fa-chalkboard-teacher',
                'route' => null,
                'subitems' => [
                    ['label' => 'Classes', 'route' => '/teaching/classes'],
                    ['label' => 'Schedule', 'route' => '/teaching/schedule']
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'route' => null,
                'subitems' => [
                    ['label' => 'View Plans', 'route' => '/lessons/view']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ]
        ]
    ],

    // ACCOUNTANT (ID: 10)
    10 => [
        'role_name' => 'Accountant',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Finance Overview', 'route' => '/dashboard/finance'],
                    ['label' => 'Today\'s Collection', 'route' => '/dashboard/collection']
                ]
            ],
            [
                'label' => 'Fees',
                'icon' => 'fas fa-receipt',
                'route' => null,
                'subitems' => [
                    ['label' => 'Fee Structure', 'route' => '/fees/structure'],
                    ['label' => 'Student Fees', 'route' => '/fees/students'],
                    ['label' => 'Arrears', 'route' => '/fees/arrears']
                ]
            ],
            [
                'label' => 'Payments',
                'icon' => 'fas fa-money-bill-wave',
                'route' => null,
                'subitems' => [
                    ['label' => 'Receive Payment', 'route' => '/payments/receive'],
                    ['label' => 'Allocate', 'route' => '/payments/allocate'],
                    ['label' => 'Reconcile', 'route' => '/payments/reconcile']
                ]
            ],
            [
                'label' => 'Payroll',
                'icon' => 'fas fa-wallet',
                'route' => null,
                'subitems' => [
                    ['label' => 'Staff Payroll', 'route' => '/payroll/staff'],
                    ['label' => 'Generate Payslips', 'route' => '/payroll/payslips'],
                    ['label' => 'P9 Forms', 'route' => '/payroll/p9']
                ]
            ],
            [
                'label' => 'Expenses',
                'icon' => 'fas fa-credit-card',
                'route' => null,
                'subitems' => [
                    ['label' => 'Record', 'route' => '/expenses/record'],
                    ['label' => 'Approve', 'route' => '/expenses/approve'],
                    ['label' => 'Track', 'route' => '/expenses/track']
                ]
            ],
            [
                'label' => 'Banking',
                'icon' => 'fas fa-university',
                'route' => null,
                'subitems' => [
                    ['label' => 'Transactions', 'route' => '/banking/transactions'],
                    ['label' => 'Reconciliation', 'route' => '/banking/reconciliation']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-line',
                'route' => null,
                'subitems' => [
                    ['label' => 'Financial', 'route' => '/reports/financial'],
                    ['label' => 'Collection', 'route' => '/reports/collection'],
                    ['label' => 'Arrears', 'route' => '/reports/arrears'],
                    ['label' => 'Payroll', 'route' => '/reports/payroll']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ]
        ]
    ],

    // INVENTORY MANAGER (ID: 14)
    14 => [
        'role_name' => 'Inventory Manager',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Inventory Overview', 'route' => '/dashboard/inventory']
                ]
            ],
            [
                'label' => 'Uniforms',
                'icon' => 'fas fa-tshirt',
                'route' => null,
                'subitems' => [
                    ['label' => 'Stock', 'route' => '/uniforms/stock'],
                    ['label' => 'Sales', 'route' => '/uniforms/sales'],
                    ['label' => 'Sizing', 'route' => '/uniforms/sizing']
                ]
            ],
            [
                'label' => 'Requisitions',
                'icon' => 'fas fa-clipboard-list',
                'route' => null,
                'subitems' => [
                    ['label' => 'Pending', 'route' => '/requisitions/pending'],
                    ['label' => 'Approved', 'route' => '/requisitions/approved'],
                    ['label' => 'History', 'route' => '/requisitions/history']
                ]
            ],
            [
                'label' => 'Stock Management',
                'icon' => 'fas fa-boxes',
                'route' => null,
                'subitems' => [
                    ['label' => 'Receive Stock', 'route' => '/stock/receive'],
                    ['label' => 'Stock Count', 'route' => '/stock/count'],
                    ['label' => 'Adjustments', 'route' => '/stock/adjustments']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Stock Levels', 'route' => '/reports/stock-levels'],
                    ['label' => 'Sales', 'route' => '/reports/sales'],
                    ['label' => 'Valuation', 'route' => '/reports/valuation']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ]
        ]
    ],

    // CATERESS (ID: 16)
    16 => [
        'role_name' => 'Cateress',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Kitchen Overview', 'route' => '/dashboard/kitchen']
                ]
            ],
            [
                'label' => 'Menu Planning',
                'icon' => 'fas fa-utensils',
                'route' => null,
                'subitems' => [
                    ['label' => 'Weekly Menu', 'route' => '/menu/weekly'],
                    ['label' => 'Meal Schedule', 'route' => '/menu/schedule']
                ]
            ],
            [
                'label' => 'Food Store',
                'icon' => 'fas fa-shopping-basket',
                'route' => null,
                'subitems' => [
                    ['label' => 'Inventory', 'route' => '/food-store/inventory'],
                    ['label' => 'Stock Levels', 'route' => '/food-store/stock'],
                    ['label' => 'Requisitions', 'route' => '/food-store/requisitions']
                ]
            ],
            [
                'label' => 'Kitchen Staff',
                'icon' => 'fas fa-users',
                'route' => null,
                'subitems' => [
                    ['label' => 'Schedule', 'route' => '/kitchen-staff/schedule'],
                    ['label' => 'Assignments', 'route' => '/kitchen-staff/assignments']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Consumption', 'route' => '/reports/consumption'],
                    ['label' => 'Stock', 'route' => '/reports/stock']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ]
        ]
    ],

    // BOARDING MASTER (ID: 18)
    18 => [
        'role_name' => 'Boarding Master',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Boarding Overview', 'route' => '/dashboard/boarding']
                ]
            ],
            [
                'label' => 'Boarding Students',
                'icon' => 'fas fa-bed',
                'route' => null,
                'subitems' => [
                    ['label' => 'All Students', 'route' => '/boarding/students'],
                    ['label' => 'Room Allocation', 'route' => '/boarding/rooms'],
                    ['label' => 'Health', 'route' => '/boarding/health']
                ]
            ],
            [
                'label' => 'Welfare',
                'icon' => 'fas fa-heartbeat',
                'route' => null,
                'subitems' => [
                    ['label' => 'Health Records', 'route' => '/welfare/health'],
                    ['label' => 'Incidents', 'route' => '/welfare/incidents']
                ]
            ],
            [
                'label' => 'Discipline',
                'icon' => 'fas fa-gavel',
                'route' => null,
                'subitems' => [
                    ['label' => 'Cases', 'route' => '/discipline/cases'],
                    ['label' => 'Night Roll Call', 'route' => '/discipline/roll-call']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Boarding', 'route' => '/reports/boarding'],
                    ['label' => 'Health', 'route' => '/reports/health'],
                    ['label' => 'Incidents', 'route' => '/reports/incidents']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => null,
                'subitems' => [
                    ['label' => 'Messages', 'route' => '/communications/messages'],
                    ['label' => 'Parent Communication', 'route' => '/communications/parents']
                ]
            ]
        ]
    ],

    // TALENT DEVELOPMENT (ID: 21)
    21 => [
        'role_name' => 'Talent Development',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Activities Overview', 'route' => '/dashboard/activities']
                ]
            ],
            [
                'label' => 'Sports',
                'icon' => 'fas fa-futbol',
                'route' => null,
                'subitems' => [
                    ['label' => 'Teams', 'route' => '/sports/teams'],
                    ['label' => 'Training', 'route' => '/sports/training'],
                    ['label' => 'Fixtures', 'route' => '/sports/fixtures'],
                    ['label' => 'Results', 'route' => '/sports/results']
                ]
            ],
            [
                'label' => 'Music & Drama',
                'icon' => 'fas fa-music',
                'route' => null,
                'subitems' => [
                    ['label' => 'Groups', 'route' => '/music-drama/groups'],
                    ['label' => 'Rehearsals', 'route' => '/music-drama/rehearsals'],
                    ['label' => 'Performances', 'route' => '/music-drama/performances']
                ]
            ],
            [
                'label' => 'Competitions',
                'icon' => 'fas fa-trophy',
                'route' => null,
                'subitems' => [
                    ['label' => 'Upcoming', 'route' => '/competitions/upcoming'],
                    ['label' => 'Results', 'route' => '/competitions/results'],
                    ['label' => 'Awards', 'route' => '/competitions/awards']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'route' => null,
                'subitems' => [
                    ['label' => 'Talent Database', 'route' => '/students/talent'],
                    ['label' => 'Participation', 'route' => '/students/participation']
                ]
            ],
            [
                'label' => 'Equipment',
                'icon' => 'fas fa-dumbbell',
                'route' => null,
                'subitems' => [
                    ['label' => 'Inventory', 'route' => '/equipment/inventory'],
                    ['label' => 'Requisitions', 'route' => '/equipment/requisitions']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ]
        ]
    ],

    // DRIVER (ID: 23)
    23 => [
        'role_name' => 'Driver',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'My Routes', 'route' => '/dashboard/routes'],
                    ['label' => 'Today\'s Schedule', 'route' => '/dashboard/schedule']
                ]
            ],
            [
                'label' => 'Routes',
                'icon' => 'fas fa-route',
                'route' => null,
                'subitems' => [
                    ['label' => 'My Routes', 'route' => '/routes/my-routes'],
                    ['label' => 'Students', 'route' => '/routes/students'],
                    ['label' => 'Stops', 'route' => '/routes/stops']
                ]
            ],
            [
                'label' => 'Vehicle',
                'icon' => 'fas fa-bus',
                'route' => null,
                'subitems' => [
                    ['label' => 'My Vehicle', 'route' => '/vehicle/my-vehicle'],
                    ['label' => 'Fuel Logs', 'route' => '/vehicle/fuel'],
                    ['label' => 'Maintenance', 'route' => '/vehicle/maintenance']
                ]
            ],
            [
                'label' => 'Trip Logs',
                'icon' => 'fas fa-clipboard',
                'route' => null,
                'subitems' => [
                    ['label' => 'Daily Logs', 'route' => '/trips/daily'],
                    ['label' => 'History', 'route' => '/trips/history']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ]
        ]
    ],

    // CHAPLAIN (ID: 24)
    24 => [
        'role_name' => 'Chaplain',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'route' => '/dashboard',
                'subitems' => [
                    ['label' => 'Pastoral Overview', 'route' => '/dashboard/pastoral']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'route' => null,
                'subitems' => [
                    ['label' => 'Counseling Cases', 'route' => '/students/counseling'],
                    ['label' => 'Appointments', 'route' => '/students/appointments']
                ]
            ],
            [
                'label' => 'Chapel',
                'icon' => 'fas fa-church',
                'route' => null,
                'subitems' => [
                    ['label' => 'Services Schedule', 'route' => '/chapel/schedule'],
                    ['label' => 'Attendance', 'route' => '/chapel/attendance']
                ]
            ],
            [
                'label' => 'Welfare',
                'icon' => 'fas fa-hands-helping',
                'route' => null,
                'subitems' => [
                    ['label' => 'Student Support', 'route' => '/welfare/support'],
                    ['label' => 'Referrals', 'route' => '/welfare/referrals']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'route' => null,
                'subitems' => [
                    ['label' => 'Counseling', 'route' => '/reports/counseling'],
                    ['label' => 'Chapel Attendance', 'route' => '/reports/chapel']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'route' => '/communications/messages',
                'subitems' => []
            ]
        ]
    ]
];

// Generate PHP code for dashboards.php
$output = "<?php\n";
$output .= "/**\n";
$output .= " * COMPREHENSIVE DASHBOARD CONFIGURATIONS\n";
$output .= " * Auto-generated from REST API endpoints and role responsibilities\n";
$output .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
$output .= " * \n";
$output .= " * This file contains hierarchical sidebar menus with sub-items\n";
$output .= " * based on actual system functionality and Kenyan school roles\n";
$output .= " */\n\n";
$output .= "return [\n";

foreach ($roles as $role) {
    $roleId = $role['id'];

    if (isset($dashboardDefinitions[$roleId])) {
        $def = $dashboardDefinitions[$roleId];
        $output .= "    // {$def['role_name']} (ID: {$roleId})\n";
        $output .= "    {$roleId} => [\n";
        $output .= "        'role_name' => '{$def['role_name']}',\n";
        $output .= "        'menus' => [\n";

        foreach ($def['menus'] as $menu) {
            $output .= "            [\n";
            $output .= "                'label' => " . var_export($menu['label'], true) . ",\n";
            $output .= "                'icon' => " . var_export($menu['icon'], true) . ",\n";
            $output .= "                'route' => " . ($menu['route'] ? var_export($menu['route'], true) : "null") . ",\n";
            $output .= "                'subitems' => [\n";

            foreach ($menu['subitems'] as $subitem) {
                $output .= "                    ['label' => " . var_export($subitem['label'], true) . ", 'route' => " . var_export($subitem['route'], true) . "],\n";
            }

            $output .= "                ]\n";
            $output .= "            ],\n";
        }
        $output .= "        ]\n";
        $output .= "    ],\n\n";

        echo "✓ Generated dashboard for: {$def['role_name']} (" . count($def['menus']) . " menu items)\n";
    }
}

$output .= "];\n";

// Write to file
$outputFile = __DIR__ . '/../api/includes/dashboards.php';
file_put_contents($outputFile, $output);

echo "\n==============================================\n";
echo "DASHBOARD GENERATION COMPLETE!\n";
echo "==============================================\n";
echo "File saved to: {$outputFile}\n";
echo "Total roles configured: " . count($roles) . "\n";
echo "\nNext steps:\n";
echo "1. Review the generated dashboards.php file\n";
echo "2. Test sidebar display for each role\n";
echo "3. Adjust routes to match your frontend routing\n";
echo "4. Map permissions to menu items as needed\n";
