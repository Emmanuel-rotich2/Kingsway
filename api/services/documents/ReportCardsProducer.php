<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Modules\academic\AcademicManager;
use App\API\Services\PrintService;
use PDO;

/**
 * Bulk API for CBC report cards.
 *
 * Entities are per-student report card data assembled via
 * AcademicManager::getReportCardData(). Each entity renders as a self-contained
 * HTML document through the exact shared template/CSS path used by the
 * synchronous flow, so output is byte-identical.
 */
final class ReportCardsProducer implements DocumentBatchProducer
{
    private AcademicManager $academic;
    private PrintService $prints;

    public function __construct(?PDO $db = null, ?AcademicManager $academic = null, ?PrintService $prints = null)
    {
        $this->academic = $academic ?? new AcademicManager();
        $this->prints = $prints ?? new PrintService();
    }

    public static function kind(): string
    {
        return 'report_cards';
    }

    public static function label(): string
    {
        return 'Report Cards';
    }

    /**
     * @return array{result_mode: string, term_id: int, filename: string}
     */
    public static function defaultOptions(): array
    {
        return [
            'result_mode' => 'both',
            'term_id' => 0,
            'filename' => 'report_cards',
        ];
    }

    public function resolveEntities(array $selection, array $options, array $context): array
    {
        $studentIds = array_values(array_map('intval', (array) ($selection['student_ids'] ?? [])));
        $studentIds = array_values(array_unique(array_filter($studentIds, static fn (int $id): bool => $id > 0)));
        if ($studentIds === []) {
            return ['total' => 0, 'unit' => 'report cards', 'entities' => []];
        }

        $termId = (int) ($options['term_id'] ?? 0);
        $resultMode = (string) ($options['result_mode'] ?? 'both');

        $records = [];
        foreach ($studentIds as $sid) {
            $res = $this->academic->getReportCardData($sid, $termId, $resultMode);
            if (($res['success'] ?? false) !== false && !empty($res['data'])) {
                $data = $res['data'];
                // Determine level from class name for template selection
                $className = (string) ($data['student']['class_name'] ?? '');
                $level = $this->mapClassToLevel($className);
                $data['level'] = $level;
                $records[] = $data;
            }
        }

        return [
            'total' => count($records),
            'unit' => 'report cards',
            'entities' => $records,
        ];
    }

    public function renderHtml(array $entity, array $options, array $context): ?array
    {
        try {
            $html = $this->prints->buildReportCardHtml($entity, [
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'filename' => 'report_card',
            ]);
            return ['html' => $html];
        } catch (\Throwable $e) {
            \App\API\Includes\FileLogger::write('document_generation', [
                'type' => 'report_card_render_failed',
                'entity_id' => (string) ($entity['student']['id'] ?? ''),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function mapClassToLevel(string $className): string
    {
        $className = strtolower(trim($className));
        if ($className === '') return 'PP';

        if (preg_match('/^pp\d?$|^pre.primary|^play.group|^nursery/', $className)) {
            return 'PP';
        }
        if (preg_match('/^grade\s*[123]|^lower.primary|^std\s*[123]/', $className)) {
            return 'LowerPrimary';
        }
        if (preg_match('/^grade\s*[456]|^upper.primary|^std\s*[456]/', $className)) {
            return 'UpperPrimary';
        }
        if (preg_match('/^grade\s*[789]|^junior.secondary|^form\s*[123]/', $className)) {
            return 'JuniorSecondary';
        }
        return 'PP';
    }
}