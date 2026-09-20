<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/** Immutable PDF generation, approval and guardian delivery for report cards. */
final class ReportCardReleaseService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(array $filters): array
    {
        $where = ['r.version_no = (SELECT MAX(r2.version_no) FROM report_card_releases r2 WHERE r2.student_id=r.student_id AND r2.academic_year_term_id=r.academic_year_term_id)'];
        $params = [];
        if (!empty($filters['term_id'])) { $where[] = 'r.academic_year_term_id=?'; $params[] = (int) $filters['term_id']; }
        if (!empty($filters['student_id'])) { $where[] = 'r.student_id=?'; $params[] = (int) $filters['student_id']; }
        if (!empty($filters['status'])) { $where[] = 'r.status=?'; $params[] = (string) $filters['status']; }
        if (!empty($filters['class_id'])) {
            $where[] = '(ayc.class_id=? OR sae.academic_year_class_stream_id=?)';
            $params[] = (int) $filters['class_id'];
            $params[] = (int) $filters['class_id'];
        }
        $stmt = $this->db->prepare(
            'SELECT r.id AS release_id, r.student_id, r.academic_year_term_id, r.version_no,
                    r.status, r.overall_percentage, r.class_position, r.class_population,
                    r.generated_at, r.approved_at, r.released_at, r.pdf_path
             FROM report_card_releases r
             JOIN student_academic_enrollments sae ON sae.id=r.student_academic_enrollment_id
             JOIN academic_year_class_streams aycs ON aycs.id=sae.academic_year_class_stream_id
             JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY r.generated_at DESC'
        );
        $stmt->execute($params);
        $download = new DownloadService();
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            try {
                $row['download_url'] = $download->generatedDownloadUrlForAbsolutePath((string) $row['pdf_path']);
            } catch (\Throwable $e) {
                $row['download_url'] = null;
            }
            unset($row['pdf_path']);
            $rows[] = $row;
        }
        return $rows;
    }

    public function generate(array $reportData, int $actorUserId): array
    {
        $student = $reportData['student'] ?? [];
        $term = $reportData['term'] ?? [];
        $studentId = (int) ($student['id'] ?? 0);
        $termId = (int) ($term['id'] ?? 0);
        $enrollmentId = (int) ($student['enrollment_id'] ?? 0);
        if (!$studentId || !$termId || !$enrollmentId) throw new RuntimeException('Incomplete report card identity context', 422);
        if (empty($reportData['completeness']['release_ready'])) {
            $complete = (int) ($reportData['completeness']['complete_learning_areas'] ?? 0);
            $expected = (int) ($reportData['completeness']['expected_learning_areas'] ?? 0);
            throw new RuntimeException("Report card is incomplete: {$complete} of {$expected} learning areas are ready", 409);
        }

        $reportData['level'] = $this->level((string) ($student['class_name'] ?? ''));
        $reportData['comments'] = $reportData['comments'] ?? [];
        if ((int) ($reportData['result_policy']['publish_rank_to_guardians'] ?? 1) !== 1) {
            $reportData['ranking']['class_position'] = null;
            $reportData['ranking']['cohort_position'] = null;
        }

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM students WHERE id = ? FOR UPDATE');
            $lock->execute([$studentId]);
            $versionStmt = $this->db->prepare(
                'SELECT COALESCE(MAX(version_no), 0) + 1 FROM report_card_releases
                 WHERE student_id = ? AND academic_year_term_id = ?'
            );
            $versionStmt->execute([$studentId, $termId]);
            $version = (int) $versionStmt->fetchColumn();
            $filename = sprintf('report_card_%s_term_%d_v%d', $student['admission_no'] ?? $studentId, $termId, $version);
            $pdfPath = (new PrintService())->printReportCard($reportData, [
                'filename' => $filename,
                'reportCode' => sprintf('RC-%d-%d-V%d', $studentId, $termId, $version),
            ]);
            $hash = hash_file('sha256', $pdfPath);
            if (!$hash) throw new RuntimeException('Unable to fingerprint the generated report card', 500);
            $ranking = $reportData['ranking'] ?? [];
            $stmt = $this->db->prepare(
                "INSERT INTO report_card_releases
                    (student_id, student_academic_enrollment_id, academic_year_term_id, version_no,
                     status, report_data_json, pdf_path, pdf_sha256, overall_percentage,
                     class_position, class_population, generated_by)
                 VALUES (?, ?, ?, ?, 'pending_approval', ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $studentId, $enrollmentId, $termId, $version,
                json_encode($reportData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $pdfPath, $hash,
                $ranking['overall_percentage'] ?? null,
                $ranking['class_position'] ?? null,
                $ranking['class_population'] ?? null,
                $actorUserId,
            ]);
            $releaseId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return ['release_id' => $releaseId, 'version' => $version, 'status' => 'pending_approval', 'pdf_path' => $pdfPath];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function approve(int $releaseId, int $actorUserId): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM report_card_releases WHERE id = ? FOR UPDATE');
            $stmt->execute([$releaseId]);
            $release = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$release) throw new RuntimeException('Report card release not found', 404);
            if ($release['status'] !== 'pending_approval') throw new RuntimeException('Only a pending report card can be approved', 409);
            if (!is_file((string) $release['pdf_path']) || hash_file('sha256', $release['pdf_path']) !== $release['pdf_sha256']) {
                throw new RuntimeException('The generated report PDF is missing or has changed', 409);
            }
            // Keep the currently released version visible to guardians until
            // the corrected version is itself released. Approval only replaces
            // another approved-but-not-yet-released version.
            $this->db->prepare(
                "UPDATE report_card_releases SET status = 'superseded'
                 WHERE student_id = ? AND academic_year_term_id = ? AND id <> ?
                   AND status = 'approved'"
            )->execute([(int) $release['student_id'], (int) $release['academic_year_term_id'], $releaseId]);
            $this->db->prepare(
                "UPDATE report_card_releases SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?"
            )->execute([$actorUserId, $releaseId]);
            $this->db->commit();
            return ['release_id' => $releaseId, 'status' => 'approved'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function release(int $releaseId, int $actorUserId, array $channels = ['sms', 'email', 'whatsapp']): array
    {
        $stmt = $this->db->prepare('SELECT * FROM report_card_releases WHERE id = ? LIMIT 1');
        $stmt->execute([$releaseId]);
        $release = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$release) throw new RuntimeException('Report card release not found', 404);
        if ($release['status'] !== 'approved') throw new RuntimeException('Approve the report card before releasing it', 409);
        if (!is_file((string) $release['pdf_path']) || hash_file('sha256', $release['pdf_path']) !== $release['pdf_sha256']) {
            throw new RuntimeException('The approved report PDF is missing or has changed', 409);
        }
        $data = json_decode((string) $release['report_data_json'], true) ?: [];
        $student = $data['student'] ?? [];
        $term = $data['term'] ?? [];
        $name = trim(implode(' ', array_filter([$student['first_name'] ?? '', $student['last_name'] ?? '']))) ?: 'your child';
        $ranking = $data['ranking'] ?? [];
        $average = isset($ranking['overall_percentage']) ? number_format((float) $ranking['overall_percentage'], 1) . '%' : 'available in the report';
        $portalUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/') . '/parent_portal.php';
        $message = "Kingsway: {$name}'s {$term['name']} report is ready. Overall: {$average}. View it in the parent portal: {$portalUrl}";
        $publicUrl = $this->publicUrl((string) $release['pdf_path']);
        $platform = new CommunicationPlatformService($this->db);
        $outcomes = [];
        foreach (array_values(array_unique($channels)) as $channel) {
            if (!in_array($channel, ['sms', 'email', 'whatsapp'], true)) continue;
            try {
                $options = [
                    'sender_id' => $actorUserId,
                    'purpose' => 'results',
                    'academic_year_id' => $term['academic_year_id'] ?? null,
                    'academic_year_term_id' => (int) $release['academic_year_term_id'],
                ];
                if ($channel !== 'sms') {
                    $options['attachments'] = [[
                        'file_name' => basename((string) $release['pdf_path']),
                        'file_path' => $release['pdf_path'],
                        'mime_type' => 'application/pdf',
                        'public_url' => $publicUrl,
                    ]];
                }
                $outcomes[$channel] = $platform->queueRenderedForStudentParents(
                    (int) $release['student_id'],
                    $channel,
                    "{$term['name']} report card — {$name}",
                    $message,
                    $options
                );
                $this->recordDeliveries($releaseId, (int) $release['student_id'], $channel, $outcomes[$channel]);
            } catch (\Throwable $e) {
                $outcomes[$channel] = ['status' => 'failed', 'message' => $e->getMessage()];
                $this->recordDeliveries($releaseId, (int) $release['student_id'], $channel, null, $e->getMessage());
            }
        }
        $this->recordPortalAvailability($releaseId, (int) $release['student_id']);
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT status FROM report_card_releases WHERE id = ? FOR UPDATE');
            $lock->execute([$releaseId]);
            if ($lock->fetchColumn() !== 'approved') {
                throw new RuntimeException('The report card release state changed; reload and try again', 409);
            }
            $this->db->prepare(
                "UPDATE report_card_releases SET status='superseded'
                 WHERE student_id=? AND academic_year_term_id=? AND id<>?
                   AND status IN ('approved','released')"
            )->execute([(int) $release['student_id'], (int) $release['academic_year_term_id'], $releaseId]);
            $this->db->prepare(
                "UPDATE report_card_releases SET status='released', released_by=?, released_at=NOW()
                 WHERE id=? AND status='approved'"
            )->execute([$actorUserId, $releaseId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return ['release_id' => $releaseId, 'status' => 'released', 'channels' => $outcomes];
    }

    private function recordPortalAvailability(int $releaseId, int $studentId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO report_card_deliveries
                (report_card_release_id, parent_id, channel, status, failure_reason)
             SELECT ?, sp.parent_id, 'portal', 'sent', NULL
             FROM student_parents sp
             WHERE sp.student_id = ?
             ON DUPLICATE KEY UPDATE status='sent', failure_reason=NULL"
        );
        $stmt->execute([$releaseId, $studentId]);
    }

    private function recordDeliveries(int $releaseId, int $studentId, string $channel, ?array $outcome, ?string $error = null): void
    {
        $parents = $this->db->prepare('SELECT parent_id FROM student_parents WHERE student_id = ?');
        $parents->execute([$studentId]);
        $insert = $this->db->prepare(
            "INSERT INTO report_card_deliveries
                (report_card_release_id, parent_id, channel, communication_id, status, failure_reason)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE communication_id=VALUES(communication_id), status=VALUES(status), failure_reason=VALUES(failure_reason)"
        );
        foreach ($parents->fetchAll(PDO::FETCH_COLUMN) as $parentId) {
            $recipient = null;
            foreach ((array) ($outcome['recipients'] ?? []) as $candidate) {
                if ((int) ($candidate['parent_id'] ?? 0) === (int) $parentId) { $recipient = $candidate; break; }
            }
            if ($error !== null) {
                $status = 'failed';
                $reason = $error;
            } elseif (($recipient['status'] ?? '') === 'queued') {
                $status = 'queued';
                $reason = null;
            } else {
                $status = 'skipped';
                $reason = $recipient['reason'] ?? 'No eligible guardian endpoint';
            }
            $insert->execute([$releaseId, (int) $parentId, $channel, $outcome['communication_id'] ?? null, $status, $reason]);
        }
    }

    private function publicUrl(string $path): ?string
    {
        $baseUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        if (strpos($baseUrl, 'https://') !== 0) return null;
        try {
            // WhatsApp fetches a short-lived signed endpoint; the underlying
            // generated PDF path is never exposed as a permanent public URL.
            return (new DownloadService())->generatedDownloadUrlForAbsolutePath($path, 3600);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function level(string $className): string
    {
        if (preg_match('/play|pre.?primary|pp\s*[12]/i', $className)) return 'PP';
        if (preg_match('/([1-9])/', $className, $match)) {
            $grade = (int) $match[1];
            if ($grade <= 3) return 'LowerPrimary';
            if ($grade <= 6) return 'UpperPrimary';
            return 'JuniorSecondary';
        }
        return 'PP';
    }
}
