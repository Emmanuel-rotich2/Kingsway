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
        ['label' => 'Dashboard', 'url' => 'system_administrator_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        [
            'label' => 'Users & Roles',
            'url' => null,
            'icon' => 'fas fa-shield-alt',
            'subitems' => [
                ['label' => 'User Accounts', 'url' => 'manage_users'],
                ['label' => 'First School Admin', 'url' => 'bootstrap_school_administrator'],
                ['label' => 'Account Status', 'url' => 'account_status'],
                ['label' => 'Role Definitions', 'url' => 'manage_roles'],
                ['label' => 'Role-Permission Matrix', 'url' => 'role_permission_matrix'],
                ['label' => 'Resource Permissions', 'url' => 'resource_based_permissions'],
            ]
        ],

        [
            'label' => 'Security Center',
            'url' => null,
            'icon' => 'fas fa-lock',
            'subitems' => [
                ['label' => 'Authentication Logs', 'url' => 'authentication_logs'],
                ['label' => 'Failed Logins', 'url' => 'failed_login_attempts'],
                ['label' => 'Active Sessions', 'url' => 'active_sessions'],
                ['label' => 'Token Management', 'url' => 'token_management'],
                ['label' => 'IP Whitelist/Blacklist', 'url' => 'ip_whitelist_blacklist'],
            ]
        ],

        [
            'label' => 'Policy & Governance',
            'url' => null,
            'icon' => 'fas fa-gavel',
            'subitems' => [
                ['label' => 'Domain Isolation', 'url' => 'domain_isolation_rules'],
                ['label' => 'Time-Bound Access', 'url' => 'time_bound_access'],
                ['label' => 'Permission Policies', 'url' => 'permission_policies'],
                ['label' => 'Route Access Rules', 'url' => 'route_access_rules'],
            ]
        ],

        [
            'label' => 'Configuration',
            'url' => null,
            'icon' => 'fas fa-cogs',
            'subitems' => [
                ['label' => 'System Settings', 'url' => 'system_settings'],
                ['label' => 'Payment Accounts', 'url' => 'payment_integration_settings'],
                ['label' => 'Feature Flags', 'url' => 'feature_flags'],
                ['label' => 'Module Enablement', 'url' => 'module_enablement'],
                ['label' => 'Maintenance Mode', 'url' => 'maintenance_mode'],
            ]
        ],

        [
            'label' => 'Navigation & UI',
            'url' => null,
            'icon' => 'fas fa-sitemap',
            'subitems' => [
                ['label' => 'Route Registry', 'url' => 'route_registry'],
                ['label' => 'Role Navigation', 'url' => 'sidebar_menus'],
            ]
        ],

        [
            'label' => 'Monitoring',
            'url' => null,
            'icon' => 'fas fa-heartbeat',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'System Health', 'url' => 'system_health'],
                ['label' => 'Log Viewer', 'url' => 'log_viewer'],
                ['label' => 'Error Logs', 'url' => 'error_logs'],
                ['label' => 'Background Jobs', 'url' => 'background_jobs'],
                ['label' => 'API Metrics', 'url' => 'api_metrics'],
                ['label' => 'Rate Limiting', 'url' => 'rate_limiting_status'],
            ]
        ],

        [
            'label' => 'Data Governance',
            'url' => null,
            'icon' => 'fas fa-database',
            'subitems' => [
                ['label' => 'Migrations', 'url' => 'migrations'],
                ['label' => 'Backups', 'url' => 'backups'],
                ['label' => 'Data Retention', 'url' => 'data_retention'],
            ]
        ],

        [
            'label' => 'Audit & Forensics',
            'url' => null,
            'icon' => 'fas fa-search',
            'subitems' => [
                ['label' => 'Activity Logs', 'url' => 'activity_audit_logs'],
                ['label' => 'Permission Changes', 'url' => 'permission_changes'],
                ['label' => 'Policy Violations', 'url' => 'policy_violations'],
                ['label' => 'Security Incidents', 'url' => 'security_incidents'],
            ]
        ],

        [
            'label' => 'Developer Tools',
            'url' => null,
            'icon' => 'fas fa-code',
            'subitems' => [
                ['label' => 'API Explorer', 'url' => 'api_explorer'],
                ['label' => 'Webhook Registry', 'url' => 'webhook_registry'],
                ['label' => 'System Diagnostics', 'url' => 'system_diagnostics'],
                ['label' => 'Job Inspector', 'url' => 'job_inspector'],
            ]
        ],


        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_system_announcements'],
                ['label' => 'SMS', 'url' => 'manage_sms_configurations'],
                ['label' => 'Email', 'url' => 'manage_email_configurations'],
                ['label' => 'WhatsApp', 'url' => 'manage_whatsapp_configurations'],
            ]
        ],
    ],

    // =========================================================================
    // 3 — Director / School Owner
    // Strategic authority: approves admissions, schedules academic year/terms,
    // approves fee structure, creates + approves payroll, approves staff appts,
    // oversees all departments. NOT operational/classroom.
    // =========================================================================
    3 => [
        ['label' => 'Dashboard', 'url' => 'director_owner_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'QR Scanner', 'url' => 'qr-scanner', 'icon' => 'fas fa-qrcode', 'subitems' => []],

        // Admissions - Director oversight only (operational handled by School Admin, HT, Deputy Academic)
        [
            'label' => 'Admissions Oversight',
            'url' => null,
            'icon' => 'fas fa-user-check',
            'subitems' => [
                ['label' => 'Enrollment Reports', 'url' => 'enrollment_reports'],        // overview of admissions stats
            ]
        ],

        // Remaining approvals — these are Director approval gates in other workflows
        [
            'label' => 'Approvals',
            'url' => null,
            'icon' => 'fas fa-check-double',
            'subitems' => [
                ['label' => 'Fee Structure Approval', 'url' => 'fee_structure_approvals'],        // approve what accountant drafted
                ['label' => 'Finance Approvals', 'url' => 'finance_approvals'],           // approve large transactions
            ]
        ],

        // Academic Calendar — Director schedules Terms 1, 2, 3 - overview/oversight and make approvals where required
        [
            'label' => 'Academic Calendar',
            'url' => null,
            'icon' => 'fas fa-calendar',
            'subitems' => [
                ['label' => 'Academic Years', 'url' => 'academic_years'],
                ['label' => 'Manage Terms', 'url' => 'manage_terms'],               // DIRECTOR schedules terms
                ['label' => 'Year Calendar', 'url' => 'year_calendar'],
            ]
        ],

        // Staff — manage employees, approve appointments
        [
            'label' => 'Staff',
            'url' => null,
            'icon' => 'fas fa-chalkboard-teacher',
            'subitems' => [
                ['label' => 'All Staff', 'url' => 'manage_staff'],
                ['label' => 'Staff Onboarding', 'url' => 'staff_onboarding'],
                ['label' => 'Staff Lifecycle', 'url' => 'staff_lifecycle'],
                ['label' => 'Staff Appointments', 'url' => 'staff_appointments'],
                ['label' => 'Recruitment Applications', 'url' => 'manage_job_applications'],
                ['label' => 'Import Existing Staff', 'url' => 'import_existing_staff'],
                ['label' => 'Teachers', 'url' => 'all_teachers'],
                ['label' => 'Staff Performance', 'url' => 'staff_performance'],
                ['label' => 'Teacher Workload', 'url' => 'teacher_workload'],
                ['label' => 'Staff Attendance', 'url' => 'staff_attendance'],
                ['label' => 'Staff Leave', 'url' => 'staff_leave'],
            ]
        ],

        // Students — oversight, not operational
        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'Students Overview', 'url' => 'students_overview'],
                ['label' => 'Performance Overview', 'url' => 'student_performance'],
                ['label' => 'Discipline Overview', 'url' => 'discipline_cases'],
                ['label' => 'Special Needs', 'url' => 'special_needs'],
                ['label' => 'Student Leadership', 'url' => 'student_leadership'],
                ['label' => 'Alumni Management', 'url' => 'alumni_management'],
            ]
        ],

        // Finance — approve fee structure, view reports, budget oversight
        [
            'label' => 'Finance',
            'url' => null,
            'icon' => 'fas fa-coins',
            'subitems' => [
                ['label' => 'Financial Reports', 'url' => 'finance_reports'],
                ['label' => 'Budget Overview', 'url' => 'budget_overview'],
                ['label' => 'Fee Structure', 'url' => 'manage_fee_structure'],       // approve what accountant drafted
                ['label' => 'Extra Charges', 'url' => 'manage_extra_charges'],
                ['label' => 'Student Fees Overview', 'url' => 'student_fees'],
                ['label' => 'Petty Cash', 'url' => 'petty_cash'],
            ]
        ],

        // Payroll — Director creates AND approves (workflow role)
        [
            'label' => 'Payroll',
            'url' => null,
            'icon' => 'fas fa-wallet',
            'subitems' => [
                ['label' => 'Manage Payrolls', 'url' => 'manage_payrolls'],            // create, review and approve by permission
                ['label' => 'Payroll History', 'url' => 'payroll'],
                ['label' => 'Payslips', 'url' => 'payslips'],
                ['label' => 'Statutory Remittances', 'url' => 'statutory_remittances'],  // KRA/NHIF/NSSF tracking
            ]
        ],

        // Accounts — financial oversight
        [
            'label' => 'Accounts',
            'url' => null,
            'icon' => 'fas fa-university',
            'subitems' => [
                ['label' => 'Bank Accounts', 'url' => 'bank_accounts'],
                ['label' => 'Bank Transactions', 'url' => 'bank_transactions'],
                ['label' => 'M-Pesa Settlements', 'url' => 'mpesa_settlements'],
                ['label' => 'Unmatched Payments', 'url' => 'unmatched_payments'],
            ]
        ],

        // Payments — approve large vendor payments
        [
            'label' => 'Payments & Vendors',
            'url' => null,
            'icon' => 'fas fa-money-bill-wave',
            'subitems' => [
                ['label' => 'Manage Payments', 'url' => 'manage_payments'],
                ['label' => 'Supplier Payments', 'url' => 'supplier_payments'],
                ['label' => 'Parent Refunds', 'url' => 'parent_refunds'],
                ['label' => 'Student Fund Transfers', 'url' => 'student_fund_transfers'],
                ['label' => 'Payment Reconciliation', 'url' => 'payment_reconciliation'],
                ['label' => 'KCB Reconciliation', 'url' => 'kcb_disbursement_reconciliation'],
                ['label' => 'Vendors', 'url' => 'vendors'],
                ['label' => 'Purchase Orders', 'url' => 'purchase_orders'],
            ]
        ],

        // Academic — view/overview, not operational
        [
            'label' => 'Academic Overview',
            'url' => null,
            'icon' => 'fas fa-graduation-cap',
            'subitems' => [
                ['label' => 'Classes', 'url' => 'manage_classes'],
                ['label' => 'View Timetable', 'url' => 'manage_timetable'],
                ['label' => 'Academic Oversight', 'url' => 'academic_planning_oversight'],
                ['label' => 'View Results', 'url' => 'view_results'],
                ['label' => 'Report Cards', 'url' => 'report_cards'],
                ['label' => 'Exam Schedule', 'url' => 'exam_schedule'],
            ]
        ],

        // Attendance — oversight
        [
            'label' => 'Attendance',
            'url' => null,
            'icon' => 'fas fa-clipboard-check',
            'subitems' => [
                ['label' => 'View Attendance', 'url' => 'view_attendance'],
                ['label' => 'Attendance Reports', 'url' => 'attendance_reports'],
            ]
        ],

        // Transport — Director approves transport fees (workflow)
        [
            'label' => 'Transport',
            'url' => null,
            'icon' => 'fas fa-bus',
            'subitems' => [
                ['label' => 'Manage Transport', 'url' => 'manage_transport'],
                ['label' => 'Transportation Fees', 'url' => 'transport_fees'],            // Director approves transport fee rates
                ['label' => 'Routes Overview', 'url' => 'my_routes'],
            ]
        ],

        // Boarding — oversight
        [
            'label' => 'Boarding',
            'url' => null,
            'icon' => 'fas fa-bed',
            'subitems' => [
                ['label' => 'Boarding Overview', 'url' => 'manage_boarding'],
                ['label' => 'Dormitory Management', 'url' => 'dormitory_management'],
                ['label' => 'Permissions & Exeats', 'url' => 'permissions_exeats'],
            ]
        ],

        //view reports of everything that is happening in school - realtime, daily, weekly, monthly, each term, academic year(yealy )
        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Academic', 'url' => 'academic_reports'],
                ['label' => 'Performance', 'url' => 'performance_reports'],
                ['label' => 'Comparative', 'url' => 'comparative_reports'],
                ['label' => 'Attendance Trends', 'url' => 'attendance_trends'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
                ['label' => 'SMS', 'url' => 'manage_sms'],
                ['label' => 'Email', 'url' => 'manage_email'],
            ]
        ],

        [
            'label' => 'Website Management',
            'url' => null,
            'icon' => 'fas fa-globe',
            'subitems' => [
                ['label' => 'News & Stories', 'url' => 'manage_articles'],
                ['label' => 'Public Events', 'url' => 'manage_public_events'],
                ['label' => 'School Gallery', 'url' => 'manage_school_gallery'],
                ['label' => 'Job Vacancies', 'url' => 'manage_job_vacancies'],
                ['label' => 'Job Applications', 'url' => 'manage_job_applications'],
                ['label' => 'Page Content', 'url' => 'manage_page_content'],
                ['label' => 'Public Downloads', 'url' => 'manage_public_downloads'],
                ['label' => 'Contact Inquiries', 'url' => 'manage_inquiries'],
                ['label' => 'Website Overview', 'url' => 'manage_website'],
            ],
        ],

        // Activities — oversight, not operational and make recommendations to school admin
        [
            'label' => 'Activities',
            'url' => null,
            'icon' => 'fas fa-running',
            'subitems' => [
                ['label' => 'Manage Activities', 'url' => 'manage_activities'],
                ['label' => 'School Events', 'url' => 'school_events'],
                ['label' => 'Sports', 'url' => 'sports'],
            ]
        ],

        [
            'label' => 'Inventory & Store',
            'url' => null,
            'icon' => 'fas fa-boxes',
            'subitems' => [
                ['label' => 'Inventory Overview', 'url' => 'manage_inventory'], //view stock and every asset the school has.
                ['label' => 'Uniform Sales', 'url' => 'manage_uniform_sales'],
                ['label' => 'Catalogue Orders', 'url' => 'catalog_orders_management'],
                ['label' => 'Unit Traceability', 'url' => 'uniform_unit_tracking'],
                ['label' => 'Offers', 'url' => 'uniform_discounts'],
            ]
        ],

        ['label' => 'Library', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [['label' => 'Library Overview', 'url' => 'manage_library']]],
        ['label' => 'Parents', 'url' => null, 'icon' => 'fas fa-users-cog', 'subitems' => [['label' => 'All Parents', 'url' => 'all_parents'], ['label' => 'PTA Management', 'url' => 'pta_management']]],
    ],

    // =========================================================================
    // 4 — School Administrator
    // Operational backbone: admissions (intake→ID→class), staff onboarding,
    // student records, fee collection, transport coordination, payroll creation
    // =========================================================================
    4 => [
        ['label' => 'Dashboard', 'url' => 'school_administrative_officer_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'QR Scanner', 'url' => 'qr-scanner', 'icon' => 'fas fa-qrcode', 'subitems' => []],

        // ADMISSIONS — School Admin is the primary handler of the full workflow
        // Application → intake → documents → schedule interview → record fee → CREATE student → generate ID → assign class
        [
            'label' => 'Admissions',
            'url' => null,
            'icon' => 'fas fa-user-plus',
            'subitems' => [
                ['label' => 'New Applications', 'url' => 'new_applications'],           // receive applications
                ['label' => 'Applications Workspace', 'url' => 'manage_students_admissions'], // tabbed workspace
                ['label' => 'Class Placement', 'url' => 'admissions_class_placement'], // place student in class
                ['label' => 'Placement Tests', 'url' => 'placement_tests'],          // manage placement tests
            ]
        ],

        // STUDENTS — manage all student records
        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'All Students', 'url' => 'manage_students'],
                ['label' => 'Student Leadership', 'url' => 'student_leadership'],
                ['label' => 'Student Profiles', 'url' => 'student_profiles'],
                ['label' => 'ID Cards', 'url' => 'student_id_cards'],
                ['label' => 'Family Groups', 'url' => 'manage_family_groups'],
                ['label' => 'Special Needs', 'url' => 'special_needs'],
                ['label' => 'Student Promotion', 'url' => 'student_promotion'],          // end-of-year promotion
                ['label' => 'Alumni Management', 'url' => 'alumni_management'],
            ]
        ],


        // CLASSES — was missing, Admin assigns students to classes
        [
            'label' => 'Classes',
            'url' => null,
            'icon' => 'fas fa-school',
            'subitems' => [
                ['label' => 'Manage Classes', 'url' => 'manage_classes'],
                ['label' => 'Class Streams', 'url' => 'class_streams'],
                ['label' => 'Class Capacity', 'url' => 'class_capacity'],
            ]
        ],

        // STAFF — school Admin handles full HR operations + onboarding
        [
            'label' => 'Staff',
            'url' => null,
            'icon' => 'fas fa-chalkboard-teacher',
            'subitems' => [
                ['label' => 'All Staff', 'url' => 'manage_staff'],
                ['label' => 'Staff Onboarding', 'url' => 'staff_onboarding'],
                ['label' => 'Staff Lifecycle', 'url' => 'staff_lifecycle'],
                ['label' => 'Staff Appointments', 'url' => 'staff_appointments'],
                ['label' => 'Recruitment Applications', 'url' => 'manage_job_applications'],
                ['label' => 'Import Existing Staff', 'url' => 'import_existing_staff'],
                ['label' => 'Teachers', 'url' => 'all_teachers'],
                ['label' => 'Staff Security Passes', 'url' => 'staff_id_cards'],              // generate and issue lanyard passes
                ['label' => 'Staff Role Assignments', 'url' => 'staff_role_assignments'],
                ['label' => 'Staff Attendance', 'url' => 'staff_attendance'],
                ['label' => 'Leave Management', 'url' => 'staff_leave'],
                ['label' => 'Performance Overview', 'url' => 'staff_performance'],
            ]
        ],

        // FINANCE — Admin manages charges, collections, reconciliation and refunds
        [
            'label' => 'Finance',
            'url' => null,
            'icon' => 'fas fa-coins',
            'subitems' => [
                ['label' => 'Fee Structure', 'url' => 'manage_fee_structure'],       // review (not draft — that's accountant)
                ['label' => 'Extra Charges', 'url' => 'manage_extra_charges'],
                ['label' => 'Transport Fees', 'url' => 'transport_fees'],
                ['label' => 'Student Fee Accounts', 'url' => 'student_fees'],
                ['label' => 'Payment Register', 'url' => 'manage_payments'],
                ['label' => 'Supplier Payments', 'url' => 'supplier_payments'],
                ['label' => 'Parent Refunds', 'url' => 'parent_refunds'],
                ['label' => 'Student Fund Transfers', 'url' => 'student_fund_transfers'],
                ['label' => 'Payment Reconciliation', 'url' => 'payment_reconciliation'],
                ['label' => 'KCB Reconciliation', 'url' => 'kcb_disbursement_reconciliation'],
                ['label' => 'Unmatched Payments', 'url' => 'unmatched_payments'],
                ['label' => 'Students with Balance', 'url' => 'students_with_balance'],
                ['label' => 'Uniform Sales', 'url' => 'manage_uniform_sales'],
                ['label' => 'Catalogue Orders', 'url' => 'catalog_orders_management'],
                ['label' => 'Unit Traceability', 'url' => 'uniform_unit_tracking'],
                ['label' => 'Offers & Discounts', 'url' => 'uniform_discounts'],
            ]
        ],

        // PAYROLL — Admin creates payroll (Director then approves, Accountant pays)
        [
            'label' => 'Payroll',
            'url' => null,
            'icon' => 'fas fa-wallet',
            'subitems' => [
                ['label' => 'Create Payroll', 'url' => 'manage_payrolls'],            // Admin drafts payroll
                ['label' => 'Payroll Records', 'url' => 'payroll'],
                ['label' => 'Statutory Remittances', 'url' => 'statutory_remittances'],
            ]
        ],

        // TRANSPORT — Admin coordinates student transport assignments
        [
            'label' => 'Transport',
            'url' => null,
            'icon' => 'fas fa-bus',
            'subitems' => [
                ['label' => 'Manage Transport', 'url' => 'manage_transport'],
                ['label' => 'Routes', 'url' => 'my_routes'],
                ['label' => 'Route Assignments', 'url' => 'student_route_assignment'],
            ]
        ],

        // ACADEMIC — Admin view + report card distribution
        [
            'label' => 'Academic',
            'url' => null,
            'icon' => 'fas fa-graduation-cap',
            'subitems' => [
                ['label' => 'Academic Years', 'url' => 'academic_years'],
                ['label' => 'Manage Terms', 'url' => 'manage_terms'],               // Admin can also schedule terms
                ['label' => 'Year Calendar', 'url' => 'year_calendar'],
                ['label' => 'Term Transition', 'url' => 'term_transition'],
                ['label' => 'Year Rollover', 'url' => 'year_rollover'],
                ['label' => 'View Timetable', 'url' => 'manage_timetable'],
                ['label' => 'Academic Oversight', 'url' => 'academic_planning_oversight'],
                ['label' => 'Report Cards', 'url' => 'report_cards'],              // distribute report cards
                ['label' => 'CBC Curriculum', 'url' => 'curriculum_cbc'],
                ['label' => 'CBC Manager', 'url' => 'cbc_curriculum'],
                ['label' => 'Assessment Rubrics', 'url' => 'assessment_rubrics'],
                ['label' => 'Grading Scales', 'url' => 'grading_scales'],
                ['label' => 'Curriculum Proposals', 'url' => 'curriculum_proposals'],
                ['label' => 'Student Portfolio', 'url' => 'student_portfolio'],

            ]
        ],

        // ASSESSMENTS & EXAMS
        [
            'label' => 'Assessments & Exams',
            'url' => null,
            'icon' => 'fas fa-file-alt',
            'subitems' => [
                ['label' => 'Exam Schedule', 'url' => 'exam_schedule'],
                ['label' => 'Exam Timetable Drafts', 'url' => 'exam_timetable_drafts'],
                ['label' => 'Supervision Roster', 'url' => 'supervision_roster'],
                ['label' => 'Grading Status', 'url' => 'grading_status'],
                ['label' => 'Results Moderation', 'url' => 'exam_moderation'],
                ['label' => 'View Results', 'url' => 'view_results'],
            ]
        ],
        // ATTENDANCE
        [
            'label' => 'Attendance',
            'url' => null,
            'icon' => 'fas fa-clipboard-check',
            'subitems' => [
                ['label' => 'View Attendance', 'url' => 'view_attendance'],
            ]
        ],

        [
            'label' => 'Parents',
            'url' => null,
            'icon' => 'fas fa-users-cog',
            'subitems' => [
                ['label' => 'All Parents/Guardians', 'url' => 'all_parents'],
                ['label' => 'Parent Meetings', 'url' => 'parent_meetings'],
                ['label' => 'PTA Management', 'url' => 'pta_management'],
            ]
        ],

        [
            'label' => 'Events & Calendar',
            'url' => null,
            'icon' => 'fas fa-calendar-alt',
            'subitems' => [
                ['label' => 'School Events', 'url' => 'school_events'],
                ['label' => 'Manage Holidays', 'url' => 'manage_holidays'],
                ['label' => 'Staff Meetings', 'url' => 'manage_staff_meetings'],
                ['label' => 'Assemblies', 'url' => 'assemblies'],
            ]
        ],

        [
            'label' => 'Boarding',
            'url' => null,
            'icon' => 'fas fa-bed',
            'subitems' => [
                ['label' => 'Boarding Overview', 'url' => 'manage_boarding'],
                ['label' => 'Permissions & Exeats', 'url' => 'permissions_exeats'],         // Admin approves exeats
                ['label' => 'Roll Call', 'url' => 'boarding_roll_call'],
            ]
        ],

        [
            'label' => 'Discipline',
            'url' => null,
            'icon' => 'fas fa-gavel',
            'subitems' => [
                ['label' => 'Discipline Cases', 'url' => 'discipline_cases'],
                ['label' => 'Parent Notifications', 'url' => 'send_parent_notifications'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'Manage Messages', 'url' => 'manage_communications'],
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
                ['label' => 'SMS', 'url' => 'manage_sms'],
                ['label' => 'Email', 'url' => 'manage_email'],
                ['label' => 'WhatsApp', 'url' => 'manage_whatsapp'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Academic Reports', 'url' => 'academic_reports'],
                ['label' => 'Enrollment Reports', 'url' => 'enrollment_reports'],
                ['label' => 'Attendance Reports', 'url' => 'attendance_reports'],
                ['label' => 'Financial Summary', 'url' => 'finance_reports'],
            ]
        ],

        /*school public website management - manage website content(about school, mission, vision, logo, contact info, news, events, gallery)
        and all data about the school visible on our public pages like -  
            about.php
            contact.php
            downloads.php
            event-detail.php
            events.php - We have some events that must not be posted to web page, eg. internal events, so we need to manage which events are visible on the website and which are not.
            index.php
            job-detail.php
            news-article.php
            news.php - all department heads should have permissions to post news articles to the website, but only the school administrator can approve and publish them.
            careers.php - the jobs that the school advertises on the website should be managed by the school administrator, and only approved jobs should be visible on the website.
            admissions.php
            offline.html
        */
        [
            'label' => 'Website Management',
            'url' => 'manage_website',
            'icon' => 'fas fa-globe',
            'subitems' => []
        ],

        ['label' => 'Library', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [['label' => 'Manage Library', 'url' => 'manage_library']]],
        ['label' => 'Health Records', 'url' => null, 'icon' => 'fas fa-heartbeat', 'subitems' => [['label' => 'Student Health', 'url' => 'student_health'], ['label' => 'Sick Bay Log', 'url' => 'sick_bay']]],

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
        ['label' => 'QR Scanner', 'url' => 'qr-scanner', 'icon' => 'fas fa-qrcode', 'subitems' => []],

        // ADMISSIONS — HT review, interview, decision, oversight
        [
            'label' => 'Admissions',
            'url' => null,
            'icon' => 'fas fa-user-plus',
            'subitems' => [
                ['label' => 'Applications', 'url' => 'admissions_academic_applications'],
                ['label' => 'Applications Review', 'url' => 'admissions_headteacher_applications'],
                ['label' => 'Interview Queue', 'url' => 'admission_interviews'],
                ['label' => 'Admission Decisions', 'url' => 'admissions_admission_decisions'],
                ['label' => 'Approval Follow-up', 'url' => 'admissions_pending_admission_approvals'],
                ['label' => 'Enrollment Reports', 'url' => 'enrollment_reports'],
            ]
        ],

        // ACADEMIC — HT approves timetable + lesson plans (key workflow authority)
        [
            'label' => 'Academic',
            'url' => null,
            'icon' => 'fas fa-graduation-cap',
            'subitems' => [
                ['label' => 'Subjects', 'url' => 'manage_subjects'],
                ['label' => 'Timetable (Approve)', 'url' => 'manage_timetable'],           // APPROVE
                ['label' => 'Academic Planning', 'url' => 'academic_planning_oversight'], // schemes, lesson plans, coverage and approvals in one workspace
                ['label' => 'My Schemes of Work', 'url' => 'my_schemes_of_work'],          // teacher scope, when the HT also teaches
                ['label' => 'My Lesson Plans', 'url' => 'manage_lesson_plans'],             // teacher scope, when the HT also teaches
                ['label' => 'Content Reconciliation', 'url' => 'academic_content_reconciliation'],
                ['label' => 'Academic Calendar', 'url' => 'academic_calendar'],
                ['label' => 'Academic Years', 'url' => 'academic_years'],
                ['label' => 'CBC Curriculum', 'url' => 'curriculum_cbc'],
                ['label' => 'CBC Manager', 'url' => 'cbc_curriculum'],
                ['label' => 'Assessment Rubrics', 'url' => 'assessment_rubrics'],
                ['label' => 'Grading Scales', 'url' => 'grading_scales'],
                ['label' => 'Curriculum Proposals', 'url' => 'curriculum_proposals'],
                ['label' => 'Student Portfolio', 'url' => 'student_portfolio'],
            ]
        ],

        // ASSESSMENTS & EXAMS
        [
            'label' => 'Assessments & Exams',
            'url' => null,
            'icon' => 'fas fa-file-alt',
            'subitems' => [
                // Exam configuration belongs to the Deputy Academic/Admin
                // workflow. The Headteacher oversees the resulting school
                // schedule rather than maintaining a second setup screen.
                ['label' => 'School Exam Schedule', 'url' => 'exam_schedule'],
                ['label' => 'Exam Timetable Review', 'url' => 'exam_timetable_drafts'],
                ['label' => 'Supervision Roster', 'url' => 'supervision_roster'],
                ['label' => 'Grading Status', 'url' => 'grading_status'],
                ['label' => 'Results Moderation', 'url' => 'exam_moderation'],
                ['label' => 'Official Results', 'url' => 'view_results'],
                ['label' => 'Results Analysis', 'url' => 'results_analysis'],
                ['label' => 'Report Cards (Approve)', 'url' => 'report_cards'],              // HT signs off on report cards
            ]
        ],

        // STAFF — HT interviews candidates, manages performance
        [
            'label' => 'Staff',
            'url' => null,
            'icon' => 'fas fa-chalkboard-teacher',
            'subitems' => [
                ['label' => 'All Staff', 'url' => 'manage_staff'],
                ['label' => 'Staff Onboarding', 'url' => 'staff_onboarding'],
                ['label' => 'Staff Lifecycle', 'url' => 'staff_lifecycle'],
                ['label' => 'Staff Appointments', 'url' => 'staff_appointments'],
                ['label' => 'Recruitment Applications', 'url' => 'manage_job_applications'],
                ['label' => 'Teachers', 'url' => 'all_teachers'],
                ['label' => 'Performance Reviews', 'url' => 'teacher_performance_reviews'],
                ['label' => 'Teacher Workload', 'url' => 'teacher_workload'],
                ['label' => 'Staff Attendance', 'url' => 'staff_attendance'],
                ['label' => 'Leave Approval', 'url' => 'staff_leave'],
            ]
        ],

        // CLASSES — HT also must manage classes, assign teachers to classes, and approve class capacity
        [
            'label' => 'Classes',
            'url' => null,
            'icon' => 'fas fa-school',
            'subitems' => [
                ['label' => 'Manage Classes', 'url' => 'manage_classes'],
                ['label' => 'Class Streams', 'url' => 'class_streams'],
                ['label' => 'Class Capacity', 'url' => 'class_capacity'],
            ]
        ],

        // STUDENTS
        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'Students Overview', 'url' => 'students_overview'],
                ['label' => 'Performance Overview', 'url' => 'student_performance'],
                ['label' => 'Discipline Cases', 'url' => 'discipline_cases'],
                ['label' => 'Counseling', 'url' => 'student_counseling'],
                ['label' => 'Special Needs', 'url' => 'special_needs'],
                ['label' => 'Health Records', 'url' => 'student_health'],
                ['label' => 'Student Leadership', 'url' => 'student_leadership'],
                ['label' => 'Alumni Management', 'url' => 'alumni_management'],
            ]
        ],

        // ATTENDANCE
        [
            'label' => 'Attendance',
            'url' => null,
            'icon' => 'fas fa-clipboard-check',
            'subitems' => [
                ['label' => 'View Attendance', 'url' => 'view_attendance'],
                ['label' => 'Attendance Reports', 'url' => 'attendance_reports'],
            ]
        ],

        // FINANCE REVIEW — HT reviews fee structure before Director approves
        [
            'label' => 'Finance Review',
            'url' => null,
            'icon' => 'fas fa-coins',
            'subitems' => [
                ['label' => 'Fee Structure Review', 'url' => 'manage_fee_structure'],       // REVIEW before Director approves
                ['label' => 'Financial Reports', 'url' => 'finance_reports'],
                ['label' => 'Budget Overview', 'url' => 'budget_overview'],
            ]
        ],

        [
            'label' => 'Parents',
            'url' => null,
            'icon' => 'fas fa-users-cog',
            'subitems' => [
                ['label' => 'All Parents', 'url' => 'all_parents'],
                ['label' => 'Parent Meetings', 'url' => 'parent_meetings'],
                ['label' => 'PTA Management', 'url' => 'pta_management'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Academic Reports', 'url' => 'academic_reports'],
                ['label' => 'Performance Analysis', 'url' => 'performance_analysis'],
                ['label' => 'Term Reports', 'url' => 'term_reports'],
                ['label' => 'Comparative', 'url' => 'comparative_reports'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
                ['label' => 'SMS', 'url' => 'manage_sms'],
            ]
        ],

        ['label' => 'Boarding', 'url' => null, 'icon' => 'fas fa-bed', 'subitems' => [['label' => 'Boarding Overview', 'url' => 'manage_boarding'], ['label' => 'Roll Call', 'url' => 'boarding_roll_call']]],
        ['label' => 'Transport', 'url' => null, 'icon' => 'fas fa-bus', 'subitems' => [['label' => 'Transport Overview', 'url' => 'manage_transport']]],
        ['label' => 'Activities', 'url' => null, 'icon' => 'fas fa-running', 'subitems' => [['label' => 'Manage Activities', 'url' => 'manage_activities'], ['label' => 'School Events', 'url' => 'school_events']]],
        ['label' => 'Library', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [['label' => 'Manage Library', 'url' => 'manage_library']]],
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
        [
            'label' => 'My Teaching',
            'url' => null,
            'icon' => 'fas fa-chalkboard',
            'subitems' => [
                ['label' => 'My Timetable', 'url' => 'timetable'],                  // view personal teaching schedule
                ['label' => 'My Class', 'url' => 'my_students_list'],           // if assigned a home class
                ['label' => 'Mark Class Attendance', 'url' => 'class_mark_attendance'],            // daily class register
                ['label' => 'My Lesson Plans', 'url' => 'manage_lesson_plans'],        // CREATE own plans (not just review)
                ['label' => 'Schemes of Work', 'url' => 'schemes_of_work'],            // plan the term
                ['label' => 'Content Reconciliation', 'url' => 'academic_content_reconciliation'],
                ['label' => 'Enter Assessment Marks', 'url' => 'formative_assessments'],      // grade own students
                ['label' => 'Competency Ratings', 'url' => 'competencies_sheet'],         // CBC competency entries
            ]
        ],

        // ── ADMIN: ADMISSIONS ─────────────────────────────────────────────────
        [
            'label' => 'Admissions',
            'url' => null,
            'icon' => 'fas fa-user-plus',
            'subitems' => [
                ['label' => 'Academic Applications', 'url' => 'admissions_academic_applications'],
                ['label' => 'Class Placement', 'url' => 'admissions_class_placement'], // recommend which class
                ['label' => 'Placement Tests', 'url' => 'placement_tests'],
            ]
        ],

        // ── ADMIN: ACADEMIC MANAGEMENT ────────────────────────────────────────
        [
            'label' => 'Academic',
            'url' => null,
            'icon' => 'fas fa-graduation-cap',
            'subitems' => [
                ['label' => 'Manage Classes', 'url' => 'manage_classes'],
                ['label' => 'Class Streams', 'url' => 'class_streams'],
                ['label' => 'Learning Areas', 'url' => 'manage_subjects'],
                ['label' => 'CBC Curriculum', 'url' => 'curriculum_cbc'],
                ['label' => 'CBC Manager', 'url' => 'cbc_curriculum'],
                ['label' => 'Assessment Rubrics', 'url' => 'assessment_rubrics'],
                ['label' => 'Grading Scales', 'url' => 'grading_scales'],
                ['label' => 'Curriculum Proposals', 'url' => 'curriculum_proposals'],
                ['label' => 'Student Portfolio', 'url' => 'student_portfolio'],
                ['label' => 'Academic Years', 'url' => 'academic_years'],
                ['label' => 'Academic Calendar', 'url' => 'academic_calendar'],
                ['label' => 'Alumni Management', 'url' => 'alumni_management'],
            ]
        ],

        // ── ADMIN: TIMETABLE (Deputy ASSIGNS teachers — key workflow step) ────
        [
            'label' => 'Timetable Management',
            'url' => null,
            'icon' => 'fas fa-calendar-alt',
            'subitems' => [
                ['label' => 'All Timetables', 'url' => 'manage_timetable'], //draft timetables for grades 4-9 and assign teachers to the learning areas in the timetable
                ['label' => 'Assign Teachers', 'url' => 'teacher_subject_assignment'],           // WORKFLOW: Deputy assigns teachers
                ['label' => 'Teacher Timetables', 'url' => 'teacher_timetables'],
                ['label' => 'Supervision Roster', 'url' => 'supervision_roster'],
            ]
        ],

        // ── ADMIN: LESSON PLAN REVIEW (Deputy reviews before HT approves) ────
        [
            'label' => 'Lesson Plan Review',
            'url' => null,
            'icon' => 'fas fa-book-open',
            'subitems' => [
                ['label' => 'All Lesson Plans', 'url' => 'all_lesson_plans'],
                ['label' => 'Pending My Review', 'url' => 'lesson_plan_approval'],       // WORKFLOW: Deputy → HT
                ['label' => 'Approved Plans', 'url' => 'approved_lesson_plans'],
                ['label' => 'By Class', 'url' => 'lesson_plans_by_class'],
                ['label' => 'By Teacher', 'url' => 'lesson_plans_by_teacher'],
            ]
        ],

        // ── ADMIN: ASSESSMENTS & EXAMS ────────────────────────────────────────
        [
            'label' => 'Assessments & Exams',
            'url' => null,
            'icon' => 'fas fa-file-alt',
            'subitems' => [
                ['label' => 'Exam Setup', 'url' => 'exam_setup'],
                ['label' => 'Draft Exam Timetable', 'url' => 'exam_timetable_drafts'],
                ['label' => 'Exam Schedule', 'url' => 'exam_schedule'],
                ['label' => 'Grading Status', 'url' => 'grading_status'],             // track which teachers have graded
                ['label' => 'Results Moderation', 'url' => 'exam_moderation'],
                ['label' => 'View All Results', 'url' => 'view_results'],
                ['label' => 'Results Analysis', 'url' => 'results_analysis'],
                ['label' => 'Report Cards', 'url' => 'report_cards'],
                ['label' => 'National Exams', 'url' => 'national_exams'],
            ]
        ],

        // ── ADMIN: TEACHER MANAGEMENT ─────────────────────────────────────────
        [
            'label' => 'Teacher Management',
            'url' => null,
            'icon' => 'fas fa-chalkboard-teacher',
            'subitems' => [
                ['label' => 'All Teachers', 'url' => 'all_teachers'],
                ['label' => 'Assign Class Teachers', 'url' => 'assign_class_teachers'],
                ['label' => 'Subject Allocation', 'url' => 'assign_subjects_to_teachers'],
                ['label' => 'Teacher Workload', 'url' => 'teacher_workload'],
                ['label' => 'Performance Reviews', 'url' => 'teacher_performance_reviews'],
            ]
        ],

        // ── STUDENTS ──────────────────────────────────────────────────────────
        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'Academic Students', 'url' => 'academic_students'],
                ['label' => 'Performance Overview', 'url' => 'student_performance'],
                ['label' => 'Student Promotion', 'url' => 'student_promotion'],
                ['label' => 'Special Needs', 'url' => 'special_needs'],
                ['label' => 'Student Leadership', 'url' => 'student_leadership'],
            ]
        ],

        // ── ATTENDANCE (school-wide oversight, not just own class) ────────────
        [
            'label' => 'Attendance',
            'url' => null,
            'icon' => 'fas fa-clipboard-check',
            'subitems' => [
                ['label' => 'Daily Overview', 'url' => 'view_attendance'],
                ['label' => 'Submit Attendance', 'url' => 'daily_attendance'],
                ['label' => 'Attendance Reports', 'url' => 'attendance_reports'],
                ['label' => 'Attendance Trends', 'url' => 'attendance_trends'],
            ]
        ],

        // ── REPORTS ───────────────────────────────────────────────────────────
        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Academic Reports', 'url' => 'academic_reports'],
                ['label' => 'Performance Analysis', 'url' => 'performance_analysis'],
                ['label' => 'Term Reports', 'url' => 'term_reports'],
                ['label' => 'Comparative Reports', 'url' => 'comparative_reports'],
                ['label' => 'Enrollment Trends', 'url' => 'enrollment_trends'],
            ]
        ],

        // ── HR (personal) ────────────────────────────────────────────────────
        [
            'label' => 'Staff',
            'url' => null,
            'icon' => 'fas fa-chalkboard-teacher',
            'subitems' => [
                ['label' => 'Staff Appointments', 'url' => 'staff_appointments'],
            ]
        ],

        [
            'label' => 'My HR',
            'url' => null,
            'icon' => 'fas fa-id-badge',
            'subitems' => [
                ['label' => 'My Payslip', 'url' => 'detailed_payslip'],
                ['label' => 'Leave Requests', 'url' => 'staff_leave'],
                ['label' => 'My Attendance', 'url' => 'my_attendance'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
                ['label' => 'Parent Messaging', 'url' => 'manage_email'],
            ]
        ],

        [
            'label' => 'Website Management',
            'url' => null,
            'icon' => 'fas fa-globe',
            'subitems' => [
                ['label' => 'News Articles', 'url' => 'manage_articles'],
                ['label' => 'Events', 'url' => 'manage_public_events'],
                ['label' => 'Applications', 'url' => 'manage_job_applications'],
            ]
        ],

        ['label' => 'Library', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [['label' => 'Library', 'url' => 'manage_library']]],
    ],

    // =========================================================================
    // 7 — Class Teacher
    // Daily: own class attendance, draft timetable, create lesson plans,
    // enter assessment marks, log discipline, communicate with parents
    // =========================================================================
    7 => [
        ['label' => 'Dashboard', 'url' => 'class_teacher_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'QR Scanner', 'url' => 'qr-scanner', 'icon' => 'fas fa-qrcode', 'subitems' => []],

        [
            'label' => 'Academic',
            'url' => null,
            'icon' => 'fas fa-graduation-cap',
            'subitems' => [
                ['label' => 'CBC Curriculum', 'url' => 'curriculum_cbc'],
                ['label' => 'Assessment Rubrics', 'url' => 'assessment_rubrics'],
                ['label' => 'Grading Scales', 'url' => 'grading_scales'],
                ['label' => 'Curriculum Proposals', 'url' => 'curriculum_proposals'],
            ]
        ],

        // MY CLASS — primary daily view
        [
            'label' => 'My Class',
            'url' => null,
            'icon' => 'fas fa-users',
            'subitems' => [
                ['label' => 'Student List', 'url' => 'my_students_list'],
                ['label' => 'Student Profiles', 'url' => 'student_profiles'],
                ['label' => 'Class Performance', 'url' => 'my_students_performance'],
                ['label' => 'Student Portfolio', 'url' => 'student_portfolio'],
                ['label' => 'Student Leadership', 'url' => 'student_leadership'],
                ['label' => 'Special Needs Students', 'url' => 'special_needs_students'],
            ]
        ],

        // ATTENDANCE — Class teacher marks daily attendance
        [
            'label' => 'Attendance',
            'url' => null,
            'icon' => 'fas fa-clipboard-check',
            'subitems' => [
                ['label' => 'Mark Class Attendance', 'url' => 'class_mark_attendance'],            // daily task
                ['label' => 'Attendance History', 'url' => 'class_attendance_history'],
                ['label' => 'Absentees Today', 'url' => 'today_absentees'],
            ]
        ],

        // TIMETABLE — Class teacher DRAFTS timetable (Deputy reviews, HT approves)
        [
            'label' => 'Timetable',
            'url' => null,
            'icon' => 'fas fa-calendar-alt',
            'subitems' => [
                ['label' => 'Draft My Timetable', 'url' => 'manage_timetable'],           // DRAFT — key workflow step
                ['label' => 'View Approved Timetable', 'url' => 'timetable'],
            ]
        ],

        // LESSON PLANS — one scoped workspace; creation is inside Manage Lesson Plans
        [
            'label' => 'Lesson Plans',
            'url' => null,
            'icon' => 'fas fa-book',
            'subitems' => [
                ['label' => 'My Lesson Plans', 'url' => 'manage_lesson_plans'],
                ['label' => 'Schemes of Work', 'url' => 'my_schemes_of_work'],
            ]
        ],

        // ASSESSMENTS — enter marks for own class
        [
            'label' => 'Assessments',
            'url' => null,
            'icon' => 'fas fa-tasks',
            'subitems' => [
                // Creation and editing live inside one scoped formative workspace.
                // Summative exams are created only by publishing the leadership timetable.
                ['label' => 'Formative Assessments', 'url' => 'my_cats'],
                ['label' => 'Enter Formative Scores', 'url' => 'enter_marks'],
                ['label' => 'Summative Exam Scores', 'url' => 'enter_exam_results'],
                ['label' => 'Class Results', 'url' => 'class_results'],
                ['label' => 'CBC Competencies', 'url' => 'competencies_sheet'],
            ]
        ],

        [
            'label' => 'Examinations',
            'url' => null,
            'icon' => 'fas fa-file-signature',
            'subitems' => [
                ['label' => 'Exam Schedule', 'url' => 'exam_schedule'],
            ]
        ],

        // DISCIPLINE — log incidents in own class
        [
            'label' => 'Discipline',
            'url' => null,
            'icon' => 'fas fa-gavel',
            'subitems' => [
                ['label' => 'Log Incident', 'url' => 'log_discipline_incident'],
                ['label' => 'Behavior Notes', 'url' => 'student_behavior_notes'],
                ['label' => 'Conduct Grades', 'url' => 'class_conduct_grades'],
            ]
        ],

        // PARENTS — communicate with own class parents
        [
            'label' => 'Parents',
            'url' => null,
            'icon' => 'fas fa-users-cog',
            'subitems' => [
                ['label' => 'Parent Contacts', 'url' => 'class_parent_contacts'],
                ['label' => 'Send Message', 'url' => 'send_class_message'],
                ['label' => 'Meeting Records', 'url' => 'parent_meeting_records'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Class Report', 'url' => 'generate_class_report'],
                ['label' => 'Progress Reports', 'url' => 'student_progress_reports'],
                ['label' => 'Report Cards', 'url' => 'class_report_cards'],
            ]
        ],

        // ── HR (personal) ────────────────────────────────────────────────────
        [
            'label' => 'My HR',
            'url' => null,
            'icon' => 'fas fa-id-badge',
            'subitems' => [
                ['label' => 'My Payslip', 'url' => 'detailed_payslip'],
                ['label' => 'Leave Requests', 'url' => 'staff_leave'],
                ['label' => 'My Attendance', 'url' => 'my_attendance'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
            ]
        ],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 8 — Subject Teacher
    // Teaches across multiple classes. Creates lesson plans, enters subject marks.
    // =========================================================================
    8 => [
        ['label' => 'Dashboard', 'url' => 'subject_teacher_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'QR Scanner', 'url' => 'qr-scanner', 'icon' => 'fas fa-qrcode', 'subitems' => []],

        [
            'label' => 'Academic',
            'url' => null,
            'icon' => 'fas fa-graduation-cap',
            'subitems' => [
                ['label' => 'CBC Curriculum', 'url' => 'curriculum_cbc'],
                ['label' => 'Assessment Rubrics', 'url' => 'assessment_rubrics'],
                ['label' => 'Grading Scales', 'url' => 'grading_scales'],
                ['label' => 'Curriculum Proposals', 'url' => 'curriculum_proposals'],
            ]
        ],

        [
            'label' => 'My Subjects',
            'url' => null,
            'icon' => 'fas fa-book-reader',
            'subitems' => [
                ['label' => 'Subject Overview', 'url' => 'my_subjects_overview'],
                ['label' => 'Classes I Teach', 'url' => 'my_classes_taught'],
                ['label' => 'Syllabus Coverage', 'url' => 'my_subject_syllabus'],
            ]
        ],

        [
            'label' => 'My Students',
            'url' => null,
            'icon' => 'fas fa-users',
            'subitems' => [
                ['label' => 'Subject Student List', 'url' => 'subject_students_list'],
                ['label' => 'Subject Learners', 'url' => 'students_by_class'],
                ['label' => 'Performance Tracking', 'url' => 'student_subject_performance'],
                ['label' => 'Student Portfolio', 'url' => 'student_portfolio'],
                ['label' => 'Student Leadership', 'url' => 'student_leadership'],
            ]
        ],

        // ATTENDANCE — mark subject attendance
        [
            'label' => 'Attendance',
            'url' => null,
            'icon' => 'fas fa-clipboard-check',
            'subitems' => [
                ['label' => 'Mark Class Attendance', 'url' => 'class_mark_attendance'],
                ['label' => 'Attendance History', 'url' => 'view_attendance'],
            ]
        ],

        // TIMETABLE — view (deputy assigns, not subject teacher)
        ['label' => 'My Teaching Timetable', 'url' => 'timetable', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],

        // LESSON PLANS — one scoped workspace; creation is inside Manage Lesson Plans
        [
            'label' => 'Lesson Plans',
            'url' => null,
            'icon' => 'fas fa-book',
            'subitems' => [
                ['label' => 'My Lesson Plans', 'url' => 'manage_lesson_plans'], // view own plans
                // One shared teacher entry; the page resolves both
                // class-teacher and subject-teacher scopes.
                ['label' => 'Schemes of Work', 'url' => 'my_schemes_of_work'],
            ]
        ],

        // ASSESSMENTS — enter marks per subject
        [
            'label' => 'Assessments',
            'url' => null,
            'icon' => 'fas fa-tasks',
            'subitems' => [
                ['label' => 'Formative Assessments', 'url' => 'my_cats'],
                ['label' => 'Enter Formative Scores', 'url' => 'subject_grade_entry'],
                ['label' => 'Subject Grading Status', 'url' => 'subject_grading_status'],
            ]
        ],

        [
            'label' => 'Examinations',
            'url' => null,
            'icon' => 'fas fa-file-alt',
            'subitems' => [
                ['label' => 'Exam Schedule', 'url' => 'subject_exam_schedule'],
                ['label' => 'Enter Summative Scores', 'url' => 'enter_exam_results'],
                ['label' => 'My Subject Results', 'url' => 'subject_results_summary'],
            ]
        ],

        [
            'label' => 'Resources',
            'url' => null,
            'icon' => 'fas fa-folder-open',
            'subitems' => [
                ['label' => 'Teaching Materials', 'url' => 'teaching_materials'],
                ['label' => 'Upload Resource', 'url' => 'upload_teaching_resource'],
                ['label' => 'Past Papers', 'url' => 'past_papers'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Subject Report', 'url' => 'generate_subject_report'],
                ['label' => 'Class Comparison', 'url' => 'subject_class_comparison'],
            ]
        ],

        // ── HR (personal) ────────────────────────────────────────────────────
        [
            'label' => 'My HR',
            'url' => null,
            'icon' => 'fas fa-id-badge',
            'subitems' => [
                ['label' => 'My Payslip', 'url' => 'detailed_payslip'],
                ['label' => 'Leave Requests', 'url' => 'staff_leave'],
                ['label' => 'My Attendance', 'url' => 'my_attendance'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
            ]
        ],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 9 — Intern / Student Teacher
    // Learning role: observe, draft lesson plans under supervision, limited access
    // =========================================================================
    9 => [
        ['label' => 'Dashboard', 'url' => 'intern_student_teacher_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        [
            'label' => 'Academic',
            'url' => null,
            'icon' => 'fas fa-graduation-cap',
            'subitems' => [
                ['label' => 'CBC Curriculum', 'url' => 'curriculum_cbc'],
                ['label' => 'Assessment Rubrics', 'url' => 'assessment_rubrics'],
                ['label' => 'Grading Scales', 'url' => 'grading_scales'],
                ['label' => 'Curriculum Proposals', 'url' => 'curriculum_proposals'],
            ]
        ],

        [
            'label' => 'My Assignments',
            'url' => null,
            'icon' => 'fas fa-clipboard-list',
            'subitems' => [
                ['label' => 'Assigned Classes', 'url' => 'intern_assigned_classes'],
                ['label' => 'Assigned Subjects', 'url' => 'intern_assigned_subjects'],
                ['label' => 'My Schedule', 'url' => 'intern_schedule'],
            ]
        ],

        [
            'label' => 'Lesson Plans',
            'url' => null,
            'icon' => 'fas fa-book',
            'subitems' => [
                ['label' => 'My Lesson Plans', 'url' => 'manage_lesson_plans'],
                ['label' => 'Mentor Feedback', 'url' => 'mentor_feedback'],
            ]
        ],

        [
            'label' => 'My Students',
            'url' => null,
            'icon' => 'fas fa-users',
            'subitems' => [
                ['label' => 'View Student List', 'url' => 'view_class_lists'],
                ['label' => 'View Student Info', 'url' => 'view_student_info'],
                ['label' => 'Student Leadership', 'url' => 'student_leadership'],
            ]
        ],

        ['label' => 'Timetable', 'url' => 'timetable', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],

        [
            'label' => 'Observations',
            'url' => null,
            'icon' => 'fas fa-eye',
            'subitems' => [
                ['label' => 'Observation Schedule', 'url' => 'observation_schedule'],
                ['label' => 'Feedback Received', 'url' => 'observation_feedback'],
                ['label' => 'Improvement Areas', 'url' => 'improvement_areas'],
            ]
        ],

        [
            'label' => 'Mentorship',
            'url' => null,
            'icon' => 'fas fa-user-tie',
            'subitems' => [
                ['label' => 'My Mentor', 'url' => 'my_mentor'],
                ['label' => 'Meetings', 'url' => 'mentor_meetings'],
                ['label' => 'Mentor Notes', 'url' => 'mentor_notes'],
            ]
        ],

        [
            'label' => 'My Development',
            'url' => null,
            'icon' => 'fas fa-graduation-cap',
            'subitems' => [
                ['label' => 'Competency Checklist', 'url' => 'competency_checklist'],
                ['label' => 'Progress Tracker', 'url' => 'development_progress'],
                ['label' => 'Learning Goals', 'url' => 'learning_goals'],
                ['label' => 'Reflection Journal', 'url' => 'reflection_journal'],
            ]
        ],

        [
            'label' => 'Resources',
            'url' => null,
            'icon' => 'fas fa-folder-open',
            'subitems' => [
                ['label' => 'Teaching Materials', 'url' => 'view_teaching_materials'],
                ['label' => 'Syllabus', 'url' => 'view_syllabus'],
                ['label' => 'Past Papers', 'url' => 'view_past_papers'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
            ]
        ],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
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
        [
            'label' => 'Fee Structure',
            'url' => null,
            'icon' => 'fas fa-list-alt',
            'subitems' => [
                ['label' => 'Draft Fee Structure', 'url' => 'draft_fee_structure'],       // ACCOUNTANT DRAFTS new structure or edits the existing ones that are not yet applied
                ['label' => 'Current Structure', 'url' => 'manage_fee_structure'],
                ['label' => 'Extra Charges', 'url' => 'manage_extra_charges'],
                ['label' => 'Boarding Fees', 'url' => 'manage_boarding_fee_structure'],
                ['label' => 'Transport Fees', 'url' => 'manage_transport_fee_structure'],
            ]
        ],

        // STUDENT BILLING — bill students within approved structure
        [
            'label' => 'Student Billing',
            'url' => null,
            'icon' => 'fas fa-file-invoice',
            'subitems' => [

                ['label' => 'Student Fee Accounts', 'url' => 'student_fees'],
                ['label' => 'Admission Fees', 'url' => 'manage_admissions_payments'],
                ['label' => 'Payment Register', 'url' => 'manage_payments'], //record and audit received transactions
                ['label' => 'Payment Records', 'url' => 'payment_records'],
                ['label' => 'Unmatched Payments', 'url' => 'unmatched_payments'],
                ['label' => 'Students with Balance', 'url' => 'students_with_balance'],


            ]
        ],

        // PAYROLL — Accountant processes payment after Director approval
        [
            'label' => 'Payroll',
            'url' => null,
            'icon' => 'fas fa-wallet',
            'subitems' => [
                ['label' => 'Manage Payrolls', 'url' => 'manage_payrolls'],            // view approved and process by permission
                ['label' => 'Payslips', 'url' => 'payslips'],
                ['label' => 'Payroll History', 'url' => 'payroll'],
                ['label' => 'Statutory Remittances', 'url' => 'statutory_remittances'],
            ]
        ],


        // EXPENDITURE
        [
            'label' => 'Expenditure',
            'url' => null,
            'icon' => 'fas fa-receipt',
            'subitems' => [
                ['label' => 'Vendors & Suppliers', 'url' => 'vendors'],
                ['label' => 'Purchase Orders', 'url' => 'purchase_orders'],
                ['label' => 'Petty Cash', 'url' => 'petty_cash'],
                ['label' => 'Manage Expenses', 'url' => 'manage_expenses'],
            ]
        ],

        // ACCOUNTS & BALANCES
        [
            'label' => 'Accounts & Balances',
            'url' => null,
            'icon' => 'fas fa-university',
            'subitems' => [
                ['label' => 'Bank Accounts', 'url' => 'bank_accounts'],
                ['label' => 'Bank Transactions', 'url' => 'bank_transactions'],
                ['label' => 'M-Pesa Settlements', 'url' => 'mpesa_settlements'],
                ['label' => 'Reconciliation', 'url' => 'mpesa_reconciliation'],
            ]
        ],

        // BUDGET & PLANNING
        [
            'label' => 'Budget & Planning',
            'url' => null,
            'icon' => 'fas fa-chart-line',
            'subitems' => [
                ['label' => 'Budget Overview', 'url' => 'budget_overview'],
                ['label' => 'Budget vs Actuals', 'url' => 'budget_actuals'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Financial Reports', 'url' => 'finance_reports'],
                ['label' => 'Payment Reports', 'url' => 'payment_reports'],
                ['label' => 'Enrollment Finance', 'url' => 'enrollment_reports'],
            ]
        ],

        [
            'label' => 'Controls & Audit',
            'url' => null,
            'icon' => 'fas fa-shield-alt',
            'subitems' => [
                ['label' => 'Transaction Approvals', 'url' => 'transaction_approvals'],
                ['label' => 'KCB Reconciliation', 'url' => 'kcb_disbursement_reconciliation'],
                ['label' => 'Audit Logs', 'url' => 'audit_logs'],
                ['label' => 'Adjustments', 'url' => 'adjustments'],
                ['label' => 'Exception Reports', 'url' => 'exception_reports'],
            ]
        ],

        [
            'label' => 'Assets & Inventory',
            'url' => null,
            'icon' => 'fas fa-boxes',
            'subitems' => [
                ['label' => 'Asset Purchases', 'url' => 'asset_purchases'],
                ['label' => 'Depreciation', 'url' => 'depreciation'],
                ['label' => 'Inventory Expenses', 'url' => 'inventory_expenses'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
                ['label' => 'SMS', 'url' => 'manage_sms'],
                ['label' => 'Email', 'url' => 'manage_email'],
            ]
        ],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 14 — Uniform Store Manager
    // Uniform catalogue, stock, sales, purchasing, promotion and tailoring.
    // =========================================================================
    14 => [
        ['label' => 'Dashboard', 'url' => 'store_manager_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        [
            'label' => 'Stock & Products',
            'url' => null,
            'icon' => 'fas fa-boxes',
            'subitems' => [
                ['label' => 'Product Catalogue', 'url' => 'internal_products_catalog'],
                ['label' => 'Manage Products', 'url' => 'products_catalog_management'],
                ['label' => 'Stock Intake', 'url' => 'uniform_stock_intake'],
                ['label' => 'Unit Traceability', 'url' => 'uniform_unit_tracking'],
                ['label' => 'Stock & Sizes', 'url' => 'manage_uniform_sales'],
            ]
        ],

        [
            'label' => 'Sales & Orders',
            'url' => null,
            'icon' => 'fas fa-shopping-cart',
            'subitems' => [
                ['label' => 'Point of Sale', 'url' => 'uniform_point_of_sale'],
                ['label' => 'Orders & Dispatch', 'url' => 'catalog_orders_management'],
                ['label' => 'Offers & Discounts', 'url' => 'uniform_discounts'],
                ['label' => 'Sales Records', 'url' => 'uniform_sales_records'],
            ]
        ],

        [
            'label' => 'Vendors & Suppliers',
            'url' => null,
            'icon' => 'fas fa-truck',
            'subitems' => [
                ['label' => 'Vendors', 'url' => 'vendors'],
                ['label' => 'Purchase Orders', 'url' => 'purchase_orders'],
                ['label' => 'Goods Received', 'url' => 'goods_received'],
                ['label' => 'Invoices', 'url' => 'vendor_invoices'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Uniform Sales Reports', 'url' => 'uniform_sales_records'],
                ['label' => 'Purchase Reports', 'url' => 'purchase_reports'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
            ]
        ],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 71 — Food Store Manager
    // Food receipts, daily issues/consumption, stock sufficiency and purchasing.
    // =========================================================================
    71 => [
        ['label' => 'Dashboard', 'url' => 'food_store_manager_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        [
            'label' => 'Food Store',
            'url' => null,
            'icon' => 'fas fa-warehouse',
            'subitems' => [
                ['label' => 'Food Inventory', 'url' => 'food_store'],
                ['label' => 'Stock Levels', 'url' => 'food_stock_levels'],
                ['label' => 'Low Stock Alerts', 'url' => 'food_low_stock'],
                ['label' => 'Incoming Stock & Orders', 'url' => 'food_orders'],
                ['label' => 'Daily Usage', 'url' => 'food_consumption'],
            ]
        ],
        [
            'label' => 'Suppliers & Purchasing',
            'url' => null,
            'icon' => 'fas fa-truck',
            'subitems' => [
                ['label' => 'Food Suppliers', 'url' => 'vendors'],
                ['label' => 'Food Purchase Orders', 'url' => 'purchase_orders'],
                ['label' => 'Goods Received', 'url' => 'goods_received'],
            ]
        ],
        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Food Consumption Report', 'url' => 'report_food_consumption_trend'],
                ['label' => 'Purchase Reports', 'url' => 'purchase_reports'],
            ]
        ],
        ['label' => 'My Messages', 'url' => 'communications/messages_inbox', 'icon' => 'fas fa-comments', 'subitems' => []],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 72 — Librarian (secondary-role friendly)
    // Catalogue, circulation, overdue books and library reporting only.
    // =========================================================================
    72 => [
        ['label' => 'Dashboard', 'url' => 'librarian_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        [
            'label' => 'Library',
            'url' => null,
            'icon' => 'fas fa-book',
            'subitems' => [
                ['label' => 'Manage Books', 'url' => 'manage_library'],
                ['label' => 'Issue / Return', 'url' => 'library_issue_return'],
                ['label' => 'Overdue Books', 'url' => 'library_overdue'],
            ]
        ],
        ['label' => 'My Messages', 'url' => 'communications/messages_inbox', 'icon' => 'fas fa-comments', 'subitems' => []],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 16 — Cateress / Catering Manager
    // Menu planning, food store, daily meals, suppliers
    // =========================================================================
    16 => [
        ['label' => 'Dashboard', 'url' => 'catering_manager_cook_lead_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        [
            'label' => 'Menu & Food',
            'url' => null,
            'icon' => 'fas fa-utensils',
            'subitems' => [
                ['label' => 'Menu Planning', 'url' => 'menu_planning'],
                ['label' => 'Today\'s Menu', 'url' => 'todays_menu'],
                ['label' => 'Weekly Menu', 'url' => 'weekly_menu'],
                ['label' => 'Manage Menus', 'url' => 'manage_menus'],
            ]
        ],

        [
            'label' => 'Food Store',
            'url' => null,
            'icon' => 'fas fa-warehouse',
            'subitems' => [
                ['label' => 'Food Inventory', 'url' => 'food_store'],
                ['label' => 'Food Stock Levels', 'url' => 'food_stock_levels'],
                ['label' => 'Low Stock Alerts', 'url' => 'food_low_stock'],
                ['label' => 'Food Orders', 'url' => 'food_orders'],
            ]
        ],

        [
            'label' => 'Suppliers',
            'url' => null,
            'icon' => 'fas fa-truck',
            'subitems' => [
                ['label' => 'Catering Suppliers', 'url' => 'vendors'],
                ['label' => 'Food Purchase Orders', 'url' => 'purchase_orders'],
            ]
        ],

        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'Boarding Students', 'url' => 'catering_boarding_students'], // meal quantities only
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Meal Statistics', 'url' => 'meal_statistics'],
                ['label' => 'Food Consumption', 'url' => 'food_consumption'],
            ]
        ],

        ['label' => 'My Messages', 'url' => 'communications/messages_inbox', 'icon' => 'fas fa-comments', 'subitems' => []],
        ['label' => 'Announcements', 'url' => 'manage_announcements', 'icon' => 'fas fa-bullhorn', 'subitems' => []],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 18 — Boarding Master / Matron / Housemother
    // Workflow role: ASSIGNS DORM to new boarders after admission is approved
    // Daily: roll call, exeats, health, dormitory management
    // =========================================================================
    18 => [
        ['label' => 'Dashboard', 'url' => 'matron_housemother_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'QR Scanner', 'url' => 'qr-scanner', 'icon' => 'fas fa-qrcode', 'subitems' => []],

        // ADMISSIONS — Boarding Master assigns dorm after Admin creates student record
        ['label' => 'New Boarders', 'url' => 'room_assignments', 'icon' => 'fas fa-user-plus', 'subitems' => []],
        // BOARDING — core daily operations
        [
            'label' => 'Boarding',
            'url' => null,
            'icon' => 'fas fa-bed',
            'subitems' => [
                ['label' => 'Manage Boarding', 'url' => 'manage_boarding'],
                ['label' => 'Roll Call', 'url' => 'boarding_roll_call'],
                ['label' => 'Dormitory Management', 'url' => 'dormitory_management'],
                ['label' => 'Boarding Students List', 'url' => 'boarding_students'],
            ]
        ],

        // PERMISSIONS & EXEATS
        [
            'label' => 'Permissions & Exeats',
            'url' => null,
            'icon' => 'fas fa-id-card',
            'subitems' => [
                ['label' => 'Issue Exeat', 'url' => 'permissions_exeats'],
                ['label' => 'Pending Requests', 'url' => 'pending_exeat_requests'],
                ['label' => 'Exeat History', 'url' => 'exeat_history'],
                ['label' => 'End-of-Term Travel', 'url' => 'end_of_term_travel'],
            ]
        ],

        // HEALTH — boarding school health is boarding master's responsibility
        [
            'label' => 'Health & Medical',
            'url' => null,
            'icon' => 'fas fa-heartbeat',
            'subitems' => [
                ['label' => 'Student Health Records', 'url' => 'student_health'],
                ['label' => 'Sick Bay Log', 'url' => 'sick_bay'],
                ['label' => 'Medical Alerts', 'url' => 'medical_alerts'],
                ['label' => 'Vaccination Records', 'url' => 'vaccination_records'],
            ]
        ],

        // ATTENDANCE — evening/morning roll call
        [
            'label' => 'Attendance',
            'url' => null,
            'icon' => 'fas fa-clipboard-check',
            'subitems' => [
                ['label' => 'Evening Roll Call', 'url' => 'evening_roll_call'],
                ['label' => 'Morning Roll Call', 'url' => 'morning_roll_call'],
                ['label' => 'Absence Reports', 'url' => 'attendance_reports'],
            ]
        ],

        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'Boarding Profiles', 'url' => 'student_profiles'],
                ['label' => 'Special Needs', 'url' => 'special_needs'],
            ]
        ],

        // ── HR (personal) ────────────────────────────────────────────────────
        [
            'label' => 'My HR',
            'url' => null,
            'icon' => 'fas fa-id-badge',
            'subitems' => [
                ['label' => 'My Payslip', 'url' => 'detailed_payslip'],
                ['label' => 'Leave Requests', 'url' => 'staff_leave'],
                ['label' => 'My Attendance', 'url' => 'my_attendance'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
                ['label' => 'Parent Notifications', 'url' => 'send_parent_notifications'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Boarding Reports', 'url' => 'boarding_reports'],
                ['label' => 'Health Reports', 'url' => 'health_reports'],
                ['label' => 'Exeat Reports', 'url' => 'exeat_reports'],
            ]
        ],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 21 — Talent Development / HoD Activities
    // Activities, sports, clubs, competitions, school events
    // =========================================================================
    21 => [
        ['label' => 'Dashboard', 'url' => 'hod_talent_development_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        [
            'label' => 'Activities',
            'url' => null,
            'icon' => 'fas fa-running',
            'subitems' => [
                ['label' => 'Manage Activities', 'url' => 'manage_activities'],
                ['label' => 'Create Activity', 'url' => 'create_activity'],
                ['label' => 'Clubs & Societies', 'url' => 'clubs_societies'],
                ['label' => 'Sports Teams', 'url' => 'sports'],
                ['label' => 'Competitions', 'url' => 'competitions'],
            ]
        ],

        [
            'label' => 'Events & Calendar',
            'url' => null,
            'icon' => 'fas fa-calendar-alt',
            'subitems' => [
                ['label' => 'School Events', 'url' => 'school_events'],
                ['label' => 'Assemblies', 'url' => 'assemblies'],
                ['label' => 'View Calendar', 'url' => 'view_calendar'],
            ]
        ],

        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'Activity Participants', 'url' => 'all_students'],
                ['label' => 'Participant Registration', 'url' => 'activity_participants'],
                ['label' => 'Achievement Records', 'url' => 'activity_achievements'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
            ]
        ],

        [
            'label' => 'Website Management',
            'url' => null,
            'icon' => 'fas fa-globe',
            'subitems' => [
                ['label' => 'News Articles', 'url' => 'manage_articles'],
                ['label' => 'Events', 'url' => 'manage_public_events'],
                ['label' => 'Gallery', 'url' => 'manage_school_gallery'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Activity Reports', 'url' => 'activity_reports'],
                ['label' => 'Participation Reports', 'url' => 'participation_reports'],
            ]
        ],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 23 — Driver
    // Routes, vehicle, passenger list
    // =========================================================================
    23 => [
        ['label' => 'Dashboard', 'url' => 'driver_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],
        ['label' => 'QR Scanner', 'url' => 'qr-scanner', 'icon' => 'fas fa-qrcode', 'subitems' => []],

        [
            'label' => 'Transport',
            'url' => null,
            'icon' => 'fas fa-bus',
            'subitems' => [
                ['label' => 'My Routes', 'url' => 'my_routes'],
                ['label' => 'My Vehicle', 'url' => 'my_vehicle'],
            ]
        ],

        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-users',
            'subitems' => [
                ['label' => 'My Passengers', 'url' => 'transport_passengers'],
                ['label' => 'Passenger Attendance', 'url' => 'mark_attendance'],
            ]
        ],

        // ── HR (personal) ────────────────────────────────────────────────────
        [
            'label' => 'My HR',
            'url' => null,
            'icon' => 'fas fa-id-badge',
            'subitems' => [
                ['label' => 'My Payslip', 'url' => 'detailed_payslip'],
                ['label' => 'Leave Requests', 'url' => 'staff_leave'],
                ['label' => 'My Attendance', 'url' => 'my_attendance'],
            ]
        ],

        ['label' => 'Announcements', 'url' => 'manage_announcements', 'icon' => 'fas fa-bullhorn', 'subitems' => []],
        ['label' => 'My Messages', 'url' => 'communications/messages_inbox', 'icon' => 'fas fa-comments', 'subitems' => []],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 24 — Chaplain / School Counselor
    // Counseling sessions, chapel, student welfare, referrals from Discipline
    // =========================================================================
     24 => [
        ['label' => 'Dashboard', 'url' => 'school_counselor_chaplain_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        [
            'label' => 'Chaplaincy Department',
            'url' => null,
            'icon' => 'fas fa-church',
            'subitems' => [
                ['label' => 'Department & Team', 'url' => 'chaplaincy_department'],
                ['label' => 'Spiritual Programs', 'url' => 'chaplaincy_programs'],
                ['label' => 'Groups & Pastoral Care', 'url' => 'chaplaincy_pastoral'],
            ]
        ],

        [
            'label' => 'Student Leadership',
            'url' => null,
            'icon' => 'fas fa-medal',
            'subitems' => [
                ['label' => 'Leadership & Offices', 'url' => 'student_leadership'],
            ]
        ],

        [
            'label' => 'Counseling',
            'url' => null,
            'icon' => 'fas fa-hands-helping',
            'subitems' => [
                ['label' => 'Student Sessions', 'url' => 'student_counseling'],
                ['label' => 'Counseling Records', 'url' => 'counseling_records'],
                ['label' => 'Referrals', 'url' => 'counseling_referrals'],      // from Discipline Deputy
                ['label' => 'Case Management', 'url' => 'counseling_cases'],
            ]
        ],

        [
            'label' => 'Student Welfare',
            'url' => null,
            'icon' => 'fas fa-heart',
            'subitems' => [
                ['label' => 'At-Risk Students', 'url' => 'at_risk_students'],
                ['label' => 'Intervention Plans', 'url' => 'intervention_plans'],
                ['label' => 'Special Needs', 'url' => 'special_needs'],
                ['label' => 'Follow-ups', 'url' => 'welfare_follow_ups'],
            ]
        ],

        [
            'label' => 'Parent Communication',
            'url' => null,
            'icon' => 'fas fa-users',
            'subitems' => [
                ['label' => 'Schedule Meetings', 'url' => 'schedule_parent_meetings'],
                ['label' => 'Meeting Records', 'url' => 'parent_meeting_records'],
                ['label' => 'Send Notifications', 'url' => 'send_parent_notifications'],
            ]
        ],

        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'Student Welfare', 'url' => 'student_welfare'],
                ['label' => 'Welfare Records', 'url' => 'welfare_records'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Counseling Reports', 'url' => 'counseling_reports'],
                ['label' => 'Welfare Summary', 'url' => 'welfare_summary'],
            ]
        ],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 32 — Kitchen Staff
    // Shared support dashboard plus kitchen operations and personal self-service.
    // =========================================================================
    32 => [
        ['label' => 'Dashboard', 'url' => 'support_staff_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'My Attendance', 'url' => 'my_attendance', 'icon' => 'fas fa-clipboard-check', 'subitems' => []],
        ['label' => 'Leave Requests', 'url' => 'staff_leave', 'icon' => 'fas fa-calendar-check', 'subitems' => []],
        ['label' => 'Payslips & P9', 'url' => 'detailed_payslip', 'icon' => 'fas fa-file-invoice', 'subitems' => []],
        [
            'label' => 'Kitchen',
            'url' => null,
            'icon' => 'fas fa-utensils',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Meal Planning', 'url' => 'catering_boarding_students'],
                ['label' => 'Food Store', 'url' => 'food_store'],
            ]
        ],
        ['label' => 'Announcements', 'url' => 'manage_announcements', 'icon' => 'fas fa-bullhorn', 'subitems' => []],
        ['label' => 'My Messages', 'url' => 'communications/messages_inbox', 'icon' => 'fas fa-comments', 'subitems' => []],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 33 — Security Staff
    // Shared support dashboard plus gate operations and personal self-service.
    // =========================================================================
    33 => [
        ['label' => 'Dashboard', 'url' => 'support_staff_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'My Attendance', 'url' => 'my_attendance', 'icon' => 'fas fa-clipboard-check', 'subitems' => []],
        ['label' => 'Leave Requests', 'url' => 'staff_leave', 'icon' => 'fas fa-calendar-check', 'subitems' => []],
        ['label' => 'Payslips & P9', 'url' => 'detailed_payslip', 'icon' => 'fas fa-file-invoice', 'subitems' => []],
        [
            'label' => 'Gate Operations',
            'url' => null,
            'icon' => 'fas fa-shield-alt',
            'subitems' => [
                ['label' => 'Permissions & Exeats', 'url' => 'permissions_exeats'],
            ]
        ],
        ['label' => 'Announcements', 'url' => 'manage_announcements', 'icon' => 'fas fa-bullhorn', 'subitems' => []],
        ['label' => 'My Messages', 'url' => 'communications/messages_inbox', 'icon' => 'fas fa-comments', 'subitems' => []],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 34 — Janitor / Cleaner
    // Shared support dashboard and personal self-service. Assigned maintenance
    // work is summarised on the dashboard until a dedicated staff task page exists.
    // =========================================================================
    34 => [
        ['label' => 'Dashboard', 'url' => 'support_staff_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'My Attendance', 'url' => 'my_attendance', 'icon' => 'fas fa-clipboard-check', 'subitems' => []],
        ['label' => 'Leave Requests', 'url' => 'staff_leave', 'icon' => 'fas fa-calendar-check', 'subitems' => []],
        ['label' => 'Payslips & P9', 'url' => 'detailed_payslip', 'icon' => 'fas fa-file-invoice', 'subitems' => []],
        ['label' => 'Announcements', 'url' => 'manage_announcements', 'icon' => 'fas fa-bullhorn', 'subitems' => []],
        ['label' => 'My Messages', 'url' => 'communications/messages_inbox', 'icon' => 'fas fa-comments', 'subitems' => []],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
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
        ['label' => 'QR Scanner', 'url' => 'qr-scanner', 'icon' => 'fas fa-qrcode', 'subitems' => []],

        [
            'label' => 'Admissions',
            'url' => null,
            'icon' => 'fas fa-user-plus',
            'subitems' => [
                ['label' => 'Applications', 'url' => 'admissions_academic_applications'],
            ],
        ],

        // ── MY TEACHING (daily teacher tasks) ────────────────────────────────
        [
            'label' => 'My Teaching',
            'url' => null,
            'icon' => 'fas fa-chalkboard',
            'subitems' => [
                ['label' => 'My Timetable', 'url' => 'timetable'],                  // personal teaching schedule
                ['label' => 'My Class', 'url' => 'my_students_list'],           // if assigned a home class
                ['label' => 'Mark Class Attendance', 'url' => 'class_mark_attendance'],            // daily class register
                ['label' => 'My Lesson Plans', 'url' => 'manage_lesson_plans'],        // create own plans
                ['label' => 'Schemes of Work', 'url' => 'schemes_of_work'],
                ['label' => 'Enter Assessment Marks', 'url' => 'formative_assessments'],      // grade own students
                ['label' => 'Competency Ratings', 'url' => 'competencies_sheet'],         // CBC competency entries
            ]
        ],

        // ── ADMIN: DISCIPLINE (primary domain) ───────────────────────────────
        [
            'label' => 'Discipline',
            'url' => null,
            'icon' => 'fas fa-gavel',
            'subitems' => [
                ['label' => 'All Cases', 'url' => 'discipline_cases'],
                ['label' => 'Log New Case', 'url' => 'log_discipline_case'],
                ['label' => 'Open Cases', 'url' => 'pending_discipline_cases'],
                ['label' => 'Suspensions / Expulsions', 'url' => 'suspensions_expulsions'],
                ['label' => 'Sanctions', 'url' => 'sanctions'],
                ['label' => 'Policy Violations', 'url' => 'policy_violations'],
            ]
        ],

        // deputy HT Disciplinary: STAFF (disciplinary oversight) ─────────────────────────────
        [
            'label' => 'Staff',
            'url' => null,
            'icon' => 'fas fa-chalkboard-teacher',
            'subitems' => [
                ['label' => 'All Staff', 'url' => 'manage_staff'],
                ['label' => 'Staff Onboarding', 'url' => 'staff_onboarding'],
                ['label' => 'Staff Lifecycle', 'url' => 'staff_lifecycle'],
                ['label' => 'Staff Appointments', 'url' => 'staff_appointments'],
                ['label' => 'Recruitment Applications', 'url' => 'manage_job_applications'],
                ['label' => 'Teachers', 'url' => 'all_teachers'],
                ['label' => 'Staff Performance', 'url' => 'staff_performance'],
                ['label' => 'Performance Reviews', 'url' => 'teacher_performance_reviews'],
                ['label' => 'Staff Attendance', 'url' => 'staff_attendance'],
                ['label' => 'Staff Leave', 'url' => 'staff_leave'],
            ]
        ],

        // ── ADMIN: STUDENT CONDUCT ────────────────────────────────────────────
        [
            'label' => 'Student Conduct',
            'url' => null,
            'icon' => 'fas fa-user-check',
            'subitems' => [
                ['label' => 'Behavior Logs', 'url' => 'behavior_logs'],
                ['label' => 'Conduct Grades', 'url' => 'conduct_grades'],
                ['label' => 'Rewards & Recognition', 'url' => 'rewards_recognition'],
                ['label' => 'Refer to Counseling', 'url' => 'student_counseling'],         // → Chaplain/Counselor workflow
                ['label' => 'At-Risk Students', 'url' => 'student_welfare'],
            ]
        ],

        // ── ADMIN: ATTENDANCE (truancy is a discipline matter) ────────────────
        [
            'label' => 'Truancy & Attendance',
            'url' => null,
            'icon' => 'fas fa-clipboard-check',
            'subitems' => [
                ['label' => 'Daily Attendance', 'url' => 'view_attendance'],
                ['label' => 'Attendance Reports', 'url' => 'attendance_reports'],
                ['label' => 'Absenteeism Trends', 'url' => 'attendance_trends'],
                ['label' => 'Parent Alerts (Truancy)', 'url' => 'send_parent_notifications'],
            ]
        ],

        // ── ADMIN: PARENT COMMUNICATION ───────────────────────────────────────
        [
            'label' => 'Parent Communication',
            'url' => null,
            'icon' => 'fas fa-users',
            'subitems' => [
                ['label' => 'Parent Meetings', 'url' => 'parent_meetings'],
                ['label' => 'Send Notifications', 'url' => 'manage_communications'],
                ['label' => 'Meeting Records', 'url' => 'parent_meeting_records'],
            ]
        ],

        // ── STUDENTS ──────────────────────────────────────────────────────────
        [
            'label' => 'Students',
            'url' => null,
            'icon' => 'fas fa-user-graduate',
            'subitems' => [
                ['label' => 'Discipline Students', 'url' => 'discipline_students'],
                ['label' => 'Student Profiles', 'url' => 'student_profiles'],
                ['label' => 'Special Needs', 'url' => 'special_needs'],
                ['label' => 'Student Leadership', 'url' => 'student_leadership'],
            ]
        ],

        // ── HR (personal) ────────────────────────────────────────────────────
        [
            'label' => 'My HR',
            'url' => null,
            'icon' => 'fas fa-id-badge',
            'subitems' => [
                ['label' => 'My Payslip', 'url' => 'detailed_payslip'],
                ['label' => 'Leave Requests', 'url' => 'staff_leave'],
                ['label' => 'My Attendance', 'url' => 'my_attendance'],
            ]
        ],

        [
            'label' => 'Communications',
            'url' => null,
            'icon' => 'fas fa-comments',
            'subitems' => [
                ['label' => 'My Messages', 'url' => 'communications/messages_inbox'],
                ['label' => 'Announcements', 'url' => 'manage_announcements'],
            ]
        ],

        [
            'label' => 'Website Management',
            'url' => null,
            'icon' => 'fas fa-globe',
            'subitems' => [
                ['label' => 'News Articles', 'url' => 'manage_articles'],
                ['label' => 'Events', 'url' => 'manage_public_events'],
            ]
        ],

        [
            'label' => 'Reports & Analytics',
            'url' => null,
            'icon' => 'fas fa-chart-bar',
            'subitems' => [
                ['label' => 'Analytics Catalogue', 'url' => 'analytics_catalogue'],
                ['label' => 'Discipline Reports', 'url' => 'discipline_reports'],
                ['label' => 'Conduct Reports', 'url' => 'conduct_reports'],
                ['label' => 'Term Summary', 'url' => 'term_reports'],
            ]
        ],

        ['label' => 'Library', 'url' => null, 'icon' => 'fas fa-book', 'subitems' => [['label' => 'Library', 'url' => 'manage_library']]],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

    // =========================================================================
    // 64 — Generic Staff (office and operational staff)
    // Shared department-aware dashboard and complete personal self-service.
    // =========================================================================
    64 => [
        ['label' => 'Dashboard', 'url' => 'support_staff_dashboard', 'icon' => 'fas fa-tachometer-alt', 'subitems' => []],

        ['label' => 'Timetable', 'url' => 'timetable', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
        ['label' => 'My Attendance', 'url' => 'my_attendance', 'icon' => 'fas fa-clipboard-check', 'subitems' => []],
        ['label' => 'Leave Requests', 'url' => 'staff_leave', 'icon' => 'fas fa-calendar-check', 'subitems' => []],
        ['label' => 'Payslips & P9', 'url' => 'detailed_payslip', 'icon' => 'fas fa-file-invoice', 'subitems' => []],
        ['label' => 'Announcements', 'url' => 'manage_announcements', 'icon' => 'fas fa-bullhorn', 'subitems' => []],
        ['label' => 'My Messages', 'url' => 'communications/messages_inbox', 'icon' => 'fas fa-comments', 'subitems' => []],
        ['label' => 'School Calendar', 'url' => 'academic_calendar', 'icon' => 'fas fa-calendar-alt', 'subitems' => []],
    ],

];
