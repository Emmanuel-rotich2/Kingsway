<?php
return ([
    'admin' => [
        [
            'label' => 'Dashboard',
            'icon' => 'bi bi-speedometer2',
            'url' => 'admin_dashboard'
        ],
        [
            'label' => 'User Management',
            'icon' => 'bi bi-people',
            'subitems' => [
                ['label' => 'Manage System Users', 'icon' => 'bi bi-person', 'url' => 'manage_users'],
                ['label' => 'Manage Students', 'icon' => 'bi bi-person', 'url' => 'manage_students'],
                ['label' => 'Roles & Permissions', 'icon' => 'bi bi-shield-lock', 'url' => 'manage_roles'],
                ['label' => 'Audit Logs', 'icon' => 'bi bi-journal-text', 'url' => 'audit_logs'],
            ]
        ],
        [
            'label' => 'System Settings',
            'icon' => 'bi bi-gear',
            'subitems' => [
                ['label' => 'Academic Terms', 'icon' => 'bi bi-calendar3', 'url' => 'academic_terms'],
                ['label' => 'School Levels', 'icon' => 'bi bi-diagram-3', 'url' => 'school_levels'],
                ['label' => 'Departments', 'icon' => 'bi bi-building', 'url' => 'departments'],
                ['label' => 'Fee Structures', 'icon' => 'bi bi-cash-stack', 'url' => 'fee_structures'],
                ['label' => 'Communication Templates', 'icon' => 'bi bi-envelope', 'url' => 'communication_templates'],
            ]
        ],
        [
            'label' => 'Inventory',
            'icon' => 'bi bi-box-seam',
            'subitems' => [
                ['label' => 'Items', 'icon' => 'bi bi-box', 'url' => 'inventory_items'],
                ['label' => 'Categories', 'icon' => 'bi bi-tags', 'url' => 'inventory_categories'],
                ['label' => 'Stock Management', 'icon' => 'bi bi-boxes', 'url' => 'stock_management'],
            ]
        ],
        [
            'label' => 'Reports',
            'icon' => 'bi bi-file-earmark-text',
            'subitems' => [
                ['label' => 'System Reports', 'icon' => 'bi bi-file-text', 'url' => 'system_reports'],
                ['label' => 'Audit Reports', 'icon' => 'bi bi-file-ruled', 'url' => 'audit_reports'],
            ]
        ]
    ],

    'teacher' => [
        [
            'label' => 'Dashboard',
            'icon' => 'bi bi-speedometer2',
            'url' => 'teacher_dashboard'
        ],
        [
            'label' => 'Class Management',
            'icon' => 'bi bi-mortarboard',
            'subitems' => [
                ['label' => 'My Classes', 'icon' => 'bi bi-mortarboard', 'url' => 'myclasses'],
                ['label' => 'Mark Attendance', 'icon' => 'bi bi-calendar-check', 'url' => 'mark_attendance'],
                ['label' => 'Enter Results', 'icon' => 'bi bi-file-text', 'url' => 'enter_results'],
                ['label' => 'View Results', 'icon' => 'bi bi-graph-up', 'url' => 'view_results'],
            ]
        ],
        [
            'label' => 'Academics',
            'icon' => 'bi bi-book',
            'subitems' => [
                ['label' => 'Lesson Plans', 'icon' => 'bi bi-journal-text', 'url' => 'lesson_plans'],
                ['label' => 'Assignments', 'icon' => 'bi bi-file-earmark-text', 'url' => 'assignments'],
                ['label' => 'Assessments', 'icon' => 'bi bi-clipboard-check', 'url' => 'assessments'],
                ['label' => 'Grade Book', 'icon' => 'bi bi-journal-bookmark', 'url' => 'grade_book'],
            ]
        ],
        [
            'label' => 'Communication',
            'icon' => 'bi bi-chat-dots',
            'subitems' => [
                ['label' => 'Parent Messages', 'icon' => 'bi bi-envelope', 'url' => 'parent_messages'],
                ['label' => 'Student Notes', 'icon' => 'bi bi-journal-text', 'url' => 'student_notes'],
                ['label' => 'Announcements', 'icon' => 'bi bi-megaphone', 'url' => 'announcements'],
            ]
        ]
    ],

    'registrar' => [
        [
            'label' => 'Dashboard',
            'icon' => 'bi bi-speedometer2',
            'url' => 'admissions_dashboard'
        ],
        [
            'label' => 'Admissions',
            'icon' => 'bi bi-person-plus',
            'subitems' => [
                ['label' => 'New Applications', 'icon' => 'bi bi-file-plus', 'url' => 'new_applications'],
                ['label' => 'Process Applications', 'icon' => 'bi bi-check2-square', 'url' => 'process_applications'],
                ['label' => 'Admission Letters', 'icon' => 'bi bi-envelope-paper', 'url' => 'admission_letters'],
            ]
        ],
        [
            'label' => 'Student Records',
            'icon' => 'bi bi-folder-check',
            'subitems' => [
                ['label' => 'Student Files', 'icon' => 'bi bi-file-earmark-person', 'url' => 'student_files'],
                ['label' => 'Academic Records', 'icon' => 'bi bi-mortarboard', 'url' => 'academic_records'],
                ['label' => 'Report Cards', 'icon' => 'bi bi-file-text', 'url' => 'report_cards'],
                ['label' => 'Transcripts', 'icon' => 'bi bi-file-earmark-text', 'url' => 'transcripts'],
            ]
        ],
        [
            'label' => 'Clearance',
            'icon' => 'bi bi-check2-circle',
            'subitems' => [
                ['label' => 'Pending Clearance', 'icon' => 'bi bi-hourglass', 'url' => 'pending_clearance'],
                ['label' => 'Cleared Students', 'icon' => 'bi bi-check2-all', 'url' => 'cleared_students'],
                ['label' => 'Certificates', 'icon' => 'bi bi-award', 'url' => 'certificates'],
            ]
        ],
        [
            'label' => 'Reports',
            'icon' => 'bi bi-file-earmark-text',
            'subitems' => [
                ['label' => 'Admission Reports', 'icon' => 'bi bi-graph-up', 'url' => 'admission_reports'],
                ['label' => 'Student Statistics', 'icon' => 'bi bi-bar-chart', 'url' => 'student_statistics'],
                ['label' => 'Clearance Reports', 'icon' => 'bi bi-file-check', 'url' => 'clearance_reports'],
            ]
        ]
    ],

    'headteacher' => [
        [
            'label' => 'Dashboard',
            'icon' => 'bi bi-speedometer2',
            'url' => 'headteacher_dashboard'
        ],
        [
            'label' => 'Academic',
            'icon' => 'bi bi-book',
            'subitems' => [
                ['label' => 'Curriculum Units', 'icon' => 'bi bi-journal-bookmark', 'url' => 'curriculum_units'],
                ['label' => 'Lesson Plans', 'icon' => 'bi bi-journal-text', 'url' => 'lesson_plans'],
                ['label' => 'Academic Terms', 'icon' => 'bi bi-calendar3', 'url' => 'academic_terms'],
                ['label' => 'Scheme of Work', 'icon' => 'bi bi-file-text', 'url' => 'scheme_of_work'],
                ['label' => 'Lesson Observations', 'icon' => 'bi bi-eye', 'url' => 'lesson_observations'],
            ]
        ],
        [
            'label' => 'Staff',
            'icon' => 'bi bi-person-badge',
            'subitems' => [
                ['label' => 'Teaching Staff', 'icon' => 'bi bi-person-video3', 'url' => 'manage_teachers'],
                ['label' => 'Non-Teaching Staff', 'icon' => 'bi bi-person-workspace', 'url' => 'manage_non_teaching_staff'],
                ['label' => 'Staff Attendance', 'icon' => 'bi bi-calendar-check', 'url' => 'staff_attendance'],
                ['label' => 'Staff Leave', 'icon' => 'bi bi-calendar-x', 'url' => 'staff_leave'],
                ['label' => 'Staff Performance', 'icon' => 'bi bi-graph-up', 'url' => 'staff_performance'],
            ]
        ],
        [
            'label' => 'Students',
            'icon' => 'bi bi-mortarboard',
            'subitems' => [
                ['label' => 'Student Admission', 'icon' => 'bi bi-person-plus', 'url' => 'manage_students_admissions'],
                ['label' => 'Student Records', 'icon' => 'bi bi-file-person', 'url' => 'manage_students'],
                ['label' => 'Class Assignment', 'icon' => 'bi bi-diagram-2', 'url' => 'class_assignment'],
                ['label' => 'Student Transfers', 'icon' => 'bi bi-arrow-left-right', 'url' => 'student_transfers'],
            ]
        ],
        [
            'label' => 'Reports',
            'icon' => 'bi bi-file-earmark-text',
            'subitems' => [
                ['label' => 'Academic Reports', 'icon' => 'bi bi-file-text', 'url' => 'academic_reports'],
                ['label' => 'Staff Reports', 'icon' => 'bi bi-file-person', 'url' => 'staff_reports'],
                ['label' => 'Student Reports', 'icon' => 'bi bi-file-earmark-person', 'url' => 'student_reports'],
            ]
        ]
    ],

    'accountant' => [
        [
            'label' => 'Dashboard',
            'icon' => 'bi bi-speedometer2',
            'url' => 'accounts_dashboard'
        ],
        [
            'label' => 'Fee Management',
            'icon' => 'bi bi-cash-stack',
            'subitems' => [
                ['label' => 'Fee Structures', 'icon' => 'bi bi-list-check', 'url' => 'fee_structures'],
                ['label' => 'Student Fees', 'icon' => 'bi bi-cash', 'url' => 'student_fees'],
                ['label' => 'Fee Collection', 'icon' => 'bi bi-collection', 'url' => 'fee_collection'],
                ['label' => 'Payment Records', 'icon' => 'bi bi-receipt', 'url' => 'payment_records'],
                ['label' => 'Bank Transactions', 'icon' => 'bi bi-bank', 'url' => 'bank_transactions'],
                ['label' => 'MPesa Transactions', 'icon' => 'bi bi-phone', 'url' => 'mpesa_transactions'],
            ]
        ],
        [
            'label' => 'Payroll',
            'icon' => 'bi bi-credit-card',
            'subitems' => [
                ['label' => 'Staff Payroll', 'icon' => 'bi bi-cash-coin', 'url' => 'manage_payrolls'],
                ['label' => 'Allowances', 'icon' => 'bi bi-plus-circle', 'url' => 'allowances'],
                ['label' => 'Deductions', 'icon' => 'bi bi-dash-circle', 'url' => 'deductions'],
                ['label' => 'Payslip Generation', 'icon' => 'bi bi-file-earmark-text', 'url' => 'generate_payslips'],
            ]
        ],
        [
            'label' => 'Financial Reports',
            'icon' => 'bi bi-file-earmark-spreadsheet',
            'subitems' => [
                ['label' => 'Fee Collection', 'icon' => 'bi bi-graph-up', 'url' => 'fee_collection_reports'],
                ['label' => 'Payment Reports', 'icon' => 'bi bi-cash-coin', 'url' => 'payment_reports'],
                ['label' => 'Expense Reports', 'icon' => 'bi bi-graph-down', 'url' => 'expense_reports'],
                ['label' => 'Financial Statements', 'icon' => 'bi bi-file-spreadsheet', 'url' => 'financial_statements'],
                ['label' => 'Payroll Reports', 'icon' => 'bi bi-file-earmark-ruled', 'url' => 'payroll_reports'],
            ]
        ]
    ],

    'transport' => [

        [
            'label' => 'Fleet Management',
            'icon' => 'bi bi-truck',
            'subitems' => [
                ['label' => 'Vehicles', 'icon' => 'bi bi-truck-flatbed', 'url' => 'vehicles'],
                ['label' => 'Drivers', 'icon' => 'bi bi-person-badge', 'url' => 'drivers'],
                ['label' => 'Maintenance', 'icon' => 'bi bi-tools', 'url' => 'maintenance'],
                ['label' => 'Fuel Logs', 'icon' => 'bi bi-fuel-pump', 'url' => 'fuel_logs'],
            ]
        ],
        [
            'label' => 'Routes',
            'icon' => 'bi bi-map',
            'subitems' => [
                ['label' => 'Route Management', 'icon' => 'bi bi-geo-alt', 'url' => 'route_management'],
                ['label' => 'Stops', 'icon' => 'bi bi-sign-stop', 'url' => 'stops'],
                ['label' => 'Student Routes', 'icon' => 'bi bi-people', 'url' => 'student_routes'],
            ]
        ],
        [
            'label' => 'Scheduling',
            'icon' => 'bi bi-calendar4-week',
            'subitems' => [
                ['label' => 'Transport Schedule', 'icon' => 'bi bi-calendar-check', 'url' => 'transport_schedule'],
                ['label' => 'Driver Assignment', 'icon' => 'bi bi-person-check', 'url' => 'driver_assignment'],
                ['label' => 'Vehicle Assignment', 'icon' => 'bi bi-truck-front', 'url' => 'vehicle_assignment'],
            ]
        ],
        [
            'label' => 'Reports',
            'icon' => 'bi bi-file-earmark-text',
            'subitems' => [
                ['label' => 'Vehicle Reports', 'icon' => 'bi bi-file-text', 'url' => 'vehicle_reports'],
                ['label' => 'Route Reports', 'icon' => 'bi bi-file-earmark-text', 'url' => 'route_reports'],
                ['label' => 'Maintenance Reports', 'icon' => 'bi bi-file-ruled', 'url' => 'maintenance_reports'],
                ['label' => 'Fuel Reports', 'icon' => 'bi bi-file-bar-graph', 'url' => 'fuel_reports'],
            ]
        ]
    ],

    'communications' => [
        [
            'label' => 'Templates',
            'icon' => 'bi bi-file-earmark-text',
            'subitems' => [
                ['label' => 'SMS Templates', 'icon' => 'bi bi-chat-text', 'url' => 'sms_templates'],
                ['label' => 'Email Templates', 'icon' => 'bi bi-envelope-paper', 'url' => 'email_templates'],
                ['label' => 'Notification Templates', 'icon' => 'bi bi-bell-fill', 'url' => 'notification_templates'],
            ]
        ],
        [
            'label' => 'Groups',
            'icon' => 'bi bi-people',
            'subitems' => [
                ['label' => 'Message Groups', 'icon' => 'bi bi-people-fill', 'url' => 'message_groups'],
                ['label' => 'Group Members', 'icon' => 'bi bi-person-plus', 'url' => 'group_members'],
            ]
        ],
        [
            'label' => 'Reports',
            'icon' => 'bi bi-file-earmark-text',
            'subitems' => [
                ['label' => 'Message Reports', 'icon' => 'bi bi-graph-up', 'url' => 'message_reports'],
                ['label' => 'Delivery Reports', 'icon' => 'bi bi-check2-all', 'url' => 'delivery_reports'],
            ]
        ]
    ],

    'student_affairs' => [
        [
            'label' => 'Student Management',
            'icon' => 'bi bi-mortarboard',
            'subitems' => [
                ['label' => 'Student Records', 'icon' => 'bi bi-file-person', 'url' => 'student_records'],
                ['label' => 'Attendance Records', 'icon' => 'bi bi-calendar-check', 'url' => 'attendance_records'],
                ['label' => 'Discipline Records', 'icon' => 'bi bi-exclamation-triangle', 'url' => 'discipline_records'],
            ]
        ],
        [
            'label' => 'Activities',
            'icon' => 'bi bi-calendar-event',
            'subitems' => [
                ['label' => 'School Events', 'icon' => 'bi bi-calendar-week', 'url' => 'school_events'],
                ['label' => 'Extra-Curricular', 'icon' => 'bi bi-trophy', 'url' => 'extra_curricular'],
                ['label' => 'Clubs & Societies', 'icon' => 'bi bi-people', 'url' => 'clubs'],
            ]
        ],
        [
            'label' => 'Transport',
            'icon' => 'bi bi-truck',
            'subitems' => [
                ['label' => 'Route Management', 'icon' => 'bi bi-map', 'url' => 'transport_routes'],
                ['label' => 'Vehicle Assignment', 'icon' => 'bi bi-truck-flatbed', 'url' => 'vehicle_assignment'],
                ['label' => 'Transport Schedule', 'icon' => 'bi bi-calendar4-week', 'url' => 'transport_schedule'],
            ]
        ]
    ],

    // Universal items for all users
    'universal' => [
        [
            'label' => 'Messages',
            'icon' => 'bi bi-chat-dots',
            'subitems' => [
                ['label' => 'Announcements', 'icon' => 'bi bi-megaphone', 'url' => 'announcements'],
                ['label' => 'SMS Messages', 'icon' => 'bi bi-chat', 'url' => 'sms_messages'],
                ['label' => 'Email Messages', 'icon' => 'bi bi-envelope', 'url' => 'email_messages'],
                ['label' => 'Notifications', 'icon' => 'bi bi-bell', 'url' => 'notifications']
            ]
        ],
        [
            'label' => 'Calendar',
            'icon' => 'bi bi-calendar',
            'url' => 'calendar'
        ],
        [
            'label' => 'Settings',
            'icon' => 'bi bi-gear',
            'url' => 'settings'
        ],
        [
            'label' => 'CBE Assessments',
            'icon' => 'fa fa-clipboard-check',
            'roles' => ['admin', 'teacher', 'director', 'head_teacher'],
            'url' => '/pages/cbe_assessments.php',
        ],
        [
            'label' => 'CBE Reports',
            'icon' => 'fa fa-chart-bar',
            'roles' => ['admin', 'teacher', 'director', 'head_teacher'],
            'url' => '/pages/cbe_reports.php',
        ]
    ]
]);