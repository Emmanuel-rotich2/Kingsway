<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;
use DomainException;

/** Policy boundary between staff requests and AI workflow execution. */
class AiWorkflowService
{
    public function describe(): array
    {
        return array_values(AiWorkflowRegistry::all());
    }

    /** Return only workflows suitable for the authenticated staff shell. */
    public function describeForContext(array $permissions, string $route = '', string $module = 'dashboard', string $audience = 'staff', array $roleNames = []): array
    {
        $permissions = array_values(array_filter(array_map(static function ($permission): string {
            if (is_array($permission)) {
                $permission = $permission['code'] ?? $permission['permission_code'] ?? $permission['name'] ?? '';
            } elseif (is_object($permission)) {
                $permission = $permission->code ?? $permission->permission_code ?? $permission->name ?? '';
            }
            return is_scalar($permission) ? trim((string) $permission) : '';
        }, $permissions)));
        $route = strtolower(trim($route));
        $module = strtolower(trim($module));
        $routeDomains = [
            'admissions' => ['admission', 'enrollment', 'manage_students', 'interview', 'placement', 'applicant'],
            'academics' => ['academic', 'scheme', 'lesson', 'assessment', 'timetable', 'exam', 'cbc', 'rubric', 'coverage', 'curriculum', 'portfolio', 'grade'],
            'attendance' => ['attendance', 'absen', 'late', 'register'], 'boarding' => ['boarding', 'roll_call', 'exeat', 'dorm', 'hostel'],
            'transport' => ['transport', 'route', 'vehicle', 'fuel', 'manifest', 'driver'], 'inventory' => ['inventory', 'stock', 'requisition', 'store', 'asset', 'uniform'],
            'catering' => ['food', 'catering', 'meal', 'menu', 'bakery'], 'maintenance' => ['maintenance', 'facility', 'equipment'],
            'finance' => ['finance', 'payment', 'fee', 'reconciliation', 'budget', 'expense', 'payroll', 'ledger', 'arrear', 'invoice'],
            'communications' => ['communication', 'message', 'sms', 'email', 'whatsapp', 'inbox', 'announcement', 'forum', 'outbox'],
            'staff' => ['staff', 'hr', 'leave', 'workload', 'onboarding', 'appraisal', 'payslip'],
            'health' => ['health', 'sick', 'welfare', 'counsel', 'clinic', 'nurse'],
            'counseling' => ['counsel', 'welfare', 'safeguard', 'case', 'guidance'],
            'activities' => ['activit', 'sport', 'club', 'library', 'resource', 'talent'],
            'reports' => ['report', 'analytics', 'kpi', 'insight'], 'system' => ['system', 'diagnostic', 'health', 'admin', 'queue', 'log', 'audit', 'security'],
            'curriculum' => ['curriculum', 'scheme', 'lesson', 'assessment', 'kicd', 'strand'],
        ];
        $roleNames = array_values(array_filter(array_map(static function ($role): string {
            if (is_array($role)) {
                $role = $role['name'] ?? $role['role_name'] ?? '';
            } elseif (is_object($role)) {
                $role = $role->name ?? $role->role_name ?? '';
            }
            return is_scalar($role) ? strtolower(trim((string) $role)) : '';
        }, $roleNames)));
        $visible = array_values(array_filter($this->describe(), static function (array $workflow) use ($permissions, $route, $module, $audience, $routeDomains, $roleNames): bool {
            if (!in_array($audience, (array) ($workflow['audiences'] ?? ['staff']), true)) return false;
            $required = (string) ($workflow['permission'] ?? '');
            if ($required !== '' && !in_array('*', $permissions, true) && !in_array($required, $permissions, true)) return false;
            if ($roleNames !== [] && !self::roleMayUseDomain($roleNames, (string) ($workflow['domain'] ?? 'system'))) return false;
            return true;
        }));
        $routeMatches = static function (array $workflow) use ($route, $module, $routeDomains): bool {
            if ($route === '' || $route === 'dashboard') return false;
            $domain = strtolower((string) ($workflow['domain'] ?? 'system'));
            foreach ($routeDomains[$domain] ?? [$module] as $token) {
                if ($token !== '' && str_contains($route, $token)) return true;
            }
            return false;
        };
        $contextualCount = 0;
        foreach ($visible as $workflow) {
            if ($routeMatches($workflow)) {
                $contextualCount++;
            }
        }
        if ($contextualCount > 0) {
            // A recognized workspace shows its domain assistance first and
            // keeps the catalogue scoped to that domain (existing contract).
            $visible = array_values(array_filter($visible, $routeMatches));
            foreach ($visible as &$workflow) {
                $workflow['contextual'] = true;
            }
            unset($workflow);
        } elseif ($route !== '' && $route !== 'dashboard') {
            // An unrecognized route must never hide every permitted workflow:
            // surface everything the user may use, ranked with contextual
            // metadata so the shell can still order its display.
            foreach ($visible as &$workflow) {
                $workflow['contextual'] = false;
            }
            unset($workflow);
        } else {
            foreach ($visible as &$workflow) {
                $workflow['contextual'] = true;
            }
            unset($workflow);
        }
        foreach ($visible as &$workflow) {
            $workflow['suggested_questions'] = $this->suggestedQuestions((string)($workflow['id'] ?? ''));
        }
        unset($workflow);
        return $visible;
    }

    /** Keep broad legacy permission rows from crossing AI domain boundaries. */
    private static function roleMayUseDomain(array $roles, string $domain): bool
    {
        $patterns = [
            'academics' => ['teacher', 'headteacher', 'deputy', 'academic', 'intern'],
            'curriculum' => ['teacher', 'headteacher', 'deputy', 'academic', 'intern'],
            'finance' => ['accountant', 'director', 'administrator', 'headteacher'],
            'inventory' => ['inventory', 'store', 'procurement', 'director', 'administrator'],
            'catering' => ['catering', 'cook', 'kitchen', 'boarding', 'director', 'administrator'],
            'attendance' => ['teacher', 'headteacher', 'deputy', 'boarding', 'administrator'],
            'boarding' => ['boarding', 'headteacher', 'deputy', 'administrator'],
            'admissions' => ['admission', 'administrator', 'headteacher', 'director'],
            'reports' => ['teacher', 'headteacher', 'deputy', 'accountant', 'administrator', 'director', 'manager'],
            'system' => ['administrator', 'director', 'security'],
            'staff' => ['administrator', 'director', 'headteacher', 'hr', 'deputy'],
            'health' => ['administrator', 'director', 'headteacher', 'nurse', 'counsel', 'welfare'],
            'counseling' => ['administrator', 'director', 'headteacher', 'counsel', 'welfare', 'safeguard'],
            'activities' => ['administrator', 'director', 'headteacher', 'activity', 'sport', 'club', 'library'],
        ];
        $needles = $patterns[$domain] ?? [];
        if ($needles === []) return true;
        foreach ($roles as $role) {
            foreach ($needles as $needle) {
                if (str_contains($role, $needle)) return true;
            }
        }
        return false;
    }

    /** Suggestions are server-owned and returned only after workflow RBAC filtering. */
    private function suggestedQuestions(string $workflowId): array
    {
        return [
            'academics.scheme_draft' => ['Prepare a CBC scheme draft for this term.'],
            'academics.lesson_plan_draft' => ['Prepare a lesson plan from my approved scheme.'],
            'academics.assessment_draft' => ['Suggest an assessment brief for this learning outcome.'],
            'academics.timetable_planning' => ['Ask me for the missing timetable constraints.'],
            'attendance.exception_summary' => ['Which attendance registers need follow-up?'],
            'attendance.lateness_pattern_review' => ['Summarize the authorized lateness patterns for this period.'],
            'boarding.exception_summary' => ['Which boarding roll-call or welfare exceptions need review?'],
            'transport.operations_summary' => ['Summarize the authorized transport capacity and follow-up signals.'],
            'inventory.replenishment_review' => ['Which authorized stock items need replenishment review?'],
            'catering.consumption_review' => ['Summarize meal consumption and waste variance for review.'],
            'maintenance.facilities_review' => ['Which facilities or equipment exceptions need follow-up?'],
            'health.welfare_review' => ['Summarize aggregate welfare administration follow-up without identifying learners.'],
            'activities.resource_review' => ['Summarize authorized activity participation and resource follow-up.'],
            'activities.library_review' => ['Summarize aggregate library and resource utilization for review.'],
            'staff.hr_review' => ['Summarize authorized staff workload, leave, and coverage signals.'],
            'counseling.welfare_review' => ['Summarize aggregate counselling follow-up without exposing case identities.'],
            'curriculum.kicd_change_interpretation' => ['Explain the approved curriculum-policy change for staff review.'],
            'finance.reconciliation_review' => ['Summarize the authorized reconciliation exceptions.'],
            'admissions.application_followup_draft' => ['Which authorized applications need follow-up?'],
            'communications.parent_message_draft' => ['Prepare a school communication draft for human approval.'],
            'reports.kpi_brief' => ['Explain the key trends in this report.'],
            'reports.school_brief' => ['Summarize the governed school intelligence signals for this period.'],
            'system.nlq_query' => ['What are the key trends in my authorized school reports?'],
            'system.operations_brief' => ['Summarize current queue and system health signals.'],
            'system.security_brief' => ['Summarize current authentication and authorization signals.'],
        ][$workflowId] ?? [];
    }

    public function authorize(string $workflowId, array $context): array
    {
        $workflow = AiWorkflowRegistry::resolve($workflowId);
        if ($workflow === null) {
            throw new DomainException('AI workflow not found.', 404);
        }
        $audience = (string) ($context['audience'] ?? 'staff');
        if (!in_array($audience, (array) ($workflow['audiences'] ?? ['staff']), true)) {
            throw new DomainException('AI workflow is not available for this audience.', 403);
        }
        $permissions = array_values(array_unique(array_map('strval', array_merge(
            (array) ($context['permissions'] ?? []),
            (array) ($context['effective_permissions'] ?? [])
        ))));
        if ($workflow['permission'] !== '' && !in_array('*', $permissions, true)
            && !in_array($workflow['permission'], $permissions, true)) {
            throw new DomainException('AI workflow is not permitted for this user.', 403);
        }
        if ($workflow['action_level'] === 'execute' && empty($context['explicit_execute'])) {
            throw new DomainException('Explicit execution approval is required.', 403);
        }
        return $workflow;
    }

    /**
     * Execute only after the caller has supplied already-authorized,
     * minimized context. This foundation returns a reviewable job envelope;
     * domain adapters will add validated handlers in later slices.
     */
    public function prepare(string $workflowId, array $context, array $input): array
    {
        $workflow = $this->authorize($workflowId, $context);
        $minimized = AiPromptPolicy::minimize($workflowId, $input);
        $payload = [
            'workflow_id' => $workflowId,
            'action_level' => $workflow['action_level'],
            'requires_approval' => $workflow['requires_approval'],
            'operator_id' => (int) ($context['user_id'] ?? 0),
            'input_hash' => hash('sha256', json_encode($minimized)),
            'status' => 'awaiting_review',
        ];
        FileLogger::write('ai_generation', [
            'type' => 'workflow_prepared',
            'workflow_id' => $workflowId,
            'operator_id' => $payload['operator_id'],
            'input_hash' => $payload['input_hash'],
            'action_level' => $workflow['action_level'],
        ]);
        return $payload;
    }
}
