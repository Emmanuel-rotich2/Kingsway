<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Authoritative summative score-entry and moderation service.
 *
 * It binds every write to the published exam's assessment, exact class-stream
 * enrollment, authenticated teacher scope, active grading scale and lifecycle
 * state. Browser-supplied grades, enrollment ids and marker ids are ignored.
 */
final class AssessmentResultsService
{
    private PDO $db;
    private int $userId;
    private ?int $staffId = null;
    private ?array $roleNames = null;
    private CbcGradingService $grading;

    public function __construct(PDO $db, int $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->grading = new CbcGradingService($db);
    }

    public function examEntryContext(int $examScheduleId): array
    {
        $exam = $this->exam($examScheduleId);
        $this->assertCanAccessAssessment($exam);
        $this->assertPeriodAllowsEntry((int) $exam['assessment_id']);

        $roster = $this->roster((int) $exam['academic_year_class_stream_id'], (int) $exam['assessment_id']);
        return [
            'exam' => $exam,
            'students' => $roster,
            'editable' => $exam['assessment_status'] === 'pending_submission',
            'can_submit' => $exam['assessment_status'] === 'pending_submission' && count($roster) > 0,
        ];
    }

    public function save(int $assessmentId, array $rows, bool $submit, string $reason = ''): array
    {
        if (!$rows) {
            throw new RuntimeException('At least one learner result is required', 422);
        }

        $this->db->beginTransaction();
        try {
            $assessment = $this->assessment($assessmentId, true);
            $this->assertCanAccessAssessment($assessment);
            $this->assertPeriodAllowsEntry($assessmentId);
            if ($assessment['assessment_status'] !== 'pending_submission') {
                throw new RuntimeException('Submitted or approved results are locked; moderation must reopen them', 409);
            }

            $roster = $this->rosterMap((int) $assessment['academic_year_class_stream_id']);
            if (!$roster) {
                throw new RuntimeException('No active learner enrollments exist for this class stream', 409);
            }

            $normalized = [];
            foreach ($rows as $row) {
                $studentId = (int) ($row['student_id'] ?? 0);
                if (!$studentId || !isset($roster[$studentId])) {
                    throw new RuntimeException('A supplied learner is not enrolled in this exam class stream', 422);
                }
                if (isset($normalized[$studentId])) {
                    throw new RuntimeException('A learner appears more than once in the submitted marks', 422);
                }

                $entryStatus = strtolower(trim((string) ($row['entry_status'] ?? 'present')));
                if (!in_array($entryStatus, ['present', 'absent', 'exempted'], true)) {
                    throw new RuntimeException('Invalid learner examination status', 422);
                }

                $score = null;
                $grade = null;
                if ($entryStatus === 'present') {
                    $raw = $row['marks_obtained'] ?? $row['score_obtained'] ?? $row['score'] ?? null;
                    if ($raw === null || $raw === '' || !is_numeric($raw)) {
                        throw new RuntimeException('Every present learner requires numeric marks', 422);
                    }
                    $score = (float) $raw;
                    $grade = $this->grading->grade($score, (float) $assessment['max_marks']);
                }

                $normalized[$studentId] = [
                    'student_id' => $studentId,
                    'enrollment_id' => $roster[$studentId],
                    'entry_status' => $entryStatus,
                    'score' => $score,
                    'grade' => $grade,
                    'remarks' => trim((string) ($row['remarks'] ?? '')),
                ];
            }

            if ($submit) {
                $missing = array_diff(array_keys($roster), array_keys($normalized));
                if ($missing) {
                    throw new RuntimeException(count($missing) . ' learner(s) still require marks, absent, or exempted status', 422);
                }
            }

            $upsert = $this->db->prepare(
                "INSERT INTO assessment_results
                    (assessment_id, student_academic_enrollment_id, marks_obtained, entry_status,
                     grade, points, remarks, moderation_note, submitted_at, is_submitted,
                     is_approved, responder_type, responder_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 0, 'teacher', ?)
                 ON DUPLICATE KEY UPDATE
                    marks_obtained = VALUES(marks_obtained),
                    entry_status = VALUES(entry_status),
                    grade = VALUES(grade),
                    points = VALUES(points),
                    remarks = VALUES(remarks),
                    moderation_note = NULL,
                    submitted_at = VALUES(submitted_at),
                    is_submitted = VALUES(is_submitted),
                    is_approved = 0,
                    responder_type = 'teacher',
                    responder_id = VALUES(responder_id)"
            );
            $existingStmt = $this->db->prepare(
                'SELECT * FROM assessment_results WHERE assessment_id = ? AND student_academic_enrollment_id = ? LIMIT 1'
            );

            $saved = 0;
            foreach ($normalized as $item) {
                $existingStmt->execute([$assessmentId, $item['enrollment_id']]);
                $old = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($old && (int) $old['is_approved'] === 1) {
                    throw new RuntimeException('An approved learner result cannot be overwritten', 409);
                }

                $grade = $item['grade'];
                $submittedAt = $submit ? date('Y-m-d H:i:s') : null;
                $upsert->execute([
                    $assessmentId,
                    $item['enrollment_id'],
                    $item['score'],
                    $item['entry_status'],
                    $grade['grade_code'] ?? null,
                    $grade['points'] ?? null,
                    $item['remarks'],
                    $submittedAt,
                    $submit ? 1 : 0,
                    $this->staffId(),
                ]);

                $resultId = $old ? (int) $old['id'] : (int) $this->db->lastInsertId();
                $newValues = [
                    'marks_obtained' => $item['score'],
                    'entry_status' => $item['entry_status'],
                    'grade' => $grade['grade_code'] ?? null,
                    'points' => $grade['points'] ?? null,
                    'remarks' => $item['remarks'],
                    'is_submitted' => $submit,
                ];
                $this->recordEvent(
                    $assessmentId,
                    $resultId,
                    $item['enrollment_id'],
                    $submit ? 'submitted' : ($old ? 'updated' : 'created'),
                    $old,
                    $newValues,
                    $reason
                );
                $saved++;
            }

            $status = $submit ? 'submitted' : 'pending_submission';
            $stmt = $this->db->prepare(
                'UPDATE assessments SET status = ?, submitted_by = ?, submitted_at = ? WHERE id = ?'
            );
            $stmt->execute([
                $status,
                $submit ? $this->staffId() : null,
                $submit ? date('Y-m-d H:i:s') : null,
                $assessmentId,
            ]);
            if ($submit) (new ExamPeriodService($this->db, $this->userId))->markSubmitted($assessmentId);

            $this->db->commit();
            return [
                'assessment_id' => $assessmentId,
                'saved_count' => $saved,
                'roster_count' => count($roster),
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function moderate(int $assessmentId, bool $approve, ?int $studentId, string $reason): array
    {
        if (!$this->isAcademicLeader()) {
            throw new RuntimeException('Academic leadership access is required for moderation', 403);
        }
        if (!$approve && trim($reason) === '') {
            throw new RuntimeException('A rejection reason is required', 422);
        }

        $this->db->beginTransaction();
        try {
            $assessment = $this->assessment($assessmentId, true);
            if (!in_array($assessment['assessment_status'], ['submitted', 'pending_approval'], true)) {
                throw new RuntimeException('Only submitted results can be moderated', 409);
            }

            $params = [$assessmentId];
            $studentSql = '';
            if ($studentId !== null) {
                $studentSql = ' AND sae.student_id = ?';
                $params[] = $studentId;
            }
            $stmt = $this->db->prepare(
                "SELECT ar.* FROM assessment_results ar
                 JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                 WHERE ar.assessment_id = ? AND ar.is_submitted = 1{$studentSql}
                 FOR UPDATE"
            );
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$results) {
                throw new RuntimeException('No submitted learner results matched this moderation request', 404);
            }

            $update = $this->db->prepare(
                'UPDATE assessment_results SET is_approved = ?, is_submitted = ?, moderation_note = ? WHERE id = ?'
            );
            foreach ($results as $result) {
                $update->execute([
                    $approve ? 1 : 0,
                    $approve ? 1 : 0,
                    $approve ? null : trim($reason),
                    (int) $result['id'],
                ]);
                $this->recordEvent(
                    $assessmentId,
                    (int) $result['id'],
                    (int) $result['student_academic_enrollment_id'],
                    $approve ? 'approved' : 'rejected',
                    $result,
                    ['is_approved' => $approve, 'is_submitted' => $approve, 'moderation_note' => $approve ? null : trim($reason)],
                    $reason
                );
            }

            // A rejected row reopens the assessment as one controlled batch.
            // This prevents a teacher from having to overwrite already-approved
            // rows while resubmitting a complete class register.
            if (!$approve) {
                $reopen = $this->db->prepare(
                    'UPDATE assessment_results
                     SET is_submitted = 0, is_approved = 0
                     WHERE assessment_id = ?'
                );
                $reopen->execute([$assessmentId]);
            }

            $nextStatus = 'submitted';
            if (!$approve) {
                $nextStatus = 'pending_submission';
            } else {
                $remaining = $this->db->prepare(
                    'SELECT COUNT(*) FROM assessment_results WHERE assessment_id = ? AND (is_submitted = 0 OR is_approved = 0)'
                );
                $remaining->execute([$assessmentId]);
                if ((int) $remaining->fetchColumn() === 0) {
                    $expected = count($this->rosterMap((int) $assessment['academic_year_class_stream_id']));
                    $actual = $this->db->prepare('SELECT COUNT(*) FROM assessment_results WHERE assessment_id = ? AND is_approved = 1');
                    $actual->execute([$assessmentId]);
                    if ((int) $actual->fetchColumn() === $expected && $expected > 0) {
                        $nextStatus = 'approved';
                    }
                }
            }

            $assessmentUpdate = $this->db->prepare(
                "UPDATE assessments
                 SET status = ?, approved_by = ?, moderated_by = ?, moderated_at = NOW(),
                     reopened_by = ?, reopened_at = ?, reopen_reason = ?
                 WHERE id = ?"
            );
            $assessmentUpdate->execute([
                $nextStatus,
                $nextStatus === 'approved' ? $this->staffId() : null,
                $this->staffId(),
                $approve ? null : $this->staffId(),
                $approve ? null : date('Y-m-d H:i:s'),
                $approve ? null : trim($reason),
                $assessmentId,
            ]);

            $periodService = new ExamPeriodService($this->db, $this->userId);
            if ($approve) $periodService->refreshCompletion($assessmentId);
            else $this->db->prepare("UPDATE exam_periods ep JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id SET ep.status='results_open' WHERE esa.assessment_id=? AND ep.status='moderation'")->execute([$assessmentId]);

            if ($nextStatus === 'approved') {
                (new TermResultsService($this->db))->compute(
                    (int) $assessment['academic_year_class_stream_id'],
                    (int) $assessment['academic_year_term_id'],
                    (int) $assessment['learning_area_id']
                );
            }

            $this->db->commit();
            return [
                'assessment_id' => $assessmentId,
                'moderated_count' => count($results),
                'status' => $nextStatus,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function exam(int $assessmentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT es.id AS exam_schedule_id, esa.assessment_id, es.exam_name, es.exam_type,
                    es.exam_date, es.start_time, es.end_time, es.venue, es.status AS schedule_status,
                    esa.academic_year_class_stream_id, es.academic_year_class_id, es.academic_year_term_id, es.learning_area_id,
                    ep.id AS exam_period_id, ep.status AS exam_period_status,
                    a.title AS assessment_title, a.max_marks, a.assessment_type_id,
                    a.assigned_by, a.status AS assessment_status,
                    la.name AS learning_area_name, c.name AS class_name, sn.name AS stream_name,
                    at.name AS assessment_type_name
             FROM exam_schedules es
             JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=es.id
             JOIN assessments a ON a.id = esa.assessment_id
             JOIN academic_year_class_streams aycs ON aycs.id = esa.academic_year_class_stream_id
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             JOIN classes c ON c.id = ayc.class_id
             LEFT JOIN streams sn ON sn.id = aycs.stream_id
             JOIN learning_areas la ON la.id = es.learning_area_id
             JOIN assessment_types at ON at.id = a.assessment_type_id
             LEFT JOIN exam_period_timetable_entries ept ON ept.exam_schedule_id = es.id
             LEFT JOIN exam_period_class_learning_areas epcla ON epcla.id=ept.exam_period_class_learning_area_id
             LEFT JOIN exam_period_classes epc ON epc.id=epcla.exam_period_class_id
             LEFT JOIN exam_periods ep ON ep.id=epc.exam_period_id
             WHERE esa.assessment_id = ? AND es.status <> 'cancelled' LIMIT 1"
        );
        $stmt->execute([$assessmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Published exam assessment was not found', 404);
        }
        return $row;
    }

    private function assessment(int $assessmentId, bool $lock): array
    {
        $sql = "SELECT a.id AS assessment_id, a.academic_year_class_stream_id,
                       a.academic_year_term_id, a.learning_area_id, a.max_marks,
                       a.assigned_by, a.status AS assessment_status
                FROM assessments a WHERE a.id = ?" . ($lock ? ' FOR UPDATE' : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$assessmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Assessment not found', 404);
        }
        return $row;
    }

    private function roster(int $classStreamId, int $assessmentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sae.id AS enrollment_id, s.id AS student_id, s.admission_no,
                    p.first_name, p.middle_name, p.last_name,
                    ar.marks_obtained, COALESCE(ar.entry_status, 'present') AS entry_status,
                    ar.grade, ar.points, ar.remarks, ar.moderation_note,
                    ar.is_submitted, ar.is_approved, ar.updated_at
             FROM student_academic_enrollments sae
             JOIN students s ON s.id = sae.student_id AND s.status = 'active'
             JOIN persons p ON p.id = s.person_id
             LEFT JOIN assessment_results ar
               ON ar.student_academic_enrollment_id = sae.id AND ar.assessment_id = ?
             WHERE sae.academic_year_class_stream_id = ?
               AND sae.enrollment_status IN ('pending','active')
             ORDER BY p.first_name, p.middle_name, p.last_name, s.admission_no"
        );
        $stmt->execute([$assessmentId, $classStreamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function rosterMap(int $classStreamId): array
    {
        $stmt = $this->db->prepare(
            "SELECT student_id, id FROM student_academic_enrollments
             WHERE academic_year_class_stream_id = ? AND enrollment_status IN ('pending','active')"
        );
        $stmt->execute([$classStreamId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['student_id']] = (int) $row['id'];
        }
        return $map;
    }

    private function assertCanAccessAssessment(array $assessment): void
    {
        if ($this->isAcademicLeader()) {
            return;
        }
        $staffId = $this->staffId();
        if ($staffId && (int) $assessment['assigned_by'] === $staffId) {
            return;
        }

        $scope = (new TeacherScopeService($this->db))->forUser(
            ['user_id' => $this->userId, 'staff_id' => $staffId],
            null,
            (int) $assessment['academic_year_term_id']
        );
        foreach ($scope['subject_assignments'] ?? [] as $assignment) {
            if ((int) $assignment['stream_id'] === (int) $assessment['academic_year_class_stream_id']
                && (int) $assignment['learning_area_id'] === (int) $assessment['learning_area_id']) {
                return;
            }
        }
        if (in_array((int) $assessment['academic_year_class_stream_id'], $scope['class_teacher_stream_ids'] ?? [], true)) {
            return;
        }
        throw new RuntimeException('This exam is outside your assigned teaching scope', 403);
    }

    private function assertPeriodAllowsEntry(int $assessmentId): void
    {
        $status = (new ExamPeriodService($this->db, $this->userId))->periodAccess($assessmentId);
        if ($status !== null && !in_array($status, ['results_open', 'moderation'], true)) {
            throw new RuntimeException('The Headteacher or School Administrator has not opened result entry for this exam period', 409);
        }
    }

    private function staffId(): ?int
    {
        if ($this->staffId !== null) {
            return $this->staffId ?: null;
        }
        $stmt = $this->db->prepare(
            "SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id
             WHERE u.id = ? AND s.status = 'active' LIMIT 1"
        );
        $stmt->execute([$this->userId]);
        $this->staffId = (int) ($stmt->fetchColumn() ?: 0);
        return $this->staffId ?: null;
    }

    private function isAcademicLeader(): bool
    {
        foreach ($this->roleNames() as $role) {
            if (preg_match('/system administrator|school admin|headteacher|deputy head/i', $role)) {
                return true;
            }
        }
        return false;
    }

    private function roleNames(): array
    {
        if ($this->roleNames !== null) {
            return $this->roleNames;
        }
        $stmt = $this->db->prepare(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? AND r.is_active = 1'
        );
        $stmt->execute([$this->userId]);
        $this->roleNames = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        return $this->roleNames;
    }

    private function recordEvent(
        int $assessmentId,
        int $resultId,
        int $enrollmentId,
        string $eventType,
        ?array $oldValues,
        array $newValues,
        string $reason
    ): void {
        $stmt = $this->db->prepare(
            "INSERT INTO assessment_result_events
                (assessment_id, assessment_result_id, student_academic_enrollment_id,
                 event_type, old_values_json, new_values_json, reason, actor_user_id, actor_staff_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $assessmentId,
            $resultId,
            $enrollmentId,
            $eventType,
            $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            trim($reason) !== '' ? trim($reason) : null,
            $this->userId ?: null,
            $this->staffId(),
        ]);
    }
}
