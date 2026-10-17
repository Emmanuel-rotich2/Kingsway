<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Services\PrintService;
use PDO;

/**
 * Bulk API for student fee statements.
 *
 * Entities are per-student fee statement data assembled via
 * PrintService::prepareStudentFeeStatement(). Each entity renders as a
 * self-contained HTML document through the exact shared template/CSS path
 * used by the synchronous flow.
 */
final class FeeStatementsProducer implements DocumentBatchProducer
{
    private PrintService $prints;

    public function __construct(?PDO $db = null, ?PrintService $prints = null)
    {
        $this->prints = $prints ?? new PrintService();
    }

    public static function kind(): string
    {
        return 'fee_statements';
    }

    public static function label(): string
    {
        return 'Student Fee Statements';
    }

    /**
     * @return array{academic_year: int|null, filename: string}
     */
    public static function defaultOptions(): array
    {
        return [
            'academic_year' => null,
            'filename' => 'fee_statements',
        ];
    }

    public function resolveEntities(array $selection, array $options, array $context): array
    {
        $studentIds = array_values(array_map('intval', (array) ($selection['student_ids'] ?? [])));
        $studentIds = array_values(array_unique(array_filter($studentIds, static fn (int $id): bool => $id > 0)));
        if ($studentIds === []) {
            return ['total' => 0, 'unit' => 'fee statements', 'entities' => []];
        }

        $academicYear = $options['academic_year'] !== null ? (int) $options['academic_year'] : null;

        $records = [];
        foreach ($studentIds as $sid) {
            try {
                $statement = $this->prints->prepareStudentFeeStatement($sid, $academicYear);
                $records[] = $statement;
            } catch (\Throwable $e) {
                \App\API\Includes\FileLogger::write('document_generation', [
                    'type' => 'fee_statement_prepare_failed',
                    'entity_id' => (string) $sid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total' => count($records),
            'unit' => 'fee statements',
            'entities' => $records,
        ];
    }

    public function renderHtml(array $entity, array $options, array $context): ?array
    {
        try {
            $html = $this->prints->buildFeeStatementHtml($entity, [
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'filename' => 'fee_statement',
            ]);
            return ['html' => $html];
        } catch (\Throwable $e) {
            \App\API\Includes\FileLogger::write('document_generation', [
                'type' => 'fee_statement_render_failed',
                'entity_id' => (string) ($entity['student']['id'] ?? ''),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}