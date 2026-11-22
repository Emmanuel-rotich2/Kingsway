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
               FROM staff s
               JOIN persons p ON p.id = s.person_id
               LEFT JOIN staff_types st ON st.id = s.staff_type_id
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
            "SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        if (!$id) throw new RuntimeException('No active academic year configured', 422);
        return $id;
    }

    /**
     * The term an allocation belongs to. An explicit term must belong to the
     * year. Otherwise: the running term, else the next term to open (so a new
     * year is prepared from Term 1, not its last term), else the last term.
     */
    private function currentTermId(int $yearId, int $requestedTermId = 0): int
    {
        if ($requestedTermId > 0) {
            $id = (int)$this->db->query(
                "SELECT id FROM academic_year_terms WHERE id = ? AND academic_year_id = ? LIMIT 1",
                [$requestedTermId, $yearId]
            )->fetchColumn();
            if (!$id) throw new RuntimeException('This term is not part of the selected academic year', 422);
            return $id;
        }
        // Same rule as every planning page (AcademicPeriodService): during a
        // holiday allocations are prepared for the next term to open.
        [, $id] = (new AcademicPeriodService($this->db))->planningTerm($yearId);
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

    /**
     * Every open stream of one academic year with its class teacher, if any,
     * so unassigned streams show up as gaps to fill. The year defaults to the
     * current one; closed (completed) streams are left out.
     */
    public function listClassTeachers(array $filters = []): array
    {
        $yearId = (int)($filters['academic_year_id'] ?? 0) ?: $this->currentYearId();
        $where = ['csd.academic_year_id = ?', "csd.class_stream_status <> 'completed'"];
        $params = [$yearId];
        if (!empty($filters['teacher_id'])) { $where[] = 'csd.class_teacher_id = ?'; $params[] = (int)$filters['teacher_id']; }
        return $this->classTeacherRows(implode(' AND ', $where), $params);
    }

    public function getClassTeacher(int $id): ?array
    {
        return $this->classTeacherRows('csd.id = ?', [$id])[0] ?? null;
    }

    private function classTeacherRows(string $whereSql, array $params): array
    {
        $rows = $this->db->query(
            "SELECT csd.id, csd.class_teacher_id teacher_id, csd.class_id, csd.stream_id,
                    csd.id class_stream_id, csd.academic_year_id, csd.class_stream_status status,
                    csd.class_name class_name, csd.stream_name stream_name, csd.grade_level, csd.level_id,
                    csd.year_name academic_year,
                    CONCAT(sctx.first_name,' ',sctx.last_name) teacher_name, sctx.email teacher_email
               FROM " . ReadReplicaService::qualifiedRef("class_stream_directory") . " csd
               LEFT JOIN " . ReadReplicaService::qualifiedRef("staff_context") . " sctx ON sctx.staff_id = csd.class_teacher_id
              WHERE $whereSql",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            // A class teacher id whose staff record no longer exists is not a
            // class teacher: report it so the stream is reassigned.
            $row['teacher_missing'] = !empty($row['teacher_id']) && trim((string) ($row['teacher_name'] ?? '')) === '';
        }
        unset($row);
        usort($rows, static fn($a, $b) =>
            [\App\API\Services\ClassLessonLoadService::classOrder((string) $a['class_name']), (string) $a['stream_name']]
            <=> [\App\API\Services\ClassLessonLoadService::classOrder((string) $b['class_name']), (string) $b['stream_name']]);
        return $rows;
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

        // A new assignment must not silently replace another teacher; an edit
        // of this stream's row is how the class teacher is changed.
        $current = $this->db->query("SELECT class_teacher_id FROM academic_year_class_streams WHERE id = ?", [$aycsId])->fetchColumn();
        if (!$id && $current && (int)$current !== $staffId) {
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
    // Subject teachers. Two physical shapes share one logical list:
    //   scope 'class'  -> academic_year_class_learning_area_teachers
    //                     (applies to every stream of the class)
    //   scope 'stream' -> academic_year_class_stream_learning_area_teachers
    //                     (one stream; Grade 4-9 parallel streams)
    // An id is only unique together with its scope.
    // ---------------------------------------------------------------------

    public static function normalizeScope(mixed $scope): string
    {
        return strtolower(trim((string) $scope)) === 'stream' ? 'stream' : 'class';
    }

    /** Columns shared by list/get: one row per assignment, not per stream. */
    private const SUBJECT_SELECT = "SELECT DISTINCT v.id, v.assignment_scope, v.staff_id teacher_id, v.subject_id, v.class_id,
                    CASE WHEN v.assignment_scope = 'stream' COLLATE utf8mb4_unicode_ci THEN v.stream_id END AS stream_id,
                    CASE WHEN v.assignment_scope = 'stream' COLLATE utf8mb4_unicode_ci THEN v.class_stream_id END AS class_stream_id,
                    v.academic_year_class_id, v.academic_year_id, v.role,
                    v.staff_no, v.staff_name teacher_name, v.subject_name, v.class_name,
                    CASE WHEN v.assignment_scope = 'stream' COLLATE utf8mb4_unicode_ci THEN v.stream_name ELSE 'All streams' END AS stream_name,
                    v.academic_year
               FROM vw_staff_assignments_detailed v";

    public function listSubjectAssignments(array $filters = []): array
    {
        $where = ["v.role IN ('subject_teacher','assistant','hod')"]; $params = [];
        foreach (['teacher_id'=>'staff_id','staff_id'=>'staff_id','subject_id'=>'subject_id','class_id'=>'class_id','academic_year_id'=>'academic_year_id','academic_year_term_id'=>'academic_year_term_id'] as $key=>$col) {
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

        $total = (int)$this->db->query(
            "SELECT COUNT(*) FROM (SELECT DISTINCT v.assignment_scope, v.id FROM vw_staff_assignments_detailed v WHERE $whereSql) x",
            $params
        )->fetchColumn();
        $rows = $this->db->query(
            self::SUBJECT_SELECT . " WHERE $whereSql ORDER BY teacher_name, subject_name, class_name LIMIT $limit OFFSET $offset",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['items' => $rows, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]];
    }

    public function getSubjectAssignment(int $id, string $scope = 'class'): ?array
    {
        $row = $this->db->query(
            self::SUBJECT_SELECT . " WHERE v.id = ? AND v.assignment_scope = ? COLLATE utf8mb4_unicode_ci LIMIT 1",
            [$id, self::normalizeScope($scope)]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Resolve the stream picked in the UI to an academic_year_class_streams id
     * for this class-in-year. Accepts either form callers send: the
     * academic_year_class_streams id itself, or a streams.id from the stream
     * dropdowns. Returns 0 when no stream was chosen (class-wide assignment).
     */
    private function resolveClassStreamId(array $data, int $aycId): int
    {
        $aycsId = (int)($data['academic_year_class_stream_id'] ?? 0);
        $streamId = (int)($data['stream_id'] ?? 0);
        if ($aycsId <= 0 && $streamId <= 0) return 0;
        if ($aycsId > 0) {
            $ok = (int)$this->db->query(
                "SELECT id FROM academic_year_class_streams WHERE id = ? AND academic_year_class_id = ? AND status IN ('planning','active')",
                [$aycsId, $aycId]
            )->fetchColumn();
            if ($ok) return $ok;
            throw new RuntimeException('This stream is not part of the selected class and academic year', 422);
        }
        $id = (int)$this->db->query(
            "SELECT id FROM academic_year_class_streams WHERE academic_year_class_id = ? AND stream_id = ? AND status IN ('planning','active') LIMIT 1",
            [$aycId, $streamId]
        )->fetchColumn();
        if ($id) return $id;
        throw new RuntimeException('This stream is not part of the selected class and academic year', 422);
    }

    /**
     * Create or update a subject-teacher assignment. With a stream it is stored
     * at stream level; without one it applies to every stream of the class.
     * On update, $data['assignment_scope'] names the row being edited; a
     * change of scope moves the assignment to the other table.
     *
     * @return array{id:int, assignment_scope:string}
     */
    public function saveSubjectAssignmentScoped(array $data, ?int $id = null, int $userId = 0): array
    {
        $staffId = (int)($data['teacher_id'] ?? $data['staff_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);
        $classId = (int)($data['class_id'] ?? 0);
        $yearId = (int)($data['academic_year_id'] ?? 0) ?: $this->currentYearId();
        if (!$staffId || !$subjectId || !$classId) throw new RuntimeException('teacher_id, subject_id and class_id are required', 422);
        $this->teacher($staffId);

        $role = (string) ($data['role'] ?? 'subject_teacher');
        if (!in_array($role, ['subject_teacher','assistant','hod'], true)) $role = 'subject_teacher';
        $termId = $this->currentTermId($yearId, (int)($data['academic_year_term_id'] ?? 0));

        $aycId = $this->resolveYearClassId($yearId, $classId);
        $areaId = (int)$this->db->query(
            "SELECT id FROM academic_year_class_learning_areas WHERE academic_year_class_id = ? AND learning_area_id = ? LIMIT 1",
            [$aycId, $subjectId]
        )->fetchColumn();
        if (!$areaId) throw new RuntimeException('This learning area is not set up for the class in the selected academic year', 422);

        $classStreamId = $this->resolveClassStreamId($data, $aycId);
        $targetScope = $classStreamId > 0 ? 'stream' : 'class';
        $currentScope = self::normalizeScope($data['assignment_scope'] ?? $targetScope);

        $owns = !$this->db->getConnection()->inTransaction();
        if ($owns) $this->db->getConnection()->beginTransaction();
        try {
            if ($id && $currentScope !== $targetScope) {
                // Scope changed (all streams <-> one stream): move the row.
                $this->removeSubjectAssignment($id, $currentScope);
                $id = null;
            }
            $result = $targetScope === 'stream'
                ? $this->writeStreamAssignment($id, $classStreamId, $subjectId, $termId, $staffId, $role)
                : $this->writeClassAssignment($id, $areaId, $termId, $staffId, $role);
            if ($owns) $this->db->getConnection()->commit();
            return ['id' => $result, 'assignment_scope' => $targetScope];
        } catch (\Throwable $e) {
            if ($owns && $this->db->getConnection()->inTransaction()) $this->db->getConnection()->rollBack();
            throw $e;
        }
    }

    /** Back-compatible wrapper: returns the id only. */
    public function saveSubjectAssignment(array $data, ?int $id = null, int $userId = 0): int
    {
        return $this->saveSubjectAssignmentScoped($data, $id, $userId)['id'];
    }

    private function writeStreamAssignment(?int $id, int $classStreamId, int $subjectId, int $termId, int $staffId, string $role): int
    {
        $contextId = (int)$this->db->query(
            "SELECT sla.id FROM " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_areas") . " sla
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " cla ON cla.id = sla.academic_year_class_learning_area_id
             WHERE sla.academic_year_class_stream_id = ? AND cla.learning_area_id = ? LIMIT 1",
            [$classStreamId, $subjectId]
        )->fetchColumn();
        if (!$contextId) throw new RuntimeException('This learning area is not configured for the selected stream', 422);

        $conflict = (int)$this->db->query(
            "SELECT id FROM academic_year_class_stream_learning_area_teachers
              WHERE academic_year_class_stream_id = ? AND academic_year_term_id = ? AND learning_area_id = ?
                AND staff_id = ? AND role = ? AND status = 'active' AND id <> ? LIMIT 1",
            [$classStreamId, $termId, $subjectId, $staffId, $role, (int)$id]
        )->fetchColumn();
        if ($conflict) throw new RuntimeException('Duplicate stream subject assignment', 409);

        if ($id) {
            $this->db->query(
                "UPDATE academic_year_class_stream_learning_area_teachers
                    SET academic_year_class_stream_id = ?, academic_year_class_stream_learning_area_id = ?, academic_year_term_id = ?,
                        learning_area_id = ?, staff_id = ?, role = ?, status = 'active'
                  WHERE id = ?",
                [$classStreamId, $contextId, $termId, $subjectId, $staffId, $role, $id]
            );
            return $id;
        }
        $this->db->query(
            "INSERT INTO academic_year_class_stream_learning_area_teachers
                (academic_year_class_stream_id, academic_year_class_stream_learning_area_id, academic_year_term_id, learning_area_id, staff_id, role, status)
             VALUES (?, ?, ?, ?, ?, ?, 'active')",
            [$classStreamId, $contextId, $termId, $subjectId, $staffId, $role]
        );
        return (int)$this->db->lastInsertId();
    }

    private function writeClassAssignment(?int $id, int $areaId, int $termId, int $staffId, string $role): int
    {
        // UNIQUE(area, term, staff, role) — surface a clean 409 instead of a driver error.
        $conflict = (int)$this->db->query(
            "SELECT id FROM academic_year_class_learning_area_teachers
              WHERE academic_year_class_learning_area_id = ? AND academic_year_term_id = ? AND staff_id = ? AND role = ? AND id <> ? LIMIT 1",
            [$areaId, $termId, $staffId, $role, (int)$id]
        )->fetchColumn();
        if ($conflict) throw new RuntimeException('Duplicate active subject assignment', 409);

        if ($id) {
            $this->db->query(
                "UPDATE academic_year_class_learning_area_teachers
                    SET academic_year_class_learning_area_id = ?, academic_year_term_id = ?, staff_id = ?, role = ?
                  WHERE id = ?",
                [$areaId, $termId, $staffId, $role, $id]
            );
            return $id;
        }
        $this->db->query(
            "INSERT INTO academic_year_class_learning_area_teachers
                (academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)
             VALUES (?, ?, ?, ?)",
            [$areaId, $termId, $staffId, $role]
        );
        return (int)$this->db->lastInsertId();
    }

    /**
     * Remove a subject-teacher assignment. Class-level rows are deleted (the
     * table has no status column); stream-level rows are ended so history
     * survives.
     */
    public function removeSubjectAssignment(int $id, string $scope = 'class'): void
    {
        if (self::normalizeScope($scope) === 'stream') {
            $this->db->query("UPDATE academic_year_class_stream_learning_area_teachers SET status = 'ended' WHERE id = ?", [$id]);
            return;
        }
        $this->db->query("DELETE FROM academic_year_class_learning_area_teachers WHERE id = ?", [$id]);
    }

    /** @deprecated legacy single-table remove; routes to the subject-teacher table. */
    public function remove(int $id, string $scope = 'class'): void { $this->removeSubjectAssignment($id, $scope); }
}
