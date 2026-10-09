<?php
declare(strict_types=1);

namespace App\API\Services;

/**
 * Builds the shared response contract for all governed report executions.
 */
final class GovernedReportResponseBuilder
{
    public function build(
        array $definition,
        array $run,
        array $parameters,
        array $scope,
        array $metrics,
        $payload,
        array $warnings = []
    ): array {
        $rows = $this->rows($payload);
        $summary = $this->summary($payload);
        $generatedAt = gmdate('c');

        return [
            'report' => [
                'id' => (int) $definition['id'],
                'code' => (string) $definition['code'],
                'version' => (int) $definition['version'],
                'title' => (string) $definition['title'],
                'description' => $definition['description'] ?? null,
                'decision_purpose' => (string) $definition['decision_purpose'],
                'domain' => (string) $definition['domain'],
                'category' => (string) $definition['category'],
                'grain' => (string) $definition['grain'],
                'sensitivity' => (string) $definition['sensitivity'],
                // Data-currency only. The source type and source name (a
                // database view/service identifier) are internal schema detail
                // and must not be sent to a browser: it discloses how the report
                // is built. `as_of` at the top level already states when the
                // figures were produced, which is what a reader needs.
                'data_currency' => [
                    'freshness_minutes' => (int) $definition['freshness_minutes'],
                ],
            ],
            'run' => $run,
            'generated_at' => $generatedAt,
            'as_of' => $generatedAt,
            'filters' => $parameters,
            'effective_scope' => $scope,
            'columns' => $definition['columns'] ?? [],
            'rows' => $rows,
            'row_count' => count($rows),
            'summary' => $summary,
            'payload' => $rows === [] && $summary === [] ? $payload : null,
            'metrics' => $metrics,
            'visualizations' => $definition['visualizations'] ?? [],
            'permitted_exports' => !empty($definition['capabilities']['export'])
                ? ($definition['export_formats'] ?? [])
                : [],
            'warnings' => array_values($warnings),
            'pagination' => null,
        ];
    }

    public function rowCount($payload): int
    {
        return count($this->rows($payload));
    }

    public function summary($payload): array
    {
        if (!is_array($payload)) {
            return [];
        }
        foreach (['summary', 'totals', 'kpis'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }
        if ($this->isList($payload)) {
            return [];
        }
        $hasNestedArray = false;
        foreach ($payload as $value) {
            if (is_array($value)) {
                $hasNestedArray = true;
                break;
            }
        }
        return $hasNestedArray ? [] : $payload;
    }

    private function rows($payload): array
    {
        if (!is_array($payload)) {
            return [];
        }
        if (isset($payload['rows']) && is_array($payload['rows'])) {
            return array_values($payload['rows']);
        }
        if ($this->isList($payload)) {
            return array_values($payload);
        }
        return [];
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}
