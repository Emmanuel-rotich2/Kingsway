<?php

namespace App\API\Services;

use DomainException;

/** Applies workflow-specific data minimization before any provider call. */
class AiPromptPolicy
{
    private const FIELDS = [
        'admissions.application_followup_draft' => ['stage', 'missing_items', 'days_waiting', 'grade_band', 'term_name', 'channel'],
        'admissions.interview_preparation' => ['stage', 'grade_band', 'days_waiting', 'missing_items', 'interview_focus'],
        'admissions.placement_review' => ['stage', 'grade_band', 'interview_status', 'placement_signal_band', 'capacity_signal', 'follow_up_intent'],
        'communications.parent_message_draft' => ['purpose', 'audience', 'tone', 'facts', 'deadline', 'channel'],
        'communications.parent_portal_assistant' => ['question', 'audience', 'context'],
        'public.faq_assistant' => ['question', 'audience', 'corpus', 'conversation'],
        'research.external_knowledge' => ['question', 'audience', 'sources'],
        'finance.reconciliation_review' => ['unmatched_count', 'days_open', 'amount_band', 'currency', 'provider', 'reconciliation_rule'],
        'academics.scheme_draft' => ['learning_area', 'grade_level', 'term_name', 'instructional_weeks', 'strands', 'sub_strands', 'learning_outcomes', 'teacher_intent'],
        'academics.lesson_plan_draft' => ['learning_area', 'grade_level', 'term_name', 'week_number', 'strand', 'sub_strand', 'scheme_title', 'learning_outcomes', 'learning_experiences', 'resources', 'assessment_tools', 'competencies', 'inquiry_questions', 'teacher_intent'],
        'academics.assessment_draft' => ['learning_area', 'grade_level', 'term_name', 'assessment_type', 'max_marks', 'learning_outcome', 'teacher_intent'],
        'academics.rubric_draft' => ['learning_area', 'grade_level', 'term_name', 'learning_outcome', 'criterion_count', 'performance_levels', 'teacher_intent'],
        'academics.coverage_review' => ['report_date', 'grade_level', 'learning_area', 'planned_count', 'completed_count', 'overdue_count', 'coverage_rate', 'unmapped_count', 'follow_up_intent'],
        'academics.learning_gap_review' => ['report_date', 'grade_level', 'learning_area', 'learner_count', 'assessment_count', 'below_threshold_count', 'missing_evidence_count', 'gap_bands', 'follow_up_intent'],
        'academics.timetable_planning' => ['class_band', 'class_count', 'learning_areas', 'candidate_counts', 'assignment_candidates', 'period_count', 'availability_constraints', 'workload_constraints', 'room_constraints', 'double_periods', 'user_constraints'],
        'learners.support_planning' => ['report_date', 'scope', 'learner_count', 'attendance_below_threshold', 'average_score_band', 'discipline_case_count', 'fee_balance_band', 'support_follow_up_intent'],
        'reports.kpi_brief' => ['report_title', 'report_code', 'decision_purpose', 'as_of', 'row_count', 'summary', 'warnings'],
        'reports.school_brief' => ['cadence', 'report_date', 'as_of', 'domains_ran', 'alert_count', 'alerts_summary', 'metrics_summary', 'audience'],
        'attendance.exception_summary' => ['report_date', 'register_count', 'completed_count', 'open_count', 'overdue_count', 'not_marked_count', 'scope_stream_count', 'exception_types', 'follow_up_intent'],
        'attendance.lateness_pattern_review' => ['date_from', 'date_to', 'present_count', 'absent_count', 'late_count', 'late_rate', 'follow_up_intent'],
        'boarding.exception_summary' => ['report_date', 'dormitory_count', 'capacity', 'assigned_beds', 'available_beds', 'occupancy_rate', 'present_tonight', 'absent_or_unknown', 'on_leave', 'pending_leaves', 'urgent_notes', 'roll_call_rate', 'follow_up_intent'],
        'transport.operations_summary' => ['report_date', 'active_route_count', 'active_vehicle_count', 'assigned_passenger_count', 'follow_up_intent'],
        'inventory.replenishment_review' => ['report_date', 'active_item_count', 'low_stock_count', 'out_of_stock_count', 'pending_requisition_count', 'category_exception_count', 'follow_up_intent'],
        'catering.consumption_review' => ['date_from', 'date_to', 'meals_planned', 'planned_servings', 'prepared_meals', 'actual_servings', 'food_items', 'low_stock', 'quantity_used', 'waste_quantity', 'waste_rate', 'follow_up_intent'],
        'maintenance.facilities_review' => ['report_date', 'equipment_total', 'equipment_overdue', 'equipment_pending', 'equipment_scheduled', 'equipment_in_progress', 'vehicle_total', 'vehicle_repairs', 'vehicle_routine', 'vehicle_inspections', 'vehicle_emergencies', 'recurring_type_count', 'follow_up_intent'],
        'health.welfare_review' => ['report_date', 'record_count', 'active_visit_count', 'referral_count', 'vaccination_due_count', 'follow_up_intent'],
        'activities.resource_review' => ['report_date', 'activity_count', 'active_activity_count', 'upcoming_activity_count', 'participant_count', 'resource_count', 'follow_up_intent'],
        'activities.library_review' => ['report_date', 'resource_count', 'resource_type_count', 'available_count', 'assigned_count', 'follow_up_intent'],
        'staff.hr_review' => ['report_date', 'staff_count', 'teaching_staff_count', 'non_teaching_staff_count', 'on_leave_count', 'pending_leave_count', 'onboarding_count', 'workload_exception_count', 'follow_up_intent'],
        'counseling.welfare_review' => ['report_date', 'total_cases', 'open_cases', 'urgent_cases', 'follow_ups_due', 'sessions_count', 'student_case_count', 'staff_case_count', 'follow_up_intent'],
        'curriculum.kicd_change_interpretation' => ['source', 'report_date', 'audience', 'previous_hash', 'current_hash', 'content'],
        'system.nlq_query' => ['question', 'audience'],
        'system.operations_brief' => ['report_date', 'queue_total', 'queue_pending', 'queue_processing', 'queue_stale', 'queue_failed', 'queue_done', 'queue_cancelled', 'queue_dead_letter', 'oldest_processing_minutes', 'oldest_pending_minutes', 'worker_freshness_minutes', 'error_total_24h', 'error_critical_24h', 'error_category_count', 'error_signatures', 'follow_up_intent'],
        'system.security_brief' => ['report_date', 'failed_login_count', 'failed_login_identities', 'permission_denied_count', 'security_incident_count', 'top_signals', 'follow_up_intent'],
    ];

    public static function minimize(string $workflowId, array $input): array
    {
        $allowed = self::FIELDS[$workflowId] ?? null;
        if ($allowed === null) {
            throw new DomainException('This AI workflow has no approved prompt policy.', 422);
        }
        $unknown = array_diff(array_keys($input), $allowed);
        if ($unknown !== []) {
            throw new DomainException('Input contains fields that are not approved for this AI workflow.', 422);
        }
        $clean = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                if ($key === 'assignment_candidates') {
                    if (count($value) > 60) throw new DomainException('AI timetable candidates exceed the safe limit.', 422);
                    $clean[$key] = [];
                    foreach ($value as $candidate) {
                        if (!is_array($candidate)) throw new DomainException('AI timetable candidates must be objects.', 422);
                        $allowedCandidate = ['academic_year_class_stream_id', 'learning_area_id', 'teacher_id', 'day_of_week', 'time_slot_id'];
                        if (array_diff(array_keys($candidate), $allowedCandidate) !== []) {
                            throw new DomainException('AI timetable candidate contains an unapproved field.', 422);
                        }
                        $normalizedCandidate = [];
                        foreach ($allowedCandidate as $candidateKey) {
                            if (array_key_exists($candidateKey, $candidate)) {
                                $normalizedCandidate[$candidateKey] = max(0, (int) $candidate[$candidateKey]);
                            }
                        }
                        $clean[$key][] = $normalizedCandidate;
                    }
                    continue;
                }
                if (count($value) > 20) {
                    throw new DomainException('AI workflow input contains too many items.', 422);
                }
                foreach ($value as $item) {
                    if (!is_scalar($item)) {
                        throw new DomainException('AI workflow input contains an unsupported value.', 422);
                    }
                }
                $clean[$key] = array_map(static function ($item): string {
                    return mb_substr(trim((string) $item), 0, 500);
                }, $value);
                continue;
            }
            if (!is_scalar($value)) {
                throw new DomainException('AI workflow input contains an unsupported value.', 422);
            }
            $limit = $key === 'corpus' ? 15000 : 1000;
            $clean[$key] = mb_substr(trim((string) $value), 0, $limit);
        }
        $contextLimit = array_key_exists('corpus', $clean) ? 20000 : 10000;
        if (strlen((string) json_encode($clean)) > $contextLimit) {
            throw new DomainException('AI workflow context is too large.', 422);
        }
        return $clean;
    }
}
