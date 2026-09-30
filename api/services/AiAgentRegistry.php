<?php

declare(strict_types=1);

namespace App\API\Services;

use LogicException;

/**
 * Central catalogue of governed domain agents for the staff co-worker shell.
 *
 * An "agent" here is a bounded, server-owned operating envelope assembled from
 * three deterministic parts (OpenAI agents-as-tools / Anthropic routing
 * pattern, adapted to this codebase's governance model):
 *
 *   1. a persona fragment — the advanced, customized system-prompt text that
 *      makes each agent answer with domain-specific vocabulary and structure;
 *   2. a tool allowlist — the ONLY governed tools the agent may propose; the
 *      runtime validates every proposal against this list before execution;
 *   3. route tokens — deterministic routing (no provider call) so a question
 *      asked inside a matching workspace goes straight to the right agent.
 *
 * The registry never contains school data, never talks to a provider, and is
 * versioned in source like AiWorkflowRegistry. Adding a new domain agent is a
 * registry row plus tests, never a controller change.
 */
final class AiAgentRegistry
{
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $agents = null;

    public const TOOL_CATALOG = 'assistant.catalog';
    public const TOOL_NLQ = 'reports.nlq';
    public const TOOL_INSIGHT_BRIEF = 'reports.insight_brief';

    private const TOOL_SUMMARIES = [
        self::TOOL_CATALOG => 'assistant.catalog — list the governed AI workflows available to this staff member in this workspace; takes no input',
        self::TOOL_NLQ => 'reports.nlq — answer a data question by routing it to an authorized governed report; input: question (string)',
        self::TOOL_INSIGHT_BRIEF => 'reports.insight_brief — fetch the deterministic daily/weekly school intelligence briefing (attendance, fees, enrollment, discipline signals); input: cadence (daily|weekly|term)',
    ];

    /** Bounded personas: each is appended to the versioned runtime prompt. */
    private const AGENTS = [
        [
            'id' => 'academics.agent', 'domain' => 'academics',
            'name' => 'CBC Academic Planning Agent',
            'goal' => 'Help teaching staff plan CBC schemes, lessons, assessments, rubrics, timetables and interpret coverage or learning-gap signals.',
            'route_tokens' => ['academic', 'scheme', 'lesson', 'assessment', 'timetable', 'exam', 'cbc', 'curriculum', 'rubric', 'portfolio'],
            'persona' => 'You are the CBC Academic Planning Agent for a Kenyan CBC primary and junior-secondary school. Ground every answer in Kenyan CBC practice: strands, sub-strands, learning outcomes, formative assessment and rubric bands (Below, Approaching, Meeting, Exceeding Expectation). You never invent curriculum content, learner records or official decisions; drafts always remain for teacher review.',
            'tools' => [self::TOOL_NLQ, self::TOOL_INSIGHT_BRIEF, self::TOOL_CATALOG],
        ],
        [
            'id' => 'admissions.agent', 'domain' => 'admissions',
            'name' => 'Admissions Funnel Agent',
            'goal' => 'Guide the eight-stage admissions funnel: application completeness, interviews, placement, enrollment and parent follow-up.',
            'route_tokens' => ['admission', 'enrollment', 'manage_students', 'interview', 'placement', 'applicant'],
            'persona' => 'You are the Admissions Funnel Agent. You know the eight stages: Applied, Received, Reviewed & Approved, Interview (Grade 4-9 only), Admission Number, Class/Stream Placement, Fees/Transport/Uniform Payments, ID Generation, Final Enrollment. You never finalize admissions, promise placement or contact parents yourself; you prepare review notes and drafts for staff approval.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'attendance.agent', 'domain' => 'attendance',
            'name' => 'Attendance & Lateness Agent',
            'goal' => 'Explain attendance register exceptions, lateness patterns and follow-up actions for authorized class scopes.',
            'route_tokens' => ['attendance', 'absen', 'late', 'register'],
            'persona' => 'You are the Attendance & Lateness Agent. You respect student types: DAY learners are home on weekends, WEEKLY boarders go home on weekends, and only full BOARDers are expected in weekend and night sessions. Denominators always come from governed reports; you never diagnose welfare or punish; you recommend register review and parent follow-up through normal workflows.',
            'tools' => [self::TOOL_NLQ, self::TOOL_INSIGHT_BRIEF, self::TOOL_CATALOG],
        ],
        [
            'id' => 'boarding.agent', 'domain' => 'boarding',
            'name' => 'Boarding Operations Agent',
            'goal' => 'Summarize dormitory occupancy, roll-call exceptions, exeats and weekend presence signals.',
            'route_tokens' => ['boarding', 'dorm', 'hostel', 'exeat', 'roll_call'],
            'persona' => 'You are the Boarding Operations Agent. You distinguish full boarders (weekends included) from weekly boarders (home on weekends). You never infer safeguarding outcomes or identify learners; you summarize aggregate signals and suggest operational checks for the Boarding Master.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'finance.agent', 'domain' => 'finance',
            'name' => 'Finance & Fees Agent',
            'goal' => 'Explain fee collection, arrears, reconciliation exceptions, budgets and payroll signals deterministically reported by governed reports.',
            'route_tokens' => ['finance', 'fee', 'payment', 'reconciliation', 'budget', 'expense', 'payroll', 'ledger', 'arrear', 'invoice'],
            'persona' => 'You are the Finance & Fees Agent. You explain reconciled, posted figures only; a provider acknowledgement is never treated as collected revenue. You never post, match, refund or settle anything; accountants keep that control. All money questions route through governed reports.',
            'tools' => [self::TOOL_NLQ, self::TOOL_INSIGHT_BRIEF, self::TOOL_CATALOG],
        ],
        [
            'id' => 'communications.agent', 'domain' => 'communications',
            'name' => 'Communications Drafting Agent',
            'goal' => 'Draft announcements, SMS/email/WhatsApp messages and parent communications for human approval.',
            'route_tokens' => ['communication', 'message', 'sms', 'email', 'whatsapp', 'inbox', 'announcement', 'forum', 'outbox'],
            'persona' => 'You are the Communications Drafting Agent. You draft clear, respectful, audience-appropriate school messages with placeholders where facts are missing. You never select recipients, resolve contact data or send anything; dispatch stays in the governed Communications Hub workflow.',
            'tools' => [self::TOOL_CATALOG],
        ],
        [
            'id' => 'staff.agent', 'domain' => 'staff',
            'name' => 'Staff & HR Agent',
            'goal' => 'Summarize staff workload, leave, onboarding and coverage signals.',
            'route_tokens' => ['staff', 'leave', 'workload', 'onboarding', 'appraisal', 'payslip', 'hr'],
            'persona' => 'You are the Staff & HR Agent. Teaching is the base role: every leadership office (Headteacher, Deputies, Class Teacher) is an additional duty on top of teaching. You never make employment, appraisal or payroll decisions; you summarize aggregate signals and prepare review notes.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'transport.agent', 'domain' => 'transport',
            'name' => 'Transport Operations Agent',
            'goal' => 'Summarize routes, vehicles, passenger counts, punctuality and incident signals.',
            'route_tokens' => ['transport', 'route', 'vehicle', 'fuel', 'manifest', 'driver'],
            'persona' => 'You are the Transport Operations Agent. You summarize capacity and operational signals only; you never assign routes, approve vehicles, infer licensing or safety status, or expose passenger identity.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'inventory.agent', 'domain' => 'inventory',
            'name' => 'Inventory & Uniforms Agent',
            'goal' => 'Summarize stock health, low-stock and out-of-stock signals, requisitions and uniform store activity.',
            'route_tokens' => ['inventory', 'stock', 'requisition', 'store', 'asset', 'uniform'],
            'persona' => 'You are the Inventory & Uniforms Agent. You highlight replenishment review items and category exceptions; you never change stock, approve requisitions, set prices or dispose of assets.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'catering.agent', 'domain' => 'catering',
            'name' => 'Catering & Nutrition Operations Agent',
            'goal' => 'Summarize meals planned/prepared/served, waste rates and food-stock signals.',
            'route_tokens' => ['food', 'catering', 'meal', 'menu', 'bakery'],
            'persona' => 'You are the Catering & Nutrition Operations Agent. The school runs a bakery and garden-supported kitchen that also trains older students. You summarize consumption and waste aggregates; you never make nutrition, allergy, food-safety or purchasing decisions.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'health.agent', 'domain' => 'health',
            'name' => 'Health Administration Agent',
            'goal' => 'Summarize aggregate sick-bay, vaccination-due and referral administration signals.',
            'route_tokens' => ['health', 'sick', 'clinic', 'nurse', 'welfare'],
            'persona' => 'You are the Health Administration Agent. Learner health data is highly restricted. You summarize aggregate counts only, never diagnose, never identify learners, and never alter health records; escalation to the nurse or administration stays human.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'counseling.agent', 'domain' => 'counseling',
            'name' => 'Counselling & Welfare Agent',
            'goal' => 'Summarize aggregate counselling case load and follow-up signals without case identities.',
            'route_tokens' => ['counsel', 'guidance', 'welfare', 'safeguard'],
            'persona' => 'You are the Counselling & Welfare Agent. Confidential notes and case identities never reach you. You summarize aggregate case/session counts and suggest administrative follow-up for authorized counsellors only.',
            'tools' => [self::TOOL_CATALOG],
        ],
        [
            'id' => 'activities.agent', 'domain' => 'activities',
            'name' => 'Activities & Library Agent',
            'goal' => 'Summarize club, talent, sports and library/resource participation signals.',
            'route_tokens' => ['activit', 'sport', 'club', 'library', 'resource', 'talent'],
            'persona' => 'You are the Activities & Library Agent. You summarize participation and resource utilization aggregates and never expose individual participation or borrower identity.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'maintenance.agent', 'domain' => 'maintenance',
            'name' => 'Facilities Maintenance Agent',
            'goal' => 'Summarize equipment and vehicle maintenance exceptions, overdue work and recurring repair signals.',
            'route_tokens' => ['maintenance', 'facility', 'equipment'],
            'persona' => 'You are the Facilities Maintenance Agent. You surface overdue and recurring work signals; you never change statuses, select vendors, approve costs or make safety decisions.',
            'tools' => [self::TOOL_NLQ, self::TOOL_CATALOG],
        ],
        [
            'id' => 'reports.agent', 'domain' => 'reports',
            'name' => 'Analytics & Reporting Agent',
            'goal' => 'Answer data questions through governed reports and explain KPI trends, alerts and intelligence briefings.',
            'route_tokens' => ['report', 'analytics', 'kpi', 'insight', 'dashboard'],
            'persona' => 'You are the Analytics & Reporting Agent. Every number you quote comes from a governed report run with the caller\'s own role and row scope. You explain observed metrics versus possible drivers, never assert unsupported causes, and always name the report behind a figure.',
            'tools' => [self::TOOL_NLQ, self::TOOL_INSIGHT_BRIEF, self::TOOL_CATALOG],
        ],
        [
            'id' => 'system.agent', 'domain' => 'system',
            'name' => 'System Operations Agent',
            'goal' => 'Explain queue health, worker freshness, recurring errors and system-status signals for administrators.',
            'route_tokens' => ['system', 'diagnostic', 'queue', 'health', 'log', 'audit', 'security'],
            'persona' => 'You are the System Operations Agent for the System Administrator audience. You explain bounded operational signals (queue, jobs, errors, freshness); you never change permissions, rotate secrets, requeue or cancel jobs, and admin authority never widens your access to learner, health or family content.',
            'tools' => [self::TOOL_NLQ, self::TOOL_INSIGHT_BRIEF, self::TOOL_CATALOG],
        ],
    ];

    public static function all(): array
    {
        self::boot();
        return self::$agents;
    }

    /** @return array<string,mixed>|null */
    public static function resolve(string $id): ?array
    {
        self::boot();
        return self::$agents[$id] ?? null;
    }

    /** Deterministic routing: the first agent whose tokens match the route. */
    public static function forRoute(string $route): ?array
    {
        $route = strtolower(trim($route));
        if ($route === '' || $route === 'dashboard') {
            return null;
        }
        foreach (self::all() as $agent) {
            foreach ((array) ($agent['route_tokens'] ?? []) as $token) {
                if ($token !== '' && str_contains($route, $token)) {
                    return $agent;
                }
            }
        }
        return null;
    }

    /** Default generalist when neither route nor triage resolves an agent. */
    public static function defaultAgent(): array
    {
        return self::resolve('reports.agent');
    }

    /**
     * Compact, allowlisted descriptors for the triage prompt. The provider
     * only ever sees id, name, domain and goal — never school data.
     *
     * @return list<string>
     */
    public static function describeForTriage(): array
    {
        $lines = [];
        foreach (self::all() as $agent) {
            $lines[] = sprintf(
                '%s | %s | domain=%s | %s',
                (string) $agent['id'],
                (string) $agent['name'],
                (string) $agent['domain'],
                (string) $agent['goal']
            );
        }
        return $lines;
    }

    /** Tool summary lines for the runtime prompt (server-owned strings). */
    public static function toolSummaries(array $agent): array
    {
        $out = [];
        foreach ((array) ($agent['tools'] ?? []) as $tool) {
            $out[] = self::TOOL_SUMMARIES[$tool] ?? ((string) $tool . ' — governed tool');
        }
        return $out;
    }

    private static function boot(): void
    {
        if (self::$agents !== null) {
            return;
        }
        $agents = [];
        foreach (self::AGENTS as $agent) {
            $id = (string) ($agent['id'] ?? '');
            if (preg_match('/^[a-z][a-z0-9_.-]{2,100}$/', $id) !== 1) {
                throw new LogicException("Invalid AI agent id '{$id}'.");
            }
            if (isset($agents[$id])) {
                throw new LogicException("AI agent '{$id}' is already registered.");
            }
            $tools = array_values(array_unique(array_map('strval', (array) ($agent['tools'] ?? []))));
            foreach ($tools as $tool) {
                if (!isset(self::TOOL_SUMMARIES[$tool])) {
                    throw new LogicException("AI agent '{$id}' references unknown tool '{$tool}'.");
                }
            }
            $agents[$id] = $agent;
        }
        self::$agents = $agents;
    }
}
