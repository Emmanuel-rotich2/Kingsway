<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;
use PDO;
use Throwable;

/**
 * Governed agent-tool execution — PHP owns authorization, data and audit.
 *
 * Why this class exists
 * --------------------
 * The Python engine is the *reasoning* layer. It must never touch school data
 * and it must never be the thing that authorizes a tool. It relays the
 * recorded operator envelope here, and every tool is executed inside PHP
 * through the governed services the school already runs.
 *
 * The previous shape relayed straight back out to the Python engine, which has
 * no such route — so every governed tool failed with "the Python AI platform
 * rejected the request". This class breaks that cycle: PHP executes, it does
 * not re-delegate.
 *
 * The three gates every tool passes, in order:
 *   1. the operator's permissions are resolved from the database, never from
 *      what the engine claimed;
 *   2. the tool must be a registered governed workflow whose permission the
 *      operator holds — a subject teacher therefore cannot reach finance;
 *   3. the result is bounded and aggregate-first, so no schema or
 *      infrastructure detail ever leaves the platform.
 *
 * Draft tools queue a review entry through DraftRepository, which is the
 * audited path the rest of the school uses. Nothing here writes school data
 * implicitly, and nothing is applied until a human approves it.
 */
final class AgentToolExecutor
{
    /** Governed read tools that answer in the same turn. */
    private const READ_TOOLS = [
        'assistant.catalog' => 'workflow catalogue',
        'assistant.workspace_scan' => 'workspace scan',
        'data.query' => 'permission-scoped dataset',
        'reports.insight_brief' => 'intelligence briefing',
        'reports.kpi_brief' => 'KPI brief',
        'reports.school_brief' => 'school brief',
        'academics.coverage_review' => 'curriculum coverage review',
        'academics.learning_gap_review' => 'learning-gap review',
        'admissions.placement_review' => 'admissions placement review',
        'attendance.exception_summary' => 'attendance exception summary',
        'attendance.lateness_pattern_review' => 'lateness pattern review',
        'boarding.exception_summary' => 'boarding exception summary',
        'transport.operations_summary' => 'transport operations summary',
        'inventory.replenishment_review' => 'inventory replenishment review',
        'catering.consumption_review' => 'catering consumption review',
        'maintenance.facilities_review' => 'facilities maintenance review',
        'health.welfare_review' => 'health welfare review',
        'activities.resource_review' => 'activities resource review',
        'activities.library_review' => 'library review',
        'staff.hr_review' => 'staff HR review',
        'counseling.welfare_review' => 'counselling welfare review',
        'learners.support_planning' => 'learner support planning',
        'finance.reconciliation_review' => 'finance reconciliation review',
        'system.operations_brief' => 'operations brief',
        'system.security_brief' => 'security brief',
    ];

    /** Tools that prepare a governed draft for human approval. */
    private const DRAFT_TOOLS = [
        'academics.scheme_draft' => 'CBC scheme draft',
        'academics.lesson_plan_draft' => 'lesson plan draft',
        'academics.assessment_draft' => 'assessment draft',
        'academics.rubric_draft' => 'rubric draft',
        'academics.exam_timetable_planning' => 'exam timetable plan',
        'admissions.application_followup_draft' => 'admissions follow-up draft',
        'admissions.interview_preparation' => 'admissions interview preparation',
        'communications.parent_message_draft' => 'parent message draft',
        'drafts.create' => 'message draft',
    ];

    /**
     * Governed report tools. They answer a staff question by running ONE
     * authorized governed report through the same NLQ engine the browser
     * uses, so the assistant and the reports screen can never disagree about
     * which rows an operator may see.
     */
    private const REPORT_TOOLS = [
        'reports.nlq' => 'governed report',
        'reports.aggregate' => 'governed aggregation',
        'charts.generate' => 'chart source report',
    ];

    /**
     * Row grains that describe a PERSON rather than an aggregate.
     *
     * A report row may only leave PHP for the engine when one row is a class,
     * a term, a month, a channel, a category or similar. A row that IS a
     * learner, a member of staff or a pastoral visit is withheld: the model
     * never receives a record about a child.
     */
    private const PERSON_GRAIN_TOKENS = [
        'learner', 'student', 'pupil', 'child', 'staff', 'teacher', 'parent',
        'guardian', 'visit', 'roster', 'profile', 'individual', 'personal',
        'individual record',
    ];

    /**
     * Column names that carry a personal identifier even in an aggregate row.
     *
     * Deliberately a list of exact column names rather than a loose substring
     * match: `class_name` is a label, `student_name` is a person. A loose
     * pattern would strip the class labels the assistant needs to explain a
     * figure, and keeping them costs no privacy.
     */
    private const PERSON_COLUMN_PATTERN = '/^(full_?name|first_?name|last_?name|surname|middle_?name|student_?name|learner_?name|pupil_?name|child_?name|staff_?name|teacher_?name|parent_?name|guardian_?name|admission_?(no|number|id)|registration_?(no|number)|phone|phone_?number|mobile|email|email_?address|id_?(no|number)|national_?id|tax_?id|dob|date_?of_?birth|birth_?date|guardian|guardian_?name|parent|parent_?name|student_?id|learner_?id|pupil_?id|staff_?id|teacher_?id|user_?id|person_?id)$/i';

    /** Bounded per-domain governed readers. */
    private const DOMAIN_READERS = [
        'academics' => ['service' => \App\API\Services\SubjectTeacherAnalyticsService::class, 'method' => 'getFullDashboardData', 'label' => 'academic planning'],
        'attendance' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getDailyAttendanceStats', 'label' => 'attendance'],
        'boarding' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getDailyAttendanceStats', 'label' => 'boarding'],
        'admissions' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getStudentAdmissionsStats', 'label' => 'admissions'],
        'staff' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getTeachingStaffStats', 'label' => 'staffing'],
        'transport' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getFullDashboardData', 'label' => 'transport'],
        'inventory' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getFullDashboardData', 'label' => 'inventory'],
        'maintenance' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getFullDashboardData', 'label' => 'facilities'],
        'activities' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getFullDashboardData', 'label' => 'activities'],
        'communications' => ['service' => \App\API\Services\SchoolAdminAnalyticsService::class, 'method' => 'getAnnouncementsStats', 'label' => 'communications'],
    ];

    /**
     * Execute one governed tool for a recorded operator.
     *
     * @param list<string>        $permissions CURRENT server-resolved permissions
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function execute(int $userId, array $permissions, string $tool, array $input, string $requestId = '', ?PDO $pdo = null): array
    {
        if ($userId < 1) {
            throw new DomainException('A recorded operator is required.', 422);
        }
        if ($permissions === []) {
            throw new DomainException('The recorded operator permissions could not be resolved.', 403);
        }

        // system.* tools run inside the engine against allowlisted local files;
        // web.* tools are outbound public reads. Neither touches school data,
        // so neither is reproduced in the governed data layer.
        if (str_starts_with($tool, 'system.') || str_starts_with($tool, 'web.')) {
            return [
                'status' => 'unavailable',
                'message' => 'that tool is executed by the assistant engine, not the governed data layer',
            ];
        }

        if (WorkflowRegistry::resolve((string) $tool) === null) {
            return ['status' => 'unknown_tool'];
        }

        $context = [
            'user_id' => $userId,
            'permissions' => $permissions,
            'effective_permissions' => $permissions,
            'audience' => 'staff',
            'request_id' => mb_substr($requestId, 0, 100),
        ];

        // Gate 2: the workflow's own permission gate. A missing grant is a
        // plain denial, so the assistant says so honestly rather than failing.
        try {
            (new WorkflowAuthorization())->authorize((string) $tool, $context);
        } catch (DomainException $e) {
            return [
                'status' => 'not_authorized',
                'message' => 'this workspace does not grant access to that information',
            ];
        }

        try {
            if (isset(self::DRAFT_TOOLS[(string) $tool])) {
                return $this->queueDraft((string) $tool, $context, (array) $input);
            }
            if (isset(self::REPORT_TOOLS[(string) $tool])) {
                return $this->readReport((string) $tool, $context, (array) $input, $pdo);
            }
            return $this->readGoverned((string) $tool, $context, (array) $input, $pdo);
        } catch (DomainException $e) {
            return ['status' => 'not_authorized', 'message' => 'access could not be confirmed'];
        } catch (Throwable $e) {
            Logger::error('ai_generation', '[AgentToolExecutor] ' . $tool . ' failed', [
                'error_class' => get_class($e),
                'reason' => mb_substr($e->getMessage(), 0, 200),
            ]);
            return ['status' => 'unavailable', 'message' => 'that information could not be retrieved right now'];
        }
    }

    /**
     * Queue a governed draft for human approval.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function queueDraft(string $tool, array $context, array $input): array
    {
        $metadata = [
            'subject_type' => 'agent_tool',
            'grade_level' => mb_substr(trim((string) ($input['grade_level'] ?? '')), 0, 80),
            'learning_area_id' => (int) ($input['learning_area_id'] ?? 0),
            'report_code' => (string) ($input['report_code'] ?? ''),
            'channel' => mb_substr(trim((string) ($input['channel'] ?? '')), 0, 40),
            'audience' => mb_substr(trim((string) ($input['audience'] ?? '')), 0, 40),
            'scope' => mb_substr(trim((string) ($input['scope'] ?? '')), 0, 40),
        ];
        $queued = (new DraftRepository())->queue($tool, $context, $input, $metadata);
        return [
            'status' => 'queued',
            'workflow_id' => (string) $queued['workflow_id'],
            'draft_job_id' => (string) ($queued['job_id'] ?? ''),
            'action_level' => (string) ($queued['action_level'] ?? 'prepare'),
            'summary' => (self::DRAFT_TOOLS[$tool] ?? 'Draft') . ' queued for your review',
            'next_steps' => [
                'Open the drafts inbox to review and approve it.',
                'Nothing is applied or sent until you approve.',
            ],
        ];
    }

    /**
     * Run one authorized governed report for the recorded operator.
     *
     * The same NLQ engine the browser uses does the work, so the assistant and
     * the reports screen resolve the same report for the same question. The
     * difference is the last step: rows are only released to the engine when
     * one row is an aggregate (a class, a term, a month), never when a row is
     * a person. A person-grain report still answers with its summary.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function readReport(string $tool, array $context, array $input, ?PDO $pdo): array
    {
        if ($pdo === null) {
            return [
                'status' => 'unavailable',
                'message' => 'the governed report connection is unavailable',
            ];
        }
        $question = trim((string) ($input['question'] ?? ''));
        if ($question === '' || mb_strlen($question) > 500) {
            return [
                'status' => 'unavailable',
                'message' => 'a data question of up to 500 characters is required',
            ];
        }
        try {
            $service = new NlqQueryService();
            $result = $tool === 'reports.aggregate'
                ? $service->aggregate($pdo, $context, $question, (string) ($input['agg_type'] ?? 'count'))
                : $service->ask($pdo, $context, $question);
        } catch (DomainException $e) {
            return ['status' => 'not_authorized', 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Logger::error('ai_generation', '[AgentToolExecutor] ' . $tool . ' failed', [
                'error_class' => get_class($e),
                'reason' => mb_substr($e->getMessage(), 0, 200),
            ]);
            return ['status' => 'unavailable', 'message' => 'that information could not be retrieved right now'];
        }
        return $this->normaliseReportResult($result);
    }

    /**
     * Shape a governed report result for the engine, applying the row gate.
     *
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function normaliseReportResult(array $result): array
    {
        $status = (string) ($result['status'] ?? 'unavailable');
        if ($status !== 'answered') {
            return [
                'status' => $status === 'cannot_answer' ? 'not_authorized' : $status,
                'message' => mb_substr((string) ($result['message'] ?? 'that information is not available for this workspace'), 0, 200),
            ];
        }
        $answer = is_array($result['answer'] ?? null) ? $result['answer'] : [];
        $grain = mb_strtolower(trim((string) ($answer['grain'] ?? '')));
        $rows = is_array($answer['rows'] ?? null) ? array_values($answer['rows']) : [];

        $withheld = false;
        if ($this->grainDescribesAPerson($grain)) {
            $rows = [];
            $withheld = true;
        } else {
            $rows = $this->stripPersonalColumns($rows);
        }

        return [
            'status' => 'answered',
            'report_code' => mb_substr((string) ($answer['report_code'] ?? ''), 0, 100),
            'report_title' => mb_substr((string) ($answer['report_title'] ?? ''), 0, 200),
            'row_count' => (int) ($answer['row_count'] ?? count($rows)),
            'summary' => is_array($answer['summary'] ?? null) ? array_slice($answer['summary'], 0, 8) : [],
            'columns' => is_array($answer['columns'] ?? null) ? array_slice($answer['columns'], 0, 20) : [],
            'rows' => array_slice($rows, 0, 25),
            'preview_limited' => (bool) ($answer['preview_limited'] ?? false),
            'explanation' => mb_substr((string) ($result['explanation'] ?? ''), 0, 600),
            'effective_scope' => is_array($answer['effective_scope'] ?? null) ? $answer['effective_scope'] : [],
            // The engine is told that the detail was withheld so it can say so
            // honestly instead of inventing a row-level conclusion.
            'rows_withheld' => $withheld,
        ];
    }

    /** True when one row of the report IS a person rather than an aggregate. */
    private function grainDescribesAPerson(string $grain): bool
    {
        if ($grain === '') {
            // An unknown grain is treated as personal: fail closed.
            return true;
        }
        foreach (self::PERSON_GRAIN_TOKENS as $token) {
            if (str_contains($grain, $token)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Drop any column whose name suggests a personal identifier, so an
     * aggregate row can never smuggle a name across the boundary.
     *
     * @param array<int,mixed> $rows
     * @return array<int,array<string,mixed>>
     */
    private function stripPersonalColumns(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $kept = [];
            foreach ($row as $column => $value) {
                if (!is_string($column) || preg_match(self::PERSON_COLUMN_PATTERN, $column) === 1) {
                    continue;
                }
                if (!is_scalar($value) && $value !== null) {
                    continue;
                }
                $kept[$column] = is_string($value) ? mb_substr($value, 0, 120) : $value;
            }
            if ($kept !== []) {
                $clean[] = $kept;
            }
        }
        return $clean;
    }

    /**
     * Read governed school data for a summary tool.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function readGoverned(string $tool, array $context, array $input, ?PDO $pdo = null): array
    {
        $userId = (int) $context['user_id'];

        if ($tool === 'data.query') {
            $service = new PolarsDatasetService($pdo);
            $dataset = strtolower(trim((string) ($input['dataset'] ?? '')));
            if ($dataset === '') {
                return [
                    'status' => 'ok',
                    'datasets' => $service->datasets(array_values(array_map('strval', (array) $context['permissions']))),
                    'note' => 'Choose a dataset name, then analyse its rows with data.polars.',
                ];
            }
            return $service->rows($dataset, array_values(array_map('strval', (array) $context['permissions'])));
        }

        if ($tool === 'assistant.workspace_scan') {
            $pdo = $pdo ?? \App\Database\Database::getInstance()->getConnection();
            return (new WorkspaceBriefingScanner())->scan(
                $pdo,
                $userId,
                array_values(array_map('strval', (array) $context['permissions'])),
                (string) ($input['route'] ?? '')
            );
        }

        if ($tool === 'assistant.catalog') {
            return [
                'status' => 'ok',
                'workflows' => (new WorkflowAuthorization())->describeForContext(
                    array_values(array_map('strval', (array) $context['permissions'])),
                    (string) ($input['route'] ?? ''),
                    (string) ($input['module'] ?? 'dashboard'),
                    'staff',
                    []
                ),
            ];
        }

        if ($tool === 'reports.insight_brief') {
            $cadence = in_array((string) ($input['cadence'] ?? ''), ['daily', 'weekly', 'term'], true)
                ? (string) $input['cadence']
                : 'daily';
            $brief = (new InsightScheduler())->pageLoad(
                $userId,
                array_values(array_map('strval', (array) $context['permissions'])),
                (string) $context['request_id']
            );
            $status = is_array($brief) ? (string) ($brief['status'] ?? '') : '';
            if ($status === 'ready') {
                return ['status' => 'ready', 'cadence' => $cadence, 'briefing' => $brief['briefing'] ?? null];
            }
            return ['status' => 'generating', 'cadence' => $cadence];
        }

        $workflow = WorkflowRegistry::resolve($tool) ?? [];
        $domain = (string) ($workflow['domain'] ?? '');
        // Transport is a deterministic, no-approval insight: the same bounded
        // aggregate builder that feeds the transport page also feeds the
        // proactive briefing, so both show the same authorized signals.
        if ($domain === 'transport' || str_contains(strtolower((string) ($input['route'] ?? '')), 'transport')) {
            return $this->transportFinding($context);
        }
        $reader = self::DOMAIN_READERS[$domain] ?? null;
        if ($reader === null) {
            return [
                'status' => 'ok',
                'workflow_id' => $tool,
                'summary' => 'Ask a specific question and the assistant will run the matching governed report for your workspace.',
            ];
        }

        /** @var object $service */
        $service = new ($reader['service'])();
        $method = $reader['method'];
        $data = $service->$method();

        // Bounded, aggregate-first output. Row detail stays in the governed
        // reports; the assistant receives counts and signals only.
        return [
            'status' => 'answered',
            'domain' => $domain,
            'summary' => (string) ($data['summary'] ?? self::summarise($data, (string) $reader['label'])),
            'metrics' => array_slice((array) ($data['metrics'] ?? $data), 0, 8),
        ];
    }

    /**
     * Deterministic transport signals for the proactive briefing. No provider
     * call, no draft, no approval: figures come from the same allowlisted
     * aggregate builder used by the transport insight card.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function transportFinding(array $context): array
    {
        try {
            $api = new \App\API\Modules\transport\TransportAPI();
            $response = $api->getAiOperationsSummaryInput();
            if (($response['success'] ?? false) !== true) {
                return ['status' => 'unavailable', 'summary' => 'Transport signals are temporarily unavailable.'];
            }
            $signals = is_array($response['data'] ?? null) ? $response['data'] : [];
        } catch (Throwable $e) {
            return ['status' => 'unavailable', 'summary' => 'Transport signals are temporarily unavailable.'];
        }

        $int = static fn(string $key): int => max(0, (int) ($signals[$key] ?? 0));
        $findings = [];
        if ($int('overdue_service_count') > 0) {
            $findings[] = $int('overdue_service_count') . ' active vehicle(s) are overdue for service.';
        }
        if ($int('expired_insurance_count') > 0) {
            $findings[] = $int('expired_insurance_count') . ' active vehicle(s) have expired insurance.';
        }
        if ($int('routes_without_vehicle_count') > 0) {
            $findings[] = $int('routes_without_vehicle_count') . ' active route(s) have no vehicle assigned.';
        }
        if ($int('active_vehicle_count') > $int('assigned_passenger_count') && $int('assigned_passenger_count') === 0) {
            $findings[] = 'No learners are assigned to transport yet.';
        }
        if ($findings === []) {
            $findings[] = 'No transport attention items from the current aggregate signals.';
        }

        return [
            'status' => 'answered',
            'domain' => 'transport',
            'summary' => sprintf(
                'Transport: %d active route(s), %d active vehicle(s), %d assigned passenger(s), %d available seats.',
                $int('active_route_count'),
                $int('active_vehicle_count'),
                $int('assigned_passenger_count'),
                $int('total_active_capacity')
            ),
            'metrics' => [
                'active_routes' => $int('active_route_count'),
                'active_vehicles' => $int('active_vehicle_count'),
                'assigned_passengers' => $int('assigned_passenger_count'),
                'available_seats' => $int('total_active_capacity'),
            ],
            'findings' => array_slice($findings, 0, 5),
            'requires_approval' => false,
            'note' => 'Advisory only; the transport register remains authoritative.',
        ];
    }

    /**
     * Turn a governed reader payload into one bounded sentence so the engine
     * never has to improvise, and never sees identifiers it should not.
     *
     * @param array<string,mixed> $data
     */
    private static function summarise(array $data, string $label): string
    {
        $count = 0;
        foreach ($data as $value) {
            if (is_array($value)) {
                $count += count($value);
            } elseif ($value !== null && $value !== '') {
                $count++;
            }
        }
        return 'The ' . $label . ' workspace reports ' . $count . ' current signal(s).';
    }
}
