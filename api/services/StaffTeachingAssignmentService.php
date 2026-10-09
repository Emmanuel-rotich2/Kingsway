<?php
namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * Teaching-assignment service — 3NF/4NF schema.
 *
 * Legacy `staff_class_assignments` (one denormalized row carrying class+stream+subject+year+role)
 * is split in the new schema:
 *   - subject_teacher  -> row in `academic_year_class_learning_area_teachers`
 *                         (bound to a learning-area-in-class-in-year context + term)
 *   - class_teacher    -> `academic_year_class_streams.class_teacher_id` (a column on the stream-in-year row)
 *
 * Reads use the shipped view `vw_staff_assignments_detailed` (subject teachers, flattened to the
 * legacy column shape) and a direct stream query (class teachers). Writes resolve the year-scoped
 * context rows and REQUIRE them to already exist (academic setup owns their creation).
 */
final class StaffTeachingAssignmentService
{
    private $db;
    public function __construct() { $this->db = Database::getInstance(); }

    /**
     * Resolve a staff member and assert they are a teacher.
     *
     * A class teacher cannot be created as a class teacher: they are created as
     * a Subject Teacher, and this service only attaches them to a stream. So the
     * school-admin picker may only offer people who already qualify as teachers.
     *
     * The test is the school's rule, not a string match on a job title: ANY role
     * whose title contains "teacher" makes a person a teacher (Headteacher, Class
     * Teacher, Subject Teacher, Intern/Student Teacher, and any future "Senior
     * Teacher"), plus the two Deputy Head offices, which are timetabled and do
     * not carry the word.
     *
     * A secondary duty such as Librarian never demotes someone out of being a
     * teacher, and any user may hold more than one role.
     */
    private function teacher(int $staffId): array
    {
        $row = $this->db->query(
            "SELECT s.id, p.first_name, p.last_name, s.status, st.name staff_type
               FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " 
              WHERE s.id = ? LIMIT 1",
            [$staffId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status'] !== 'active') throw new RuntimeException('Active teacher not found', 422);

        $teachingRoles = $this->db->query(
            "SELECT r.name
               FROM user_roles ur
               JOIN users u ON u.id = ur.user_id
               JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.person_id = u.person_id
               JOIN roles r ON r.id = ur.role_id
              WHERE s.id = ?
                AND (LOWER(r.name) LIKE '%teacher%'
                     OR r.name IN ('Deputy Head - Academic', 'Deputy Head - Discipline'))
              ORDER BY r.name",
            [$staffId]
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!$teachingRoles) {
            throw new RuntimeException(
                'Only a teacher can be appointed. Create this person as a Subject Teacher first, '
                . 'then assign the class here.', 422
            );
        }

        $row['teaching_roles'] = $teachingRoles;
        return $row;
    }

    private function currentYearId(): int
    {
        $id = (int)$this->db->query(
            "SELECT id FROM academic_years WHERE is_current = 1 OR status = 'active' ORDER BY is_current DESC, id DESC LIMIT 1"
        )->fetchColumn();
        if (!$id) throw new RuntimeException('No active academic year configured', 422);
        return $id;
    }

    /** Current term-in-year instance; falls back to the latest defined term for the year. */
    private function currentTermId(int $yearId): int
    {
        $id = (int)$this->db->query(
            "SELECT id FROM academic_year_terms
              WHERE academic_year_id = ?
              ORDER BY (status = 'current') DESC, id DESC LIMIT 1",
            [$yearId]
        )->fetchColumn();
        if (!$id) throw new RuntimeException('No term configured for the selected academic year', 422);
        return $id;
    }

    /** Resolve an existing class-in-year context row (never auto-created). */
    private function resolveYearClassId(int $yearId, int $classId): int
    {
        $id = (int)$this->db->query(
            "SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ? LIMIT 1",
            [$yearId, $classId]
        )->fetchColumn();
        if (!$id) throw new RuntimeException('This class is not set up for the selected academic year', 422);
        return $id;
    }

    // ---------------------------------------------------------------------
    // Class teachers  (academic_year_class_streams.class_teacher_id)
    // The row id exposed to callers is the academic_year_class_streams id.
    // ---------------------------------------------------------------------

    public function listClassTeachers(array $filters = []): array
    {
        $where = ['csd.class_teacher_id IS NOT NULL']; $params = [];
        if (!empty($filters['academic_year_id'])) { $where[] = 'csd.academic_year_id = ?'; $params[] = (int)$filters['academic_year_id']; }
        if (!empty($filters['teacher_id']))       { $where[] = 'csd.class_teacher_id = ?'; $params[] = (int)$filters['teacher_id']; }
        return $this->db->query(
            "SELECT csd.id, csd.class_teacher_id teacher_id, csd.class_id, csd.stream_id,
                    csd.id class_stream_id, csd.academic_year_id, csd.class_stream_status status,
                    csd.class_name class_name, csd.stream_name stream_name,
                    CONCAT(sctx.first_name,' ',sctx.last_name) teacher_name, sctx.email teacher_email
               FROM " . ReadReplicaService::qualifiedRef("class_stream_directory") . " csd
               LEFT JOIN " . ReadReplicaService::qualifiedRef("staff_context") . " sctx ON sctx.staff_id = csd.class_teacher_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY csd.class_name, csd.stream_name",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClassTeacher(int $id): ?array
    {
        foreach ($this->listClassTeachers([]) as $r) if ((int)$r['id'] === $id) return $r;
        return null;
    }

    public function saveClassTeacher(array $data, ?int $id = null, int $userId = 0): int
    {
        $staffId = (int)($data['teacher_id'] ?? $data['staff_id'] ?? 0);
        $classId = (int)($data['class_id'] ?? 0);
        $streamId = (int)($data['stream_id'] ?? $data['class_stream_id'] ?? 0);
        $yearId = (int)($data['academic_year_id'] ?? 0) ?: $this->currentYearId();
        if (!$staffId || !$classId) throw new RuntimeException('teacher_id and class_id are required', 422);
        $this->teacher($staffId);

        // When editing, the id IS the academic_year_class_streams row.
        if ($id) {
            $aycsId = $id;
            $exists = $this->db->query("SELECT id FROM academic_year_class_streams WHERE id = ? LIMIT 1", [$aycsId])->fetchColumn();
            if (!$exists) throw new RuntimeException('Class stream assignment not found', 404);
        } else {
            $aycId = $this->resolveYearClassId($yearId, $classId);
            $aycsId = (int)$this->db->query(
                "SELECT id FROM academic_year_class_streams WHERE academic_year_class_id = ?" .
                ($streamId ? " AND stream_id = ?" : "") . " LIMIT 1",
                $streamId ? [$aycId, $streamId] : [$aycId]
            )->fetchColumn();
            if (!$aycsId) throw new RuntimeException('This class/stream is not set up for the selected academic year', 422);
        }

        $current = $this->db->query("SELECT class_teacher_id FROM academic_year_class_streams WHERE id = ?", [$aycsId])->fetchColumn();
        if ($current && (int)$current !== $staffId) {
            throw new RuntimeException('This class/stream already has an active class teacher', 409);
        }
        $this->db->query("UPDATE academic_year_class_streams SET class_teacher_id = ? WHERE id = ?", [$staffId, $aycsId]);
        return (int)$aycsId;
    }

    /** Clear the class-teacher on a stream-in-year row. */
    public function removeClassTeacher(int $id): void
    {
        $this->db->query("UPDATE academic_year_class_streams SET class_teacher_id = NULL WHERE id = ?", [$id]);
    }

    // ---------------------------------------------------------------------
    // Subject teachers  (academic_year_class_learning_area_teachers)
    // The row id exposed to callers is the teacher-assignment row id.
    // ---------------------------------------------------------------------

    public function listSubjectAssignments(array $filters = []): array
    {
        $where = ["v.role = 'subject_teacher'"]; $params = [];
        foreach (['teacher_id'=>'staff_id','subject_id'=>'subject_id','class_id'=>'class_id','academic_year_id'=>'academic_year_id'] as $key=>$col) {
            if (isset($filters[$key]) && $filters[$key] !== '') { $where[] = "v.$col = ?"; $params[] = $filters[$key]; }
        }
        if (!empty($filters['search'])) {
            $where[] = "(v.staff_name LIKE ? OR v.subject_name LIKE ? OR v.class_name LIKE ?)";
            $q = '%' . $filters['search'] . '%'; array_push($params, $q, $q, $q);
        }
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $whereSql = implode(' AND ', $where);

        $total = (int)$this->db->query("SELECT COUNT(*) FROM vw_staff_assignments_detailed v WHERE $whereSql", $params)->fetchColumn();
        $rows = $this->db->query(
            "SELECT v.id, v.staff_id teacher_id, v.subject_id, v.class_id, v.stream_id, v.class_stream_id,
                    v.academic_year_id, v.staff_name teacher_name, v.subject_name, v.class_name, v.stream_name
               FROM vw_staff_assignments_detailed v
              WHERE $whereSql
              ORDER BY v.staff_name, v.subject_name
              LIMIT $limit OFFSET $offset",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['items' => $rows, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]];
    }

    public function getSubjectAssignment(int $id): ?array
    {
        $row = $this->db->query(
            "SELECT v.id, v.staff_id teacher_id, v.subject_id, v.class_id, v.stream_id, v.class_stream_id,
                    v.academic_year_id, v.staff_name teacher_name, v.subject_name, v.class_name, v.stream_name
               FROM vw_staff_assignments_detailed v
              WHERE v.id = ? AND v.role = 'subject_teacher' LIMIT 1",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveSubjectAssignment(array $data, ?int $id = null, int $userId = 0): int
    {
        $staffId = (int)($data['teacher_id'] ?? $data['staff_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);
        $classId = (int)($data['class_id'] ?? 0);
        $streamId = (int)($data['stream_id'] ?? $data['academic_year_class_stream_id'] ?? 0);
        $yearId = (int)($data['academic_year_id'] ?? 0) ?: $this->currentYearId();
        if (!$staffId || !$subjectId || !$classId || !$streamId) throw new RuntimeException('teacher_id, subject_id, class_id and stream_id are required', 422);
        $this->teacher($staffId);

        $role = in_array(($data['role'] ?? 'subject_teacher'), ['subject_teacher','assistant','hod'], true) ? $data['role'] : 'subject_teacher';
        $termId = $this->currentTermId($yearId);

        // Resolve the learning-area-in-class-in-year context (must already exist).
        $aycId = $this->resolveYearClassId($yearId, $classId);
        $areaId = (int)$this->db->query(
            "SELECT id FROM academic_year_class_learning_areas WHERE academic_year_class_id = ? AND learning_area_id = ? LIMIT 1",
            [$aycId, $subjectId]
        )->fetchColumn();
        if (!$areaId) throw new RuntimeException('This learning area is not set up for the class in the selected academic year', 422);

        // Grade 4-9 commonly assigns different subject teachers to parallel
        // streams. Persist that relationship at stream level when supplied;
        // the older class-level table remains available for assignments that
        // intentionally apply to every stream.
        if ($streamId > 0) {
            $streamValid = (int)$this->db->query(
                "SELECT COUNT(*) FROM academic_year_class_streams
                 WHERE id = ? AND academic_year_class_id = ? AND status IN ('planning','active')",
                [$streamId, $aycId]
            )->fetchColumn();
            if (!$streamValid) throw new RuntimeException('This stream is not part of the selected class and academic year', 422);
            $contextId = (int)$this->db->query(
                "SELECT sla.id FROM " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_areas") . "
                 JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " cla ON cla.id = sla.academic_year_class_learning_area_id
                 WHERE sla.academic_year_class_stream_id = ? AND cla.learning_area_id = ? LIMIT 1",
                [$streamId, $subjectId]
            )->fetchColumn();
            if (!$contextId) throw new RuntimeException('This learning area is not configured for the selected stream', 422);
            if ($id) {
                $this->db->query(
                    "UPDATE academic_year_class_stream_learning_area_teachers
                     SET academic_year_class_stream_id = ?, academic_year_class_stream_learning_area_id = ?, academic_year_term_id = ?, learning_area_id = ?, staff_id = ?, role = ?
                     WHERE id = ?",
                    [$streamId, $contextId, $termId, $subjectId, $staffId, $role, $id]
                );
                return $id;
            }
            $conflict = $this->db->query(
                "SELECT id FROM academic_year_class_stream_learning_area_teachers
                 WHERE academic_year_class_stream_id = ? AND academic_year_term_id = ?
                   AND learning_area_id = ? AND staff_id = ? AND role = ? LIMIT 1",
                [$streamId, $termId, $subjectId, $staffId, $role]
            )->fetchColumn();
            if ($conflict) throw new RuntimeException('Duplicate stream subject assignment', 409);
            $this->db->query(
                "INSERT INTO academic_year_class_stream_learning_area_teachers
                    (academic_year_class_stream_id, academic_year_class_stream_learning_area_id, academic_year_term_id, learning_area_id, staff_id, role)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$streamId, $contextId, $termId, $subjectId, $staffId, $role]
            );
            return (int)$this->db->lastInsertId();
        }

        if ($id) {
            $this->db->query(
                "UPDATE academic_year_class_learning_area_teachers
                    SET academic_year_class_learning_area_id = ?, academic_year_term_id = ?, staff_id = ?, role = ?
                  WHERE id = ?",
                [$areaId, $termId, $staffId, $role, $id]
            );
            return $id;
        }

        // UNIQUE(area, term, staff, role) — surface a clean 409 instead of a driver error.
        $conflict = $this->db->query(
            "SELECT id FROM academic_year_class_learning_area_teachers
              WHERE academic_year_class_learning_area_id = ? AND academic_year_term_id = ? AND staff_id = ? AND role = ? LIMIT 1",
            [$areaId, $termId, $staffId, $role]
        )->fetchColumn();
        if ($conflict) throw new RuntimeException('Duplicate active subject assignment', 409);

        $this->db->query(
            "INSERT INTO academic_year_class_learning_area_teachers
                (academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)
             VALUES (?, ?, ?, ?)",
            [$areaId, $termId, $staffId, $role]
        );
        return (int)$this->db->lastInsertId();
    }

    /** Remove a subject-teacher assignment row (no soft-delete column exists on the target table). */
    public function removeSubjectAssignment(int $id): void
    {
        $this->db->query("DELETE FROM academic_year_class_learning_area_teachers WHERE id = ?", [$id]);
    }

    /** @deprecated legacy single-table remove; routes to the subject-teacher table. */
    public function remove(int $id): void { $this->removeSubjectAssignment($id); }
}
