<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use PDO;
use App\API\Services\PrintService;
use App\API\Services\DownloadService;
use App\API\Includes\FileLogger;
use App\API\Services\Logger as LegacyLogger;

/**
 * AwardCertificateService
 *
 * Generates department-bound award certificates for issued student awards:
 * - resolves the award's type -> template_key binding;
 * - allocates a per-prefix, per-year serial certificate number;
 * - renders the certificate PDF through PrintService (DOMPDF);
 * - records issuance in `student_award_certificates` (immutable, unique
 *   serial per award) and persists the number back onto the award;
 * - writes a file-based FileLogger audit journal entry (never the database)
 *   so certificate generation is traceable.
 *
 * Learner records are children (under 15) — outputs are confidential.
 * Printability is always gated upstream by student_leadership_manage.
 */
class AwardCertificateService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Resolve an award with its type/category template binding.
     */
    private function loadAward(int $awardId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.student_id, a.award_type_id, a.title, a.award_description,
                   a.academic_year_id, a.academic_year_term_id, a.issue_date,
                   a.status, a.certificate_no,
                   t.code AS type_code, t.name AS award_type_name,
                   t.template_key, t.number_prefix, t.signatory_label,
                   t.secondary_signatory_label,
                   c.name AS category_name,
                   ay.year_name AS academic_year_name,
                   CONCAT_WS(' ', p.first_name, p.last_name) AS student_name,
                   st.admission_no
            FROM student_awards a
            JOIN students st ON st.id = a.student_id
            JOIN persons p ON p.id = st.person_id
            LEFT JOIN student_award_types t ON t.id = a.award_type_id
            LEFT JOIN student_award_categories c ON c.id = t.category_id
            LEFT JOIN academic_years ay ON ay.id = a.academic_year_id
            WHERE a.id = ?
        ");
        $stmt->execute([$awardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Allocate the next certificate number for a prefix + year.
     * Format: <PREFIX>-<YEAR>-<seq padded to at least 4 digits>
     */
    private function nextCertificateNumber(?string $prefix, int $year): string
    {
        $prefix = self::formatPrefix($prefix);
        $year = max(2000, $year);

        $this->db->prepare("
            INSERT INTO award_certificate_counters (prefix, year, last_seq)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE last_seq = last_seq + 1
        ")->execute([$prefix, $year]);

        $stmt = $this->db->prepare("SELECT last_seq FROM award_certificate_counters WHERE prefix = ? AND year = ?");
        $stmt->execute([$prefix, $year]);
        $seq = (int) $stmt->fetchColumn();

        return self::formatCertificateNumber($prefix, $year, $seq);
    }

    /**
     * Sanitize a serial prefix: keep alphanumerics only, uppercase, max 12.
     */
    public static function formatPrefix(?string $prefix): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $prefix), 0, 12));
        return $prefix !== '' ? $prefix : 'KPS';
    }

    /**
     * Render a certificate number from parts: <PREFIX>-<YEAR>-<seq padded to 4>.
     */
    public static function formatCertificateNumber(?string $prefix, int $year, int $seq): string
    {
        return self::formatPrefix($prefix) . '-' . (string) max(2000, $year) . '-' . str_pad((string) max(1, $seq), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Coerce mixed input to a list of positive integer award IDs.
     */
    private function normalizeAwardIds(array $awardIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $awardIds), static fn ($id) => $id > 0)));
    }

    private function currentAcademicYearId(): int
    {
        return (int) date('Y');
    }

    /**
     * Generate certificates for one or more awards. Idempotent per award:
     * an award that already has an issued certificate returns its existing
     * file instead of minting a duplicate serial.
     *
     * @return array{status:string, code:int, message:string, data:array}
     */
    public function generateForAwards(array $awardIds, int $operatorId = 0): array
    {
        $awardIds = $this->normalizeAwardIds($awardIds);
        if (!$awardIds) {
            return ['status' => 'error', 'code' => 422, 'message' => 'No award IDs supplied', 'data' => null];
        }
        $results = [];
        foreach ($awardIds as $awardId) {
            try {
                $results[] = $this->generateForAward($awardId, $operatorId);
            } catch (\Throwable $e) {
                $results[] = [
                    'award_id' => $awardId,
                    'success' => false,
                    'message' => 'Certificate generation failed',
                ];
                LegacyLogger::legacyError('[AwardCertificateService] award ' . $awardId . ': ' . $e->getMessage());
            }
        }
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Certificates processed',
            'data' => ['results' => $results],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateForAward(int $awardId, int $operatorId = 0): array
    {
        $award = $this->loadAward($awardId);
        if ($award === null) {
            return ['award_id' => $awardId, 'success' => false, 'message' => 'Award not found'];
        }
        if ($award['status'] === 'revoked') {
            return ['award_id' => $awardId, 'success' => false, 'message' => 'Revoked awards cannot be printed'];
        }

        $existing = $this->existingCertificate($awardId);
        if ($existing) {
            return $this->buildResult($award, $existing['certificate_number'], $existing['pdf_path'], true);
        }

        $templateKey = trim((string) ($award['template_key'] ?? ''));
        if ($templateKey === '') {
            return ['award_id' => $awardId, 'success' => false, 'message' => 'This award type has no certificate template'];
        }

        $year = (int) ($award['academic_year_name'] ?? '') > 0
            ? (int) $award['academic_year_name']
            : $this->currentAcademicYearId();
        $certificateNumber = $this->nextCertificateNumber($award['number_prefix'], $year);

        $prints = new PrintService();
        $schoolConfig = $prints->getSchoolConfig();

        $principalName = ($award['signatory_label'] !== null && $award['signatory_label'] !== '')
            ? $award['signatory_label']
            : (string) ($schoolConfig['principal'] ?? 'Headteacher');
        $principalTitle = ($award['signatory_label'] !== null && $award['signatory_label'] !== '')
            ? $award['signatory_label']
            : (string) ($schoolConfig['principal_title'] ?? 'Headteacher');

        $pdf = $prints->printCertificate($templateKey, [
            'recipientName' => $award['student_name'],
            'achievement' => $award['award_description'] ?? $award['award_type_name'],
            'certificateTypeName' => $award['award_type_name'],
            'certificateCategoryName' => $award['category_name'],
            'academicYear' => (string) $year,
            'certificateNumber' => $certificateNumber,
            'dateAwarded' => $award['issue_date'] !== null
                ? date('d F Y', strtotime((string) $award['issue_date']))
                : date('d F Y'),
            'principalName' => $principalName,
            'principalTitle' => $principalTitle,
            'teacherName' => $award['secondary_signatory_label'] ?? 'Academic Officer',
            'admissionNo' => $award['admission_no'],
            'studentName' => $award['student_name'],
        ]);

        $this->db->prepare("
            INSERT INTO student_award_certificates
                (award_id, award_type_id, certificate_number, template_key, pdf_path, issued_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $awardId,
            $award['award_type_id'] !== null ? (int) $award['award_type_id'] : null,
            $certificateNumber,
            $templateKey,
            $pdf,
            $operatorId === 0 ? null : $operatorId,
        ]);

        $this->db->prepare("UPDATE student_awards SET certificate_no = ? WHERE id = ?")
            ->execute([$certificateNumber, $awardId]);

        FileLogger::write('certificates', [
            'type' => 'award_certificate',
            'action' => 'issued',
            'user_id' => $operatorId,
            'status' => 'success',
            'award_id' => $awardId,
            'student_id' => (int) $award['student_id'],
            'template_key' => $templateKey,
            'certificate_number' => $certificateNumber,
        ]);

        return $this->buildResult($award, $certificateNumber, $pdf, false);
    }

    private function existingCertificate(int $awardId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT certificate_number, pdf_path
            FROM student_award_certificates
            WHERE award_id = ?
        ");
        $stmt->execute([$awardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResult(array $award, string $number, ?string $pdfPath, bool $reprinted): array
    {
        $result = [
            'award_id' => (int) $award['id'],
            'student_name' => $award['student_name'],
            'award_type_name' => $award['award_type_name'],
            'certificate_number' => $number,
            'template_key' => $award['template_key'],
            'reprinted' => $reprinted,
            'success' => true,
            'message' => $reprinted ? 'Certification already issued' : 'Certificate generated',
        ];
        if ($pdfPath !== null) {
            $url = (new DownloadService())->printUrlForAbsolutePath($pdfPath, 1800);
            $result['file'] = [
                'filename' => basename($pdfPath),
                'mime_type' => 'application/pdf',
                'url' => $url,
                'download_url' => $url,
            ];
        }
        return $result;
    }
}