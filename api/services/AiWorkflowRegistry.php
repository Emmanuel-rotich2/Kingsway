<?php

namespace App\API\Services;

use LogicException;

/** Central catalogue of governed staff-assistance workflows. */
class AiWorkflowRegistry
{
    private static $workflows = null;

    public static function all(): array
    {
        self::boot();
        return self::$workflows;
    }

    public static function resolve(string $id): ?array
    {
        self::boot();
        return self::$workflows[$id] ?? null;
    }

    public static function register(array $definition): void
    {
        self::boot();
        $id = (string) ($definition['id'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_.-]{2,100}$/', $id) !== 1) {
            throw new LogicException("Invalid AI workflow id '{$id}'.");
        }
        if (isset(self::$workflows[$id])) {
            throw new LogicException("AI workflow '{$id}' is already registered.");
        }
        $level = (string) ($definition['action_level'] ?? 'assist');
        if (!in_array($level, ['assist', 'recommend', 'prepare', 'execute'], true)) {
            throw new LogicException("AI workflow '{$id}' has an invalid action level.");
        }
        self::$workflows[$id] = [
            'id' => $id,
            'domain' => (string) ($definition['domain'] ?? 'system'),
            'summary' => (string) ($definition['summary'] ?? ''),
            'action_level' => $level,
            'requires_approval' => (bool) ($definition['requires_approval'] ?? true),
            'sensitive' => (bool) ($definition['sensitive'] ?? true),
            'permission' => (string) ($definition['permission'] ?? ''),
            'audiences' => array_values(array_unique(array_map('strval', (array) ($definition['audiences'] ?? ['staff'])))),
            'status' => (string) ($definition['status'] ?? 'planned'),
        ];
    }

    private static function boot(): void
    {
        if (self::$workflows !== null) {
            return;
        }
        self::$workflows = [];
        foreach ([
            ['id' => 'admissions.application_followup_draft', 'domain' => 'admissions', 'summary' => 'Draft a completeness or follow-up message for an application.', 'permission' => 'admission_view', 'status' => 'implemented'],
            ['id' => 'admissions.interview_preparation', 'domain' => 'admissions', 'summary' => 'Prepare an interview agenda from an authorized application stage and policy context.', 'action_level' => 'prepare', 'permission' => 'admission_view', 'status' => 'planned'],
            ['id' => 'admissions.placement_review', 'domain' => 'admissions', 'summary' => 'Prepare placement review notes from authorized aggregate application and assessment signals.', 'action_level' => 'recommend', 'permission' => 'admission_view', 'status' => 'planned'],
            ['id' => 'academics.scheme_draft', 'domain' => 'academics', 'summary' => 'Prepare a grounded CBC scheme draft for teacher review.', 'permission' => 'academic_view', 'status' => 'implemented'],
            ['id' => 'academics.lesson_plan_draft', 'domain' => 'academics', 'summary' => 'Prepare a grounded lesson-plan draft from an approved scheme row.', 'permission' => 'academic_view', 'status' => 'implemented'],
            ['id' => 'academics.assessment_draft', 'domain' => 'academics', 'summary' => 'Prepare an assessment and question suggestions grounded in an authorized CBC outcome.', 'permission' => 'academic_view', 'status' => 'implemented'],
            ['id' => 'academics.rubric_draft', 'domain' => 'academics', 'summary' => 'Prepare an editable rubric draft grounded in an authorized CBC outcome.', 'permission' => 'academic_view', 'status' => 'planned'],
            ['id' => 'academics.coverage_review', 'domain' => 'academics', 'summary' => 'Explain aggregate curriculum coverage signals for teacher review.', 'action_level' => 'recommend', 'permission' => 'academic_view', 'status' => 'planned'],
            ['id' => 'academics.learning_gap_review', 'domain' => 'academics', 'summary' => 'Prepare aggregate learning-gap follow-up suggestions without identifying learners to the provider.', 'action_level' => 'recommend', 'permission' => 'academic_view', 'status' => 'planned'],
            ['id' => 'academics.timetable_planning', 'domain' => 'academics', 'summary' => 'Collect timetable constraints and prepare an editable specialist-aware timetable plan for human approval.', 'action_level' => 'prepare', 'permission' => 'academic_view', 'status' => 'planned'],
            ['id' => 'learners.support_planning', 'domain' => 'learners', 'summary' => 'Prepare aggregate learner-support follow-up suggestions from authorized performance signals.', 'action_level' => 'recommend', 'permission' => 'student_view', 'status' => 'planned'],
            ['id' => 'attendance.exception_summary', 'domain' => 'attendance', 'summary' => 'Summarize authorized attendance exceptions and missing registers.', 'action_level' => 'recommend', 'permission' => 'attendance_view', 'status' => 'implemented'],
            ['id' => 'attendance.lateness_pattern_review', 'domain' => 'attendance', 'summary' => 'Review aggregate lateness patterns for authorized attendance scope.', 'action_level' => 'recommend', 'permission' => 'attendance_view', 'status' => 'planned'],
            ['id' => 'boarding.exception_summary', 'domain' => 'boarding', 'summary' => 'Summarize authorized boarding occupancy and roll-call exceptions.', 'action_level' => 'recommend', 'permission' => 'attendance_boarding_view', 'status' => 'implemented'],
            ['id' => 'transport.operations_summary', 'domain' => 'transport', 'summary' => 'Summarize authorized transport capacity and operational follow-up signals.', 'action_level' => 'recommend', 'permission' => 'transport_view', 'status' => 'implemented'],
            ['id' => 'inventory.replenishment_review', 'domain' => 'inventory', 'summary' => 'Prepare aggregate low-stock and replenishment review guidance.', 'action_level' => 'recommend', 'permission' => 'inventory_view', 'status' => 'implemented'],
            ['id' => 'catering.consumption_review', 'domain' => 'catering', 'summary' => 'Summarize aggregate catering consumption, waste, and meal variance.', 'action_level' => 'recommend', 'permission' => 'inventory_view', 'status' => 'implemented'],
            ['id' => 'maintenance.facilities_review', 'domain' => 'maintenance', 'summary' => 'Review aggregate equipment and vehicle maintenance exceptions and recurring work signals.', 'action_level' => 'recommend', 'permission' => 'maintenance_view', 'status' => 'implemented'],
            ['id' => 'health.welfare_review', 'domain' => 'health', 'summary' => 'Prepare aggregate health and welfare administration follow-up without exposing learner identities to the provider.', 'action_level' => 'recommend', 'permission' => 'health_view', 'status' => 'implemented'],
            ['id' => 'activities.resource_review', 'domain' => 'activities', 'summary' => 'Prepare aggregate activities participation and resource follow-up guidance.', 'action_level' => 'recommend', 'permission' => 'activities_view', 'status' => 'implemented'],
            ['id' => 'activities.library_review', 'domain' => 'activities', 'summary' => 'Prepare aggregate library/resource utilization guidance without exposing borrower identities.', 'action_level' => 'recommend', 'permission' => 'activities_view', 'status' => 'implemented'],
            ['id' => 'staff.hr_review', 'domain' => 'staff', 'summary' => 'Prepare aggregate staff workload, leave, onboarding, and coverage follow-up guidance.', 'action_level' => 'recommend', 'permission' => 'staff_view', 'status' => 'implemented'],
            ['id' => 'counseling.welfare_review', 'domain' => 'counseling', 'summary' => 'Prepare aggregate counselling follow-up guidance without exposing case identities or confidential notes.', 'action_level' => 'recommend', 'permission' => 'health_view', 'status' => 'implemented'],
            ['id' => 'curriculum.kicd_change_interpretation', 'domain' => 'curriculum', 'summary' => 'Interpret a changed KICD/curriculum policy fragment for staff review without asserting official status.', 'action_level' => 'recommend', 'permission' => 'academic_view', 'status' => 'implemented'],
            ['id' => 'finance.reconciliation_review', 'domain' => 'finance', 'summary' => 'Prepare review notes for unmatched or suspicious payment records.', 'action_level' => 'prepare', 'permission' => 'finance_view', 'status' => 'implemented'],
            ['id' => 'communications.parent_message_draft', 'domain' => 'communications', 'summary' => 'Draft a role-appropriate school communication for approval.', 'permission' => 'communications_view', 'status' => 'implemented'],
            ['id' => 'communications.parent_portal_assistant', 'domain' => 'communications', 'summary' => 'Answer a parent question using only linked-child portal summaries and public school facts.', 'action_level' => 'assist', 'requires_approval' => false, 'sensitive' => true, 'permission' => '', 'audiences' => ['parent'], 'status' => 'implemented'],
            ['id' => 'public.faq_assistant', 'domain' => 'public', 'summary' => 'Answer public school questions from the approved published corpus and route visitors to a human when needed.', 'action_level' => 'assist', 'requires_approval' => false, 'sensitive' => false, 'permission' => '', 'audiences' => ['public'], 'status' => 'implemented'],
            ['id' => 'research.external_knowledge', 'domain' => 'research', 'summary' => 'Answer research questions only from approved, cached external sources with citations and provenance.', 'action_level' => 'assist', 'requires_approval' => false, 'sensitive' => false, 'permission' => 'ai_research', 'audiences' => ['staff'], 'status' => 'implemented'],
            ['id' => 'reports.kpi_brief', 'domain' => 'reports', 'summary' => 'Explain governed KPI results using authorized aggregate data.', 'permission' => 'analytics_catalogue_view', 'status' => 'implemented'],
            ['id' => 'reports.school_brief', 'domain' => 'reports', 'summary' => 'Summarize deterministic intelligence signals as a reviewable daily, weekly, or term briefing for staff.', 'action_level' => 'recommend', 'permission' => 'analytics_catalogue_view', 'status' => 'implemented'],
            ['id' => 'system.nlq_query', 'domain' => 'system', 'summary' => 'Answer a natural-language staff question by routing it to an authorized governed report.', 'action_level' => 'assist', 'requires_approval' => false, 'sensitive' => false, 'permission' => 'analytics_catalogue_view', 'status' => 'implemented'],
            ['id' => 'system.operations_brief', 'domain' => 'system', 'summary' => 'Summarize queue, health, and data-quality signals for administrators.', 'action_level' => 'recommend', 'permission' => 'system_view', 'status' => 'implemented'],
            ['id' => 'system.security_brief', 'domain' => 'system', 'summary' => 'Summarize aggregate authentication and authorization anomaly signals for administrators.', 'action_level' => 'recommend', 'permission' => 'system_view', 'status' => 'implemented'],
        ] as $definition) {
            self::register($definition);
        }
    }
}
