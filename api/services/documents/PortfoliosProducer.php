<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Modules\students\PortfolioManager;
use App\API\Services\PrintService;
use PDO;

/**
 * Bulk API for student portfolios.
 *
 * Entities are per-student portfolio data assembled via
 * PortfolioManager::getStudentPortfolioData(). Each entity renders as a
 * self-contained HTML document through the exact shared template/CSS path used
 * by the synchronous flow, so output is byte-identical.
 */
final class PortfoliosProducer implements DocumentBatchProducer
{
    private PortfolioManager $portfolio;
    private PrintService $prints;

    public function __construct(?PDO $db = null, ?PortfolioManager $portfolio = null, ?PrintService $prints = null)
    {
        $this->portfolio = $portfolio ?? new PortfolioManager($db ?? \App\Database\Database::getInstance()->getConnection());
        $this->prints = $prints ?? new PrintService();
    }

    public static function kind(): string
    {
        return 'portfolios';
    }

    public static function label(): string
    {
        return 'Student Portfolios';
    }

    /**
     * @return array{filename: string}
     */
    public static function defaultOptions(): array
    {
        return [
            'filename' => 'portfolio',
        ];
    }

    public function resolveEntities(array $selection, array $options, array $context): array
    {
        $studentIds = array_values(array_map('intval', (array) ($selection['student_ids'] ?? [])));
        $studentIds = array_values(array_unique(array_filter($studentIds, static fn (int $id): bool => $id > 0)));
        if ($studentIds === []) {
            return ['total' => 0, 'unit' => 'portfolios', 'entities' => []];
        }

        $records = [];
        foreach ($studentIds as $sid) {
            $data = $this->portfolio->getStudentPortfolioData($sid);
            if ($data !== null) {
                $records[] = $data;
            }
        }

        return [
            'total' => count($records),
            'unit' => 'portfolios',
            'entities' => $records,
        ];
    }

    public function renderHtml(array $entity, array $options, array $context): ?array
    {
        try {
            $html = $this->prints->buildPortfolioHtml($entity, [
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'filename' => 'portfolio',
            ]);
            return ['html' => $html];
        } catch (\Throwable $e) {
            \App\API\Includes\FileLogger::write('document_generation', [
                'type' => 'portfolio_render_failed',
                'entity_id' => (string) ($entity['student']['id'] ?? ''),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}