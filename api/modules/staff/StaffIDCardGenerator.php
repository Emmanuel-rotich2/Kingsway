<?php

declare(strict_types=1);

namespace App\API\Modules\staff;

use App\API\Includes\BaseAPI;
use App\API\Services\StaffRecordsService;
use App\API\Services\StaffSecurityPassCredentialService;
use Exception;
use PDO;
use Throwable;
use function App\API\Includes\formatResponse;

/**
 * StaffIDCardGenerator
 *
 * Compatibility class name retained because StaffController already depends on
 * it. The generated document is now a portrait Staff Security Pass intended
 * for a lanyard, gate verification and attendance-device integration.
 *
 * Responsibilities:
 * - load canonical staff/pass data;
 * - create the signed QR credential;
 * - delegate PDF rendering to PrintService;
 * - delegate browser URLs to DownloadService through BaseAPI.
 */
final class StaffIDCardGenerator extends BaseAPI
{
    private StaffRecordsService $recordsService;
    private StaffSecurityPassCredentialService $credentialService;

    public function __construct()
    {
        parent::__construct('staff_id_cards');
        $this->recordsService = new StaffRecordsService();
        $this->credentialService = new StaffSecurityPassCredentialService();
    }

    /**
     * Upload a staff portrait through the existing MediaManager/UploadService
     * lifecycle and persist the canonical staff.profile_pic_url column.
     */
    public function uploadStaffPhoto($staffId, $fileData)
    {
        try {
            $statement = $this->db->prepare(
                'SELECT id, staff_no FROM staff WHERE id = ? LIMIT 1'
            );
            $statement->execute([(int) $staffId]);
            $staff = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                return formatResponse(false, null, 'Staff member not found.');
            }

            $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
            $mediaId = $mediaManager->upload(
                $fileData,
                'staff/profile_pictures',
                (int) $staffId,
                null,
                $this->user_id,
                'staff profile photo',
                '',
                'photo_staff_' . (int) $staffId
            );

            $profilePictureUrl = $mediaManager->getFileUrl($mediaId)
                ?: $mediaManager->getPreviewUrl($mediaId);

            if (!$profilePictureUrl) {
                return formatResponse(
                    false,
                    null,
                    'Uploaded staff portrait could not be resolved.'
                );
            }

            $statement = $this->db->prepare(
                'UPDATE staff
                 SET profile_pic_url = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $statement->execute([$profilePictureUrl, (int) $staffId]);

            $this->logAction(
                'update',
                (int) $staffId,
                'Uploaded staff security-pass portrait through MediaManager.'
            );

            return formatResponse(
                true,
                [
                    'profile_pic_url' => $profilePictureUrl,
                    'media_id' => $mediaId,
                ],
                'Staff portrait uploaded successfully.'
            );
        } catch (Throwable $exception) {
            $this->logError('uploadStaffPhoto', $exception->getMessage());

            return formatResponse(
                false,
                null,
                'Failed to upload staff portrait: '
                    . $exception->getMessage()
            );
        }
    }

    /**
     * Generate one portrait security-pass PDF.
     *
     * $format is retained for endpoint compatibility. PrintService remains the
     * canonical owner and always returns a PDF descriptor.
     */
    public function generateIDCard(
        $staffId,
        $format = 'pdf',
        $side = 'both',
        $printMode = 'direct_card',
        $expiresAt = null
    ) {
        try {
            // $expiresAt is retained only for endpoint compatibility.
            // Staff security passes are valid while employment remains current.
            $pass = $this->loadSinglePass(
                (int) $staffId,
                false
            );

            $result = $this->prints()->printSingleStaffSecurityPass(
                $pass,
                [
                    'printerMode' => $this->normalizePrintMode($printMode),
                    'side' => $this->normalizeSide($side),
                    'filename' => 'staff_security_pass_'
                        . $this->safeIdentifier($pass['staff_no'])
                        . '_'
                        . date('Y-m-d_His'),
                ]
            );

            $payload = $this->buildPrintPayload($result, [$pass]);

            $this->logAction(
                'create',
                (int) $staffId,
                'Generated portrait staff security pass.'
            );

            return formatResponse(
                true,
                $payload,
                'Staff security pass generated successfully.'
            );
        } catch (Throwable $exception) {
            $this->logError('generateIDCard', $exception->getMessage());

            return formatResponse(
                false,
                null,
                'Failed to generate staff security pass: '
                    . $exception->getMessage()
            );
        }
    }

    /**
     * Generate a printable copy of an existing registered pass.
     */
    public function generatePrintableSingle(
        $staffId,
        $side = 'both',
        $printMode = 'direct_card'
    ) {
        try {
            $pass = $this->loadSinglePass((int) $staffId, true);

            $result = $this->prints()->printSingleStaffSecurityPass(
                $pass,
                [
                    'printerMode' => $this->normalizePrintMode($printMode),
                    'side' => $this->normalizeSide($side),
                    'filename' => 'staff_security_pass_'
                        . $this->safeIdentifier($pass['staff_no'])
                        . '_'
                        . date('Y-m-d_His'),
                ]
            );

            return formatResponse(
                true,
                $this->buildPrintPayload($result, [$pass]),
                'Staff security-pass document prepared successfully.'
            );
        } catch (Throwable $exception) {
            $this->logError(
                'generatePrintableSingle',
                $exception->getMessage()
            );

            return formatResponse(
                false,
                null,
                'Failed to prepare staff security pass: '
                    . $exception->getMessage()
            );
        }
    }

    /**
     * Generate selected staff security passes as A4 sheets or individual
     * portrait pass pages.
     */
    public function generateBulkIDCardsPDF(
        $staffIds,
        $printMode = 'a4_pdf',
        $includeFront = true,
        $includeBack = true,
        $expiresAt = null,
        $requireExisting = false
    ) {
        try {
            $staffIds = array_values(
                array_unique(
                    array_filter(
                        array_map('intval', (array) $staffIds),
                        static fn (int $staffId): bool => $staffId > 0
                    )
                )
            );

            if ($staffIds === []) {
                return formatResponse(
                    false,
                    null,
                    'Select at least one staff member.'
                );
            }

            if (!$includeFront && !$includeBack) {
                return formatResponse(
                    false,
                    null,
                    'Select at least one security-pass side.'
                );
            }

            $side = $includeFront && $includeBack
                ? 'both'
                : ($includeFront ? 'front' : 'back');

            // $expiresAt is retained only for endpoint compatibility.
            $passes = $this->loadPasses(
                $staffIds,
                (bool) $requireExisting
            );

            $result = $this->prints()->printStaffSecurityPasses(
                $passes,
                [
                    'printerMode' => $this->normalizePrintMode($printMode),
                    'side' => $side,
                    'chunkSize' => 100,
                    'filename' => 'staff_security_passes_'
                        . date('Y-m-d_His'),
                ]
            );

            $this->logAction(
                'create',
                0,
                sprintf(
                    'Generated portrait security-pass PDF for %d staff members.',
                    count($passes)
                )
            );

            return formatResponse(
                true,
                $this->buildPrintPayload($result, $passes),
                'Staff security-pass PDF generated successfully.'
            );
        } catch (Throwable $exception) {
            $this->logError(
                'generateBulkIDCardsPDF',
                $exception->getMessage()
            );

            return formatResponse(
                false,
                null,
                'Failed to generate staff security passes: '
                    . $exception->getMessage()
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSinglePass(
        int $staffId,
        bool $requireExisting
    ): array {
        if ($staffId <= 0) {
            throw new Exception('A valid staff ID is required.');
        }

        $passes = $this->loadPasses(
            [$staffId],
            $requireExisting
        );

        if ($passes === []) {
            throw new Exception('Staff member not found.');
        }

        return $passes[0];
    }

    /**
     * @param array<int, int> $staffIds
     * @return array<int, array<string, mixed>>
     */
    private function loadPasses(
        array $staffIds,
        bool $requireExisting
    ): array {
        $rows = $this->recordsService->idCards([
            'staff_ids' => $staffIds,
        ]);

        if ($rows === []) {
            throw new Exception('No current staff members were found.');
        }

        $returnedStaffIds = array_map(
            static fn (array $row): int => (int) ($row['staff_id'] ?? 0),
            $rows
        );
        $missingStaffIds = array_values(array_diff($staffIds, $returnedStaffIds));

        if ($missingStaffIds !== []) {
            throw new Exception(
                'One or more selected staff members are missing or inactive.'
            );
        }

        if ($requireExisting) {
            $missingPasses = array_filter(
                $rows,
                static fn (array $row): bool => empty($row['id'])
            );

            if ($missingPasses !== []) {
                throw new Exception(
                    'One or more selected staff members do not have a generated security pass.'
                );
            }
        }

        return array_map(
            function (array $row): array {
                $passNumber = trim((string) ($row['card_number'] ?? ''));

                if ($passNumber === '') {
                    $passNumber = $this->recordsService
                        ->securityPassNumberForStaff((int) $row['staff_id']);
                }

                $credential = $this->credentialService->issue(
                    $passNumber,
                    null
                );

                $row['pass_id'] = isset($row['id']) && $row['id'] !== null
                    ? (int) $row['id']
                    : null;
                $row['card_number'] = $passNumber;
                $row['expires_at'] = null;
                $row['generated_at'] = $row['generated_at'] ?: date('Y-m-d');
                $row['qr_code_data_uri'] = $this->credentialService
                    ->qrDataUri($credential);

                return $row;
            },
            $rows
        );
    }

    /**
     * @param array<string, mixed> $result
     * @param array<int, array<string, mixed>> $passes
     * @return array<string, mixed>
     */
    private function buildPrintPayload(array $result, array $passes): array
    {
        $files = array_map(
            fn (string $path): array => $this->buildPrintFile($path),
            $result['files'] ?? []
        );

        return array_merge(
            $result,
            [
                'staff_count' => count($passes),
                'passes' => array_map(
                    static fn (array $pass): array => [
                        'staff_id' => (int) $pass['staff_id'],
                        'staff_no' => (string) $pass['staff_no'],
                        'card_number' => (string) $pass['card_number'],
                    ],
                    $passes
                ),
                'files' => $files,
                'file' => $files[0] ?? null,
                'pdf_url' => $files[0]['download_url'] ?? null,
                'download_url' => $files[0]['download_url'] ?? null,
            ]
        );
    }

    /**
     * @return array{filename:string,download_url:string,url:string}
     */
    private function buildPrintFile(string $path): array
    {
        $url = $this->generatedDownloadUrl($path, true);

        return [
            'filename' => basename($path),
            'download_url' => $url,
            'url' => $url,
        ];
    }

    private function normalizePrintMode($value): string
    {
        return in_array(
            strtolower(trim((string) $value)),
            ['direct_card', 'direct'],
            true
        )
            ? 'direct_card'
            : 'a4_pdf';
    }

    private function normalizeSide($value): string
    {
        $side = strtolower(trim((string) $value));

        return in_array($side, ['front', 'back', 'both'], true)
            ? $side
            : 'both';
    }

    private function safeIdentifier(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($value));
        return $safe !== null && $safe !== '' ? $safe : 'staff';
    }
}
