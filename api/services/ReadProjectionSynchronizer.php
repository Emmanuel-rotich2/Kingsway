<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Database\ConnectionManager;
use App\Database\Database;
use App\API\Includes\FileLogger;
use PDO;
use RuntimeException;

/**
 * Incrementally deployable materialized read projection synchronizer.
 *
 * The first projection is an aggregate dashboard trend. It is rebuilt into a
 * staging table and atomically renamed into place, so readers see either the
 * previous complete snapshot or the next complete snapshot—never a half-filled
 * table. The source remains KingsWayAcademy and the target is KingsWayReads.
 */
final class ReadProjectionSynchronizer
{
    private const STAGE_PREFIX = '__stage_';

    /**
     * Freshness budgets at or below this threshold are served by the
     * per-projection sync-projection crontab lines, which refresh inline in
     * the request. Those projections are the expensive aggregate views on the
     * shortest leash, so the queued batch path deliberately skips them: it
     * would duplicate the inline work and push every cheaper projection on
     * the shared FIFO path past its own budget.
     *
     * Every projection with a budget this tight MUST therefore have its own
     * crontab line (see deploy/kingsway.crontab.example).
     */
    private const INLINE_REFRESH_BUDGET_SECONDS = 300;

    /**
     * Indexes required by the read APIs after each atomic rebuild. MySQL's
     * CREATE TABLE ... AS SELECT does not copy indexes, so without this
     * allowlist every refresh silently turns the read model back into a full
     * scan. Keep names and columns code-owned; never accept them from a job.
     *
     * @var array<string,array<string,array<int,string>>>
     */
    private const READ_INDEXES = [        'dashboards' => ['idx_pk' => ['dashboards']],

        'activity_schedule_directory' => [
            'idx_schedule_id' => ['id'],
            'idx_activity_id' => ['activity_id'],
            'idx_day_start' => ['day_of_week', 'start_time'],
            'idx_status_venue_day_start' => ['activity_status', 'venue', 'day_of_week', 'start_time'],
            'idx_category_status' => ['category_id', 'activity_status'],
            'idx_end_start_dates' => ['activity_end_date', 'activity_start_date'],
        ],
        'academic_class_directory' => [
            'idx_class_year_id' => ['id'],
            'idx_current_class' => ['is_current_year', 'class_id'],
            'idx_current_name' => ['is_current_year', 'class_name'],
            'idx_year_class' => ['academic_year_id', 'class_id'],
        ],
        'activity_category_summary' => [
            'idx_category' => ['id'],
            'idx_active_name' => ['is_active', 'name'],
            'idx_active_activity_count' => ['is_active', 'activity_count'],
        ],
        'collection_rate_by_class' => [
            'idx_level_term' => ['level_code', 'academic_term'],
        ],
        'class_learning_area_performance' => [
            'idx_year_class_term' => ['academic_year_class_id', 'term_number'],
            'idx_academic_year' => ['academic_year'],
            'idx_term_number' => ['term_number'],
            'idx_class_name' => ['class_name'],
            'idx_stream_name' => ['stream_name'],
            'idx_learning_area' => ['learning_area'],
        ],
        'student_growth_trend' => [
            'idx_student' => ['student_id'],
            'idx_student_area' => ['student_id', 'learning_area_id'],
            'idx_year_term' => ['year', 'term_id'],
        ],
        'student_timeline_subject_scores' => [
            'idx_student' => ['student_id'],
            'idx_student_year_term' => ['student_id', 'academic_year_id', 'term_id'],
        ],
        'deputy_class_formative_performance' => [
            'idx_term_year' => ['academic_year_term_id', 'academic_year_id'],
            'idx_class' => ['class_id'],
        ],
        'budget_utilization' => [
            'idx_budget' => ['budget_id'],
            'idx_year_term' => ['academic_year', 'term'],
        ],
        'dormitory_occupancy' => [
            'idx_dormitory_year' => ['dormitory_id', 'academic_year'],
            'idx_academic_year' => ['academic_year'],
            'idx_gender' => ['gender'],
        ],
        'fee_collection_monthly_trend' => [
            'idx_month' => ['month'],
        ],
        'fee_status_summary' => [
            'idx_year_term_class_stream' => ['academic_year', 'term_number', 'class_id', 'stream_id'],
            'idx_class_stream' => ['class_id', 'stream_id'],
            'idx_academic_year' => ['academic_year'],
            'idx_term_number' => ['term_number'],
            'idx_student_period' => ['student_id', 'academic_year', 'term_number'],
            'idx_admission_no' => ['admission_no'],
            'idx_payment_status' => ['payment_status'],
            'idx_current_balance' => ['current_balance'],
            'idx_student_type' => ['student_type_id'],
            'idx_level' => ['level_id'],
        ],
        'expense_directory' => [
            'idx_expense_status_date' => ['status', 'expense_date', 'id'],
            'idx_expense_department_status_date' => ['department_id', 'status', 'expense_date'],
            'idx_expense_category_date' => ['category_id', 'expense_date'],
            'idx_expense_year_term_status' => ['academic_year', 'term', 'status'],
            'idx_expense_vendor' => ['vendor_id'],
            'idx_expense_creator' => ['created_by'],
            'idx_expense_approver' => ['approved_by'],
            'idx_expense_budget_line_item' => ['budget_line_item_id'],
            'idx_expense_id' => ['id'],
        ],
        'staff_meeting_summary' => [
            'idx_meeting_id' => ['id'],
            'idx_meeting_date_start' => ['meeting_date', 'start_time'],
            'idx_department_status_date_start' => ['department_id', 'status', 'meeting_date', 'start_time'],
            'idx_meeting_type_status_date_start' => ['meeting_type', 'status', 'meeting_date', 'start_time'],
        ],
        'staff_meeting_member' => [
            'idx_meeting_staff' => ['meeting_id', 'staff_id'],
            'idx_staff_meeting' => ['staff_id', 'meeting_id'],
            'idx_meeting_date_start' => ['meeting_date', 'start_time'],
        ],
        'parent_student_dashboard' => [
            'idx_parent_scope' => ['parent_id', 'student_data_scope'],
            'idx_parent_student' => ['parent_id', 'student_id'],
            'idx_parent_user' => ['parent_user_id'],
            'idx_student_year' => ['student_id', 'academic_year_id'],
            'idx_enrollment_year' => ['student_academic_enrollment_id', 'academic_year_id'],
        ],
        'curriculum_taxonomy' => [
            'idx_curriculum_strand' => ['strand_id'],
            'idx_curriculum_grade_status' => ['grade_level', 'status'],
            'idx_learning_area_grade_status' => ['learning_area_id', 'grade_level', 'status'],
            'idx_learning_area_family_grade' => ['learning_area_family_id', 'grade_level'],
            'idx_sub_strand_code' => ['code'],
        ],
        'payment_directory' => [
            'idx_payment_status_date' => ['status', 'payment_date', 'id'],
            'idx_payment_student_date' => ['student_id', 'payment_date', 'id'],
            'idx_payment_method_status_date' => ['method', 'status', 'payment_date'],
            'idx_payment_reference' => ['reference'],
            'idx_payment_receipt' => ['receipt_no'],
            'idx_payment_year_term_date' => ['academic_year', 'academic_year_term_id', 'payment_date'],
            'idx_payment_receiver_date' => ['received_by', 'payment_date'],
        ],
        'internal_conversation_member' => [
            'uq_conversation_member' => ['conversation_id', 'participant_id'],
            'idx_member_activity' => ['participant_id', 'activity_at'],
            'idx_member_mute_activity' => ['participant_id', 'is_muted', 'activity_at'],
        ],
        'internal_message_recipient' => [
            'uq_message_recipient' => ['message_id', 'recipient_id'],
            'idx_recipient_conversation_created' => ['recipient_id', 'conversation_id', 'created_at', 'message_id'],
        ],
        'internal_message_recipient_directory' => [
            'uq_directory_user' => ['user_id'],
            'idx_directory_status_user' => ['user_status', 'user_id'],
        ],
        'student_transport_roster' => [
            'idx_transport_assignment' => ['assignment_id'],
            'idx_transport_student_status' => ['student_id', 'assignment_status'],
            'idx_transport_route_status' => ['route_id', 'assignment_status'],
            'idx_transport_vehicle' => ['vehicle_id'],
            'idx_transport_class_stream' => ['class_id', 'stream_id'],
            'idx_transport_driver' => ['driver_id'],
        ],
        'academic_term_summary' => [
            'idx_term_year_date' => ['academic_year_id', 'opening_date'],
            'idx_term_year_status' => ['academic_year_id', 'term_period_status'],
            'idx_term_id' => ['term_id'],
        ],
        'student_dashboard_summary' => [
            'idx_student' => ['student_id'],
            'idx_class_stream' => ['class_id', 'stream_id'],
            'idx_academic_year' => ['academic_year_id'],
        ],
        'portfolio_hub_summary' => [
            'idx_student_year' => ['student_id', 'academic_year_id'],
            'idx_class_stream' => ['class_id', 'stream_id'],
            'idx_year_week' => ['academic_year_id', 'week_number'],
        ],
        'portfolio_hub_class' => [
            'idx_class_term_student' => ['class_id', 'filter_term_id', 'student_id'],
            'idx_portfolio' => ['portfolio_id'],
            'idx_term_class' => ['filter_term_id', 'class_id'],
        ],
        // Phase 1 directory stars. Indexes follow the measured read filters:
        // identity lookup, class/stream placement, academic period and the
        // row-level scope keys that replaced the role/permission joins.
        'person_directory' => [
            'idx_person' => ['person_id'],
            'idx_user' => ['user_id'],
            'idx_student' => ['student_id'],
            'idx_staff' => ['staff_id'],
            'idx_parent' => ['parent_id'],
            'idx_phone' => ['phone'],
            'idx_admission_no' => ['admission_no'],
        ],
        'academic_calendar' => [
            'idx_year' => ['academic_year_id'],
            'idx_class' => ['class_id'],
            'idx_stream' => ['stream_id'],
            'idx_class_stream' => ['class_id', 'stream_id'],
            'idx_term' => ['term_id'],
            'idx_class_teacher' => ['class_teacher_id'],
            'idx_year_class' => ['academic_year_id', 'class_id'],
        ],
        'student_directory' => [
            'idx_student' => ['student_id'],
            'idx_person' => ['person_id'],
            'idx_class_stream' => ['class_id', 'stream_id'],
            'idx_year_class' => ['academic_year_id', 'class_id'],
            'idx_admission_no' => ['admission_no'],
            'idx_status' => ['enrollment_status'],
            'idx_student_year' => ['student_id', 'academic_year_id'],
            'idx_type' => ['student_type_id'],
            'idx_scope_student' => ['scope_student_id'],
            'idx_scope_stream' => ['scope_class_stream_id'],
        ],
        'student_fee_balances' => [
            'idx_student' => ['student_id'],
            'idx_year' => ['academic_year_id'],
            'idx_year_term' => ['academic_year_term_id'],
            'idx_term' => ['term_id'],
            'idx_status' => ['payment_status'],
            'idx_enrollment' => ['student_academic_enrollment_id'],
            'idx_student_year' => ['student_id', 'academic_year_id'],
        ],
        'student_fee_ledger' => [
            'idx_student' => ['student_id'],
            'idx_enrollment' => ['student_academic_enrollment_id'],
            'idx_term' => ['term_id'],
            'idx_student_term' => ['student_id', 'term_id'],
            'idx_status' => ['payment_status'],
        ],
        'student_attendance_analytics' => [
            'idx_student' => ['student_id'],
            'idx_year_term' => ['academic_year', 'term_number'],
            'idx_student_year' => ['student_id', 'academic_year'],
            'idx_class' => ['class_name'],
        ],
        'student_attendance_enrollment' => [
            'idx_enrollment' => ['student_academic_enrollment_id'],
            'idx_student' => ['student_id'],
            'idx_year' => ['academic_year_id'],
            'idx_date' => ['attendance_date'],
            'idx_status' => ['status'],
        ],
        'student_attendance_summary' => [
            'idx_student' => ['student_id'],
            'idx_date' => ['date'],
            'idx_date_session' => ['date', 'session_name'],
            'idx_status' => ['status'],
        ],
        'staff_daily_register' => [
            'idx_date' => ['date'],
            'idx_staff' => ['staff_id'],
            'idx_date_staff' => ['date', 'staff_id'],
            'idx_marked_status' => ['marked_status'],
            'idx_department' => ['department_id'],
        ],
        'student_term_performance' => [
            'idx_student' => ['student_id'],
            'idx_student_year' => ['student_id', 'academic_year'],
            'idx_year' => ['academic_year'],
            'idx_term' => ['term_number'],
        ],
        'student_learning_progress' => [
            'idx_student' => ['student_id'],
            'idx_student_year' => ['student_id', 'academic_year'],
            'idx_learning_area' => ['learning_area'],
            'idx_year_term' => ['academic_year', 'term_number'],
        ],
        'staff_workload' => [
            'idx_staff' => ['staff_id'],
            'idx_staff_no' => ['staff_no'],
            'idx_category' => ['category_name'],
        ],
        'student_transport_summary' => [
            'idx_student' => ['student_id'],
            'idx_billing_month' => ['billing_month'],
            'idx_status' => ['payment_status'],
        ],
        'student_health_summary' => [
            'idx_student' => ['student_id'],
            'idx_admission_no' => ['admission_no'],
        ],
        'academic_term' => [
            'idx_ayt' => ['academic_year_term_id'],
            'idx_term' => ['term_id'],
            'idx_year_term' => ['academic_year_id', 'term_id'],
            'idx_year_code' => ['year_code'],
        ],
        'staff_directory' => [
            'idx_staff' => ['staff_id'],
            'idx_person' => ['person_id'],
            'idx_user' => ['user_id'],
            'idx_staff_no' => ['staff_no'],
            'idx_status' => ['staff_status'],
            'idx_type_category' => ['staff_type_id', 'staff_category_id'],
        ],
        // Phase 2 bridges.
        'student_term_placement' => [
            'idx_student' => ['student_id'],
            'idx_enrollment' => ['enrollment_id'],
            'idx_person' => ['person_id'],
            'idx_ayt' => ['academic_year_term_id'],
            'idx_term' => ['term_id'],
            'idx_year_term' => ['academic_year_id', 'academic_year_term_id'],
            'idx_student_term' => ['student_id', 'academic_year_term_id'],
            'idx_year_class' => ['academic_year_id', 'class_id'],
            'idx_admission_no' => ['admission_no'],
            'idx_scope_student' => ['scope_student_id'],
            'idx_scope_stream' => ['scope_class_stream_id'],
        ],
        'guardian_link' => [
            'idx_student' => ['student_id'],
            'idx_parent' => ['parent_id'],
            'idx_pair' => ['student_id', 'parent_id'],
            'idx_parent_user' => ['parent_user_id'],
            'idx_primary' => ['parent_user_id', 'is_primary_contact'],
            'idx_admission_no' => ['admission_no'],
            'idx_scope_student' => ['scope_student_id'],
            'idx_scope_parent' => ['scope_parent_id'],
        ],
        'calendar_day_type' => [
            'idx_calendar_day' => ['calendar_day_id'],
            'idx_calendar' => ['academic_year_calendar_id'],
            'idx_date' => ['calendar_date'],
            'idx_day_type' => ['calendar_day_type_id'],
        ],
        'timetable_conflict' => [
            'idx_type_term' => ['conflict_type', 'academic_year_term_id'],
            'idx_type_stream_term' => ['conflict_type', 'academic_year_class_stream_id', 'academic_year_term_id'],
            'idx_schedule_pair' => ['schedule_id_1', 'schedule_id_2'],
        ],
        'dashboard_catalog' => [
            'idx_role' => ['role_id'],
            'idx_dashboard' => ['dashboard_id'],
            'idx_route_name' => ['route_name'],
        ],
        'learner_competency' => [
            'idx_student' => ['student_id'],
            'idx_competency' => ['competency_id'],
            'idx_student_term' => ['student_id', 'term_id'],
        ],
        'uniform_catalog' => [
            'idx_item' => ['item_id'],
            'idx_product' => ['product_id'],
            'idx_size' => ['size_id'],
        ],
        'payslip' => [
            'idx_staff' => ['staff_id'],
            'idx_period' => ['payroll_month', 'payroll_year'],
        ],
        'staff_profile_assignment' => [
            'idx_staff' => ['staff_id'],
            'idx_person' => ['staff_person_id'],
            'idx_department' => ['department_id'],
        ],
        'admission_application_workflow' => [
            'idx_application' => ['application_id'],
            'idx_workflow' => ['workflow_instance_id'],
            'idx_status' => ['application_status', 'workflow_status'],
        ],
        'competency_catalog' => [
            'idx_competency' => ['competency_id'],
        ],
        'enrollment_membership' => [
            'idx_student' => ['student_id'],
            'idx_aycs' => ['academic_year_class_stream_id'],
            'idx_enrollment' => ['enrollment_id'],
        ],

        'academic_year_class_stream_learning_areas' => ['idx_pk' => ['id']],
        'term_subject_scores' => ['idx_pk' => ['id']],
        'student_fee_obligations' => ['idx_pk' => ['id']],
        'assessment_results' => ['idx_pk' => ['id']],
        'strands' => ['idx_pk' => ['id']],
        'dashboards' => ['idx_pk' => ['id']],
        'sidebar_menu_items' => ['idx_pk' => ['id']],
        'admission_applications' => ['idx_pk' => ['id']],
        'inventory_transactions' => ['idx_pk' => ['id']],
        'sub_strands' => ['idx_pk' => ['id']],
        'academic_year_classes' => ['idx_pk' => ['id']],
        'mpesa_transactions' => ['idx_pk' => ['id']],
        'academic_year_calendar_days' => ['idx_pk' => ['id']],
        'school_financial_accounts' => ['idx_pk' => ['id']],
        'workflow_instances' => ['idx_pk' => ['id']],
        'classes' => ['idx_pk' => ['id']],
        'activity_schedule' => ['idx_pk' => ['id']],
        'academic_year_fee_schedules' => ['idx_pk' => ['id']],
        'attendance_sessions' => ['idx_pk' => ['id']],
        'payments' => ['idx_pk' => ['id']],
        'learning_areas' => ['idx_pk' => ['id']],
        'academic_years' => ['idx_pk' => ['id']],
        'streams' => ['idx_pk' => ['id']],
        'departments' => ['idx_pk' => ['id']],
        'staff_types' => ['idx_pk' => ['id']],
        'inventory_items' => ['idx_pk' => ['id']],
        'inventory_categories' => ['idx_pk' => ['id']],
        'lesson_plans' => ['idx_pk' => ['id']],
        'schemes_of_work' => ['idx_pk' => ['id']],
        'assessment_tools' => ['idx_pk' => ['id']],
        'assessment_rubrics' => ['idx_pk' => ['id']],
        'grade_rules' => ['idx_pk' => ['id']],
        'grading_scales' => ['idx_pk' => ['id']],
        'timetable_entries' => ['idx_pk' => ['id']],
        'time_slots' => ['idx_pk' => ['id']],
        'rooms' => ['idx_pk' => ['id']],
        'dormitories' => ['idx_pk' => ['id']],
        'vehicles' => ['idx_pk' => ['id']],
        'transport_routes' => ['idx_pk' => ['id']],
        'uniform_catalog_products' => ['idx_pk' => ['id']],
        'uniform_sizes' => ['idx_pk' => ['id']],
        'fee_credit_notes' => ['idx_pk' => ['id']],
        'payroll_runs' => ['idx_pk' => ['id']],
        'payslips' => ['idx_pk' => ['id']],
        'staff_appointments' => ['idx_pk' => ['id']],
        'staff_categories' => ['idx_pk' => ['id']],
        'staff_department_assignments' => ['idx_pk' => ['id']],
        'staff_duty_roster' => ['idx_pk' => ['id']],
        'student_attendance' => ['idx_pk' => ['id']],
        'student_health_records' => ['idx_pk' => ['id']],
        'student_health_visits' => ['idx_pk' => ['id']],
        'student_parents' => ['idx_pk' => ['student_id', 'parent_id']],
        'student_permissions' => ['idx_pk' => ['id']],
        'student_transport_assignments' => ['idx_pk' => ['id']],
        'student_transport_entitlements' => ['idx_pk' => ['id']],
        'student_transport_attendance' => ['idx_pk' => ['id']],
        'student_vaccinations' => ['idx_pk' => ['id']],
        'student_award_categories' => ['idx_pk' => ['id']],
        'student_award_types' => ['idx_pk' => ['id']],
        'student_clearances' => ['idx_pk' => ['id']],
        'student_fee_migration_snapshots' => ['idx_pk' => ['id']],
        'student_fee_rollover_balances' => ['idx_pk' => ['id']],
        'student_scholarship_awards' => ['idx_pk' => ['id']],
        'student_transitions' => ['idx_pk' => ['id']],
        'communication_attachments' => ['idx_pk' => ['id']],
        'communication_threads' => ['idx_pk' => ['id']],
        'announcements_bulletin' => ['idx_pk' => ['id']],
        'bank_transactions' => ['idx_pk' => ['id']],
        'budgets' => ['idx_pk' => ['id']],
        'budget_line_items' => ['idx_pk' => ['id']],
        'expenses' => ['idx_pk' => ['id']],
        'fixed_assets' => ['idx_pk' => ['id']],
        'food_consumption_records' => ['idx_pk' => ['id']],
        'meal_plans' => ['idx_pk' => ['id']],
        'purchase_orders' => ['idx_pk' => ['id']],
        'requisition_items' => ['idx_pk' => ['id']],
        'requisitions' => ['idx_pk' => ['id']],
        'suppliers' => ['idx_pk' => ['id']],
        'library_books' => ['idx_pk' => ['id']],
        'library_categories' => ['idx_pk' => ['id']],
        'library_issues' => ['idx_pk' => ['id']],
        'portfolio_artifacts' => ['idx_pk' => ['id']],
        'promotion_batches' => ['idx_pk' => ['id']],
        'cash_reconciliation_sessions' => ['idx_pk' => ['id']],
        'chart_of_accounts' => ['idx_pk' => ['id']],
        'financial_statement_lines' => ['idx_pk' => ['id']],
        'school_financial_account_channels' => ['idx_pk' => ['financial_account_id', 'channel_id']],
        'payment_collection_routes' => ['idx_pk' => ['id']],
        'payment_collection_route_channels' => ['idx_pk' => ['route_id', 'channel_id']],
        'payment_pos_terminals' => ['idx_pk' => ['id']],
        'payment_reconciliations' => ['idx_pk' => ['id']],
        'payment_unmatched_cases' => ['idx_pk' => ['id']],
        'transport_monthly_bills' => ['idx_pk' => ['id']],
        'transport_payment_intents' => ['idx_pk' => ['id']],
        'transport_entitlement_payment_allocations' => ['idx_pk' => ['id']],
        'transport_vehicle_routes' => ['idx_pk' => ['id']],
        'uniform_catalog_images' => ['idx_pk' => ['id']],
        'uniform_payment_intents' => ['idx_pk' => ['id']],
        'uniform_payment_records' => ['idx_pk' => ['id']],
        'uniform_sales' => ['idx_pk' => ['id']],
        'routes_registry' => ['idx_pk' => ['id']],
        'workflow_stage_history' => ['idx_pk' => ['id']],
        'workflow_stages' => ['idx_pk' => ['id']],
        'workflow_definitions' => ['idx_pk' => ['id']],
        'admission_documents' => ['idx_pk' => ['id']],
        'admission_interview_assessment_items' => ['idx_pk' => ['id']],
        'admission_placement_tests' => ['idx_pk' => ['id']],
        'admission_windows' => ['idx_pk' => ['id']],
        'analytics_report_metrics' => ['idx_pk' => ['report_definition_id', 'metric_definition_id']],
        'analytics_report_runs' => ['idx_pk' => ['id']],
        'assessment_learning_outcomes' => ['idx_pk' => ['assessment_id', 'learning_outcome_id']],
        'assessment_policy_documents' => ['idx_pk' => ['id']],
        'assessment_policy_schedule_rules' => ['idx_pk' => ['id']],
        'assessment_rubric_criteria' => ['idx_pk' => ['assessment_id', 'assessment_rubric_id']],
        'core_competencies' => ['idx_pk' => ['id']],
        'core_values' => ['idx_pk' => ['id']],
        'counseling_sessions' => ['idx_pk' => ['id']],
        'learner_competencies' => ['idx_pk' => ['id']],
        'learner_values_acquisition' => ['idx_pk' => ['id']],
        'learning_outcomes' => ['idx_pk' => ['id']],
        'leave_types' => ['idx_pk' => ['id']],
        'lesson_plan_assessment_rubrics' => ['idx_pk' => ['lesson_plan_id', 'assessment_rubric_id']],
        'lesson_plan_assessment_tools' => ['idx_pk' => ['lesson_plan_id', 'assessment_tool_id']],
        'lesson_plan_competencies' => ['idx_pk' => ['lesson_plan_id', 'competency_id']],
        'lesson_plan_learner_evidence_questions' => ['idx_pk' => ['id']],
        'lesson_plan_learner_evidence_resources' => ['idx_pk' => ['learner_evidence_id', 'lesson_plan_resource_id']],
        'lesson_plan_outcomes' => ['idx_pk' => ['lesson_plan_id', 'learning_outcome_id']],
        'lesson_plan_rubrics' => ['idx_pk' => ['lesson_plan_id', 'sub_strand_rubric_id']],
        'sub_strand_competencies' => ['idx_pk' => ['id']],
        'strand_competency' => ['idx_pk' => ['id']],
        'activity_categories' => ['idx_pk' => ['id']],
        'activity_participants' => ['idx_pk' => ['id']],
        'activity_resources' => ['idx_pk' => ['id']],
        'chapel_program_sessions' => ['idx_pk' => ['id']],
        'chaplaincy_volunteers' => ['idx_pk' => ['id']],
        'duty_roster_drafts' => ['idx_pk' => ['id']],
        'equipment_maintenance' => ['idx_pk' => ['id']],
        'exam_timetable_draft_entries' => ['idx_pk' => ['id']],
        'exam_timetable_drafts' => ['idx_pk' => ['id']],
        'extra_charge_classes' => ['idx_pk' => ['extra_charge_id', 'class_id']],
        'extra_charges' => ['idx_pk' => ['id']],
        'national_assessment_windows' => ['idx_pk' => ['id']],
        'parent_meeting_targets' => ['idx_pk' => ['id']],
        'past_papers' => ['idx_pk' => ['id']],
        'performance_review_kpis' => ['idx_pk' => ['id']],
        'staff_children' => ['idx_pk' => ['id']],
        'staff_deductions' => ['idx_pk' => ['id']],
        'staff_offboarding' => ['idx_pk' => ['id']],
        'staff_payroll_award_recipients' => ['idx_pk' => ['batch_id', 'staff_id']],
        'staff_qualifications' => ['idx_pk' => ['id']],
        'staff_role_default_positions' => ['idx_pk' => ['role_id', 'position_id']],
        'staff_specialization_qualifications' => ['idx_pk' => ['specialization_id', 'qualification_id']],
        'scheme_workbook_items' => ['idx_pk' => ['id']],
        'scheme_workbook_weeks' => ['idx_pk' => ['id']],
        'scheme_templates' => ['idx_pk' => ['id']],
        'scheme_workbooks' => ['idx_pk' => ['id']],
        'timetable_drafts' => ['idx_pk' => ['id']],
        'academic_class_progression' => ['idx_pk' => ['id']],
        'academic_year_class_learning_area_teachers' => ['idx_pk' => ['id']],
        'academic_year_class_stream_learning_area_teachers' => ['idx_pk' => ['id']],
        'academic_year_class_learning_areas' => ['idx_pk' => ['id']],
        'academic_year_calendar' => ['idx_pk' => ['id']],
        'blocked_devices' => ['idx_pk' => ['id']],
        'catalog_order_items' => ['idx_pk' => ['id']],
        'catalog_orders' => ['idx_pk' => ['id']],
        'catalog_reviews' => ['idx_pk' => ['id']],
        'catalog_stock_units' => ['idx_pk' => ['id']],
        'catalog_wishlists' => ['idx_pk' => ['id']],
        'inventory_locations' => ['idx_pk' => ['id']],
        'job_vacancies' => ['idx_pk' => ['id']],
        'leadership_positions' => ['idx_pk' => ['id']],

        'academic_term_terms' => ['idx_pk' => ['academic_year_term_id']],
        'academic_year_class_stream_learning_areas_detailed' => ['idx_pk' => ['id']],
        'academic_year_classes_streams' => ['idx_pk' => ['id']],
        'assessment_results_detailed' => ['idx_pk' => ['id']],
        'leadership_positions_categories' => ['idx_pk' => ['id']],
        'strands_learning_areas' => ['idx_pk' => ['id']],
        'strands_sub_strands' => ['idx_pk' => ['id']],
        'student_academic_enrollments_fees' => ['idx_pk' => ['id']],
        'student_academic_enrollments_streams' => ['idx_pk' => ['id']],
        'student_fee_obligations_enrolled' => ['idx_pk' => ['id']],
        'academic_year_calendar_days_typed' => [
            'idx_id' => ['id'],
            'idx_calendar_date' => ['date'],
            'idx_calendar_day_type' => ['calendar_day_type_id'],
        ],
        'inventory_transactions_items' => [
            'idx_id' => ['id'],
            'idx_item' => ['item_id'],
            'idx_transaction_date' => ['transaction_date'],
        ],
        'admission_applications_interviews' => [
            'idx_id' => ['id'],
            'idx_interview' => ['interview_record_id'],
            'idx_status' => ['status'],
        ],
        'learner_values_acquisition_values' => [
            'idx_id' => ['id'],
            'idx_student' => ['student_id'],
            'idx_value' => ['value_id'],
        ],
        'portfolio_artifacts_portfolios' => [
            'idx_id' => ['id'],
            'idx_portfolio' => ['portfolio_id'],
            'idx_student' => ['student_id'],
        ],
        'attendance_sessions_config' => [
            'idx_id' => ['id'],
            'idx_term' => ['academic_year_term_id'],
            'idx_session' => ['session_id'],
        ],
        'mpesa_transactions_payments' => [
            'idx_id' => ['id'],
            'idx_normalized_reference' => ['normalized_reference'],
            'idx_payment' => ['payment_id'],
        ],
        'persons' => ['idx_pk' => ['id']],
        'student_academic_enrollments' => ['idx_pk' => ['id']],
        'academic_year_class_streams' => ['idx_pk' => ['id']],
        'students' => ['idx_pk' => ['id']],
        'staff' => ['idx_pk' => ['id']],
        'academic_year_terms' => ['idx_pk' => ['id']],
        'assessments' => ['idx_pk' => ['id']],
        'parents' => ['idx_pk' => ['id']],
        'exam_schedules' => ['idx_pk' => ['id']],
        'learner_placement' => ['idx_pk' => ['enrollment_id']],
        'exam_context' => ['idx_pk' => ['result_id']],
        'scheme_lesson_context' => ['idx_pk' => ['scheme_id']],
        'learner_guardian' => ['idx_pk' => ['student_parent_id']],
        'staff_context' => ['idx_pk' => ['staff_id']],
        'attendance_register_context' => ['idx_pk' => ['attendance_id']],
        'fee_statement_context' => ['idx_pk' => ['student_academic_enrollment_id']],
        'admission_workflow_context' => ['idx_pk' => ['application_id']],
        'transport_assignment_context' => ['idx_pk' => ['assignment_id']],
        'boarding_dorm_context' => ['idx_pk' => ['assignment_id']],
        'lesson_plan_context' => ['idx_pk' => ['lesson_plan_id']],
        'class_stream_directory' => ['idx_pk' => ['id'], 'idx_year_class' => ['academic_year_id', 'class_id']],
    ];

    private function __construct()
    {
    }

    public static function supports(string $projection): bool
    {
        return isset(ReadReplicaService::MATERIALIZED_TARGETS[$projection]);
    }

    public static function targetTable(string $projection): string
    {
        if (!self::supports($projection)) {
            throw new \DomainException("Projection '{$projection}' is not materialized yet.", 422);
        }
        return ReadReplicaService::MATERIALIZED_TARGETS[$projection];
    }

    /**
     * One scheduled slice of the queued (worker-drained) refresh batch.
     *
     * Returns the `$batch`-th slice of the projections that are safe to
     * refresh through the queue: inline-refreshed hot projections (budget at
     * or below INLINE_REFRESH_BUDGET_SECONDS) are excluded, the remainder is
     * sorted for a stable slice order across the five cron lines.
     *
     * @return list<string>
     */
    public static function batchQueueSlice(int $batch, int $modulus = 5): array
    {
        $queueable = [];
        foreach (array_keys(ReadReplicaService::MATERIALIZED_TARGETS) as $projection) {
            $budget = ReadReplicaService::POLICIES[$projection]['max_age_seconds'] ?? 600;
            if ($budget <= self::INLINE_REFRESH_BUDGET_SECONDS) {
                continue;
            }
            $queueable[] = $projection;
        }
        sort($queueable);

        return array_values(array_filter(
            $queueable,
            static fn (string $name, int $index): bool => $index % $modulus === $batch,
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Whether a projection is refreshed inline by its own crontab line
     * instead of through the queued batch path.
     */
    public static function usesInlineRefresh(string $projection): bool
    {
        $budget = ReadReplicaService::POLICIES[$projection]['max_age_seconds'] ?? 600;
        return $budget <= self::INLINE_REFRESH_BUDGET_SECONDS;
    }

    /**
     * Refresh one projection and return a redacted operational result.
     *
     * @return array<string,mixed>
     */
    public static function synchronize(string $projection): array
    {
        $target = self::targetTable($projection);
        $source = ReadReplicaService::sourceFor($projection);
        $reads = ConnectionManager::schemaFor(ConnectionManager::NS_READS);
        $stage = self::STAGE_PREFIX . substr($target, 0, 40) . '_' . substr(bin2hex(random_bytes(8)), 0, 12);
        $pdo = Database::getInstance()->getConnection();
        $started = microtime(true);
        $sourceQueryMs = 0;
        $indexBuildMs = 0;
        $rows = 0;
        $lockName = 'KingswayProjection:' . $projection;
        $lock = ConnectionManager::run(static function (PDO $active) use ($lockName): int {
            $stmt = $active->prepare('SELECT GET_LOCK(?, 0)');
            $stmt->execute([$lockName]);
            return (int) $stmt->fetchColumn();
        }, ConnectionManager::NS_READS);
        if ($lock !== 1) {
            throw new RuntimeException('This read projection is already being refreshed.');
        }

        try {
            ConnectionManager::run(static function (PDO $active) use ($reads, $source, $stage, $projection, &$sourceQueryMs, &$indexBuildMs, &$rows): void {
                self::assertSourceExists($active, $source);
                self::createStage($active, $reads, $source, $stage);
                $queryStarted = microtime(true);
                $insert = $active->exec('INSERT INTO ' . self::qid($reads, $stage) . ' SELECT * FROM ' . self::qualified($source));
                $sourceQueryMs = (int) round((microtime(true) - $queryStarted) * 1000);
                $rows = max(0, (int) $insert);

                $indexStarted = microtime(true);
                self::createReadIndexes($active, $reads, $stage, $projection);
                $indexBuildMs = (int) round((microtime(true) - $indexStarted) * 1000);
            }, ConnectionManager::NS_READS);

            $result = ConnectionManager::run(static function (PDO $active) use ($reads, $target, $stage, $source, $projection, $rows): array {
                self::publish($active, $reads, $target, $stage);
                $watermark = self::watermark($active, $source);
                self::writeMeta($active, $reads, $projection, $source, $rows, $watermark, null);
                return ['rows_count' => $rows, 'source_watermark' => $watermark];
            }, ConnectionManager::NS_READS);

            $report = [
                'status' => 'published',
                'projection' => $projection,
                'target' => $target,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'source_query_ms' => $sourceQueryMs,
                'index_build_ms' => $indexBuildMs,
            ] + $result;
            FileLogger::write('reads', ['event' => 'projection_refreshed'] + $report);
            return $report;
        } catch (\Throwable $e) {
            FileLogger::write('reads', [
                'event' => 'projection_refresh_failed',
                'projection' => $projection,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'error_class' => get_class($e),
            ], 'error');
            try {
                ConnectionManager::run(static function (PDO $active) use ($reads, $stage, $projection, $source, $e): void {
                    self::dropIfExists($active, $reads, $stage);
                    $target = self::targetTable($projection);
                    if (self::objectExists($active, $reads, $target)) {
                        // Keep serving the last known good snapshot until its
                        // normal freshness limit expires; a failed refresh must
                        // not force every reader back onto the heavy source view.
                        $meta = $active->prepare('UPDATE ' . self::qid($reads, 'reads_meta') . ' SET last_error = ? WHERE projection = ?');
                        $meta->execute([self::redactError($e->getMessage()), $projection]);
                    } else {
                        self::writeMeta($active, $reads, $projection, $source, 0, null, self::redactError($e->getMessage()));
                    }
                }, ConnectionManager::NS_READS);
            } catch (\Throwable $metaError) {
                // Preserve the original synchronization failure.
            }
            throw new RuntimeException('Read projection synchronization failed: ' . self::redactError($e->getMessage()), 0, $e);
        } finally {
            try {
                ConnectionManager::run(static function (PDO $active) use ($lockName): void {
                    $stmt = $active->prepare('SELECT RELEASE_LOCK(?)');
                    $stmt->execute([$lockName]);
                }, ConnectionManager::NS_READS);
            } catch (\Throwable $releaseError) {
                FileLogger::write('reads', [
                    'event' => 'projection_lock_release_failed',
                    'projection' => $projection,
                    'error_class' => get_class($releaseError),
                ], 'warning');
            }
            unset($pdo);
        }
    }

    /** Recreate the indexed read shape on the staging snapshot before publish. */
    private static function createReadIndexes(PDO $pdo, string $reads, string $stage, string $projection): void
    {
        $indexes = self::READ_INDEXES[$projection] ?? [];
        if ($indexes === []) {
            throw new RuntimeException("Projection '{$projection}' has no registered read indexes.");
        }

        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([$reads, $stage]);
        $columns = array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);

        foreach ($indexes as $name => $indexColumns) {
            foreach ($indexColumns as $column) {
                if (!isset($columns[$column])) {
                    throw new RuntimeException("Registered read index column '{$column}' is missing from '{$projection}'.");
                }
            }
            $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '', $column) . '`', $indexColumns);
            $pdo->exec(
                'ALTER TABLE ' . self::qid($reads, $stage)
                . ' ADD INDEX `' . str_replace('`', '', $name) . '` (' . implode(',', $quotedColumns) . ')'
            );
        }
    }

    private static function assertSourceExists(PDO $pdo, string $source): void
    {
        [$schema, $object] = self::splitQualified($source);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([$schema, $object]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException("Source projection '{$object}' does not exist.");
        }
    }

    private static function createStage(PDO $pdo, string $reads, string $source, string $stage): void
    {
        self::dropIfExists($pdo, $reads, $stage);
        // CREATE TABLE AS SELECT copies the view's result columns without
        // copying source indexes or exposing source DDL to the reads schema.
        $pdo->exec('CREATE TABLE ' . self::qid($reads, $stage) . ' AS SELECT * FROM ' . self::qualified($source) . ' WHERE 1 = 0');
    }

    private static function publish(PDO $pdo, string $reads, string $target, string $stage): void
    {
        $old = self::STAGE_PREFIX . 'old_' . substr($target, 0, 40) . '_' . substr(bin2hex(random_bytes(6)), 0, 8);
        $targetExists = self::objectExists($pdo, $reads, $target);
        if ($targetExists) {
            try {
                $pdo->exec('RENAME TABLE ' . self::qid($reads, $target) . ' TO ' . self::qid($reads, $old) . ', ' . self::qid($reads, $stage) . ' TO ' . self::qid($reads, $target));
                self::dropIfExists($pdo, $reads, $old);
            } catch (\PDOException $e) {
                if (!self::isBrokenTableRename($e)) {
                    throw $e;
                }

                // Read projections are disposable copies. MariaDB can retain
                // an information_schema entry after an interrupted InnoDB
                // rename, making the normal atomic swap fail with errno 155.
                // Remove only the damaged target, then publish the complete
                // staged snapshot; the master schema is never touched.
                self::dropIfExists($pdo, $reads, $target);
                $pdo->exec('RENAME TABLE ' . self::qid($reads, $stage) . ' TO ' . self::qid($reads, $target));
            }
            return;
        }
        $pdo->exec('RENAME TABLE ' . self::qid($reads, $stage) . ' TO ' . self::qid($reads, $target));
    }

    private static function isBrokenTableRename(\PDOException $e): bool
    {
        $errorInfo = $e->errorInfo ?? [];
        $message = strtolower($e->getMessage());
        return (int) ($errorInfo[1] ?? 0) === 1025
            && (str_contains($message, 'does not exist in the storage engine')
                || str_contains($message, 'error on rename'));
    }

    private static function objectExists(PDO $pdo, string $schema, string $object): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([$schema, $object]);
        return (int) $stmt->fetchColumn() === 1;
    }

    private static function dropIfExists(PDO $pdo, string $schema, string $object): void
    {
        if (self::objectExists($pdo, $schema, $object)) {
            $pdo->exec('DROP TABLE ' . self::qid($schema, $object));
        }
    }

    private static function watermark(PDO $pdo, string $source): ?string
    {
        try {
            $value = $pdo->query('SELECT MAX(`month`) FROM ' . self::qualified($source))->fetchColumn();
            return $value === false || $value === null ? null : (string) $value;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function writeMeta(PDO $pdo, string $reads, string $projection, string $source, int $rows, ?string $watermark, ?string $error): void
    {
        $policy = ReadReplicaService::policy($projection);
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::qid($reads, 'reads_meta') . ' '
            . '(projection, source_view, rows_count, source_watermark, as_of, refreshed_at, status, storage_mode, sensitivity, max_age_seconds, last_error) '
            . 'VALUES (:projection, :source_view, :rows_count, :source_watermark, NOW(), '
            . ($error === null ? 'NOW()' : 'NULL') . ', :status, :storage_mode, :sensitivity, :max_age_seconds, :last_error) '
            . 'ON DUPLICATE KEY UPDATE source_view=VALUES(source_view), rows_count=VALUES(rows_count), '
            . 'source_watermark=VALUES(source_watermark), as_of=VALUES(as_of), refreshed_at=VALUES(refreshed_at), '
            . 'status=VALUES(status), storage_mode=VALUES(storage_mode), sensitivity=VALUES(sensitivity), '
            . 'max_age_seconds=VALUES(max_age_seconds), last_error=VALUES(last_error)'
        );
        $stmt->execute([
            ':projection' => $projection,
            ':source_view' => $source,
            ':rows_count' => $rows,
            ':source_watermark' => $watermark,
            ':status' => $error === null ? 'live' : 'failed',
            ':storage_mode' => 'materialized_table',
            ':sensitivity' => $policy['sensitivity'],
            ':max_age_seconds' => $policy['max_age_seconds'],
            ':last_error' => $error,
        ]);
    }

    private static function splitQualified(string $source): array
    {
        $parts = explode('.', $source, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Source object must be schema-qualified.');
        }
        return [trim($parts[0], '`'), trim($parts[1], '`')];
    }

    private static function qualified(string $source): string
    {
        [$schema, $object] = self::splitQualified($source);
        return self::qid($schema, $object);
    }

    private static function qid(string $schema, string $object): string
    {
        return '`' . str_replace('`', '', $schema) . '`.`' . str_replace('`', '', $object) . '`';
    }

    private static function redactError(string $message): string
    {
        $message = preg_replace('/(?:password|secret|token|key)\s*[=:]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? $message;
        return substr($message, 0, 500);
    }
}
