<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use PDO;
use RuntimeException;

/**
 * KNEC CBA portal upload audit trail.
 *
 * Circular KNEC/GEN/TD/SE/SBA/GUI/2026/03 (4.0): the school administers the
 * tools, scores the scripts, and uploads ALL learner scores to cba.knec.ac.ke
 * by the registry deadline. This service records that upload act — who, when,
 * which scope, how many learners, and the confirmation reference — so the
 * school can evidence compliance during KNEC verification. Recording an
 * upload here NEVER changes any learner result; it journals operational
 * compliance state only (file journal, never a DB log table).
 */
final class KnecUploadTrackingService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<int,array> */
    public function listForPeriod(int $examPeriodId): array
    {
        $stmt = $this->db->prepare(
            'SELECT k.id, k.exam_period_id, k.academic_year_class_id, c.name AS class_name,
                    k.learning_area_id, la.name AS learning_area_name, k.uploaded_by, k.uploaded_at,
                    k.learners_uploaded, k.confirmation_ref, k.method, k.status, k.notes
             FROM knec_upload_tracking k
             LEFT JOIN ' . ReadReplicaService::qualifiedRef('academic_year_classes') . ' ayc ON ayc.id = k.academic_year_class_id
             LEFT JOIN ' . ReadReplicaService::qualifiedRef('classes') . ' c ON c.id = ayc.class_id
             LEFT JOIN ' . ReadReplicaService::qualifiedRef('learning_areas') . ' la ON la.id = k.learning_area_id
             WHERE k.exam_period_id = ?
             ORDER BY k.uploaded_at DESC'
        );
        $stmt->execute([$examPeriodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordUpload(array $payload, int $userId): array
    {
        $examPeriodId = (int) ($payload['exam_period_id'] ?? 0);
        if (!$examPeriodId) {
            throw new RuntimeException('exam_period_id is required', 422);
        }
        $status = (string) ($payload['status'] ?? 'uploaded');
        if (!in_array($status, ['uploaded', 'confirmed', 'failed', 'reversed'], true)) {
            throw new RuntimeException('Invalid upload status', 422);
        }
        $method = (string) ($payload['method'] ?? 'cba_portal');
        if (!in_array($method, ['cba_portal', 'manual', 'bulk'], true)) {
            throw new RuntimeException('Invalid upload method', 422);
        }
        $insert = $this->db->prepare(
            'INSERT INTO knec_upload_tracking
                (exam_period_id, academic_year_class_id, learning_area_id, uploaded_by, learners_uploaded, confirmation_ref, method, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $examPeriodId,
            !empty($payload['academic_year_class_id']) ? (int) $payload['academic_year_class_id'] : null,
            !empty($payload['learning_area_id']) ? (int) $payload['learning_area_id'] : null,
            $userId,
            (int) ($payload['learners_uploaded'] ?? 0),
            trim((string) ($payload['confirmation_ref'] ?? '')) ?: null,
            $method,
            $status,
            trim((string) ($payload['notes'] ?? '')) ?: null,
        ]);
        FileLogger::write('assessment', [
            'type' => 'knec_scores_uploaded',
            'tracking_id' => (int) $this->db->lastInsertId(),
            'exam_period_id' => $examPeriodId,
            'uploaded_by' => $userId,
            'learners_uploaded' => (int) ($payload['learners_uploaded'] ?? 0),
            'status' => $status,
        ]);
        return ['id' => (int) $this->db->lastInsertId()];
    }
}
