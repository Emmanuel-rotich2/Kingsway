<?php
/**
 * Dashboard component registry and safe fallback mapper.
 *
 * Runtime role assignments are read from `role_dashboards` + `dashboards` by
 * the authentication layer. This registry mirrors the approved production
 * mapping, validates component/controller availability, and provides a safe
 * access-denied fallback when database configuration is missing or invalid.
 */
namespace App\Config;

use App\API\Services\ReportRegistry;

class DashboardRouter
{
    /** System-domain pages explicitly approved for the System Administrator. */
    private const SYSTEM_ADMIN_ROUTES = [
        'system_administrator_dashboard',
        'manage_users', 'bootstrap_school_administrator', 'account_status', 'manage_roles',
        'role_permission_matrix', 'resource_based_permissions',
        'authentication_logs', 'failed_login_attempts', 'active_sessions',
        'token_management', 'ip_whitelist_blacklist',
        'domain_isolation_rules', 'time_bound_access', 'permission_policies',
        'route_access_rules', 'system_settings',
        'payment_integration_settings', 'feature_flags', 'module_enablement',
        'maintenance_mode', 'route_registry', 'sidebar_menus',
        'role_navigation_config', 'analytics_catalogue', 'system_health',
        'error_logs', 'background_jobs', 'api_metrics', 'rate_limiting_status',
        'migrations', 'backups', 'data_retention', 'activity_audit_logs',
        'log_viewer',
        'permission_changes', 'policy_violations', 'security_incidents',
        'api_explorer', 'webhook_registry', 'system_diagnostics', 'job_inspector',
        'communications/messages_inbox', 'manage_system_announcements',
        'manage_sms_configurations', 'manage_email_configurations',
        'manage_whatsapp_configurations', 'account_settings', 'profile',
    ];
    // role_id => dashboard file key (without .php)
    private const ROLE_DASHBOARDS = [
        2  => 'system_administrator_dashboard',
        3  => 'director_owner_dashboard',
        4  => 'school_administrative_officer_dashboard',
        5  => 'headteacher_dashboard',
        6  => 'deputy_head_academic_dashboard',
        7  => 'class_teacher_dashboard',
        8  => 'subject_teacher_dashboard',
        9  => 'intern_student_teacher_dashboard',
        10 => 'school_accountant_dashboard',
        14 => 'store_manager_dashboard',
        71 => 'food_store_manager_dashboard',
        72 => 'librarian_dashboard',
        16 => 'catering_manager_cook_lead_dashboard',
        18 => 'matron_housemother_dashboard',
        21 => 'hod_talent_development_dashboard',
        23 => 'driver_dashboard',
        24 => 'school_counselor_chaplain_dashboard',
        32 => 'support_staff_dashboard',
        33 => 'support_staff_dashboard',
        34 => 'support_staff_dashboard',
        63 => 'deputy_head_discipline_dashboard',
        64 => 'support_staff_dashboard',
    ];

    private const DEFAULT_DASHBOARD = 'dashboard_access_denied';

    private const DASHBOARD_ALIASES = [
        'dashboard' => self::DEFAULT_DASHBOARD,
        'home' => self::DEFAULT_DASHBOARD,
        'director_dashboard' => 'director_owner_dashboard',
        'school_admin_dashboard' => 'school_administrative_officer_dashboard',
    ];

    // Retired teacher assessment pages remain valid bookmarks, but resolve to
    // the single scoped formative-assessment workspace.
    private const ROUTE_ALIASES = [
        'create_assessment' => 'my_cats',
        'create_subject_cat' => 'my_cats',
        'my_subject_cats' => 'my_cats',
    ];

// Auto-generated from the final SidebarConfigReader menu — do not edit manually
// Run: php scripts/generate_route_roles.php --write
private const ROUTE_ROLES = [
    'academic_calendar' => [5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64, 71, 72],  // 19 role(s)
    'academic_content_reconciliation' => [5, 6],  // 2 role(s)
    'academic_planning_oversight' => [3, 4, 5],  // 3 role(s)
    'academic_reports' => [3, 4, 5, 6],  // 4 role(s)
    'academic_students' => [6],  // 1 role(s)
    'academic_years' => [3, 4, 5, 6],  // 4 role(s)
    'account_status' => [2],  // 1 role(s)
    'active_sessions' => [2],  // 1 role(s)
    'activity_achievements' => [21],  // 1 role(s)
    'activity_audit_logs' => [2],  // 1 role(s)
    'activity_participants' => [21],  // 1 role(s)
    'activity_reports' => [21],  // 1 role(s)
    'adjustments' => [10],  // 1 role(s)
    'admission_interviews' => [5],  // 1 role(s)
    'admissions_academic_applications' => [5, 6, 63],  // 3 role(s)
    'admissions_admission_decisions' => [5],  // 1 role(s)
    'admissions_class_placement' => [4, 6],  // 2 role(s)
    'admissions_headteacher_applications' => [5],  // 1 role(s)
    'admissions_pending_admission_approvals' => [5],  // 1 role(s)
    'all_lesson_plans' => [6],  // 1 role(s)
    'all_parents' => [3, 4, 5],  // 3 role(s)
    'all_students' => [21],  // 1 role(s)
    'all_teachers' => [3, 4, 5, 6, 63],  // 5 role(s)
    'alumni_management' => [3, 4, 5, 6],  // 4 role(s)
    'analytics_catalogue' => [2, 3, 4, 5, 6, 7, 10, 14, 16, 32, 63],  // 11 role(s)
    'api_explorer' => [2],  // 1 role(s)
    'api_metrics' => [2],  // 1 role(s)
    'approved_lesson_plans' => [6],  // 1 role(s)
    'assemblies' => [4, 21],  // 2 role(s)
    'assessment_rubrics' => [4, 5, 6, 7, 8, 9],  // 6 role(s)
    'asset_purchases' => [10],  // 1 role(s)
    'assign_class_teachers' => [6],  // 1 role(s)
    'assign_subjects_to_teachers' => [6],  // 1 role(s)
    'at_risk_students' => [24],  // 1 role(s)
    'attendance_reports' => [3, 4, 5, 6, 18, 63],  // 6 role(s)
    'attendance_trends' => [3, 6, 63],  // 3 role(s)
    'audit_logs' => [10],  // 1 role(s)
    'authentication_logs' => [2],  // 1 role(s)
    'background_jobs' => [2],  // 1 role(s)
    'backups' => [2],  // 1 role(s)
    'bank_accounts' => [3, 10],  // 2 role(s)
    'bank_transactions' => [3, 10],  // 2 role(s)
    'behavior_logs' => [63],  // 1 role(s)
    'boarding_reports' => [18],  // 1 role(s)
    'boarding_roll_call' => [4, 5, 18],  // 3 role(s)
    'boarding_students' => [18],  // 1 role(s)
    'bootstrap_school_administrator' => [2],  // 1 role(s)
    'budget_actuals' => [10],  // 1 role(s)
    'budget_overview' => [3, 5, 10],  // 3 role(s)
    'catalog_orders_management' => [3, 4, 14],  // 3 role(s)
    'catering_boarding_students' => [16, 32],  // 2 role(s)
    'catering_manager_cook_lead_dashboard' => [16],  // 1 role(s)
    'chaplaincy_department' => [24],  // 1 role(s)
    'chaplaincy_programs' => [24],  // 1 role(s)
    'chaplaincy_pastoral' => [24],  // 1 role(s)
    'class_attendance_history' => [7],  // 1 role(s)
    'class_capacity' => [4, 5],  // 2 role(s)
    'class_conduct_grades' => [7],  // 1 role(s)
    'class_mark_attendance' => [6, 7, 8, 63],  // 4 role(s)
    'class_parent_contacts' => [7],  // 1 role(s)
    'class_report_cards' => [7],  // 1 role(s)
    'class_results' => [7],  // 1 role(s)
    'class_streams' => [4, 5, 6],  // 3 role(s)
    'class_teacher_dashboard' => [7],  // 1 role(s)
    'clubs_societies' => [21],  // 1 role(s)
    'communications/messages_inbox' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64, 71, 72],  // 22 role(s)
    'comparative_reports' => [3, 5, 6],  // 3 role(s)
    'competencies_sheet' => [6, 7, 63],  // 3 role(s)
    'competency_checklist' => [9],  // 1 role(s)
    'competitions' => [21],  // 1 role(s)
    'conduct_grades' => [63],  // 1 role(s)
    'conduct_reports' => [63],  // 1 role(s)
    'counseling_cases' => [24],  // 1 role(s)
    'counseling_records' => [24],  // 1 role(s)
    'counseling_referrals' => [24],  // 1 role(s)
    'counseling_reports' => [24],  // 1 role(s)
    'create_activity' => [21],  // 1 role(s)
    'curriculum_cbc' => [4, 5, 6, 7, 8, 9],  // 6 role(s)
    'curriculum_proposals' => [4, 5, 6, 7, 8, 9],  // 6 role(s)
    'daily_attendance' => [6],  // 1 role(s)
    'data_retention' => [2],  // 1 role(s)
    'depreciation' => [10],  // 1 role(s)
    'deputy_head_academic_dashboard' => [6],  // 1 role(s)
    'deputy_head_discipline_dashboard' => [63],  // 1 role(s)
    'detailed_payslip' => [6, 7, 8, 18, 23, 32, 33, 34, 63, 64],  // 10 role(s)
    'development_progress' => [9],  // 1 role(s)
    'director_owner_dashboard' => [3],  // 1 role(s)
    'discipline_cases' => [3, 4, 5, 63],  // 4 role(s)
    'discipline_reports' => [63],  // 1 role(s)
    'discipline_students' => [63],  // 1 role(s)
    'domain_isolation_rules' => [2],  // 1 role(s)
    'dormitory_management' => [3, 18],  // 2 role(s)
    'draft_fee_structure' => [10],  // 1 role(s)
    'driver_dashboard' => [23],  // 1 role(s)
    'end_of_term_travel' => [18],  // 1 role(s)
    'enrollment_reports' => [3, 4, 5, 10],  // 4 role(s)
    'enrollment_trends' => [6],  // 1 role(s)
    'enter_exam_results' => [7, 8],  // 2 role(s)
    'enter_marks' => [7],  // 1 role(s)
    'error_logs' => [2],  // 1 role(s)
    'evening_roll_call' => [18],  // 1 role(s)
    'exam_moderation' => [4, 5, 6],  // 3 role(s)
    'exam_schedule' => [3, 4, 5, 6, 7],  // 5 role(s)
    'exam_setup' => [6],  // 1 role(s)
    'exam_timetable_drafts' => [4, 5, 6],  // 3 role(s)
    'exception_reports' => [10],  // 1 role(s)
    'exeat_history' => [18],  // 1 role(s)
    'exeat_reports' => [18],  // 1 role(s)
    'failed_login_attempts' => [2],  // 1 role(s)
    'feature_flags' => [2],  // 1 role(s)
    'fee_structure_approvals' => [3],  // 1 role(s)
    'finance_approvals' => [3],  // 1 role(s)
    'finance_reports' => [3, 4, 5, 10],  // 4 role(s)
    'food_consumption' => [16, 71],  // 2 role(s)
    'food_low_stock' => [16, 71],  // 2 role(s)
    'food_orders' => [16, 71],  // 2 role(s)
    'food_stock_levels' => [16, 71],  // 2 role(s)
    'food_store' => [16, 32, 71],  // 3 role(s)
    'food_store_manager_dashboard' => [71],  // 1 role(s)
    'formative_assessments' => [6, 63],  // 2 role(s)
    'generate_class_report' => [7],  // 1 role(s)
    'generate_subject_report' => [8],  // 1 role(s)
    'goods_received' => [14, 71],  // 2 role(s)
    'grading_scales' => [4, 5, 6, 7, 8, 9],  // 6 role(s)
    'grading_status' => [4, 5, 6],  // 3 role(s)
    'headteacher_dashboard' => [5],  // 1 role(s)
    'health_reports' => [18],  // 1 role(s)
    'hod_talent_development_dashboard' => [21],  // 1 role(s)
    'import_existing_staff' => [3, 4],  // 2 role(s)
    'improvement_areas' => [9],  // 1 role(s)
    'intern_assigned_classes' => [9],  // 1 role(s)
    'intern_assigned_subjects' => [9],  // 1 role(s)
    'intern_schedule' => [9],  // 1 role(s)
    'intern_student_teacher_dashboard' => [9],  // 1 role(s)
    'internal_catalog_account' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64, 71, 72],  // 22 role(s)
    'internal_product_details' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64, 71, 72],  // 22 role(s)
    'internal_products_catalog' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64, 71, 72],  // 22 role(s)
    'intervention_plans' => [24],  // 1 role(s)
    'inventory_expenses' => [10],  // 1 role(s)
    'ip_whitelist_blacklist' => [2],  // 1 role(s)
    'job_inspector' => [2],  // 1 role(s)
    'kcb_disbursement_reconciliation' => [3, 4, 10],  // 3 role(s)
    'learning_goals' => [9],  // 1 role(s)
    'lesson_plan_approval' => [6],  // 1 role(s)
    'lesson_plans_by_class' => [6],  // 1 role(s)
    'lesson_plans_by_teacher' => [6],  // 1 role(s)
    'librarian_dashboard' => [72],  // 1 role(s)
    'library_issue_return' => [72],  // 1 role(s)
    'library_overdue' => [72],  // 1 role(s)
    'log_discipline_case' => [63],  // 1 role(s)
    'log_discipline_incident' => [7],  // 1 role(s)
    'log_viewer' => [2],  // 1 role(s)
    'maintenance_mode' => [2],  // 1 role(s)
    'manage_activities' => [3, 5, 21],  // 3 role(s)
    'manage_admissions_payments' => [10],  // 1 role(s)
    'manage_announcements' => [3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64],  // 19 role(s)
    'manage_articles' => [3, 6, 21, 63],  // 4 role(s)
    'manage_boarding' => [3, 4, 5, 18],  // 4 role(s)
    'manage_boarding_fee_structure' => [10],  // 1 role(s)
    'manage_classes' => [3, 4, 5, 6],  // 4 role(s)
    'manage_communications' => [4, 63],  // 2 role(s)
    'manage_email' => [3, 4, 6, 10],  // 4 role(s)
    'manage_email_configurations' => [2],  // 1 role(s)
    'manage_expenses' => [10],  // 1 role(s)
    'manage_extra_charges' => [3, 4, 10],  // 3 role(s)
    'manage_family_groups' => [4],  // 1 role(s)
    'manage_fee_structure' => [3, 4, 5, 10],  // 4 role(s)
    'manage_holidays' => [4],  // 1 role(s)
    'manage_inquiries' => [3],  // 1 role(s)
    'manage_inventory' => [3],  // 1 role(s)
    'manage_job_applications' => [3, 4, 5, 6, 63],  // 5 role(s)
    'manage_job_vacancies' => [3],  // 1 role(s)
    'manage_lesson_plans' => [5, 6, 7, 8, 9, 63],  // 6 role(s)
    'manage_library' => [3, 4, 5, 6, 63, 72],  // 6 role(s)
    'manage_menus' => [16],  // 1 role(s)
    'manage_page_content' => [3],  // 1 role(s)
    'manage_payments' => [3, 4, 10],  // 3 role(s)
    'manage_payrolls' => [3, 4, 10],  // 3 role(s)
    'manage_public_downloads' => [3],  // 1 role(s)
    'manage_public_events' => [3, 6, 21, 63],  // 4 role(s)
    'manage_roles' => [2],  // 1 role(s)
    'manage_school_gallery' => [3, 21],  // 2 role(s)
    'manage_sms' => [3, 4, 5, 10],  // 4 role(s)
    'manage_sms_configurations' => [2],  // 1 role(s)
    'manage_staff' => [3, 4, 5, 63],  // 4 role(s)
    'manage_staff_meetings' => [4],  // 1 role(s)
    'manage_students' => [4],  // 1 role(s)
    'manage_students_admissions' => [4],  // 1 role(s)
    'manage_subjects' => [5, 6],  // 2 role(s)
    'manage_system_announcements' => [2],  // 1 role(s)
    'manage_terms' => [3, 4],  // 2 role(s)
    'manage_timetable' => [3, 4, 5, 6, 7],  // 5 role(s)
    'manage_transport' => [3, 4, 5],  // 3 role(s)
    'manage_transport_fee_structure' => [10],  // 1 role(s)
    'manage_uniform_sales' => [3, 4, 14],  // 3 role(s)
    'manage_users' => [2],  // 1 role(s)
    'manage_website' => [3, 4],  // 2 role(s)
    'manage_whatsapp' => [4],  // 1 role(s)
    'manage_whatsapp_configurations' => [2],  // 1 role(s)
    'mark_attendance' => [23],  // 1 role(s)
    'matron_housemother_dashboard' => [18],  // 1 role(s)
    'meal_statistics' => [16],  // 1 role(s)
    'medical_alerts' => [18],  // 1 role(s)
    'mentor_feedback' => [9],  // 1 role(s)
    'mentor_meetings' => [9],  // 1 role(s)
    'mentor_notes' => [9],  // 1 role(s)
    'menu_planning' => [16],  // 1 role(s)
    'migrations' => [2],  // 1 role(s)
    'module_enablement' => [2],  // 1 role(s)
    'morning_roll_call' => [18],  // 1 role(s)
    'mpesa_reconciliation' => [10],  // 1 role(s)
    'mpesa_settlements' => [3, 10],  // 2 role(s)
    'my_attendance' => [6, 7, 8, 18, 23, 32, 33, 34, 63, 64],  // 10 role(s)
    'my_cats' => [7, 8],  // 2 role(s)
    'my_classes_taught' => [8],  // 1 role(s)
    'my_family' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 32, 33, 34, 63, 64, 71, 72],  // 22 role(s)
    'my_mentor' => [9],  // 1 role(s)
    'my_routes' => [3, 4, 23],  // 3 role(s)
    'my_schemes_of_work' => [5, 7, 8],  // 3 role(s)
    'my_students_list' => [6, 7, 63],  // 3 role(s)
    'my_students_performance' => [7],  // 1 role(s)
    'my_subject_syllabus' => [8],  // 1 role(s)
    'my_subjects_overview' => [8],  // 1 role(s)
    'my_vehicle' => [23],  // 1 role(s)
    'national_exams' => [6],  // 1 role(s)
    'new_applications' => [4],  // 1 role(s)
    'observation_feedback' => [9],  // 1 role(s)
    'observation_schedule' => [9],  // 1 role(s)
    'parent_meeting_records' => [7, 24, 63],  // 3 role(s)
    'parent_meetings' => [4, 5, 63],  // 3 role(s)
    'parent_refunds' => [3, 4],  // 2 role(s)
    'participation_reports' => [21],  // 1 role(s)
    'past_papers' => [8],  // 1 role(s)
    'payment_integration_settings' => [2],  // 1 role(s)
    'payment_reconciliation' => [3, 4],  // 2 role(s)
    'payment_records' => [10],  // 1 role(s)
    'payment_reports' => [10],  // 1 role(s)
    'payroll' => [3, 4, 10],  // 3 role(s)
    'payslips' => [3, 10],  // 2 role(s)
    'pending_discipline_cases' => [63],  // 1 role(s)
    'pending_exeat_requests' => [18],  // 1 role(s)
    'performance_analysis' => [5, 6],  // 2 role(s)
    'performance_reports' => [3],  // 1 role(s)
    'permission_changes' => [2],  // 1 role(s)
    'permission_policies' => [2],  // 1 role(s)
    'permissions_exeats' => [3, 4, 18, 33],  // 4 role(s)
    'petty_cash' => [3, 10],  // 2 role(s)
    'placement_tests' => [4, 6],  // 2 role(s)
    'policy_violations' => [2, 63],  // 2 role(s)
    'products_catalog_management' => [4, 14],  // 2 role(s)
    'pta_management' => [3, 4, 5],  // 3 role(s)
    'purchase_orders' => [3, 10, 14, 16, 71],  // 5 role(s)
    'purchase_reports' => [14, 71],  // 2 role(s)
    'qr-scanner' => [3, 4, 5, 7, 8, 18, 23, 63],  // 8 role(s)
    'rate_limiting_status' => [2],  // 1 role(s)
    'reflection_journal' => [9],  // 1 role(s)
    'report_admission_conversion' => [3, 4, 5],  // 3 role(s)
    'report_cards' => [3, 4, 5, 6],  // 4 role(s)
    'report_cbc_class_assessment' => [3, 4, 5, 6, 7],  // 5 role(s)
    'report_cbc_rubric_distribution' => [3, 4, 5, 6, 7],  // 5 role(s)
    'report_class_attendance_by_term' => [3, 4, 5, 6, 7],  // 5 role(s)
    'report_communication_delivery' => [3, 4, 5],  // 3 role(s)
    'report_current_enrollment_by_class' => [3, 4, 5, 6, 7],  // 5 role(s)
    'report_discipline_incident_trend' => [3, 4, 5, 63],  // 4 role(s)
    'report_enrolled_admissions_cohort' => [3, 4, 5],  // 3 role(s)
    'report_enrollment_trend' => [3, 4, 5],  // 3 role(s)
    'report_fee_arrears_by_class' => [3, 4, 5, 10],  // 4 role(s)
    'report_fee_summary_by_class' => [3, 4, 5, 10],  // 4 role(s)
    'report_food_consumption_trend' => [3, 4, 5, 16, 32, 71],  // 6 role(s)
    'report_inventory_stock_levels' => [3, 4, 5, 14],  // 4 role(s)
    'report_staff_attendance_rate' => [3, 4, 5, 6],  // 4 role(s)
    'report_system_audit_summary' => [2, 3],  // 2 role(s)
    'resource_based_permissions' => [2],  // 1 role(s)
    'results_analysis' => [5, 6],  // 2 role(s)
    'rewards_recognition' => [63],  // 1 role(s)
    'role_permission_matrix' => [2],  // 1 role(s)
    'room_assignments' => [18],  // 1 role(s)
    'route_access_rules' => [2],  // 1 role(s)
    'route_registry' => [2],  // 1 role(s)
    'sanctions' => [63],  // 1 role(s)
    'schedule_parent_meetings' => [24],  // 1 role(s)
    'schemes_of_work' => [6, 63],  // 2 role(s)
    'school_accountant_dashboard' => [10],  // 1 role(s)
    'school_administrative_officer_dashboard' => [4],  // 1 role(s)
    'school_counselor_chaplain_dashboard' => [24],  // 1 role(s)
    'school_events' => [3, 4, 5, 21],  // 4 role(s)
    'security_incidents' => [2],  // 1 role(s)
    'send_class_message' => [7],  // 1 role(s)
    'send_parent_notifications' => [4, 18, 24, 63],  // 4 role(s)
    'sick_bay' => [4, 18],  // 2 role(s)
    'sidebar_menus' => [2],  // 1 role(s)
    'special_needs' => [3, 4, 5, 6, 18, 24, 63],  // 7 role(s)
    'special_needs_students' => [7],  // 1 role(s)
    'sports' => [3, 21],  // 2 role(s)
    'staff_appointments' => [3, 4, 5, 6, 63],  // 5 role(s)
    'staff_attendance' => [3, 4, 5, 63],  // 4 role(s)
    'staff_id_cards' => [4],  // 1 role(s)
    'staff_leave' => [3, 4, 5, 6, 7, 8, 18, 23, 32, 33, 34, 63, 64],  // 13 role(s)
    'staff_lifecycle' => [3, 4, 5, 63],  // 4 role(s)
    'staff_onboarding' => [3, 4, 5, 63],  // 4 role(s)
    'staff_performance' => [3, 4, 63],  // 3 role(s)
    'staff_role_assignments' => [4],  // 1 role(s)
    'statutory_remittances' => [3, 4, 10],  // 3 role(s)
    'store_manager_dashboard' => [14],  // 1 role(s)
    'student_behavior_notes' => [7],  // 1 role(s)
    'student_counseling' => [5, 24, 63],  // 3 role(s)
    'student_fees' => [3, 4, 10],  // 3 role(s)
    'student_fund_transfers' => [3, 4],  // 2 role(s)
    'student_health' => [4, 5, 18],  // 3 role(s)
    'student_id_cards' => [4],  // 1 role(s)
    'student_leadership' => [3, 4, 5, 6, 7, 8, 9, 24, 63],  // 9 role(s)
    'student_performance' => [3, 5, 6],  // 3 role(s)
    'student_portfolio' => [4, 5, 6, 7, 8],  // 5 role(s)
    'student_profiles' => [4, 7, 18, 63],  // 4 role(s)
    'student_progress_reports' => [7],  // 1 role(s)
    'student_promotion' => [4, 6],  // 2 role(s)
    'student_route_assignment' => [4],  // 1 role(s)
    'student_subject_performance' => [8],  // 1 role(s)
    'student_welfare' => [24, 63],  // 2 role(s)
    'students_by_class' => [8],  // 1 role(s)
    'students_overview' => [3, 5],  // 2 role(s)
    'students_with_balance' => [4, 10],  // 2 role(s)
    'subject_class_comparison' => [8],  // 1 role(s)
    'subject_exam_schedule' => [8],  // 1 role(s)
    'subject_grade_entry' => [8],  // 1 role(s)
    'subject_grading_status' => [8],  // 1 role(s)
    'subject_results_summary' => [8],  // 1 role(s)
    'subject_students_list' => [8],  // 1 role(s)
    'subject_teacher_dashboard' => [8],  // 1 role(s)
    'supervision_roster' => [4, 5, 6],  // 3 role(s)
    'supplier_payments' => [3, 4],  // 2 role(s)
    'support_staff_dashboard' => [32, 33, 34, 64],  // 4 role(s)
    'suspensions_expulsions' => [63],  // 1 role(s)
    'system_administrator_dashboard' => [2],  // 1 role(s)
    'system_diagnostics' => [2],  // 1 role(s)
    'system_health' => [2],  // 1 role(s)
    'system_settings' => [2],  // 1 role(s)
    'teacher_performance_reviews' => [5, 6, 63],  // 3 role(s)
    'teacher_subject_assignment' => [6],  // 1 role(s)
    'teacher_workload' => [3, 5, 6],  // 3 role(s)
    'teaching_materials' => [8],  // 1 role(s)
    'term_reports' => [5, 6, 63],  // 3 role(s)
    'term_transition' => [4],  // 1 role(s)
    'time_bound_access' => [2],  // 1 role(s)
    'timetable' => [6, 7, 8, 9, 63, 64],  // 6 role(s)
    'today_absentees' => [7],  // 1 role(s)
    'todays_menu' => [16],  // 1 role(s)
    'token_management' => [2],  // 1 role(s)
    'transaction_approvals' => [10],  // 1 role(s)
    'transport_fees' => [3, 4],  // 2 role(s)
    'transport_passengers' => [23],  // 1 role(s)
    'uniform_discounts' => [3, 4, 14],  // 3 role(s)
    'uniform_point_of_sale' => [14],  // 1 role(s)
    'uniform_sales_records' => [14],  // 1 role(s)
    'uniform_stock_intake' => [14],  // 1 role(s)
    'uniform_unit_tracking' => [3, 4, 14],  // 3 role(s)
    'unmatched_payments' => [3, 4, 10],  // 3 role(s)
    'upload_teaching_resource' => [8],  // 1 role(s)
    'vaccination_records' => [18],  // 1 role(s)
    'vendor_invoices' => [14],  // 1 role(s)
    'vendors' => [3, 10, 14, 16, 71],  // 5 role(s)
    'view_attendance' => [3, 4, 5, 6, 8, 63],  // 6 role(s)
    'view_calendar' => [21],  // 1 role(s)
    'view_class_lists' => [9],  // 1 role(s)
    'view_past_papers' => [9],  // 1 role(s)
    'view_results' => [3, 4, 5, 6],  // 4 role(s)
    'view_student_info' => [9],  // 1 role(s)
    'view_syllabus' => [9],  // 1 role(s)
    'view_teaching_materials' => [9],  // 1 role(s)
    'webhook_registry' => [2],  // 1 role(s)
    'weekly_menu' => [16],  // 1 role(s)
    'welfare_follow_ups' => [24],  // 1 role(s)
    'welfare_records' => [24],  // 1 role(s)
    'welfare_summary' => [24],  // 1 role(s)
    'year_calendar' => [3, 4],  // 2 role(s)
    'year_rollover' => [4],  // 1 role(s)
];











    // role name → role_id for string-based lookups
    private const ROLE_NAME_MAP = [
        'system administrator'    => 2,
        'director'                => 3,
        'school administrator'    => 4,
        'headteacher'             => 5,
        'deputy head - academic'  => 6,
        'deputy head academic'    => 6,
        'class teacher'           => 7,
        'subject teacher'         => 8,
        'intern/student teacher'  => 9,
        'intern student teacher'  => 9,
        'accountant'              => 10,
        'school accountant'       => 10,
        'inventory manager'       => 14,
        'store manager'           => 14,
        'cateress'                => 16,
        'catering manager'        => 16,
        'boarding master'         => 18,
        'matron'                  => 18,
        'housemother'             => 18,
        'talent development'      => 21,
        'hod talent development'  => 21,
        'driver'                  => 23,
        'chaplain'                => 24,
        'counselor'               => 24,
        'school counselor'        => 24,
        'kitchen staff'           => 32,
        'security staff'          => 33,
        'janitor'                 => 34,
        'deputy head - discipline'=> 63,
        'deputy head discipline'  => 63,
        'staff'                   => 64,
    ];

    /**
     * Get dashboard route for a role (by ID, name, or array of roles).
     */
    public static function getDashboardForRole($role): string
    {
        if (is_array($role)) {
            if (empty($role)) return self::DEFAULT_DASHBOARD;
            $role = $role[0]['id'] ?? $role[0]['name'] ?? $role[0];
        }

        if (is_numeric($role)) {
            return self::ROLE_DASHBOARDS[(int)$role] ?? self::DEFAULT_DASHBOARD;
        }

        // String name lookup — normalise and match
        $key = strtolower(trim((string)$role));
        if (isset(self::ROLE_NAME_MAP[$key])) {
            $id = self::ROLE_NAME_MAP[$key];
            return self::ROLE_DASHBOARDS[$id] ?? self::DEFAULT_DASHBOARD;
        }

        // Unknown or newly-created roles must be explicitly assigned in the
        // database and mirrored in this fallback registry. Never infer a
        // privileged dashboard from an arbitrary role name.
        return self::DEFAULT_DASHBOARD;
    }

    public static function normalizeDashboardKey(string $key): string
    {
        $key = trim($key);
        return self::DASHBOARD_ALIASES[$key] ?? $key;
    }

    public static function getDefaultDashboard(): string
    {
        return self::DEFAULT_DASHBOARD;
    }

    public static function getRoleDashboards(): array
    {
        return self::ROLE_DASHBOARDS;
    }

    public static function getRoleNameMap(): array
    {
        return self::ROLE_NAME_MAP;
    }

    public static function dashboardExists(string $key): bool
    {
        $key = self::normalizeDashboardKey($key);
        return file_exists(__DIR__ . '/../components/dashboards/' . $key . '.php');
    }

    public static function getDashboardPath(string $key): ?string
    {
        $key = self::normalizeDashboardKey($key);
        $path = __DIR__ . '/../components/dashboards/' . $key . '.php';
        return file_exists($path) ? $path : null;
    }

    public static function getDashboardJsPath(string $key): ?string
    {
        $key = self::normalizeDashboardKey($key);
        $path = __DIR__ . '/../js/dashboards/' . $key . '.js';
        return file_exists($path) ? $path : null;
    }

    public static function getDashboardRegistry(): array
    {
        $registry = [];
        foreach (array_unique(self::ROLE_DASHBOARDS) as $key) {
            $registry[$key] = [
                'key' => $key,
                'php' => self::dashboardExists($key),
                'js' => self::getDashboardJsPath($key) !== null,
            ];
        }

        if (!isset($registry[self::DEFAULT_DASHBOARD])) {
            $registry[self::DEFAULT_DASHBOARD] = [
                'key' => self::DEFAULT_DASHBOARD,
                'php' => self::dashboardExists(self::DEFAULT_DASHBOARD),
                'js' => false,
            ];
        }

        foreach (self::DASHBOARD_ALIASES as $alias => $target) {
            $registry[$alias] = [
                'key' => $alias,
                'alias_for' => $target,
                'php' => self::dashboardExists($target),
                'js' => self::getDashboardJsPath($target) !== null,
            ];
        }

        ksort($registry);
        return array_values($registry);
    }

    public static function getDashboardUrl($role): string
    {
        return '?route=' . self::getDashboardForRole($role);
    }

    /** Returns the approved fallback role-to-dashboard pairs. */
    public static function getAllDashboards(): array
    {
        $result = [];
        foreach (self::ROLE_DASHBOARDS as $roleId => $key) {
            if (isset($result[$key])) continue; // dedupe support_staff_dashboard etc.
            $result[$key] = [
                'name'      => $key,
                'title'     => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $key))),
                'is_active' => 1,
            ];
        }
        return array_values($result);
    }

    /** Returns fallback dashboard information for one role. */
    public static function getDashboardsForRole(int $roleId): array
    {
        $key = self::ROLE_DASHBOARDS[$roleId] ?? null;
        if (!$key) return [];
        return [[
            'name'       => $key,
            'title'      => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $key))),
            'is_primary' => 1,
            'is_active'  => 1,
        ]];
    }

    /**
     * Check if a route is allowed for a given role ID.
     * Returns true if the route has no explicit restriction (open route)
     * or if the role is in the allowed list.
     */
    public static function isRouteAllowedForRole(string $route, int $roleId): bool
    {
        $route = self::ROUTE_ALIASES[$route] ?? $route;
        $reportAccess = ReportRegistry::roleCanAccess($route, $roleId);
        if ($reportAccess !== null) return $reportAccess;

        // Role 2 is infrastructure-only and deny-by-default. Do this before
        // dashboard and generic route handling so manually typed school URLs
        // cannot cross the SYSTEM/SCHOOL boundary.
        if ($roleId === 2) {
            return in_array($route, self::SYSTEM_ADMIN_ROUTES, true);
        }

        // Dashboard routes bypass the allowlist (each role has its own dashboard)
        $dashboardKeys = array_values(self::ROLE_DASHBOARDS);
        if (in_array($route, $dashboardKeys, true)) {
            return (self::ROLE_DASHBOARDS[$roleId] ?? null) === $route;
        }

        // If no restriction defined, the route is public within the app shell
        $allowed = self::ROUTE_ROLES[$route] ?? null;
        if ($allowed === null) {
            return true;
        }

        return in_array($roleId, $allowed, true);
    }

    /** @deprecated Use getDashboardForRole() */
    public static function getUserDefaultDashboard(): string
    {
        return self::DEFAULT_DASHBOARD;
    }

    public static function redirectToDefaultDashboard(bool $exit = true): void
    {
        header('Location: ?route=' . self::DEFAULT_DASHBOARD);
        if ($exit) exit;
    }

    // No-op: this fallback registry has no runtime cache.
    public static function clearCache(): void {}
}
