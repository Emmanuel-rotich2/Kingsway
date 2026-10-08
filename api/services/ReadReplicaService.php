<?php

namespace App\API\Services;

use App\Database\ConnectionManager;
use PDO;

/**
 * ReadReplicaService - explicit read-model routing (roadmap §4.5).
 *
 * The configured master schema is the single WRITE authority.
 * Reads are projected into the configured reads schema as allowlisted objects.
 * MySQL has no native materialized views, so eligible reporting projections
 * are refreshed into ordinary tables. Request-path reads fail closed when
 * their configured physical materialization is not fresh.
 *
 * A pass-through view is not an offload: MySQL evaluates its source query on
 * the master schema. It is a refresh source only, never a request-path target.
 */
final class ReadReplicaService
{
    /**
     * Catalogue: projection name => source view name on the master schema.
     * The physical master schema is resolved at runtime via
     * ConnectionManager::schemaFor(NS_MASTER) so production deployments can
     * rename databases via config without touching this catalogue. The reads
     * namespace mirrors each source as an allowlisted read object.
     *
     * @var array<string,string>
     */
    public const PROJECTIONS = [        'dashboards' => 'vw_dashboards',
        'student_growth_trend' => 'vw_student_growth_trend',
        'student_timeline_subject_scores' => 'vw_student_timeline_subject_scores',
        'deputy_class_formative_performance' => 'vw_deputy_class_formative_performance',

        'student_fee_ledger' => 'vw_student_fee_ledger',
        'expense_directory' => 'vw_expense_directory',
        'staff_meeting_summary' => 'vw_staff_meeting_summary',
        'staff_meeting_member' => 'vw_staff_meeting_member',
        'parent_student_dashboard' => 'vw_parent_student_dashboard',
        'curriculum_taxonomy' => 'vw_curriculum_taxonomy',
        'payment_directory' => 'vw_payment_directory',
        'internal_conversation_member' => 'vw_internal_conversation_member',
        'internal_message_recipient' => 'vw_internal_message_recipient',
        'internal_message_recipient_directory' => 'vw_internal_message_recipient_directory',
        'student_transport_roster' => 'vw_student_transport_roster',
        'academic_term_summary' => 'vw_academic_term_summary',
        'academic_class_directory' => 'vw_academic_class_directory',
        'activity_schedule_directory' => 'vw_activity_schedule_directory',
        'activity_category_summary' => 'vw_activity_category_summary',
        'collection_rate_by_class' => 'vw_collection_rate_by_class',
        'student_attendance_analytics' => 'vw_student_attendance_analytics',
        'student_attendance_enrollment' => 'vw_student_attendance_enrollment',
        'admission_application_workflow' => 'vw_admission_application_workflow',
        'student_fee_balances' => 'vw_student_fee_balances',
        'student_term_performance' => 'vw_student_term_performance',
        'class_learning_area_performance' => 'vw_class_learning_area_performance',
        'student_learning_progress' => 'vw_student_learning_progress',
        'budget_utilization' => 'vw_budget_utilization',
        'staff_daily_register' => 'vw_staff_daily_register',
        'student_attendance_summary' => 'vw_student_attendance_summary',
        'staff_workload' => 'vw_staff_workload',
        'dormitory_occupancy' => 'vw_dormitory_occupancy',
        'student_transport_summary' => 'vw_student_transport_summary',
        'student_health_summary' => 'vw_student_health_summary',
        'fee_collection_monthly_trend' => 'vw_fee_collection_monthly_trend',
        'fee_status_summary' => 'vw_fee_status_summary',
        // Phase 3 expansion (2026-10-04 scaling masterplan): pre-composed
        // dashboard/portfolio reads so request threads never join views.
        'student_dashboard_summary' => 'vw_student_dashboard_summary',
        'portfolio_hub_summary' => 'vw_portfolio_hub_summary',
        'portfolio_hub_class' => 'vw_portfolio_hub_class',
        // Phase 1 directory stars (2026-10-04). These four retire the four
        // repeated single-join enrichment shapes measured on the read path:
        // persons, the academic calendar, learner placement and staff org
        // placement. Each is materialized so a request is one indexed
        // single-table read with a live WHERE filter and no JOIN.
        'person_directory' => 'vw_person_directory',
        'academic_calendar' => 'vw_academic_calendar',
        'student_directory' => 'vw_student_directory',
        'staff_directory' => 'vw_staff_directory',
        // Term-keyed calendar. Separate from academic_calendar because that one
        // is class-stream-keyed and cannot serve a term-label lookup.
        'academic_term' => 'vw_academic_term',
        // Phase 2 bridges (2026-10-04).
        // student_term_placement is the class-stream-year-TERM bridge: one row per
        // (enrollment x term). It is deliberately a separate projection because
        // student_directory is one row per enrollment, so folding terms into it
        // would fan out and break every caller keyed on student_id.
        'student_term_placement' => 'vw_student_term_placement',
        // guardian_link is the parent-child bridge. student_parents has no id
        // column, so (student_id, parent_id) is the key. Carries parent contact
        // data for guardian communication and student identity for the parent
        // portal child list.
        'guardian_link' => 'vw_guardian_link',
        'calendar_day_type' => 'vw_calendar_day',
        'timetable_conflict' => 'vw_timetable_conflicts',
        // Phase 2 bridges, set 2 (2026-10-05): flat bridges built from the
        // measured two-JOIN demand (scripts/derive_bridge_columns.php).
        // Column naming is systematic {table}_{column} so coverage checks
        // and caller conversion stay mechanical.
        'dashboard_catalog' => 'vw_dashboard_catalog',
        'learner_competency' => 'vw_learner_competency',
        'uniform_catalog' => 'vw_uniform_catalog',
        'payslip' => 'vw_payslip',
        'staff_profile_assignment' => 'vw_staff_profile_assignment',
        // Blocked-site companions: framework dimension + orphan-preserving
        // enrollment register (one row per enrollment, no person joins).
        'competency_catalog' => 'vw_competency_catalog',
        'enrollment_membership' => 'vw_enrollment_membership',

        'academic_year_class_stream_learning_areas' => 'vw_academic_year_class_stream_learning_areas',
        'term_subject_scores' => 'vw_term_subject_scores',
        'student_fee_obligations' => 'vw_student_fee_obligations',
        'assessment_results' => 'vw_assessment_results',
        'strands' => 'vw_strands',
        'dashboards' => 'vw_dashboards',
        'sidebar_menu_items' => 'vw_sidebar_menu_items',
        'admission_applications' => 'vw_admission_applications',
        'inventory_transactions' => 'vw_inventory_transactions',
        'sub_strands' => 'vw_sub_strands',
        'academic_year_classes' => 'vw_academic_year_classes',
        'mpesa_transactions' => 'vw_mpesa_transactions',
        'academic_year_calendar_days' => 'vw_academic_year_calendar_days',
        'school_financial_accounts' => 'vw_school_financial_accounts',
        'workflow_instances' => 'vw_workflow_instances',
        'classes' => 'vw_classes',
        'activity_schedule' => 'vw_activity_schedule',
        'academic_year_fee_schedules' => 'vw_academic_year_fee_schedules',
        'attendance_sessions' => 'vw_attendance_sessions',
        'payments' => 'vw_payments',
        'learning_areas' => 'vw_learning_areas',
        'academic_years' => 'vw_academic_years',
        'streams' => 'vw_streams',
        'departments' => 'vw_departments',
        'staff_types' => 'vw_staff_types',
        'inventory_items' => 'vw_inventory_items',
        'inventory_categories' => 'vw_inventory_categories',
        'lesson_plans' => 'vw_lesson_plans',
        'schemes_of_work' => 'vw_schemes_of_work',
        'assessment_tools' => 'vw_assessment_tools',
        'assessment_rubrics' => 'vw_assessment_rubrics',
        'grade_rules' => 'vw_grade_rules',
        'grading_scales' => 'vw_grading_scales',
        'timetable_entries' => 'vw_timetable_entries',
        'time_slots' => 'vw_time_slots',
        'rooms' => 'vw_rooms',
        'dormitories' => 'vw_dormitories',
        'vehicles' => 'vw_vehicles',
        'transport_routes' => 'vw_transport_routes',
        'uniform_catalog_products' => 'vw_uniform_catalog_products',
        'uniform_sizes' => 'vw_uniform_sizes',
        'fee_credit_notes' => 'vw_fee_credit_notes',
        'payroll_runs' => 'vw_payroll_runs',
        'payslips' => 'vw_payslips',
        'staff_appointments' => 'vw_staff_appointments',
        'staff_categories' => 'vw_staff_categories',
        'staff_department_assignments' => 'vw_staff_department_assignments',
        'staff_duty_roster' => 'vw_staff_duty_roster',
        'student_attendance' => 'vw_student_attendance',
        'student_health_records' => 'vw_student_health_records',
        'student_health_visits' => 'vw_student_health_visits',
        'student_parents' => 'vw_student_parents',
        'student_permissions' => 'vw_student_permissions',
        'student_transport_assignments' => 'vw_student_transport_assignments',
        'student_transport_entitlements' => 'vw_student_transport_entitlements',
        'student_transport_attendance' => 'vw_student_transport_attendance',
        'student_vaccinations' => 'vw_student_vaccinations',
        'student_award_categories' => 'vw_student_award_categories',
        'student_award_types' => 'vw_student_award_types',
        'student_clearances' => 'vw_student_clearances',
        'student_fee_migration_snapshots' => 'vw_student_fee_migration_snapshots',
        'student_fee_rollover_balances' => 'vw_student_fee_rollover_balances',
        'student_scholarship_awards' => 'vw_student_scholarship_awards',
        'student_transitions' => 'vw_student_transitions',
        'communication_attachments' => 'vw_communication_attachments',
        'communication_threads' => 'vw_communication_threads',
        'announcements_bulletin' => 'vw_announcements_bulletin',
        'bank_transactions' => 'vw_bank_transactions',
        'budgets' => 'vw_budgets',
        'budget_line_items' => 'vw_budget_line_items',
        'expenses' => 'vw_expenses',
        'fixed_assets' => 'vw_fixed_assets',
        'food_consumption_records' => 'vw_food_consumption_records',
        'meal_plans' => 'vw_meal_plans',
        'purchase_orders' => 'vw_purchase_orders',
        'requisition_items' => 'vw_requisition_items',
        'requisitions' => 'vw_requisitions',
        'suppliers' => 'vw_suppliers',
        'library_books' => 'vw_library_books',
        'library_categories' => 'vw_library_categories',
        'library_issues' => 'vw_library_issues',
        'portfolio_artifacts' => 'vw_portfolio_artifacts',
        'promotion_batches' => 'vw_promotion_batches',
        'cash_reconciliation_sessions' => 'vw_cash_reconciliation_sessions',
        'chart_of_accounts' => 'vw_chart_of_accounts',
        'financial_statement_lines' => 'vw_financial_statement_lines',
        'school_financial_account_channels' => 'vw_school_financial_account_channels',
        'payment_collection_routes' => 'vw_payment_collection_routes',
        'payment_collection_route_channels' => 'vw_payment_collection_route_channels',
        'payment_pos_terminals' => 'vw_payment_pos_terminals',
        'payment_reconciliations' => 'vw_payment_reconciliations',
        'payment_unmatched_cases' => 'vw_payment_unmatched_cases',
        'transport_monthly_bills' => 'vw_transport_monthly_bills',
        'transport_payment_intents' => 'vw_transport_payment_intents',
        'transport_entitlement_payment_allocations' => 'vw_transport_entitlement_payment_allocations',
        'transport_vehicle_routes' => 'vw_transport_vehicle_routes',
        'uniform_catalog_images' => 'vw_uniform_catalog_images',
        'uniform_payment_intents' => 'vw_uniform_payment_intents',
        'uniform_payment_records' => 'vw_uniform_payment_records',
        'uniform_sales' => 'vw_uniform_sales',
        'routes_registry' => 'vw_routes_registry',
        'workflow_stage_history' => 'vw_workflow_stage_history',
        'workflow_stages' => 'vw_workflow_stages',
        'workflow_definitions' => 'vw_workflow_definitions',
        'admission_documents' => 'vw_admission_documents',
        'admission_interview_assessment_items' => 'vw_admission_interview_assessment_items',
        'admission_placement_tests' => 'vw_admission_placement_tests',
        'admission_windows' => 'vw_admission_windows',
        'analytics_report_metrics' => 'vw_analytics_report_metrics',
        'analytics_report_runs' => 'vw_analytics_report_runs',
        'assessment_learning_outcomes' => 'vw_assessment_learning_outcomes',
        'assessment_policy_documents' => 'vw_assessment_policy_documents',
        'assessment_policy_schedule_rules' => 'vw_assessment_policy_schedule_rules',
        'assessment_rubric_criteria' => 'vw_assessment_rubric_criteria',
        'core_competencies' => 'vw_core_competencies',
        'core_values' => 'vw_core_values',
        'counseling_sessions' => 'vw_counseling_sessions',
        'learner_competencies' => 'vw_learner_competencies',
        'learner_values_acquisition' => 'vw_learner_values_acquisition',
        'learning_outcomes' => 'vw_learning_outcomes',
        'leave_types' => 'vw_leave_types',
        'lesson_plan_assessment_rubrics' => 'vw_lesson_plan_assessment_rubrics',
        'lesson_plan_assessment_tools' => 'vw_lesson_plan_assessment_tools',
        'lesson_plan_competencies' => 'vw_lesson_plan_competencies',
        'lesson_plan_learner_evidence_questions' => 'vw_lesson_plan_learner_evidence_questions',
        'lesson_plan_learner_evidence_resources' => 'vw_lesson_plan_learner_evidence_resources',
        'lesson_plan_outcomes' => 'vw_lesson_plan_outcomes',
        'lesson_plan_rubrics' => 'vw_lesson_plan_rubrics',
        'sub_strand_competencies' => 'vw_sub_strand_competencies',
        'strand_competency' => 'vw_strand_competency',
        'activity_categories' => 'vw_activity_categories',
        'activity_participants' => 'vw_activity_participants',
        'activity_resources' => 'vw_activity_resources',
        'chapel_program_sessions' => 'vw_chapel_program_sessions',
        'chaplaincy_volunteers' => 'vw_chaplaincy_volunteers',
        'duty_roster_drafts' => 'vw_duty_roster_drafts',
        'equipment_maintenance' => 'vw_equipment_maintenance',
        'exam_timetable_draft_entries' => 'vw_exam_timetable_draft_entries',
        'exam_timetable_drafts' => 'vw_exam_timetable_drafts',
        'extra_charge_classes' => 'vw_extra_charge_classes',
        'extra_charges' => 'vw_extra_charges',
        'national_assessment_windows' => 'vw_national_assessment_windows',
        'parent_meeting_targets' => 'vw_parent_meeting_targets',
        'past_papers' => 'vw_past_papers',
        'performance_review_kpis' => 'vw_performance_review_kpis',
        'staff_children' => 'vw_staff_children',
        'staff_deductions' => 'vw_staff_deductions',
        'staff_offboarding' => 'vw_staff_offboarding',
        'staff_payroll_award_recipients' => 'vw_staff_payroll_award_recipients',
        'staff_qualifications' => 'vw_staff_qualifications',
        'staff_role_default_positions' => 'vw_staff_role_default_positions',
        'staff_specialization_qualifications' => 'vw_staff_specialization_qualifications',
        'scheme_workbook_items' => 'vw_scheme_workbook_items',
        'scheme_workbook_weeks' => 'vw_scheme_workbook_weeks',
        'scheme_templates' => 'vw_scheme_templates',
        'scheme_workbooks' => 'vw_scheme_workbooks',
        'timetable_drafts' => 'vw_timetable_drafts',
        'academic_class_progression' => 'vw_academic_class_progression',
        'academic_year_class_learning_area_teachers' => 'vw_academic_year_class_learning_area_teachers',
        'academic_year_class_stream_learning_area_teachers' => 'vw_academic_year_class_stream_learning_area_teachers',
        'academic_year_class_learning_areas' => 'vw_academic_year_class_learning_areas',
        'academic_year_calendar' => 'vw_academic_year_calendar',
        'blocked_devices' => 'vw_blocked_devices',
        'catalog_order_items' => 'vw_catalog_order_items',
        'catalog_orders' => 'vw_catalog_orders',
        'catalog_reviews' => 'vw_catalog_reviews',
        'catalog_stock_units' => 'vw_catalog_stock_units',
        'catalog_wishlists' => 'vw_catalog_wishlists',
        'inventory_locations' => 'vw_inventory_locations',
        'job_vacancies' => 'vw_job_vacancies',
        'leadership_positions' => 'vw_leadership_positions',

        'academic_term_terms' => 'vw_academic_term_terms',
        'academic_year_class_stream_learning_areas_detailed' => 'vw_academic_year_class_stream_learning_areas_detailed',
        'academic_year_classes_streams' => 'vw_academic_year_classes_streams',
        'assessment_results_detailed' => 'vw_assessment_results_detailed',
        'leadership_positions_categories' => 'vw_leadership_positions_categories',
        'strands_learning_areas' => 'vw_strands_learning_areas',
        'strands_sub_strands' => 'vw_strands_sub_strands',
        'student_academic_enrollments_fees' => 'vw_student_academic_enrollments_fees',
        'student_academic_enrollments_streams' => 'vw_student_academic_enrollments_streams',
        'student_fee_obligations_enrolled' => 'vw_student_fee_obligations_enrolled',
        'academic_year_calendar_days_typed' => 'vw_academic_year_calendar_days_typed',
        'inventory_transactions_items' => 'vw_inventory_transactions_items',
        'admission_applications_interviews' => 'vw_admission_applications_interviews',
        'learner_values_acquisition_values' => 'vw_learner_values_acquisition_values',
        'portfolio_artifacts_portfolios' => 'vw_portfolio_artifacts_portfolios',
        'attendance_sessions_config' => 'vw_attendance_sessions_config',
        'mpesa_transactions_payments' => 'vw_mpesa_transactions_payments',
        'persons' => 'vw_persons',
        'student_academic_enrollments' => 'vw_student_academic_enrollments',
        'academic_year_class_streams' => 'vw_academic_year_class_streams',
        'students' => 'vw_students',
        'staff' => 'vw_staff',
        'academic_year_terms' => 'vw_academic_year_terms',
        'assessments' => 'vw_assessments',
        'parents' => 'vw_parents',
        'exam_schedules' => 'vw_exam_schedules',
        'learner_placement' => 'vw_learner_placement',
        'exam_context' => 'vw_exam_context',
        'scheme_lesson_context' => 'vw_scheme_lesson_context',
        'learner_guardian' => 'vw_learner_guardian',
        'staff_context' => 'vw_staff_context',
        'attendance_register_context' => 'vw_attendance_register_context',
        'fee_statement_context' => 'vw_fee_statement_context',
        'admission_workflow_context' => 'vw_admission_workflow_context',
        'transport_assignment_context' => 'vw_transport_assignment_context',
        'boarding_dorm_context' => 'vw_boarding_dorm_context',
        'lesson_plan_context' => 'vw_lesson_plan_context',
        'class_stream_directory' => 'vw_class_stream_directory',
    ];

    /**
     * Read-model safety policy. Storage can change without changing callers.
     *
     * @var array<string,array{storage_mode:string,sensitivity:string,max_age_seconds:int}>
     */
    public const POLICIES = [
        'student_fee_ledger' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 120],
        'expense_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'staff_meeting_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_meeting_member' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'parent_student_dashboard' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 120],
        'curriculum_taxonomy' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 3600],
        'payment_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'internal_conversation_member' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'internal_message_recipient' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'internal_message_recipient_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'student_transport_roster' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'academic_term_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'academic_class_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'activity_schedule_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'activity_category_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'collection_rate_by_class' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'student_attendance_analytics' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_attendance_enrollment' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_fee_balances' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 120],
        'student_term_performance' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'class_learning_area_performance' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'student_learning_progress' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'budget_utilization' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'staff_daily_register' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'student_attendance_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'staff_workload' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 900],
        'dormitory_occupancy' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_transport_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_health_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'health', 'max_age_seconds' => 600],
        'fee_collection_monthly_trend' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 900],
        'fee_status_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'student_dashboard_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'portfolio_hub_summary' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        // These are scheduled every five minutes. Give the scheduler enough
        // headroom for a slow rebuild and one delayed/missed cron tick; a hard
        // five-minute cutoff made otherwise healthy snapshots intermittently
        // unusable between the refresh boundary and the next publish.
        'portfolio_hub_class' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_growth_trend' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'student_timeline_subject_scores' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'deputy_class_formative_performance' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        // Directory stars carry identity columns, so they are declared personal
        // and kept on short freshness: staff act on these rows during the day.
        'person_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'academic_calendar' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'student_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'staff_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'academic_term' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        // Placement carries learner identity, so personal on the same 600s budget
        // as student_directory. Guardian links carry a parent phone/email next to
        // a child record, so they are the most sensitive projection in the set and
        // stay on the shortest refresh the cron supports.
        'student_term_placement' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'guardian_link' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'calendar_day_type' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'timetable_conflict' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        // Set-2 bridges: cold registries on the 10-minute cadence (900s
        // budget = two missed runs tolerated). payslip is payroll data so
        // it stays financial; learner_competency is learner evidence.
        'dashboard_catalog' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'learner_competency' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'uniform_catalog' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'payslip' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 900],
        // Staff org placement: staff-scoped like staff_directory, on the cold
        // 10-minute cadence because department assignments change rarely.
        'staff_profile_assignment' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 900],
        'admission_application_workflow' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'competency_catalog' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'enrollment_membership' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],

        'academic_year_class_stream_learning_areas' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'term_subject_scores' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'student_fee_obligations' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'assessment_results' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'strands' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'dashboards' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'sidebar_menu_items' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'admission_applications' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'inventory_transactions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'sub_strands' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_year_classes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'mpesa_transactions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'academic_year_calendar_days' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'school_financial_accounts' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'workflow_instances' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'classes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'activity_schedule' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_year_fee_schedules' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'attendance_sessions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'payments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'learning_areas' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_years' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'streams' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'departments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'staff_types' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'inventory_items' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'inventory_categories' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'lesson_plans' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'schemes_of_work' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'assessment_tools' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'assessment_rubrics' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'grade_rules' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'grading_scales' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'timetable_entries' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'time_slots' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'rooms' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'dormitories' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'vehicles' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'transport_routes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'uniform_catalog_products' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'uniform_sizes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'fee_credit_notes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'payroll_runs' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'payslips' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'staff_appointments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_categories' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_department_assignments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_duty_roster' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'student_attendance' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_health_records' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_health_visits' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_parents' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_permissions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_transport_assignments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_transport_entitlements' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_transport_attendance' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_vaccinations' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_award_categories' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_award_types' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_clearances' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_fee_migration_snapshots' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_fee_rollover_balances' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_scholarship_awards' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_transitions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'communication_attachments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'communication_threads' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'announcements_bulletin' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'bank_transactions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'budgets' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'budget_line_items' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'expenses' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'fixed_assets' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'food_consumption_records' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'meal_plans' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'purchase_orders' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'requisition_items' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'requisitions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'suppliers' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'library_books' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'library_categories' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'library_issues' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'portfolio_artifacts' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'promotion_batches' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'cash_reconciliation_sessions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'chart_of_accounts' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'financial_statement_lines' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'school_financial_account_channels' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'payment_collection_routes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'payment_collection_route_channels' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'payment_pos_terminals' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'payment_reconciliations' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'payment_unmatched_cases' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'transport_monthly_bills' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'transport_payment_intents' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'transport_entitlement_payment_allocations' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'transport_vehicle_routes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'uniform_catalog_images' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'uniform_payment_intents' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'uniform_payment_records' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'uniform_sales' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'routes_registry' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'security', 'max_age_seconds' => 600],
        'workflow_stage_history' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'workflow_stages' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'workflow_definitions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'admission_documents' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'admission_interview_assessment_items' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'admission_placement_tests' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'admission_windows' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'analytics_report_metrics' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'analytics_report_runs' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'assessment_learning_outcomes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'assessment_policy_documents' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'assessment_policy_schedule_rules' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'assessment_rubric_criteria' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'core_competencies' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'core_values' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'counseling_sessions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'learner_competencies' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'learner_values_acquisition' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'learning_outcomes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'leave_types' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'lesson_plan_assessment_rubrics' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'lesson_plan_assessment_tools' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'lesson_plan_competencies' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'lesson_plan_learner_evidence_questions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'lesson_plan_learner_evidence_resources' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'lesson_plan_outcomes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'lesson_plan_rubrics' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'sub_strand_competencies' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'strand_competency' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'activity_categories' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'activity_participants' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'activity_resources' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'chapel_program_sessions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'chaplaincy_volunteers' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'duty_roster_drafts' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'equipment_maintenance' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'exam_timetable_draft_entries' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'exam_timetable_drafts' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'extra_charge_classes' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'extra_charges' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'national_assessment_windows' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'parent_meeting_targets' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'past_papers' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'performance_review_kpis' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'staff_children' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_deductions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_offboarding' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_payroll_award_recipients' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_qualifications' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_role_default_positions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'staff_specialization_qualifications' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'scheme_workbook_items' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'scheme_workbook_weeks' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'scheme_templates' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'scheme_workbooks' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'timetable_drafts' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_class_progression' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_year_class_learning_area_teachers' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_year_class_stream_learning_area_teachers' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_year_class_learning_areas' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_year_calendar' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'blocked_devices' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'catalog_order_items' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'catalog_orders' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'catalog_reviews' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'catalog_stock_units' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'catalog_wishlists' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'inventory_locations' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'job_vacancies' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'leadership_positions' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],

        'academic_term_terms' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_year_class_stream_learning_areas_detailed' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'academic_year_classes_streams' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'assessment_results_detailed' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'leadership_positions_categories' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'strands_learning_areas' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'strands_sub_strands' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'student_academic_enrollments_fees' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'student_academic_enrollments_streams' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'student_fee_obligations_enrolled' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'academic_year_calendar_days_typed' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'inventory_transactions_items' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'admission_applications_interviews' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'learner_values_acquisition_values' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'portfolio_artifacts_portfolios' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'attendance_sessions_config' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
        'mpesa_transactions_payments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 600],
        'persons' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'student_academic_enrollments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'academic_year_class_streams' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'students' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'staff' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 600],
        'academic_year_terms' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'assessments' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'parents' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'exam_schedules' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'learner_placement' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'exam_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'scheme_lesson_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'learner_guardian' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'staff_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'staff', 'max_age_seconds' => 900],
        'attendance_register_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'fee_statement_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 120],
        'admission_workflow_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'transport_assignment_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'boarding_dorm_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'personal', 'max_age_seconds' => 600],
        'lesson_plan_context' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 900],
        'class_stream_directory' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'normal', 'max_age_seconds' => 600],
    ];

    /** Materialized targets use separate names from the legacy pass-through views. */
    public const MATERIALIZED_TARGETS = [
        'academic_class_directory' => 'mmv_academic_class_directory',
        'activity_schedule_directory' => 'mmv_activity_schedule_directory',
        'activity_category_summary' => 'mmv_activity_category_summary',
        'collection_rate_by_class' => 'mmv_collection_rate_by_class',
        'class_learning_area_performance' => 'mmv_class_learning_area_performance',
        'budget_utilization' => 'mmv_budget_utilization',
        'dormitory_occupancy' => 'mmv_dormitory_occupancy',
        'fee_collection_monthly_trend' => 'mmv_fee_collection_monthly_trend',
        // The fee-workspace scalability fix: the heavy aggregation runs only
        // in the 5-minute projection worker; the fee pages read this summary
        // table (a filtered scan, millisecond-fast at any enrolment).
        'fee_status_summary' => 'mmv_fee_status_summary',
        'student_dashboard_summary' => 'mmv_student_dashboard_summary',
        'portfolio_hub_summary' => 'mmv_portfolio_hub_summary',
        'portfolio_hub_class' => 'mmv_portfolio_hub_class',
        'student_growth_trend' => 'mmv_student_growth_trend',
        'student_timeline_subject_scores' => 'mmv_student_timeline_subject_scores',
        'deputy_class_formative_performance' => 'mmv_deputy_class_formative_performance',
        // Phase 1 directory stars.
        'person_directory' => 'mmv_person_directory',
        'academic_calendar' => 'mmv_academic_calendar',
        'student_directory' => 'mmv_student_directory',
        'staff_directory' => 'mmv_staff_directory',
        'academic_term' => 'mmv_academic_term',
        // Phase 2 bridges.
        'student_term_placement' => 'mmv_student_term_placement',
        'guardian_link' => 'mmv_guardian_link',
        'calendar_day_type' => 'mmv_calendar_day_type',
        'timetable_conflict' => 'mmv_timetable_conflict',
        'dashboard_catalog' => 'mmv_dashboard_catalog',
        'learner_competency' => 'mmv_learner_competency',
        'uniform_catalog' => 'mmv_uniform_catalog',
        'payslip' => 'mmv_payslip',
        'staff_profile_assignment' => 'mmv_staff_profile_assignment',
        'admission_application_workflow' => 'mmv_admission_application_workflow',
        'student_attendance_enrollment' => 'mmv_student_attendance_enrollment',
        'admission_application_workflow' => 'mmv_admission_application_workflow',
        'competency_catalog' => 'mmv_competency_catalog',
        'enrollment_membership' => 'mmv_enrollment_membership',
        // Formerly pass_through_view: these were evaluated live on the master at
        // request time, so their freshness budget was meaningless. Materialized
        // 2026-10-04; student_fee_balances has 54 read call sites.
        'student_fee_balances' => 'mmv_student_fee_balances',
        'student_fee_ledger' => 'mmv_student_fee_ledger',
        'expense_directory' => 'mmv_expense_directory',
        'staff_meeting_summary' => 'mmv_staff_meeting_summary',
        'staff_meeting_member' => 'mmv_staff_meeting_member',
        'parent_student_dashboard' => 'mmv_parent_student_dashboard',
        'curriculum_taxonomy' => 'mmv_curriculum_taxonomy',
        'payment_directory' => 'mmv_payment_directory',
        'internal_conversation_member' => 'mmv_internal_conversation_member',
        'internal_message_recipient' => 'mmv_internal_message_recipient',
        'internal_message_recipient_directory' => 'mmv_internal_message_recipient_directory',
        'student_transport_roster' => 'mmv_student_transport_roster',
        'academic_term_summary' => 'mmv_academic_term_summary',
        'student_attendance_analytics' => 'mmv_student_attendance_analytics',
        'student_attendance_summary' => 'mmv_student_attendance_summary',
        'staff_daily_register' => 'mmv_staff_daily_register',
        'student_term_performance' => 'mmv_student_term_performance',
        'student_learning_progress' => 'mmv_student_learning_progress',
        'staff_workload' => 'mmv_staff_workload',
        'student_transport_summary' => 'mmv_student_transport_summary',
        'student_health_summary' => 'mmv_student_health_summary',

        'academic_year_class_stream_learning_areas' => 'mmv_academic_year_class_stream_learning_areas',
        'term_subject_scores' => 'mmv_term_subject_scores',
        'student_fee_obligations' => 'mmv_student_fee_obligations',
        'assessment_results' => 'mmv_assessment_results',
        'strands' => 'mmv_strands',
        'dashboards' => 'mmv_dashboards',
        'sidebar_menu_items' => 'mmv_sidebar_menu_items',
        'admission_applications' => 'mmv_admission_applications',
        'inventory_transactions' => 'mmv_inventory_transactions',
        'sub_strands' => 'mmv_sub_strands',
        'academic_year_classes' => 'mmv_academic_year_classes',
        'mpesa_transactions' => 'mmv_mpesa_transactions',
        'academic_year_calendar_days' => 'mmv_academic_year_calendar_days',
        'school_financial_accounts' => 'mmv_school_financial_accounts',
        'workflow_instances' => 'mmv_workflow_instances',
        'classes' => 'mmv_classes',
        'activity_schedule' => 'mmv_activity_schedule',
        'academic_year_fee_schedules' => 'mmv_academic_year_fee_schedules',
        'attendance_sessions' => 'mmv_attendance_sessions',
        'payments' => 'mmv_payments',
        'learning_areas' => 'mmv_learning_areas',
        'academic_years' => 'mmv_academic_years',
        'streams' => 'mmv_streams',
        'departments' => 'mmv_departments',
        'staff_types' => 'mmv_staff_types',
        'inventory_items' => 'mmv_inventory_items',
        'inventory_categories' => 'mmv_inventory_categories',
        'lesson_plans' => 'mmv_lesson_plans',
        'schemes_of_work' => 'mmv_schemes_of_work',
        'assessment_tools' => 'mmv_assessment_tools',
        'assessment_rubrics' => 'mmv_assessment_rubrics',
        'grade_rules' => 'mmv_grade_rules',
        'grading_scales' => 'mmv_grading_scales',
        'timetable_entries' => 'mmv_timetable_entries',
        'time_slots' => 'mmv_time_slots',
        'rooms' => 'mmv_rooms',
        'dormitories' => 'mmv_dormitories',
        'vehicles' => 'mmv_vehicles',
        'transport_routes' => 'mmv_transport_routes',
        'uniform_catalog_products' => 'mmv_uniform_catalog_products',
        'uniform_sizes' => 'mmv_uniform_sizes',
        'fee_credit_notes' => 'mmv_fee_credit_notes',
        'payroll_runs' => 'mmv_payroll_runs',
        'payslips' => 'mmv_payslips',
        'staff_appointments' => 'mmv_staff_appointments',
        'staff_categories' => 'mmv_staff_categories',
        'staff_department_assignments' => 'mmv_staff_department_assignments',
        'staff_duty_roster' => 'mmv_staff_duty_roster',
        'student_attendance' => 'mmv_student_attendance',
        'student_health_records' => 'mmv_student_health_records',
        'student_health_visits' => 'mmv_student_health_visits',
        'student_parents' => 'mmv_student_parents',
        'student_permissions' => 'mmv_student_permissions',
        'student_transport_assignments' => 'mmv_student_transport_assignments',
        'student_transport_entitlements' => 'mmv_student_transport_entitlements',
        'student_transport_attendance' => 'mmv_student_transport_attendance',
        'student_vaccinations' => 'mmv_student_vaccinations',
        'student_award_categories' => 'mmv_student_award_categories',
        'student_award_types' => 'mmv_student_award_types',
        'student_clearances' => 'mmv_student_clearances',
        'student_fee_migration_snapshots' => 'mmv_student_fee_migration_snapshots',
        'student_fee_rollover_balances' => 'mmv_student_fee_rollover_balances',
        'student_scholarship_awards' => 'mmv_student_scholarship_awards',
        'student_transitions' => 'mmv_student_transitions',
        'communication_attachments' => 'mmv_communication_attachments',
        'communication_threads' => 'mmv_communication_threads',
        'announcements_bulletin' => 'mmv_announcements_bulletin',
        'bank_transactions' => 'mmv_bank_transactions',
        'budgets' => 'mmv_budgets',
        'budget_line_items' => 'mmv_budget_line_items',
        'expenses' => 'mmv_expenses',
        'fixed_assets' => 'mmv_fixed_assets',
        'food_consumption_records' => 'mmv_food_consumption_records',
        'meal_plans' => 'mmv_meal_plans',
        'purchase_orders' => 'mmv_purchase_orders',
        'requisition_items' => 'mmv_requisition_items',
        'requisitions' => 'mmv_requisitions',
        'suppliers' => 'mmv_suppliers',
        'library_books' => 'mmv_library_books',
        'library_categories' => 'mmv_library_categories',
        'library_issues' => 'mmv_library_issues',
        'portfolio_artifacts' => 'mmv_portfolio_artifacts',
        'promotion_batches' => 'mmv_promotion_batches',
        'cash_reconciliation_sessions' => 'mmv_cash_reconciliation_sessions',
        'chart_of_accounts' => 'mmv_chart_of_accounts',
        'financial_statement_lines' => 'mmv_financial_statement_lines',
        'school_financial_account_channels' => 'mmv_school_financial_account_channels',
        'payment_collection_routes' => 'mmv_payment_collection_routes',
        'payment_collection_route_channels' => 'mmv_payment_collection_route_channels',
        'payment_pos_terminals' => 'mmv_payment_pos_terminals',
        'payment_reconciliations' => 'mmv_payment_reconciliations',
        'payment_unmatched_cases' => 'mmv_payment_unmatched_cases',
        'transport_monthly_bills' => 'mmv_transport_monthly_bills',
        'transport_payment_intents' => 'mmv_transport_payment_intents',
        'transport_entitlement_payment_allocations' => 'mmv_transport_entitlement_payment_allocations',
        'transport_vehicle_routes' => 'mmv_transport_vehicle_routes',
        'uniform_catalog_images' => 'mmv_uniform_catalog_images',
        'uniform_payment_intents' => 'mmv_uniform_payment_intents',
        'uniform_payment_records' => 'mmv_uniform_payment_records',
        'uniform_sales' => 'mmv_uniform_sales',
        'routes_registry' => 'mmv_routes_registry',
        'workflow_stage_history' => 'mmv_workflow_stage_history',
        'workflow_stages' => 'mmv_workflow_stages',
        'workflow_definitions' => 'mmv_workflow_definitions',
        'admission_documents' => 'mmv_admission_documents',
        'admission_interview_assessment_items' => 'mmv_admission_interview_assessment_items',
        'admission_placement_tests' => 'mmv_admission_placement_tests',
        'admission_windows' => 'mmv_admission_windows',
        'analytics_report_metrics' => 'mmv_analytics_report_metrics',
        'analytics_report_runs' => 'mmv_analytics_report_runs',
        'assessment_learning_outcomes' => 'mmv_assessment_learning_outcomes',
        'assessment_policy_documents' => 'mmv_assessment_policy_documents',
        'assessment_policy_schedule_rules' => 'mmv_assessment_policy_schedule_rules',
        'assessment_rubric_criteria' => 'mmv_assessment_rubric_criteria',
        'core_competencies' => 'mmv_core_competencies',
        'core_values' => 'mmv_core_values',
        'counseling_sessions' => 'mmv_counseling_sessions',
        'learner_competencies' => 'mmv_learner_competencies',
        'learner_values_acquisition' => 'mmv_learner_values_acquisition',
        'learning_outcomes' => 'mmv_learning_outcomes',
        'leave_types' => 'mmv_leave_types',
        'lesson_plan_assessment_rubrics' => 'mmv_lesson_plan_assessment_rubrics',
        'lesson_plan_assessment_tools' => 'mmv_lesson_plan_assessment_tools',
        'lesson_plan_competencies' => 'mmv_lesson_plan_competencies',
        'lesson_plan_learner_evidence_questions' => 'mmv_lesson_plan_learner_evidence_questions',
        'lesson_plan_learner_evidence_resources' => 'mmv_lesson_plan_learner_evidence_resources',
        'lesson_plan_outcomes' => 'mmv_lesson_plan_outcomes',
        'lesson_plan_rubrics' => 'mmv_lesson_plan_rubrics',
        'sub_strand_competencies' => 'mmv_sub_strand_competencies',
        'strand_competency' => 'mmv_strand_competency',
        'activity_categories' => 'mmv_activity_categories',
        'activity_participants' => 'mmv_activity_participants',
        'activity_resources' => 'mmv_activity_resources',
        'chapel_program_sessions' => 'mmv_chapel_program_sessions',
        'chaplaincy_volunteers' => 'mmv_chaplaincy_volunteers',
        'duty_roster_drafts' => 'mmv_duty_roster_drafts',
        'equipment_maintenance' => 'mmv_equipment_maintenance',
        'exam_timetable_draft_entries' => 'mmv_exam_timetable_draft_entries',
        'exam_timetable_drafts' => 'mmv_exam_timetable_drafts',
        'extra_charge_classes' => 'mmv_extra_charge_classes',
        'extra_charges' => 'mmv_extra_charges',
        'national_assessment_windows' => 'mmv_national_assessment_windows',
        'parent_meeting_targets' => 'mmv_parent_meeting_targets',
        'past_papers' => 'mmv_past_papers',
        'performance_review_kpis' => 'mmv_performance_review_kpis',
        'staff_children' => 'mmv_staff_children',
        'staff_deductions' => 'mmv_staff_deductions',
        'staff_offboarding' => 'mmv_staff_offboarding',
        'staff_payroll_award_recipients' => 'mmv_staff_payroll_award_recipients',
        'staff_qualifications' => 'mmv_staff_qualifications',
        'staff_role_default_positions' => 'mmv_staff_role_default_positions',
        'staff_specialization_qualifications' => 'mmv_staff_specialization_qualifications',
        'scheme_workbook_items' => 'mmv_scheme_workbook_items',
        'scheme_workbook_weeks' => 'mmv_scheme_workbook_weeks',
        'scheme_templates' => 'mmv_scheme_templates',
        'scheme_workbooks' => 'mmv_scheme_workbooks',
        'timetable_drafts' => 'mmv_timetable_drafts',
        'academic_class_progression' => 'mmv_academic_class_progression',
        'academic_year_class_learning_area_teachers' => 'mmv_academic_year_class_learning_area_teachers',
        'academic_year_class_stream_learning_area_teachers' => 'mmv_academic_year_class_stream_learning_area_teachers',
        'academic_year_class_learning_areas' => 'mmv_academic_year_class_learning_areas',
        'academic_year_calendar' => 'mmv_academic_year_calendar',
        'blocked_devices' => 'mmv_blocked_devices',
        'catalog_order_items' => 'mmv_catalog_order_items',
        'catalog_orders' => 'mmv_catalog_orders',
        'catalog_reviews' => 'mmv_catalog_reviews',
        'catalog_stock_units' => 'mmv_catalog_stock_units',
        'catalog_wishlists' => 'mmv_catalog_wishlists',
        'inventory_locations' => 'mmv_inventory_locations',
        'job_vacancies' => 'mmv_job_vacancies',
        'leadership_positions' => 'mmv_leadership_positions',

        'academic_term_terms' => 'mmv_academic_term_terms',
        'academic_year_class_stream_learning_areas_detailed' => 'mmv_academic_year_class_stream_learning_areas_detailed',
        'academic_year_classes_streams' => 'mmv_academic_year_classes_streams',
        'assessment_results_detailed' => 'mmv_assessment_results_detailed',
        'leadership_positions_categories' => 'mmv_leadership_positions_categories',
        'strands_learning_areas' => 'mmv_strands_learning_areas',
        'strands_sub_strands' => 'mmv_strands_sub_strands',
        'student_academic_enrollments_fees' => 'mmv_student_academic_enrollments_fees',
        'student_academic_enrollments_streams' => 'mmv_student_academic_enrollments_streams',
        'student_fee_obligations_enrolled' => 'mmv_student_fee_obligations_enrolled',
        'academic_year_calendar_days_typed' => 'mmv_academic_year_calendar_days_typed',
        'inventory_transactions_items' => 'mmv_inventory_transactions_items',
        'admission_applications_interviews' => 'mmv_admission_applications_interviews',
        'learner_values_acquisition_values' => 'mmv_learner_values_acquisition_values',
        'portfolio_artifacts_portfolios' => 'mmv_portfolio_artifacts_portfolios',
        'attendance_sessions_config' => 'mmv_attendance_sessions_config',
        'mpesa_transactions_payments' => 'mmv_mpesa_transactions_payments',
        'persons' => 'mmv_persons',
        'student_academic_enrollments' => 'mmv_student_academic_enrollments',
        'academic_year_class_streams' => 'mmv_academic_year_class_streams',
        'students' => 'mmv_students',
        'staff' => 'mmv_staff',
        'academic_year_terms' => 'mmv_academic_year_terms',
        'assessments' => 'mmv_assessments',
        'parents' => 'mmv_parents',
        'exam_schedules' => 'mmv_exam_schedules',
        'learner_placement' => 'mmv_learner_placement',
        'exam_context' => 'mmv_exam_context',
        'scheme_lesson_context' => 'mmv_scheme_lesson_context',
        'learner_guardian' => 'mmv_learner_guardian',
        'staff_context' => 'mmv_staff_context',
        'attendance_register_context' => 'mmv_attendance_register_context',
        'fee_statement_context' => 'mmv_fee_statement_context',
        'admission_workflow_context' => 'mmv_admission_workflow_context',
        'transport_assignment_context' => 'mmv_transport_assignment_context',
        'boarding_dorm_context' => 'mmv_boarding_dorm_context',
        'lesson_plan_context' => 'mmv_lesson_plan_context',
        'class_stream_directory' => 'mmv_class_stream_directory',
    ];

    /** Fully-qualified source object (master schema resolved at runtime). */
    public static function sourceFor(string $projection): string
    {
        return ConnectionManager::schemaFor(ConnectionManager::NS_MASTER) . '.' . self::PROJECTIONS[$projection];
    }

    /**
     * Authoritative source object for access-control and row-scope decisions.
     * These decisions must observe current master data; a fresh materialized
     * copy can still lag a just-revoked role, assignment, or parent link.
     */
    public static function masterSourceRef(string $projection): string
    {
        if (!self::hasProjection($projection)) {
            throw new \DomainException("Unknown master read source '{$projection}'.", 404);
        }
        return self::sourceFor($projection);
    }

    private const MAX_QUERY_LIMIT = 500;
    private const MAX_OFFSET = 100000;

    /**
     * Memoized availability/freshness check for the materialized target.
     * Request reads fail closed when the configured reads schema is absent.
     */
    private static function replicaReachable(string $projection): bool
    {
        if (!isset(self::$refCache[$projection])) {
            try {
                self::qualifiedRef($projection); // populates $refCache via probe
            } catch (\Throwable) {
                return false;
            }
        }
        $resolved = self::$refCache[$projection];
        return strpos($resolved, '.') !== false;
    }

    private function __construct()
    {
        // Static service facade.
    }

    public static function projections(): array
    {
        return array_keys(self::PROJECTIONS);
    }

    public static function table(string $projection): string
    {
        if (isset(self::MATERIALIZED_TARGETS[$projection])) {
            return self::MATERIALIZED_TARGETS[$projection];
        }
        $safe = preg_replace('/[^a-z0-9_]/', '', $projection);
        return 'mv_' . $safe;
    }

    public static function hasProjection(string $projection): bool
    {
        return isset(self::PROJECTIONS[$projection]);
    }

    /** @return array{storage_mode:string,sensitivity:string,max_age_seconds:int} */
    public static function policy(string $projection): array
    {
        if (!self::hasProjection($projection)) {
            throw new \DomainException("Unknown read-replica projection '{$projection}'.", 404);
        }
        return self::POLICIES[$projection] ?? [
            'storage_mode' => 'unclassified',
            'sensitivity' => 'unknown',
            'max_age_seconds' => 0,
        ];
    }

    /**
     * True only when this projection is backed by an actual materialized
     * object. A view in the configured reads schema is deliberately not sufficient.
     */
    public static function isOffloaded(string $projection): bool
    {
        if (!self::hasProjection($projection)) {
            return false;
        }
        $policy = self::policy($projection);
        if ($policy['storage_mode'] !== 'materialized_table') {
            return false;
        }
        return self::replicaReachable($projection);
    }

    /**
     * Schema-qualified reference for a read that MUST stay on the master:
     * authorization, sessions, and write-path lookups. Authorization tables
     * (role grants, permissions, route access) are never registered as
     * projections, so a stale materialization can never decide access.
     *
     * Unlike qualifiedRef() this never touches the reads schema, never probes
     * freshness, and is unaffected by a master pin.
     *
     * @throws \InvalidArgumentException non-identifier table name
     */
    public static function masterRef(string $table): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid master table reference.', 400);
        }
        return ConnectionManager::schemaFor(ConnectionManager::NS_MASTER) . '.' . $table;
    }

    /**
     * Schema-qualified reference for a single-table request read against the
     * configured physical materialization. Do not join it to other objects in
     * request-path SQL; compose that shape in a source view and materialize it.
     *
     * Resolution is per-request and config-driven (DB_READS_NAME/DB_NAME), so
     * production database renames never break the reference. Missing, failed,
     * or stale materializations fail closed; request reads never evaluate a
     * source view on the master.
     *
     * Memoized per projection; first use checks target presence and freshness
     * once per request. The financial balance projection gets one bounded
     * Python refresh attempt before an ordinary read is rejected.
     *
     * @throws \DomainException unknown projection
     */
    public static function qualifiedRef(string $projection): string
    {
        if (!self::hasProjection($projection)) {
            throw new \DomainException("Unknown read-replica projection '{$projection}'.", 404);
        }
        // A sticky-master cookie applies to ordinary reads. An explicit
        // qualifiedRef remains an explicit read from the materialized reads
        // schema; do not reject it solely because a previous mutation pinned
        // the request. Strong-consistency decisions and read-after-write
        // checks must use masterRef()/masterSourceRef() at their call sites.
        if (!isset(self::$refCache[$projection])) {
            $replicaSchema = ConnectionManager::schemaFor(ConnectionManager::NS_READS);
            $view = self::table($projection);
            $policy = self::policy($projection);
            // Route only to a successfully refreshed materialized table. These
            // checks use fully qualified schema names on the existing PDO so
            // they remain safe inside a caller's transaction; switching the
            // connection namespace would be rejected while that transaction is
            // open.
            $exists = false;
            $fresh = false;
            try {
                if ($policy['storage_mode'] === 'materialized_table') {
                    $pdo = \App\Database\Database::getInstance()->getConnection();
                    $stmt = $pdo->prepare(
                        'SELECT COUNT(*) FROM information_schema.TABLES '
                        . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
                        . 'AND TABLE_TYPE = ?'
                    );
                    $stmt->execute([$replicaSchema, $view, 'BASE TABLE']);
                    $exists = (int) $stmt->fetchColumn() > 0;
                    if ($exists) {
                        $metaTable = '`' . str_replace('`', '', $replicaSchema) . '`.`reads_meta`';
                        $meta = $pdo->prepare(
                            "SELECT status, refreshed_at, TIMESTAMPDIFF(SECOND, refreshed_at, NOW()) AS age_seconds "
                            . "FROM {$metaTable} WHERE projection = ? LIMIT 1"
                        );
                        $meta->execute([$projection]);
                        $row = $meta->fetch(PDO::FETCH_ASSOC) ?: [];
                        $age = isset($row['age_seconds']) ? (int) $row['age_seconds'] : -1;
                        $fresh = ($row['status'] ?? null) === 'live'
                            && !empty($row['refreshed_at'])
                            && $age >= 0
                            && $age <= (int) $policy['max_age_seconds'];
                    }
                }
            } catch (\Throwable $e) {
                $exists = false;
                $fresh = false;
            }
            // Any materialized projection can drift stale when its scheduled
            // refresh is missed (worker down, queue jam, cron gap). A stale
            // snapshot must not fail every consuming page when the refresh
            // engine can repair it safely: attempt one bounded refresh per
            // projection per request before rejecting an ordinary read.
            // Never wait on the refresh engine while this request holds a
            // database transaction: transactional callers (fee posting,
            // payment processing, payroll) stay fail-closed rather than
            // using stale financial data or holding locks across an
            // inter-service call.
            if (!$fresh
                && $policy['storage_mode'] === 'materialized_table'
                && isset($pdo)
                && !$pdo->inTransaction()
                && !isset(self::$repairAttempted[$projection])) {
                self::$repairAttempted[$projection] = true;
                $refreshStarted = microtime(true);
                try {
                    $bridge = new \App\API\Services\ReadProjectionBridge();
                    $refresh = $bridge->refreshForRead($projection);

                    $tableCheck = $pdo->prepare(
                        'SELECT COUNT(*) FROM information_schema.TABLES '
                        . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND TABLE_TYPE = ?'
                    );
                    $tableCheck->execute([$replicaSchema, $view, 'BASE TABLE']);
                    $exists = (int) $tableCheck->fetchColumn() > 0;
                    if ($exists) {
                        $metaTable = '`' . str_replace('`', '', $replicaSchema) . '`.`reads_meta`';
                        $meta = $pdo->prepare(
                            "SELECT status, refreshed_at, TIMESTAMPDIFF(SECOND, refreshed_at, NOW()) AS age_seconds "
                            . "FROM {$metaTable} WHERE projection = ? LIMIT 1"
                        );
                        $meta->execute([$projection]);
                        $row = $meta->fetch(PDO::FETCH_ASSOC) ?: [];
                        $age = isset($row['age_seconds']) ? (int) $row['age_seconds'] : -1;
                        $fresh = ($row['status'] ?? null) === 'live'
                            && !empty($row['refreshed_at'])
                            && $age >= 0
                            && $age <= (int) $policy['max_age_seconds'];
                    }
                    \App\API\Includes\FileLogger::write('reads', [
                        'event' => $fresh ? 'projection_auto_refreshed_for_read' : 'projection_auto_refresh_not_fresh',
                        'projection' => $projection,
                        'engine' => $refresh['engine'] ?? 'python',
                        'duration_ms' => (int) round((microtime(true) - $refreshStarted) * 1000),
                        'request_id' => (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_KINGSWAY_REQUEST_ID'] ?? ''),
                    ], $fresh ? 'info' : 'warning');
                } catch (\Throwable $e) {
                    \App\API\Includes\FileLogger::write('reads', [
                        'event' => 'projection_auto_refresh_failed',
                        'projection' => $projection,
                        'error_class' => get_class($e),
                        'duration_ms' => (int) round((microtime(true) - $refreshStarted) * 1000),
                        'request_id' => (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_KINGSWAY_REQUEST_ID'] ?? ''),
                    ], 'error');
                }
            }
            if (!$fresh) {
                self::$refState[$projection] = $exists ? 'stale' : 'unavailable';
                if ($policy['storage_mode'] === 'materialized_table') {
                    \App\API\Includes\FileLogger::write('reads', [
                        'event' => 'projection_read_rejected',
                        'projection' => $projection,
                        'reason' => self::$refState[$projection],
                        'request_id' => (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_KINGSWAY_REQUEST_ID'] ?? ''),
                    ], 'error');
                    throw new \RuntimeException("Materialized read '{$projection}' is unavailable or stale.");
                }
            }
            self::$refCache[$projection] = '`' . str_replace('`', '', $replicaSchema) . '`.`' . $view . '`';
            self::$refState[$projection] = $fresh
                ? 'materialized_fresh'
                : 'not_materialized';
        }
        return self::$refCache[$projection];
    }

    /** @var array<string,string> Per-request memo of resolved replica references. */
    private static $refCache = [];

    /** @var array<string,string> Per-request resolution state for freshness reporting. */
    private static $refState = [];

    /** @var array<string,bool> Per-request memo of projections whose on-demand repair was already attempted (succeeded or failed). */
    private static $repairAttempted = [];

    /**
     * Verify a reachable materialized projection using row-count parity.
     */
    public static function liveFresh(string $projection): bool
    {
        if (!self::hasProjection($projection)) {
            return false;
        }
        // A source view is never a healthy request-path target.
        if (!self::replicaReachable($projection)) {
            return false;
        }
        return (bool) ConnectionManager::run(static function (PDO $pdo) use ($projection): bool {
            $source = self::sourceFor($projection);
            $replica = (int) $pdo->query('SELECT COUNT(*) FROM `' . self::table($projection) . '`')->fetchColumn();
            $master = (int) $pdo->query('SELECT COUNT(*) FROM ' . $source)->fetchColumn();
            return $replica === $master;
        }, ConnectionManager::NS_READS);
    }

    /**
     * Freshness/watermark status for every projection. Source views are
     * metadata only; request reads require a fresh physical table.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function freshness(): array
    {
        $result = [];
        foreach (array_keys(self::PROJECTIONS) as $projection) {
            $policy = self::policy($projection);
            // `isOffloaded()` deliberately returns false for a stale snapshot.
            // Keep the operational report useful in that state: read the
            // available table and metadata below so operators can see its age,
            // parity, and last refresh error instead of receiving only the
            // unhelpful word "stale".
            if (!self::isOffloaded($projection) && (self::$refState[$projection] ?? null) !== 'stale') {
                $result[] = [
                    'projection' => $projection,
                    'source_view' => self::sourceFor($projection),
                    'rows_count' => null,
                    'master_rows' => null,
                    'realtime' => false,
                    'as_of' => null,
                    'status' => self::$refState[$projection] ?? 'unavailable',
                    'storage_mode' => $policy['storage_mode'],
                    'sensitivity' => $policy['sensitivity'],
                    'max_age_seconds' => $policy['max_age_seconds'],
                ];
                continue;
            }
            $result[] = ConnectionManager::run(static function (PDO $pdo) use ($projection, $policy): array {
                $source = self::sourceFor($projection);
                $replica = (int) $pdo->query('SELECT COUNT(*) FROM `' . self::table($projection) . '`')->fetchColumn();
                $master = (int) $pdo->query('SELECT COUNT(*) FROM ' . $source)->fetchColumn();
                $stored = [];
                try {
                    $meta = $pdo->prepare(
                        'SELECT rows_count, source_watermark, as_of, refreshed_at, status, storage_mode, sensitivity, max_age_seconds, last_error, '
                        . 'TIMESTAMPDIFF(SECOND, refreshed_at, NOW()) AS age_seconds '
                        . 'FROM `reads_meta` WHERE projection = ? LIMIT 1'
                    );
                    $meta->execute([$projection]);
                    $stored = $meta->fetch(PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $ignored) {
                    // Older installations may not have reads_meta yet.
                }
                $storageMode = (string) ($stored['storage_mode'] ?? $policy['storage_mode']);
                $refreshedAt = $stored['refreshed_at'] ?? null;
                $ageSeconds = isset($stored['age_seconds']) ? (int) $stored['age_seconds'] : null;
                $withinFreshness = ($stored['status'] ?? null) === 'live'
                    && $ageSeconds !== null
                    && $ageSeconds >= 0
                    && $ageSeconds <= $policy['max_age_seconds'];
                return [
                    'projection' => $projection,
                    'source_view' => $source,
                    'rows_count' => $replica,
                    'master_rows' => $master,
                    'realtime' => false,
                    'as_of' => $pdo->query('SELECT NOW()')->fetchColumn(),
                    'status' => $withinFreshness && $replica === $master ? 'materialized_parity' : 'materialized_unhealthy',
                    'storage_mode' => $storageMode,
                    'sensitivity' => $stored['sensitivity'] ?? $policy['sensitivity'],
                    'max_age_seconds' => $policy['max_age_seconds'],
                    'source_watermark' => $stored['source_watermark'] ?? null,
                    'refreshed_at' => $refreshedAt,
                    'age_seconds' => $ageSeconds,
                    'within_freshness' => $withinFreshness,
                    'last_error' => $stored['last_error'] ?? null,
                ];
            }, ConnectionManager::NS_READS);
        }
        return $result;
    }

    /**
     * Field-projected read against a fresh materialized projection. An absent,
     * stale, or unavailable projection is an explicit failure; request code
     * never falls back to a source view on the master. Only $fields columns are fetched; both $fields and
     * $filters keys are whitelisted against the live column list of the replica
     * view so a caller can never read or filter on an invented column.
     *
     * @param array<int,string>            $fields
     * @param array<string,mixed>          $filters
     * @param array<int,string>|string     $orderBy  e.g. ['student_id','DESC']
     *
     * @throws \DomainException unknown projection/field/filter
     */
    public static function query(string $projection, array $fields, array $filters = [], int $limit = 200, int $offset = 0, array $orderBy = []): array
    {
        if (!self::hasProjection($projection)) {
            throw new \DomainException("Unknown read-replica projection '{$projection}'.", 404);
        }
        if ($limit < 1) {
            throw new \DomainException('Replica query limit must be >= 1.', 422);
        }
        $limit = min($limit, self::MAX_QUERY_LIMIT);
        $offset = max(0, min($offset, self::MAX_OFFSET));

        self::qualifiedRef($projection); // enforce freshness and fail closed
        if (!self::isOffloaded($projection)) {
            throw new \RuntimeException("Projection '{$projection}' is not physically materialized.");
        }
        $namespace = ConnectionManager::NS_READS;

        return ConnectionManager::run(static function (PDO $pdo) use ($projection, $fields, $filters, $limit, $offset, $orderBy, $namespace): array {
            $table = self::table($projection);
            $cols = self::tableColumns($pdo, $table, $namespace);
            if ($fields === []) {
                $fields = $cols;
            }
            $projected = [];
            foreach ($fields as $field) {
                if (!in_array($field, $cols, true)) {
                    throw new \DomainException("Unknown replica column '{$field}' on {$projection}.", 422);
                }
                $projected[] = '`' . str_replace('`', '', $field) . '`';
            }

            $where = [];
            $params = [];
            foreach ($filters as $key => $value) {
                if (!in_array($key, $cols, true)) {
                    throw new \DomainException("Unknown replica filter column '{$key}' on {$projection}.", 422);
                }
                $col = '`' . str_replace('`', '', $key) . '`';
                if (is_array($value)) {
                    $placeholders = [];
                    foreach ($value as $i => $v) {
                        $ph = ':f_' . $key . '_' . $i;
                        $placeholders[] = $ph;
                        $params[$ph] = $v;
                    }
                    $where[] = "{$col} IN (" . implode(',', $placeholders) . ')';
                } else {
                    $ph = ':f_' . $key;
                    $where[] = "{$col} = {$ph}";
                    $params[$ph] = $value;
                }
            }

            $order = '';
            if ($orderBy !== []) {
                // Callers pass an associative array (['level_name' => 'ASC']).
                // Reading it positionally made $orderCol the DIRECTION ('ASC'),
                // failed the column check, and silently degraded the whole
                // result to [] — the "No records found" bug.
                $orderCol = (string) array_key_first($orderBy);
                $dir = strtoupper((string) ($orderBy[$orderCol] ?? 'ASC'));
                if (!in_array($orderCol, $cols, true)) {
                    throw new \DomainException("Unknown replica order column '{$orderCol}'.", 422);
                }
                $order = ' ORDER BY `' . str_replace('`', '', $orderCol) . '` ' . ($dir === 'DESC' ? 'DESC' : 'ASC');
            }

            $sql = 'SELECT ' . implode(',', $projected) . " FROM `{$table}`";
            if ($where !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= $order . " LIMIT {$limit} OFFSET {$offset}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, $namespace);
    }

    /**
     * Column allowlist for a projection table or master source view, read from
     * whichever schema is serving the query.
     *
     * @return array<int,string>
     */
    private static function tableColumns(PDO $pdo, string $table, string $namespace): array
    {
        $rows = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=" . $pdo->quote(ConnectionManager::schemaFor($namespace)) . " AND TABLE_NAME=" . $pdo->quote($table) . " ORDER BY ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($rows === []) {
            throw new \DomainException("Replica view '{$table}' has no columns.", 503);
        }
        return array_values(array_filter($rows));
    }
}
