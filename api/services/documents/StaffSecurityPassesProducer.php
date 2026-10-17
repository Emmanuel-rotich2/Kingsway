<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Services\StaffRecordsService;
use App\API\Services\PrintService;
use PDO;

/**
 * Bulk API for staff security passes.
 *
 * Entities are staff pass records assembled via
 * StaffRecordsService::idCards(). Each entity renders as a self-contained
 * HTML document through the exact shared template/CSS path used by the
 * synchronous flow, so output is byte-identical.
 */
final class StaffSecurityPassesProducer implements DocumentBatchProducer
{
    private StaffRecordsService $records;
    private PrintService $prints;

    public function __construct(?PDO $db = null, ?StaffRecordsService $records = null, ?PrintService $prints = null)
    {
        $this->records = $records ?? new StaffRecordsService($db ?? \App\Database\Database::getInstance()->getConnection());
        $this->prints = $prints ?? new PrintService();
    }

    public static function kind(): string
    {
        return 'staff_security_passes';
    }

    public static function label(): string
    {
        return 'Staff Security Passes';
    }

    /**
     * @return array{printerMode: string, side: string, filename: string}
     */
    public static function defaultOptions(): array
    {
        return [
            'printerMode' => 'a4_pdf',
            'side' => 'both',
            'filename' => 'staff_security_passes',
        ];
    }

    public function resolveEntities(array $selection, array $options, array $context): array
    {
        $staffIds = array_values(array_map('intval', (array) ($selection['staff_ids'] ?? [])));
        $staffIds = array_values(array_unique(array_filter($staffIds, static fn (int $id): bool => $id > 0)));
        if ($staffIds === []) {
            return ['total' => 0, 'unit' => 'staff security passes', 'entities' => []];
        }

        $rows = $this->records->idCards(['staff_ids' => $staffIds]);
        // Filter to only those with existing pass cards (requireExisting = true)
        $records = array_filter($rows, static fn (array $row): bool => !empty($row['id']));

        return [
            'total' => count($records),
            'unit' => 'staff security passes',
            'entities' => array_values($records),
        ];
    }

    public function renderHtml(array $entity, array $options, array $context): ?array
    {
        try {
            // Normalize the pass data similar to StaffIDCardGenerator::loadPasses
            $normalized = $this->normalizePass($entity);
            $doc = $this->prints->buildStaffSecurityPassHtml($normalized, [
                'printerMode' => (string) ($options['printerMode'] ?? 'a4_pdf'),
                'side' => (string) ($options['side'] ?? 'both'),
                'filename' => 'staff_security_pass',
            ]);
            return ['html' => $doc['html']];
        } catch (\Throwable $e) {
            \App\API\Includes\FileLogger::write('document_generation', [
                'type' => 'staff_pass_render_failed',
                'entity_id' => (string) ($entity['staff_id'] ?? ''),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function normalizePass(array $row): array
    {
        $passNumber = trim((string) ($row['card_number'] ?? ''));
        if ($passNumber === '') {
            $passNumber = $this->records->securityPassNumberForStaff((int) $row['staff_id']);
        }

        $credentialService = new \App\API\Services\StaffSecurityPassCredentialService();
        $credential = $credentialService->issue($passNumber, null);

        return array_merge($row, [
            'pass_id' => isset($row['id']) && $row['id'] !== null ? (int) $row['id'] : null,
            'card_number' => $passNumber,
            'expires_at' => null,
            'generated_at' => $row['generated_at'] ?: date('Y-m-d'),
            'qr_code_data_uri' => $credentialService->qrDataUri($credential),
        ]);
    }
}