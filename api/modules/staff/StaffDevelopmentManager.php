<?php
declare(strict_types=1);

namespace App\API\Modules\staff;

use App\API\Services\ReadReplicaService;
use PDO;
use RuntimeException;

/**
 * Self-service staff development records and supervisor-scoped mentoring.
 * Every method receives a staff ID resolved from the authenticated session.
 */
final class StaffDevelopmentManager
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function listLearningGoals(int $staffId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, goal_text, category, target_date, status, created_at, updated_at
               FROM staff_learning_goals WHERE staff_id = ? ORDER BY target_date IS NULL, target_date, id DESC'
        );
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createLearningGoal(int $staffId, array $data): int
    {
        $goal = trim((string) ($data['goal_text'] ?? ''));
        if ($goal === '' || mb_strlen($goal) > 2000) {
            throw new RuntimeException('A learning goal of 1–2,000 characters is required.', 422);
        }
        $category = trim((string) ($data['category'] ?? 'Professional')) ?: 'Professional';
        $status = $this->goalStatus($data['status'] ?? 'not_started');
        $targetDate = $this->optionalDate($data['target_date'] ?? null);
        $stmt = $this->db->prepare(
            'INSERT INTO staff_learning_goals (staff_id, goal_text, category, target_date, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$staffId, $goal, $category, $targetDate, $status]);
        return (int) $this->db->lastInsertId();
    }

    public function updateLearningGoal(int $staffId, int $goalId, array $data): void
    {
        $sets = [];
        $values = [];
        if (array_key_exists('goal_text', $data)) {
            $goal = trim((string) $data['goal_text']);
            if ($goal === '' || mb_strlen($goal) > 2000) throw new RuntimeException('A valid learning goal is required.', 422);
            $sets[] = 'goal_text = ?'; $values[] = $goal;
        }
        if (array_key_exists('category', $data)) { $sets[] = 'category = ?'; $values[] = trim((string) $data['category']); }
        if (array_key_exists('target_date', $data)) { $sets[] = 'target_date = ?'; $values[] = $this->optionalDate($data['target_date']); }
        if (array_key_exists('status', $data)) { $sets[] = 'status = ?'; $values[] = $this->goalStatus($data['status']); }
        $this->updateOwned('staff_learning_goals', $staffId, $goalId, $sets, $values);
    }

    public function deleteLearningGoal(int $staffId, int $goalId): void
    {
        $stmt = $this->db->prepare('DELETE FROM staff_learning_goals WHERE id = ? AND staff_id = ?');
        $stmt->execute([$goalId, $staffId]);
        if ($stmt->rowCount() === 0) throw new RuntimeException('Learning goal not found.', 404);
    }

    public function listCompetencyChecklist(int $staffId, int $academicYearId = 0, int $termId = 0): array
    {
        $kpiTemplates = ReadReplicaService::masterRef('staff_kpi_templates');
        $sql = 'SELECT kt.id, kt.kpi_name AS competency, sc.category_name AS domain,
                       COALESCE(kt.measurement_criteria, kt.description) AS description,
                       r.self_rating, r.mentor_rating, COALESCE(r.mentor_validated, 0) AS mentor_validated,
                       r.evidence
                  FROM ' . $kpiTemplates . ' kt
                  INNER JOIN ' . ReadReplicaService::qualifiedRef('staff') . ' s ON s.staff_category_id = kt.staff_category_id
                  INNER JOIN ' . ReadReplicaService::qualifiedRef('staff_categories') . ' sc ON sc.id = kt.staff_category_id
                  LEFT JOIN staff_competency_self_assessments r
                    ON r.kpi_template_id = kt.id AND r.staff_id = s.id
                   AND r.academic_year_id = ? AND r.academic_year_term_id = ?
                 WHERE s.id = ? AND kt.is_active = 1
                 ORDER BY sc.category_name, kt.kpi_name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$academicYearId, $termId, $staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveCompetencyRating(int $staffId, int $templateId, array $data): void
    {
        $rating = filter_var($data['self_rating'] ?? null, FILTER_VALIDATE_INT);
        if ($rating === false || $rating < 1 || $rating > 4) throw new RuntimeException('Self-rating must be between 1 and 4.', 422);
        $kpiTemplates = ReadReplicaService::masterRef('staff_kpi_templates');
        $valid = $this->db->prepare(
            'SELECT kt.id FROM ' . $kpiTemplates . ' kt
             INNER JOIN ' . ReadReplicaService::qualifiedRef('staff') . ' s ON s.staff_category_id = kt.staff_category_id
             WHERE kt.id = ? AND s.id = ? AND kt.is_active = 1 LIMIT 1'
        );
        $valid->execute([$templateId, $staffId]);
        if (!$valid->fetchColumn()) throw new RuntimeException('Competency item not found.', 404);
        $evidence = trim((string) ($data['evidence'] ?? ''));
        $yearId = max(0, (int) ($data['academic_year_id'] ?? 0));
        $termId = max(0, (int) ($data['academic_year_term_id'] ?? $data['term_id'] ?? 0));
        $stmt = $this->db->prepare(
            'INSERT INTO staff_competency_self_assessments
                (staff_id, kpi_template_id, academic_year_id, academic_year_term_id, self_rating, evidence)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE self_rating = VALUES(self_rating), evidence = VALUES(evidence), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$staffId, $templateId, $yearId, $termId, $rating, $evidence !== '' ? $evidence : null]);
    }

    public function listDevelopmentProgress(int $staffId): array
    {
        $stmt = $this->db->prepare(
            'SELECT onboarding_id, status, start_date, expected_end_date, progress_percent
               FROM ' . ReadReplicaService::masterRef('vw_staff_onboarding_progress') . '
              WHERE staff_id = ? ORDER BY start_date DESC, onboarding_id DESC LIMIT 1'
        );
        $stmt->execute([$staffId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$progress) return ['milestones' => [], 'meta' => ['weeks_remaining' => null, 'progress_percent' => 0]];

        $tasks = $this->db->prepare(
            'SELECT id, task_name AS title, description, category, due_date, status,
                    completed_date AS completed_at
               FROM onboarding_tasks WHERE onboarding_id = ? ORDER BY sequence, due_date, id'
        );
        $tasks->execute([(int) $progress['onboarding_id']]);
        $milestones = $tasks->fetchAll(PDO::FETCH_ASSOC);
        return [
            'milestones' => $milestones,
            'meta' => [
                'end_date' => $progress['expected_end_date'] ?? null,
                'progress_percent' => (float) ($progress['progress_percent'] ?? 0),
                'weeks_remaining' => $this->weeksRemaining($progress['expected_end_date'] ?? null),
            ],
        ];
    }

    public function completeDevelopmentMilestone(int $staffId, int $taskId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'SELECT ot.id FROM onboarding_tasks ot
             INNER JOIN ' . ReadReplicaService::masterRef('vw_staff_onboarding_progress') . ' p
               ON p.onboarding_id = ot.onboarding_id
             WHERE ot.id = ? AND p.staff_id = ? LIMIT 1'
        );
        $stmt->execute([$taskId, $staffId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Development milestone not found.', 404);
        $update = $this->db->prepare(
            "UPDATE onboarding_tasks SET status = 'completed', completed_by = ?, completed_date = NOW() WHERE id = ?"
        );
        $update->execute([$userId, $taskId]);
    }

    public function listReflections(int $staffId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, entry_date AS date, lesson_class, went_well, improve, next_steps, created_at
               FROM staff_reflection_journal WHERE staff_id = ? ORDER BY entry_date DESC, id DESC'
        );
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createReflection(int $staffId, array $data): int
    {
        $date = $this->requiredDate($data['date'] ?? null, 'A valid reflection date is required.');
        $lessonClass = trim((string) ($data['lesson_class'] ?? ''));
        if ($lessonClass === '' || mb_strlen($lessonClass) > 255) throw new RuntimeException('Lesson/class is required.', 422);
        $stmt = $this->db->prepare(
            'INSERT INTO staff_reflection_journal (staff_id, entry_date, lesson_class, went_well, improve, next_steps)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $staffId, $date, $lessonClass,
            $this->nullableText($data['went_well'] ?? null),
            $this->nullableText($data['improve'] ?? null),
            $this->nullableText($data['next_steps'] ?? null),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listMentorMeetings(int $staffId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, meeting_date AS date, start_time AS time, location, meeting_type AS type,
                    agenda, notes, status, mentor_staff_id, staff_id
               FROM staff_mentor_meetings WHERE staff_id = ? OR mentor_staff_id = ?
              ORDER BY meeting_date DESC, start_time DESC, id DESC'
        );
        $stmt->execute([$staffId, $staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createMentorMeeting(int $staffId, array $data): int
    {
        $date = $this->requiredDate($data['date'] ?? null, 'A valid meeting date is required.');
        $mentor = $this->supervisorId($staffId);
        if (!$mentor) throw new RuntimeException('A supervisor must be assigned before scheduling a mentor meeting.', 409);
        $type = $this->meetingType($data['type'] ?? 'scheduled');
        $time = $this->time($data['time'] ?? null);
        $stmt = $this->db->prepare(
            'INSERT INTO staff_mentor_meetings (staff_id, mentor_staff_id, meeting_date, start_time, location, meeting_type, agenda, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $staffId, $mentor, $date, $time,
            $this->nullableShortText($data['location'] ?? null, 255), $type,
            $this->nullableText($data['agenda'] ?? null), $this->nullableText($data['notes'] ?? null),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateMentorMeeting(int $actorStaffId, int $meetingId, array $data): void
    {
        $stmt = $this->db->prepare(
            'SELECT staff_id, mentor_staff_id FROM staff_mentor_meetings WHERE id = ? AND (staff_id = ? OR mentor_staff_id = ?)'
        );
        $stmt->execute([$meetingId, $actorStaffId, $actorStaffId]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$meeting) throw new RuntimeException('Mentor meeting not found.', 404);
        $isOwner = (int) $meeting['staff_id'] === $actorStaffId;
        $fields = $isOwner
            ? ['date', 'time', 'location', 'type', 'agenda', 'notes']
            : ['notes'];
        $sets = []; $values = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) continue;
            $column = match ($field) {
                'date' => 'meeting_date', 'time' => 'start_time', 'location' => 'location',
                'type' => 'meeting_type', 'agenda' => 'agenda', 'notes' => 'notes',
            };
            $value = match ($field) {
                'date' => $this->requiredDate($data[$field], 'A valid meeting date is required.'),
                'time' => $this->time($data[$field]),
                'location' => $this->nullableShortText($data[$field], 255),
                'type' => $this->meetingType($data[$field]),
                default => $this->nullableText($data[$field]),
            };
            $sets[] = $column . ' = ?'; $values[] = $value;
        }
        if ($sets === []) throw new RuntimeException('No editable meeting fields were supplied.', 422);
        $values[] = $meetingId;
        $update = $this->db->prepare('UPDATE staff_mentor_meetings SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $update->execute($values);
    }

    private function supervisorId(int $staffId): ?int
    {
        $stmt = $this->db->prepare('SELECT supervisor_id FROM staff WHERE id = ? AND status = ? LIMIT 1');
        $stmt->execute([$staffId, 'active']);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function updateOwned(string $table, int $staffId, int $id, array $sets, array $values): void
    {
        if (!$sets) throw new RuntimeException('No editable fields were supplied.', 422);
        $exists = $this->db->prepare("SELECT id FROM {$table} WHERE id = ? AND staff_id = ?");
        $exists->execute([$id, $staffId]);
        if (!$exists->fetchColumn()) throw new RuntimeException('Record not found.', 404);
        $values[] = $id; $values[] = $staffId;
        $stmt = $this->db->prepare("UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = ? AND staff_id = ?');
        $stmt->execute($values);
    }

    private function goalStatus(mixed $status): string
    {
        $status = strtolower(trim((string) $status));
        if (!in_array($status, ['not_started', 'in_progress', 'completed', 'cancelled'], true)) {
            throw new RuntimeException('Invalid learning-goal status.', 422);
        }
        return $status;
    }

    private function meetingType(mixed $type): string
    {
        $type = strtolower(trim((string) $type));
        if (!in_array($type, ['scheduled', 'informal', 'observation_debrief'], true)) {
            throw new RuntimeException('Invalid mentor-meeting type.', 422);
        }
        return $type;
    }

    private function requiredDate(mixed $date, string $message): string
    {
        $date = trim((string) $date);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new RuntimeException($message, 422);
        return $date;
    }

    private function optionalDate(mixed $date): ?string
    {
        if ($date === null || trim((string) $date) === '') return null;
        return $this->requiredDate($date, 'A valid target date is required.');
    }

    private function time(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return '09:00:00';
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            throw new RuntimeException('A valid meeting time is required.', 422);
        }
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if (mb_strlen($value) > 10000) throw new RuntimeException('Text fields may not exceed 10,000 characters.', 422);
        return $value === '' ? null : $value;
    }

    private function nullableShortText(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        if (mb_strlen($value) > $max) throw new RuntimeException('The supplied value is too long.', 422);
        return $value === '' ? null : $value;
    }

    private function weeksRemaining(mixed $endDate): ?int
    {
        if (!$endDate) return null;
        try {
            $days = (int) (new \DateTimeImmutable('today'))->diff(new \DateTimeImmutable((string) $endDate))->format('%r%a');
            return max(0, (int) ceil($days / 7));
        } catch (\Throwable) {
            return null;
        }
    }
}
