<?php

namespace App\API\Services;

use App\API\Modules\reports\ReportsAPI;
use App\API\Includes\FileLogger;
use DomainException;
use PDO;

/** Queues and executes governed report explanations as background AI work. */
final class AiAnalyticsInsightService
{
    public function queue(string $reportCode, array $filters, array $user, string $requestId): array
    {
        $reportCode = strtoupper(trim($reportCode));
        if (!preg_match('/^[A-Z][A-Z0-9_]{2,99}$/', $reportCode)) {
            throw new DomainException('A valid report code is required.', 422);
        }
        if (count($filters) > 30) {
            throw new DomainException('Too many report filters were supplied.', 422);
        }
        foreach ($filters as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,79}$/', $key)) {
                throw new DomainException('A report filter name is invalid.', 422);
            }
            if (is_array($value)) {
                if (count($value) > 20) {
                    throw new DomainException('A report filter contains too many values.', 422);
                }
                foreach ($value as $item) {
                    if (!is_scalar($item)) {
                        throw new DomainException('Report filters must contain scalar values.', 422);
                    }
                }
            } elseif (!is_scalar($value)) {
                throw new DomainException('Report filters must contain scalar values.', 422);
            }
        }

        $actor = [
            'id' => (int) ($user['id'] ?? $user['user_id'] ?? 0),
            'roles' => array_values((array) ($user['roles'] ?? [])),
            'permissions' => array_values((array) ($user['permissions'] ?? [])),
            'effective_permissions' => array_values((array) ($user['effective_permissions'] ?? [])),
        ];
        if ($actor['id'] < 1) {
            throw new DomainException('An authenticated report owner is required.', 401);
        }
        (new AiWorkflowService())->authorize('reports.kpi_brief', [
            'user_id' => $actor['id'],
            'permissions' => array_values(array_unique(array_merge(
                $actor['effective_permissions'],
                $actor['permissions']
            ))),
        ]);

        $jobId = JobQueue::push('ai.analytics.insight', [
            'report_code' => $reportCode,
            'filters' => $filters,
            'user' => $actor,
            'request_id' => substr($requestId, 0, 100),
        ], 0, 3, 60);

        FileLogger::write('ai_generation', [
            'type' => 'analytics_insight_queued',
            'job_id' => $jobId,
            'report_code' => $reportCode,
            'operator_id' => $actor['id'],
            'request_id' => substr($requestId, 0, 100),
        ]);

        return [
            'job_id' => $jobId,
            'workflow_id' => 'reports.kpi_brief',
            'status' => 'queued',
            'execution' => 'background',
        ];
    }

    public function execute(PDO $pdo, array $payload): array
    {
        $reportCode = strtoupper(trim((string) ($payload['report_code'] ?? '')));
        $user = isset($payload['user']) && is_array($payload['user']) ? $payload['user'] : [];
        $filters = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];
        $requestId = (string) ($payload['request_id'] ?? 'ai-analytics-insight');
        if ($reportCode === '' || (int) ($user['id'] ?? 0) < 1) {
            throw new DomainException('Background analytics insight payload is incomplete.', 422);
        }

        // ReportsAPI applies report capability and row-scope authorization again
        // in the worker. Queue execution never grants broader access.
        $result = (new ReportsAPI())->executeGoverned($reportCode, $filters, $user, $requestId);
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
        $input = [
            'report_title' => (string) ($result['report']['title'] ?? $reportCode),
            'report_code' => $reportCode,
            'decision_purpose' => (string) ($result['report']['decision_purpose'] ?? ''),
            'as_of' => (string) ($result['as_of'] ?? ''),
            'row_count' => (string) ((int) ($result['row_count'] ?? 0)),
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'warnings' => json_encode(array_map(
                static fn($warning): string => is_array($warning)
                    ? (string) ($warning['message'] ?? '')
                    : (string) $warning,
                $warnings
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $draft = (new AiDraftService())->create(
            $pdo,
            'reports.kpi_brief',
            [
                'user_id' => (int) $user['id'],
                'permissions' => array_values(array_unique(array_merge(
                    (array) ($user['effective_permissions'] ?? []),
                    (array) ($user['permissions'] ?? [])
                ))),
                'request_id' => $requestId,
            ],
            $input,
            [
                'subject_type' => 'governed_report',
                'subject_id' => (int) ($result['run']['id'] ?? 0),
                'scope' => 'governed_report_run',
                'report_code' => $reportCode,
                'execution' => 'background',
            ]
        );

        FileLogger::write('ai_generation', [
            'type' => 'analytics_insight_created',
            'job_report_code' => $reportCode,
            'draft_id' => (int) ($draft['draft_id'] ?? 0),
            'report_run_id' => (int) ($result['run']['id'] ?? 0),
            'operator_id' => (int) $user['id'],
        ]);
        return $draft;
    }
}
