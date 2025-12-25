<?php
/**
 * COMPREHENSIVE DASHBOARD CONFIGURATIONS
 * Auto-generated with proper route naming
 * Generated on: 2025-12-12 17:04:55
 * 
 * Route format: manage_xxx (matches existing pages)
 * Compatible with home.php?route=xxx pattern
 */

return [
    // System Administrator (ID: 2)
    2 => [
        'role_name' => 'System Administrator',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'system_administrator_dashboard',
                'subitems' => [
                ]
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
                    ['label' => 'Import Students', 'url' => 'import_existing_students'],
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
                    ['label' => 'View Results', 'url' => 'view_results'],
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
                    ['label' => 'Payroll', 'url' => 'manage_payrolls'],
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
                    ['label' => 'Reports', 'url' => 'finance_reports'],
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
                    ['label' => 'Email', 'url' => 'manage_email'],
                ]
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fas fa-boxes',
                'url' => null,
                'subitems' => [
                    ['label' => 'Items', 'url' => 'manage_inventory'],
                    ['label' => 'Requisitions', 'url' => 'manage_requisitions'],
                    ['label' => 'Stock', 'url' => 'manage_stock'],
                ]
            ],
            [
                'label' => 'Transport',
                'icon' => 'fas fa-bus',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Transport', 'url' => 'manage_transport'],
                ]
            ],
            [
                'label' => 'Activities',
                'icon' => 'fas fa-running',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Activities', 'url' => 'manage_activities'],
                ]
            ],
            [
                'label' => 'Boarding',
                'icon' => 'fas fa-bed',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Boarding', 'url' => 'manage_boarding'],
                ]
            ],
            [
                'label' => 'Workflows',
                'icon' => 'fas fa-tasks',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Workflows', 'url' => 'manage_workflows'],
                ]
            ],
            [
                'label' => 'Users & Access',
                'icon' => 'fas fa-users-cog',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Users', 'url' => 'manage_users'],
                    ['label' => 'Roles & Permissions', 'url' => 'manage_roles'],
                ]
            ],
            [
                'label' => 'Settings',
                'icon' => 'fas fa-cogs',
                'url' => null,
                'subitems' => [
                    ['label' => 'System Settings', 'url' => 'system_settings'],
                    ['label' => 'School Settings', 'url' => 'school_settings'],
                    ['label' => 'API Explorer', 'url' => 'api_explorer'],
                ]
            ],
        ]
    ],

    // Director (ID: 3)
    3 => [
        'role_name' => 'Director',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'director_owner_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Finance',
                'icon' => 'fas fa-coins',
                'url' => null,
                'subitems' => [
                    ['label' => 'Financial Reports', 'url' => 'finance_reports'],
                    ['label' => 'Budget Overview', 'url' => 'budget_overview'],
                    ['label' => 'Approvals', 'url' => 'finance_approvals'],
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-line',
                'url' => null,
                'subitems' => [
                    ['label' => 'Financial', 'url' => 'financial_reports'],
                    ['label' => 'Enrollment', 'url' => 'enrollment_reports'],
                    ['label' => 'Performance', 'url' => 'performance_reports'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // School Administrator (ID: 4)
    4 => [
        'role_name' => 'School Administrator',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'school_administrative_officer_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Students', 'url' => 'manage_students'],
                    ['label' => 'Admissions', 'url' => 'manage_students_admissions'],
                    ['label' => 'Attendance', 'url' => 'mark_attendance'],
                ]
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'url' => null,
                'subitems' => [
                    ['label' => 'Classes', 'url' => 'manage_classes'],
                    ['label' => 'Timetable', 'url' => 'manage_timetable'],
                    ['label' => 'Results', 'url' => 'view_results'],
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Staff', 'url' => 'manage_staff'],
                    ['label' => 'Attendance', 'url' => 'staff_attendance'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
            [
                'label' => 'Users',
                'icon' => 'fas fa-users-cog',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Users', 'url' => 'manage_users'],
                ]
            ],
        ]
    ],

    // Headteacher (ID: 5)
    5 => [
        'role_name' => 'Headteacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'headteacher_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Academic',
                'icon' => 'fas fa-graduation-cap',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Academics', 'url' => 'manage_academics'],
                    ['label' => 'Classes', 'url' => 'manage_classes'],
                    ['label' => 'Timetable', 'url' => 'manage_timetable'],
                    ['label' => 'Results', 'url' => 'view_results'],
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Students', 'url' => 'manage_students'],
                    ['label' => 'Admissions', 'url' => 'manage_students_admissions'],
                    ['label' => 'Performance', 'url' => 'student_performance'],
                ]
            ],
            [
                'label' => 'Staff',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Staff', 'url' => 'manage_staff'],
                    ['label' => 'Performance', 'url' => 'staff_performance'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Deputy Head - Academic (ID: 6)
    6 => [
        'role_name' => 'Deputy Head - Academic',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'deputy_headteacher_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Timetable',
                'icon' => 'fas fa-calendar-alt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Timetable', 'url' => 'manage_timetable'],
                ]
            ],
            [
                'label' => 'Assessments',
                'icon' => 'fas fa-file-alt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Assessments', 'url' => 'manage_assessments'],
                    ['label' => 'Add Results', 'url' => 'add_results'],
                    ['label' => 'View Results', 'url' => 'view_results'],
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Students', 'url' => 'manage_students'],
                ]
            ],
            [
                'label' => 'Classes',
                'icon' => 'fas fa-door-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Classes', 'url' => 'manage_classes'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Class Teacher (ID: 7)
    7 => [
        'role_name' => 'Class Teacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'class_teacher_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'My Classes',
                'icon' => 'fas fa-door-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'My Classes', 'url' => 'myclasses'],
                    ['label' => 'Class Attendance', 'url' => 'mark_attendance'],
                ]
            ],
            [
                'label' => 'Assessments',
                'icon' => 'fas fa-file-alt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Assessments', 'url' => 'manage_assessments'],
                    ['label' => 'Enter Results', 'url' => 'submit_results'],
                    ['label' => 'View Results', 'url' => 'view_results'],
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Lesson Plans', 'url' => 'manage_lesson_plans'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Subject Teacher (ID: 8)
    8 => [
        'role_name' => 'Subject Teacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'subject_teacher_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'My Teaching',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'My Classes', 'url' => 'myclasses'],
                ]
            ],
            [
                'label' => 'Assessments',
                'icon' => 'fas fa-file-alt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Enter Results', 'url' => 'submit_results'],
                    ['label' => 'View Results', 'url' => 'view_results'],
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'My Plans', 'url' => 'manage_lesson_plans'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Intern/Student Teacher (ID: 9)
    9 => [
        'role_name' => 'Intern/Student Teacher',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'intern_student_teacher_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'My Classes',
                'icon' => 'fas fa-chalkboard-teacher',
                'url' => null,
                'subitems' => [
                    ['label' => 'View Classes', 'url' => 'myclasses'],
                ]
            ],
            [
                'label' => 'Lesson Plans',
                'icon' => 'fas fa-book-open',
                'url' => null,
                'subitems' => [
                    ['label' => 'View Plans', 'url' => 'manage_lesson_plans'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Accountant (ID: 10)
    10 => [
        'role_name' => 'Accountant',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'school_accountant_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Fees',
                'icon' => 'fas fa-receipt',
                'url' => null,
                'subitems' => [
                    ['label' => 'Fee Structure', 'url' => 'manage_fees'],
                    ['label' => 'Student Fees', 'url' => 'student_fees'],
                ]
            ],
            [
                'label' => 'Payments',
                'icon' => 'fas fa-money-bill-wave',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Payments', 'url' => 'manage_payments'],
                ]
            ],
            [
                'label' => 'Payroll',
                'icon' => 'fas fa-wallet',
                'url' => null,
                'subitems' => [
                    ['label' => 'Staff Payroll', 'url' => 'payroll'],
                    ['label' => 'Manage Payroll', 'url' => 'manage_payrolls'],
                ]
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-line',
                'url' => null,
                'subitems' => [
                    ['label' => 'Financial Reports', 'url' => 'finance_reports'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Inventory Manager (ID: 14)
    14 => [
        'role_name' => 'Inventory Manager',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'store_manager_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fas fa-boxes',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Inventory', 'url' => 'manage_inventory'],
                    ['label' => 'Stock Management', 'url' => 'manage_stock'],
                    ['label' => 'Requisitions', 'url' => 'manage_requisitions'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Cateress (ID: 16)
    16 => [
        'role_name' => 'Cateress',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'catering_manager_cook_lead_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Menu & Food',
                'icon' => 'fas fa-utensils',
                'url' => null,
                'subitems' => [
                    ['label' => 'Menu Planning', 'url' => 'menu_planning'],
                    ['label' => 'Food Store', 'url' => 'food_store'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Boarding Master (ID: 18)
    18 => [
        'role_name' => 'Boarding Master',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'matron_housemother_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Boarding',
                'icon' => 'fas fa-bed',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Boarding', 'url' => 'manage_boarding'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Talent Development (ID: 21)
    21 => [
        'role_name' => 'Talent Development',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'hod_talent_development_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Activities',
                'icon' => 'fas fa-running',
                'url' => null,
                'subitems' => [
                    ['label' => 'Manage Activities', 'url' => 'manage_activities'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Driver (ID: 23)
    23 => [
        'role_name' => 'Driver',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'driver_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Transport',
                'icon' => 'fas fa-bus',
                'url' => null,
                'subitems' => [
                    ['label' => 'My Routes', 'url' => 'my_routes'],
                    ['label' => 'My Vehicle', 'url' => 'my_vehicle'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Chaplain (ID: 24)
    24 => [
        'role_name' => 'Chaplain',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'school_counselor_chaplain_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Counseling',
                'icon' => 'fas fa-hands-helping',
                'url' => null,
                'subitems' => [
                    ['label' => 'Student Counseling', 'url' => 'student_counseling'],
                    ['label' => 'Chapel Services', 'url' => 'chapel_services'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

    // Deputy Head - Discipline (ID: 63)
    63 => [
        'role_name' => 'Deputy Head - Discipline',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'deputy_headteacher_dashboard',
                'subitems' => [
                ]
            ],
            [
                'label' => 'Students',
                'icon' => 'fas fa-user-graduate',
                'url' => null,
                'subitems' => [
                    ['label' => 'All Students', 'url' => 'manage_students'],
                    ['label' => 'Admissions', 'url' => 'manage_students_admissions'],
                    ['label' => 'Discipline', 'url' => 'student_discipline'],
                ]
            ],
            [
                'label' => 'Attendance',
                'icon' => 'fas fa-clipboard-check',
                'url' => null,
                'subitems' => [
                    ['label' => 'Mark Attendance', 'url' => 'mark_attendance'],
                    ['label' => 'View Attendance', 'url' => 'view_attendance'],
                ]
            ],
            [
                'label' => 'Communications',
                'icon' => 'fas fa-comments',
                'url' => null,
                'subitems' => [
                    ['label' => 'Messages', 'url' => 'manage_communications'],
                ]
            ],
        ]
    ],

];
