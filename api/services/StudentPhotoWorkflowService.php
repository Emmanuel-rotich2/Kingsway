<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Modules\system\MediaManager;
use PDO;
use RuntimeException;

final class StudentPhotoWorkflowService
{
    public function __construct(private PDO $db)
    {
    }

    public function submit(int $studentId, array $file, int $actorId, string $source = 'staff_upload', bool $approveImmediately = false): array
    {
        $personId = $this->personId($studentId);
        if ($personId < 1) throw new RuntimeException('Student not found.');

        $media = new MediaManager($this->db);
        $mediaId = (int) $media->upload(
            $file,
            'students/images',
            $studentId,
            null,
            $actorId,
            'student photo',
            'student-photo',
            'photo_student_' . $studentId
        );

        $status = $approveImmediately ? 'approved' : 'pending';
        $stmt = $this->db->prepare(
            'INSERT INTO person_photo_versions
                (person_id, media_id, source, status, requested_by, approved_by, reviewed_at, reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $personId, $mediaId, $source, $status, $actorId,
            $approveImmediately ? $actorId : null,
            $approveImmediately ? date('Y-m-d H:i:s') : null,
            $approveImmediately ? 'Approved at upload by authorized staff.' : null,
        ]);
        $versionId = (int) $this->db->lastInsertId();

        if ($approveImmediately) $this->promote($versionId, $actorId);

        return ['version_id' => $versionId, 'media_id' => $mediaId, 'status' => $status];
    }

    public function approve(int $versionId, int $actorId, string $reason = ''): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id, person_id, status FROM person_photo_versions WHERE id = ? FOR UPDATE');
            $stmt->execute([$versionId]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$version) throw new RuntimeException('Photo request not found.');
            if ($version['status'] !== 'pending') throw new RuntimeException('Only pending photos can be approved.');
            $this->promote($versionId, $actorId, $reason);
            $this->db->commit();
            return ['version_id' => $versionId, 'status' => 'approved'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function reject(int $versionId, int $actorId, string $reason): array
    {
        if (trim($reason) === '') throw new RuntimeException('A rejection reason is required.');
        $stmt = $this->db->prepare(
            "UPDATE person_photo_versions
             SET status = 'rejected', approved_by = ?, reviewed_at = NOW(), reason = ?
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$actorId, $reason, $versionId]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('Only pending photos can be rejected.');
        return ['version_id' => $versionId, 'status' => 'rejected'];
    }

    public function list(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ppv.*, mf.filename, mf.original_name, mf.file_type
             FROM person_photo_versions ppv
             JOIN students s ON s.person_id = ppv.person_id
             LEFT JOIN media_files mf ON mf.id = ppv.media_id
             WHERE s.id = ? ORDER BY ppv.created_at DESC, ppv.id DESC'
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listPending(): array
    {
        $stmt = $this->db->query(
            "SELECT ppv.*, s.id AS student_id, s.admission_no,
                    p.first_name, p.middle_name, p.last_name,
                    mf.filename, mf.original_name, mf.file_type
             FROM person_photo_versions ppv
             JOIN persons p ON p.id = ppv.person_id
             JOIN students s ON s.person_id = p.id
             LEFT JOIN media_files mf ON mf.id = ppv.media_id
             WHERE ppv.status = 'pending'
             ORDER BY ppv.created_at ASC, ppv.id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function promote(int $versionId, int $actorId, string $reason = ''): void
    {
        $stmt = $this->db->prepare('SELECT person_id, media_id FROM person_photo_versions WHERE id = ? FOR UPDATE');
        $stmt->execute([$versionId]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$version) throw new RuntimeException('Photo request not found.');

        $this->db->prepare("UPDATE person_photo_versions SET status = 'superseded' WHERE person_id = ? AND status = 'approved'")
            ->execute([(int) $version['person_id']]);
        $this->db->prepare("UPDATE person_photo_versions SET status = 'approved', approved_by = ?, reviewed_at = NOW(), reason = ? WHERE id = ?")
            ->execute([$actorId, $reason !== '' ? $reason : 'Approved by authorized staff.', $versionId]);

        $media = new MediaManager($this->db);
        $stored = $media->getStoredReference((int) $version['media_id']);
        $this->db->prepare('UPDATE persons SET photo_media_id = ?, photo_url = ? WHERE id = ?')
            ->execute([(int) $version['media_id'], $stored, (int) $version['person_id']]);
    }

    private function personId(int $studentId): int
    {
        $stmt = $this->db->prepare('SELECT person_id FROM students WHERE id = ?');
        $stmt->execute([$studentId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
