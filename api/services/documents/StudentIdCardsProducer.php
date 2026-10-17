<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Modules\students\StudentIDCardGenerator;
use App\API\Services\PrintService;
use PDO;

/**
 * Bulk API for student ID cards.
 *
 * Entities are the normalized card records from
 * StudentIDCardGenerator::loadStudentCardRecords(one query per batch). Each
 * entity renders as a self-contained HTML document through the exact shared
 * template/CSS path used by the synchronous flow, so output is byte-identical.
 */
final class StudentIdCardsProducer implements DocumentBatchProducer
{
    private StudentIDCardGenerator $generator;
    private PrintService $prints;

    public function __construct(?PDO $db = null, ?StudentIDCardGenerator $generator = null, ?PrintService $prints = null)
    {
        $this->generator = $generator ?? new StudentIDCardGenerator();
        $this->prints = $prints ?? new PrintService();
    }

    public static function kind(): string
    {
        return 'student_id_cards';
    }

    public static function label(): string
    {
        return 'Student ID Cards';
    }

    /**
     * @return array{printerMode: string, side: string, filename: string}
     */
    public static function defaultOptions(): array
    {
        return [
            'printerMode' => 'a4_pdf',
            'side' => 'both',
            'filename' => 'student_id_cards',
        ];
    }

    public function resolveEntities(array $selection, array $options, array $context): array
    {
        $ids = array_values(array_map('intval', (array) ($selection['student_ids'] ?? [])));
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        $records = $this->generator->loadStudentCardRecords($ids);
        return [
            'total' => count($records),
            'unit' => 'student ID cards',
            'entities' => $records,
        ];
    }

    public function renderHtml(array $entity, array $options, array $context): ?array
    {
        $doc = $this->prints->renderStudentIdCardHtmlChunks([$entity], [
            'printerMode' => (string) ($options['printerMode'] ?? 'a4_pdf'),
            'side' => (string) ($options['side'] ?? 'both'),
            'chunkSize' => 1,
            'filename' => 'card',
        ]);
        if ($doc === []) {
            return null;
        }
        return [
            'html' => $doc[0]['html'],
        ];
    }
}