<?php
/**
 * PROPER DASHBOARD GENERATOR
 * 
 * Generates dashboards.php with correct route naming matching your existing pattern:
 * - Routes use underscore format: manage_students, manage_staff, etc.
 * - Sub-items route to actual existing pages
 * - Compatible with home.php?route=xxx pattern
 * - Creates necessary page files
 */

$db = new PDO("mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=KingsWayAcademy", 'root', 'admin123');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "==============================================\n";
echo "PROPER DASHBOARD GENERATOR\n";
echo "==============================================\n\n";

// Fetch all roles
$rolesStmt = $db->query("SELECT id, name, description FROM roles ORDER BY id");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($roles) . " roles\n\n";

/**
 * COMPREHENSIVE DASHBOARD DEFINITIONS
 * Routes match existing page naming convention
 */
$dashboardDefinitions = [
    // SYSTEM ADMINISTRATOR (ID: 2)
    2 => [
        'role_name' => 'System Administrator',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'system_administrator_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Students', 'url' => 'manage_students'],
                    ['label' => 'Admissions', 'url' => 'manage_students_admissions'],
                    ['label' => 'Attendance', 'url' => 'mark_attendance'],
                    ['label' => 'ID Cards', 'url' => 'student_id_cards'],
                    ['label' => 'Import Students', 'url' => 'import_existing_students']
                ]
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Academics', 'url' => 'manage_academics'],
                    ['label' => 'Classes', 'url' => 'manage_classes'],
                    ['label' => 'Subjects', 'url' => 'manage_subjects'],
                    ['label' => 'Timetable', 'url' => 'manage_timetable'],
                    ['label' => 'Assessments', 'url' => 'manage_assessments'],
                    ['label' => 'Lesson Plans', 'url' => 'manage_lesson_plans'],
                    ['label' => 'Add Results', 'url' => 'add_results'],
                    ['label' => 'View Results', 'url' => 'view_results']
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Staff', 'url' => 'manage_staff'],
                    ['label' => 'Teachers', 'url' => 'manage_teachers'],
                    ['label' => 'Non-Teaching Staff', 'url' => 'manage_non_teaching_staff'],
                    ['label' => 'Payroll', 'url' => 'manage_payrolls']
                ]
            ],
            [
                'label' => 'Finance',
                'icon' => 'fas fa-coins',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Finance', 'url' => 'manage_finance'],
                    ['label' => 'Fee Structure', 'url' => 'manage_fees'],
                    ['label' => 'Payments', 'url' => 'manage_payments'],
                    ['label' => 'Payroll', 'url' => 'payroll'],
                    ['label' => 'Expenses', 'url' => 'manage_expenses'],
                    ['label' => 'Reports', 'url' => 'finance_reports']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                    ['label' => 'Announcements', 'url' => 'manage_announcements'],
                    ['label' => 'SMS', 'url' => 'manage_sms'],
                    ['label' => 'Email', 'url' => 'manage_email']
                ]
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fas fa-boxes',
                'url' => null,
                'subitems' => [
                    ['label' => 'Items', 'url' => 'manage_inventory'],
                    ['label' => 'Requisitions', 'url' => 'manage_requisitions'],
                    ['label' => 'Stock', 'url' => 'manage_stock']
                ]
            ],
            [
                'label' => 'Transport',
                'icon' => 'fas fa-bus',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Transport', 'url' => 'manage_transport']
                ]
            ],
            [
                'label' => 'Activities',
                'icon' => 'fas fa-running',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Activities', 'url' => 'manage_activities']
                ]
            ],
            [
                'label' => 'Boarding',
                'icon' => 'fas fa-bed',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Boarding', 'url' => 'manage_boarding']
                ]
            ],
            [
                'label' => 'Workflows',
                'icon' => 'fas fa-tasks',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Workflows', 'url' => 'manage_workflows']
                ]
            ],
            [
                'label' => 'Users & Access',
                'icon' => 'fas fa-users-cog',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Users', 'url' => 'manage_users'],
                    ['label' => 'Roles & Permissions', 'url' => 'manage_roles']
                ]
            ],
            [
                'label' => 'Settings',
                'icon' => 'fas fa-cogs',
                'url' => null,
                'subitems' => [
                    ['label' => 'System Settings', 'url' => 'system_settings'],
                    ['label' => 'School Settings', 'url' => 'school_settings'],
                    ['label' => 'API Explorer', 'url' => 'api_explorer']
                ]
            ]
        ]
    ],

    // DIRECTOR (ID: 3)
    3 => [
        'role_name' => 'Director',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'director_owner_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Students', 'url' => 'manage_students'],
                    ['label' => 'Admissions', 'url' => 'manage_students_admissions'],
                    ['label' => 'Attendance', 'url' => 'mark_attendance'],
                    ['label' => 'Discipline', 'url' => 'student_discipline'],
                    ['label' => 'Transfers', 'url' => 'manage_transfers'],
                    ['label' => 'Medical Records', 'url' => 'student_medical_records']
                ]
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'url' => null,
                'subitems' => [
                    ['label' => 'Classes & Streams', 'url' => 'manage_classes'],
                    ['label' => 'Timetable', 'url' => 'manage_timetable'],
                    ['label' => 'Exams', 'url' => 'manage_exams'],
                    ['label' => 'Assessments', 'url' => 'manage_assessments'],
                    ['label' => 'Results', 'url' => 'view_results'],
                    ['label' => 'Curriculum', 'url' => 'manage_curriculum']
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'Teaching Staff', 'url' => 'manage_teachers'],
                    ['label' => 'Non-Teaching Staff', 'url' => 'manage_non_teaching_staff'],
                    ['label' => 'Staff Attendance', 'url' => 'staff_attendance'],
                    ['label' => 'Leave Management', 'url' => 'manage_leaves'],
                    ['label' => 'Performance Reviews', 'url' => 'staff_performance'],
                    ['label' => 'Payroll', 'url' => 'manage_payrolls']
                ]
            ],
            [
                'label' => 'Finance',
                'icon' => 'fas fa-coins',
                'url' => null,
                'subitems' => [
                    ['label' => 'Financial Overview', 'url' => 'manage_finance'],
                    ['label' => 'Fee Management', 'url' => 'manage_fees'],
                    ['label' => 'Student Payments', 'url' => 'manage_payments'],
                    ['label' => 'Expenses', 'url' => 'manage_expenses'],
                    ['label' => 'Budget', 'url' => 'budget_overview'],
                    ['label' => 'Approvals', 'url' => 'finance_approvals']
                ]
            ],
            [
                'label' => 'Inventory & Uniforms',
                'icon' => 'fas fa-boxes',
                'url' => null,
                'subitems' => [
                    ['label' => 'Inventory Items', 'url' => 'manage_inventory'],
                    ['label' => 'Uniform Sales', 'url' => 'uniform_sales'],
                    ['label' => 'Stock Levels', 'url' => 'view_stock'],
                    ['label' => 'Requisitions', 'url' => 'manage_requisitions'],
                    ['label' => 'Stock Movements', 'url' => 'inventory_transactions']
                ]
            ],
            [
                'label' => 'Transport',
                'icon' => 'fas fa-bus',
                'url' => null,
                'subitems' => [
                    ['label' => 'Routes', 'url' => 'manage_routes'],
                    ['label' => 'Vehicles', 'url' => 'manage_vehicles'],
                    ['label' => 'Drivers', 'url' => 'manage_drivers'],
                    ['label' => 'Trip Logs', 'url' => 'manage_trip_logs']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                    ['label' => 'Announcements', 'url' => 'manage_announcements'],
                    ['label' => 'SMS', 'url' => 'manage_sms'],
                    ['label' => 'Emails', 'url' => 'manage_email'],
                    ['label' => 'Email Templates', 'url' => 'manage_email_templates']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-line',
                'url' => null,
                'subitems' => [
                    ['label' => 'Financial Reports', 'url' => 'financial_reports'],
                    ['label' => 'Enrollment Reports', 'url' => 'enrollment_reports'],
                    ['label' => 'Academic Performance', 'url' => 'performance_reports'],
                    ['label' => 'Attendance Reports', 'url' => 'attendance_reports'],
                    ['label' => 'Staff Reports', 'url' => 'staff_reports'],
                    ['label' => 'Inventory Reports', 'url' => 'inventory_reports']
                ]
            ],
            [
                'label' => 'Activities & Events',
                'icon' => 'fas fa-calendar',
                'url' => null,
                'subitems' => [
                    ['label' => 'Activities', 'url' => 'manage_activities'],
                    ['label' => 'Events', 'url' => 'manage_events'],
                    ['label' => 'Sports', 'url' => 'manage_sports'],
                    ['label' => 'Clubs', 'url' => 'manage_clubs']
                ]
            ],
            [
                'label' => 'Boarding Management',
                'icon' => 'fas fa-bed',
                'url' => null,
                'subitems' => [
                    ['label' => 'Boarding Houses', 'url' => 'manage_boarding'],
                    ['label' => 'Room Assignments', 'url' => 'manage_room_assignments'],
                    ['label' => 'Boarding Rules', 'url' => 'manage_boarding_rules'],
                    ['label' => 'Health & Welfare', 'url' => 'boarding_health']
                ]
            ],
            [
                'label' => 'School Settings',
                'icon' => 'fas fa-cog',
                'url' => null,
                'subitems' => [
                    ['label' => 'School Information', 'url' => 'school_settings'],
                    ['label' => 'Academic Calendar', 'url' => 'manage_academic_calendar'],
                    ['label' => 'Terms & Sessions', 'url' => 'manage_terms'],
                    ['label' => 'Configuration', 'url' => 'system_configuration']
                ]
            ]
        ]
    ],

    // SCHOOL ADMINISTRATOR (ID: 4)
    4 => [
        'role_name' => 'School Administrator',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'school_administrative_officer_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Students', 'url' => 'manage_students'],
                    ['label' => 'Admissions', 'url' => 'manage_students_admissions'],
                    ['label' => 'Attendance', 'url' => 'mark_attendance']
                ]
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'url' => null,
                'subitems' => [
                    ['label' => 'Classes', 'url' => 'manage_classes'],
                    ['label' => 'Timetable', 'url' => 'manage_timetable'],
                    ['label' => 'Results', 'url' => 'view_results']
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Staff', 'url' => 'manage_staff'],
                    ['label' => 'Attendance', 'url' => 'staff_attendance']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
            ],
            [
                'label' => 'Users',
                'icon' => 'fas fa-users-cog',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Users', 'url' => 'manage_users']
                ]
            ]
        ]
    ],

    // HEADTEACHER (ID: 5)
    5 => [
        'role_name' => 'Headteacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'headteacher_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Academics', 'url' => 'manage_academics'],
                    ['label' => 'Classes', 'url' => 'manage_classes'],
                    ['label' => 'Timetable', 'url' => 'manage_timetable'],
                    ['label' => 'Results', 'url' => 'view_results']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Students', 'url' => 'manage_students'],
                    ['label' => 'Admissions', 'url' => 'manage_students_admissions'],
                    ['label' => 'Performance', 'url' => 'student_performance']
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Staff', 'url' => 'manage_staff'],
                    ['label' => 'Performance', 'url' => 'staff_performance']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
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
                'url' => 'deputy_head_academic_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Timetable',
                'icon' => 'fas fa-calendar-alt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Timetable', 'url' => 'manage_timetable']
                ]
            ],
            [
                'label' => 'Assessments',
                'icon' => 'fas fa-file-alt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Assessments', 'url' => 'manage_assessments'],
                    ['label' => 'Add Results', 'url' => 'add_results'],
                    ['label' => 'View Results', 'url' => 'view_results']
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Students', 'url' => 'manage_students']
                ]
            ],
            [
                'label' => 'Classes',
                'icon' => 'fas fa-door-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Classes', 'url' => 'manage_classes']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
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
                'url' => 'deputy_head_discipline_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Students', 'url' => 'manage_students'],
                    ['label' => 'Admissions', 'url' => 'manage_students_admissions'],
                    ['label' => 'Discipline', 'url' => 'student_discipline']
                ]
            ],
            [
                'label' => 'Attendance',
                'icon' => 'fas fa-clipboard-check',
                'url' => null,
                'subitems' => [
                    ['label' => 'Mark Attendance', 'url' => 'mark_attendance'],
                    ['label' => 'View Attendance', 'url' => 'view_attendance']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
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
                'url' => 'class_teacher_dashboard',
                'subitems' => []
            ],
            // 'My Classes' menu removed for Class Teacher (id: 7) by request

            [
                'label' => 'Assessments',
                'icon' => 'fas fa-file-alt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Assessments', 'url' => 'manage_assessments'],
                    ['label' => 'Enter Results', 'url' => 'submit_results'],
                    ['label' => 'View Results', 'url' => 'view_results']
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Lesson Plans', 'url' => 'manage_lesson_plans']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
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
                'url' => 'subject_teacher_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'My Teaching',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'My Classes', 'url' => 'myclasses']
                ]
            ],
            [
                'label' => 'Assessments',
                'icon' => 'fas fa-file-alt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Enter Results', 'url' => 'submit_results'],
                    ['label' => 'View Results', 'url' => 'view_results']
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'My Plans', 'url' => 'manage_lesson_plans']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
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
                'url' => 'intern_student_teacher_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'My Classes',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'View Classes', 'url' => 'myclasses']
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'View Plans', 'url' => 'manage_lesson_plans']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
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
                'url' => 'school_accountant_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Fees',
                'icon' => 'fas fa-receipt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Fee Structure', 'url' => 'manage_fees'],
                    ['label' => 'Student Fees', 'url' => 'student_fees']
                ]
            ],
            [
                'label' => 'Payments',
                'icon' => 'fas fa-money-bill-wave',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Payments', 'url' => 'manage_payments']
                ]
            ],
            [
                'label' => 'Payroll',
                'icon' => 'fas fa-wallet',
                'url' => null,
                'subitems' => [
                    ['label' => 'Staff Payroll', 'url' => 'payroll'],
                    ['label' => 'Manage Payroll', 'url' => 'manage_payrolls']
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-line',
                'url' => null,
                'subitems' => [
                    ['label' => 'Financial Reports', 'url' => 'finance_reports']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
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
                'url' => 'store_manager_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fas fa-boxes',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Inventory', 'url' => 'manage_inventory'],
                    ['label' => 'Stock Management', 'url' => 'manage_stock'],
                    ['label' => 'Requisitions', 'url' => 'manage_requisitions']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
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
                'url' => 'catering_manager_cook_lead_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Menu & Food',
                'icon' => 'fas fa-utensils',
                'url' => null,
                'subitems' => [
                    ['label' => 'Menu Planning', 'url' => 'menu_planning'],
                    ['label' => 'Food Store', 'url' => 'food_store']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
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
                'url' => 'matron_housemother_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Boarding',
                'icon' => 'fas fa-bed',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Boarding', 'url' => 'manage_boarding']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
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
                'url' => 'hod_talent_development_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Activities',
                'icon' => 'fas fa-running',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Activities', 'url' => 'manage_activities']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
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
                'url' => 'driver_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Transport',
                'icon' => 'fas fa-bus',
                'url' => null,
                'subitems' => [
                    ['label' => 'My Routes', 'url' => 'my_routes'],
                    ['label' => 'My Vehicle', 'url' => 'my_vehicle']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
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
                'url' => 'school_counselor_chaplain_dashboard',
                'subitems' => []
            ],
            [
                'label' => 'Counseling',
                'icon' => 'fas fa-hands-helping',
                'url' => null,
                'subitems' => [
                    ['label' => 'Student Counseling', 'url' => 'student_counseling'],
                    ['label' => 'Chapel Services', 'url' => 'chapel_services']
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications']
                ]
            ]
        ]
    ]
];

// Generate PHP code for dashboards.php
$output = "<?php\n";
$output .= "/**\n";
$output .= " * COMPREHENSIVE DASHBOARD CONFIGURATIONS\n";
$output .= " * Auto-generated with proper route naming\n";
$output .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
$output .= " * \n";
$output .= " * Route format: manage_xxx (matches existing pages)\n";
$output .= " * Compatible with home.php?route=xxx pattern\n";
$output .= " */\n\n";
$output .= "return [\n";

$missingPages = [];

foreach ($roles as $role) {
    $roleId = $role['id'];

    if (isset($dashboardDefinitions[$roleId])) {
        $def = $dashboardDefinitions[$roleId];

        $output .= "    // {$def['role_name']} (ID: {$roleId})\n";
        $output .= "    {$roleId} => [\n";
        $output .= "        'role_name' => " . var_export($def['role_name'], true) . ",\n";
        $output .= "        'menus' => [\n";

        foreach ($def['menus'] as $menu) {
            $output .= "            [\n";
            $output .= "                'label' => " . var_export($menu['label'], true) . ",\n";
            $output .= "                'icon' => " . var_export($menu['icon'], true) . ",\n";
            $output .= "                'url' => " . ($menu['url'] ? var_export($menu['url'], true) : "null") . ",\n";
            $output .= "                'subitems' => [\n";

            foreach ($menu['subitems'] as $subitem) {
                $output .= "                    ['label' => " . var_export($subitem['label'], true) . ", 'url' => " . var_export($subitem['url'], true) . "],\n";

                // Track missing pages
                $pagePath = __DIR__ . "/../pages/{$subitem['url']}.php";
                if (!file_exists($pagePath)) {
                    $missingPages[$subitem['url']] = $subitem['label'];
                }
            }

            // Check main route too
            if ($menu['url']) {
                $dashPath = __DIR__ . "/../components/dashboards/{$menu['url']}.php";
                if (!file_exists($dashPath)) {
                    $missingPages[$menu['url']] = $menu['label'] . ' Dashboard';
                }
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
echo "Total roles configured: " . count($roles) . "\n\n";

// Report missing pages
if (!empty($missingPages)) {
    echo "MISSING PAGES TO CREATE:\n";
    echo "========================\n";
    $missing_file = __DIR__ . '/missing_pages.txt';
    file_put_contents($missing_file, "");
    foreach ($missingPages as $route => $label) {
        echo "- {$route}.php ({$label})\n";
        file_put_contents($missing_file, "{$route}.php\n", FILE_APPEND);
    }
    echo "\nList saved to: {$missing_file}\n";
}
