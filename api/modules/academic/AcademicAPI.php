<?php
namespace App\API\Modules\academic;
require_once __DIR__ . '/../../includes/BaseAPI.php';
use App\API\Includes\BaseAPI;
use PDO;
use Exception;

class AcademicAPI extends BaseAPI {
    public function __construct() {
        parent::__construct('academic');
    }

    // List all learning areas with pagination and search
    public function list($params = []) {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE name LIKE ? OR code LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get total count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM learning_areas $where");
            if (!empty($bindings)) {
                $stmt->execute($bindings);
            } else {
                $stmt->execute();
            }
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "SELECT * FROM learning_areas $where ORDER BY $sort $order LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            
            if (!empty($bindings)) {
                $stmt->execute(array_merge($bindings, [$limit, $offset]));
            } else {
                $stmt->execute([$limit, $offset]);
            }
            
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed learning areas');
            
            return $this->response([
                'status' => 'success',
                'data' => [
                    'subjects' => $subjects,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single learning area
    public function get($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM learning_areas WHERE id = ?");
            $stmt->execute([$id]);
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subject) {
                return $this->response(['status' => 'error', 'message' => 'Learning area not found'], 404);
            }

            $this->logAction('read', $id, "Retrieved learning area details: {$subject['name']}");
            
            return $this->response(['status' => 'success', 'data' => $subject]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new learning area
    public function create($data) {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['name', 'code', 'level_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "INSERT INTO learning_areas (name, code, level_id, description) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['code'],
                $data['level_id'],
                $data['description'] ?? null
            ]);

            $subjectId = $this->db->lastInsertId();

            $this->commit();
            $this->logAction('create', $subjectId, "Created new learning area: {$data['name']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Learning area created successfully',
                'data' => ['id' => $subjectId]
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update learning area
    public function update($id, $data) {
        try {
            $this->beginTransaction();

            // Check if learning area exists
            $stmt = $this->db->prepare("SELECT id FROM learning_areas WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'Learning area not found'], 404);
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['name', 'code', 'level_id', 'description', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE learning_areas SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->commit();
            $this->logAction('update', $id, "Updated learning area details");

            return $this->response([
                'status' => 'success',
                'message' => 'Learning area updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete learning area (soft delete)
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE learning_areas SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Learning area not found'], 404);
            }

            $this->logAction('delete', $id, "Deactivated learning area");
            
            return $this->response([
                'status' => 'success',
                'message' => 'Learning area deactivated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints
    public function handleCustomGet($id, $action, $params) {
        switch ($action) {
            case 'teachers':
                return $this->getAssignedTeachers($id);
            case 'classes':
                return $this->getAssignedClasses($id);
            case 'assessments':
                return $this->getAssessments($id, $params);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Custom POST endpoints
    public function handleCustomPost($id, $action, $data) {
        switch ($action) {
            case 'assign-teacher':
                return $this->assignTeacher($id, $data);
            case 'create-assessment':
                return $this->createAssessment($id, $data);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Helper methods
    private function getAssignedTeachers($subjectId) {
        try {
            $sql = "
                SELECT 
                    s.*,
                    ts.assigned_date,
                    c.name as class_name,
                    cs.stream_name
                FROM teacher_subjects ts
                JOIN staff s ON ts.teacher_id = s.id
                JOIN classes c ON ts.class_id = c.id
                JOIN class_streams cs ON c.id = cs.class_id
                WHERE ts.subject_id = ?
                ORDER BY c.name, cs.stream_name
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$subjectId]);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $teachers
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getAssignedClasses($subjectId) {
        try {
            $sql = "
                SELECT 
                    c.*,
                    cs.stream_name,
                    COUNT(DISTINCT s.id) as student_count,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM classes c
                JOIN class_streams cs ON c.id = cs.class_id
                LEFT JOIN students s ON cs.id = s.stream_id
                LEFT JOIN teacher_subjects ts ON c.id = ts.class_id AND ts.subject_id = ?
                LEFT JOIN staff t ON ts.teacher_id = t.id
                WHERE c.status = 'active'
                GROUP BY c.id, cs.id
                ORDER BY c.name, cs.stream_name
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$subjectId]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $classes
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getAssessments($subjectId, $params) {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = "WHERE a.subject_id = ?";
            $bindings = [$subjectId];

            if (!empty($params['term_id'])) {
                $where .= " AND a.term_id = ?";
                $bindings[] = $params['term_id'];
            }

            if (!empty($params['type'])) {
                $where .= " AND a.type = ?";
                $bindings[] = $params['type'];
            }

            if (!empty($params['status'])) {
                $where .= " AND a.status = ?";
                $bindings[] = $params['status'];
            }

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM assessments a
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    a.*,
                    u.username as created_by_name,
                    la.name as subject_name,
                    at.name as term_name,
                    COUNT(ar.id) as total_submissions,
                    AVG(ar.marks_obtained) as average_marks
                FROM assessments a
                JOIN users u ON a.created_by = u.id
                JOIN learning_areas la ON a.subject_id = la.id
                JOIN academic_terms at ON a.term_id = at.id
                LEFT JOIN assessment_results ar ON a.id = ar.assessment_id
                $where
                GROUP BY a.id
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'assessments' => $assessments,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function assignTeacher($subjectId, $data) {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['teacher_id', 'class_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Check if assignment already exists
            $stmt = $this->db->prepare("
                SELECT id FROM teacher_subjects 
                WHERE teacher_id = ? AND subject_id = ? AND class_id = ?
            ");
            $stmt->execute([$data['teacher_id'], $subjectId, $data['class_id']]);
            
            if ($stmt->fetch()) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Teacher is already assigned to this subject and class'
                ], 400);
            }

            // Create assignment
            $sql = "
                INSERT INTO teacher_subjects (teacher_id, subject_id, class_id, assigned_date)
                VALUES (?, ?, ?, NOW())
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['teacher_id'],
                $subjectId,
                $data['class_id']
            ]);

            $this->commit();
            $this->logAction('create', null, "Assigned teacher to subject");

            return $this->response([
                'status' => 'success',
                'message' => 'Teacher assigned successfully'
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function createAssessment($subjectId, $data) {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['name', 'type', 'total_marks', 'weight', 'assessment_date', 'term_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate assessment type
            $validTypes = ['exam', 'quiz', 'assignment', 'project', 'cat'];
            if (!in_array($data['type'], $validTypes)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid assessment type'
                ], 400);
            }

            // Insert assessment
            $sql = "
                INSERT INTO assessments (
                    subject_id,
                    term_id,
                    name,
                    type,
                    total_marks,
                    weight,
                    assessment_date,
                    status,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $subjectId,
                $data['term_id'],
                $data['name'],
                $data['type'],
                $data['total_marks'],
                $data['weight'],
                $data['assessment_date'],
                $data['status'] ?? 'draft',
                $this->getCurrentUserId()
            ]);

            $assessmentId = $this->db->lastInsertId();

            $this->commit();
            $this->logAction('create', $assessmentId, "Created new assessment: {$data['name']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Assessment created successfully',
                'data' => ['id' => $assessmentId]
            ]);

        } catch (Exception $e) {
            $this->rollback();
            return $this->handleException($e);
        }
    }

    public function getLessonPlans($params = []) {
        try {
            $sql = "
                SELECT 
                    lp.*,
                    la.name as learning_area_name,
                    c.name as class_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name
                FROM lesson_plans lp
                JOIN learning_areas la ON lp.learning_area_id = la.id
                JOIN classes c ON lp.class_id = c.id
                JOIN staff s ON lp.teacher_id = s.id
                WHERE lp.status = 'active'
                ORDER BY lp.date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $plans]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createLessonPlan($data) {
        try {
            $required = ['learning_area_id', 'class_id', 'teacher_id', 'date', 'objectives'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->beginTransaction();

            $sql = "
                INSERT INTO lesson_plans (
                    learning_area_id,
                    class_id,
                    teacher_id,
                    date,
                    objectives,
                    resources,
                    activities,
                    assessment,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['learning_area_id'],
                $data['class_id'],
                $data['teacher_id'],
                $data['date'],
                $data['objectives'],
                json_encode($data['resources'] ?? []),
                json_encode($data['activities'] ?? []),
                $data['assessment'] ?? null,
                'active'
            ]);

            $planId = $this->db->lastInsertId();

            // Add resources if provided
            if (!empty($data['lesson_resources'])) {
                $sql = "
                    INSERT INTO lesson_resources (
                        lesson_plan_id,
                        name,
                        type,
                        url,
                        notes
                    ) VALUES (?, ?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['lesson_resources'] as $resource) {
                    $stmt->execute([
                        $planId,
                        $resource['name'],
                        $resource['type'],
                        $resource['url'] ?? null,
                        $resource['notes'] ?? null
                    ]);
                }
            }

            $this->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Lesson plan created successfully',
                'data' => ['id' => $planId]
            ], 201);
        } catch (Exception $e) {
            $this->rollback();
            return $this->handleException($e);
        }
    }

    public function getCurriculumUnits($params = []) {
        try {
            $sql = "
                SELECT 
                    cu.*,
                    la.name as learning_area_name,
                    COUNT(DISTINCT ut.id) as topic_count
                FROM curriculum_units cu
                JOIN learning_areas la ON cu.learning_area_id = la.id
                LEFT JOIN unit_topics ut ON cu.id = ut.unit_id
                WHERE cu.status = 'active'
                GROUP BY cu.id
                ORDER BY cu.sequence
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $units]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createCurriculumUnit($data) {
        try {
            $required = ['learning_area_id', 'name', 'sequence'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->beginTransaction();

            $sql = "
                INSERT INTO curriculum_units (
                    learning_area_id,
                    name,
                    description,
                    sequence,
                    duration,
                    objectives,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['learning_area_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['sequence'],
                $data['duration'] ?? null,
                json_encode($data['objectives'] ?? []),
                'active'
            ]);

            $unitId = $this->db->lastInsertId();

            // Add topics if provided
            if (!empty($data['topics'])) {
                $sql = "
                    INSERT INTO unit_topics (
                        unit_id,
                        name,
                        description,
                        sequence,
                        duration
                    ) VALUES (?, ?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['topics'] as $topic) {
                    $stmt->execute([
                        $unitId,
                        $topic['name'],
                        $topic['description'] ?? null,
                        $topic['sequence'],
                        $topic['duration'] ?? null
                    ]);
                }
            }

            $this->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Curriculum unit created successfully',
                'data' => ['id' => $unitId]
            ], 201);
        } catch (Exception $e) {
            $this->rollback();
            return $this->handleException($e);
        }
    }

    public function getAcademicTerms($params = []) {
        try {
            $sql = "
                SELECT 
                    at.*,
                    COUNT(DISTINCT ac.id) as calendar_events
                FROM academic_terms at
                LEFT JOIN academic_calendar ac ON at.id = ac.term_id
                GROUP BY at.id
                ORDER BY at.start_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $terms]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createAcademicTerm($data) {
        try {
            $required = ['name', 'start_date', 'end_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO academic_terms (
                    name,
                    start_date,
                    end_date,
                    description,
                    status
                ) VALUES (?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['start_date'],
                $data['end_date'],
                $data['description'] ?? null,
                'active'
            ]);

            $termId = $this->db->lastInsertId();

            return $this->response([
                'status' => 'success',
                'message' => 'Academic term created successfully',
                'data' => ['id' => $termId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSchemeOfWork($params = []) {
        try {
            $sql = "
                SELECT 
                    sw.*,
                    la.name as learning_area_name,
                    c.name as class_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name
                FROM schemes_of_work sw
                JOIN learning_areas la ON sw.learning_area_id = la.id
                JOIN classes c ON sw.class_id = c.id
                JOIN staff s ON sw.teacher_id = s.id
                WHERE sw.status = 'active'
                ORDER BY sw.term_id, sw.week_number
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $schemes]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createSchemeOfWork($data) {
        try {
            $required = ['learning_area_id', 'class_id', 'teacher_id', 'term_id', 'week_number'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO schemes_of_work (
                    learning_area_id,
                    class_id,
                    teacher_id,
                    term_id,
                    week_number,
                    topics,
                    objectives,
                    resources,
                    activities,
                    assessment,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['learning_area_id'],
                $data['class_id'],
                $data['teacher_id'],
                $data['term_id'],
                $data['week_number'],
                json_encode($data['topics'] ?? []),
                json_encode($data['objectives'] ?? []),
                json_encode($data['resources'] ?? []),
                json_encode($data['activities'] ?? []),
                json_encode($data['assessment'] ?? []),
                'active'
            ]);

            $schemeId = $this->db->lastInsertId();

            return $this->response([
                'status' => 'success',
                'message' => 'Scheme of work created successfully',
                'data' => ['id' => $schemeId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getLessonObservations($params = []) {
        try {
            $sql = "
                SELECT 
                    lo.*,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                    CONCAT(o.first_name, ' ', o.last_name) as observer_name,
                    la.name as learning_area_name,
                    c.name as class_name
                FROM lesson_observations lo
                JOIN staff t ON lo.teacher_id = t.id
                JOIN staff o ON lo.observer_id = o.id
                JOIN learning_areas la ON lo.learning_area_id = la.id
                JOIN classes c ON lo.class_id = c.id
                ORDER BY lo.observation_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $observations]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createLessonObservation($data) {
        try {
            $required = ['teacher_id', 'observer_id', 'learning_area_id', 'class_id', 'observation_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO lesson_observations (
                    teacher_id,
                    observer_id,
                    learning_area_id,
                    class_id,
                    observation_date,
                    strengths,
                    areas_for_improvement,
                    recommendations,
                    rating,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['teacher_id'],
                $data['observer_id'],
                $data['learning_area_id'],
                $data['class_id'],
                $data['observation_date'],
                json_encode($data['strengths'] ?? []),
                json_encode($data['areas_for_improvement'] ?? []),
                json_encode($data['recommendations'] ?? []),
                $data['rating'] ?? null,
                'completed'
            ]);

            $observationId = $this->db->lastInsertId();

            return $this->response([
                'status' => 'success',
                'message' => 'Lesson observation created successfully',
                'data' => ['id' => $observationId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
