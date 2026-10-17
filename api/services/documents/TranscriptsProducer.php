<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Services\PrintService;
use PDO;

/**
 * Bulk API for learner transcripts.
 *
 * The selection should include transcript data for each learner. This is
 * a placeholder producer until a dedicated transcript data service is
 * implemented. The caller provides the full transcript payload per student.
 *
 * Expected selection:
 *   - transcripts: array<int, array<string, mixed>> - each with fields like
 *     student, terms, scores, competencies, attendance, values, etc.
 */
final class TranscriptsProducer implements DocumentBatchProducer
{
    private PrintService $prints;

    public function __construct(?PDO $db = null, ?PrintService $prints = null)
    {
        $this->prints = $prints ?? new PrintService();
    }

    public static function kind(): string
    {
        return 'transcripts';
    }

    public static function label(): string
    {
        return 'Learner Transcripts';
    }

    /**
     * @return array{filename: string}
     */
    public static function defaultOptions(): array
    {
        return [
            'filename' => 'transcripts',
        ];
    }

    public function resolveEntities(array $selection, array $options, array $context): array
    {
        $transcripts = (array) ($selection['transcripts'] ?? []);
        $entities = array_values(array_filter($transcripts, static fn ($v): bool => is_array($v)));

        return [
            'total' => count($entities),
            'unit' => 'transcripts',
            'entities' => $entities,
        ];
    }

    public function renderHtml(array $entity, array $options, array $context): ?array
    {
        try {
            $html = $this->prints->buildTranscriptHtml($entity, [
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'filename' => 'transcript',
            ]);
            return ['html' => $html];
        } catch (\Throwable $e) {
            \App\API\Includes\FileLogger::write('document_generation', [
                'type' => 'transcript_render_failed',
                'entity_id' => (string) ($entity['student']['id'] ?? ''),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}