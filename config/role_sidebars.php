<?php
/**
 * Role sidebar definitions — workflow-driven, one source of truth.
 *
 * Every item placement is justified by a real school workflow step.
 * Ordering rule: primary daily tasks first, oversight/reports last.
 *
 * Workflows baked in:
 *  Admissions   : Admin(intake+ID+class) → HT(interview+approve) → DH-Academic(class placement) → Accountant(fee) → Boarding(dorm)
 *  Fee Structure: Director(schedule terms) → Accountant(draft) → Admin+HT(review) → Director(approve)
 *  Payroll      : Admin+Director(create) → Director(approve) → Accountant(pay)
 *  Timetable    : Class Teacher(draft) → DH-Academic(review+assign teachers) → HT(approve)
 *  Lesson Plans : Teacher(create) → DH-Academic(review) → HT(approve)
 *  Staff        : Application → Director/HT(interview) → Admin(onboard+ID+account) → Director(approve) → Accountant(pay)
 */

return [

    // =========================================================================
    // 2 — System Administrator
    // Technical owner: users, security, monitoring, system config, audit
    // Does NOT manage school day-to-day — that is the school roles
    // =========================================================================
    2 => [
        ['label' => 'Dashboard',       'url' => 'system_administrator_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'Users & Roles', 'url' => null, 'icon' => 'fas fa-shield-alt', 'subitems' => [
            ['label' => 'User Accounts',           'url' => 'manage_users'],
            ['label' => 'Account Status',          'url' => 'account_status'],
            ['label' => 'Role Definitions',        'url' => 'manage_roles'],
            ['label' => 'Role-Permission Matrix',  'url' => 'role_permission_matrix'],
            ['label' => 'Resource Permissions',    'url' => 'resource_based_permissions'],
        ]],

        ['label' => 'Security Center', 'url' => null, 'icon' => 'fas fa-lock', 'subitems' => [
            ['label' => 'Authentication Logs',     'url' => 'authentication_logs'],
            ['label' => 'Failed Logins',           'url' => 'failed_login_attempts'],
            ['label' => 'Active Sessions',         'url' => 'active_sessions'],
            ['label' => 'Token Management',        'url' => 'token_management'],
            ['label' => 'IP Whitelist/Blacklist',  'url' => 'ip_whitelist_blacklist'],
        ]],

        ['label' => 'Policy & Governance', 'url' => null, 'icon' => 'fas fa-gavel', 'subitems' => [
            ['label' => 'Domain Isolation',        'url' => 'domain_isolation_rules'],
            ['label' => 'Time-Bound Access',       'url' => 'time_bound_access'],
            ['label' => 'Permission Policies',     'url' => 'permission_policies'],
            ['label' => 'Route Access Rules',      'url' => 'route_access_rules'],
        ]],

        ['label' => 'Configuration', 'url' => null, 'icon' => 'fas fa-cogs', 'subitems' => [
            ['label' => 'System Settings',         'url' => 'system_settings'],
            ['label' => 'Feature Flags',           'url' => 'feature_flags'],
            ['label' => 'Module Enablement',       'url' => 'module_enablement'],
            ['label' => 'Maintenance Mode',        'url' => 'maintenance_mode'],
        ]],

        ['label' => 'Navigation & UI', 'url' => null, 'icon' => 'fas fa-sitemap', 'subitems' => [
            ['label' => 'Route Registry',          'url' => 'route_registry'],
            ['label' => 'Sidebar Menus',           'url' => 'sidebar_menus'],
            ['label' => 'Role Navigation Config',  'url' => 'role_navigation_config'],
        ]],

        ['label' => 'Monitoring', 'url' => null, 'icon' => 'fas fa-heartbeat', 'subitems' => [
            ['label' => 'System Health',           'url' => 'system_health'],
            ['label' => 'Error Logs',              'url' => 'error_logs'],
            ['label' => 'Background Jobs',         'url' => 'background_jobs'],
            ['label' => 'API Metrics',             'url' => 'api_metrics'],
            ['label' => 'Rate Limiting',           'url' => 'rate_limiting_status'],
        ]],

        ['label' => 'Data Governance', 'url' => null, 'icon' => 'fas fa-database', 'subitems' => [
            ['label' => 'Migrations',              'url' => 'migrations'],
            ['label' => 'Backups',                 'url' => 'backups'],
            ['label' => 'Data Retention',          'url' => 'data_retention'],
        ]],

        ['label' => 'Audit & Forensics', 'url' => null, 'icon' => 'fas fa-search', 'subitems' => [
            ['label' => 'Activity Logs',           'url' => 'activity_audit_logs'],
            ['label' => 'Permission Changes',      'url' => 'permission_changes'],
            ['label' => 'Policy Violations',       'url' => 'policy_violations'],
            ['label' => 'Security Incidents',      'url' => 'security_incidents'],
        ]],

        ['label' => 'Developer Tools', 'url' => null, 'icon' => 'fas fa-code', 'subitems' => [
            ['label' => 'API Explorer',            'url' => 'api_explorer'],
            ['label' => 'Webhook Registry',        'url' => 'webhook_registry'],
            ['label' => 'System Diagnostics',      'url' => 'system_diagnostics'],
            ['label' => 'Job Inspector',           'url' => 'job_inspector'],
        ]],
    ],

    // =========================================================================
    // 3 — Director / School Owner
    // Strategic authority: approves admissions, schedules academic year/terms,
    // approves fee structure, creates + approves payroll, approves staff appts,
    // oversees all departments. NOT operational/classroom.
    // =========================================================================
    3 => [
        ['label' => 'Dashboard', 'url' => 'director_owner_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        // Admissions - Director oversight only (operational handled by School Admin, HT, Deputy Academic)
        ['label' => 'Admissions Oversight', 'url' => null, 'icon' => 'fas fa-user-check', 'subitems' => [
            ['label' => 'Enrollment Reports',      'url' => 'enrollment_reports'],        // overview of admissions stats
        ]],

        // Remaining approvals — these are Director approval gates in other workflows
        ['label' => 'Approvals', 'url' => null, 'icon' => 'fas fa-check-double', 'subitems' => [
            ['label' => 'Fee Structure Approval',  'url' => 'manage_fee_structure'],        // approve what accountant drafted
            ['label' => 'Finance Approvals',       'url' => 'finance_approvals'],           // approve large transactions
        ]],

        // Academic Calendar — Director schedules Terms 1, 2, 3
        ['label' => 'Academic Calendar', 'url' => null, 'icon' => 'fas fa-calendar', 'subitems' => [
            ['label' => 'Academic Years',          'url' => 'academic_years'],
            ['label' => 'Schedule Terms',          'url' => 'manage_terms'],               // DIRECTOR schedules terms
            ['label' => 'Term Dates',              'url' => 'term_dates'],
            ['label' => 'Year Calendar',           'url' => 'year_calendar'],
        ]],

        // Staff — manage employees, approve appointments
        ['label' => 'Staff', 'url' => null, 'icon' => 'fas fa-chalkboard-teacher', 'subitems' => [
            ['label' => 'All Staff',               'url' => 'manage_staff'],
            ['label' => 'Staff Onboarding',        'url' => 'staff_onboarding'],
            ['label' => 'Staff Lifecycle',         'url' => 'staff_lifecycle'],
            ['label' => 'Staff Appointments',      'url' => 'staff_appointments'],
            ['label' => 'Import Existing Staff',   'url' => 'import_existing_staff'],
            ['label' => 'Teachers',                'url' => 'all_teachers'],
            ['label' => 'Staff Performance',       'url' => 'staff_performance'],
            ['label' => 'Teacher Workload',        'url' => 'teacher_workload'],
            ['label' => 'Staff Attendance',        'url' => 'staff_attendance'],
            ['label' => 'Staff Leave',             'url' => 'staff_leave'],
        ]],

        // Students — oversight, not operational
        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'Students Overview',       'url' => 'students_overview'],
            ['label' => 'Performance Overview',    'url' => 'student_performance'],
            ['label' => 'Discipline Overview',     'url' => 'discipline_cases'],
            ['label' => 'Special Needs',           'url' => 'special_needs'],
        ]],

        // Finance — approve fee structure, view reports, budget oversight
        ['label' => 'Finance', 'url' => null, 'icon' => 'fas fa-coins', 'subitems' => [
            ['label' => 'Financial Reports',       'url' => 'finance_reports'],
            ['label' => 'Budget Overview',         'url' => 'budget_overview'],
            ['label' => 'Fee Structure',           'url' => 'manage_fee_structure'],       // approve what accountant drafted
            ['label' => 'Student Fees Overview',   'url' => 'student_fees'],
            ['label' => 'Petty Cash',              'url' => 'petty_cash'],
        ]],

        // Payroll — Director creates AND approves (workflow role)
        ['label' => 'Payroll', 'url' => null, 'icon' => 'fas fa-wallet', 'subitems' => [
            ['label' => 'Manage Payrolls',         'url' => 'manage_payrolls'],            // create, review and approve by permission
            ['label' => 'Payroll History',         'url' => 'payroll'],
            ['label' => 'Payslips',                'url' => 'payslips'],
        ]],

        // Accounts — financial oversight
        ['label' => 'Accounts', 'url' => null, 'icon' => 'fas fa-university', 'subitems' => [
            ['label' => 'Bank Accounts',           'url' => 'bank_accounts'],
            ['label' => 'Bank Transactions',       'url' => 'bank_transactions'],
            ['label' => 'M-Pesa Settlements',      'url' => 'mpesa_settlements'],
            ['label' => 'Unmatched Payments',      'url' => 'unmatched_payments'],
        ]],

        // Payments — approve large vendor payments
        ['label' => 'Payments & Vendors', 'url' => null, 'icon' => 'fas fa-money-bill-wave', 'subitems' => [
            ['label' => 'Manage Payments',         'url' => 'manage_payments'],
            ['label' => 'Vendors',                 'url' => 'vendors'],
            ['label' => 'Purchase Orders',         'url' => 'purchase_orders'],
        ]],

        // Academic — view/overview, not operational
        ['label' => 'Academic Overview', 'url' => null, 'icon' => 'fas fa-graduation-cap', 'subitems' => [
            ['label' => 'Classes',                 'url' => 'manage_classes'],
            ['label' => 'View Timetable',          'url' => 'manage_timetable'],
            ['label' => 'View Results',            'url' => 'view_results'],
            ['label' => 'Report Cards',            'url' => 'report_cards'],
            ['label' => 'Exam Schedule',           'url' => 'exam_schedule'],
        ]],

        // Attendance — oversight
        ['label' => 'Attendance', 'url' => null, 'icon' => 'fas fa-clipboard-check', 'subitems' => [
            ['label' => 'View Attendance',         'url' => 'view_attendance'],
            ['label' => 'Attendance Reports',      'url' => 'attendance_reports'],
        ]],

        // Transport — Director approves transport fees (workflow)
        ['label' => 'Transport', 'url' => null, 'icon' => 'fas fa-bus', 'subitems' => [
            ['label' => 'Manage Transport',        'url' => 'manage_transport'],
            ['label' => 'Transportation Fees',     'url' => 'manage_transport'],            // Director approves transport fee rates
            ['label' => 'Routes Overview',         'url' => 'my_routes'],
        ]],

        // Boarding — oversight
        ['label' => 'Boarding', 'url' => null, 'icon' => 'fas fa-bed', 'subitems' => [
            ['label' => 'Boarding Overview',       'url' => 'manage_boarding'],
            ['label' => 'Dormitory Management',    'url' => 'dormitory_management'],
            ['label' => 'Permissions & Exeats',    'url' => 'permissions_exeats'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Financial',               'url' => 'finance_reports'],
            ['label' => 'Academic',                'url' => 'academic_reports'],
            ['label' => 'Performance',             'url' => 'performance_reports'],
            ['label' => 'Enrollment',              'url' => 'enrollment_reports'],
            ['label' => 'Comparative',             'url' => 'comparative_reports'],
            ['label' => 'Attendance Trends',       'url' => 'attendance_trends'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
            ['label' => 'SMS',                     'url' => 'manage_sms'],
            ['label' => 'Email',                   'url' => 'manage_email'],
        ]],

        ['label' => 'Activities', 'url' => null, 'icon' => 'fas fa-running', 'subitems' => [
            ['label' => 'Manage Activities',       'url' => 'manage_activities'],
            ['label' => 'School Events',           'url' => 'school_events'],
            ['label' => 'Sports',                  'url' => 'sports'],
        ]],

        ['label' => 'Inventory & Store', 'url' => null, 'icon' => 'fas fa-boxes', 'subitems' => [
            ['label' => 'Inventory Overview',      'url' => 'manage_inventory'],
            ['label' => 'Uniform Sales',           'url' => 'manage_uniform_sales'],
        ]],

        ['label' => 'Library',       'url' => null, 'icon' => 'fas fa-book',        'subitems' => [['label' => 'Library Overview', 'url' => 'manage_library']]],
        ['label' => 'Parents',       'url' => null, 'icon' => 'fas fa-users-cog',   'subitems' => [['label' => 'All Parents', 'url' => 'all_parents'], ['label' => 'PTA Management', 'url' => 'pta_management']]],
    ],

    // =========================================================================
    // 4 — School Administrator
    // Operational backbone: admissions (intake→ID→class), staff onboarding,
    // student records, fee collection, transport coordination, payroll creation
    // =========================================================================
    4 => [
        ['label' => 'Dashboard', 'url' => 'school_administrative_officer_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        // ADMISSIONS — School Admin is the primary handler of the full workflow
        // Application → intake → documents → schedule interview → record fee → CREATE student → generate ID → assign class
        ['label' => 'Admissions', 'url' => null, 'icon' => 'fas fa-user-plus', 'subitems' => [
            ['label' => 'New Applications',        'url' => 'new_applications'],           // receive applications
            ['label' => 'Applications Workspace',  'url' => 'manage_students_admissions'], // tabbed workspace
            ['label' => 'Class Placement',         'url' => 'admissions_class_placement'], // place student in class
            ['label' => 'Placement Tests',         'url' => 'placement_tests'],          // manage placement tests
            ['label' => 'Enrollment Reports',      'url' => 'enrollment_reports'],        // comprehensive reports
        ]],

        // STUDENTS — manage all student records
        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'All Students',            'url' => 'manage_students'],
            ['label' => 'Student Profiles',        'url' => 'student_profiles'],
            ['label' => 'ID Cards',                'url' => 'student_id_cards'],
            ['label' => 'Family Groups',           'url' => 'manage_family_groups'],
            ['label' => 'Special Needs',           'url' => 'special_needs'],
            ['label' => 'Student Promotion',       'url' => 'student_promotion'],          // end-of-year promotion
        ]],

        // CLASSES — was missing, Admin assigns students to classes
        ['label' => 'Classes', 'url' => null, 'icon' => 'fas fa-school', 'subitems' => [
            ['label' => 'Manage Classes',          'url' => 'manage_classes'],
            ['label' => 'Class Streams',           'url' => 'class_streams'],
            ['label' => 'Class Capacity',          'url' => 'class_capacity'],
        ]],

        // STAFF — school Admin handles full HR operations + onboarding
        ['label' => 'Staff', 'url' => null, 'icon' => 'fas fa-chalkboard-teacher', 'subitems' => [
            ['label' => 'All Staff',               'url' => 'manage_staff'],
            ['label' => 'Staff Onboarding',        'url' => 'staff_onboarding'],
            ['label' => 'Staff Lifecycle',         'url' => 'staff_lifecycle'],
            ['label' => 'Staff Appointments',      'url' => 'staff_appointments'],
            ['label' => 'Import Existing Staff',   'url' => 'import_existing_staff'],
            ['label' => 'Teachers',                'url' => 'all_teachers'],
            ['label' => 'Staff Security Passes',   'url' => 'staff_id_cards'],              // generate and issue lanyard passes
            ['label' => 'Staff Role Assignments',   'url' => 'staff_role_assignments'],
            ['label' => 'Staff Attendance',        'url' => 'staff_attendance'],
            ['label' => 'Leave Management',        'url' => 'staff_leave'],
            ['label' => 'Performance Overview',    'url' => 'staff_performance'],
        ]],

        // FEES — Admin collects fees, reviews fee structure before Director approves
        ['label' => 'Fees', 'url' => null, 'icon' => 'fas fa-receipt', 'subitems' => [
            ['label' => 'Fee Structure',           'url' => 'manage_fee_structure'],       // review (not draft — that's accountant)
            ['label' => 'Student Fee Accounts',    'url' => 'student_fees'],
            ['label' => 'Record Payments',         'url' => 'manage_payments'],
            ['label' => 'Unmatched Payments',      'url' => 'unmatched_payments'],
            ['label' => 'Fee Defaulters',          'url' => 'fee_defaulters'],
            ['label' => 'Students with Balance',   'url' => 'students_with_balance'],
            ['label' => 'Uniform Sales',           'url' => 'manage_uniform_sales'],
        ]],

        // PAYROLL — Admin creates payroll (Director then approves, Accountant pays)
        ['label' => 'Payroll', 'url' => null, 'icon' => 'fas fa-wallet', 'subitems' => [
            ['label' => 'Create Payroll',          'url' => 'manage_payrolls'],            // Admin drafts payroll
            ['label' => 'Payroll Records',         'url' => 'payroll'],
        ]],

        // TRANSPORT — Admin coordinates student transport assignments
        ['label' => 'Transport', 'url' => null, 'icon' => 'fas fa-bus', 'subitems' => [
            ['label' => 'Manage Transport',        'url' => 'manage_transport'],
            ['label' => 'Routes',                  'url' => 'my_routes'],
            ['label' => 'Assign Students to Routes','url' => 'manage_transport'],
            ['label' => 'Transport Fees',          'url' => 'manage_transport'],
            ['label' => 'Permissions & Exeats',    'url' => 'permissions_exeats'],
        ]],

        // ACADEMIC — Admin view + report card distribution
        ['label' => 'Academic', 'url' => null, 'icon' => 'fas fa-graduation-cap', 'subitems' => [
            ['label' => 'View Timetable',          'url' => 'manage_timetable'],
            ['label' => 'Exam Schedule',           'url' => 'exam_schedule'],
            ['label' => 'View Results',            'url' => 'view_results'],
            ['label' => 'Report Cards',            'url' => 'report_cards'],              // distribute report cards
            ['label' => 'Academic Years',          'url' => 'academic_years'],
        ]],

        // ATTENDANCE
        ['label' => 'Attendance', 'url' => null, 'icon' => 'fas fa-clipboard-check', 'subitems' => [
            ['label' => 'View Attendance',         'url' => 'view_attendance'],
            ['label' => 'Attendance Reports',      'url' => 'attendance_reports'],
        ]],

        ['label' => 'Parents', 'url' => null, 'icon' => 'fas fa-users-cog', 'subitems' => [
            ['label' => 'All Parents/Guardians',   'url' => 'all_parents'],
            ['label' => 'Parent Meetings',         'url' => 'parent_meetings'],
            ['label' => 'PTA Management',          'url' => 'pta_management'],
        ]],

        ['label' => 'Events & Calendar', 'url' => null, 'icon' => 'fas fa-calendar-alt', 'subitems' => [
            ['label' => 'School Events',           'url' => 'school_events'],
            ['label' => 'Manage Calendar',         'url' => 'manage_calendar_events'],
            ['label' => 'Assemblies',              'url' => 'assemblies'],
        ]],

        ['label' => 'Boarding', 'url' => null, 'icon' => 'fas fa-bed', 'subitems' => [
            ['label' => 'Boarding Overview',       'url' => 'manage_boarding'],
            ['label' => 'Permissions & Exeats',    'url' => 'permissions_exeats'],         // Admin approves exeats
            ['label' => 'Roll Call',               'url' => 'boarding_roll_call'],
        ]],

        ['label' => 'Discipline', 'url' => null, 'icon' => 'fas fa-gavel', 'subitems' => [
            ['label' => 'Discipline Cases',        'url' => 'discipline_cases'],
            ['label' => 'Parent Notifications',    'url' => 'manage_communications'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
            ['label' => 'SMS',                     'url' => 'manage_sms'],
            ['label' => 'Email',                   'url' => 'manage_email'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Academic Reports',        'url' => 'academic_reports'],
            ['label' => 'Enrollment Reports',      'url' => 'enrollment_reports'],
            ['label' => 'Attendance Reports',      'url' => 'attendance_reports'],
            ['label' => 'Financial Summary',       'url' => 'finance_reports'],
        ]],

        ['label' => 'Library',        'url' => null, 'icon' => 'fas fa-book',        'subitems' => [['label' => 'Manage Library', 'url' => 'manage_library']]],
        ['label' => 'Health Records', 'url' => null, 'icon' => 'fas fa-heartbeat',   'subitems' => [['label' => 'Student Health', 'url' => 'student_health'], ['label' => 'Sick Bay Log', 'url' => 'sick_bay']]],
        
    ],

    // =========================================================================
    // 5 — Headteacher
    // Academic + school leadership. Workflow roles:
    //   Admissions: interview + APPROVE
    //   Timetable: APPROVE
    //   Lesson Plans: APPROVE
    //   Fee Structure: REVIEW (before Director approves)
    //   Staff: interview, recommend appointment
    // =========================================================================
    5 => [
        ['label' => 'Dashboard', 'url' => 'headteacher_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        // ADMISSIONS — HT review, interview, decision, oversight
        ['label' => 'Admissions', 'url' => null, 'icon' => 'fas fa-user-plus', 'subitems' => [
            ['label' => 'Applications Review',    'url' => 'admissions_headteacher_applications'],
            ['label' => 'Interview Queue',        'url' => 'admission_interviews'],
            ['label' => 'Admission Decisions',    'url' => 'admissions_admission_decisions'],
            ['label' => 'Approval Follow-up',     'url' => 'admissions_pending_admission_approvals'],
            ['label' => 'Enrollment Reports',     'url' => 'enrollment_reports'],
        ]],

        // ACADEMIC — HT approves timetable + lesson plans (key workflow authority)
        ['label' => 'Academic', 'url' => null, 'icon' => 'fas fa-graduation-cap', 'subitems' => [
            ['label' => 'Classes',                 'url' => 'manage_classes'],
            ['label' => 'Subjects',                'url' => 'manage_subjects'],
            ['label' => 'Timetable (Approve)',     'url' => 'manage_timetable'],           // APPROVE
            ['label' => 'Lesson Plan Approval',    'url' => 'lesson_plan_approval'],       // APPROVE
            ['label' => 'All Lesson Plans',        'url' => 'manage_lesson_plans'],
            ['label' => 'Schemes of Work',         'url' => 'schemes_of_work'],
            ['label' => 'Academic Calendar',       'url' => 'academic_calendar'],
            ['label' => 'Academic Years',          'url' => 'academic_years'],
            ['label' => 'CBC Curriculum',          'url' => 'curriculum_cbc'],
        ]],

        // ASSESSMENTS & EXAMS
        ['label' => 'Assessments & Exams', 'url' => null, 'icon' => 'fas fa-file-alt', 'subitems' => [
            ['label' => 'Exam Setup',              'url' => 'exam_setup'],
            ['label' => 'Exam Schedule',           'url' => 'exam_schedule'],
            ['label' => 'Supervision Roster',      'url' => 'supervision_roster'],
            ['label' => 'Grading Status',          'url' => 'grading_status'],
            ['label' => 'View Results',            'url' => 'view_results'],
            ['label' => 'Results Analysis',        'url' => 'results_analysis'],
            ['label' => 'Report Cards (Approve)',  'url' => 'report_cards'],              // HT signs off on report cards
        ]],

        // STAFF — HT interviews candidates, manages performance
        ['label' => 'Staff', 'url' => null, 'icon' => 'fas fa-chalkboard-teacher', 'subitems' => [
            ['label' => 'All Staff',               'url' => 'manage_staff'],
            ['label' => 'Staff Onboarding',        'url' => 'staff_onboarding'],
            ['label' => 'Staff Lifecycle',         'url' => 'staff_lifecycle'],
            ['label' => 'Staff Appointments',      'url' => 'staff_appointments'],
            ['label' => 'Teachers',                'url' => 'all_teachers'],
            ['label' => 'Performance Reviews',     'url' => 'teacher_performance_reviews'],
            ['label' => 'Teacher Workload',        'url' => 'teacher_workload'],
            ['label' => 'Staff Attendance',        'url' => 'staff_attendance'],
            ['label' => 'Leave Approval',          'url' => 'staff_leave'],
        ]],

        // STUDENTS
        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'Students Overview',       'url' => 'students_overview'],
            ['label' => 'Performance Overview',    'url' => 'student_performance'],
            ['label' => 'Discipline Cases',        'url' => 'discipline_cases'],
            ['label' => 'Counseling',              'url' => 'student_counseling'],
            ['label' => 'Special Needs',           'url' => 'special_needs'],
            ['label' => 'Health Records',          'url' => 'student_health'],
        ]],

        // ATTENDANCE
        ['label' => 'Attendance', 'url' => null, 'icon' => 'fas fa-clipboard-check', 'subitems' => [
            ['label' => 'View Attendance',         'url' => 'view_attendance'],
            ['label' => 'Attendance Reports',      'url' => 'attendance_reports'],
        ]],

        // FINANCE REVIEW — HT reviews fee structure before Director approves
        ['label' => 'Finance Review', 'url' => null, 'icon' => 'fas fa-coins', 'subitems' => [
            ['label' => 'Fee Structure Review',    'url' => 'manage_fee_structure'],       // REVIEW before Director approves
            ['label' => 'Financial Reports',       'url' => 'finance_reports'],
            ['label' => 'Budget Overview',         'url' => 'budget_overview'],
        ]],

        ['label' => 'Parents', 'url' => null, 'icon' => 'fas fa-users-cog', 'subitems' => [
            ['label' => 'All Parents',             'url' => 'all_parents'],
            ['label' => 'Parent Meetings',         'url' => 'parent_meetings'],
            ['label' => 'PTA Management',          'url' => 'pta_management'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Academic Reports',        'url' => 'academic_reports'],
            ['label' => 'Performance Analysis',    'url' => 'performance_analysis'],
            ['label' => 'Enrollment',              'url' => 'enrollment_reports'],
            ['label' => 'Attendance',              'url' => 'attendance_reports'],
            ['label' => 'Term Reports',            'url' => 'term_reports'],
            ['label' => 'Comparative',             'url' => 'comparative_reports'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
            ['label' => 'SMS',                     'url' => 'manage_sms'],
        ]],

        ['label' => 'Boarding',   'url' => null, 'icon' => 'fas fa-bed',     'subitems' => [['label' => 'Boarding Overview', 'url' => 'manage_boarding'], ['label' => 'Roll Call', 'url' => 'boarding_roll_call']]],
        ['label' => 'Transport',  'url' => null, 'icon' => 'fas fa-bus',     'subitems' => [['label' => 'Transport Overview', 'url' => 'manage_transport']]],
        ['label' => 'Activities', 'url' => null, 'icon' => 'fas fa-running', 'subitems' => [['label' => 'Manage Activities', 'url' => 'manage_activities'], ['label' => 'School Events', 'url' => 'school_events']]],
        ['label' => 'Library',    'url' => null, 'icon' => 'fas fa-book',    'subitems' => [['label' => 'Manage Library', 'url' => 'manage_library']]],
    ],

    // =========================================================================
    // 6 — Deputy Head (Academic)
    // DUAL ROLE: still a classroom teacher + academic administrator.
    // As teacher     : marks own class attendance, creates lesson plans, enters marks, views own timetable.
    // As DH Academic : reviews lesson plans, assigns teachers to timetable, manages exam cycle,
    //                  handles class placement for admissions, manages curriculum/academic calendar.
    // Sidebar ordering: MY TEACHING first (daily tasks) → ADMIN duties below.
    // =========================================================================
    6 => [
        ['label' => 'Dashboard', 'url' => 'deputy_head_academic_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        // ── MY TEACHING (daily teacher tasks) ────────────────────────────────
        ['label' => 'My Teaching', 'url' => null, 'icon' => 'fas fa-chalkboard', 'subitems' => [
            ['label' => 'My Timetable',            'url' => 'timetable'],                  // view personal teaching schedule
            ['label' => 'My Class',                'url' => 'my_students_list'],           // if assigned a home class
            ['label' => 'Mark Attendance',         'url' => 'mark_attendance'],            // daily class register
            ['label' => 'My Lesson Plans',         'url' => 'manage_lesson_plans'],        // CREATE own plans (not just review)
            ['label' => 'Schemes of Work',         'url' => 'schemes_of_work'],            // plan the term
            ['label' => 'Enter Assessment Marks',  'url' => 'formative_assessments'],      // grade own students
            ['label' => 'Competency Ratings',      'url' => 'competencies_sheet'],         // CBC competency entries
        ]],

        // ── ADMIN: ADMISSIONS ─────────────────────────────────────────────────
        ['label' => 'Admissions', 'url' => null, 'icon' => 'fas fa-user-plus', 'subitems' => [
            ['label' => 'Academic Applications',   'url' => 'admissions_academic_applications'],
            ['label' => 'Class Placement',         'url' => 'admissions_class_placement'], // recommend which class
            ['label' => 'Placement Tests',         'url' => 'placement_tests'],
        ]],

        // ── ADMIN: ACADEMIC MANAGEMENT ────────────────────────────────────────
        ['label' => 'Academic', 'url' => null, 'icon' => 'fas fa-graduation-cap', 'subitems' => [
            ['label' => 'Manage Classes',          'url' => 'manage_classes'],
            ['label' => 'Class Streams',           'url' => 'class_streams'],
            ['label' => 'Subjects / Learning Areas','url' => 'manage_subjects'],
            ['label' => 'CBC Curriculum',          'url' => 'curriculum_cbc'],
            ['label' => 'Academic Years',          'url' => 'academic_years'],
            ['label' => 'Academic Calendar',       'url' => 'academic_calendar'],
        ]],

        // ── ADMIN: TIMETABLE (Deputy ASSIGNS teachers — key workflow step) ────
        ['label' => 'Timetable Management', 'url' => null, 'icon' => 'fas fa-calendar-alt', 'subitems' => [
            ['label' => 'All Timetables',          'url' => 'manage_timetable'],
            ['label' => 'Assign Teachers',         'url' => 'manage_timetable'],           // WORKFLOW: Deputy assigns teachers
            ['label' => 'Teacher Timetables',      'url' => 'timetable'],
            ['label' => 'Supervision Roster',      'url' => 'supervision_roster'],
        ]],

        // ── ADMIN: LESSON PLAN REVIEW (Deputy reviews before HT approves) ────
        ['label' => 'Lesson Plan Review', 'url' => null, 'icon' => 'fas fa-book-open', 'subitems' => [
            ['label' => 'All Lesson Plans',        'url' => 'all_lesson_plans'],
            ['label' => 'Pending My Review',       'url' => 'lesson_plan_approval'],       // WORKFLOW: Deputy → HT
            ['label' => 'Approved Plans',          'url' => 'manage_lesson_plans'],
            ['label' => 'By Class',                'url' => 'lesson_plans_by_class'],
            ['label' => 'By Teacher',              'url' => 'lesson_plans_by_teacher'],
        ]],

        // ── ADMIN: ASSESSMENTS & EXAMS ────────────────────────────────────────
        ['label' => 'Assessments & Exams', 'url' => null, 'icon' => 'fas fa-file-alt', 'subitems' => [
            ['label' => 'Exam Setup',              'url' => 'exam_setup'],
            ['label' => 'Exam Schedule',           'url' => 'exam_schedule'],
            ['label' => 'Grading Status',          'url' => 'grading_status'],             // track which teachers have graded
            ['label' => 'View All Results',        'url' => 'view_results'],
            ['label' => 'Results Analysis',        'url' => 'results_analysis'],
            ['label' => 'Report Cards',            'url' => 'report_cards'],
            ['label' => 'National Exams',          'url' => 'national_exams'],
        ]],

        // ── ADMIN: TEACHER MANAGEMENT ─────────────────────────────────────────
        ['label' => 'Teacher Management', 'url' => null, 'icon' => 'fas fa-chalkboard-teacher', 'subitems' => [
            ['label' => 'All Teachers',            'url' => 'all_teachers'],
            ['label' => 'Assign Class Teachers',   'url' => 'assign_class_teachers'],
            ['label' => 'Subject Allocation',      'url' => 'assign_subjects_to_teachers'],
            ['label' => 'Teacher Workload',        'url' => 'teacher_workload'],
            ['label' => 'Performance Reviews',     'url' => 'teacher_performance_reviews'],
        ]],

        // ── STUDENTS ──────────────────────────────────────────────────────────
        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'Academic Students',       'url' => 'academic_students'],
            ['label' => 'Performance Overview',    'url' => 'student_performance'],
            ['label' => 'Student Promotion',       'url' => 'student_promotion'],
            ['label' => 'Special Needs',           'url' => 'special_needs'],
        ]],

        // ── ATTENDANCE (school-wide oversight, not just own class) ────────────
        ['label' => 'Attendance', 'url' => null, 'icon' => 'fas fa-clipboard-check', 'subitems' => [
            ['label' => 'Daily Overview',          'url' => 'view_attendance'],
            ['label' => 'Submit Attendance',       'url' => 'mark_attendance'],
            ['label' => 'Attendance Reports',      'url' => 'attendance_reports'],
            ['label' => 'Attendance Trends',       'url' => 'attendance_trends'],
        ]],

        // ── REPORTS ───────────────────────────────────────────────────────────
        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Academic Reports',        'url' => 'academic_reports'],
            ['label' => 'Performance Analysis',    'url' => 'performance_analysis'],
            ['label' => 'Term Reports',            'url' => 'term_reports'],
            ['label' => 'Comparative Reports',     'url' => 'comparative_reports'],
            ['label' => 'Enrollment Trends',       'url' => 'enrollment_trends'],
        ]],

        // ── HR (personal) ────────────────────────────────────────────────────
        ['label' => 'My HR', 'url' => null, 'icon' => 'fas fa-id-badge', 'subitems' => [
            ['label' => 'My Payslip',              'url' => 'detailed_payslip'],
            ['label' => 'My Attendance',           'url' => 'my_attendance'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
            ['label' => 'Parent Messaging',        'url' => 'manage_email'],
        ]],

        ['label' => 'Library', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [['label' => 'Library', 'url' => 'manage_library']]],
    ],

    // =========================================================================
    // 7 — Class Teacher
    // Daily: own class attendance, draft timetable, create lesson plans,
    // enter assessment marks, log discipline, communicate with parents
    // =========================================================================
    7 => [
        ['label' => 'Dashboard', 'url' => 'class_teacher_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        // MY CLASS — primary daily view
        ['label' => 'My Class', 'url' => null, 'icon' => 'fas fa-users', 'subitems' => [
            ['label' => 'Student List',            'url' => 'my_students_list'],
            ['label' => 'Student Profiles',        'url' => 'student_profiles'],
            ['label' => 'Class Performance',       'url' => 'my_students_performance'],
            ['label' => 'Special Needs Students',  'url' => 'special_needs_students'],
        ]],

        // ATTENDANCE — Class teacher marks daily attendance
        ['label' => 'Attendance', 'url' => null, 'icon' => 'fas fa-clipboard-check', 'subitems' => [
            ['label' => 'Mark Attendance',         'url' => 'mark_attendance'],            // daily task
            ['label' => 'Attendance History',      'url' => 'class_attendance_history'],
            ['label' => 'Absentees Today',         'url' => 'today_absentees'],
        ]],

        // TIMETABLE — Class teacher DRAFTS timetable (Deputy reviews, HT approves)
        ['label' => 'Timetable', 'url' => null, 'icon' => 'fas fa-calendar-alt', 'subitems' => [
            ['label' => 'Draft My Timetable',      'url' => 'manage_timetable'],           // DRAFT — key workflow step
            ['label' => 'View Approved Timetable', 'url' => 'timetable'],
        ]],

        // LESSON PLANS — Class teacher creates (Deputy reviews, HT approves)
        ['label' => 'Lesson Plans', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [
            ['label' => 'My Lesson Plans',         'url' => 'manage_lesson_plans'],
            ['label' => 'Create Lesson Plan',      'url' => 'manage_lesson_plans'],        // CREATE — key workflow step
            ['label' => 'Schemes of Work',         'url' => 'my_schemes_of_work'],
        ]],

        // ASSESSMENTS — enter marks for own class
        ['label' => 'Assessments', 'url' => null, 'icon' => 'fas fa-tasks', 'subitems' => [
            ['label' => 'Create Assessment',       'url' => 'create_assessment'],
            ['label' => 'CATs (Formative)',        'url' => 'my_cats'],
            ['label' => 'Enter Marks',             'url' => 'enter_marks'],
            ['label' => 'Class Results',           'url' => 'class_results'],
            ['label' => 'CBC Competencies',        'url' => 'competencies_sheet'],
        ]],

        ['label' => 'Examinations', 'url' => null, 'icon' => 'fas fa-file-signature', 'subitems' => [
            ['label' => 'Exam Schedule',           'url' => 'exam_schedule'],
            ['label' => 'Grade Entry',             'url' => 'grade_entry'],
        ]],

        // DISCIPLINE — log incidents in own class
        ['label' => 'Discipline', 'url' => null, 'icon' => 'fas fa-gavel', 'subitems' => [
            ['label' => 'Log Incident',            'url' => 'log_discipline_incident'],
            ['label' => 'Behavior Notes',          'url' => 'student_behavior_notes'],
            ['label' => 'Conduct Grades',          'url' => 'class_conduct_grades'],
        ]],

        // PARENTS — communicate with own class parents
        ['label' => 'Parents', 'url' => null, 'icon' => 'fas fa-users-cog', 'subitems' => [
            ['label' => 'Parent Contacts',         'url' => 'class_parent_contacts'],
            ['label' => 'Send Message',            'url' => 'send_class_message'],
            ['label' => 'Meeting Records',         'url' => 'parent_meeting_records'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Class Report',            'url' => 'generate_class_report'],
            ['label' => 'Progress Reports',        'url' => 'student_progress_reports'],
            ['label' => 'Report Cards',            'url' => 'class_report_cards'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
        ]],
    ],

    // =========================================================================
    // 8 — Subject Teacher
    // Teaches across multiple classes. Creates lesson plans, enters subject marks.
    // =========================================================================
    8 => [
        ['label' => 'Dashboard', 'url' => 'subject_teacher_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'My Subjects', 'url' => null, 'icon' => 'fas fa-book-reader', 'subitems' => [
            ['label' => 'Subject Overview',        'url' => 'my_subjects_overview'],
            ['label' => 'Classes I Teach',         'url' => 'my_classes_taught'],
            ['label' => 'Syllabus Coverage',       'url' => 'my_subject_syllabus'],
        ]],

        ['label' => 'My Students', 'url' => null, 'icon' => 'fas fa-users', 'subitems' => [
            ['label' => 'Student List',            'url' => 'subject_students_list'],
            ['label' => 'By Class',                'url' => 'students_by_class'],
            ['label' => 'Performance Tracking',    'url' => 'student_subject_performance'],
        ]],

        // ATTENDANCE — mark subject attendance
        ['label' => 'Attendance', 'url' => null, 'icon' => 'fas fa-clipboard-check', 'subitems' => [
            ['label' => 'Mark Attendance',         'url' => 'mark_attendance'],
            ['label' => 'View History',            'url' => 'view_attendance'],
        ]],

        // TIMETABLE — view (deputy assigns, not subject teacher)
        ['label' => 'Timetable',   'url' => 'timetable', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],

        // LESSON PLANS — subject teacher CREATES
        ['label' => 'Lesson Plans', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [
            ['label' => 'My Lesson Plans',         'url' => 'manage_lesson_plans'],
            ['label' => 'Create Lesson Plan',      'url' => 'manage_lesson_plans'],
            ['label' => 'Schemes of Work',         'url' => 'subject_schemes_of_work'],
        ]],

        // ASSESSMENTS — enter marks per subject
        ['label' => 'Assessments', 'url' => null, 'icon' => 'fas fa-tasks', 'subitems' => [
            ['label' => 'Create CAT',              'url' => 'create_subject_cat'],
            ['label' => 'My CATs',                 'url' => 'my_subject_cats'],
            ['label' => 'Grade Entry',             'url' => 'subject_grade_entry'],
            ['label' => 'Grading Status',          'url' => 'subject_grading_status'],
        ]],

        ['label' => 'Examinations', 'url' => null, 'icon' => 'fas fa-file-alt', 'subitems' => [
            ['label' => 'Exam Schedule',           'url' => 'subject_exam_schedule'],
            ['label' => 'Enter Results',           'url' => 'enter_exam_results'],
            ['label' => 'Results Summary',         'url' => 'subject_results_summary'],
        ]],

        ['label' => 'Resources', 'url' => null, 'icon' => 'fas fa-folder-open', 'subitems' => [
            ['label' => 'Teaching Materials',      'url' => 'teaching_materials'],
            ['label' => 'Upload Resource',         'url' => 'upload_teaching_resource'],
            ['label' => 'Past Papers',             'url' => 'past_papers'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Subject Report',          'url' => 'generate_subject_report'],
            ['label' => 'Class Comparison',        'url' => 'subject_class_comparison'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
        ]],
    ],

    // =========================================================================
    // 9 — Intern / Student Teacher
    // Learning role: observe, draft lesson plans under supervision, limited access
    // =========================================================================
    9 => [
        ['label' => 'Dashboard', 'url' => 'intern_student_teacher_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'My Assignments', 'url' => null, 'icon' => 'fas fa-clipboard-list', 'subitems' => [
            ['label' => 'Assigned Classes',        'url' => 'intern_assigned_classes'],
            ['label' => 'Assigned Subjects',       'url' => 'intern_assigned_subjects'],
            ['label' => 'My Schedule',             'url' => 'intern_schedule'],
        ]],

        ['label' => 'Lesson Plans', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [
            ['label' => 'My Lesson Plans',         'url' => 'manage_lesson_plans'],
            ['label' => 'Create Plan',             'url' => 'manage_lesson_plans'],
            ['label' => 'Mentor Feedback',         'url' => 'manage_lesson_plans'],
        ]],

        ['label' => 'My Students', 'url' => null, 'icon' => 'fas fa-users', 'subitems' => [
            ['label' => 'View Student List',       'url' => 'view_class_lists'],
            ['label' => 'View Student Info',       'url' => 'view_student_info'],
        ]],

        ['label' => 'Timetable',   'url' => 'timetable', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],

        ['label' => 'Observations', 'url' => null, 'icon' => 'fas fa-eye', 'subitems' => [
            ['label' => 'Observation Schedule',    'url' => 'observation_schedule'],
            ['label' => 'Feedback Received',       'url' => 'observation_feedback'],
            ['label' => 'Improvement Areas',       'url' => 'improvement_areas'],
        ]],

        ['label' => 'Mentorship', 'url' => null, 'icon' => 'fas fa-user-tie', 'subitems' => [
            ['label' => 'My Mentor',               'url' => 'my_mentor'],
            ['label' => 'Meetings',                'url' => 'mentor_meetings'],
            ['label' => 'Mentor Notes',            'url' => 'mentor_notes'],
        ]],

        ['label' => 'My Development', 'url' => null, 'icon' => 'fas fa-graduation-cap', 'subitems' => [
            ['label' => 'Competency Checklist',    'url' => 'competency_checklist'],
            ['label' => 'Progress Tracker',        'url' => 'development_progress'],
            ['label' => 'Learning Goals',          'url' => 'learning_goals'],
            ['label' => 'Reflection Journal',      'url' => 'reflection_journal'],
        ]],

        ['label' => 'Resources', 'url' => null, 'icon' => 'fas fa-folder-open', 'subitems' => [
            ['label' => 'Teaching Materials',      'url' => 'view_teaching_materials'],
            ['label' => 'Syllabus',                'url' => 'view_syllabus'],
            ['label' => 'Past Papers',             'url' => 'view_past_papers'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
        ]],
    ],

    // =========================================================================
    // 10 — Accountant
    // Workflow roles:
    //   Fee Structure: DRAFT (Director then approves)
    //   Student billing: generate bills, reconcile payments
    //   Payroll: PROCESS PAYMENT (after Director approves)
    //   Admission fees: record payment
    // =========================================================================
    10 => [
        ['label' => 'Dashboard', 'url' => 'school_accountant_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        // FEE STRUCTURE — Accountant DRAFTS (Admin+HT review, Director approves)
        ['label' => 'Fee Structure', 'url' => null, 'icon' => 'fas fa-list-alt', 'subitems' => [
            ['label' => 'Draft Fee Structure',     'url' => 'manage_fee_structure'],       // ACCOUNTANT DRAFTS
            ['label' => 'Current Structure',       'url' => 'manage_fee_structure'],
            ['label' => 'Fee Components',          'url' => 'manage_fee_structure'],
            ['label' => 'Boarding Fees',           'url' => 'manage_fee_structure'],
            ['label' => 'Transport Fees',          'url' => 'manage_fee_structure'],
            ['label' => 'Pending Director Approval','url' => 'manage_fee_structure'],
        ]],

        // STUDENT BILLING — bill students within approved structure
        ['label' => 'Student Billing', 'url' => null, 'icon' => 'fas fa-file-invoice', 'subitems' => [
            ['label' => 'Student Fee Accounts',    'url' => 'student_fees'],
            ['label' => 'Generate Bills',          'url' => 'student_fees'],
            ['label' => 'Payment Records',         'url' => 'manage_payments'],
            ['label' => 'Unmatched Payments',      'url' => 'unmatched_payments'],
            ['label' => 'Fee Defaulters',          'url' => 'fee_defaulters'],
            ['label' => 'Students with Balance',   'url' => 'students_with_balance'],
        ]],

        // PAYROLL — Accountant processes payment after Director approval
        ['label' => 'Payroll', 'url' => null, 'icon' => 'fas fa-wallet', 'subitems' => [
            ['label' => 'Manage Payrolls',         'url' => 'manage_payrolls'],            // view approved and process by permission
            ['label' => 'Payslips',                'url' => 'payslips'],
            ['label' => 'Payroll History',         'url' => 'payroll'],
        ]],

        // ADMISSION FEES — record when new student pays
        ['label' => 'Admission Fees', 'url' => 'manage_payments', 'icon' => 'fas fa-user-plus', 'subitems' => []],

        // EXPENDITURE
        ['label' => 'Expenditure', 'url' => null, 'icon' => 'fas fa-receipt', 'subitems' => [
            ['label' => 'Vendors & Suppliers',     'url' => 'vendors'],
            ['label' => 'Purchase Orders',         'url' => 'purchase_orders'],
            ['label' => 'Petty Cash',              'url' => 'petty_cash'],
            ['label' => 'Manage Expenses',         'url' => 'manage_expenses'],
        ]],

        // ACCOUNTS & BALANCES
        ['label' => 'Accounts & Balances', 'url' => null, 'icon' => 'fas fa-university', 'subitems' => [
            ['label' => 'Bank Accounts',           'url' => 'bank_accounts'],
            ['label' => 'Bank Transactions',       'url' => 'bank_transactions'],
            ['label' => 'M-Pesa Settlements',      'url' => 'mpesa_settlements'],
            ['label' => 'Reconciliation',          'url' => 'mpesa_reconciliation'],
            ['label' => 'Cash Reconciliation',     'url' => 'cash_reconciliation'],
        ]],

        // BUDGET & PLANNING
        ['label' => 'Budget & Planning', 'url' => null, 'icon' => 'fas fa-chart-line', 'subitems' => [
            ['label' => 'Budget Overview',         'url' => 'budget_overview'],
            ['label' => 'Budget vs Actuals',       'url' => 'budget_overview'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Financial Reports',       'url' => 'finance_reports'],
            ['label' => 'Payment Reports',         'url' => 'manage_payments'],
            ['label' => 'Enrollment Finance',      'url' => 'enrollment_reports'],
        ]],

        ['label' => 'Controls & Audit', 'url' => null, 'icon' => 'fas fa-shield-alt', 'subitems' => [
            ['label' => 'Transaction Approvals',   'url' => 'transaction_approvals'],
            ['label' => 'Audit Logs',              'url' => 'audit_logs'],
            ['label' => 'Adjustments',             'url' => 'adjustments'],
            ['label' => 'Exception Reports',       'url' => 'exception_reports'],
        ]],

        ['label' => 'Assets & Inventory', 'url' => null, 'icon' => 'fas fa-boxes', 'subitems' => [
            ['label' => 'Asset Purchases',         'url' => 'asset_purchases'],
            ['label' => 'Depreciation',            'url' => 'depreciation'],
            ['label' => 'Inventory Expenses',      'url' => 'inventory_expenses'],
        ]],
    ],

    // =========================================================================
    // 14 — Inventory Manager (Store Manager)
    // Stock, requisitions, purchase orders, uniform sales, library
    // =========================================================================
    14 => [
        ['label' => 'Dashboard', 'url' => 'store_manager_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'Inventory', 'url' => null, 'icon' => 'fas fa-boxes', 'subitems' => [
            ['label' => 'Manage Inventory',        'url' => 'manage_inventory'],
            ['label' => 'Stock Management',        'url' => 'manage_stock'],
            ['label' => 'Requisitions',            'url' => 'manage_requisitions'],
            ['label' => 'Low Stock Alerts',        'url' => 'manage_inventory'],
            ['label' => 'Stock Reports',           'url' => 'manage_inventory'],
        ]],

        ['label' => 'Uniform Sales', 'url' => null, 'icon' => 'fas fa-shopping-cart', 'subitems' => [
            ['label' => 'Manage Sales',            'url' => 'manage_uniform_sales'],
            ['label' => 'Sales Records',           'url' => 'manage_uniform_sales'],
        ]],

        ['label' => 'Vendors & Suppliers', 'url' => null, 'icon' => 'fas fa-truck', 'subitems' => [
            ['label' => 'Vendors',                 'url' => 'vendors'],
            ['label' => 'Purchase Orders',         'url' => 'purchase_orders'],
            ['label' => 'Goods Received',          'url' => 'purchase_orders'],
            ['label' => 'Invoices',                'url' => 'vendor_invoices'],
        ]],

        ['label' => 'Library', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [
            ['label' => 'Manage Books',            'url' => 'manage_library'],
            ['label' => 'Issue / Return',          'url' => 'manage_library'],
            ['label' => 'Overdue Books',           'url' => 'manage_library'],
        ]],

        ['label' => 'Catering Store', 'url' => null, 'icon' => 'fas fa-utensils', 'subitems' => [
            ['label' => 'Food Stock',              'url' => 'food_store'],
            ['label' => 'Food Orders',             'url' => 'food_store'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Inventory Reports',       'url' => 'manage_inventory'],
            ['label' => 'Purchase Reports',        'url' => 'purchase_orders'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
        ]],
    ],

    // =========================================================================
    // 16 — Cateress / Catering Manager
    // Menu planning, food store, daily meals, suppliers
    // =========================================================================
    16 => [
        ['label' => 'Dashboard', 'url' => 'catering_manager_cook_lead_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'Menu & Food', 'url' => null, 'icon' => 'fas fa-utensils', 'subitems' => [
            ['label' => 'Menu Planning',           'url' => 'menu_planning'],
            ['label' => 'Today\'s Menu',           'url' => 'manage_menus'],
            ['label' => 'Weekly Menu',             'url' => 'manage_menus'],
            ['label' => 'Manage Menus',            'url' => 'manage_menus'],
        ]],

        ['label' => 'Food Store', 'url' => null, 'icon' => 'fas fa-warehouse', 'subitems' => [
            ['label' => 'Food Inventory',          'url' => 'food_store'],
            ['label' => 'Food Stock Levels',       'url' => 'food_store'],
            ['label' => 'Low Stock Alerts',        'url' => 'food_store'],
            ['label' => 'Food Orders',             'url' => 'food_store'],
        ]],

        ['label' => 'Suppliers', 'url' => null, 'icon' => 'fas fa-truck', 'subitems' => [
            ['label' => 'Catering Suppliers',      'url' => 'vendors'],
            ['label' => 'Food Purchase Orders',    'url' => 'purchase_orders'],
        ]],

        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'Boarding Students',       'url' => 'catering_boarding_students'], // meal quantities only
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Meal Statistics',         'url' => 'manage_menus'],
            ['label' => 'Food Consumption',        'url' => 'food_store'],
        ]],

        ['label' => 'Announcements', 'url' => 'manage_announcements', 'icon' => 'fas fa-bullhorn', 'subitems' => []],
    ],

    // =========================================================================
    // 18 — Boarding Master / Matron / Housemother
    // Workflow role: ASSIGNS DORM to new boarders after admission is approved
    // Daily: roll call, exeats, health, dormitory management
    // =========================================================================
    18 => [
        ['label' => 'Dashboard', 'url' => 'matron_housemother_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        // ADMISSIONS — Boarding Master assigns dorm after Admin creates student record
        ['label' => 'New Boarders', 'url' => 'dormitory_management', 'icon' => 'fas fa-user-plus', 'subitems' => []],

        // BOARDING — core daily operations
        ['label' => 'Boarding', 'url' => null, 'icon' => 'fas fa-bed', 'subitems' => [
            ['label' => 'Manage Boarding',         'url' => 'manage_boarding'],
            ['label' => 'Roll Call',               'url' => 'boarding_roll_call'],
            ['label' => 'Dormitory Management',    'url' => 'dormitory_management'],
            ['label' => 'Room Assignments',        'url' => 'dormitory_management'],
            ['label' => 'Boarding Students List',  'url' => 'boarding_students'],
        ]],

        // PERMISSIONS & EXEATS
        ['label' => 'Permissions & Exeats', 'url' => null, 'icon' => 'fas fa-id-card', 'subitems' => [
            ['label' => 'Issue Exeat',             'url' => 'permissions_exeats'],
            ['label' => 'Pending Requests',        'url' => 'permissions_exeats'],
            ['label' => 'Exeat History',           'url' => 'permissions_exeats'],
            ['label' => 'End-of-Term Travel',      'url' => 'permissions_exeats'],
        ]],

        // HEALTH — boarding school health is boarding master's responsibility
        ['label' => 'Health & Medical', 'url' => null, 'icon' => 'fas fa-heartbeat', 'subitems' => [
            ['label' => 'Student Health Records',  'url' => 'student_health'],
            ['label' => 'Sick Bay Log',            'url' => 'sick_bay'],
            ['label' => 'Medical Alerts',          'url' => 'student_health'],
            ['label' => 'Vaccination Records',     'url' => 'student_health'],
        ]],

        // ATTENDANCE — evening/morning roll call
        ['label' => 'Attendance', 'url' => null, 'icon' => 'fas fa-clipboard-check', 'subitems' => [
            ['label' => 'Evening Roll Call',       'url' => 'boarding_roll_call'],
            ['label' => 'Morning Roll Call',       'url' => 'boarding_roll_call'],
            ['label' => 'Absence Reports',         'url' => 'attendance_reports'],
        ]],

        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'All Boarding Students',   'url' => 'boarding_students'],
            ['label' => 'Student Profiles',        'url' => 'student_profiles'],
            ['label' => 'Special Needs',           'url' => 'special_needs'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
            ['label' => 'Parent Notifications',    'url' => 'manage_communications'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Boarding Reports',        'url' => 'manage_boarding'],
            ['label' => 'Health Reports',          'url' => 'student_health'],
            ['label' => 'Exeat Reports',           'url' => 'permissions_exeats'],
        ]],
    ],

    // =========================================================================
    // 21 — Talent Development / HoD Activities
    // Activities, sports, clubs, competitions, school events
    // =========================================================================
    21 => [
        ['label' => 'Dashboard', 'url' => 'hod_talent_development_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'Activities', 'url' => null, 'icon' => 'fas fa-running', 'subitems' => [
            ['label' => 'Manage Activities',       'url' => 'manage_activities'],
            ['label' => 'Create Activity',         'url' => 'manage_activities'],
            ['label' => 'Clubs & Societies',       'url' => 'clubs_societies'],
            ['label' => 'Sports Teams',            'url' => 'sports'],
            ['label' => 'Competitions',            'url' => 'competitions'],
        ]],

        ['label' => 'Events & Calendar', 'url' => null, 'icon' => 'fas fa-calendar-alt', 'subitems' => [
            ['label' => 'School Events',           'url' => 'school_events'],
            ['label' => 'Assemblies',              'url' => 'assemblies'],
            ['label' => 'Event Schedule',          'url' => 'manage_calendar_events'],
            ['label' => 'View Calendar',           'url' => 'view_calendar'],
        ]],

        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'Activity Participants',   'url' => 'all_students'],
            ['label' => 'Participant Registration','url' => 'manage_activities'],
            ['label' => 'Achievement Records',     'url' => 'manage_activities'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Activity Reports',        'url' => 'manage_activities'],
            ['label' => 'Participation Reports',   'url' => 'manage_activities'],
        ]],
    ],

    // =========================================================================
    // 23 — Driver
    // Routes, vehicle, passenger list
    // =========================================================================
    23 => [
        ['label' => 'Dashboard',     'url' => 'driver_dashboard',        'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'Transport', 'url' => null, 'icon' => 'fas fa-bus', 'subitems' => [
            ['label' => 'My Routes',               'url' => 'my_routes'],
            ['label' => 'My Vehicle',              'url' => 'my_vehicle'],
        ]],

        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-users', 'subitems' => [
            ['label' => 'My Passengers',           'url' => 'transport_passengers'],
            ['label' => 'Passenger Attendance',    'url' => 'mark_attendance'],
        ]],

        ['label' => 'Announcements', 'url' => 'manage_announcements', 'icon' => 'fas fa-bullhorn', 'subitems' => []],
        ['label' => 'Messages',      'url' => 'manage_communications', 'icon' => 'fas fa-comments',  'subitems' => []],
    ],

    // =========================================================================
    // 24 — Chaplain / School Counselor
    // Counseling sessions, chapel, student welfare, referrals from Discipline
    // =========================================================================
    24 => [
        ['label' => 'Dashboard', 'url' => 'school_counselor_chaplain_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'Counseling', 'url' => null, 'icon' => 'fas fa-hands-helping', 'subitems' => [
            ['label' => 'Student Sessions',        'url' => 'student_counseling'],
            ['label' => 'Counseling Records',      'url' => 'counseling_records'],
            ['label' => 'Referrals',               'url' => 'counseling_referrals'],      // from Discipline Deputy
            ['label' => 'Case Management',         'url' => 'student_counseling'],
        ]],

        ['label' => 'Chapel', 'url' => null, 'icon' => 'fas fa-church', 'subitems' => [
            ['label' => 'Chapel Services',         'url' => 'chapel_services'],
            ['label' => 'Chapel Schedule',         'url' => 'chapel_services'],
        ]],

        ['label' => 'Student Welfare', 'url' => null, 'icon' => 'fas fa-heart', 'subitems' => [
            ['label' => 'At-Risk Students',        'url' => 'at_risk_students'],
            ['label' => 'Intervention Plans',      'url' => 'intervention_plans'],
            ['label' => 'Special Needs',           'url' => 'special_needs'],
            ['label' => 'Follow-ups',              'url' => 'welfare_follow_ups'],
        ]],

        ['label' => 'Parent Communication', 'url' => null, 'icon' => 'fas fa-users', 'subitems' => [
            ['label' => 'Schedule Meetings',       'url' => 'schedule_parent_meetings'],
            ['label' => 'Meeting Records',         'url' => 'parent_meeting_records'],
            ['label' => 'Send Notifications',      'url' => 'send_parent_notifications'],
        ]],

        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'Student Welfare',         'url' => 'student_welfare'],
            ['label' => 'Welfare Records',         'url' => 'student_counseling'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Counseling Reports',      'url' => 'student_counseling'],
            ['label' => 'Welfare Summary',         'url' => 'at_risk_students'],
        ]],
    ],

    // =========================================================================
    // 32 — Kitchen Staff
    // Shared support dashboard plus kitchen operations and personal self-service.
    // =========================================================================
    32 => [
        ['label' => 'Dashboard',       'url' => 'support_staff_dashboard',      'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'My Profile',      'url' => 'complete_staff_profile',        'icon' => 'fas fa-id-badge',       'subitems' => []],
        ['label' => 'My Attendance',   'url' => 'my_attendance',                 'icon' => 'fas fa-clipboard-check','subitems' => []],
        ['label' => 'Leave Requests',  'url' => 'staff_leave',                   'icon' => 'fas fa-calendar-check', 'subitems' => []],
        ['label' => 'Payslips & P9',   'url' => 'detailed_payslip',              'icon' => 'fas fa-file-invoice',   'subitems' => []],
        ['label' => 'Kitchen', 'url' => null, 'icon' => 'fas fa-utensils', 'subitems' => [
            ['label' => 'Meal Planning', 'url' => 'catering_boarding_students'],
            ['label' => 'Food Store',    'url' => 'food_store'],
        ]],
        ['label' => 'Announcements',   'url' => 'manage_announcements',          'icon' => 'fas fa-bullhorn',      'subitems' => []],
        ['label' => 'Messages',        'url' => 'manage_communications',         'icon' => 'fas fa-comments',      'subitems' => []],
    ],

    // =========================================================================
    // 33 — Security Staff
    // Shared support dashboard plus gate operations and personal self-service.
    // =========================================================================
    33 => [
        ['label' => 'Dashboard',       'url' => 'support_staff_dashboard',      'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'My Profile',      'url' => 'complete_staff_profile',        'icon' => 'fas fa-id-badge',       'subitems' => []],
        ['label' => 'My Attendance',   'url' => 'my_attendance',                 'icon' => 'fas fa-clipboard-check','subitems' => []],
        ['label' => 'Leave Requests',  'url' => 'staff_leave',                   'icon' => 'fas fa-calendar-check', 'subitems' => []],
        ['label' => 'Payslips & P9',   'url' => 'detailed_payslip',              'icon' => 'fas fa-file-invoice',   'subitems' => []],
        ['label' => 'Gate Operations', 'url' => null, 'icon' => 'fas fa-shield-alt', 'subitems' => [
            ['label' => 'Permissions & Exeats', 'url' => 'permissions_exeats'],
        ]],
        ['label' => 'Announcements',   'url' => 'manage_announcements',          'icon' => 'fas fa-bullhorn',      'subitems' => []],
        ['label' => 'Messages',        'url' => 'manage_communications',         'icon' => 'fas fa-comments',      'subitems' => []],
    ],

    // =========================================================================
    // 34 — Janitor / Cleaner
    // Shared support dashboard and personal self-service. Assigned maintenance
    // work is summarised on the dashboard until a dedicated staff task page exists.
    // =========================================================================
    34 => [
        ['label' => 'Dashboard',       'url' => 'support_staff_dashboard',      'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'My Profile',      'url' => 'complete_staff_profile',        'icon' => 'fas fa-id-badge',       'subitems' => []],
        ['label' => 'My Attendance',   'url' => 'my_attendance',                 'icon' => 'fas fa-clipboard-check','subitems' => []],
        ['label' => 'Leave Requests',  'url' => 'staff_leave',                   'icon' => 'fas fa-calendar-check', 'subitems' => []],
        ['label' => 'Payslips & P9',   'url' => 'detailed_payslip',              'icon' => 'fas fa-file-invoice',   'subitems' => []],
        ['label' => 'Announcements',   'url' => 'manage_announcements',          'icon' => 'fas fa-bullhorn',      'subitems' => []],
        ['label' => 'Messages',        'url' => 'manage_communications',         'icon' => 'fas fa-comments',      'subitems' => []],
    ],

    // =========================================================================
    // 63 — Deputy Head (Discipline)
    // DUAL ROLE: still a classroom teacher + discipline administrator.
    // As teacher       : marks own class attendance, creates lesson plans, enters marks.
    // As DH Discipline : manages discipline cases, truancy, conduct, parent meetings.
    // Sidebar ordering: MY TEACHING first (daily) → ADMIN duties below.
    // =========================================================================
    63 => [
        ['label' => 'Dashboard', 'url' => 'deputy_head_discipline_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        // ── MY TEACHING (daily teacher tasks) ────────────────────────────────
        ['label' => 'My Teaching', 'url' => null, 'icon' => 'fas fa-chalkboard', 'subitems' => [
            ['label' => 'My Timetable',            'url' => 'timetable'],                  // personal teaching schedule
            ['label' => 'My Class',                'url' => 'my_students_list'],           // if assigned a home class
            ['label' => 'Mark Attendance',         'url' => 'mark_attendance'],            // daily class register
            ['label' => 'My Lesson Plans',         'url' => 'manage_lesson_plans'],        // create own plans
            ['label' => 'Schemes of Work',         'url' => 'schemes_of_work'],
            ['label' => 'Enter Assessment Marks',  'url' => 'formative_assessments'],      // grade own students
            ['label' => 'Competency Ratings',      'url' => 'competencies_sheet'],         // CBC competency entries
        ]],

        // ── ADMIN: DISCIPLINE (primary domain) ───────────────────────────────
        ['label' => 'Discipline', 'url' => null, 'icon' => 'fas fa-gavel', 'subitems' => [
            ['label' => 'All Cases',               'url' => 'discipline_cases'],
            ['label' => 'Log New Case',            'url' => 'log_discipline_case'],
            ['label' => 'Open Cases',              'url' => 'discipline_cases'],
            ['label' => 'Suspensions / Expulsions','url' => 'discipline_cases'],
            ['label' => 'Sanctions',               'url' => 'policy_violations'],
            ['label' => 'Policy Violations',       'url' => 'policy_violations'],
        ]],

        // deputy HT Disciplinary: STAFF (disciplinary oversight) ─────────────────────────────
        ['label' => 'Staff', 'url' => null, 'icon' => 'fas fa-chalkboard-teacher', 'subitems' => [
            ['label' => 'All Staff',               'url' => 'manage_staff'],
            ['label' => 'Staff Onboarding',        'url' => 'staff_onboarding'],
            ['label' => 'Staff Lifecycle',         'url' => 'staff_lifecycle'],
            ['label' => 'Staff Appointments',      'url' => 'staff_appointments'],
            ['label' => 'Teachers',                'url' => 'all_teachers'],
            ['label' => 'Staff Performance',       'url' => 'staff_performance'],
            ['label' => 'Performance Reviews',     'url' => 'teacher_performance_reviews'],
            ['label' => 'Staff Attendance',        'url' => 'staff_attendance'],
            ['label' => 'Staff Leave',             'url' => 'staff_leave'],
        ]],

        // ── ADMIN: STUDENT CONDUCT ────────────────────────────────────────────
        ['label' => 'Student Conduct', 'url' => null, 'icon' => 'fas fa-user-check', 'subitems' => [
            ['label' => 'Behavior Logs',           'url' => 'conduct_reports'],
            ['label' => 'Conduct Grades',          'url' => 'conduct_reports'],
            ['label' => 'Rewards & Recognition',   'url' => 'conduct_reports'],
            ['label' => 'Refer to Counseling',     'url' => 'student_counseling'],         // → Chaplain/Counselor workflow
            ['label' => 'At-Risk Students',        'url' => 'student_welfare'],
        ]],

        // ── ADMIN: ATTENDANCE (truancy is a discipline matter) ────────────────
        ['label' => 'Truancy & Attendance', 'url' => null, 'icon' => 'fas fa-clipboard-check', 'subitems' => [
            ['label' => 'Daily Attendance Overview','url' => 'view_attendance'],
            ['label' => 'Attendance Reports',      'url' => 'attendance_reports'],
            ['label' => 'Absenteeism Trends',      'url' => 'attendance_trends'],
            ['label' => 'Parent Alerts (Truancy)', 'url' => 'manage_communications'],
        ]],

        // ── ADMIN: PARENT COMMUNICATION ───────────────────────────────────────
        ['label' => 'Parent Communication', 'url' => null, 'icon' => 'fas fa-users', 'subitems' => [
            ['label' => 'Parent Meetings',         'url' => 'parent_meetings'],
            ['label' => 'Send Notifications',      'url' => 'manage_communications'],
            ['label' => 'Meeting Records',         'url' => 'parent_meetings'],
        ]],

        // ── STUDENTS ──────────────────────────────────────────────────────────
        ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [
            ['label' => 'Discipline Students',     'url' => 'discipline_students'],
            ['label' => 'Student Profiles',        'url' => 'student_profiles'],
            ['label' => 'Special Needs',           'url' => 'special_needs'],
        ]],

        // ── HR (personal) ────────────────────────────────────────────────────
        ['label' => 'My HR', 'url' => null, 'icon' => 'fas fa-id-badge', 'subitems' => [
            ['label' => 'My Payslip',              'url' => 'detailed_payslip'],
            ['label' => 'My Attendance',           'url' => 'my_attendance'],
        ]],

        ['label' => 'Communications', 'url' => null, 'icon' => 'fas fa-comments', 'subitems' => [
            ['label' => 'Messages',                'url' => 'manage_communications'],
            ['label' => 'Announcements',           'url' => 'manage_announcements'],
        ]],

        ['label' => 'Reports', 'url' => null, 'icon' => 'fas fa-chart-bar', 'subitems' => [
            ['label' => 'Discipline Reports',      'url' => 'conduct_reports'],
            ['label' => 'Attendance Reports',      'url' => 'attendance_reports'],
            ['label' => 'Conduct Reports',         'url' => 'conduct_reports'],
            ['label' => 'Term Summary',            'url' => 'term_reports'],
        ]],

        ['label' => 'Library', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [['label' => 'Library', 'url' => 'manage_library']]],
    ],

    // =========================================================================
    // 64 — Generic Staff (office and operational staff)
    // Shared department-aware dashboard and complete personal self-service.
    // =========================================================================
    64 => [
        ['label' => 'Dashboard',       'url' => 'support_staff_dashboard',      'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'My Profile',      'url' => 'complete_staff_profile',        'icon' => 'fas fa-id-badge',       'subitems' => []],
        ['label' => 'Timetable',       'url' => 'timetable',                     'icon' => 'fas fa-calendar-alt',   'subitems' => []],
        ['label' => 'My Attendance',   'url' => 'my_attendance',                 'icon' => 'fas fa-clipboard-check','subitems' => []],
        ['label' => 'Leave Requests',  'url' => 'staff_leave',                   'icon' => 'fas fa-calendar-check', 'subitems' => []],
        ['label' => 'Payslips & P9',   'url' => 'detailed_payslip',              'icon' => 'fas fa-file-invoice',   'subitems' => []],
        ['label' => 'Announcements',   'url' => 'manage_announcements',          'icon' => 'fas fa-bullhorn',      'subitems' => []],
        ['label' => 'Messages',        'url' => 'manage_communications',         'icon' => 'fas fa-comments',      'subitems' => []],
    ],

];
