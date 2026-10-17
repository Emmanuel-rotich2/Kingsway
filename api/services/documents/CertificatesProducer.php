<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Services\PrintService;
use PDO;

/**
 * Bulk API for certificates.
 *
 * Certificates are generated from a template type and per-recipient data.
 * The selection must include:
 *   - certificate_type: string (e.g., 'leadership_service', 'co_curricular')
 *   - recipients: array<int, array<string, mixed>> - each with fields like
 *     recipientName, achievement, certificateNumber, dateAwarded, etc.
 *
 * This mirrors the synchronous PrintController::postCertificate flow where
 * the caller supplies the full data payload.
 */
final class CertificatesProducer implements DocumentBatchProducer
{
    private PrintService $prints;

    public function __construct(?PDO $db = null, ?PrintService $prints = null)
    {
        $this->prints = $prints ?? new PrintService();
    }

    public static function kind(): string
    {
        return 'certificates';
    }

    public static function label(): string
    {
        return 'Certificates';
    }

    /**
     * @return array{filename: string}
     */
    public static function defaultOptions(): array
    {
        return [
            'filename' => 'certificates',
        ];
    }

    public function resolveEntities(array $selection, array $options, array $context): array
    {
        $certType = trim((string) ($selection['certificate_type'] ?? ''));
        if ($certType === '') {
            return ['total' => 0, 'unit' => 'certificates', 'entities' => []];
        }
        if (!preg_match('/^[a-z0-9_]{1,80}$/i', $certType)) {
            return ['total' => 0, 'unit' => 'certificates', 'entities' => []];
        }

        $recipients = (array) ($selection['recipients'] ?? []);
        $entities = [];
        foreach ($recipients as $index => $recipient) {
            if (!is_array($recipient)) continue;
            $recipient = array_merge(['certificate_type' => $certType], $recipient);
            $entities[] = $recipient;
        }

        return [
            'total' => count($entities),
            'unit' => 'certificates',
            'entities' => $entities,
        ];
    }

    public function renderHtml(array $entity, array $options, array $context): ?array
    {
        try {
            $certType = (string) ($entity['certificate_type'] ?? '');
            if ($certType === '') {
                return null;
            }
            $html = $this->prints->buildCertificateHtml($certType, $entity, [
                'orientation' => 'landscape',
                'paperSize' => 'A4',
                'filename' => 'certificate',
            ]);
            return ['html' => $html];
        } catch (\Throwable $e) {
            \App\API\Includes\FileLogger::write('document_generation', [
                'type' => 'certificate_render_failed',
                'entity_id' => (string) ($entity['certificateNumber'] ?? ''),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}