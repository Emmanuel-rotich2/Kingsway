<?php

namespace App\API\Modules\academic;

use App\API\Includes\BaseAPI;
use App\API\Services\CalendarSyncService;
use App\API\Services\ExtraChargeService;
use App\API\Services\TermResultsService;
use PDO;
use Exception;
use Throwable;

/**
 * AcademicManager - owns the remaining raw academic SQL previously embedded in
 * AcademicController (teaching resources, calendar/timetable, formative
 * assessments, assessment tooling, CBC curriculum, strands/sub-strands,
 * learning outcomes, competency ratings, grading, report cards, timelines,
 * transfer requests, year rollover, deputy dashboards, curriculum tree,
 * portfolios, and assessment moderation/approval).
 *
 * All DB access uses $this->dbQuery() (raw PDO prepared statements); the
 * Database wrapper's query($sql, $params) is NOT available here because
 * BaseAPI::$db is the raw PDO connection.
 */
class AcademicManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('academic');
    }

    /** Add an exact learning-area/grade assignment boundary to a curriculum query. */
    private function addCurriculumScope(
        array &$conditions,
        array &$params,
        array $query,
        string $areaColumn,
        ?string $gradeColumn = null,
        string $prefix = 'scope'
    ): void {
        if (!array_key_exists('_scope_contexts', $query)) return;
        $contexts = is_array($query['_scope_contexts']) ? $query['_scope_contexts'] : [];
        if (!$contexts) {
            $conditions[] = '1=0';
            return;
        }
        $clauses = [];
        foreach (array_values($contexts) as $index => $context) {
            $areaId = (int) ($context['learning_area_id'] ?? 0);
            if (!$areaId) continue;
            $areaKey = ':' . $prefix . '_area_' . $index;
            $clause = $areaColumn . '=' . $areaKey;
            $params[$areaKey] = $areaId;
            $grade = trim((string) ($context['grade_level'] ?? ''));
            if ($gradeColumn && $grade !== '') {
                $gradeKey = ':' . $prefix . '_grade_' . $index;
                $clause = '(' . $clause . ' AND ' . $gradeColumn . '=' . $gradeKey . ')';
                $params[$gradeKey] = $grade;
            }
            $clauses[] = $clause;
        }
        $conditions[] = $clauses ? '(' . implode(' OR ', $clauses) . ')' : '1=0';
    }

    // ==================== TEACHING RESOURCES ====================

    public function getResources(array $data): array
    {
        try {
            $type = $data['type'] ?? 'material';
            $where = [];
            $params = [];
            $classId = isset($data['class_id']) && $data['class_id'] !== '' ? (int) $data['class_id'] : null;
            $subjectId = isset($data['subject_id']) && $data['subject_id'] !== '' ? (int) $data['subject_id'] : null;
            $termId = isset($data['term_id']) && $data['term_id'] !== '' ? (int) $data['term_id'] : null;
            $q = isset($data['q']) && trim($data['q']) !== '' ? trim($data['q']) : null;

            if ($type === 'past_paper') {
                if ($termId) { $where[] = 'p.term_id = ?'; $params[] = $termId; }
                if ($subjectId) { $where[] = 'p.subject_id = ?'; $params[] = $subjectId; }
                if ($q) { $where[] = 'p.title LIKE ?'; $params[] = "%{$q}%"; }
                $sql = "SELECT p.id, 'past_paper' AS type, p.title, p.description,
                                p.subject_id, p.learning_area_id, p.exam_year, p.exam_type,
                                p.term_id, NULL AS class_id, p.file_name, p.file_type,
                                p.file_size, p.file_path, p.status, p.download_count,
                                p.created_at,
                                la.name AS learning_area, la.name AS subject_name
                        FROM past_papers p
                        LEFT JOIN learning_areas la ON la.id = p.learning_area_id";
            } else {
                if ($classId) { $where[] = 'm.academic_year_class_stream_id IN (SELECT aycs.id FROM academic_year_class_streams aycs JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id WHERE ayc.class_id = ?)'; $params[] = $classId; }
                if ($termId) { $where[] = 'm.academic_year_term_id = ?'; $params[] = $termId; }
                if ($subjectId) { $where[] = 'm.learning_area_id = ?'; $params[] = $subjectId; }
                if ($q) { $where[] = 'm.title LIKE ?'; $params[] = "%{$q}%"; }
                $sql = "SELECT m.id, 'material' AS type, m.title, m.description,
                                m.learning_area_id AS subject_id, m.learning_area_id, NULL AS exam_year, m.resource_type AS exam_type,
                                m.academic_year_term_id AS term_id, m.academic_year_class_stream_id AS class_id, m.file_name, m.file_type,
                                m.file_size, m.file_path, m.status, m.download_count,
                                m.created_at,
                                la.name AS learning_area, la.name AS subject_name,
                                c.name AS class_name,
                                CONCAT(sp.first_name, ' ', sp.last_name) AS uploaded_by_name
                        FROM teaching_materials m
                        LEFT JOIN learning_areas la ON la.id = m.learning_area_id
                        LEFT JOIN academic_year_class_streams aycs ON aycs.id = m.academic_year_class_stream_id
                        LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                        LEFT JOIN classes c ON c.id = ayc.class_id
                        LEFT JOIN staff s ON s.id = m.teacher_id
                        LEFT JOIN persons sp ON sp.id = s.person_id";
            }
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY created_at DESC LIMIT 200';

            $rows = $this->dbQuery($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getResources');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postResources(array $data, ?array $file, ?int $userId): array
    {
        try {
            if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return $this->errorResponse('No file uploaded or upload failed.', 400);
            }
            $title = trim($data['title'] ?? '');
            if ($title === '') {
                return $this->errorResponse('A title is required.', 400);
            }
            $allowedExt = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'zip'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                return $this->errorResponse('File type not allowed.', 400);
            }

            $base = defined('UPLOAD_PATH') ? UPLOAD_PATH : dirname(__DIR__, 3) . '/uploads';
            if (!preg_match('#^(/|\\\\|[A-Za-z]:\\\\)#', $base)) {
                $base = dirname(__DIR__, 3) . '/' . $base;
            }
            $destDir = $base . '/teaching_materials';
            if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
                return $this->errorResponse('Could not create upload directory: ' . $destDir, 500);
            }
            $safeName = bin2hex(random_bytes(12)) . '.' . $ext;
            $destPath = $destDir . '/' . $safeName;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                return $this->errorResponse('Could not store the uploaded file.', 500);
            }

            $relPath = 'uploads/teaching_materials/' . $safeName;

            $teacherId = null;
            if ($userId) {
                $t = $this->dbQuery('SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ?', [$userId])->fetch(PDO::FETCH_ASSOC);
                $teacherId = $t['id'] ?? null;
            }

            $typeMap = [
                'worksheet'    => 'document',
                'notes'        => 'document',
                'past paper'   => 'document',
                'presentation' => 'presentation',
                'other'        => 'other',
            ];
            $resourceType = $typeMap[strtolower(trim($data['type'] ?? ''))] ?? 'document';

            $classStreamId = null;
            if (!empty($data['class'])) {
                $classStreamId = $this->resolveStreamIdForClass((int) $data['class']);
            }
            $academicYearId = !empty($data['academic_year_id'])
                ? (int) $data['academic_year_id']
                : $this->resolveCurrentAcademicYearId();

            $this->dbQuery(
                "INSERT INTO teaching_materials
                    (title, description, learning_area_id, teacher_id, academic_year_class_stream_id,
                     academic_year_term_id, file_path, file_name, file_type, file_size, resource_type, status, academic_year_id, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)",
                [
                    $title,
                    trim($data['description'] ?? ''),
                    !empty($data['subject_id']) ? (int) $data['subject_id'] : null,
                    $teacherId,
                    $classStreamId,
                    !empty($data['term']) ? (int) $data['term'] : null,
                    $relPath,
                    $file['name'],
                    $file['type'] ?: $ext,
                    $file['size'],
                    $resourceType,
                    $academicYearId,
                    $userId,
                ]
            );

            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Resource uploaded successfully.');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postResources');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getResourceDownloadMeta(int $id): array
    {
        try {
            $row = $this->dbQuery(
                "SELECT id, file_path, file_name, file_type, file_size FROM teaching_materials WHERE id = ?
                 UNION ALL
                 SELECT id, file_path, file_name, file_type, file_size FROM past_papers WHERE id = ?",
                [$id, $id]
            )->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['file_path'])) {
                return $this->errorResponse('Resource not found.', 404);
            }
            $abs = (strpos($row['file_path'], '/') === 0)
                ? $row['file_path']
                : dirname(__DIR__, 3) . '/' . $row['file_path'];
            if (!is_file($abs)) {
                return $this->errorResponse('File is missing on the server.', 404);
            }
            $row['absolute_path'] = $abs;

            $this->dbQuery("UPDATE teaching_materials SET download_count = download_count + 1 WHERE id = ?", [$id]);
            $this->dbQuery("UPDATE past_papers SET download_count = download_count + 1 WHERE id = ?", [$id]);

            return $this->successResponse($row);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getResourceDownloadMeta');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CALENDAR / TIMETABLE / ASSESSMENTS LIST ====================

    public function resolveCurrentAcademicYearId(): ?int
    {
        $stmt = $this->dbQuery("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /**
     * Resolve a classes.id to the current year's academic_year_class_streams id.
     */
    private function resolveStreamIdForClass(int $classId): ?int
    {
        $academicYearId = $this->resolveCurrentAcademicYearId();
        if (!$academicYearId) {
            return null;
        }
        $row = $this->dbQuery(
            "SELECT aycs.id
             FROM academic_year_classes ayc
             JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
             WHERE ayc.academic_year_id = ? AND ayc.class_id = ?
             ORDER BY aycs.status = 'active' DESC, aycs.id
             LIMIT 1",
            [$academicYearId, $classId]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }

    public function getTimetableStats(array $data): array
    {
        try {
            $termId = (int) ($data['term_id'] ?? 0);
            $where = '';
            $bindings = [];
            if ($termId > 0) {
                $where = 'WHERE te.academic_year_term_id = ?';
                $bindings[] = $termId;
            }

            $slots = $this->dbQuery(
                "SELECT te.id, te.academic_year_class_stream_id, te.academic_year_term_id,
                        te.day_of_week, te.time_slot_id, te.teacher_id,
                        ayc.class_id, c.name AS class_name
                 FROM timetable_entries te
                 JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 {$where}
                 ORDER BY te.day_of_week, te.time_slot_id",
                $bindings
            )->fetchAll(PDO::FETCH_ASSOC);

            $classes = count(array_unique(array_map(static function ($s) {
                return (int) $s['class_id'];
            }, $slots)));
            $teachers = count(array_unique(array_filter(array_map(static function ($s) {
                return (int) $s['teacher_id'];
            }, $slots))));

            return $this->successResponse([
                'slots' => $slots,
                'class_count' => $classes,
                'teacher_count' => $teachers,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getTimetableStats');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getAssessmentsList(array $data): array
    {
        try {
            $where  = ['1=1'];
            $params = [];
            if (!empty($data['class_id']))           { $where[] = 'a.academic_year_class_stream_id=:cid'; $params[':cid']  = (int) $data['class_id']; }
            if (!empty($data['term_id']))             { $where[] = 'a.academic_year_term_id=:tid';      $params[':tid']  = (int) $data['term_id']; }
            if (!empty($data['subject_id']))          { $where[] = 'a.learning_area_id=:sid';   $params[':sid']  = (int) $data['subject_id']; }
            if (!empty($data['status']))              { $where[] = 'a.status=:st';        $params[':st']   = $data['status']; }
            if (!empty($data['assessment_type_id'])) { $where[] = 'a.assessment_type_id=:atid'; $params[':atid'] = (int) $data['assessment_type_id']; }

            $rows = $this->dbQuery(
                "SELECT a.id, a.academic_year_class_stream_id, a.academic_year_term_id, a.learning_area_id, a.title, a.max_marks,
                        a.assessment_date, a.status, a.assessment_type_id,
                        c.name  AS class_name, sn.name AS stream_name,
                        la.name AS learning_area_name, la.code AS learning_area_code,
                        at.name AS type_name, at.is_formative, at.is_summative,
                        t.name  AS term_name, t.code AS term_number,
                        COUNT(DISTINCT fs.student_id) AS graded_count,
                        COUNT(DISTINCT sae.student_id) AS total_students,
                        ROUND(AVG(fs.percentage), 2)  AS average_pct
                 FROM assessments a
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 LEFT JOIN formative_scores fs ON fs.assessment_id = a.id
                 LEFT JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id = a.academic_year_class_stream_id
                        AND sae.enrollment_status IN ('active','completed')
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY a.id
                 ORDER BY a.assessment_date DESC, a.id DESC
                 LIMIT 500",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getAssessmentsList');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== FORMATIVE ASSESSMENTS ====================

    public function getFormativeAssessments(array $data, ?int $staffId = null): array
    {
        try {
            $where  = ["a.assessment_type_id IS NOT NULL", "at.is_formative = 1"];
            $params = [];

            if (!empty($data['class_id'])) {
                $where[] = "a.academic_year_class_stream_id IN (
                    SELECT aycs2.id FROM academic_year_class_streams aycs2
                    JOIN academic_year_classes ayc2 ON ayc2.id = aycs2.academic_year_class_id
                    WHERE ayc2.class_id = :cid)";
                $params[':cid'] = (int) $data['class_id'];
            }
            if (!empty($data['subject_id']))  { $where[] = "a.learning_area_id=:sid";   $params[':sid'] = (int) $data['subject_id']; }
            if (!empty($data['term_id']))     { $where[] = "a.academic_year_term_id=:tid";      $params[':tid'] = (int) $data['term_id']; }
            if (!empty($data['type_id']))     { $where[] = "a.assessment_type_id=:atid"; $params[':atid'] = (int) $data['type_id']; }
            if (!empty($data['year_id']))     { $where[] = "ayt.academic_year_id=:yid"; $params[':yid'] = (int) $data['year_id']; }
            if (!empty($data['teacher_only']) && $staffId) {
                $where[] = "(EXISTS (SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope WHERE tscope.staff_id = :ctid AND tscope.academic_year_class_stream_id = a.academic_year_class_stream_id AND tscope.scope_type = 'class_teacher') OR a.assigned_by = :ctid2)";
                $params[':ctid'] = $staffId;
                $params[':ctid2'] = $staffId;
            }
            if (!empty($data['teacher_scope_only']) && $staffId) {
                $where[] = "EXISTS (
                    SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                    WHERE tscope.staff_id = :teacher_scope_id
                      AND tscope.academic_year_class_stream_id = a.academic_year_class_stream_id
                      AND (tscope.scope_type = 'class_teacher'
                           OR (tscope.learning_area_id = a.learning_area_id
                               AND tscope.academic_year_term_id = a.academic_year_term_id))
                )";
                $params[':teacher_scope_id'] = $staffId;
            }
            if (!empty($data['subject_teacher_only']) && $staffId) {
                $where[] = "EXISTS (
                    SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                    WHERE tscope.staff_id = :stid_scope
                      AND tscope.academic_year_class_stream_id = a.academic_year_class_stream_id
                      AND tscope.learning_area_id = a.learning_area_id
                      AND tscope.academic_year_term_id = a.academic_year_term_id
                )";
                $params[':stid_scope'] = $staffId;
            }

            $rows = $this->dbQuery(
                "SELECT a.*,
                        a.assessment_date AS cat_date,
                        a.title AS name,
                        ayc.class_id AS class_id,
                        aycs.stream_id,
                        sn.name AS stream_name,
                        a.learning_area_id AS subject_id,
                        CASE a.status
                            WHEN 'pending_submission' THEN 'draft'
                            WHEN 'submitted' THEN 'active'
                            WHEN 'pending_approval' THEN 'active'
                            WHEN 'approved' THEN 'completed'
                            ELSE a.status
                        END AS status,
                        (SELECT COUNT(*) FROM formative_scores xfs WHERE xfs.assessment_id = a.id) AS student_count,
                        at.name AS type_name, at.name AS type, at.is_formative, at.is_summative,
                        la.name AS subject_name, la.code AS subject_code,
                        c.name AS class_name,
                        strand.name AS strand_name,
                        sub.name AS sub_strand_name,
                        scheme_template.title AS scheme_title,
                        lesson_template.title AS lesson_title,
                        tool.tool_name AS assessment_tool_name,
                        t.name AS term_name,
                        CONCAT(p.first_name,' ',p.last_name) AS assigned_by_name
                 FROM assessments a
                 JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 LEFT JOIN strands strand ON strand.id = a.strand_id
                 LEFT JOIN sub_strands sub ON sub.id = a.sub_strand_id
                 LEFT JOIN schemes_of_work scheme ON scheme.id = a.scheme_of_work_id
                 LEFT JOIN scheme_templates scheme_template ON scheme_template.id = scheme.scheme_template_id
                 LEFT JOIN lesson_plans lesson ON lesson.id = a.lesson_plan_id
                 LEFT JOIN lesson_templates lesson_template ON lesson_template.id = lesson.lesson_template_id
                 LEFT JOIN assessment_tools tool ON tool.id = a.assessment_tool_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 LEFT JOIN staff st ON st.id = a.assigned_by
                 LEFT JOIN persons p ON p.id = st.person_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY a.assessment_date DESC
                 LIMIT 500",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $assessmentIds = array_map('intval', array_column($rows, 'id'));
                $placeholders = implode(',', array_fill(0, count($assessmentIds), '?'));
                $outcomeRows = $this->dbQuery(
                    "SELECT map.assessment_id, outcome.id, outcome.outcome, map.sort_order
                     FROM assessment_learning_outcomes map
                     JOIN learning_outcomes outcome ON outcome.id = map.learning_outcome_id
                     WHERE map.assessment_id IN ($placeholders)
                     ORDER BY map.assessment_id, map.sort_order, outcome.id",
                    $assessmentIds
                )->fetchAll(PDO::FETCH_ASSOC);
                $rubricRows = $this->dbQuery(
                    "SELECT map.assessment_id, rubric.id, rubric.criteria_name,
                            rubric.level_1_descriptor, rubric.level_2_descriptor,
                            rubric.level_3_descriptor, rubric.level_4_descriptor,
                            rubric.points_per_level, map.weight, map.sort_order
                     FROM assessment_rubric_criteria map
                     JOIN assessment_rubrics rubric ON rubric.id = map.assessment_rubric_id
                     WHERE map.assessment_id IN ($placeholders)
                     ORDER BY map.assessment_id, map.sort_order, rubric.id",
                    $assessmentIds
                )->fetchAll(PDO::FETCH_ASSOC);

                $outcomesByAssessment = [];
                foreach ($outcomeRows as $outcome) {
                    $outcomesByAssessment[(int) $outcome['assessment_id']][] = $outcome;
                }
                $rubricsByAssessment = [];
                foreach ($rubricRows as $rubric) {
                    $rubricsByAssessment[(int) $rubric['assessment_id']][] = $rubric;
                }
                foreach ($rows as &$row) {
                    $assessmentId = (int) $row['id'];
                    $row['learning_outcomes'] = $outcomesByAssessment[$assessmentId] ?? [];
                    $row['learning_outcome_ids'] = array_map('intval', array_column($row['learning_outcomes'], 'id'));
                    $row['rubric_criteria'] = $rubricsByAssessment[$assessmentId] ?? [];
                    $row['assessment_rubric_ids'] = array_map('intval', array_column($row['rubric_criteria'], 'id'));
                }
                unset($row);
            }
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getFormativeAssessments');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Map the frontend CAT status vocabulary onto the assessments.status enum.
     */
    private function mapFormativeStatus(?string $status): string
    {
        switch (strtolower((string) $status)) {
            case 'draft':     return 'pending_submission';
            case 'active':    return 'submitted';
            case 'completed': return 'pending_approval';
            case 'pending_submission':
            case 'submitted':
            case 'pending_approval':
            case 'approved':  return strtolower((string) $status);
            default:          return 'pending_submission';
        }
    }

    /** @return int[] */
    private function normalizeFormativeIds($values): array
    {
        $ids = [];
        foreach ((array) $values as $value) {
            if (is_array($value)) {
                $value = $value['id'] ?? $value['learning_outcome_id'] ?? $value['assessment_rubric_id'] ?? 0;
            }
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    private function teacherHasFormativeScope(
        int $staffId,
        int $termId,
        int $classStreamId,
        int $learningAreaId
    ): bool {
        $allowed = $this->dbQuery(
            "SELECT 1
             FROM vw_teacher_effective_stream_learning_areas scope
             WHERE scope.staff_id = ?
               AND scope.academic_year_term_id = ?
               AND scope.academic_year_class_stream_id = ?
               AND (scope.scope_type = 'class_teacher' OR scope.learning_area_id = ?)
             LIMIT 1",
            [$staffId, $termId, $classStreamId, $learningAreaId]
        )->fetchColumn();

        return (bool) $allowed;
    }

    /**
     * Resolve and validate the immutable CBC context behind a formative task.
     * Lesson/scheme context is authoritative; caller-supplied curriculum IDs
     * must agree with it and can never widen the authenticated teacher's scope.
     */
    private function resolveFormativeContext(
        array $data,
        int $staffId,
        bool $canManageAll,
        array $existing = []
    ): array {
        $termId = (int) ($data['term_id'] ?? $data['academic_year_term_id'] ?? $existing['academic_year_term_id'] ?? 0);
        if ($termId <= 0) {
            throw new \InvalidArgumentException('term_id is required');
        }

        $lessonPlanId = (int) ($data['lesson_plan_id'] ?? $existing['lesson_plan_id'] ?? 0);
        $schemeId = (int) ($data['scheme_of_work_id'] ?? $data['scheme_id'] ?? $existing['scheme_of_work_id'] ?? 0);
        $classStreamId = (int) ($data['academic_year_class_stream_id'] ?? $existing['academic_year_class_stream_id'] ?? 0);
        $streamLearningAreaId = (int) ($data['academic_year_class_stream_learning_area_id'] ?? 0);
        $learningAreaId = (int) ($data['subject_id'] ?? $data['learning_area_id'] ?? $existing['learning_area_id'] ?? 0);
        $strandId = (int) ($data['strand_id'] ?? $existing['strand_id'] ?? 0);
        $subStrandId = (int) ($data['sub_strand_id'] ?? $data['substrand_id'] ?? $existing['sub_strand_id'] ?? 0);
        $calendarDayId = (int) ($data['academic_year_calendar_day_id'] ?? $existing['academic_year_calendar_day_id'] ?? 0);

        $requestedClassStreamId = (int) ($data['academic_year_class_stream_id'] ?? 0);
        $requestedStreamLearningAreaId = (int) ($data['academic_year_class_stream_learning_area_id'] ?? 0);
        $requestedLearningAreaId = (int) ($data['subject_id'] ?? $data['learning_area_id'] ?? 0);
        $requestedStrandId = (int) ($data['strand_id'] ?? 0);
        $requestedSubStrandId = (int) ($data['sub_strand_id'] ?? $data['substrand_id'] ?? 0);

        if ($lessonPlanId > 0) {
            $lesson = $this->dbQuery(
                "SELECT lp.id, lp.teacher_id, lp.status, lp.scheme_of_work_id,
                        lp.academic_year_calendar_day_id,
                        lp.academic_year_class_stream_learning_area_id,
                        aysla.academic_year_class_stream_id,
                        aycla.learning_area_id,
                        lt.strand_id, lt.sub_strand_id,
                        calendar.academic_year_term_id
                 FROM lesson_plans lp
                 JOIN lesson_templates lt ON lt.id = lp.lesson_template_id
                 JOIN academic_year_class_stream_learning_areas aysla
                   ON aysla.id = lp.academic_year_class_stream_learning_area_id
                 JOIN academic_year_class_learning_areas aycla
                   ON aycla.id = aysla.academic_year_class_learning_area_id
                 LEFT JOIN academic_year_calendar_days calendar_day
                   ON calendar_day.id = lp.academic_year_calendar_day_id
                 LEFT JOIN academic_year_calendar calendar
                   ON calendar.id = calendar_day.academic_year_calendar_id
                 WHERE lp.id = ? LIMIT 1",
                [$lessonPlanId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$lesson) {
                throw new \InvalidArgumentException('lesson_plan_id does not identify a lesson plan');
            }
            if (!$canManageAll && (int) $lesson['teacher_id'] !== $staffId) {
                throw new \InvalidArgumentException('The lesson plan is not assigned to this teacher');
            }
            if ($schemeId > 0 && $schemeId !== (int) $lesson['scheme_of_work_id']) {
                throw new \InvalidArgumentException('The lesson plan does not belong to the supplied scheme of work');
            }
            if (!empty($lesson['academic_year_term_id']) && (int) $lesson['academic_year_term_id'] !== $termId) {
                throw new \InvalidArgumentException('The lesson plan does not belong to the supplied term');
            }
            $lessonContext = [
                'academic_year_class_stream_id' => (int) $lesson['academic_year_class_stream_id'],
                'academic_year_class_stream_learning_area_id' => (int) $lesson['academic_year_class_stream_learning_area_id'],
                'learning_area_id' => (int) $lesson['learning_area_id'],
                'strand_id' => (int) $lesson['strand_id'],
                'sub_strand_id' => (int) $lesson['sub_strand_id'],
            ];
            $requestedContext = [
                'academic_year_class_stream_id' => $requestedClassStreamId,
                'academic_year_class_stream_learning_area_id' => $requestedStreamLearningAreaId,
                'learning_area_id' => $requestedLearningAreaId,
                'strand_id' => $requestedStrandId,
                'sub_strand_id' => $requestedSubStrandId,
            ];
            foreach ($requestedContext as $field => $requestedValue) {
                if ($requestedValue > 0 && $requestedValue !== $lessonContext[$field]) {
                    throw new \InvalidArgumentException("The supplied {$field} conflicts with the lesson plan");
                }
            }
            $schemeId = (int) $lesson['scheme_of_work_id'];
            $streamLearningAreaId = (int) $lesson['academic_year_class_stream_learning_area_id'];
            $classStreamId = (int) $lesson['academic_year_class_stream_id'];
            $learningAreaId = (int) $lesson['learning_area_id'];
            $strandId = (int) $lesson['strand_id'];
            $subStrandId = (int) $lesson['sub_strand_id'];
            $calendarDayId = (int) ($lesson['academic_year_calendar_day_id'] ?? 0);
        } elseif ($schemeId > 0) {
            $scheme = $this->dbQuery(
                "SELECT sw.id, sw.teacher_id, sw.status,
                        sw.academic_year_class_stream_learning_area_id,
                        aysla.academic_year_class_stream_id,
                        aycla.learning_area_id,
                        st.strand_id, st.sub_strand_id,
                        calendar.academic_year_term_id
                 FROM schemes_of_work sw
                 JOIN scheme_templates st ON st.id = sw.scheme_template_id
                 JOIN academic_year_class_stream_learning_areas aysla
                   ON aysla.id = sw.academic_year_class_stream_learning_area_id
                 JOIN academic_year_class_learning_areas aycla
                   ON aycla.id = aysla.academic_year_class_learning_area_id
                 LEFT JOIN academic_year_calendar calendar
                   ON calendar.id = sw.academic_year_calendar_week_id
                 WHERE sw.id = ? LIMIT 1",
                [$schemeId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$scheme) {
                throw new \InvalidArgumentException('scheme_of_work_id does not identify a scheme of work');
            }
            if (!$canManageAll && (int) $scheme['teacher_id'] !== $staffId) {
                throw new \InvalidArgumentException('The scheme of work is not assigned to this teacher');
            }
            if (!empty($scheme['academic_year_term_id']) && (int) $scheme['academic_year_term_id'] !== $termId) {
                throw new \InvalidArgumentException('The scheme of work does not belong to the supplied term');
            }
            $schemeContext = [
                'academic_year_class_stream_id' => (int) $scheme['academic_year_class_stream_id'],
                'academic_year_class_stream_learning_area_id' => (int) $scheme['academic_year_class_stream_learning_area_id'],
                'learning_area_id' => (int) $scheme['learning_area_id'],
                'strand_id' => (int) $scheme['strand_id'],
                'sub_strand_id' => (int) $scheme['sub_strand_id'],
            ];
            $requestedContext = [
                'academic_year_class_stream_id' => $requestedClassStreamId,
                'academic_year_class_stream_learning_area_id' => $requestedStreamLearningAreaId,
                'learning_area_id' => $requestedLearningAreaId,
                'strand_id' => $requestedStrandId,
                'sub_strand_id' => $requestedSubStrandId,
            ];
            foreach ($requestedContext as $field => $requestedValue) {
                if ($requestedValue > 0 && $requestedValue !== $schemeContext[$field]) {
                    throw new \InvalidArgumentException("The supplied {$field} conflicts with the scheme of work");
                }
            }
            $streamLearningAreaId = (int) $scheme['academic_year_class_stream_learning_area_id'];
            $classStreamId = (int) $scheme['academic_year_class_stream_id'];
            $learningAreaId = (int) $scheme['learning_area_id'];
            $strandId = (int) $scheme['strand_id'];
            $subStrandId = (int) $scheme['sub_strand_id'];
        }

        if ($streamLearningAreaId > 0 && $lessonPlanId <= 0 && $schemeId <= 0) {
            $streamArea = $this->dbQuery(
                "SELECT aysla.academic_year_class_stream_id, aycla.learning_area_id
                 FROM academic_year_class_stream_learning_areas aysla
                 JOIN academic_year_class_learning_areas aycla
                   ON aycla.id = aysla.academic_year_class_learning_area_id
                 WHERE aysla.id = ? LIMIT 1",
                [$streamLearningAreaId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$streamArea) {
                throw new \InvalidArgumentException('The stream learning-area context was not found');
            }
            $classStreamId = (int) $streamArea['academic_year_class_stream_id'];
            $learningAreaId = (int) $streamArea['learning_area_id'];
        }

        if ($classStreamId <= 0 && !empty($data['class_id'])) {
            $classStreamId = (int) ($this->resolveStreamIdForClass((int) $data['class_id']) ?? 0);
        }
        if ($classStreamId <= 0 || $learningAreaId <= 0) {
            throw new \InvalidArgumentException('An exact class stream and learning area are required');
        }

        $streamContext = $this->dbQuery(
            "SELECT ayc.academic_year_id, ayc.class_id
             FROM academic_year_class_streams aycs
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             WHERE aycs.id = ? LIMIT 1",
            [$classStreamId]
        )->fetch(PDO::FETCH_ASSOC);
        $termYear = $this->dbQuery(
            "SELECT academic_year_id FROM academic_year_terms WHERE id = ? LIMIT 1",
            [$termId]
        )->fetchColumn();
        if (!$streamContext || !$termYear || (int) $streamContext['academic_year_id'] !== (int) $termYear) {
            throw new \InvalidArgumentException('The term and class stream must belong to the same academic year');
        }
        if (!empty($data['class_id']) && (int) $data['class_id'] !== (int) $streamContext['class_id']) {
            throw new \InvalidArgumentException('The supplied class_id conflicts with the lesson or scheme class');
        }

        if (!$canManageAll && !$this->teacherHasFormativeScope($staffId, $termId, $classStreamId, $learningAreaId)) {
            throw new \InvalidArgumentException('The class stream or learning area is outside this teacher\'s assignment');
        }

        if (($strandId > 0) !== ($subStrandId > 0)) {
            throw new \InvalidArgumentException('Both strand_id and sub_strand_id are required together');
        }
        if ($strandId > 0) {
            $validCurriculum = $this->dbQuery(
                "SELECT 1
                 FROM strands strand
                 JOIN sub_strands sub ON sub.strand_id = strand.id
                 WHERE strand.id = ? AND sub.id = ?
                   AND strand.learning_area_id = ?
                   AND strand.status = 'active' AND sub.status = 'active'
                 LIMIT 1",
                [$strandId, $subStrandId, $learningAreaId]
            )->fetchColumn();
            if (!$validCurriculum) {
                throw new \InvalidArgumentException('The strand and sub-strand are not valid for this learning area');
            }
        }

        $outcomeIds = $this->normalizeFormativeIds(
            $data['learning_outcome_ids'] ?? $data['outcome_ids'] ?? $data['learning_outcomes'] ?? []
        );
        if (!$outcomeIds && $lessonPlanId > 0) {
            $stmt = $this->dbQuery(
                "SELECT learning_outcome_id FROM lesson_plan_outcomes
                 WHERE lesson_plan_id = ? ORDER BY sort_order, learning_outcome_id",
                [$lessonPlanId]
            );
            $outcomeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($outcomeIds) {
            if ($subStrandId <= 0) {
                throw new \InvalidArgumentException('Learning outcomes require a strand and sub-strand');
            }
            $placeholders = implode(',', array_fill(0, count($outcomeIds), '?'));
            $params = array_merge($outcomeIds, [$subStrandId]);
            $sql = "SELECT COUNT(*) FROM learning_outcomes
                    WHERE id IN ($placeholders) AND sub_strand_id = ?";
            if ((int) $this->dbQuery($sql, $params)->fetchColumn() !== count($outcomeIds)) {
                throw new \InvalidArgumentException('Every learning outcome must belong to the selected sub-strand');
            }
            if ($lessonPlanId > 0) {
                $params = array_merge([$lessonPlanId], $outcomeIds);
                $sql = "SELECT COUNT(*) FROM lesson_plan_outcomes
                        WHERE lesson_plan_id = ? AND learning_outcome_id IN ($placeholders)";
                if ((int) $this->dbQuery($sql, $params)->fetchColumn() !== count($outcomeIds)) {
                    throw new \InvalidArgumentException('Every learning outcome must be attached to the lesson plan');
                }
            }
        }

        $toolId = (int) ($data['assessment_tool_id'] ?? $data['tool_id'] ?? $existing['assessment_tool_id'] ?? 0);
        if ($toolId > 0) {
            $toolAllowed = $this->dbQuery(
                "SELECT 1 FROM assessment_tools tool
                 WHERE tool.id = ? AND tool.status = 'active'
                   AND (tool.learning_area_id = ? OR EXISTS (
                       SELECT 1 FROM assessment_tool_learning_areas map
                       WHERE map.assessment_tool_id = tool.id AND map.learning_area_id = ?
                   )) LIMIT 1",
                [$toolId, $learningAreaId, $learningAreaId]
            )->fetchColumn();
            if (!$toolAllowed) {
                throw new \InvalidArgumentException('The assessment tool is not configured for this learning area');
            }
            if ($lessonPlanId > 0) {
                $attached = $this->dbQuery(
                    "SELECT 1 FROM lesson_plan_assessment_tools
                     WHERE lesson_plan_id = ? AND assessment_tool_id = ? LIMIT 1",
                    [$lessonPlanId, $toolId]
                )->fetchColumn();
                if (!$attached) {
                    throw new \InvalidArgumentException('The assessment tool is not attached to the lesson plan');
                }
            }
        }

        $rubricIds = $this->normalizeFormativeIds(
            $data['assessment_rubric_ids'] ?? $data['rubric_ids'] ?? $data['rubrics'] ?? []
        );
        if ($rubricIds) {
            if ($toolId <= 0) {
                throw new \InvalidArgumentException('Rubric criteria require an assessment tool');
            }
            $placeholders = implode(',', array_fill(0, count($rubricIds), '?'));
            $params = array_merge($rubricIds, [$toolId]);
            if ((int) $this->dbQuery(
                "SELECT COUNT(*) FROM assessment_rubrics
                 WHERE id IN ($placeholders) AND tool_id = ?",
                $params
            )->fetchColumn() !== count($rubricIds)) {
                throw new \InvalidArgumentException('Every rubric criterion must belong to the selected assessment tool');
            }
            if ($lessonPlanId > 0) {
                $params = array_merge([$lessonPlanId], $rubricIds);
                if ((int) $this->dbQuery(
                    "SELECT COUNT(*) FROM lesson_plan_assessment_rubrics
                     WHERE lesson_plan_id = ? AND assessment_rubric_id IN ($placeholders)",
                    $params
                )->fetchColumn() !== count($rubricIds)) {
                    throw new \InvalidArgumentException('Every rubric criterion must be attached to the lesson plan');
                }
            }
        }

        return [
            'academic_year_term_id' => $termId,
            'academic_year_class_stream_id' => $classStreamId,
            'academic_year_class_stream_learning_area_id' => $streamLearningAreaId ?: null,
            'learning_area_id' => $learningAreaId,
            'scheme_of_work_id' => $schemeId ?: null,
            'lesson_plan_id' => $lessonPlanId ?: null,
            'academic_year_calendar_day_id' => $calendarDayId ?: null,
            'strand_id' => $strandId ?: null,
            'sub_strand_id' => $subStrandId ?: null,
            'assessment_tool_id' => $toolId ?: null,
            'learning_outcome_ids' => $outcomeIds,
            'assessment_rubric_ids' => $rubricIds,
        ];
    }

    private function replaceFormativeMappings(int $assessmentId, array $context): void
    {
        $this->dbQuery('DELETE FROM assessment_learning_outcomes WHERE assessment_id = ?', [$assessmentId]);
        $insertOutcome = $this->db->prepare(
            'INSERT INTO assessment_learning_outcomes (assessment_id, learning_outcome_id, sort_order) VALUES (?, ?, ?)'
        );
        foreach ($context['learning_outcome_ids'] as $index => $outcomeId) {
            $insertOutcome->execute([$assessmentId, $outcomeId, $index + 1]);
        }

        $this->dbQuery('DELETE FROM assessment_rubric_criteria WHERE assessment_id = ?', [$assessmentId]);
        $insertRubric = $this->db->prepare(
            'INSERT INTO assessment_rubric_criteria (assessment_id, assessment_rubric_id, sort_order) VALUES (?, ?, ?)'
        );
        foreach ($context['assessment_rubric_ids'] as $index => $rubricId) {
            $insertRubric->execute([$assessmentId, $rubricId, $index + 1]);
        }
    }

    /**
     * Resolve a CAT type slug/name (e.g. 'assignment', 'Quiz') to an assessment_type id.
     */
    private function resolveFormativeTypeId($type): ?int
    {
        if (empty($type)) {
            return null;
        }
        if (is_numeric($type)) {
            $id = (int) $type;
            $ok = $this->dbQuery("SELECT id FROM assessment_types WHERE id=? AND is_formative=1 LIMIT 1", [$id])->fetchColumn();
            return $ok ? (int) $ok : null;
        }
        $name = trim((string) $type);
        $row = $this->dbQuery(
            "SELECT id FROM assessment_types WHERE is_formative=1 AND (name = ? OR name LIKE ?) ORDER BY (name = ?) DESC LIMIT 1",
            [$name, "%{$name}%", $name]
        )->fetchColumn();
        return $row ? (int) $row : null;
    }

    /**
     * PUT /api/academic/formative-assessments/{id} - Update a formative assessment.
     * Accepts both the canonical contract keys (title, assessment_type_id, term_id,
     * assessment_date, class_id, subject_id, max_marks, status) and the legacy
     * my_cats/my_subject_cats form keys (name, type, cat_date).
     */
    public function putFormativeAssessments(
        int $id,
        array $data,
        int $staffId,
        bool $canManageAll = false
    ): array
    {
        try {
            $existing = $this->dbQuery(
                "SELECT id, assigned_by, academic_year_class_stream_id,
                        academic_year_calendar_day_id, learning_area_id,
                        academic_year_term_id, strand_id, sub_strand_id,
                        scheme_of_work_id, lesson_plan_id, assessment_tool_id,
                        assessment_type_id, title, description, max_marks,
                        assessment_date, status
                 FROM assessments WHERE id = :id LIMIT 1",
                [':id' => $id]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                return $this->errorResponse('Assessment not found', 404);
            }
            if (!$canManageAll && (int) $existing['assigned_by'] !== $staffId) {
                return $this->errorResponse('Only the assigning teacher may update this assessment', 403);
            }
            if ($existing['status'] === 'approved') {
                return $this->errorResponse('Approved assessments are locked and must be reopened before editing', 409);
            }

            if (!array_key_exists('learning_outcome_ids', $data)) {
                $data['learning_outcome_ids'] = array_map('intval', $this->dbQuery(
                    'SELECT learning_outcome_id FROM assessment_learning_outcomes WHERE assessment_id = ? ORDER BY sort_order',
                    [$id]
                )->fetchAll(PDO::FETCH_COLUMN));
            }
            if (!array_key_exists('assessment_rubric_ids', $data)) {
                $data['assessment_rubric_ids'] = array_map('intval', $this->dbQuery(
                    'SELECT assessment_rubric_id FROM assessment_rubric_criteria WHERE assessment_id = ? ORDER BY sort_order',
                    [$id]
                )->fetchAll(PDO::FETCH_COLUMN));
            }

            $context = $this->resolveFormativeContext($data, $staffId, $canManageAll, $existing);
            $title = trim((string) ($data['title'] ?? $data['name'] ?? $existing['title']));
            if ($title === '') {
                return $this->errorResponse('title is required', 400);
            }
            $maxMarks = (float) ($data['max_marks'] ?? $existing['max_marks']);
            if ($maxMarks <= 0) {
                return $this->errorResponse('max_marks must be greater than zero', 400);
            }

            $typeInput = $data['assessment_type_id'] ?? $data['type'] ?? $existing['assessment_type_id'];
            $typeId = $this->resolveFormativeTypeId($typeInput);
            if (!$typeId) {
                return $this->errorResponse('assessment_type_id must refer to a formative type', 400);
            }

            $status = isset($data['status'])
                ? $this->mapFormativeStatus((string) $data['status'])
                : $existing['status'];
            if ($status === 'approved' && !$canManageAll) {
                return $this->errorResponse('Teachers must submit formative assessments for approval', 403);
            }

            $this->db->beginTransaction();
            $this->dbQuery(
                "UPDATE assessments SET
                    academic_year_class_stream_id = :class_stream_id,
                    academic_year_term_id = :term_id,
                    academic_year_calendar_day_id = :calendar_day_id,
                    learning_area_id = :learning_area_id,
                    strand_id = :strand_id,
                    sub_strand_id = :sub_strand_id,
                    scheme_of_work_id = :scheme_id,
                    lesson_plan_id = :lesson_plan_id,
                    assessment_type_id = :type_id,
                    assessment_tool_id = :tool_id,
                    title = :title,
                    description = :description,
                    max_marks = :max_marks,
                    assessment_date = :assessment_date,
                    status = :status
                 WHERE id = :id",
                [
                    ':class_stream_id' => $context['academic_year_class_stream_id'],
                    ':term_id' => $context['academic_year_term_id'],
                    ':calendar_day_id' => $context['academic_year_calendar_day_id'],
                    ':learning_area_id' => $context['learning_area_id'],
                    ':strand_id' => $context['strand_id'],
                    ':sub_strand_id' => $context['sub_strand_id'],
                    ':scheme_id' => $context['scheme_of_work_id'],
                    ':lesson_plan_id' => $context['lesson_plan_id'],
                    ':type_id' => $typeId,
                    ':tool_id' => $context['assessment_tool_id'],
                    ':title' => $title,
                    ':description' => $data['description'] ?? $existing['description'],
                    ':max_marks' => $maxMarks,
                    ':assessment_date' => $data['assessment_date'] ?? $data['cat_date'] ?? $existing['assessment_date'],
                    ':status' => $status,
                    ':id' => $id,
                ]
            );
            $this->replaceFormativeMappings($id, $context);
            $this->db->commit();
            return $this->successResponse(array_merge(['id' => $id], $context), 'Formative assessment updated');
        } catch (\InvalidArgumentException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->logError($e, 'AcademicManager::putFormativeAssessments');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * DELETE /api/academic/formative-assessments/{id} - Delete a formative assessment
     * and its dependent result/scores rows.
     */
    public function deleteFormativeAssessments(
        int $id,
        int $staffId,
        bool $canManageAll = false
    ): array
    {
        try {
            $assessment = $this->dbQuery(
                "SELECT id, assigned_by, status FROM assessments WHERE id = :id LIMIT 1",
                [':id' => $id]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$assessment) {
                return $this->errorResponse('Assessment not found', 404);
            }
            if (!$canManageAll && (int) $assessment['assigned_by'] !== $staffId) {
                return $this->errorResponse('Only the assigning teacher may delete this assessment', 403);
            }
            if (!$canManageAll && $assessment['status'] !== 'pending_submission') {
                return $this->errorResponse('Only draft assessments can be deleted by a teacher', 409);
            }
            if ($assessment['status'] === 'approved') {
                return $this->errorResponse('Approved assessments cannot be deleted', 409);
            }
            $this->db->beginTransaction();
            $this->dbQuery("DELETE FROM assessment_results WHERE assessment_id = :id", [':id' => $id]);
            $this->dbQuery("DELETE FROM formative_scores WHERE assessment_id = :id", [':id' => $id]);
            $this->dbQuery("DELETE FROM assessment_history WHERE assessment_id = :id", [':id' => $id]);
            $this->dbQuery("DELETE FROM assessments WHERE id = :id", [':id' => $id]);
            $this->db->commit();
            return $this->successResponse(null, 'Formative assessment deleted');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->logError($e, 'AcademicManager::deleteFormativeAssessments');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/conduct-grades - Conduct ratings for students in a class.
     * `class` defaults to 'self' (the logged-in teacher's class); `term` optional.
     * Conduct ratings are mapped from the DB enum to report letters A/B/C/D.
     */
    public function getConductGrades(int $staffId, array $data): array
    {
        try {
            $classParam = $data['class'] ?? 'self';
            $termId = !empty($data['term']) ? (int) $data['term'] : null;

            $academicYearId = $this->resolveCurrentAcademicYearId();
            $where = [];
            $params = [];

            if ($termId) {
                $yearRow = $this->dbQuery(
                    "SELECT academic_year_id FROM academic_year_terms WHERE id = :id LIMIT 1",
                    [':id' => $termId]
                )->fetchColumn();
                $academicYearId = $yearRow ? (int) $yearRow : $academicYearId;
            }

            $streamId = null;
            if (is_numeric($classParam) && (int) $classParam > 0) {
                $streamId = $this->resolveStreamIdForClass((int) $classParam);
                if (!$streamId && $academicYearId) {
                    $streamId = $this->dbQuery(
                        "SELECT aycs.id
                         FROM academic_year_classes ayc
                         JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
                         WHERE ayc.academic_year_id = ? AND ayc.class_id = ?
                           AND EXISTS (SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope WHERE tscope.academic_year_class_stream_id = aycs.id)
                         ORDER BY aycs.id LIMIT 1",
                        [$academicYearId, (int) $classParam]
                    )->fetchColumn();
                    $streamId = $streamId ? (int) $streamId : null;
                }
            } elseif ($staffId && $academicYearId) {
                $streamId = $this->dbQuery(
                    "SELECT aycs.id
                     FROM academic_year_class_streams aycs
                     JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     WHERE ayc.academic_year_id = ? AND EXISTS (
                         SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                         WHERE tscope.staff_id = ? AND tscope.academic_year_class_stream_id = aycs.id
                           AND tscope.scope_type = 'class_teacher'
                     )
                     ORDER BY aycs.id LIMIT 1",
                    [$academicYearId, $staffId]
                )->fetchColumn();
                $streamId = $streamId ? (int) $streamId : null;
            }

            if (!$streamId) {
                return $this->successResponse([]);
            }

            $where[] = "ct.student_id IN (
                SELECT sae.student_id
                FROM student_academic_enrollments sae
                WHERE sae.academic_year_class_stream_id = ? AND sae.enrollment_status = 'active'
            )";
            $params[] = $streamId;
            if ($termId) {
                $where[] = 'ct.term_id = ?';
                $params[] = $termId;
            }

            $rows = $this->dbQuery(
                "SELECT s.id AS student_id,
                        CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                        p.first_name, p.last_name,
                        s.admission_no,
                        ct.conduct_rating, ct.conduct_comments, ct.behavior_incidents, ct.teacher_notes
                 FROM conduct_tracking ct
                 JOIN students s ON s.id = ct.student_id
                 JOIN persons p ON p.id = s.person_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.last_name, p.first_name
                 LIMIT 500",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);

            $letterMap = [
                'excellent'        => 'A',
                'good'             => 'B',
                'satisfactory'     => 'C',
                'needs_improvement' => 'D',
                'poor'             => 'D',
            ];

            $out = array_map(function ($row) use ($letterMap) {
                $rating = strtolower((string) $row['conduct_rating']);
                return [
                    'student_id'        => (int) $row['student_id'],
                    'student_name'      => $row['student_name'],
                    'first_name'        => $row['first_name'],
                    'last_name'         => $row['last_name'],
                    'admission_no'      => $row['admission_no'],
                    'conduct_grade'     => $letterMap[$rating] ?? strtoupper((string) $row['conduct_rating']),
                    'conduct_rating'    => $row['conduct_rating'],
                    'strengths'         => $row['conduct_comments'],
                    'improvement_areas' => $row['behavior_incidents'],
                    'teacher_comments'  => $row['teacher_notes'],
                ];
            }, $rows);

            return $this->successResponse(array_values($out));
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getConductGrades');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/results - Assessment result rows for the results summary page.
     * Optional filters: year_id, term_id, subject_id, class_id. When subject_teacher_only
     * is set, results are scoped to the teacher's assigned learning areas.
     */
    public function getResults(array $data, ?int $staffId = null): array
    {
        try {
            $where = [];
            $params = [];

            if (!empty($data['year_id'])) {
                $where[] = 'ayt.academic_year_id = ?';
                $params[] = (int) $data['year_id'];
            }
            if (!empty($data['term_id'])) {
                $where[] = 'a.academic_year_term_id = ?';
                $params[] = (int) $data['term_id'];
            }
            if (!empty($data['subject_id'])) {
                $where[] = 'a.learning_area_id = ?';
                $params[] = (int) $data['subject_id'];
            }
            if (!empty($data['class_id'])) {
                $where[] = 'ayc.class_id = ?';
                $params[] = (int) $data['class_id'];
            }
            if (!empty($data['subject_teacher_only']) && $staffId) {
                $where[] = "EXISTS (
                    SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                    WHERE tscope.staff_id = ?
                      AND tscope.academic_year_class_stream_id = a.academic_year_class_stream_id
                      AND tscope.learning_area_id = a.learning_area_id
                      AND tscope.academic_year_term_id = a.academic_year_term_id
                )";
                $params[] = $staffId;
            }

            $sql = "SELECT CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                           s.admission_no,
                           c.name AS class_name,
                           la.name AS subject_name,
                           ROUND(ar.marks_obtained / NULLIF(a.max_marks, 0) * 100, 2) AS marks,
                           ar.grade AS grade,
                           ar.marks_obtained,
                           a.max_marks,
                           a.title AS assessment_title,
                           a.assessment_date
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                    JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                    JOIN students s ON s.id = sae.student_id
                    JOIN persons p ON p.id = s.person_id
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id";
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= " ORDER BY c.name, p.last_name, a.assessment_date DESC LIMIT 1000";

            $rows = $this->dbQuery($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getResults');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/reports - Builds a printable report payload {columns, rows, summary}
     * for performance/assessment, attendance, behavior and discipline report types.
     */
    public function getReports(array $data, ?int $staffId = null): array
    {
        try {
            $reportType = strtolower((string) ($data['report_type'] ?? 'performance'));
            $classId = !empty($data['class_id']) ? (int) $data['class_id'] : null;
            $subjectId = !empty($data['subject_id']) ? (int) $data['subject_id'] : null;

            if (!$classId) {
                $classId = $this->resolveTeacherClassId($staffId);
            }

            $rows = [];
            $columns = [];
            $summary = [];

            switch ($reportType) {
                case 'performance':
                case 'assessment':
                    $columns = [
                        ['key' => 'student_name', 'label' => 'Student Name'],
                        ['key' => 'admission_no', 'label' => 'Adm No'],
                        ['key' => 'class_name', 'label' => 'Class'],
                        ['key' => 'subject_name', 'label' => 'Learning Area'],
                        ['key' => 'assessment_title', 'label' => 'Assessment'],
                        ['key' => 'score', 'label' => 'Score %'],
                        ['key' => 'grade', 'label' => 'Grade'],
                    ];
                    $rows = $this->reportPerformanceRows($data, $classId, $subjectId, $staffId);
                    $summary = [
                        'Students Assessed' => count(array_unique(array_column($rows, 'student_id') ?? [])),
                        'Results'           => count($rows),
                    ];
                    break;

                case 'attendance':
                    $columns = [
                        ['key' => 'student_name', 'label' => 'Student Name'],
                        ['key' => 'admission_no', 'label' => 'Adm No'],
                        ['key' => 'class_name', 'label' => 'Class'],
                        ['key' => 'session_name', 'label' => 'Session'],
                        ['key' => 'date', 'label' => 'Date'],
                        ['key' => 'status', 'label' => 'Status'],
                    ];
                    $rows = $this->reportAttendanceRows($data, $classId);
                    $summary = [
                        'Records' => count($rows),
                        'Present' => count(array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'present')),
                        'Absent'  => count(array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'absent')),
                        'Late'    => count(array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'late')),
                    ];
                    break;

                case 'behavior':
                case 'discipline':
                    $columns = [
                        ['key' => 'student_name', 'label' => 'Student Name'],
                        ['key' => 'admission_no', 'label' => 'Adm No'],
                        ['key' => 'class_name', 'label' => 'Class'],
                        ['key' => 'type', 'label' => 'Type'],
                        ['key' => 'severity', 'label' => 'Severity'],
                        ['key' => 'incident_date', 'label' => 'Date'],
                        ['key' => 'status', 'label' => 'Status'],
                        ['key' => 'action_taken', 'label' => 'Action Taken'],
                    ];
                    $rows = $this->reportDisciplineRows($data, $classId);
                    $summary = [
                        'Incidents'  => count($rows),
                        'Pending'    => count(array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'pending')),
                        'Resolved'   => count(array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'resolved')),
                    ];
                    break;

                default:
                    return $this->errorResponse('Unknown report type', 400);
            }

            return $this->successResponse([
                'columns' => $columns,
                'rows'    => $rows,
                'summary' => $summary,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getReports');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    private function resolveTeacherClassId(?int $staffId): ?int
    {
        if (!$staffId) {
            return null;
        }
        $academicYearId = $this->resolveCurrentAcademicYearId();
        if (!$academicYearId) {
            return null;
        }
        $classId = $this->dbQuery(
            "SELECT ayc.class_id
             FROM academic_year_class_streams aycs
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             WHERE ayc.academic_year_id = ? AND EXISTS (
                 SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                 WHERE tscope.staff_id = ? AND tscope.academic_year_class_stream_id = aycs.id
                   AND tscope.scope_type = 'class_teacher'
             )
             ORDER BY aycs.id LIMIT 1",
            [$academicYearId, $staffId]
        )->fetchColumn();
        return $classId ? (int) $classId : null;
    }

    private function reportPerformanceRows(array $data, ?int $classId, ?int $subjectId, ?int $staffId): array
    {
        $where = [];
        $params = [];

        if (!empty($data['year_id'])) {
            $where[] = 'ayt.academic_year_id = ?';
            $params[] = (int) $data['year_id'];
        }
        if (!empty($data['term_id'])) {
            $where[] = 'a.academic_year_term_id = ?';
            $params[] = (int) $data['term_id'];
        }
        if ($classId) {
            $where[] = 'ayc.class_id = ?';
            $params[] = $classId;
        }
        if ($subjectId) {
            $where[] = 'a.learning_area_id = ?';
            $params[] = $subjectId;
        }
        if (!empty($data['class_teacher_only']) && $staffId) {
            $where[] = "EXISTS (SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope WHERE tscope.staff_id = ? AND tscope.academic_year_class_stream_id = a.academic_year_class_stream_id AND tscope.scope_type = 'class_teacher')";
            $params[] = $staffId;
        }
        if (!empty($data['subject_teacher_only']) && $staffId) {
            $where[] = "EXISTS (
                SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                WHERE tscope.staff_id = ?
                  AND tscope.academic_year_class_stream_id = a.academic_year_class_stream_id
                  AND tscope.learning_area_id = a.learning_area_id
                  AND tscope.academic_year_term_id = a.academic_year_term_id
            )";
            $params[] = $staffId;
        }

        $sql = "SELECT ar.student_academic_enrollment_id AS enrollment_id,
                       sae.student_id AS student_id,
                       CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                       s.admission_no,
                       c.name AS class_name,
                       la.name AS subject_name,
                       a.title AS assessment_title,
                       ROUND(ar.marks_obtained / NULLIF(a.max_marks, 0) * 100, 2) AS score,
                       ar.grade AS grade
                FROM assessment_results ar
                JOIN assessments a ON a.id = ar.assessment_id
                LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                JOIN students s ON s.id = sae.student_id
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.last_name, p.first_name, a.assessment_date DESC LIMIT 1000';

        return $this->dbQuery($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function reportAttendanceRows(array $data, ?int $classId): array
    {
        $where = [];
        $params = [];
        if (!empty($data['term_id'])) {
            $where[] = 'att.term_id = ?';
            $params[] = (int) $data['term_id'];
        }
        if ($classId) {
            $where[] = 'att.class_id = ?';
            $params[] = $classId;
        }
        $sql = "SELECT att.student_id, att.student_name, att.admission_no, att.class_name,
                       att.session_name, att.date, att.status, att.absence_reason
                FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_attendance_summary') . " att";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY att.date DESC, att.student_name LIMIT 1000';
        return $this->dbQuery($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function reportDisciplineRows(array $data, ?int $classId): array
    {
        $where = [];
        $params = [];
        if (!empty($data['term_id'])) {
            $where[] = 'di.academic_year_term_id = ?';
            $params[] = (int) $data['term_id'];
        }
        if ($classId) {
            $where[] = 'ayc.class_id = ?';
            $params[] = $classId;
        }
        $sql = "SELECT CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                       s.admission_no,
                       c.name AS class_name,
                       di.type, di.severity, di.incident_date, di.status, di.action_taken
                FROM discipline_incidents di
                JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
                JOIN students s ON s.id = sae.student_id
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY di.incident_date DESC LIMIT 1000';
        return $this->dbQuery($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function postFormativeAssessments(
        array $data,
        array $user,
        bool $canManageAll = false
    ): array
    {
        try {
            // Canonical contract keys (title/assessment_type_id/term_id) are preferred;
            // the legacy my_cats form keys (name/type) are mapped onto the schema.
            $title = $data['title'] ?? $data['name'] ?? null;
            $typeId = $this->resolveFormativeTypeId($data['assessment_type_id'] ?? $data['type'] ?? null);
            $termId = $data['term_id'] ?? null;
            if (!$termId) {
                $termId = $this->dbQuery(
                    "SELECT ayt.id FROM academic_year_terms ayt
                     JOIN academic_years ay ON ay.id = ayt.academic_year_id
                     WHERE ay.is_current = 1 AND ayt.status = 'current' LIMIT 1"
                )->fetchColumn();
            }

            $required = ['max_marks'];
            foreach ($required as $f) {
                if (empty($data[$f])) return $this->errorResponse("$f is required", 400);
            }
            if (!$title) return $this->errorResponse('title is required', 400);
            if (!$typeId) return $this->errorResponse('assessment_type_id must refer to a formative type', 400);
            if (!$termId) return $this->errorResponse('term_id is required', 400);

            $staffId = $user['staff_id'] ?? null;
            if (!$staffId && !empty($user['id'])) {
                $staffId = $this->dbQuery(
                    "SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = :uid LIMIT 1",
                    [':uid' => (int) $user['id']]
                )->fetchColumn();
            }
            $staffId = $staffId ? (int) $staffId : null;
            if (!$staffId) return $this->errorResponse('A staff identity is required to create an assessment', 403);

            $data['term_id'] = (int) $termId;
            $context = $this->resolveFormativeContext($data, $staffId, $canManageAll);

            $status = $this->mapFormativeStatus($data['status'] ?? null);
            if ($status === 'approved' && !$canManageAll) {
                return $this->errorResponse('Teachers must submit formative assessments for approval', 403);
            }

            $maxMarks = (float) $data['max_marks'];
            if ($maxMarks <= 0) {
                return $this->errorResponse('max_marks must be greater than zero', 400);
            }

            $this->db->beginTransaction();
            $this->dbQuery(
                "INSERT INTO assessments
                    (academic_year_class_stream_id, learning_area_id,
                     academic_year_term_id, academic_year_calendar_day_id,
                     strand_id, sub_strand_id, scheme_of_work_id, lesson_plan_id,
                     assessment_type_id, assessment_tool_id, title, description,
                     max_marks, assessment_date, assigned_by, status)
                 VALUES
                    (:cid, :sid, :tid, :calendar_day_id, :strand_id,
                     :sub_strand_id, :scheme_id, :lesson_plan_id, :atid,
                     :tool_id, :title, :description, :marks, :dt, :aby, :st)",
                [
                    ':cid'   => $context['academic_year_class_stream_id'],
                    ':sid'   => $context['learning_area_id'],
                    ':tid'   => $context['academic_year_term_id'],
                    ':calendar_day_id' => $context['academic_year_calendar_day_id'],
                    ':strand_id' => $context['strand_id'],
                    ':sub_strand_id' => $context['sub_strand_id'],
                    ':scheme_id' => $context['scheme_of_work_id'],
                    ':lesson_plan_id' => $context['lesson_plan_id'],
                    ':title' => trim($title),
                    ':description' => trim((string) ($data['description'] ?? '')) ?: null,
                    ':marks' => $maxMarks,
                    ':dt'    => $data['assessment_date'] ?? $data['cat_date'] ?? date('Y-m-d'),
                    ':aby'   => $staffId,
                    ':atid'  => $typeId,
                    ':tool_id' => $context['assessment_tool_id'],
                    ':st'    => $status,
                ]
            );
            $assessmentId = (int) $this->db->lastInsertId();
            $this->replaceFormativeMappings($assessmentId, $context);
            $this->db->commit();
            return $this->successResponse(
                array_merge(['id' => $assessmentId], $context),
                'Formative assessment created',
                201
            );
        } catch (\InvalidArgumentException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->logError($e, 'AcademicManager::postFormativeAssessments');
            \App\API\Services\Logger::legacyError('[AcademicManager] formative assessment create failed: ' . $e->getMessage());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getFormativeAssessmentMarks(
        int $assessmentId,
        int $staffId,
        bool $canManageAll = false
    ): array
    {
        try {
            $assessment = $this->dbQuery(
                "SELECT a.id, a.assigned_by, a.academic_year_class_stream_id,
                        a.learning_area_id, a.academic_year_term_id,
                        a.max_marks, a.title,
                        c.name AS class_name
                 FROM assessments a
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 WHERE a.id=:id LIMIT 1",
                [':id' => $assessmentId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$assessment) return $this->errorResponse('Assessment not found', 404);
            if (
                !$canManageAll
                && (int) $assessment['assigned_by'] !== $staffId
                && !$this->teacherHasFormativeScope(
                    $staffId,
                    (int) $assessment['academic_year_term_id'],
                    (int) $assessment['academic_year_class_stream_id'],
                    (int) $assessment['learning_area_id']
                )
            ) {
                return $this->errorResponse('This assessment is outside your teaching scope', 403);
            }

            $rows = $this->dbQuery(
                "SELECT s.id AS student_id, p.first_name, p.last_name, s.admission_no,
                        fs.score, fs.score AS marks, fs.max_score, fs.percentage,
                        fs.cbc_grade, fs.cbc_grade AS grade, fs.remarks,
                        fs.updated_at
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 JOIN student_academic_enrollments sae ON sae.student_id = s.id
                      AND sae.academic_year_class_stream_id = :aycs
                      AND sae.enrollment_status = 'active'
                 LEFT JOIN formative_scores fs ON fs.student_id = s.id AND fs.assessment_id = :aid
                 WHERE s.status = 'active'
                 ORDER BY p.last_name, p.first_name",
                [':aycs' => (int) $assessment['academic_year_class_stream_id'], ':aid' => $assessmentId]
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getFormativeAssessmentMarks');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postFormativeAssessmentMarks(
        int $assessmentId,
        array $data,
        ?int $userId,
        int $staffId,
        bool $canManageAll = false
    ): array
    {
        try {
            $scores = $data['marks'] ?? $data['scores'] ?? [];
            if (empty($scores)) return $this->errorResponse('marks array is required', 400);

            $asmnt = $this->dbQuery(
                "SELECT id, assigned_by, academic_year_class_stream_id,
                        academic_year_term_id, learning_area_id, max_marks, status
                 FROM assessments WHERE id=:id LIMIT 1",
                [':id' => $assessmentId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$asmnt) return $this->errorResponse('Assessment not found', 404);
            if (
                !$canManageAll
                && (int) $asmnt['assigned_by'] !== $staffId
                && !$this->teacherHasFormativeScope(
                    $staffId,
                    (int) $asmnt['academic_year_term_id'],
                    (int) $asmnt['academic_year_class_stream_id'],
                    (int) $asmnt['learning_area_id']
                )
            ) {
                return $this->errorResponse('This assessment is outside your teaching scope', 403);
            }
            if ($asmnt['status'] === 'approved') {
                return $this->errorResponse('Approved assessment scores are locked', 409);
            }
            $maxMarks = (float) $asmnt['max_marks'];

            $this->db->beginTransaction();
            $ins = $this->db->prepare(
                "INSERT INTO formative_scores (assessment_id, student_id, score, max_score, remarks, entered_by)
                 VALUES (:aid, :sid, :score, :max, :rmk, :eby)
                 ON DUPLICATE KEY UPDATE score=:score, max_score=:max, remarks=:rmk, entered_by=:eby, updated_at=NOW()"
            );
            foreach ($scores as $entry) {
                $studentId = (int) ($entry['student_id'] ?? 0);
                if ($studentId <= 0) {
                    throw new \InvalidArgumentException('Every mark requires a student_id');
                }
                $isEnrolled = $this->dbQuery(
                    "SELECT 1 FROM student_academic_enrollments
                     WHERE student_id = ? AND academic_year_class_stream_id = ?
                       AND enrollment_status IN ('active','completed') LIMIT 1",
                    [$studentId, (int) $asmnt['academic_year_class_stream_id']]
                )->fetchColumn();
                if (!$isEnrolled) {
                    throw new \InvalidArgumentException('A submitted learner is not enrolled in the assessment stream');
                }
                $score = (float) ($entry['marks_obtained'] ?? $entry['score'] ?? 0);
                if ($score < 0 || $score > $maxMarks) {
                    throw new \InvalidArgumentException('Every score must be between zero and the assessment maximum');
                }
                $ins->execute([
                    ':aid'   => $assessmentId,
                    ':sid'   => $studentId,
                    ':score' => $score,
                    ':max'   => $maxMarks,
                    ':rmk'   => $entry['remarks'] ?? null,
                    ':eby'   => $userId,
                ]);
            }
            $this->db->commit();
            return $this->successResponse(['saved' => count($scores)], 'Marks saved successfully');
        } catch (\InvalidArgumentException $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            $this->logError($e, 'AcademicManager::postFormativeAssessmentMarks');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getFormativeSummary(array $data): array
    {
        try {
            $classId     = (int) ($data['class_id']     ?? 0);
            $subjectId   = (int) ($data['subject_id']   ?? 0);
            $termId      = (int) ($data['term_id']      ?? 0);
            $strandId    = (int) ($data['strand_id']    ?? 0);
            $subStrandId = (int) ($data['sub_strand_id'] ?? 0);
            $groupBy     = $data['group_by'] ?? 'learning_area';
            if (!$classId || !$termId) return $this->successResponse([], 'No filters selected — specify class_id and term_id');

            $mode = in_array($groupBy, ['learning_area', 'strand', 'sub_strand'], true) ? $groupBy : 'learning_area';
            if ($subStrandId)  $mode = 'sub_strand';
            elseif ($strandId) $mode = 'strand';

            $params = [':tid' => $termId, ':cid' => $classId, ':sid1' => $subjectId, ':sid2' => $subjectId];

            $filters = '';
            if ($strandId)    { $filters .= ' AND st.id = :stid';    $params[':stid'] = $strandId; }
            if ($subStrandId) { $filters .= ' AND ss.id = :ssid';    $params[':ssid'] = $subStrandId; }

            $gradeCase = "CASE
                            WHEN AVG(fs.percentage) >= 75 THEN 'EE'
                            WHEN AVG(fs.percentage) >= 60 THEN 'ME'
                            WHEN AVG(fs.percentage) >= 40 THEN 'AE'
                            ELSE 'BE'
                         END AS formative_grade";

            if ($mode === 'sub_strand') {
                $select = "la.id AS learning_area_id, la.name AS learning_area_name,
                        st.id AS strand_id, st.name AS strand_name,
                        ss.id AS sub_strand_id, ss.name AS sub_strand_name,
                        COUNT(fs.id) AS assessment_count,
                        ROUND(AVG(fs.percentage),2) AS formative_avg_pct, $gradeCase";
                $group  = 's.id, st.id, ss.id';
                $order  = 's.last_name, la.name, st.sort_order, ss.sort_order';
            } elseif ($mode === 'strand') {
                $select = "la.id AS learning_area_id, la.name AS learning_area_name,
                        st.id AS strand_id, st.name AS strand_name,
                        COUNT(fs.id) AS assessment_count,
                        ROUND(AVG(fs.percentage),2) AS formative_avg_pct, $gradeCase";
                $group  = 's.id, st.id';
                $order  = 's.last_name, la.name, st.sort_order';
            } else {
                $select = "la.id AS learning_area_id, la.name AS learning_area_name,
                        COUNT(fs.id) AS assessment_count,
                        ROUND(AVG(fs.percentage),2) AS formative_avg_pct, $gradeCase";
                $group  = 's.id, la.id';
                $order  = 's.last_name, la.name';
            }

            $rows = $this->dbQuery(
                "SELECT
                    s.id AS student_id,
                    CONCAT(p.first_name,' ',p.last_name) AS student_name,
                    s.admission_no,
                    $select
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 JOIN student_academic_enrollments sae ON sae.student_id = s.id
                      AND sae.academic_year_class_stream_id = :cid
                      AND sae.enrollment_status = 'active'
                 JOIN formative_scores fs ON fs.student_id = s.id
                 JOIN assessments a ON a.id = fs.assessment_id AND a.academic_year_term_id = :tid
                 JOIN assessment_types at ON at.id = a.assessment_type_id AND at.is_formative = 1
                 JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN sub_strands ss ON ss.id = a.sub_strand_id
                 LEFT JOIN strands st ON st.id = a.strand_id
                 WHERE (:sid1 = 0 OR la.id = :sid2)
                   AND s.status = 'active'
                   $filters
                 GROUP BY $group
                 ORDER BY $order",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getFormativeSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CBC: ASSESSMENT TYPES / TOOLS / CORE LISTS ====================

    public function getAssessmentTools(array $query = []): array
    {
        try {
            $conditions = ["at.status = 'active'"];
            $params = [];
            $this->addCurriculumScope($conditions, $params, $query, 'at.learning_area_id', 'at.grade_level', 'tool_scope');
            $rows = $this->dbQuery(
                "SELECT at.id, at.tool_name, at.tool_code, at.description, at.assessment_type_id, at.learning_area_id, at.grade_level,
                        a_type.name AS assessment_type_name, la.name AS learning_area_name
                 FROM assessment_tools at
                 LEFT JOIN assessment_type_classifications a_type ON a_type.id = at.assessment_type_id
                 LEFT JOIN learning_areas la ON la.id = at.learning_area_id
                 WHERE " . implode(' AND ', $conditions) . "
                 ORDER BY at.tool_name",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getAssessmentTools');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postAssessmentTools(array $data, ?int $createdBy): array
    {
        try {
            $name = trim((string) ($data['tool_name'] ?? ''));
            $typeId = (int) ($data['assessment_type_id'] ?? 0);
            $areaId = (int) ($data['learning_area_id'] ?? 0);
            if ($name === '' || !$typeId || !$areaId) {
                return $this->errorResponse('tool_name, assessment_type_id, and learning_area_id are required', 400);
            }

            $type = $this->dbQuery(
                "SELECT id FROM assessment_type_classifications WHERE id = :id AND status = 'active'",
                [':id' => $typeId]
            )->fetch(PDO::FETCH_ASSOC);
            $area = $this->dbQuery(
                "SELECT id FROM learning_areas WHERE id = :id AND status = 'active'",
                [':id' => $areaId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$type || !$area) return $this->errorResponse('Assessment type or learning area is invalid', 400);
            if (!$createdBy) return $this->errorResponse('Authenticated user is required', 401);

            $this->dbQuery(
                "INSERT INTO assessment_tools
                    (tool_name, tool_code, description, assessment_type_id, learning_area_id,
                     grade_level, competencies_assessed, created_by, status)
                 VALUES (:name, :code, :description, :type_id, :area_id, :grade, :competencies, :created_by, 'active')",
                [
                    ':name' => $name,
                    ':code' => trim((string) ($data['tool_code'] ?? '')) ?: null,
                    ':description' => trim((string) ($data['description'] ?? '')) ?: null,
                    ':type_id' => $typeId,
                    ':area_id' => $areaId,
                    ':grade' => trim((string) ($data['grade_level'] ?? '')) ?: null,
                    ':competencies' => $data['competencies_assessed'] ?? null,
                    ':created_by' => $createdBy,
                ]
            );
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Assessment tool created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postAssessmentTools');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function putAssessmentTools(int $id, array $data): array
    {
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['tool_name', 'tool_code', 'description', 'grade_level', 'competencies_assessed', 'status'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $data[$field] === '' ? null : $data[$field];
                }
            }
            foreach (['assessment_type_id', 'learning_area_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $value = (int) $data[$field];
                    $table = $field === 'assessment_type_id' ? 'assessment_type_classifications' : 'learning_areas';
                    $valid = $this->dbQuery(
                        "SELECT id FROM $table WHERE id = :id AND status = 'active'",
                        [':id' => $value]
                    )->fetch(PDO::FETCH_ASSOC);
                    if (!$valid) return $this->errorResponse("Invalid $field", 400);
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }
            if (!$fields) return $this->errorResponse('No fields to update', 400);
            $this->dbQuery("UPDATE assessment_tools SET " . implode(', ', $fields) . " WHERE id = :id", $params);
            return $this->successResponse(['id' => $id], 'Assessment tool updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putAssessmentTools');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteAssessmentTools(int $id): array
    {
        try {
            $this->dbQuery("UPDATE assessment_tools SET status = 'archived' WHERE id = :id", [':id' => $id]);
            return $this->successResponse(['id' => $id], 'Assessment tool archived');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deleteAssessmentTools');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getAssessmentTypes(array $data): array
    {
        try {
            $filter = $data['filter'] ?? 'all';
            $where  = ["status='active'"];
            if ($filter === 'formative')  $where[] = "is_formative=1";
            if ($filter === 'summative')  $where[] = "is_summative=1";
            if ($filter === 'national')   $where[] = "name IN ('KNEC Grade 3 Assessment','KPSEA','KJSEA')";

            $rows = $this->dbQuery("SELECT * FROM assessment_types WHERE " . implode(' AND ', $where) . " ORDER BY is_formative DESC, name")->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getAssessmentTypes');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getAssessmentClassifications(): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT id, code, name, description, is_national, is_knec_managed, grade_applicable
                 FROM assessment_type_classifications
                 WHERE status = 'active'
                 ORDER BY id"
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getAssessmentClassifications');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCoreCompetenciesList(): array
    {
        try {
            $rows = $this->dbQuery("SELECT id, code, name, description FROM core_competencies WHERE status='active' ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getCoreCompetenciesList');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCoreValuesList(): array
    {
        try {
            $rows = $this->dbQuery("SELECT id, code, name, description FROM core_values WHERE status='active' ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getCoreValuesList');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CBC: COMPETENCY RATINGS ====================

    public function getCompetencyRatings(array $data): array
    {
        try {
            $termId    = (int) ($data['term_id']    ?? 0);
            $classId   = (int) ($data['class_id']   ?? 0);
            $studentId = (int) ($data['student_id'] ?? 0);
            if (!$termId) return $this->errorResponse('term_id is required', 400);

            $where  = ['lc.term_id = :tid'];
            $params = [':tid' => $termId];
            if ($studentId) { $where[] = 'lc.student_id=:sid'; $params[':sid'] = $studentId; }
            elseif ($classId) {
                $where[] = "lc.student_id IN (SELECT sae.student_id FROM student_academic_enrollments sae
                              WHERE sae.academic_year_class_stream_id = :cid AND sae.enrollment_status = 'active')";
                $params[':cid'] = $classId;
            }

            $rows = $this->dbQuery(
                "SELECT lc.*,
                        cc.code AS competency_code, cc.name AS competency_name,
                        plc.code AS level_code, plc.name AS level_name,
                        CONCAT(p.first_name,' ',p.last_name) AS student_name,
                        s.admission_no
                 FROM learner_competencies lc
                 JOIN core_competencies cc ON cc.id = lc.competency_id
                 LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                 JOIN students s ON s.id = lc.student_id
                 JOIN persons p ON p.id = s.person_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.last_name, cc.sort_order",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getCompetencyRatings');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postCompetencyRatings(array $data, ?int $userId): array
    {
        try {
            $ratings = $data['ratings'] ?? [];
            $termId  = (int) ($data['term_id'] ?? 0);
            $acadYear = $data['academic_year'] ?? date('Y');
            if (!$termId || empty($ratings)) return $this->errorResponse('term_id and ratings are required', 400);

            $lvlRows = $this->dbQuery("SELECT id, code FROM performance_levels_cbc")->fetchAll(PDO::FETCH_ASSOC);
            $lvlMap  = [];
            foreach ($lvlRows as $lv) $lvlMap[$lv['code']] = $lv['id'];

            $this->db->beginTransaction();
            $ins = $this->db->prepare(
                "INSERT INTO learner_competencies
                    (student_id, competency_id, academic_year, term_id, performance_level_id, evidence, teacher_notes, assessed_by, assessed_date)
                 VALUES (:sid, :cid, :yr, :tid, :lvl, :ev, :notes, :aby, CURDATE())
                 ON DUPLICATE KEY UPDATE performance_level_id=:lvl, evidence=:ev, teacher_notes=:notes, assessed_by=:aby, updated_at=NOW()"
            );
            foreach ($ratings as $r) {
                $levelId = $lvlMap[$r['level_code'] ?? ''] ?? null;
                $ins->execute([
                    ':sid'   => (int) ($r['student_id'] ?? 0),
                    ':cid'   => (int) ($r['competency_id'] ?? 0),
                    ':yr'    => $acadYear,
                    ':tid'   => $termId,
                    ':lvl'   => $levelId,
                    ':ev'    => $r['evidence'] ?? null,
                    ':notes' => $r['notes'] ?? null,
                    ':aby'   => $userId,
                ]);
            }
            $this->db->commit();
            return $this->successResponse(['saved' => count($ratings)], 'Competency ratings saved');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            $this->logError($e, 'AcademicManager::postCompetencyRatings');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CBC: NATIONAL EXAMS ====================

    public function getNationalExams(array $data): array
    {
        try {
            $where  = ['1=1'];
            $params = [];
            foreach (['exam_type', 'exam_year'] as $f) {
                if (!empty($data[$f])) { $where[] = "ne.$f=:$f"; $params[":$f"] = $data[$f]; }
            }
            if (!empty($data['student_id'])) { $where[] = 'ne.student_id=:sid'; $params[':sid'] = (int) $data['student_id']; }
            if (!empty($data['class_id'])) {
                $where[] = "ne.student_id IN (SELECT sae.student_id FROM student_academic_enrollments sae
                              WHERE sae.academic_year_class_stream_id = :cid AND sae.enrollment_status = 'active')";
                $params[':cid'] = (int) $data['class_id'];
            }

            $rows = $this->dbQuery(
                "SELECT ne.*,
                        CONCAT(p.first_name,' ',p.last_name) AS student_name,
                        s.admission_no,
                        la.name AS learning_area_name
                 FROM national_exam_results ne
                 JOIN students s ON s.id = ne.student_id
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN learning_areas la ON la.id = ne.learning_area_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.last_name, ne.learning_area_id",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getNationalExams');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postNationalExams(array $data, ?int $userId): array
    {
        try {
            $results  = $data['results'] ?? [];
            $examType = $data['exam_type'] ?? '';
            $examYear = (int) ($data['exam_year'] ?? date('Y'));
            if (!$examType || empty($results)) return $this->errorResponse('exam_type and results are required', 400);

            $validTypes = ['KNEC_G3', 'KPSEA_G6', 'KJSEA_G9'];
            if (!in_array($examType, $validTypes)) return $this->errorResponse('Invalid exam_type', 400);

            $this->db->beginTransaction();
            $ins = $this->db->prepare(
                "INSERT INTO national_exam_results
                    (student_id, exam_type, exam_year, learning_area_id, score, max_score, percentage,
                     cbc_grade, raw_grade, points, pathway, remarks, entered_by, academic_year_id)
                 VALUES (:sid, :et, :ey, :la, :sc, :mx, :pct, :cg, :rg, :pt, :pw, :rmk, :eby, :ayid)
                 ON DUPLICATE KEY UPDATE
                    score=:sc, max_score=:mx, percentage=:pct, cbc_grade=:cg,
                    raw_grade=:rg, points=:pt, pathway=:pw, remarks=:rmk, entered_by=:eby, updated_at=NOW()"
            );
            foreach ($results as $r) {
                $score   = (float) ($r['score']     ?? 0);
                $max     = (float) ($r['max_score'] ?? 100);
                $pct     = $max > 0 ? round(($score / $max) * 100, 2) : 0;
                $grade   = $pct >= 75 ? 'EE' : ($pct >= 60 ? 'ME' : ($pct >= 40 ? 'AE' : 'BE'));
                $ins->execute([
                    ':sid'  => (int) ($r['student_id'] ?? 0),
                    ':et'   => $examType,
                    ':ey'   => $examYear,
                    ':la'   => (int) ($r['learning_area_id'] ?? 0),
                    ':sc'   => $score,
                    ':mx'   => $max,
                    ':pct'  => $pct,
                    ':cg'   => $grade,
                    ':rg'   => $r['raw_grade']  ?? null,
                    ':pt'   => !empty($r['points']) ? (float) $r['points'] : null,
                    ':pw'   => $r['pathway']    ?? null,
                    ':rmk'  => $r['remarks']    ?? null,
                    ':eby'  => $userId,
                    ':ayid' => !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                ]);
            }
            $this->db->commit();
            return $this->successResponse(['saved' => count($results)], 'National exam results saved');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            $this->logError($e, 'AcademicManager::postNationalExams');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CBC: STRANDS ====================

    public function getStrands(array $data): array
    {
        try {
            $laId  = (int) ($data['learning_area_id'] ?? 0);
            $familyId = (int) ($data['learning_area_family_id'] ?? 0);
            $conditions = [];
            $params = [];
            if ($laId) { $conditions[] = 's.learning_area_id=:la'; $params[':la'] = $laId; }
            elseif ($familyId) { $conditions[] = 'la.learning_area_family_id=:laf'; $params[':laf'] = $familyId; }
            $this->addCurriculumScope($conditions, $params, $data, 's.learning_area_id', 's.grade_level', 'strand_scope');
            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $rows = $this->dbQuery(
                "SELECT s.id, s.code, s.name, s.grade_level, s.level_range, s.sort_order,
                        la.id AS learning_area_id, la.name AS learning_area_name,
                        la.learning_area_family_id, laf.name AS learning_area_family
                 FROM strands s
                 LEFT JOIN learning_areas la ON la.id = s.learning_area_id
                 LEFT JOIN learning_area_families laf ON laf.id = la.learning_area_family_id
                 $where
                 ORDER BY s.grade_level, s.sort_order, s.id",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getStrands');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postStrands(array $data): array
    {
        try {
            if (empty($data['learning_area_id']) || empty($data['name'])) {
                return $this->errorResponse('learning_area_id and name are required', 400);
            }
            $code = $data['code'] ?? '';
            if (!$code) {
                $prefix = $this->dbQuery("SELECT code FROM learning_areas WHERE id=:id", [':id' => (int) $data['learning_area_id']])->fetchColumn();
                $cnt = $this->dbQuery("SELECT COUNT(*) FROM strands WHERE learning_area_id=:laid", [':laid' => (int) $data['learning_area_id']])->fetchColumn();
                $code = ($prefix ?: 'LA') . '-S' . (($cnt ?: 0) + 1);
            }
            $this->dbQuery(
                "INSERT INTO strands (learning_area_id, code, name, description, level_range, sort_order, status)
                 VALUES (:laid, :code, :name, :desc, :lr, :sort, :status)",
                [
                    ':laid' => (int) $data['learning_area_id'],
                    ':code' => $code,
                    ':name' => $data['name'],
                    ':desc' => $data['description'] ?? null,
                    ':lr' => $data['level_range'] ?? null,
                    ':sort' => (int) ($data['sort_order'] ?? 1),
                    ':status' => $data['status'] ?? 'active',
                ]
            );
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Strand created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postStrands');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function putStrands(int $id, array $data): array
    {
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['learning_area_id', 'code', 'name', 'description', 'level_range', 'sort_order', 'status'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = in_array($col, ['learning_area_id', 'sort_order']) ? (int) $data[$col] : $data[$col];
                }
            }
            if (empty($fields)) return $this->errorResponse('No fields to update', 400);
            $this->dbQuery("UPDATE strands SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->successResponse(['id' => $id], 'Strand updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putStrands');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteStrands(int $id): array
    {
        try {
            $this->dbQuery("DELETE FROM strands WHERE id=:id", [':id' => $id]);
            return $this->successResponse(null, 'Strand deleted');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deleteStrands');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CBC: CLASS STUDENTS ====================

    public function getClassStudents(int $classId): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT DISTINCT s.id, p.first_name, p.last_name, s.admission_no,
                        sae.academic_year_class_stream_id AS stream_id
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 JOIN student_academic_enrollments sae ON sae.student_id = s.id
                    AND sae.academic_year_class_stream_id = :cid
                    AND sae.enrollment_status IN ('active','completed')
                 WHERE s.status = 'active'
                 ORDER BY p.last_name, p.first_name",
                [':cid' => $classId]
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getClassStudents');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CBC: COMPUTE TERM SCORES ====================

    public function postComputeTermScores(array $data): array
    {
        try {
            $classId = (int) ($data['class_id'] ?? 0);
            $termId = (int) ($data['term_id'] ?? 0);
            $subjectId = !empty($data['subject_id']) ? (int) $data['subject_id'] : null;
            if (!empty($data['assessment_id']) && (!$classId || !$termId)) {
                $assessment = $this->dbQuery(
                    'SELECT academic_year_class_stream_id AS class_id,
                            academic_year_term_id AS term_id, learning_area_id AS subject_id
                     FROM assessments WHERE id = :id LIMIT 1',
                    [':id' => (int) $data['assessment_id']]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$assessment) return $this->errorResponse('Assessment not found', 404);
                $classId = $classId ?: (int) $assessment['class_id'];
                $termId = $termId ?: (int) $assessment['term_id'];
                $subjectId = $subjectId ?: (int) $assessment['subject_id'];
            }
            if (!$classId || !$termId) return $this->errorResponse('class_id and term_id are required', 400);
            $result = (new TermResultsService($this->db))->compute($classId, $termId, $subjectId);
            return $this->successResponse($result, "{$result['computed']} learner/learning-area scores recomputed");
        } catch (\RuntimeException $e) {
            $code = $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 400;
            return $this->errorResponse($e->getMessage(), $code);
        } catch (\Throwable $e) {
            $this->logError($e, 'AcademicManager::postComputeTermScores');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** @deprecated Superseded by TermResultsService; retained temporarily for migration comparison. */
    private function postComputeTermScoresLegacy(array $data): array
    {
        try {
            $classId   = (int) ($data['class_id']   ?? 0);
            $termId    = (int) ($data['term_id']    ?? 0);
            $subjectId = (int) ($data['subject_id'] ?? 0);
            $asmtId    = (int) ($data['assessment_id'] ?? 0);

            if ($asmtId && (!$classId || !$termId)) {
                $r = $this->dbQuery(
                    "SELECT academic_year_class_stream_id AS class_id, academic_year_term_id AS term_id,
                            learning_area_id AS subject_id FROM assessments WHERE id=:id LIMIT 1",
                    [':id' => $asmtId]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$r) return $this->errorResponse('Assessment not found', 404);
                $classId   = $classId   ?: (int) $r['class_id'];
                $termId    = $termId    ?: (int) $r['term_id'];
                $subjectId = $subjectId ?: (int) $r['subject_id'];
            }
            if (!$classId || !$termId) return $this->errorResponse('class_id and term_id are required', 400);

            $where  = ['a.academic_year_class_stream_id=:cid', 'a.academic_year_term_id=:tid'];
            $params = [':cid' => $classId, ':tid' => $termId];
            if ($subjectId) { $where[] = 'a.learning_area_id=:sid'; $params[':sid'] = $subjectId; }

            $scoreSourceSql = "
                SELECT fs.assessment_id, fs.student_id, fs.score, fs.max_score, fs.id AS score_id
                FROM formative_scores fs
                UNION ALL
                SELECT ar.assessment_id, sae.student_id, ar.marks_obtained AS score, a2.max_marks AS max_score, ar.id AS score_id
                FROM assessment_results ar
                JOIN assessments a2 ON a2.id = ar.assessment_id
                JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM formative_scores fs2
                    WHERE fs2.assessment_id = ar.assessment_id
                      AND fs2.student_id = sae.student_id
                )
            ";

            $rows = $this->dbQuery(
                "SELECT DISTINCT scored.student_id, a.learning_area_id AS subject_id
                 FROM ({$scoreSourceSql}) scored
                 JOIN assessments a ON a.id = scored.assessment_id
                 LEFT JOIN assessment_types at ON at.id = a.assessment_type_id
                 WHERE " . implode(' AND ', $where),
                $params
            )->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) return $this->successResponse(['computed' => 0], 'No scored assessments found for these filters');

            $combos = [];
            foreach ($rows as $r) {
                $key = $r['student_id'] . '_' . $r['subject_id'];
                $combos[$key] = ['student_id' => (int) $r['student_id'], 'subject_id' => (int) $r['subject_id']];
            }

            $upsert = $this->db->prepare(
                "INSERT INTO term_subject_scores
                    (student_id, term_id, subject_id,
                     formative_total, formative_max, formative_percentage, formative_grade, formative_count,
                     summative_total, summative_max, summative_percentage, summative_grade, summative_count,
                     overall_score, overall_percentage, overall_grade, overall_points, assessment_count, calculated_at)
                 VALUES
                    (:sid, :tid, :subid,
                     :ft, :fm, :fp, :fg, :fc,
                     :st, :sm, :sp, :sg, :sc,
                     :ov, :op, :og, :opts, :ac, NOW())
                 ON DUPLICATE KEY UPDATE
                     formative_total=VALUES(formative_total),
                     formative_max=VALUES(formative_max),
                     formative_percentage=VALUES(formative_percentage),
                     formative_grade=VALUES(formative_grade),
                     formative_count=VALUES(formative_count),
                     summative_total=VALUES(summative_total),
                     summative_max=VALUES(summative_max),
                     summative_percentage=VALUES(summative_percentage),
                     summative_grade=VALUES(summative_grade),
                     summative_count=VALUES(summative_count),
                     overall_score=VALUES(overall_score),
                     overall_percentage=VALUES(overall_percentage),
                     overall_grade=VALUES(overall_grade),
                     overall_points=VALUES(overall_points),
                     assessment_count=VALUES(assessment_count),
                     calculated_at=NOW()"
            );

            $computed = 0;
            foreach ($combos as $combo) {
                $stu  = $combo['student_id'];
                $subj = $combo['subject_id'];

                $agg = $this->dbQuery(
                    "SELECT
                        SUM(CASE WHEN COALESCE(at.is_formative, 0)=1 THEN scored.score ELSE 0 END)     AS ft,
                        SUM(CASE WHEN COALESCE(at.is_formative, 0)=1 THEN scored.max_score ELSE 0 END) AS fm,
                        COUNT(CASE WHEN COALESCE(at.is_formative, 0)=1 THEN 1 END)                     AS fc,
                        SUM(CASE WHEN COALESCE(at.is_summative, 1)=1 THEN scored.score ELSE 0 END)     AS st,
                        SUM(CASE WHEN COALESCE(at.is_summative, 1)=1 THEN scored.max_score ELSE 0 END) AS sm,
                        COUNT(CASE WHEN COALESCE(at.is_summative, 1)=1 THEN 1 END)                     AS sc,
                        COUNT(scored.score_id) AS ac
                     FROM ({$scoreSourceSql}) scored
                     JOIN assessments a ON a.id = scored.assessment_id
                        AND a.academic_year_term_id=:tid AND a.learning_area_id=:subid
                     LEFT JOIN assessment_types at ON at.id = a.assessment_type_id
                     WHERE scored.student_id=:stu",
                    [':tid' => $termId, ':subid' => $subj, ':stu' => $stu]
                )->fetch(PDO::FETCH_ASSOC);

                $ft = (float) ($agg['ft'] ?? 0);
                $fm = (float) ($agg['fm'] ?? 0);
                $fc = (int)   ($agg['fc'] ?? 0);
                $fp = $fm > 0 ? round(($ft / $fm) * 100, 2) : 0;
                $fg = $fp >= 75 ? 'EE' : ($fp >= 60 ? 'ME' : ($fp >= 40 ? 'AE' : 'BE'));

                $st = (float) ($agg['st'] ?? 0);
                $sm = (float) ($agg['sm'] ?? 0);
                $sc = (int)   ($agg['sc'] ?? 0);
                $sp = $sm > 0 ? round(($st / $sm) * 100, 2) : 0;
                $sg = $sp >= 75 ? 'EE' : ($sp >= 60 ? 'ME' : ($sp >= 40 ? 'AE' : 'BE'));

                $op  = round(($fp * 0.4) + ($sp * 0.6), 2);
                $og  = $op >= 75 ? 'EE' : ($op >= 60 ? 'ME' : ($op >= 40 ? 'AE' : 'BE'));
                $opts = $og === 'EE' ? 4.0 : ($og === 'ME' ? 3.0 : ($og === 'AE' ? 2.0 : 1.0));
                $ov = round(($ft + $st), 2);

                $upsert->execute([
                    ':sid'   => $stu,  ':tid' => $termId, ':subid' => $subj,
                    ':ft'    => $ft,   ':fm'  => $fm,  ':fp' => $fp,  ':fg' => $fg,  ':fc' => $fc,
                    ':st'    => $st,   ':sm'  => $sm,  ':sp' => $sp,  ':sg' => $sg,  ':sc' => $sc,
                    ':ov'    => $ov,   ':op'  => $op,  ':og' => $og,  ':opts' => $opts,
                    ':ac'    => (int) ($agg['ac'] ?? 0),
                ]);
                $computed++;
            }
            return $this->successResponse(['computed' => $computed], "$computed student-subject scores recomputed");
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postComputeTermScores');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CBC: REPORT CARD DATA ====================

    public function getReportCardData(int $studentId, int $termId): array
    {
        try {
            $termWhere  = $termId ? 'WHERE ayt.id=:tid LIMIT 1' : "WHERE ayt.status='current' LIMIT 1";
            $termParams = $termId ? [':tid' => $termId] : [];
            $term = $this->dbQuery(
                "SELECT ayt.id, ayt.term_id, ayt.academic_year_id,
                        ayt.opening_date, ayt.closing_date,
                        t.name, t.code AS term_code, ay.year_code,
                        CAST(ay.year_code AS UNSIGNED) AS year_value
                   FROM academic_year_terms ayt
                   JOIN terms t ON t.id = ayt.term_id
                   JOIN academic_years ay ON ay.id = ayt.academic_year_id
                   $termWhere",
                $termParams
            )->fetch(PDO::FETCH_ASSOC);
            if (!$term) return $this->errorResponse('Academic term not found', 404);
            $resolvedTermId = (int) $term['term_id'];
            $academicYearTermId = (int) $term['id'];

            $student = $this->dbQuery(
                "SELECT s.id, s.admission_no, sae.id AS enrollment_id,
                        sae.academic_year_class_stream_id AS class_stream_id,
                        p.first_name, p.middle_name, p.last_name,
                        c.name AS class_name, st.name AS stream_name
                 FROM student_academic_enrollments sae
                 JOIN students s ON s.id = sae.student_id
                 JOIN persons p ON p.id = s.person_id
                 JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 WHERE sae.student_id = :id AND sae.academic_year_id = :year_id
                   AND sae.enrollment_status IN ('pending','active','completed')
                 ORDER BY sae.id DESC LIMIT 1",
                [':id' => $studentId, ':year_id' => (int) $term['academic_year_id']]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$student) return $this->errorResponse('Learner was not enrolled in the selected academic year', 404);

            $scores = $this->dbQuery(
                "SELECT tss.*,
                        la.name AS subject_name, la.code AS subject_code
                 FROM term_subject_scores tss
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE tss.student_id=:sid AND tss.academic_year_term_id=:tid
                 ORDER BY la.name",
                [':sid' => $studentId, ':tid' => $academicYearTermId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $competencies = $this->dbQuery(
                "SELECT lc.competency_id, lc.performance_level_id, lc.evidence, lc.teacher_notes,
                        cc.code, cc.name AS competency_name,
                        plc.code AS level_code, plc.name AS level_name,
                        plc.code AS performance_level, plc.level AS points
                 FROM learner_competencies lc
                 JOIN core_competencies cc ON cc.id = lc.competency_id
                 LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                 WHERE lc.student_id=:sid AND lc.term_id=:tid AND lc.academic_year=:year_value",
                [':sid' => $studentId, ':tid' => $resolvedTermId, ':year_value' => (int) $term['year_value']]
            )->fetchAll(PDO::FETCH_ASSOC);

            $values = $this->dbQuery(
                "SELECT sv.value_id, sv.evidence,
                        cv.name AS value_name
                 FROM learner_values_acquisition sv
                 JOIN core_values cv ON cv.id = sv.value_id
                 WHERE sv.student_id=:sid AND sv.term_id=:tid AND sv.academic_year=:year_value",
                [':sid' => $studentId, ':tid' => $resolvedTermId, ':year_value' => (int) $term['year_value']]
            )->fetchAll(PDO::FETCH_ASSOC);

            $attendance = $this->dbQuery(
                "SELECT COUNT(*) AS total_days,
                        SUM(day_status='present') AS days_present,
                        SUM(day_status='absent') AS days_absent,
                        SUM(day_status='late') AS days_late
                 FROM (
                    SELECT sa.date,
                           CASE WHEN SUM(sa.status='present') > 0 THEN 'present'
                                WHEN SUM(sa.status='late') > 0 THEN 'late' ELSE 'absent' END AS day_status
                    FROM student_attendance sa
                    WHERE sa.student_academic_enrollment_id=:enrollment_id
                      AND sa.register_type='class'
                      AND sa.date BETWEEN :opening_date AND :closing_date
                    GROUP BY sa.date
                 ) attendance_days",
                [
                    ':enrollment_id' => (int) $student['enrollment_id'],
                    ':opening_date' => $term['opening_date'],
                    ':closing_date' => $term['closing_date'],
                ]
            )->fetch(PDO::FETCH_ASSOC);

            $ranking = $this->dbQuery(
                'SELECT * FROM student_term_rankings WHERE student_id=:sid AND academic_year_term_id=:tid LIMIT 1',
                [':sid' => $studentId, ':tid' => $academicYearTermId]
            )->fetch(PDO::FETCH_ASSOC) ?: null;
            $resultPolicy = $this->dbQuery(
                "SELECT formative_weight, summative_weight, ranking_method, publish_rank_to_guardians
                 FROM academic_result_policies
                 WHERE academic_year_id=:year_id AND status='active' LIMIT 1",
                [':year_id' => (int) $term['academic_year_id']]
            )->fetch(PDO::FETCH_ASSOC) ?: null;
            $expectedAreas = (int) $this->dbQuery(
                "SELECT COUNT(DISTINCT cla.learning_area_id)
                 FROM academic_year_class_stream_learning_areas sla
                 JOIN academic_year_class_learning_areas cla ON cla.id=sla.academic_year_class_learning_area_id
                 WHERE sla.academic_year_class_stream_id=:stream_id
                   AND sla.status IN ('planned','active','in_progress','covered')",
                [':stream_id' => (int) $student['class_stream_id']]
            )->fetchColumn();
            $completeAreas = count(array_filter($scores, static function (array $score): bool {
                return $score['overall_percentage'] !== null;
            }));

            return $this->successResponse([
                'student'      => $student,
                'term'         => $term,
                'scores'       => $scores,
                'competencies' => $competencies,
                'values'       => $values,
                'attendance'   => $attendance,
                'ranking'      => $ranking,
                'result_policy'=> $resultPolicy,
                'completeness' => [
                    'expected_learning_areas' => $expectedAreas,
                    'complete_learning_areas' => $completeAreas,
                    'release_ready' => $expectedAreas > 0 && $completeAreas === $expectedAreas,
                ],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getReportCardData');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CBC: STUDENT GROWTH ====================

    public function getStudentAssessmentHistory(array $data): array
    {
        try {
            $studentId = (int) ($data['student_id'] ?? 0);
            if (!$studentId) return $this->errorResponse('student_id is required', 400);

            $where  = ['fs.student_id=:sid'];
            $params = [':sid' => $studentId];
            if (!empty($data['term_id']))    { $where[] = 'a.academic_year_term_id=:tid'; $params[':tid'] = (int) $data['term_id']; }
            if (!empty($data['subject_id'])) { $where[] = 'a.learning_area_id=:sub';     $params[':sub'] = (int) $data['subject_id']; }

            $rows = $this->dbQuery(
                "SELECT a.id AS assessment_id, a.title, a.assessment_date, a.max_marks,
                        fs.score, fs.percentage, fs.cbc_grade,
                        at.name AS type_name, at.is_formative, at.is_summative,
                        la.name AS subject_name, la.code AS subject_code,
                        t.name AS term_name, ayt.id AS term_id, ay.year_code
                 FROM formative_scores fs
                 JOIN assessments a       ON a.id  = fs.assessment_id
                 JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                 LEFT JOIN terms t  ON t.id  = ayt.term_id
                 LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY a.assessment_date ASC, a.id ASC",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getStudentAssessmentHistory');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getStudentGrowthTrend(array $data): array
    {
        try {
            $studentId = (int) ($data['student_id']       ?? 0);
            $laId      = (int) ($data['learning_area_id'] ?? 0);
            if (!$studentId) return $this->errorResponse('student_id is required', 400);

            $where  = ['tss.student_id=:sid'];
            $params = [':sid' => $studentId];
            if ($laId) { $where[] = 'tss.subject_id=:la'; $params[':la'] = $laId; }

            $rows = $this->dbQuery(
                "SELECT t.id AS term_id, t.name AS term_name, t.code AS term_number, ay.year_code AS year,
                        la.id AS subject_id, la.name AS subject_name,
                        tss.formative_percentage, tss.summative_percentage,
                        tss.overall_percentage, tss.overall_grade, tss.overall_points
                 FROM term_subject_scores tss
                 JOIN student_academic_enrollments sae ON sae.student_id = tss.student_id
                 JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
                      AND ayt.academic_year_id = sae.academic_year_id
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY ay.year_code ASC, t.id ASC, la.name ASC",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getStudentGrowthTrend');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== TIMELINES ====================

    public function getStudentTimeline(int $studentId): array
    {
        try {
            $student = $this->dbQuery(
                "SELECT s.id, s.admission_no,
                        p.first_name, p.middle_name, p.last_name,
                        p.dob AS date_of_birth, p.gender, s.admission_date, s.status,
                        (SELECT COUNT(*) FROM student_fee_obligations sfo
                          WHERE sfo.student_academic_enrollment_id = sae.id AND sfo.is_sponsored = 1) > 0 AS is_sponsored,
                        NULL AS sponsor_name,
                        'obligation' AS sponsor_type,
                        (SELECT COALESCE(MAX(sfo2.sponsored_waiver_amount), 0) FROM student_fee_obligations sfo2
                          WHERE sfo2.student_academic_enrollment_id = sae.id AND sfo2.is_sponsored = 1) AS sponsor_waiver_percentage,
                        p.photo_url, s.nemis_number,
                        c.name AS current_class, sn.name AS current_stream,
                        st.name AS student_type
                 FROM students s
                 LEFT JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
                      AND sae.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1)
                      AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 LEFT JOIN student_types st ON st.id = s.student_type_id
                 WHERE s.id = ?",
                [$studentId]
            )->fetch(PDO::FETCH_ASSOC);

            if (!$student) return $this->errorResponse('Student not found', 404);

            $academics = $this->dbQuery(
                "SELECT ay.id AS academic_year_id, ay.year_code, ay.year_name,
                        c.name AS class_name, sn.name AS stream_name,
                        yav.avg_pct AS year_average,
                        NULL AS term1_average, NULL AS term2_average, NULL AS term3_average,
                        NULL AS overall_grade, NULL AS class_rank,
                        NULL AS attendance_percentage, NULL AS days_present, NULL AS days_absent,
                        tr.transition_type AS promotion_status,
                        pc.name AS promoted_to_class,
                        NULL AS teacher_comments, NULL AS head_teacher_comments,
                        sae.enrolled_on
                 FROM student_academic_enrollments sae
                 JOIN academic_years ay ON ay.id = sae.academic_year_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 LEFT JOIN student_transitions tr ON tr.student_id = sae.student_id
                      AND tr.from_student_academic_enrollment_id = sae.id
                 LEFT JOIN student_academic_enrollments to_sae ON to_sae.id = tr.to_student_academic_enrollment_id
                 LEFT JOIN academic_year_class_streams to_aycs ON to_aycs.id = to_sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes to_ayc ON to_ayc.id = to_aycs.academic_year_class_id
                 LEFT JOIN classes pc ON pc.id = to_ayc.class_id
                 LEFT JOIN (
                     SELECT tss.student_id, ayt.academic_year_id, ROUND(AVG(tss.overall_percentage), 2) AS avg_pct
                     FROM term_subject_scores tss
                     JOIN student_academic_enrollments sae2 ON sae2.student_id = tss.student_id
                     JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
                          AND ayt.academic_year_id = sae2.academic_year_id
                     GROUP BY tss.student_id, ayt.academic_year_id
                 ) yav ON yav.student_id = sae.student_id AND yav.academic_year_id = sae.academic_year_id
                 WHERE sae.student_id = ?
                 ORDER BY ay.start_date ASC",
                [$studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $subjectScores = $this->dbQuery(
                "SELECT ayt.academic_year_id, ay.year_code, t.code AS term_number, t.name AS term_name,
                        la.name AS subject_name, la.code AS subject_code,
                        tss.formative_percentage, tss.summative_percentage,
                        tss.overall_percentage, tss.overall_grade
                 FROM term_subject_scores tss
                 JOIN student_academic_enrollments sae ON sae.student_id = tss.student_id
                 JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
                      AND ayt.academic_year_id = sae.academic_year_id
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE tss.student_id = ?
                 ORDER BY ay.start_date ASC, t.id ASC, la.name ASC",
                [$studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $payments = $this->dbQuery(
                "SELECT p.payment_date, p.amount AS amount_paid, p.method AS payment_method,
                        p.receipt_no, p.reference AS reference_no, p.status
                 FROM payments p
                 WHERE p.student_id = ?
                 ORDER BY p.payment_date ASC",
                [$studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $feeBalanceSummary = $this->dbQuery(
                "SELECT COALESCE(SUM(fb.amount_due), 0) AS amount_due,
                        COALESCE(SUM(fb.amount_paid), 0) AS amount_paid,
                        COALESCE(SUM(fb.balance), 0) AS balance
                 FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " fb
                 JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                 WHERE sae.student_id = ?",
                [$studentId]
            )->fetch(PDO::FETCH_ASSOC);

            $feeObligations = $this->dbQuery(
                "SELECT ay.year_code AS academic_year, t.code AS term_number, t.name AS term_name,
                        'School Fees' AS fee_name,
                        o.amount_due, o.status AS payment_status
                 FROM student_fee_obligations o
                 JOIN student_academic_enrollments sae ON sae.id = o.student_academic_enrollment_id
                 JOIN academic_years ay ON ay.id = o.academic_year_id
                 JOIN academic_year_terms ayt ON ayt.id = o.academic_year_term_id
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN academic_year_fee_schedules fsd ON fsd.id = o.academic_year_fee_schedule_id
                 WHERE sae.student_id = ?
                 ORDER BY ay.year_code ASC, t.code ASC",
                [$studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $discipline = $this->dbQuery(
                "SELECT di.incident_date, di.type AS incident_type, di.severity,
                        di.description, di.action_taken, di.status,
                        ay.year_code AS academic_year,
                        t.code AS term_number
                 FROM discipline_incidents di
                 JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = di.academic_year_term_id
                 LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 WHERE sae.student_id = ?
                 ORDER BY di.incident_date ASC",
                [$studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $attendance = $this->dbQuery(
                "SELECT ay.year_code AS academic_year,
                        COUNT(CASE WHEN sa.status = 'present' THEN 1 END) AS days_present,
                        COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) AS days_absent,
                        COUNT(CASE WHEN sa.status = 'late' THEN 1 END) AS days_late,
                        COUNT(sa.id) AS total_recorded
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 JOIN academic_years ay ON ay.id = sae.academic_year_id
                 WHERE sae.student_id = ?
                 GROUP BY ay.id
                 ORDER BY ay.start_date ASC",
                [$studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $creditNotes = $this->dbQuery(
                "SELECT credit_number, academic_year, credit_amount, credit_reason,
                        status, applied_amount, remaining_amount, created_at
                 FROM fee_credit_notes
                 WHERE student_id = ? ORDER BY academic_year ASC",
                [$studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $transfers = $this->dbQuery(
                "SELECT st.id AS request_number, st.decided_at AS request_date,
                        st.transition_type AS transfer_type, st.reason,
                        sc.status, sc.amount_outstanding AS fee_balance_at_request,
                        st.executed_at AS completed_at
                 FROM student_transitions st
                 LEFT JOIN student_clearances sc ON sc.student_id = st.student_id
                      AND sc.clearance_type = 'finance'
                 WHERE st.student_id = ?
                 ORDER BY st.executed_at ASC",
                [$studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'student'         => $student,
                'academics'       => $academics,
                'subject_scores'  => $subjectScores,
                'payments'        => $payments,
                'fee_obligations' => $feeObligations,
                'discipline'      => $discipline,
                'attendance'      => $attendance,
                'credit_notes'    => $creditNotes,
                'transfers'       => $transfers,
                'summary' => [
                    'years_enrolled'    => count($academics),
                    'total_fees_billed' => $feeBalanceSummary['amount_due'],
                    'total_fees_paid'   => $feeBalanceSummary['amount_paid'],
                    'current_balance'   => $feeBalanceSummary['balance'],
                    'discipline_cases'  => count($discipline),
                ],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getStudentTimeline');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getStaffTimeline(int $staffId): array
    {
        try {
            $staff = $this->dbQuery(
                "SELECT s.id, s.staff_no,
                        p.first_name, p.last_name,
                        p.email AS email,
                        p.phone, p.gender, p.dob AS date_of_birth, s.employment_date, s.status AS employment_status,
                        s.salary AS basic_salary, p.photo_url,
                        d.name AS department_name, sc.category_name AS staff_category,
                        s.position AS position_title
                 FROM staff s
                 LEFT JOIN persons p ON p.id = s.person_id
                 LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
                 LEFT JOIN departments d ON d.id = sda.department_id
                 LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
                 WHERE s.id = ?",
                [$staffId]
            )->fetch(PDO::FETCH_ASSOC);

            if (!$staff) return $this->errorResponse('Staff not found', 404);

            $assignments = $this->dbQuery(
                "SELECT ay.year_code AS academic_year, c.name AS class_name,
                        NULL AS stream_name, aclat.role, la.name AS subject_name,
                        NULL AS status, NULL AS start_date, NULL AS end_date
                 FROM academic_year_class_learning_area_teachers aclat
                 JOIN academic_year_class_learning_areas aycl ON aycl.id = aclat.academic_year_class_learning_area_id
                 JOIN academic_year_classes ayc ON ayc.id = aycl.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 JOIN learning_areas la ON la.id = aycl.learning_area_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = aclat.academic_year_term_id
                 LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 WHERE aclat.staff_id = ?
                 ORDER BY ay.start_date ASC",
                [$staffId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $promotions = $this->dbQuery(
                "SELECT sa.position AS to_position, sa.position AS from_position,
                        sa.salary AS to_salary, sa.salary AS from_salary,
                        sa.employment_date AS effective_date, sa.status,
                        d.name AS to_department, NULL AS from_department
                 FROM staff_appointments sa
                 LEFT JOIN departments d ON d.id = sa.department_id
                 WHERE sa.created_staff_id = ?
                 ORDER BY sa.employment_date ASC",
                [$staffId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $payrollHistory = $this->dbQuery(
                "SELECT CONCAT(payroll_year, '-', LPAD(payroll_month, 2, '0')) AS payroll_month,
                        basic_salary,
                        allowances_total AS allowances,
                        COALESCE(paye_tax,0)+COALESCE(nssf_contribution,0)+COALESCE(nhif_contribution,0)+
                        COALESCE(loan_deduction,0)+COALESCE(child_fees_deduction,0)+COALESCE(sacco_deduction,0)+
                        COALESCE(housing_levy,0)+COALESCE(salary_advance_deduction,0)+COALESCE(other_deductions_total,0) AS total_deductions,
                        paye_tax, nssf_contribution AS nssf_deduction, nhif_contribution AS nhif_deduction,
                        net_salary, payslip_status AS status, payment_date
                 FROM payslips
                 WHERE staff_id = ?
                 ORDER BY payroll_year ASC, payroll_month ASC",
                [$staffId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $advances = $this->dbQuery(
                "SELECT advance_number, requested_amount, approved_amount,
                        request_date, deduction_schedule, amount_deducted, balance_remaining, status
                 FROM staff_salary_advances
                 WHERE staff_id = ? ORDER BY request_date ASC",
                [$staffId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $leaves = $this->dbQuery(
                "SELECT leave_type, start_date, end_date, days_requested, reason, status
                 FROM staff_leaves WHERE staff_id = ? ORDER BY start_date ASC",
                [$staffId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $performance = $this->dbQuery(
                "SELECT period AS review_period, rating AS overall_rating,
                        notes AS strengths, notes AS areas_for_improvement,
                        NULL AS performance_grade, NULL AS recommendations, NULL AS action_plan,
                        status, review_date
                 FROM performance_reviews
                 WHERE staff_id = ?
                 ORDER BY review_date ASC",
                [$staffId]
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'staff'       => $staff,
                'assignments' => $assignments,
                'promotions'  => $promotions,
                'payroll'     => $payrollHistory,
                'advances'    => $advances,
                'leaves'      => $leaves,
                'performance' => $performance,
                'summary' => [
                    'years_of_service'   => count(array_unique(array_column($assignments, 'academic_year'))),
                    'total_promotions'   => count($promotions),
                    'leave_days_taken'   => array_sum(array_column($leaves, 'days_requested')),
                    'active_advance'     => count(array_filter($advances, fn($a) => $a['status'] === 'active')),
                ],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getStaffTimeline');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== TRANSFER REQUESTS ====================

    public function getTransferRequests(?int $id): array
    {
        try {
            if ($id) {
                $row = $this->dbQuery(
                    "SELECT tr.id, tr.id AS request_number, tr.decided_at AS request_date,
                            tr.transition_type AS transfer_type, NULL AS destination_school,
                            CONCAT(p.first_name,' ',p.last_name) AS student_name,
                            s.admission_no, c.name AS class_name,
                            sc.checked_by AS requested_by, sc.checked_by AS approved_by,
                            tr.executed_at AS approval_date
                     FROM student_transitions tr
                     JOIN students s ON s.id = tr.student_id
                     JOIN persons p ON p.id = s.person_id
                     LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
                          AND sae.academic_year_id = tr.academic_year_id
                     LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                     LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     LEFT JOIN classes c ON c.id = ayc.class_id
                     LEFT JOIN student_clearances sc ON sc.transfer_request_id = tr.id
                          AND sc.clearance_type = 'finance'
                     WHERE tr.id = ?",
                    [$id]
                )->fetch(PDO::FETCH_ASSOC);

                $clearances = $this->dbQuery(
                    "SELECT sc.*, p.first_name AS checked_by_name
                     FROM student_clearances sc
                     LEFT JOIN users u ON u.id = sc.checked_by
                     LEFT JOIN persons p ON p.id = u.person_id
                     WHERE sc.transfer_request_id = ?",
                    [$id]
                )->fetchAll(PDO::FETCH_ASSOC);

                return $this->successResponse(['request' => $row, 'clearances' => $clearances]);
            }

            $rows = $this->dbQuery(
                "SELECT tr.id, tr.id AS request_number, tr.decided_at AS request_date,
                        tr.transition_type AS transfer_type, NULL AS destination_school,
                        CASE WHEN SUM(sc.status = 'blocked') > 0 THEN 'blocked'
                             WHEN COUNT(sc.id) > 0 AND SUM(sc.status = 'cleared') = COUNT(sc.id) THEN 'fully_cleared'
                             ELSE 'pending' END AS clearance_status,
                        CASE WHEN tr.executed_at IS NOT NULL THEN 'approved' ELSE 'pending' END AS status,
                        NULL AS fee_balance_at_request,
                        CONCAT(p.first_name,' ',p.last_name) AS student_name,
                        s.admission_no, c.name AS class_name
                 FROM student_transitions tr
                 JOIN students s ON s.id = tr.student_id
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
                      AND sae.academic_year_id = tr.academic_year_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN student_clearances sc ON sc.transfer_request_id = tr.id
                 WHERE tr.transition_type = 'transfer'
                 GROUP BY tr.id
                 ORDER BY tr.id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getTransferRequests');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postTransferRequests(array $data, ?int $userId): array
    {
        $studentId = $data['student_id'] ?? null;
        if (!$studentId) return $this->errorResponse('student_id is required.', 400);

        try {
            $feeCheck = $this->dbQuery(
                "SELECT COALESCE(SUM(fb.balance),0) AS outstanding
                 FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " fb
                 JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                 WHERE sae.student_id = ?
                   AND sae.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1)",
                [$studentId]
            )->fetch(PDO::FETCH_ASSOC);

            $outstanding = (float) ($feeCheck['outstanding'] ?? 0);

            if ($outstanding > 0) {
                \App\API\Includes\FileLogger::write('academic', [
                    'type' => 'academic',
                    'action' => 'TRANS_FEE_BLOCK',
                    'entity' => 'student',
                    'entity_id' => $studentId,
                    'user_id' => $userId,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'details' => [
                        'outstanding' => $outstanding,
                        'student_id' => $studentId,
                        'block' => 'Student has outstanding fees - transfer blocked',
                    ],
                    'status' => 'blocked',
                ]);

                return $this->errorResponse(
                    "Cannot initiate transfer: student has outstanding fees of KES " .
                    number_format($outstanding, 2) .
                    ". Fees must be paid or waived before transfer can proceed.",
                    422
                );
            }

            $this->dbQuery(
                "INSERT INTO student_transitions
                 (student_id, academic_year_id, transition_type, reason, decided_by, decided_at)
                 SELECT ?, ay.id, ?, ?, ?, NOW()
                 FROM academic_years ay WHERE ay.is_current = 1 LIMIT 1",
                [
                    $studentId,
                    $data['transfer_type'] ?? 'transfer',
                    $data['reason'] ?? null,
                    $userId,
                ]
            );
            $requestId = $this->db->lastInsertId();

            foreach (['finance', 'library', 'uniform', 'property', 'academic'] as $type) {
                $this->dbQuery(
                    "INSERT INTO student_clearances (student_id, transfer_request_id, clearance_type, status)
                     VALUES (?, ?, ?, 'pending')",
                    [$studentId, $requestId, $type]
                );
            }

            return $this->successResponse(['request_id' => $requestId, 'request_number' => $requestId], 'Transfer request created', 201);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postTransferRequests');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function putTransferRequests(int $id, array $data, ?int $userId): array
    {
        $action = $data['action'] ?? null;

        try {
            if ($action === 'update_clearance') {
                $this->dbQuery(
                    "UPDATE student_clearances SET status = ?, checked_by = ?, checked_at = NOW(),
                            amount_outstanding = ?, notes = ?
                     WHERE transfer_request_id = ? AND clearance_type = ?",
                    [
                        $data['status'] ?? 'pending',
                        $userId,
                        $data['amount_outstanding'] ?? 0,
                        $data['notes'] ?? null,
                        $id,
                        $data['clearance_type'] ?? '',
                    ]
                );

                return $this->successResponse(['updated' => true], 'Clearance updated');
            }

            if ($action === 'approve') {
                $this->dbQuery(
                    "UPDATE student_transitions SET decided_by = ?, decided_at = NOW(), executed_at = NOW() WHERE id = ?",
                    [$userId, $id]
                );
                $req = $this->dbQuery("SELECT student_id FROM student_transitions WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
                if ($req) {
                    $this->dbQuery("UPDATE students SET status = 'transferred' WHERE id = ?", [$req['student_id']]);
                }
                return $this->successResponse(['approved' => true], 'Transfer approved');
            }

            if ($action === 'reject') {
                $this->dbQuery(
                    "UPDATE student_transitions SET reason = CONCAT(COALESCE(reason, ''), ' | REJECTED: ', ?), decided_by = ? WHERE id = ?",
                    [$data['reason'] ?? null, $userId, $id]
                );
                return $this->successResponse(['rejected' => true], 'Transfer rejected');
            }

            return $this->errorResponse('Invalid action. Expected approve or reject.', 400);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putTransferRequests');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== YEAR-END ROLLOVER ====================

    public function getYearRolloverStatus(): array
    {
        try {
            $currentYear = $this->dbQuery(
                "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$currentYear) return $this->errorResponse('No current academic year is set.', 400);

            $termsStatus = $this->dbQuery(
                "SELECT t.code AS term_number, t.name, ayt.status FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE ayt.academic_year_id = ? ORDER BY t.id",
                [$currentYear['id']]
            )->fetchAll(PDO::FETCH_ASSOC);

            $pendingResults = $this->dbQuery(
                "SELECT COUNT(*) FROM student_academic_enrollments sae
                 WHERE sae.academic_year_id = ? AND NOT EXISTS (
                     SELECT 1 FROM term_subject_scores tss
                     JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
                          AND ayt.academic_year_id = sae.academic_year_id
                     WHERE tss.student_id = sae.student_id
                 )",
                [$currentYear['id']]
            )->fetchColumn();

            $pendingPromotions = $this->dbQuery(
                "SELECT COUNT(*) FROM student_academic_enrollments sae
                 WHERE sae.academic_year_id = ? AND NOT EXISTS (
                     SELECT 1 FROM student_transitions tr
                     WHERE tr.student_id = sae.student_id
                       AND tr.from_student_academic_enrollment_id = sae.id
                 )",
                [$currentYear['id']]
            )->fetchColumn();

            $outstandingFees = $this->dbQuery(
                "SELECT COUNT(DISTINCT fb.student_id) FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " fb
                 JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                 WHERE sae.academic_year_id = ? AND fb.balance > 0",
                [$currentYear['id']]
            )->fetchColumn();

            $rolloverLog = array_map(function (array $entry): array {
                return [
                    'step' => $entry['step'] ?? null,
                    'status' => $entry['status'] ?? null,
                    'students_promoted' => $entry['students_promoted'] ?? 0,
                    'students_retained' => $entry['students_retained'] ?? 0,
                    'fee_balances_carried' => $entry['fee_balances_carried'] ?? 0,
                    'credit_notes_created' => $entry['credit_notes_created'] ?? 0,
                    'performed_at' => $entry['performed_at'] ?? null,
                ];
            }, \App\API\Includes\FileLogger::recent('academic', 20, ['type' => 'year_rollover']));

            $allTermsComplete = !array_filter($termsStatus, fn($t) => $t['status'] !== 'completed');

            return $this->successResponse([
                'current_year'        => $currentYear,
                'terms'               => $termsStatus,
                'all_terms_complete'  => $allTermsComplete,
                'pending_results'     => (int) $pendingResults,
                'pending_promotions'  => (int) $pendingPromotions,
                'students_with_fees'  => (int) $outstandingFees,
                'ready_for_rollover'  => $allTermsComplete && $pendingResults == 0 && $pendingPromotions == 0,
                'rollover_log'        => $rolloverLog,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getYearRolloverStatus');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postYearRollover(array $data, ?int $userId): array
    {
        // The staged AcademicYearTransitionWorkflow is now the only
        // authoritative rollover path. Keeping this legacy endpoint read/write
        // capable would allow a second implementation to bypass promotions,
        // fee approval and reconciliation gates.
        return $this->errorResponse(
            'Legacy rollover endpoint is disabled. Use the staged academic year transition workflow.',
            410
        );

        /* Legacy implementation retained below for historical reference only. */
        $step = $data['step'] ?? null;
        if (!$step) return $this->errorResponse('Missing required step parameter.', 400);

        try {
            $currentYear = $this->dbQuery(
                "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$currentYear) return $this->errorResponse('No current academic year is set.', 400);

            $rolloverRef = 'ROL-' . date('Ymd');
            $result = ['step' => $step, 'status' => 'completed'];

            if ($step === 'fee_carryover') {
                $students = $this->dbQuery(
                    "SELECT sae.student_id,
                            SUM(fb.balance) AS outstanding,
                            SUM(CASE WHEN fb.balance < 0 THEN ABS(fb.balance) ELSE 0 END) AS surplus
                     FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " fb
                     JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                     WHERE sae.academic_year_id = ?
                     GROUP BY sae.student_id",
                    [$currentYear['id']]
                )->fetchAll(PDO::FETCH_ASSOC);

                $carried = 0; $credits = 0;
                foreach ($students as $s) {
                    if ((float) $s['outstanding'] > 0) {
                        \App\API\Includes\FileLogger::write('academic', [
                            'type' => 'academic',
                            'action' => 'FEE_CARRYOVER',
                            'entity' => 'student',
                            'entity_id' => $s['student_id'],
                            'user_id' => $userId,
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                            'details' => 'Year-end carryover of KES ' . $s['outstanding'],
                            'status' => 'completed',
                        ]);
                        $carried++;
                    }
                    if ((float) $s['surplus'] > 0) {
                        $creditNum = 'CRD-' . date('Ymd') . '-' . str_pad($credits + 1, 4, '0', STR_PAD_LEFT);
                        $this->dbQuery(
                            "INSERT INTO fee_credit_notes
                             (credit_number, student_id, academic_year, credit_amount, credit_reason, expiry_date, created_by)
                             VALUES (?, ?, ?, ?, 'overpayment', DATE_ADD(CURDATE(), INTERVAL 2 YEAR), ?)",
                            [$creditNum, $s['student_id'], $currentYear['year_code'], $s['surplus'], $userId]
                        );
                        $credits++;
                    }
                }
                $result['fee_balances_carried'] = $carried;
                $result['credit_notes_created'] = $credits;

            } elseif ($step === 'staff_reassignment') {
                $count = $this->dbQuery(
                    "SELECT COUNT(*) FROM academic_year_class_learning_area_teachers aclat
                     JOIN academic_year_class_learning_areas aycl ON aycl.id = aclat.academic_year_class_learning_area_id
                     JOIN academic_year_classes ayc ON ayc.id = aycl.academic_year_class_id
                     WHERE ayc.academic_year_id = ?",
                    [$currentYear['id']]
                )->fetchColumn();
                $result['staff_to_reassign'] = (int) $count;
                $result['note'] = 'Use Manage Staff → Class Assignments to confirm new year assignments';

            } elseif ($step === 'create_new_year') {
                $startYear = (int) substr((string) $currentYear['year_code'], 0, 4);
                $newYearCode = ($startYear + 1) . '/' . ($startYear + 2);
                $parseDate = static function ($value, string $label): string {
                    $date = \DateTime::createFromFormat('!Y-m-d', trim((string) $value));
                    $errors = \DateTime::getLastErrors();
                    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                        throw new Exception("{$label} must be a valid YYYY-MM-DD date");
                    }
                    return $date->format('Y-m-d');
                };
                $yearStart = $parseDate($data['year_start_date'] ?? '', 'Academic year opening date');
                $yearEnd = $parseDate($data['year_end_date'] ?? '', 'Academic year closing date');
                if ($yearStart >= $yearEnd) {
                    return $this->errorResponse('Academic year opening date must be before its closing date.', 400);
                }
                $termDates = $data['terms'] ?? [];
                if (!is_array($termDates) || count($termDates) !== 3) {
                    return $this->errorResponse('Opening and closing dates for all three terms are required.', 400);
                }
                $normalisedTerms = [];
                $previousTermEnd = null;
                foreach (array_values($termDates) as $index => $term) {
                    $termStart = $parseDate($term['start_date'] ?? $term['opening_date'] ?? '', 'Term ' . ($index + 1) . ' opening date');
                    $termEnd = $parseDate($term['end_date'] ?? $term['closing_date'] ?? '', 'Term ' . ($index + 1) . ' closing date');
                    if ($termStart >= $termEnd) {
                        return $this->errorResponse('Each term opening date must be before its closing date.', 400);
                    }
                    if ($termStart < $yearStart || $termEnd > $yearEnd) {
                        return $this->errorResponse('Every term must fall within the academic year dates.', 400);
                    }
                    if ($previousTermEnd !== null && $termStart <= $previousTermEnd) {
                        return $this->errorResponse('Term dates must be chronological and must not overlap.', 400);
                    }
                    $normalisedTerms[] = [$index + 1, $termStart, $termEnd];
                    $previousTermEnd = $termEnd;
                }
                $existing = $this->dbQuery("SELECT id FROM academic_years WHERE year_code = ?", [$newYearCode])->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $result['note'] = "Academic year $newYearCode already exists";
                    $result['new_year_id'] = $existing['id'];
                } else {
                    $this->dbQuery(
                        "INSERT INTO academic_years (year_code, year_name, start_date, end_date, status)
                         VALUES (?, ?, ?, ?, 'planning')",
                        [
                            $newYearCode,
                            "$newYearCode Academic Year",
                            $yearStart,
                            $yearEnd,
                        ]
                    );
                    $newYearId = (int) $this->db->lastInsertId();

                    foreach ($normalisedTerms as [$termNo, $start, $end]) {
                        $this->dbQuery(
                            "INSERT INTO academic_year_terms (academic_year_id, term_id, status, opening_date, closing_date)
                             SELECT ?, t.id, 'upcoming', ?, ?
                             FROM terms t WHERE t.code = ?",
                            [$newYearId, $start, $end, "T$termNo"]
                        );
                    }

                    // A new year must have its own class/stream bindings before
                    // continuing learners can be enrolled. Copy the current
                    // academic setup, including existing stream assignments;
                    // staff may revise these later in Class Assignments.
                    $classMap = [];
                    $sourceClasses = $this->dbQuery(
                        "SELECT id, class_id FROM academic_year_classes
                         WHERE academic_year_id = ? ORDER BY id",
                        [$currentYear['id']]
                    )->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($sourceClasses as $sourceClass) {
                        $this->dbQuery(
                            "INSERT INTO academic_year_classes (academic_year_id, class_id, status)
                             VALUES (?, ?, 'planning')",
                            [$newYearId, (int) $sourceClass['class_id']]
                        );
                        $classMap[(int) $sourceClass['id']] = (int) $this->db->lastInsertId();
                    }

                    $sourceStreams = $this->dbQuery(
                        "SELECT academic_year_class_id, stream_id, room_id, class_teacher_id
                         FROM academic_year_class_streams
                         WHERE academic_year_class_id IN (" .
                            (empty($classMap) ? '0' : implode(',', array_map('intval', array_keys($classMap)))) .
                         ") ORDER BY id"
                    )->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($sourceStreams as $sourceStream) {
                        $this->dbQuery(
                            "INSERT INTO academic_year_class_streams
                                (academic_year_class_id, stream_id, room_id, class_teacher_id, status)
                             VALUES (?, ?, ?, ?, 'planning')",
                            [
                                $classMap[(int) $sourceStream['academic_year_class_id']],
                                (int) $sourceStream['stream_id'],
                                $sourceStream['room_id'] ?: null,
                                $sourceStream['class_teacher_id'] ?: null,
                            ]
                        );
                    }

                    $learningAreaMap = [];
                    $sourceLearningAreas = $this->dbQuery(
                        "SELECT id, academic_year_class_id, learning_area_id, strand_id,
                                sub_strand_id, status, planned_weeks, notes
                         FROM academic_year_class_learning_areas
                         WHERE academic_year_class_id IN (" .
                            (empty($classMap) ? '0' : implode(',', array_map('intval', array_keys($classMap)))) .
                         ") ORDER BY id"
                    )->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($sourceLearningAreas as $sourceArea) {
                        $this->dbQuery(
                            "INSERT INTO academic_year_class_learning_areas
                                (academic_year_class_id, learning_area_id, strand_id,
                                 sub_strand_id, status, planned_weeks, notes)
                             VALUES (?, ?, ?, ?, 'planned', ?, ?)",
                            [
                                $classMap[(int) $sourceArea['academic_year_class_id']],
                                (int) $sourceArea['learning_area_id'],
                                $sourceArea['strand_id'] ?: null,
                                $sourceArea['sub_strand_id'] ?: null,
                                $sourceArea['planned_weeks'] ?: null,
                                $sourceArea['notes'] ?? null,
                            ]
                        );
                        $learningAreaMap[(int) $sourceArea['id']] = (int) $this->db->lastInsertId();
                    }

                    $targetTerms = [];
                    foreach ($this->dbQuery(
                        "SELECT id, term_id FROM academic_year_terms WHERE academic_year_id = ?",
                        [$newYearId]
                    )->fetchAll(PDO::FETCH_ASSOC) as $targetTerm) {
                        $targetTerms[(int) $targetTerm['term_id']] = (int) $targetTerm['id'];
                    }
                    $sourceTeachers = $this->dbQuery(
                        "SELECT academic_year_class_learning_area_id, academic_year_term_id,
                                staff_id, role
                         FROM academic_year_class_learning_area_teachers
                         WHERE academic_year_class_learning_area_id IN (" .
                            (empty($learningAreaMap) ? '0' : implode(',', array_map('intval', array_keys($learningAreaMap)))) .
                         ") ORDER BY id"
                    )->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($sourceTeachers as $sourceTeacher) {
                        $sourceTerm = $this->dbQuery(
                            "SELECT term_id FROM academic_year_terms WHERE id = ? LIMIT 1",
                            [(int) $sourceTeacher['academic_year_term_id']]
                        )->fetchColumn();
                        $targetTermId = $targetTerms[(int) $sourceTerm] ?? null;
                        if (!$targetTermId) continue;
                        $this->dbQuery(
                            "INSERT INTO academic_year_class_learning_area_teachers
                                (academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)
                             VALUES (?, ?, ?, ?)",
                            [
                                $learningAreaMap[(int) $sourceTeacher['academic_year_class_learning_area_id']],
                                $targetTermId,
                                (int) $sourceTeacher['staff_id'],
                                $sourceTeacher['role'],
                            ]
                        );
                    }
                    $result['new_year_id'] = $newYearId;
                    $result['new_year_code'] = $newYearCode;
                    $result['terms_created'] = 3;
                    $result['class_bindings_created'] = count($classMap);
                    $result['stream_bindings_created'] = count($sourceStreams);
                    $result['learning_area_bindings_created'] = count($learningAreaMap);
                    $result['teacher_bindings_created'] = count($sourceTeachers);
                }

            } elseif ($step === 'archive_old_year') {
                $this->dbQuery(
                    "UPDATE academic_years SET status = 'archived', is_current = 0 WHERE id = ?",
                    [$currentYear['id']]
                );
                $this->dbQuery(
                    "INSERT INTO academic_year_archives
                     (academic_year, status, closure_initiated_by, closure_date)
                     VALUES (?, 'archived', ?, NOW())
                     ON DUPLICATE KEY UPDATE status = 'archived', archived_at = NOW()",
                    [$currentYear['year_code'], $userId]
                );
                $result['archived_year'] = $currentYear['year_code'];

            } elseif ($step === 'activate_new_year') {
                $startYear = (int) substr((string) $currentYear['year_code'], 0, 4);
                $newYearCode = ($startYear + 1) . '/' . ($startYear + 2);
                $newYear = $this->dbQuery("SELECT id FROM academic_years WHERE year_code = ?", [$newYearCode])->fetch(PDO::FETCH_ASSOC);
                if (!$newYear) {
                    return $this->errorResponse('Next academic year does not exist; run create_new_year first.', 400);
                }

                $this->dbQuery("UPDATE academic_years SET is_current = 0");
                $this->dbQuery(
                    "UPDATE academic_years SET is_current = 1, status = 'active' WHERE id = ?",
                    [$newYear['id']]
                );
                $this->dbQuery(
                    "UPDATE academic_year_terms SET status = 'current'
                     WHERE academic_year_id = ? AND term_id = (SELECT id FROM terms WHERE code = 'T1')",
                    [$newYear['id']]
                );
                $result['continuing_students'] = $this->onboardContinuingStudents(
                    (int) $currentYear['id'],
                    (int) $newYear['id'],
                    $userId
                );
                $result['activated_year'] = $newYearCode;
            }

            \App\API\Includes\FileLogger::write('academic', [
                'type' => 'year_rollover',
                'rollover_id' => $rolloverRef,
                'from_year_id' => $currentYear['id'],
                'step' => $step,
                'status' => 'completed',
                'fee_balances_carried' => $result['fee_balances_carried'] ?? 0,
                'credit_notes_created' => $result['credit_notes_created'] ?? 0,
                'staff_reassigned' => $result['staff_to_reassign'] ?? 0,
                'performed_by' => $userId,
                'performed_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->successResponse($result, 'Rollover step completed');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postYearRollover');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Carry existing learners into the new academic year without treating
     * them as admissions. Their class/stream identity is preserved, a new
     * year enrollment is created once, and the normal term-aware fee
     * procedure seeds obligations only when the target schedules are active.
     */
    private function onboardContinuingStudents(int $fromYearId, int $toYearId, ?int $userId): array
    {
        $rows = $this->dbQuery(
            "SELECT DISTINCT sae.student_id, ayc.class_id, aycs.stream_id
             FROM student_academic_enrollments sae
             JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             JOIN students s ON s.id = sae.student_id
             WHERE sae.academic_year_id = ?
               AND sae.enrollment_status = 'active'
               AND s.status = 'active'",
            [$fromYearId]
        )->fetchAll(PDO::FETCH_ASSOC);

        $created = 0;
        $already = 0;
        $skipped = [];
        foreach ($rows as $row) {
            $targetAycs = $this->dbQuery(
                "SELECT aycs.id
                 FROM academic_year_class_streams aycs
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 WHERE ayc.academic_year_id = ?
                   AND ayc.class_id = ?
                   AND aycs.stream_id = ?
                   AND aycs.status IN ('active', 'planning')
                 ORDER BY aycs.status = 'active' DESC, aycs.id DESC
                 LIMIT 1",
                [$toYearId, (int) $row['class_id'], (int) $row['stream_id']]
            )->fetchColumn();

            if (!$targetAycs) {
                $skipped[] = (int) $row['student_id'];
                continue;
            }

            $existing = $this->dbQuery(
                "SELECT id FROM student_academic_enrollments
                 WHERE student_id = ? AND academic_year_id = ? LIMIT 1",
                [(int) $row['student_id'], $toYearId]
            )->fetchColumn();

            if ($existing) {
                $already++;
                continue;
            }

            $this->dbQuery(
                "INSERT INTO student_academic_enrollments
                    (student_id, academic_year_id, academic_year_class_stream_id,
                     enrolled_on, enrollment_status)
                 VALUES (?, ?, ?, CURDATE(), 'active')",
                [(int) $row['student_id'], $toYearId, (int) $targetAycs]
            );
            $enrollmentId = (int) $this->db->lastInsertId();

            try {
                $call = $this->db->prepare(
                    "CALL sp_onboard_student_enrollment(?, ?, @kwa_rollover_obligations)"
                );
                $call->execute([$enrollmentId, $userId]);
                $call->closeCursor();
                (new ExtraChargeService($this->db))->generateEnrollmentObligations($enrollmentId);
            } catch (Throwable $e) {
                \App\API\Services\Logger::legacyError('[YearRollover] Fee onboarding deferred for student ' . (int) $row['student_id'] . ': ' . $e->getMessage());
            }
            $created++;
        }

        return [
            'processed' => count($rows),
            'enrollments_created' => $created,
            'already_onboarded' => $already,
            'skipped_missing_target_stream' => count($skipped),
            'skipped_student_ids' => $skipped,
        ];
    }

    /**
     * GET /api/academic/my-teaching-today
     * Deputy/teacher home dashboard: today's class, attendance and schedule.
     */
    public function getMyTeachingToday(?int $userId): array
    {
        try {
            $today   = date('Y-m-d');
            $dayName = date('l'); // Monday … Sunday

            $staff = $this->dbQuery(
                "SELECT s.id AS staff_id, p.first_name, p.last_name
                 FROM staff s
                 JOIN users u ON u.person_id = s.person_id
                 JOIN persons p ON p.id = s.person_id
                 WHERE u.id = ? LIMIT 1",
                [$userId]
            )->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                return $this->successResponse([
                    'class_name' => null, 'my_students' => 0,
                    'my_attendance_rate' => null, 'my_lessons_today' => 0,
                    'my_pending_plans' => 0, 'today_schedule' => [],
                ]);
            }

            $staffId = $staff['staff_id'];

            $term = $this->dbQuery(
                "SELECT ayt.id, t.name, ayt.academic_year_id
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);

            $termId = $term['id'] ?? null;

            $classAssign = $this->dbQuery(
                "SELECT aycs.id AS stream_id, sn.name AS stream_name, c.name AS class_name, c.id AS class_id
                 FROM academic_year_class_streams aycs
                 JOIN streams sn ON sn.id = aycs.stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 WHERE aycs.class_teacher_id = ? AND ayc.academic_year_id = ?
                 LIMIT 1",
                [$staffId, $term['academic_year_id'] ?? 0]
            )->fetch(PDO::FETCH_ASSOC);

            $streamId = $classAssign['stream_id'] ?? null;

            $myStudents = 0;
            if ($streamId) {
                $myStudents = (int) $this->dbQuery(
                    "SELECT COUNT(*) FROM student_academic_enrollments
                     WHERE academic_year_class_stream_id = ? AND enrollment_status = 'active'",
                    [$streamId]
                )->fetchColumn();
            }

            $myAttendanceRate = null;
            $myPresent = 0;
            $myAbsent  = 0;
            if ($streamId && $myStudents > 0) {
                $attRow = $this->dbQuery(
                    "SELECT
                       SUM(sa.status = 'present') AS present_count,
                       SUM(sa.status = 'absent')  AS absent_count
                     FROM student_attendance sa
                     JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                     WHERE sae.academic_year_class_stream_id = ?
                       AND sa.date = ?",
                    [$streamId, $today]
                )->fetch(PDO::FETCH_ASSOC);

                $myPresent = (int) ($attRow['present_count'] ?? 0);
                $myAbsent  = (int) ($attRow['absent_count'] ?? 0);
                if ($myStudents > 0) {
                    $myAttendanceRate = round(($myPresent / $myStudents) * 100);
                }
            }

            $todaySchedule = $this->dbQuery(
                "SELECT ts.start_time, ts.end_time, la.name AS subject, c.name AS class_name
                 FROM timetable_entries te
                 JOIN time_slots ts ON ts.id = te.time_slot_id
                 JOIN learning_areas la ON la.id = te.learning_area_id
                 JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 WHERE te.teacher_id = ? AND te.day_of_week = ? AND te.academic_year_term_id = ?
                 ORDER BY ts.start_time",
                [$staffId, $dayName, $termId ?? 0]
            )->fetchAll(PDO::FETCH_ASSOC);

            $schedule = array_map(function ($row) {
                return [
                    'time'       => substr($row['start_time'] ?? '', 0, 5) . '–' . substr($row['end_time'] ?? '', 0, 5),
                    'subject'    => $row['subject'],
                    'class_name' => $row['class_name'],
                ];
            }, $todaySchedule);

            $pendingPlans = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM lesson_plans lp
                 LEFT JOIN academic_year_calendar_days aycd ON aycd.id = lp.academic_year_calendar_day_id
                 LEFT JOIN academic_year_calendar ayc ON ayc.id = aycd.academic_year_calendar_id
                 WHERE lp.teacher_id = ? AND lp.status = 'draft'
                   AND (ayc.academic_year_term_id = ? OR lp.academic_year_calendar_day_id IS NULL)",
                [$staffId, $termId ?? 0]
            )->fetchColumn();

            return $this->successResponse([
                'class_name'         => $classAssign['class_name'] ?? null,
                'stream_name'        => $classAssign['stream_name'] ?? null,
                'my_students'        => $myStudents,
                'my_attendance_rate' => $myAttendanceRate,
                'my_present'         => $myPresent,
                'my_absent'          => $myAbsent,
                'my_lessons_today'   => count($schedule),
                'my_pending_plans'   => $pendingPlans,
                'today_schedule'     => $schedule,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getMyTeachingToday');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/deputy-academic-summary
     * Deputy head (academic) administrative dashboard.
     */
    public function getDeputyAcademicSummary(): array
    {
        try {
            $term = $this->dbQuery(
                "SELECT ayt.id, ayt.academic_year_id, t.name
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            $termId   = $term['id'] ?? 0;
            $yearId   = $term['academic_year_id'] ?? 0;
            $today    = date('Y-m-d');

            $pendingAdm = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM admission_applications WHERE status IN ('pending','reviewing')"
            )->fetchColumn();

            $lpPending = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM lesson_plans lp
                 LEFT JOIN academic_year_calendar_days aycd ON aycd.id = lp.academic_year_calendar_day_id
                 LEFT JOIN academic_year_calendar ayc ON ayc.id = aycd.academic_year_calendar_id
                 WHERE lp.status = 'draft' AND (ayc.academic_year_term_id = ? OR lp.academic_year_calendar_day_id IS NULL)",
                [$termId]
            )->fetchColumn();

            $examsScheduled = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM exam_schedules WHERE academic_year_term_id = ?", [$termId]
            )->fetchColumn();

            $gradingPending = (int) $this->dbQuery(
                "SELECT COUNT(DISTINCT aclat.staff_id)
                 FROM academic_year_class_learning_area_teachers aclat
                 WHERE aclat.academic_year_term_id = ?
                   AND NOT EXISTS (
                     SELECT 1 FROM assessments a
                     JOIN formative_scores fs ON fs.assessment_id = a.id
                     WHERE a.academic_year_term_id = aclat.academic_year_term_id
                       AND a.assigned_by = aclat.staff_id
                   )",
                [$termId]
            )->fetchColumn();

            $activeTimetables = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM timetable_entries WHERE academic_year_term_id = ?", [$termId]
            )->fetchColumn();

            $attRow = $this->dbQuery(
                "SELECT SUM(status = 'present') AS p, SUM(status = 'absent') AS a,
                        COUNT(*) AS total
                 FROM student_attendance WHERE date = ?",
                [$today]
            )->fetch(PDO::FETCH_ASSOC);
            $present = (int) ($attRow['p'] ?? 0);
            $absent  = (int) ($attRow['a'] ?? 0);
            $total   = (int) ($attRow['total'] ?? 0);
            $attPct  = $total > 0 ? round(($present / $total) * 100) : null;

            $attTrend = $this->dbQuery(
                "SELECT date, ROUND(AVG(status = 'present') * 100) AS pct
                 FROM student_attendance
                 WHERE date >= DATE_SUB(?, INTERVAL 7 DAY)
                 GROUP BY date ORDER BY date",
                [$today]
            )->fetchAll(PDO::FETCH_ASSOC);

            $classPerf = $this->dbQuery(
                "SELECT c.name AS class_name, ROUND(AVG(fs.percentage), 1) AS avg_score
                 FROM formative_scores fs
                 JOIN assessments a ON a.id = fs.assessment_id AND a.academic_year_term_id = ?
                 JOIN student_academic_enrollments sae ON sae.student_id = fs.student_id AND sae.academic_year_id = ?
                 JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 GROUP BY c.id ORDER BY c.name LIMIT 12",
                [$termId, $yearId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $admRows = $this->dbQuery(
                "SELECT applicant_name AS name,
                        grade_applying_for AS class, DATE(created_at) AS date, status
                 FROM admission_applications WHERE status IN ('pending','reviewing')
                 ORDER BY created_at DESC LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC);

            $lpRows = $this->dbQuery(
                "SELECT lp.id,
                        CONCAT(p.first_name,' ',p.last_name) AS teacher_name,
                        c.name AS class_name,
                        la.name AS subject,
                        NULL AS week_label
                 FROM lesson_plans lp
                 JOIN staff s ON s.id = lp.teacher_id
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN academic_year_class_learning_areas aycl ON aycl.id = lp.academic_year_class_learning_area_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycl.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN learning_areas la ON la.id = aycl.learning_area_id
                 LEFT JOIN academic_year_calendar_days aycd ON aycd.id = lp.academic_year_calendar_day_id
                 LEFT JOIN academic_year_calendar ayc2 ON ayc2.id = aycd.academic_year_calendar_id
                 WHERE lp.status = 'draft' AND (ayc2.academic_year_term_id = ? OR lp.academic_year_calendar_day_id IS NULL)
                 ORDER BY lp.created_at ASC LIMIT 10",
                [$termId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $events = $this->dbQuery(
                "SELECT title, MIN(DATE(start_at)) AS start_date, MAX(DATE(end_at)) AS end_date
                 FROM school_events
                 WHERE start_at >= CURDATE() AND status != 'cancelled'
                 GROUP BY title ORDER BY MIN(start_at) LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'cards' => [
                    'pending_admissions'          => ['count' => $pendingAdm, 'details' => 'Awaiting placement'],
                    'lesson_plans_pending_review' => $lpPending,
                    'exams_scheduled'             => $examsScheduled,
                    'grading_pending'             => $gradingPending,
                    'active_timetables'           => $activeTimetables,
                    'school_attendance'           => $attPct,
                    'present'                     => $present,
                    'absent'                      => $absent,
                ],
                'charts' => [
                    'attendance_trend' => [
                        'labels' => array_column($attTrend, 'date'),
                        'values' => array_column($attTrend, 'pct'),
                    ],
                    'class_performance' => [
                        'labels' => array_column($classPerf, 'class_name'),
                        'values' => array_column($classPerf, 'avg_score'),
                    ],
                ],
                'tables' => [
                    'pending_admissions'   => $admRows,
                    'lesson_plans_pending' => $lpRows,
                    'upcoming_events'      => $events,
                ],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getDeputyAcademicSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/deputy-discipline-summary
     * Deputy head (discipline) administrative dashboard.
     */
    public function getDeputyDisciplineSummary(): array
    {
        try {
            $term = $this->dbQuery(
                "SELECT ayt.id, ayt.academic_year_id
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            $termId = $term['id'] ?? 0;
            $yearId = $term['academic_year_id'] ?? 0;
            $today  = date('Y-m-d');

            $openCases = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM discipline_incidents WHERE status = 'pending'"
            )->fetchColumn();

            $suspensions = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM discipline_incidents
                 WHERE action_taken LIKE '%suspend%' AND academic_year_term_id = ?",
                [$termId]
            )->fetchColumn();

            $truancy = (int) $this->dbQuery(
                "SELECT COUNT(DISTINCT sae.student_id) FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 WHERE sa.status = 'absent' AND sae.academic_year_id = ?
                 GROUP BY sae.student_id HAVING COUNT(*) > 5",
                [$yearId]
            )->fetchColumn();

            $parentMeetings = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM school_events WHERE status = 'scheduled' AND start_at >= CURDATE()"
            )->fetchColumn();

            $counselingReferrals = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM counseling_sessions"
            )->fetchColumn();

            $attRow = $this->dbQuery(
                "SELECT SUM(status = 'present') AS p, SUM(status = 'absent') AS a, COUNT(*) AS t
                 FROM student_attendance WHERE date = ?",
                [$today]
            )->fetch(PDO::FETCH_ASSOC);
            $present = (int) ($attRow['p'] ?? 0);
            $absent  = (int) ($attRow['a'] ?? 0);
            $total   = (int) ($attRow['t'] ?? 0);
            $attPct  = $total > 0 ? round(($present / $total) * 100) : null;

            $discTrend = $this->dbQuery(
                "SELECT YEARWEEK(created_at, 1) AS yw, COUNT(*) AS cases
                 FROM discipline_incidents
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
                 GROUP BY yw ORDER BY yw"
            )->fetchAll(PDO::FETCH_ASSOC);

            $attTrend = $this->dbQuery(
                "SELECT date,
                        ROUND(AVG(status = 'present') * 100) AS present_pct,
                        ROUND(AVG(status = 'absent') * 100) AS absent_pct
                 FROM student_attendance
                 WHERE date >= DATE_SUB(?, INTERVAL 7 DAY)
                 GROUP BY date ORDER BY date",
                [$today]
            )->fetchAll(PDO::FETCH_ASSOC);

            $caseRows = $this->dbQuery(
                "SELECT CONCAT(p.first_name,' ',p.last_name) AS student,
                        c.name AS class, di.type AS issue,
                        DATE(di.incident_date) AS date, di.status
                 FROM discipline_incidents di
                 JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
                 JOIN students st ON st.id = sae.student_id
                 JOIN persons p ON p.id = st.person_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 WHERE di.status = 'pending' AND sae.academic_year_id = ?
                 ORDER BY di.incident_date DESC LIMIT 10",
                [$yearId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $meetingRows = $this->dbQuery(
                "SELECT DATE(pm.start_at) AS meeting_date,
                        NULL AS parent_name, NULL AS student_name, pm.title AS reason
                 FROM school_events pm
                 WHERE pm.status = 'scheduled' AND pm.start_at >= CURDATE()
                 ORDER BY pm.start_at LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC);

            $events = $this->dbQuery(
                "SELECT title, MIN(DATE(start_at)) AS start_date, MAX(DATE(end_at)) AS end_date
                 FROM school_events
                 WHERE start_at >= CURDATE() AND status != 'cancelled'
                 GROUP BY title ORDER BY MIN(start_at) LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'cards' => [
                    'open_cases'                => $openCases,
                    'suspensions_this_term'     => $suspensions,
                    'truancy_cases'             => $truancy,
                    'parent_meetings_pending'   => $parentMeetings,
                    'counseling_referrals_open' => $counselingReferrals,
                    'school_attendance'         => $attPct,
                    'present'                   => $present,
                    'absent'                    => $absent,
                ],
                'charts' => [
                    'discipline_trend' => [
                        'labels' => array_map(fn($r) => 'Wk ' . substr($r['yw'], -2), $discTrend),
                        'values' => array_column($discTrend, 'cases'),
                    ],
                    'attendance_trend' => [
                        'labels'  => array_column($attTrend, 'date'),
                        'present' => array_column($attTrend, 'present_pct'),
                        'absent'  => array_column($attTrend, 'absent_pct'),
                    ],
                ],
                'tables' => [
                    'discipline_cases' => $caseRows,
                    'parent_meetings'  => $meetingRows,
                    'upcoming_events'  => $events,
                ],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getDeputyDisciplineSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/sub-strands?strand_id=X
     * Get sub-strands, optionally filtered by strand_id. If numeric ID in URL, return single.
     */
    public function getSubStrands(?int $id, array $query): array
    {
        try {
            if ($id) {
                $conditions = ['ss.id = :id'];
                $params = [':id' => $id];
                $this->addCurriculumScope($conditions, $params, $query, 's.learning_area_id', 's.grade_level', 'sub_one_scope');
                $row = $this->dbQuery(
                    "SELECT ss.*, s.name AS strand_name, s.code AS strand_code
                     FROM sub_strands ss
                     LEFT JOIN strands s ON s.id = ss.strand_id
                     WHERE " . implode(' AND ', $conditions),
                    $params
                )->fetch(PDO::FETCH_ASSOC);
                return $row ? $this->successResponse($row) : $this->errorResponse('Sub-strand not found', 404);
            }
            $strandId = (int) ($query['strand_id'] ?? 0);
            $conditions = [];
            $params = [];
            if ($strandId) { $conditions[] = 'ss.strand_id=:sid'; $params[':sid'] = $strandId; }
            $this->addCurriculumScope($conditions, $params, $query, 's.learning_area_id', 's.grade_level', 'sub_scope');
            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $this->dbQuery(
                "SELECT ss.*, s.name AS strand_name, s.code AS strand_code
                 FROM sub_strands ss
                 LEFT JOIN strands s ON s.id = ss.strand_id
                 $where
                 ORDER BY s.sort_order, ss.sort_order, ss.id",
                $params
            );
            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getSubStrands');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** POST /api/academic/sub-strands */
    public function postSubStrands(array $data): array
    {
        try {
            if (empty($data['strand_id']) || empty($data['name'])) {
                return $this->errorResponse('strand_id and name are required', 400);
            }
            $strand = $this->dbQuery(
                "SELECT id FROM strands WHERE id = :id AND status = 'active'",
                [':id' => (int) $data['strand_id']]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$strand) return $this->errorResponse('The selected strand does not exist or is inactive', 400);
            $code = $data['code'] ?? '';
            if (!$code && !empty($data['strand_id'])) {
                $s = $this->dbQuery("SELECT code FROM strands WHERE id=:id", [':id' => (int) $data['strand_id']])->fetch(PDO::FETCH_ASSOC);
                $cnt = $this->dbQuery("SELECT COUNT(*) AS c FROM sub_strands WHERE strand_id=:sid", [':sid' => (int) $data['strand_id']])->fetch(PDO::FETCH_ASSOC);
                $code = ($s['code'] ?? 'S') . '-SS' . (($cnt['c'] ?? 0) + 1);
            }
            $this->dbQuery(
                "INSERT INTO sub_strands (strand_id, code, name, description, sort_order, status)
                 VALUES (:sid, :code, :name, :desc, :sort, :status)",
                [
                    ':sid' => (int) $data['strand_id'],
                    ':code' => $code,
                    ':name' => $data['name'],
                    ':desc' => $data['description'] ?? null,
                    ':sort' => (int) ($data['sort_order'] ?? 1),
                    ':status' => $data['status'] ?? 'active',
                ]
            );
            $newId = $this->db->lastInsertId();
            return $this->successResponse(['id' => (int) $newId], 'Sub-strand created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postSubStrands');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** PUT /api/academic/sub-strands/{id} */
    public function putSubStrands(int $id, array $data): array
    {
        try {
            if (array_key_exists('strand_id', $data)) {
                $strand = $this->dbQuery(
                    "SELECT id FROM strands WHERE id = :id AND status = 'active'",
                    [':id' => (int) $data['strand_id']]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$strand) return $this->errorResponse('The selected strand does not exist or is inactive', 400);
            }
            $fields = [];
            $params = [':id' => $id];
            foreach (['strand_id', 'code', 'name', 'description', 'sort_order', 'status'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = $col === 'strand_id' || $col === 'sort_order' ? (int) $data[$col] : $data[$col];
                }
            }
            if (empty($fields)) return $this->errorResponse('No fields to update', 400);
            $this->dbQuery("UPDATE sub_strands SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->successResponse(['id' => (int) $id], 'Sub-strand updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putSubStrands');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** DELETE /api/academic/sub-strands/{id} */
    public function deleteSubStrands(int $id): array
    {
        try {
            $this->dbQuery("DELETE FROM sub_strands WHERE id=:id", [':id' => $id]);
            return $this->successResponse(null, 'Sub-strand deleted');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deleteSubStrands');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/learning-outcomes?sub_strand_id=X&strand_id=X&learning_area_id=X
     */
    public function getLearningOutcomes(?int $id, array $query): array
    {
        try {
            if ($id) {
                $conditions = ['lo.id = :id'];
                $params = [':id' => $id];
                $this->addCurriculumScope($conditions, $params, $query, 'lo.learning_area_id', 'lo.grade_level', 'outcome_one_scope');
                $row = $this->dbQuery(
                    "SELECT lo.*, la.name AS learning_area_name
                     FROM learning_outcomes lo
                     LEFT JOIN learning_areas la ON la.id = lo.learning_area_id
                     WHERE " . implode(' AND ', $conditions),
                    $params
                )->fetch(PDO::FETCH_ASSOC);
                return $row ? $this->successResponse($row) : $this->errorResponse('Learning outcome not found', 404);
            }
            $conds = ['s.status = :active_strand', 'la.status = :active_area'];
            $params = [':active_strand' => 'active', ':active_area' => 'active'];
            if (!empty($query['sub_strand_id'])) { $conds[] = 'lo.sub_strand_id=:ssid'; $params[':ssid'] = (int) $query['sub_strand_id']; }
            if (!empty($query['strand_id'])) { $conds[] = 's.id=:stid'; $params[':stid'] = (int) $query['strand_id']; }
            if (!empty($query['learning_area_id'])) { $conds[] = 'lo.learning_area_id=:laid'; $params[':laid'] = (int) $query['learning_area_id']; }
            if (!empty($query['grade_level'])) { $conds[] = 'lo.grade_level=:gl'; $params[':gl'] = $query['grade_level']; }
            $this->addCurriculumScope($conds, $params, $query, 'lo.learning_area_id', 'lo.grade_level', 'outcome_scope');
            $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
            $stmt = $this->dbQuery(
                "SELECT lo.*, la.name AS learning_area_name, ss.name AS sub_strand_name
                 FROM learning_outcomes lo
                 LEFT JOIN learning_areas la ON la.id = lo.learning_area_id
                 LEFT JOIN sub_strands ss ON ss.id = lo.sub_strand_id
                 LEFT JOIN strands s ON s.id = ss.strand_id
                 $where
                 ORDER BY la.name, lo.id",
                $params
            );
            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getLearningOutcomes');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** POST /api/academic/learning-outcomes */
    public function postLearningOutcomes(array $data): array
    {
        try {
            if (empty($data['learning_area_id']) || empty($data['outcome']) || empty($data['grade_level'])) {
                return $this->errorResponse('learning_area_id, outcome, and grade_level are required', 400);
            }
            $area = $this->dbQuery(
                "SELECT id FROM learning_areas WHERE id = :id AND status = 'active'",
                [':id' => (int) $data['learning_area_id']]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$area) return $this->errorResponse('The selected learning area does not exist or is inactive', 400);
            if (!empty($data['sub_strand_id'])) {
                $subStrand = $this->dbQuery(
                    "SELECT ss.id FROM sub_strands ss
                     JOIN strands s ON s.id = ss.strand_id
                     WHERE ss.id = :id AND s.learning_area_id = :area_id AND ss.status = 'active'",
                    [':id' => (int) $data['sub_strand_id'], ':area_id' => (int) $data['learning_area_id']]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$subStrand) return $this->errorResponse('The selected sub-strand does not belong to the learning area', 400);
            }
            $this->dbQuery(
                "INSERT INTO learning_outcomes (learning_area_id, sub_strand_id, outcome, grade_level)
                 VALUES (:laid, :ssid, :outcome, :gl)",
                [
                    ':laid' => (int) $data['learning_area_id'],
                    ':ssid' => !empty($data['sub_strand_id']) ? (int) $data['sub_strand_id'] : null,
                    ':outcome' => $data['outcome'],
                    ':gl' => $data['grade_level'],
                ]
            );
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Learning outcome created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postLearningOutcomes');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** PUT /api/academic/learning-outcomes/{id} */
    public function putLearningOutcomes(int $id, array $data): array
    {
        try {
            if (array_key_exists('learning_area_id', $data)) {
                $area = $this->dbQuery(
                    "SELECT id FROM learning_areas WHERE id = :id AND status = 'active'",
                    [':id' => (int) $data['learning_area_id']]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$area) return $this->errorResponse('The selected learning area does not exist or is inactive', 400);
            }
            if (!empty($data['sub_strand_id'])) {
                $areaId = (int) ($data['learning_area_id'] ?? 0);
                if (!$areaId) {
                    $areaId = (int) $this->dbQuery(
                        "SELECT learning_area_id FROM learning_outcomes WHERE id = :id",
                        [':id' => (int) $id]
                    )->fetchColumn();
                }
                $subStrand = $this->dbQuery(
                    "SELECT ss.id FROM sub_strands ss
                     JOIN strands s ON s.id = ss.strand_id
                     WHERE ss.id = :id AND s.learning_area_id = :area_id AND ss.status = 'active'",
                    [':id' => (int) $data['sub_strand_id'], ':area_id' => $areaId]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$subStrand) return $this->errorResponse('The selected sub-strand does not belong to the learning area', 400);
            }
            $fields = [];
            $params = [':id' => $id];
            foreach (['learning_area_id', 'sub_strand_id', 'outcome', 'grade_level'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = in_array($col, ['learning_area_id', 'sub_strand_id']) && $data[$col] !== null ? (int) $data[$col] : $data[$col];
                }
            }
            if (empty($fields)) return $this->errorResponse('No fields to update', 400);
            $this->dbQuery("UPDATE learning_outcomes SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->successResponse(['id' => (int) $id], 'Learning outcome updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putLearningOutcomes');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** DELETE /api/academic/learning-outcomes/{id} */
    public function deleteLearningOutcomes(int $id): array
    {
        try {
            $this->dbQuery("DELETE FROM learning_outcomes WHERE id=:id", [':id' => $id]);
            return $this->successResponse(null, 'Learning outcome deleted');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deleteLearningOutcomes');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** GET /api/academic/assessment-rubrics?tool_id=X */
    public function getAssessmentRubrics(?int $id, array $query): array
    {
        try {
            if ($id) {
                $conditions = ['ar.id = :id'];
                $params = [':id' => $id];
                $this->addCurriculumScope($conditions, $params, $query, 'at.learning_area_id', 'at.grade_level', 'rubric_one_scope');
                $row = $this->dbQuery(
                    "SELECT ar.*, at.tool_name
                     FROM assessment_rubrics ar
                     LEFT JOIN assessment_tools at ON at.id = ar.tool_id
                     WHERE " . implode(' AND ', $conditions),
                    $params
                )->fetch(PDO::FETCH_ASSOC);
                return $row ? $this->successResponse($row) : $this->errorResponse('Assessment rubric not found', 404);
            }
            $toolId = (int) ($query['tool_id'] ?? 0);
            $conditions = [];
            $params = [];
            if ($toolId) { $conditions[] = 'ar.tool_id=:tid'; $params[':tid'] = $toolId; }
            $this->addCurriculumScope($conditions, $params, $query, 'at.learning_area_id', 'at.grade_level', 'rubric_scope');
            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $this->dbQuery(
                "SELECT ar.*, at.tool_name
                 FROM assessment_rubrics ar
                 LEFT JOIN assessment_tools at ON at.id = ar.tool_id
                 $where
                 ORDER BY ar.sort_order, ar.id",
                $params
            );
            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getAssessmentRubrics');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** POST /api/academic/assessment-rubrics */
    public function postAssessmentRubrics(array $data): array
    {
        try {
            if (empty($data['tool_id']) || empty($data['criteria_name'])) {
                return $this->errorResponse('tool_id and criteria_name are required', 400);
            }
            $tool = $this->dbQuery(
                "SELECT id FROM assessment_tools WHERE id = :id AND status = 'active'",
                [':id' => (int) $data['tool_id']]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$tool) return $this->errorResponse('The selected assessment tool does not exist or is inactive', 400);
            $this->dbQuery(
                "INSERT INTO assessment_rubrics (tool_id, criteria_name, level_1_descriptor, level_2_descriptor, level_3_descriptor, level_4_descriptor, points_per_level, sort_order)
                 VALUES (:tid, :cn, :l1, :l2, :l3, :l4, :pts, :sort)",
                [
                    ':tid' => (int) $data['tool_id'],
                    ':cn' => $data['criteria_name'],
                    ':l1' => $data['level_1_descriptor'] ?? null,
                    ':l2' => $data['level_2_descriptor'] ?? null,
                    ':l3' => $data['level_3_descriptor'] ?? null,
                    ':l4' => $data['level_4_descriptor'] ?? null,
                    ':pts' => (int) ($data['points_per_level'] ?? 0),
                    ':sort' => (int) ($data['sort_order'] ?? 1),
                ]
            );
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Rubric created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postAssessmentRubrics');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** PUT /api/academic/assessment-rubrics/{id} */
    public function putAssessmentRubrics(int $id, array $data): array
    {
        try {
            if (array_key_exists('tool_id', $data)) {
                $tool = $this->dbQuery(
                    "SELECT id FROM assessment_tools WHERE id = :id AND status = 'active'",
                    [':id' => (int) $data['tool_id']]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$tool) return $this->errorResponse('The selected assessment tool does not exist or is inactive', 400);
            }
            $fields = [];
            $params = [':id' => $id];
            foreach (['tool_id', 'criteria_name', 'level_1_descriptor', 'level_2_descriptor', 'level_3_descriptor', 'level_4_descriptor', 'points_per_level', 'sort_order'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = in_array($col, ['tool_id', 'points_per_level', 'sort_order']) ? (int) $data[$col] : $data[$col];
                }
            }
            if (empty($fields)) return $this->errorResponse('No fields to update', 400);
            $this->dbQuery("UPDATE assessment_rubrics SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->successResponse(['id' => (int) $id], 'Rubric updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putAssessmentRubrics');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** DELETE /api/academic/assessment-rubrics/{id} */
    public function deleteAssessmentRubrics(int $id): array
    {
        try {
            $this->dbQuery("DELETE FROM assessment_rubrics WHERE id=:id", [':id' => $id]);
            return $this->successResponse(null, 'Rubric deleted');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deleteAssessmentRubrics');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** GET /api/academic/grading-scale|/grading-scale/{id} - Fetch a grading scale + its grade rules */
    public function getGradingScale(?int $id, array $query): array
    {
        try {
            if (isset($query['all'])) {
                $scales = $this->dbQuery(
                    "SELECT * FROM grading_scales ORDER BY (status='active') DESC, id"
                )->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($scales as $sc) {
                    $rules = $this->dbQuery(
                        "SELECT id, grade_code, grade_name, min_mark, max_mark, grade_points, performance_level, description, sort_order
                         FROM grade_rules
                         WHERE scale_id=:sid
                         ORDER BY sort_order, min_mark DESC",
                        [':sid' => $sc['id']]
                    )->fetchAll(PDO::FETCH_ASSOC);
                    $result[] = ['scale' => $sc, 'rules' => $rules];
                }
                return $this->successResponse($result);
            }
            if ($id) {
                $scale = $this->dbQuery(
                    "SELECT * FROM grading_scales WHERE id=:id",
                    [':id' => $id]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$scale) return $this->errorResponse('Grading scale not found', 404);
            } else {
                $scale = $this->dbQuery(
                    "SELECT * FROM grading_scales WHERE status='active' ORDER BY id LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                if (!$scale) return $this->successResponse(['scale' => null, 'rules' => []]);
            }
            $rules = $this->dbQuery(
                "SELECT id, grade_code, grade_name, min_mark, max_mark, grade_points, performance_level, description, sort_order
                 FROM grade_rules
                 WHERE scale_id=:sid
                 ORDER BY sort_order, min_mark DESC",
                [':sid' => $scale['id']]
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse(['scale' => $scale, 'rules' => $rules]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getGradingScale');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** POST /api/academic/grading-scale - Create a grading scale */
    public function postGradingScale(array $data): array
    {
        if (empty($data['name'])) return $this->errorResponse('Scale name is required', 400);
        try {
            $this->dbQuery(
                "INSERT INTO grading_scales (name, description, min_mark, max_mark, status)
                 VALUES (:name, :desc, :min, :max, :status)",
                [
                    ':name' => $data['name'],
                    ':desc' => $data['description'] ?? null,
                    ':min' => (float) ($data['min_mark'] ?? 0),
                    ':max' => (float) ($data['max_mark'] ?? 100),
                    ':status' => in_array($data['status'] ?? 'active', ['active', 'inactive']) ? $data['status'] : 'active',
                ]
            );
            $newId = (int) $this->db->lastInsertId();
            if (($data['status'] ?? 'active') === 'active') {
                $this->dbQuery("UPDATE grading_scales SET status='inactive' WHERE status='active' AND id<>:id", [':id' => $newId]);
            }
            return $this->successResponse(['id' => $newId], 'Grading scale created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postGradingScale');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** PUT /api/academic/grading-scale/{id} - Update a grading scale */
    public function putGradingScale(int $id, array $data): array
    {
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['name', 'description', 'min_mark', 'max_mark', 'status'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    if ($col === 'name') $params[":$col"] = $data[$col];
                    elseif ($col === 'description') $params[":$col"] = $data[$col];
                    elseif ($col === 'status') $params[":$col"] = in_array($data[$col], ['active', 'inactive']) ? $data[$col] : 'active';
                    else $params[":$col"] = (float) $data[$col];
                }
            }
            if (empty($fields)) return $this->errorResponse('No fields to update', 400);
            $this->dbQuery("UPDATE grading_scales SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            if (($data['status'] ?? '') === 'active') {
                $this->dbQuery("UPDATE grading_scales SET status='inactive' WHERE status='active' AND id<>:id", [':id' => $id]);
            }
            return $this->successResponse(['id' => (int) $id], 'Grading scale updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putGradingScale');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** POST /api/academic/grade-rules - Create a grade rule (range → grade) */
    public function postGradeRules(array $data): array
    {
        $required = ['scale_id', 'grade_code', 'grade_name', 'min_mark', 'max_mark'];
        foreach ($required as $k) {
            if (empty($data[$k])) return $this->errorResponse("{$k} is required", 400);
        }
        try {
            $scale = $this->dbQuery("SELECT id FROM grading_scales WHERE id=:id", [':id' => (int) $data['scale_id']])->fetch(PDO::FETCH_ASSOC);
            if (!$scale) return $this->errorResponse('The selected grading scale does not exist', 400);
            $this->dbQuery(
                "INSERT INTO grade_rules (scale_id, grade_code, grade_name, min_mark, max_mark, grade_points, performance_level, description, sort_order)
                 VALUES (:sid, :code, :name, :min, :max, :points, :level, :desc, :sort)",
                [
                    ':sid' => (int) $data['scale_id'],
                    ':code' => strtoupper($data['grade_code']),
                    ':name' => $data['grade_name'],
                    ':min' => (float) $data['min_mark'],
                    ':max' => (float) $data['max_mark'],
                    ':points' => (float) ($data['grade_points'] ?? 0),
                    ':level' => $data['performance_level'] ?? '',
                    ':desc' => $data['description'] ?? null,
                    ':sort' => (int) ($data['sort_order'] ?? 1),
                ]
            );
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Grade rule created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postGradeRules');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** PUT /api/academic/grade-rules/{id} - Update a grade rule */
    public function putGradeRules(int $id, array $data): array
    {
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['scale_id', 'grade_code', 'grade_name', 'min_mark', 'max_mark', 'grade_points', 'performance_level', 'description', 'sort_order'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    if ($col === 'grade_code') $params[":$col"] = strtoupper($data[$col]);
                    elseif (in_array($col, ['scale_id', 'sort_order'])) $params[":$col"] = (int) $data[$col];
                    elseif (in_array($col, ['min_mark', 'max_mark', 'grade_points'])) $params[":$col"] = (float) $data[$col];
                    else $params[":$col"] = $data[$col];
                }
            }
            if (empty($fields)) return $this->errorResponse('No fields to update', 400);
            $this->dbQuery("UPDATE grade_rules SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->successResponse(['id' => (int) $id], 'Grade rule updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putGradeRules');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** DELETE /api/academic/grade-rules/{id} - Delete a grade rule */
    public function deleteGradeRules(int $id): array
    {
        try {
            $this->dbQuery("DELETE FROM grade_rules WHERE id=:id", [':id' => $id]);
            return $this->successResponse(null, 'Grade rule deleted');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deleteGradeRules');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** GET /api/academic/strand-competencies?strand_id=X&competency_id=X */
    public function getStrandCompetencies(?int $id, array $query): array
    {
        try {
            $scopeConds = [];
            $scopeParams = [];
            $this->addCurriculumScope($scopeConds, $scopeParams, $query, 's.learning_area_id', 's.grade_level');
            if ($id) {
                $scopeWhere = $scopeConds ? ' AND ' . implode(' AND ', $scopeConds) : '';
                $row = $this->dbQuery(
                    "SELECT sc.*, s.name AS strand_name, cc.name AS competency_name
                     FROM strand_competency sc
                     LEFT JOIN strands s ON s.id = sc.strand_id
                     LEFT JOIN core_competencies cc ON cc.id = sc.competency_id
                     WHERE sc.id = :id $scopeWhere",
                    array_merge([':id' => $id], $scopeParams)
                )->fetch(PDO::FETCH_ASSOC);
                return $row ? $this->successResponse($row) : $this->errorResponse('Strand-competency mapping not found', 404);
            }
            $conds = [];
            $params = [];
            $conds = array_merge($conds, $scopeConds);
            $params = array_merge($params, $scopeParams);
            if (!empty($query['strand_id'])) { $conds[] = 'sc.strand_id=:sid'; $params[':sid'] = (int) $query['strand_id']; }
            if (!empty($query['competency_id'])) { $conds[] = 'sc.competency_id=:cid'; $params[':cid'] = (int) $query['competency_id']; }
            $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
            $stmt = $this->dbQuery(
                "SELECT sc.*, s.name AS strand_name, cc.name AS competency_name
                 FROM strand_competency sc
                 LEFT JOIN strands s ON s.id = sc.strand_id
                 LEFT JOIN core_competencies cc ON cc.id = sc.competency_id
                 $where
                 ORDER BY s.name, cc.name",
                $params
            );
            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getStrandCompetencies');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** POST /api/academic/strand-competencies */
    public function postStrandCompetencies(array $data): array
    {
        try {
            if (empty($data['strand_id']) || empty($data['competency_id'])) {
                return $this->errorResponse('strand_id and competency_id are required', 400);
            }
            $this->dbQuery(
                "INSERT INTO strand_competency (strand_id, competency_id, weight)
                 VALUES (:sid, :cid, :w)
                 ON DUPLICATE KEY UPDATE weight=VALUES(weight)",
                [
                    ':sid' => (int) $data['strand_id'],
                    ':cid' => (int) $data['competency_id'],
                    ':w' => (float) ($data['weight'] ?? 1.00),
                ]
            );
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Mapping created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postStrandCompetencies');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** PUT /api/academic/strand-competencies/{id} */
    public function putStrandCompetencies(int $id, array $data): array
    {
        try {
            $fields = [];
            $params = [':id' => $id];
            foreach (['strand_id', 'competency_id', 'weight'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=:$col";
                    $params[":$col"] = $col === 'weight' ? (float) $data[$col] : (int) $data[$col];
                }
            }
            if (empty($fields)) return $this->errorResponse('No fields to update', 400);
            $this->dbQuery("UPDATE strand_competency SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            return $this->successResponse(['id' => (int) $id], 'Mapping updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putStrandCompetencies');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** DELETE /api/academic/strand-competencies/{id} */
    public function deleteStrandCompetencies(int $id): array
    {
        try {
            $this->dbQuery("DELETE FROM strand_competency WHERE id=:id", [':id' => $id]);
            return $this->successResponse(null, 'Mapping deleted');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deleteStrandCompetencies');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/curriculum-tree?learning_area_id=X&strand_id=X
     * Returns the full CBC curriculum tree: learning areas -> strands -> sub-strands -> learning outcomes.
     */
    public function getCurriculumTree(array $query): array
    {
        try {
            $laConditions = [];
            $laParams = [];
            if (!empty($query['learning_area_family_id'])) {
                $laConditions[] = 'la.learning_area_family_id=:lafid';
                $laParams[':lafid'] = (int) $query['learning_area_family_id'];
            } elseif (!empty($query['learning_area_id'])) {
                $laConditions[] = 'la.id=:laid';
                $laParams[':laid'] = (int) $query['learning_area_id'];
            }
            $this->addCurriculumScope($laConditions, $laParams, $query, 'la.id', null, 'tree_area_scope');
            $laWhere = $laConditions ? 'WHERE ' . implode(' AND ', $laConditions) : '';
            $areas = $this->dbQuery(
                "SELECT la.id, la.code, la.name FROM learning_areas la $laWhere ORDER BY la.name",
                $laParams
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($areas as &$area) {
                $sConditions = ['s.learning_area_id=:laid'];
                $sParams = [];
                $sParams[':laid'] = $area['id'];
                if (!empty($query['grade_level'])) {
                    $sConditions[] = 's.grade_level=:grade';
                    $sParams[':grade'] = $query['grade_level'];
                }
                if (!empty($query['strand_id'])) {
                    $sConditions[] = 's.id=:sid';
                    $sParams[':sid'] = (int) $query['strand_id'];
                }
                $this->addCurriculumScope($sConditions, $sParams, $query, 's.learning_area_id', 's.grade_level', 'tree_strand_scope');
                $sWhere = 'WHERE ' . implode(' AND ', $sConditions);
                $strands = $this->dbQuery(
                    "SELECT s.id, s.code, s.name, s.grade_level, s.variant, s.source_subject, s.level_range, s.sort_order
                     FROM strands s $sWhere ORDER BY s.sort_order, s.id",
                    $sParams
                )->fetchAll(PDO::FETCH_ASSOC);

                foreach ($strands as &$strand) {
                    $subStrands = $this->dbQuery(
                        "SELECT ss.id, ss.code, ss.name, ss.sort_order
                         FROM sub_strands ss WHERE ss.strand_id=:sid AND ss.status='active'
                         ORDER BY ss.sort_order, ss.id",
                        [':sid' => $strand['id']]
                    )->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($subStrands as &$ss) {
                        $los = $this->dbQuery(
                            "SELECT lo.id, lo.outcome, lo.grade_level
                             FROM learning_outcomes lo WHERE lo.sub_strand_id=:ssid
                             ORDER BY lo.id",
                            [':ssid' => $ss['id']]
                        )->fetchAll(PDO::FETCH_ASSOC);
                        $ss['learning_outcomes'] = $los;
                    }

                    $competencies = $this->dbQuery(
                        "SELECT sc.id, cc.id AS competency_id, cc.name AS competency_name, sc.weight
                         FROM strand_competency sc
                         JOIN core_competencies cc ON cc.id = sc.competency_id
                         WHERE sc.strand_id=:sid ORDER BY cc.name",
                        [':sid' => $strand['id']]
                    )->fetchAll(PDO::FETCH_ASSOC);
                    $strand['sub_strands'] = $subStrands;
                    $strand['competencies'] = $competencies;
                }
                $area['strands'] = $strands;
            }
            return $this->successResponse($areas);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getCurriculumTree');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/pending-moderation?class_id=X&subject_id=X
     * Returns assessments with results submitted but not yet approved (pending moderation).
     */
    public function getPendingModeration(array $query): array
    {
        try {
            $conds = ["ar.is_submitted=1", "a.status IN ('submitted','pending_approval')"];
            $params = [];
            if (!empty($query['class_id'])) { $conds[] = 'a.academic_year_class_stream_id=:cid'; $params[':cid'] = (int) $query['class_id']; }
            if (!empty($query['subject_id'])) { $conds[] = 'a.learning_area_id=:sid'; $params[':sid'] = (int) $query['subject_id']; }
            if (!empty($query['term_id'])) { $conds[] = 'a.academic_year_term_id=:tid'; $params[':tid'] = (int) $query['term_id']; }
            $where = 'WHERE ' . implode(' AND ', $conds);

            $assessments = $this->dbQuery(
                "SELECT a.id AS assessment_id, a.title, a.max_marks, a.assessment_date, a.status,
                        a.academic_year_class_stream_id AS class_id, a.learning_area_id AS subject_id, a.academic_year_term_id AS term_id,
                        c.name AS class_name, la.name AS subject_name, t.name AS term_name,
                        COUNT(ar.id) AS total_students,
                        SUM(CASE WHEN ar.is_approved=1 THEN 1 ELSE 0 END) AS approved_count,
                        AVG(ar.marks_obtained) AS avg_mark
                 FROM assessments a
                 JOIN assessment_results ar ON ar.assessment_id = a.id
                 JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
                 JOIN terms t ON t.id = ayt.term_id
                 $where
                 GROUP BY a.id
                 HAVING SUM(CASE WHEN ar.is_approved=0 THEN 1 ELSE 0 END) > 0
                 ORDER BY a.assessment_date DESC, a.id",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($assessments as &$ass) {
                $results = $this->dbQuery(
                    "SELECT ar.id AS result_id, sae.student_id, ar.marks_obtained, ar.entry_status,
                            ar.grade, ar.points, ar.is_approved, ar.remarks, ar.moderation_note,
                            CONCAT(p.first_name, ' ', p.last_name) AS student_name, s.admission_no
                     FROM assessment_results ar
                     JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                     JOIN students s ON s.id = sae.student_id
                     JOIN persons p ON p.id = s.person_id
                     WHERE ar.assessment_id = :aid AND ar.is_submitted=1
                     ORDER BY p.first_name",
                    [':aid' => $ass['assessment_id']]
                )->fetchAll(PDO::FETCH_ASSOC);
                $ass['results'] = $results;
                $ass['pending_count'] = (int) $ass['total_students'] - (int) $ass['approved_count'];
            }

            return $this->successResponse($assessments);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getPendingModeration');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** POST /api/academic/approve-assessment — approve individual assessment results */
    public function approveAssessmentResults(int $assessmentId, ?int $studentId): array
    {
        try {
            $this->dbQuery(
                "UPDATE assessment_results ar
                 JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                 SET ar.is_approved=1
                 WHERE ar.assessment_id=:aid AND sae.student_id=:sid",
                [':aid' => $assessmentId, ':sid' => $studentId]
            );
            return $this->successResponse(null, 'Result approved');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::approveAssessmentResults');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** POST /api/academic/reject-assessment — reject individual result */
    public function rejectAssessmentResult(int $assessmentId, int $studentId, string $reason): array
    {
        try {
            $this->dbQuery(
                "UPDATE assessment_results ar
                 JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                 SET ar.is_approved=0, ar.remarks=:reason
                 WHERE ar.assessment_id=:aid AND sae.student_id=:sid",
                [':aid' => $assessmentId, ':sid' => $studentId, ':reason' => $reason]
            );
            return $this->successResponse(null, 'Result rejected');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::rejectAssessmentResult');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/curriculum — backward-compatible flat curriculum list
     */
    public function getCurriculum(array $query, ?int $id = null): array
    {
        try {
            if ($id) {
                $conditions = ['s.id = :id'];
                $params = [':id' => $id];
                $this->addCurriculumScope($conditions, $params, $query, 's.learning_area_id', 's.grade_level', 'curriculum_one_scope');
                $row = $this->dbQuery(
                    "SELECT s.id, s.code AS strand_code, s.grade_level,
                            la.id AS learning_area_id, la.name AS learning_area,
                            la.learning_area_family_id, laf.name AS learning_area_family,
                            s.name AS strand,
                            (SELECT GROUP_CONCAT(ssx.name ORDER BY ssx.sort_order, ssx.id SEPARATOR '; ')
                               FROM sub_strands ssx WHERE ssx.strand_id = s.id) AS sub_strands,
                            (SELECT COUNT(*) FROM sub_strands ssx WHERE ssx.strand_id = s.id) AS sub_strand_count,
                            (SELECT GROUP_CONCAT(lo.outcome SEPARATOR '; ')
                               FROM learning_outcomes lo WHERE lo.strand_id = s.id) AS indicators,
                            (SELECT COUNT(*) FROM learning_outcomes lo WHERE lo.strand_id = s.id) AS outcome_count
                     FROM strands s
                JOIN learning_areas la ON la.id = s.learning_area_id
                LEFT JOIN learning_area_families laf ON laf.id = la.learning_area_family_id
                     WHERE " . implode(' AND ', $conditions),
                    $params
                )->fetch(PDO::FETCH_ASSOC);
                return $this->successResponse($row ?: null);
            }

            $page = max(1, (int) ($query['page'] ?? 1));
            $limit = min(100, max(1, (int) ($query['limit'] ?? 15)));
            $offset = ($page - 1) * $limit;

            $conds = [];
            $params = [];
            if (!empty($query['learning_area_family_id'])) {
                $conds[] = 'la.learning_area_family_id = :lafid';
                $params[':lafid'] = (int) $query['learning_area_family_id'];
            } elseif (!empty($query['learning_area_id'])) {
                $conds[] = 's.learning_area_id = :laid';
                $params[':laid'] = (int) $query['learning_area_id'];
            } elseif (!empty($query['learning_area'])) {
                $conds[] = 'la.name LIKE :la';
                $params[':la'] = '%' . $query['learning_area'] . '%';
            }
            if (!empty($query['strand_id'])) {
                $conds[] = 's.id = :sid';
                $params[':sid'] = (int) $query['strand_id'];
            } elseif (!empty($query['strand'])) {
                $conds[] = 's.name LIKE :st';
                $params[':st'] = '%' . $query['strand'] . '%';
            }
            if (!empty($query['grade_level'])) {
                $conds[] = 's.grade_level = :gl';
                $params[':gl'] = $query['grade_level'];
            }
            if (!empty($query['search'])) {
                $conds[] = '(s.name LIKE :q OR s.code LIKE :q3 OR la.name LIKE :q2
                             OR s.id IN (SELECT strand_id FROM sub_strands WHERE name LIKE :q4))';
                $params[':q'] = '%' . $query['search'] . '%';
                $params[':q2'] = '%' . $query['search'] . '%';
                $params[':q3'] = '%' . $query['search'] . '%';
                $params[':q4'] = '%' . $query['search'] . '%';
            }
            $this->addCurriculumScope($conds, $params, $query, 's.learning_area_id', 's.grade_level', 'curriculum_scope');
            $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

            $total = (int) $this->dbQuery(
                "SELECT COUNT(DISTINCT s.id) AS total
                 FROM strands s
                 LEFT JOIN learning_areas la ON la.id = s.learning_area_id
                 $where",
                $params
            )->fetch(PDO::FETCH_ASSOC)['total'];

            $summary = $this->dbQuery(
                "SELECT COUNT(DISTINCT la.learning_area_family_id) AS learning_areas,
                        COUNT(DISTINCT s.id) AS strands,
                        COUNT(DISTINCT ss.id) AS sub_strands,
                        COUNT(DISTINCT lo.id) AS learning_outcomes
                 FROM strands s
                 LEFT JOIN learning_areas la ON la.id = s.learning_area_id
                 LEFT JOIN sub_strands ss ON ss.strand_id = s.id AND ss.status = 'active'
                 LEFT JOIN learning_outcomes lo ON lo.strand_id = s.id
                 LEFT JOIN sub_strand_competencies ssc ON ssc.sub_strand_id = ss.id
                 $where",
                $params
            )->fetch(PDO::FETCH_ASSOC);

            $rows = $this->dbQuery(
                "SELECT s.id, s.code AS strand_code, s.grade_level,
                        la.name AS learning_area, la.id AS learning_area_id,
                        la.learning_area_family_id, laf.name AS learning_area_family,
                        s.name AS strand,
                        (SELECT GROUP_CONCAT(ssx.name ORDER BY ssx.sort_order, ssx.id SEPARATOR '; ')
                           FROM sub_strands ssx WHERE ssx.strand_id = s.id) AS sub_strands,
                        (SELECT COUNT(*) FROM sub_strands ssx WHERE ssx.strand_id = s.id) AS sub_strand_count,
                        (SELECT COUNT(*) FROM learning_outcomes lo WHERE lo.strand_id = s.id) AS outcome_count
                 FROM strands s
                 LEFT JOIN learning_areas la ON la.id = s.learning_area_id
                 LEFT JOIN learning_area_families laf ON laf.id = la.learning_area_family_id
                 $where
                 ORDER BY s.grade_level, COALESCE(laf.name, la.name), s.sort_order, s.id
                 LIMIT $limit OFFSET $offset",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'data' => $rows,
                'curriculum' => $rows,
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
                'total' => $total,
                'summary' => [
                    'learning_areas' => (int) ($summary['learning_areas'] ?? 0),
                    'strands' => (int) ($summary['strands'] ?? 0),
                    'sub_strands' => (int) ($summary['sub_strands'] ?? 0),
                    'learning_outcomes' => (int) ($summary['learning_outcomes'] ?? 0),
                ],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getCurriculum');
            return $this->successResponse(['data' => [], 'curriculum' => [], 'total' => 0, 'pagination' => ['page' => 1, 'limit' => 15, 'total' => 0]]);
        }
    }

    // ==================== TEACHER PORTAL (my-*/intern-*) ====================

    /**
     * GET /api/academic/my-classes
     * Classes + subjects assigned to the logged-in teaching staff for the
     * current academic year. Identity comes from the JWT (user -> staff).
     */
    public function getMyClasses(int $staffId, array $query = []): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT
                    c.id AS class_id,
                    c.name AS class_name,
                    GROUP_CONCAT(DISTINCT st.name ORDER BY st.name SEPARATOR ', ') AS stream_name,
                    (SELECT COUNT(*) FROM student_academic_enrollments sae
                       JOIN academic_year_class_streams aycs2 ON aycs2.id = sae.academic_year_class_stream_id
                      WHERE aycs2.academic_year_class_id = ayc.id AND sae.enrollment_status = 'active') AS student_count,
                    la.id AS subject_id,
                    la.name AS subject_name,
                    0 AS lessons_per_week,
                    CONCAT(p.first_name, ' ', p.last_name) AS class_teacher_name,
                    ayc.status AS status
                 FROM vw_teacher_effective_stream_learning_areas tscope
                 JOIN academic_year_class_streams aycs ON aycs.id = tscope.academic_year_class_stream_id
                 JOIN academic_year_class_learning_areas aycla ON aycla.id = (
                     SELECT sla.academic_year_class_learning_area_id FROM academic_year_class_stream_learning_areas sla WHERE sla.id = tscope.academic_year_class_stream_learning_area_id
                 )
                 JOIN learning_areas la ON la.id = aycla.learning_area_id
                 JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 JOIN academic_years ay ON ay.id = ayc.academic_year_id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 LEFT JOIN staff cts ON cts.id = aycs.class_teacher_id
                 LEFT JOIN persons p ON p.id = cts.person_id
                 WHERE tscope.staff_id = :staff AND ay.is_current = 1
                 GROUP BY c.id, la.id, ayc.id, p.first_name, p.last_name
                 ORDER BY c.name, la.name",
                [':staff' => $staffId]
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getMyClasses');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/intern-classes
     * Classes a teaching intern is attached to for the current academic year.
     */
    public function getInternClasses(int $staffId, array $query = []): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT
                    c.id AS class_id,
                    c.name AS class_name,
                    GROUP_CONCAT(DISTINCT st.name ORDER BY st.name SEPARATOR ', ') AS stream_name,
                    la.name AS subject_name,
                    CONCAT(p.first_name, ' ', p.last_name) AS teacher_name,
                    0 AS periods_per_week,
                    ayc.status AS status,
                    0 AS observations_count,
                    NULL AS mentor_id
                 FROM academic_year_class_learning_area_teachers ayclat
                 JOIN academic_year_class_learning_areas aycla ON aycla.id = ayclat.academic_year_class_learning_area_id
                 JOIN learning_areas la ON la.id = aycla.learning_area_id
                 JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 JOIN academic_years ay ON ay.id = ayc.academic_year_id
                 JOIN staff s ON s.id = ayclat.staff_id
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 WHERE ayclat.staff_id = :staff AND ay.is_current = 1
                 GROUP BY c.id, la.id, ayc.id, p.first_name, p.last_name
                 ORDER BY c.name, la.name",
                [':staff' => $staffId]
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getInternClasses');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/intern-subjects
     * Distinct subjects assigned to a teaching intern for the current year.
     */
    public function getInternSubjects(int $staffId, array $query = []): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT
                    la.id AS subject_id,
                    la.name AS subject_name,
                    la.level_band AS learning_area,
                    COUNT(DISTINCT ayc.id) AS classes_count,
                    CONCAT(p.first_name, ' ', p.last_name) AS teacher_name,
                    0 AS periods_per_week,
                    ayclat.role AS status,
                    (SELECT COUNT(*) FROM strands s2 WHERE s2.learning_area_id = la.id AND s2.status = 'active') AS total_strands,
                    0 AS completed_strands
                 FROM academic_year_class_learning_area_teachers ayclat
                 JOIN academic_year_class_learning_areas aycla ON aycla.id = ayclat.academic_year_class_learning_area_id
                 JOIN learning_areas la ON la.id = aycla.learning_area_id
                 JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                 JOIN academic_years ay ON ay.id = ayc.academic_year_id
                 JOIN staff s ON s.id = ayclat.staff_id
                 JOIN persons p ON p.id = s.person_id
                 WHERE ayclat.staff_id = :staff AND ay.is_current = 1
                 GROUP BY la.id, la.name, la.level_band, p.first_name, p.last_name, ayclat.role
                 ORDER BY la.name",
                [':staff' => $staffId]
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getInternSubjects');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/my-subjects
     * Subjects a teacher teaches with lesson-plan / scheme status. Returns both
     * `id` and `subject_id` for the overview and syllabus dropdown consumers.
     */
    public function getMySubjects(int $staffId, array $query = []): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT
                    la.id AS id,
                    la.id AS subject_id,
                    la.name AS subject_name,
                    COUNT(DISTINCT ayc.id) AS classes_count,
                    0 AS lessons_per_week,
                    CASE
                        WHEN COUNT(DISTINCT CASE WHEN sw.status = 'approved' THEN sw.id END) > 0 THEN 'approved'
                        WHEN COUNT(DISTINCT CASE WHEN sw.status IN ('draft', 'archived') THEN sw.id END) > 0 THEN 'draft'
                        ELSE 'not_started'
                    END AS scheme_status,
                    (SELECT COUNT(*) FROM lesson_plans lp
                       JOIN academic_year_class_learning_areas aycla2 ON aycla2.id = lp.academic_year_class_learning_area_id
                      WHERE aycla2.learning_area_id = la.id) AS lesson_plans_count,
                    (SELECT COUNT(*) FROM lesson_templates lt WHERE lt.learning_area_id = la.id AND lt.status = 'approved') AS required_plans,
                    ayclat.role AS status
                 FROM academic_year_class_learning_area_teachers ayclat
                 JOIN academic_year_class_learning_areas aycla ON aycla.id = ayclat.academic_year_class_learning_area_id
                 JOIN learning_areas la ON la.id = aycla.learning_area_id
                 JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                 JOIN academic_years ay ON ay.id = ayc.academic_year_id
                 LEFT JOIN schemes_of_work sw ON sw.academic_year_class_learning_area_id = aycla.id
                 WHERE ayclat.staff_id = :staff AND ay.is_current = 1
                 GROUP BY la.id, la.name, ayclat.role
                 ORDER BY la.name",
                [':staff' => $staffId]
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getMySubjects');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/my-schemes — schemes of work owned by the teacher.
     * Optional filter: subject_id (learning_area id).
     */
    public function getMySchemes(int $staffId, array $query = []): array
    {
        try {
            return $this->successResponse($this->queryTeacherSchemes($staffId, $query));
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getMySchemes');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/subject-schemes — teacher's schemes for one subject.
     * Optional filter: subject_id (learning_area id).
     */
    public function getSubjectSchemes(int $staffId, array $query = []): array
    {
        try {
            return $this->successResponse($this->queryTeacherSchemes($staffId, $query));
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getSubjectSchemes');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    private function queryTeacherSchemes(int $staffId, array $query): array
    {
        $where = 'sw.teacher_id = ?';
        $params = [$staffId];
        $scope = (new \App\API\Services\TeacherScopeService($this->db))->forUser(
            ['staff_id' => $staffId],
            !empty($query['academic_year_id']) ? (int) $query['academic_year_id'] : null,
            !empty($query['term_id']) ? (int) $query['term_id'] : null
        );
        $scopeParts = [];
        $classStreamIds = array_values(array_filter(array_map('intval', (array) ($scope['class_stream_ids'] ?? []))));
        // Teacher workflows use only canonical stream-learning-area records.
        // Legacy class-only rows are removed during data cleanup and are never
        // eligible for an actionable teacher scheme workflow.
        if ($classStreamIds) {
            $scopeParts[] = 'sws.id IN (' . implode(',', array_fill(0, count($classStreamIds), '?')) . ')';
            foreach ($classStreamIds as $id) $params[] = $id;
        }
        foreach ((array) ($scope['subject_assignments'] ?? []) as $assignment) {
            $streamId = (int) ($assignment['stream_id'] ?? 0);
            $areaId = (int) ($assignment['learning_area_id'] ?? 0);
            if ($streamId > 0 && $areaId > 0) {
                $scopeParts[] = '(sws.id = ? AND st.learning_area_id = ?)';
                $params[] = $streamId;
                $params[] = $areaId;
            }
        }
        $where .= ' AND sw.academic_year_class_stream_learning_area_id IS NOT NULL';
        if ($scopeParts) $where .= ' AND (' . implode(' OR ', $scopeParts) . ')';
        else $where .= ' AND 1 = 0';
        if (!empty($query['academic_year_id'])) {
            $where .= ' AND COALESCE(swc.academic_year_id, ayc.academic_year_id) = ?';
            $params[] = (int) $query['academic_year_id'];
        }
        if (!empty($query['term_id'])) {
            $where .= ' AND ayt.id = ?';
            $params[] = (int) $query['term_id'];
        }
        if (!empty($query['subject_id'])) {
            $where .= ' AND st.learning_area_id = ?';
            $params[] = (int) $query['subject_id'];
        }
        if (!empty($query['class_id'])) {
            $where .= ' AND c.id = ?';
            $params[] = (int) $query['class_id'];
        }
        return $this->dbQuery(
            "SELECT
                sw.id,
                st.learning_area_id AS subject_id,
                la.name AS subject_name,
                c.name AS class_name,
                c.id AS class_id,
                sws.id AS academic_year_class_stream_id,
                sws.stream_id,
                sn.name AS stream_name,
                ac.week_number,
                strand.name AS strand_name,
                substrand.name AS sub_strand_name,
                sw.scheme_workbook_id,
                swb.status AS workbook_status,
                SUBSTRING(t.code, 2) AS term,
                t.name AS term_name,
                CASE WHEN swb.status = 'submitted' THEN 'pending' ELSE sw.status END AS status,
                CASE WHEN swb.status = 'submitted' OR sw.status = 'approved' THEN 100 WHEN sw.status = 'draft' THEN 50 ELSE 0 END AS progress,
                sw.updated_at
             FROM schemes_of_work sw
             JOIN scheme_templates st ON st.id = sw.scheme_template_id
             JOIN learning_areas la ON la.id = st.learning_area_id
             LEFT JOIN academic_year_class_learning_areas aycla ON aycla.id = sw.academic_year_class_learning_area_id
             LEFT JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
             LEFT JOIN academic_year_class_stream_learning_areas swsla ON swsla.id = sw.academic_year_class_stream_learning_area_id
             LEFT JOIN academic_year_class_streams sws ON sws.id = swsla.academic_year_class_stream_id
             LEFT JOIN academic_year_classes swc ON swc.id = sws.academic_year_class_id
             LEFT JOIN classes c ON c.id = COALESCE(swc.class_id, ayc.class_id)
             LEFT JOIN streams sn ON sn.id = COALESCE(sws.stream_id, NULL)
             LEFT JOIN scheme_workbooks swb ON swb.id = sw.scheme_workbook_id
             LEFT JOIN strands strand ON strand.id = st.strand_id
             LEFT JOIN sub_strands substrand ON substrand.id = st.sub_strand_id
             LEFT JOIN academic_year_calendar ac ON ac.id = sw.academic_year_calendar_week_id
             LEFT JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
             LEFT JOIN terms t ON t.id = ayt.term_id
             WHERE $where
             ORDER BY la.name, c.name, sw.id",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== SYLLABUS VIEWS ====================

    /**
     * GET /api/academic/syllabus
     * Read-only flat curriculum list (strands x sub-strands) with learning
     * outcomes as competency indicators. Optional filters: grade_level,
     * learning_area (name), search.
     */
    public function getSyllabus(array $query = []): array
    {
        try {
            $where = ['s.status = :active'];
            $params = [':active' => 'active'];
            if (!empty($query['grade_level'])) {
                $where[] = 's.grade_level = :gl';
                $params[':gl'] = $query['grade_level'];
            }
            if (!empty($query['learning_area'])) {
                $where[] = 'la.name LIKE :la';
                $params[':la'] = '%' . $query['learning_area'] . '%';
            }
            if (!empty($query['search'])) {
                $where[] = '(s.name LIKE :q OR ss.name LIKE :q2 OR la.name LIKE :q3)';
                $params[':q'] = '%' . $query['search'] . '%';
                $params[':q2'] = '%' . $query['search'] . '%';
                $params[':q3'] = '%' . $query['search'] . '%';
            }
            $rows = $this->dbQuery(
                "SELECT
                    s.id,
                    s.grade_level,
                    la.name AS learning_area,
                    s.name AS strand,
                    ss.name AS sub_strand,
                    (SELECT GROUP_CONCAT(lo.outcome SEPARATOR '; ')
                       FROM learning_outcomes lo
                      WHERE lo.strand_id = s.id
                        AND ((lo.sub_strand_id IS NULL AND ss.id IS NULL) OR lo.sub_strand_id = ss.id)) AS indicators,
                    NULL AS assessment_criteria
                 FROM strands s
                 JOIN learning_areas la ON la.id = s.learning_area_id
                 LEFT JOIN sub_strands ss ON ss.strand_id = s.id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY la.name, s.sort_order, s.id, ss.sort_order",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getSyllabus');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/my-syllabus
     * Syllabus strands for the learning areas the teacher is assigned to.
     * Coverage status is derived from academic_year_class_learning_areas.
     */
    public function getMySyllabus(int $staffId, array $query = []): array
    {
        try {
            $where = ["s.status = :active",
                "la.id IN (SELECT DISTINCT aycla2.learning_area_id
                            FROM academic_year_class_learning_area_teachers ayclat
                            JOIN academic_year_class_learning_areas aycla2 ON aycla2.id = ayclat.academic_year_class_learning_area_id
                            JOIN academic_year_classes ayc2 ON ayc2.id = aycla2.academic_year_class_id
                            JOIN academic_years ay2 ON ay2.id = ayc2.academic_year_id
                            WHERE ayclat.staff_id = :staff AND ay2.is_current = 1)"];
            $params = [':active' => 'active', ':staff' => $staffId];
            if (!empty($query['subject_id'])) {
                $where[] = 'la.id = :subject_id';
                $params[':subject_id'] = (int) $query['subject_id'];
            }
            $rows = $this->dbQuery(
                "SELECT
                    s.id,
                    s.name AS strand,
                    ss.name AS sub_strand,
                    (SELECT GROUP_CONCAT(lo.outcome SEPARATOR '; ')
                       FROM learning_outcomes lo
                      WHERE lo.strand_id = s.id
                        AND ((lo.sub_strand_id IS NULL AND ss.id IS NULL) OR lo.sub_strand_id = ss.id)) AS indicators,
                    NULL AS assessment_criteria,
                    CASE
                        WHEN MAX(CASE WHEN aycla.status = 'covered' THEN 1 ELSE 0 END) = 1 THEN 'completed'
                        WHEN MAX(CASE WHEN aycla.status = 'in_progress' THEN 1 ELSE 0 END) = 1 THEN 'in_progress'
                        ELSE 'not_started'
                    END AS status
                 FROM strands s
                 JOIN learning_areas la ON la.id = s.learning_area_id
                 LEFT JOIN sub_strands ss ON ss.strand_id = s.id
                 LEFT JOIN academic_year_class_learning_areas aycla ON aycla.learning_area_id = la.id AND aycla.strand_id = s.id AND aycla.status <> 'skipped'
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY s.id, ss.id
                 ORDER BY la.name, s.sort_order, s.id, ss.sort_order",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getMySyllabus');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== ACADEMIC YEAR CALENDAR / HISTORY ====================

    /**
     * GET /api/academic/year-calendar
     * Current academic year's calendar days with a derived event type.
     */
    public function getYearCalendar(): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT
                    d.id AS calendar_day_id,
                    d.date,
                    d.title AS name,
                    COALESCE(cdt.name, 'event') AS type,
                    cdt.code AS type_code,
                    d.description,
                    d.is_manual,
                    ac.week_number,
                    ayt.term_id,
                    t.name AS term_name,
                    ac.week_start,
                    ac.week_end
                 FROM academic_year_calendar_days d
                 LEFT JOIN academic_year_calendar ac ON ac.id = d.academic_year_calendar_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
                 LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 LEFT JOIN calendar_day_types cdt ON cdt.id = d.calendar_day_type_id
                 WHERE ay.is_current = 1 OR d.academic_year_calendar_id = 0
                 ORDER BY ayt.term_id, ac.week_number, d.date"
            )->fetchAll(PDO::FETCH_ASSOC);

            // Attach school events (meetings, sports days, exams, AGM, prayer days,
            // results release, etc.) to their calendar date so the frontend can show
            // them alongside the day type and create/edit them from the page.
            $byDate = [];
            $yearRow = $this->dbQuery(
                "SELECT id, start_date, end_date FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if ($yearRow) {
                $events = $this->dbQuery(
                    "SELECT id, title, type, location, start_at, status
                     FROM school_events
                     WHERE DATE(start_at) BETWEEN ? AND ?
                     ORDER BY start_at",
                    [$yearRow['start_date'], $yearRow['end_date']]
                )->fetchAll(PDO::FETCH_ASSOC);
                foreach ($events as $event) {
                    $byDate[substr((string) $event['start_at'], 0, 10)][] = [
                        'id' => (int) $event['id'],
                        'title' => $event['title'],
                        'type' => $event['type'],
                        'location' => $event['location'],
                        'start_at' => $event['start_at'],
                        'status' => $event['status'],
                    ];
                }
            }
            foreach ($rows as &$row) {
                // Skip an event when it is merely the calendar-sync mirror of the
                // day itself (same title) - otherwise a holiday day row would show
                // its own name twice (row title + event badge).
                $row['events'] = array_values(array_filter(
                    $byDate[$row['date']] ?? [],
                    function ($e) use ($row) {
                        $dayTitle = trim((string) ($row['name'] ?? ''));
                        return $dayTitle === '' || (string) ($e['title'] ?? '') !== $dayTitle;
                    }
                ));
            }
            unset($row);

            // Surface official free-form school events (Ministry of Education
            // holidays/breaks and national assessment windows such as KPSEA,
            // KILEA, KJSEA/KPLEA and KCSE) that fall OUTSIDE the generated term
            // day grid, so the full official year calendar is visible on the
            // Year Calendar page. Events that already have a matching calendar
            // day (e.g. term opening/closing days) stay as badges on that row.
            if ($yearRow) {
                // Map each date to the titles already carried by its calendar-day
                // row(s) so an event is only suppressed when it merely repeats the
                // day's own name (e.g. a term opening day). Timed events like the
                // national assessments still surface alongside holiday days.
                $titlesByDate = [];
                foreach ($rows as $row) {
                    $titlesByDate[$row['date']][] = (string) ($row['name'] ?? '');
                }
                $free = $this->dbQuery(
                    "SELECT id, title, description, type, location, start_at, status
                     FROM school_events
                     WHERE calendar_day_id IS NULL AND status <> 'cancelled'
                       AND DATE(start_at) BETWEEN ? AND ?
                     ORDER BY start_at",
                    [$yearRow['start_date'], $yearRow['end_date']]
                )->fetchAll(PDO::FETCH_ASSOC);

                $typeMap = [
                    'exam'           => ['exam_day', 'Exam Day'],
                    'half_day'       => ['half_day', 'Half Day'],
                    'school_holiday' => ['school_holiday', 'School Holiday'],
                    'public_holiday' => ['public_holiday', 'Public Holiday'],
                    'holiday'        => ['holiday', 'Holiday'],
                    'special_event'  => ['special_event', 'Special Event'],
                    'opening'        => ['special_event', 'Special Event'],
                    'closing'        => ['special_event', 'Special Event'],
                ];

                foreach ($free as $f) {
                    $date = substr((string) $f['start_at'], 0, 10);
                    $dayTitles = $titlesByDate[$date] ?? [];
                    if (in_array((string) $f['title'], $dayTitles, true)) {
                        continue; // already shown as that day's own row
                    }
                    $code = $typeMap[$f['type']] ?? ['special_event', 'Special Event'];
                    $rows[] = [
                        'calendar_day_id' => null,
                        'date' => $date,
                        'end_date' => substr((string) ($f['end_at'] ?? ''), 0, 10) ?: $date,
                        'start_time' => substr((string) ($f['start_at'] ?? ''), 11, 5) ?: '',
                        'end_time' => substr((string) ($f['end_at'] ?? ''), 11, 5) ?: '',
                        'name' => $f['title'],
                        'type' => $code[1],
                        'type_code' => $code[0],
                        'description' => $f['description'],
                        'is_manual' => 1,
                        'week_number' => null,
                        'term_id' => null,
                        'term_name' => null,
                        'week_start' => null,
                        'week_end' => null,
                        'events' => [],
                        'event_id' => (int) $f['id'],
                        'event_title' => $f['title'],
                        'event_type' => $f['type'],
                        'location' => $f['location'],
                        'start_at' => $f['start_at'],
                        'end_at' => $f['end_at'] ?? null,
                        'status' => $f['status'],
                    ];
                }

                // Full-year chronological order (stable by original row index so
                // same-date rows keep their relative order).
                $indexed = [];
                foreach ($rows as $i => $row) {
                    $indexed[] = [$row['date'], $i, $row];
                }
                usort($indexed, function ($a, $b) {
                    return [$a[0], $a[1]] <=> [$b[0], $b[1]];
                });
                $rows = array_map(function ($e) {
                    return $e[2];
                }, $indexed);
            }

            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getYearCalendar');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/calendar/days/{year_id}
     * Per-date calendar rows for a specific year, including year-wide gazetted
     * holidays (calendar_id = 0) so they can be reviewed/edited alongside term
     * days. Each row carries the calendar_day_id, type code/name and is_manual
     * flag required by the day editor.
     */
    public function getCalendarDays(int $yearId): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT
                    d.id AS calendar_day_id,
                    d.date,
                    d.title,
                    d.description,
                    d.is_manual,
                    COALESCE(cdt.code, 'school_day') AS type_code,
                    COALESCE(cdt.name, 'School Day') AS type_name,
                    ac.week_number,
                    ayt.term_id,
                    t.name AS term_name
                 FROM academic_year_calendar_days d
                 LEFT JOIN academic_year_calendar ac ON ac.id = d.academic_year_calendar_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 LEFT JOIN calendar_day_types cdt ON cdt.id = d.calendar_day_type_id
                 WHERE (d.academic_year_calendar_id = 0 OR ayt.academic_year_id = ?)
                 ORDER BY d.date, ac.week_number",
                [$yearId]
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getCalendarDays');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * PUT /api/academic/calendar/day/{id}
     * Mark a calendar day as a holiday/closure/special event (or back to a
     * normal school day). Manual days are preserved across calendar
     * regenerations via the is_manual flag.
     */
    public function updateCalendarDay(int $dayId, array $data): array
    {
        try {
            if (!$dayId) {
                return $this->errorResponse('Calendar day ID is required', 400);
            }

            $code = $data['day_type'] ?? null;
            $allowed = ['school_day', 'half_day', 'exam_day', 'special_event', 'holiday', 'public_holiday', 'school_holiday'];
            if (!is_string($code) || !in_array($code, $allowed, true)) {
                return $this->errorResponse('Invalid day_type', 400);
            }

            $stmt = $this->dbQuery("SELECT id FROM calendar_day_types WHERE code = ?", [$code]);
            $typeId = (int) $stmt->fetchColumn();
            if (!$typeId) {
                return $this->errorResponse('Unknown day_type', 400);
            }

            $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 100);
            $description = mb_substr(trim((string) ($data['description'] ?? '')), 0, 500);
            $manual = $code === 'school_day' ? 0 : 1;

            $this->dbQuery(
                "UPDATE academic_year_calendar_days
                 SET calendar_day_type_id = ?, title = ?, description = ?, is_manual = ?
                 WHERE id = ?",
                [$typeId, $title !== '' ? $title : null, $description !== '' ? $description : null, $manual, $dayId]
            );

            $sync = new CalendarSyncService($this->db);
            $sync->syncDay($dayId);

            return $this->successResponse([
                'id' => (int) $dayId,
                'day_type' => $code,
                'is_manual' => (bool) $manual,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::updateCalendarDay');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/year-history
     * All academic years with term counts and student enrolment totals.
     */
    public function getYearHistory(): array
    {
        try {
            $rows = $this->dbQuery(
                "SELECT
                    ay.id,
                    ay.year_name AS name,
                    ay.start_date,
                    ay.end_date,
                    ay.status,
                    ay.is_current,
                    (SELECT COUNT(*) FROM academic_year_terms ayt2 WHERE ayt2.academic_year_id = ay.id) AS terms,
                    (SELECT COUNT(DISTINCT sae.student_id) FROM student_academic_enrollments sae
                      WHERE sae.academic_year_id = ay.id AND sae.enrollment_status <> 'withdrawn') AS total_students,
                    NULL AS performance_avg
                 FROM academic_years ay
                 ORDER BY ay.start_date DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getYearHistory');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== LESSON PLANS BY CLASS ====================

    /**
     * GET /api/academic/lesson-plans/by-class
     * Coverage per class for the current year. When `class_id` is provided,
     * returns per-subject detail for that class instead.
     */
    public function getLessonPlansByClass(array $query): array
    {
        try {
            $classId = (int) ($query['class_id'] ?? 0);
            if ($classId) {
                $class = $this->dbQuery(
                    "SELECT c.name AS class_name
                     FROM academic_year_classes ayc
                     JOIN classes c ON c.id = ayc.class_id
                     JOIN academic_years ay ON ay.id = ayc.academic_year_id
                     WHERE ayc.class_id = :class_id AND ay.is_current = 1
                     LIMIT 1",
                    [':class_id' => $classId]
                )->fetch(PDO::FETCH_ASSOC);
                $subjects = $this->dbQuery(
                    "SELECT
                        la.name AS subject_name,
                        CONCAT(p.first_name, ' ', p.last_name) AS teacher_name,
                        CASE WHEN lp.id IS NOT NULL THEN 1 ELSE 0 END AS has_plan,
                        COALESCE(lp.status, '') AS plan_status,
                        DATE(lp.updated_at) AS last_submitted
                     FROM academic_year_class_learning_areas aycla
                     JOIN learning_areas la ON la.id = aycla.learning_area_id
                     JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                     JOIN classes c ON c.id = ayc.class_id
                     JOIN academic_years ay ON ay.id = ayc.academic_year_id
                     LEFT JOIN academic_year_class_learning_area_teachers ayclat ON ayclat.academic_year_class_learning_area_id = aycla.id AND ayclat.role = 'subject_teacher'
                     LEFT JOIN staff s ON s.id = ayclat.staff_id
                     LEFT JOIN persons p ON p.id = s.person_id
                     LEFT JOIN lesson_plans lp ON lp.academic_year_class_learning_area_id = aycla.id
                     WHERE ayc.class_id = :class_id AND ay.is_current = 1
                     GROUP BY la.id, p.first_name, p.last_name, lp.id, lp.status, lp.updated_at
                     ORDER BY la.name",
                    [':class_id' => $classId]
                )->fetchAll(PDO::FETCH_ASSOC);
                return $this->successResponse([
                    'class_name' => $class['class_name'] ?? 'Class',
                    'subjects' => $subjects,
                ]);
            }

            $page = max(1, (int) ($query['page'] ?? 1));
            $limit = min(100, max(1, (int) ($query['limit'] ?? 15)));
            $offset = ($page - 1) * $limit;

            $where = ['ay.is_current = 1'];
            $params = [];
            if (!empty($query['class_id'])) {
                $where[] = 'ayc.class_id = :class_id';
                $params[':class_id'] = (int) $query['class_id'];
            }
            if (!empty($query['search'])) {
                $where[] = 'c.name LIKE :q';
                $params[':q'] = '%' . $query['search'] . '%';
            }
            $whereSql = implode(' AND ', $where);

            $total = (int) $this->dbQuery(
                "SELECT COUNT(*) FROM academic_year_classes ayc
                 JOIN classes c ON c.id = ayc.class_id
                 JOIN academic_years ay ON ay.id = ayc.academic_year_id
                 WHERE $whereSql",
                $params
            )->fetchColumn();

            $rows = $this->dbQuery(
                "SELECT
                    ayc.id AS id,
                    ayc.id AS class_id,
                    c.name AS class_name,
                    ayc.status,
                    COUNT(DISTINCT aycla.id) AS total_subjects,
                    COUNT(DISTINCT CASE WHEN lp.id IS NOT NULL THEN aycla.id END) AS with_plans,
                    CASE WHEN COUNT(DISTINCT aycla.id) > 0
                         THEN ROUND(COUNT(DISTINCT CASE WHEN lp.id IS NOT NULL THEN aycla.id END) * 100.0 / COUNT(DISTINCT aycla.id), 1)
                         ELSE 0
                    END AS coverage_percentage
                 FROM academic_year_classes ayc
                 JOIN classes c ON c.id = ayc.class_id
                 JOIN academic_years ay ON ay.id = ayc.academic_year_id
                 LEFT JOIN academic_year_class_learning_areas aycla ON aycla.academic_year_class_id = ayc.id
                 LEFT JOIN lesson_plans lp ON lp.academic_year_class_learning_area_id = aycla.id
                 WHERE $whereSql
                 GROUP BY ayc.id, c.name, ayc.status
                 ORDER BY c.name
                 LIMIT $limit OFFSET $offset",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'data' => $rows,
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
                'total' => $total,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getLessonPlansByClass');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ==================== CURRICULUM CRUD (legacy flat endpoint) ====================

    /**
     * POST /api/academic/curriculum
     * Create a flat curriculum entry: resolves (or creates) the strand and
     * sub-strand by name for the given learning area + grade level, then
     * attaches the competency indicator as a learning outcome.
     */
    public function createCurriculumEntry(array $data): array
    {
        try {
            if (empty($data['learning_area']) || empty($data['strand']) || empty($data['grade_level'])) {
                return $this->errorResponse('learning_area, strand and grade_level are required', 400);
            }
            $laId = (int) $this->dbQuery(
                "SELECT id FROM learning_areas WHERE name = :name LIMIT 1",
                [':name' => $data['learning_area']]
            )->fetchColumn();
            if (!$laId) {
                return $this->errorResponse('The selected learning area does not exist', 400);
            }

            $strandId = $this->resolveOrCreateStrand($laId, $data['strand'], $data['grade_level']);
            if (!$strandId) {
                return $this->errorResponse('Could not resolve the strand', 500);
            }

            $subStrandId = null;
            if (!empty($data['sub_strand'])) {
                $subStrandId = $this->resolveOrCreateSubStrand($strandId, $data['sub_strand']);
            }

            if (!empty($data['indicators'])) {
                $this->dbQuery(
                    "INSERT INTO learning_outcomes (learning_area_id, strand_id, sub_strand_id, outcome, grade_level)
                     VALUES (:laid, :sid, :ssid, :outcome, :gl)",
                    [
                        ':laid' => $laId,
                        ':sid' => $strandId,
                        ':ssid' => $subStrandId,
                        ':outcome' => $data['indicators'],
                        ':gl' => $data['grade_level'],
                    ]
                );
            }

            return $this->successResponse(['id' => $strandId], 'Curriculum entry created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::createCurriculumEntry');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * PUT /api/academic/curriculum/{id} — update a flat curriculum entry.
     * The id references the strand row.
     */
    public function updateCurriculumEntry(int $id, array $data): array
    {
        try {
            $fields = [];
            $params = [':id' => $id];
            if (isset($data['strand'])) {
                $fields[] = 'name=:name';
                $params[':name'] = $data['strand'];
            }
            if (isset($data['grade_level'])) {
                $fields[] = 'grade_level=:gl';
                $params[':gl'] = $data['grade_level'];
            }
            if (!empty($data['learning_area'])) {
                $laId = (int) $this->dbQuery(
                    "SELECT id FROM learning_areas WHERE name = :name LIMIT 1",
                    [':name' => $data['learning_area']]
                )->fetchColumn();
                if (!$laId) return $this->errorResponse('The selected learning area does not exist', 400);
                $fields[] = 'learning_area_id=:laid';
                $params[':laid'] = $laId;
            }
            if ($fields) {
                $this->dbQuery("UPDATE strands SET " . implode(', ', $fields) . " WHERE id=:id", $params);
            }

            if (array_key_exists('sub_strand', $data)) {
                $subStrand = $this->dbQuery(
                    "SELECT id FROM sub_strands WHERE strand_id = :sid ORDER BY id LIMIT 1",
                    [':sid' => $id]
                )->fetch(PDO::FETCH_ASSOC);
                if ($data['sub_strand'] !== null && $data['sub_strand'] !== '') {
                    if ($subStrand) {
                        $this->dbQuery(
                            "UPDATE sub_strands SET name=:name WHERE id=:ssid",
                            [':name' => $data['sub_strand'], ':ssid' => (int) $subStrand['id']]
                        );
                    } else {
                        $this->resolveOrCreateSubStrand($id, $data['sub_strand']);
                    }
                }
            }

            if (!empty($data['indicators'])) {
                $outcome = $this->dbQuery(
                    "SELECT id FROM learning_outcomes WHERE strand_id = :sid ORDER BY id LIMIT 1",
                    [':sid' => $id]
                )->fetch(PDO::FETCH_ASSOC);
                if ($outcome) {
                    $this->dbQuery(
                        "UPDATE learning_outcomes SET outcome=:outcome WHERE id=:oid",
                        [':outcome' => $data['indicators'], ':oid' => (int) $outcome['id']]
                    );
                } else {
                    $strand = $this->dbQuery(
                        "SELECT learning_area_id, grade_level FROM strands WHERE id = :id LIMIT 1",
                        [':id' => $id]
                    )->fetch(PDO::FETCH_ASSOC);
                    if ($strand) {
                        $this->dbQuery(
                            "INSERT INTO learning_outcomes (learning_area_id, strand_id, sub_strand_id, outcome, grade_level)
                             VALUES (:laid, :sid, NULL, :outcome, :gl)",
                            [
                                ':laid' => (int) $strand['learning_area_id'],
                                ':sid' => $id,
                                ':outcome' => $data['indicators'],
                                ':gl' => $strand['grade_level'],
                            ]
                        );
                    }
                }
            }

            return $this->successResponse(['id' => $id], 'Curriculum entry updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::updateCurriculumEntry');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * DELETE /api/academic/curriculum/{id} — remove a flat curriculum entry
     * (learning outcomes + sub-strands + strand).
     */
    public function deleteCurriculumEntry(int $id): array
    {
        try {
            $this->dbQuery("DELETE FROM learning_outcomes WHERE strand_id=:sid", [':sid' => $id]);
            $this->dbQuery("DELETE FROM sub_strands WHERE strand_id=:sid", [':sid' => $id]);
            $this->dbQuery("DELETE FROM strands WHERE id=:sid", [':sid' => $id]);
            return $this->successResponse(null, 'Curriculum entry deleted');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deleteCurriculumEntry');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    private function resolveOrCreateStrand(int $laId, string $name, string $gradeLevel): ?int
    {
        $existing = $this->dbQuery(
            "SELECT id FROM strands WHERE learning_area_id = :laid AND name = :name AND grade_level = :gl LIMIT 1",
            [':laid' => $laId, ':name' => $name, ':gl' => $gradeLevel]
        )->fetchColumn();
        if ($existing) return (int) $existing;

        $prefix = $this->dbQuery("SELECT code FROM learning_areas WHERE id=:id", [':id' => $laId])->fetchColumn();
        $cnt = (int) $this->dbQuery("SELECT COUNT(*) FROM strands WHERE learning_area_id=:laid", [':laid' => $laId])->fetchColumn();
        $code = ($prefix ?: 'LA') . '-S' . ($cnt + 1);
        $this->dbQuery(
            "INSERT INTO strands (learning_area_id, grade_level, code, name, description, sort_order, status)
             VALUES (:laid, :gl, :code, :name, NULL, 1, 'active')",
            [':laid' => $laId, ':gl' => $gradeLevel, ':code' => $code, ':name' => $name]
        );
        return (int) $this->db->lastInsertId();
    }

    private function resolveOrCreateSubStrand(int $strandId, string $name): ?int
    {
        $existing = $this->dbQuery(
            "SELECT id FROM sub_strands WHERE strand_id = :sid AND name = :name LIMIT 1",
            [':sid' => $strandId, ':name' => $name]
        )->fetchColumn();
        if ($existing) return (int) $existing;

        $s = $this->dbQuery("SELECT code FROM strands WHERE id=:id", [':id' => $strandId])->fetch(PDO::FETCH_ASSOC);
        $cnt = (int) $this->dbQuery("SELECT COUNT(*) FROM sub_strands WHERE strand_id=:sid", [':sid' => $strandId])->fetchColumn();
        $code = ($s['code'] ?? 'S') . '-SS' . ($cnt + 1);
        $this->dbQuery(
            "INSERT INTO sub_strands (strand_id, code, name, description, sort_order, status)
             VALUES (:sid, :code, :name, NULL, 1, 'active')",
            [':sid' => $strandId, ':code' => $code, ':name' => $name]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * GET /api/academic/portfolio/all/{studentId}
     * Returns cumulative portfolio data across ALL years for print/PDF.
     */
    public function getPortfolioAll(int $studentId): array
    {
        try {
            $st = $this->dbQuery(
                "SELECT s.id, p.first_name, p.middle_name, p.last_name, s.admission_no, p.photo_url,
                        c.name AS class_name, st.name AS stream_name
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 WHERE s.id = :sid",
                [':sid' => $studentId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$st) return $this->errorResponse('Student not found', 404);

            $portfolios = $this->dbQuery(
                "SELECT * FROM portfolios WHERE student_id = :sid ORDER BY academic_year DESC",
                [':sid' => $studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $artifacts = $this->dbQuery(
                "SELECT pa.*, cc.name AS competency_name, cv.name AS value_name,
                        p.academic_year
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 LEFT JOIN core_competencies cc ON cc.id = pa.competency_id
                 LEFT JOIN core_values cv ON cv.id = pa.value_id
                 WHERE p.student_id = :sid
                 ORDER BY pa.upload_date DESC",
                [':sid' => $studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $compSummary = $this->dbQuery(
                "SELECT cc.name AS competency_name,
                        COUNT(pa.id) AS artifact_count,
                        ROUND(AVG(pa.rating), 1) AS avg_rating,
                        MAX(pa.rating) AS highest_rating
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 JOIN core_competencies cc ON cc.id = pa.competency_id
                 WHERE p.student_id = :sid AND pa.competency_id IS NOT NULL
                 GROUP BY cc.id, cc.name
                 ORDER BY artifact_count DESC",
                [':sid' => $studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $valsSummary = $this->dbQuery(
                "SELECT cv.name AS value_name, COUNT(pa.id) AS artifact_count
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 JOIN core_values cv ON cv.id = pa.value_id
                 WHERE p.student_id = :sid AND pa.value_id IS NOT NULL
                 GROUP BY cv.id, cv.name
                 ORDER BY artifact_count DESC",
                [':sid' => $studentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            $fbRows = $this->dbQuery(
                "SELECT pa.teacher_feedback
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 WHERE p.student_id = :sid
                   AND pa.teacher_feedback IS NOT NULL
                   AND pa.teacher_feedback != ''
                 ORDER BY pa.upload_date DESC",
                [':sid' => $studentId]
            )->fetchAll(PDO::FETCH_ASSOC);
            $teacherFeedback = implode("\n---\n", array_column($fbRows, 'teacher_feedback'));

            $years = array_values(array_unique(array_filter(array_column($artifacts, 'academic_year'))));
            sort($years);
            $yearRange = $years
                ? (count($years) > 1 ? min($years) . ' \u2013 ' . max($years) : (string) $years[0])
                : (string) date('Y');

            return $this->successResponse([
                'student' => $st,
                'portfolios' => $portfolios,
                'artifacts' => $artifacts,
                'competencySummary' => $compSummary,
                'valuesSummary' => $valsSummary,
                'teacherFeedback' => $teacherFeedback,
                'yearRange' => $yearRange,
                'totalArtifacts' => count($artifacts),
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getPortfolioAll');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/academic/portfolio/list — List portfolios for a student or class
     */
    public function getPortfolioList(array $query): array
    {
        try {
            $studentId = (int) ($query['student_id'] ?? 0);
            $classId = (int) ($query['class_id'] ?? 0);
            $status = $query['status'] ?? '';

            $conds = [];
            $params = [];
            if ($studentId) {
                $conds[] = 'p.student_id = :sid';
                $params[':sid'] = $studentId;
            }
            if ($status) {
                $conds[] = 'p.status = :st';
                $params[':st'] = $status;
            }

            $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
            if ($classId) {
                $sql = "
                    SELECT p.*, pn.first_name, pn.middle_name, pn.last_name, s.admission_no,
                           c.name AS class_name, st.name AS stream_name,
                           (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                    FROM portfolios p
                    JOIN students s ON s.id = p.student_id
                    JOIN persons pn ON pn.id = s.person_id
                    LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes c ON c.id = ayc.class_id
                    WHERE ayc.class_id = :cid $where
                    ORDER BY p.last_updated DESC
                ";
                $params[':cid'] = $classId;
            } else {
                $sql = "
                    SELECT p.*, pn.first_name, pn.middle_name, pn.last_name, s.admission_no,
                           c.name AS class_name, st.name AS stream_name,
                           (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                    FROM portfolios p
                    JOIN students s ON s.id = p.student_id
                    JOIN persons pn ON pn.id = s.person_id
                    LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes c ON c.id = ayc.class_id
                    $where
                    ORDER BY p.last_updated DESC
                ";
            }

            $stmt = $this->dbQuery($sql, $params);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return $this->successResponse($rows);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getPortfolioList');
            return $this->successResponse([]);
        }
    }

    /**
     * GET /api/academic/portfolio/get/{studentId} — Get portfolio + artifacts for a student
     */
    public function getPortfolioGet(int $studentId): array
    {
        try {
            $portfolio = $this->dbQuery(
                "SELECT p.*,
                       (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                FROM portfolios p
                WHERE p.student_id = :sid AND p.status = 'active'
                ORDER BY p.created_date DESC
                LIMIT 1",
                [':sid' => $studentId]
            )->fetch(PDO::FETCH_ASSOC);

            $artifacts = [];
            if ($portfolio) {
                $artifacts = $this->dbQuery(
                    "SELECT pa.*, cc.name AS competency_name, cv.name AS value_name
                    FROM portfolio_artifacts pa
                    LEFT JOIN core_competencies cc ON cc.id = pa.competency_id
                    LEFT JOIN core_values cv ON cv.id = pa.value_id
                    WHERE pa.portfolio_id = :pid
                    ORDER BY pa.upload_date DESC",
                    [':pid' => $portfolio['id']]
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            return $this->successResponse([
                'portfolio' => $portfolio,
                'artifacts' => $artifacts,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::getPortfolioGet');
            return $this->successResponse(['portfolio' => null, 'artifacts' => []]);
        }
    }

    /**
     * POST /api/academic/portfolio/create — Create a portfolio for a student
     */
    public function postPortfolioCreate(array $data): array
    {
        try {
            $studentId = (int) ($data['student_id'] ?? 0);
            $title = trim($data['title'] ?? '');
            $academicYear = (int) ($data['academic_year'] ?? date('Y'));
            $type = $data['portfolio_type'] ?? 'digital';
            $description = trim($data['description'] ?? '');

            if (!$studentId || !$title) {
                return $this->errorResponse('student_id and title are required', 400);
            }

            $this->dbQuery(
                "INSERT INTO portfolios (student_id, academic_year, portfolio_type, title, description, created_date, last_updated, status, created_at, updated_at)
                 VALUES (:sid, :ay, :pt, :title, :desc, CURDATE(), CURDATE(), 'active', NOW(), NOW())",
                [':sid' => $studentId, ':ay' => $academicYear, ':pt' => $type, ':title' => $title, ':desc' => $description]
            );

            return $this->successResponse(['id' => $this->db->lastInsertId()], 'Portfolio created');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postPortfolioCreate');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * POST /api/academic/portfolio/artifact-add — Add artifact to portfolio
     */
    public function postPortfolioArtifactAdd(array $data, ?array $file, ?int $userId): array
    {
        try {
            $portfolioId = (int) ($data['portfolio_id'] ?? 0);
            $title = trim($data['artifact_title'] ?? '');
            $type = $data['artifact_type'] ?? 'other';
            $description = trim($data['description'] ?? '');
            $competencyId = !empty($data['competency_id']) ? (int) $data['competency_id'] : null;
            $valueId = !empty($data['value_id']) ? (int) $data['value_id'] : null;
            $reflection = trim($data['learner_reflection'] ?? '');
            $feedback = trim($data['teacher_feedback'] ?? '');
            $rating = isset($data['rating']) && $data['rating'] !== '' ? (float) $data['rating'] : null;

            if (!$portfolioId || !$title) {
                return $this->errorResponse('portfolio_id and artifact_title are required', 400);
            }

            $portfolio = $this->dbQuery(
                "SELECT id, student_id FROM portfolios WHERE id = :pid",
                [':pid' => $portfolioId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$portfolio) {
                return $this->errorResponse('Portfolio not found', 404);
            }

            $filePath = null;
            $mediaId = null;
            if (!empty($file) && is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $media = $this->contract('App\API\Modules\system\MediaManager', $this->db);
                    $mediaId = $media->upload(
                        $file,
                        'students/portfolios',
                        (int) $portfolio['student_id'],
                        null,
                        $userId,
                        $title,
                        'portfolio artifact',
                        $title
                    );
                    $filePath = $media->getFileUrl($mediaId);
                } catch (Throwable $uploadError) {
                    $this->logError($uploadError, 'AcademicManager::postPortfolioArtifactAdd');
                    return $this->errorResponse('File could not be uploaded. Check the file type and size.', 400);
                }
            }

            $this->dbQuery(
                "INSERT INTO portfolio_artifacts (portfolio_id, artifact_title, artifact_type, description, competency_id, value_id, learner_reflection, teacher_feedback, rating, file_path, media_id, upload_date, created_at)
                 VALUES (:pid, :title, :type, :desc, :cid, :vid, :ref, :fb, :rating, :fp, :mid, CURDATE(), NOW())",
                [':pid' => $portfolioId, ':title' => $title, ':type' => $type, ':desc' => $description,
                 ':cid' => $competencyId, ':vid' => $valueId, ':ref' => $reflection, ':fb' => $feedback,
                 ':rating' => $rating, ':fp' => $filePath, ':mid' => $mediaId]
            );

            $newId = $this->db->lastInsertId();
            $this->dbQuery("UPDATE portfolios SET last_updated = CURDATE(), updated_at = NOW() WHERE id = :pid", [':pid' => $portfolioId]);

            return $this->successResponse(['id' => $newId], 'Artifact added');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postPortfolioArtifactAdd');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * PUT /api/academic/portfolio/artifact-update — Update artifact metadata
     */
    public function putPortfolioArtifactUpdate(array $data): array
    {
        try {
            $artifactId = (int) ($data['id'] ?? 0);
            if (!$artifactId) return $this->errorResponse('artifact id is required', 400);

            $sets = [];
            $params = [':id' => $artifactId];
            foreach (['artifact_title', 'artifact_type', 'description', 'learner_reflection', 'teacher_feedback'] as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = :$f";
                    $params[":$f"] = $data[$f];
                }
            }
            foreach (['competency_id', 'value_id'] as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = :$f";
                    $params[":$f"] = !empty($data[$f]) ? (int) $data[$f] : null;
                }
            }
            if (array_key_exists('rating', $data)) {
                $sets[] = 'rating = :rating';
                $params[':rating'] = ($data['rating'] !== null && $data['rating'] !== '')
                    ? (float) $data['rating']
                    : null;
            }

            if (empty($sets)) return $this->errorResponse('No fields to update', 400);

            $sql = "UPDATE portfolio_artifacts SET " . implode(', ', $sets) . " WHERE id = :id";
            $this->dbQuery($sql, $params);

            return $this->successResponse(null, 'Artifact updated');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::putPortfolioArtifactUpdate');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * POST /api/academic/portfolio/artifact-file-replace — Replace an artifact's evidence file
     */
    public function postPortfolioArtifactFileReplace(int $artifactId, array $data, ?array $file, ?int $userId): array
    {
        try {
            $art = $this->dbQuery(
                "SELECT pa.id, pa.portfolio_id, p.student_id, pa.media_id, pa.artifact_title FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id WHERE pa.id = :id",
                [':id' => $artifactId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$art) return $this->errorResponse('Artifact not found', 404);

            if (empty($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return $this->errorResponse('A replacement file is required', 400);
            }

            $oldMediaId = !empty($art['media_id']) ? (int) $art['media_id'] : null;
            $preferredName = trim($data['artifact_title'] ?? '') !== ''
                ? $data['artifact_title']
                : ($art['artifact_title'] ?? 'artifact');

            try {
                $media = $this->contract('App\API\Modules\system\MediaManager', $this->db);
                $newMediaId = $media->upload(
                    $file,
                    'students/portfolios',
                    (int) $art['student_id'],
                    null,
                    $userId,
                    $preferredName,
                    'portfolio artifact',
                    $preferredName
                );
                $fileUrl = $media->getFileUrl($newMediaId);

                $this->dbQuery(
                    "UPDATE portfolio_artifacts SET file_path = :fp, media_id = :mid WHERE id = :id",
                    [':fp' => $fileUrl, ':mid' => $newMediaId, ':id' => $artifactId]
                );

                if ($oldMediaId) {
                    try {
                        $media->deleteMedia($oldMediaId);
                    } catch (Throwable $cleanupError) {
                        $this->logError($cleanupError, 'AcademicManager::postPortfolioArtifactFileReplace');
                    }
                }

                return $this->successResponse(['media_id' => $newMediaId, 'file_path' => $fileUrl], 'Artifact file replaced');
            } catch (Throwable $uploadError) {
                $this->logError($uploadError, 'AcademicManager::postPortfolioArtifactFileReplace');
                return $this->errorResponse('File could not be uploaded. Check the file type and size.', 400);
            }
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::postPortfolioArtifactFileReplace');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * DELETE /api/academic/portfolio/artifact-delete/{id} — Delete artifact
     */
    public function deletePortfolioArtifactDelete(int $artifactId): array
    {
        try {
            $art = $this->dbQuery(
                "SELECT portfolio_id, media_id FROM portfolio_artifacts WHERE id = :id",
                [':id' => $artifactId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$art) return $this->errorResponse('Artifact not found', 404);

            $mediaId = !empty($art['media_id']) ? (int) $art['media_id'] : null;

            $this->dbQuery("DELETE FROM portfolio_artifacts WHERE id = :id", [':id' => $artifactId]);
            $this->dbQuery("UPDATE portfolios SET last_updated = CURDATE(), updated_at = NOW() WHERE id = :pid", [':pid' => $art['portfolio_id']]);

            if ($mediaId) {
                try {
                    $this->contract('App\API\Modules\system\MediaManager', $this->db)->deleteMedia($mediaId);
                } catch (Throwable $deleteError) {
                    $this->logError($deleteError, 'AcademicManager::deletePortfolioArtifactDelete');
                }
            }

            return $this->successResponse(null, 'Artifact deleted');
        } catch (Exception $e) {
            $this->logError($e, 'AcademicManager::deletePortfolioArtifactDelete');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }
}
