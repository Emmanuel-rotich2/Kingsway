<?php
namespace App\API\Modules\Students;

require_once __DIR__ . '/../../includes/BaseAPI.php';
use App\API\Includes\BaseAPI;
use PDO;
use Exception;

class StudentsAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('students');
    }

    // List all students with pagination and search
    public function list($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm, $searchTerm];
            }

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    s.*,
                    c.name as class_name,
                    cs.stream_name
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                $where
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed students');

            return $this->response([
                'status' => 'success',
                'data' => [
                    'students' => $students,
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

    // Get single student
    public function get($id)
    {
        try {
            $sql = "
                SELECT 
                    s.*,
                    c.name as class_name,
                    cs.stream_name
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE s.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Optionally, add more details if available (e.g., attendance, fee summary)
            // $student['attendance'] = $this->getAttendanceSummary($id);

            $this->logAction('read', $id, "Retrieved student details: {$student['first_name']} {$student['last_name']}");

            return $this->response(['status' => 'success', 'data' => $student]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new student
    public function create($data)
    {
        try {
            $required = ['admission_no', 'first_name', 'last_name', 'stream_id', 'date_of_birth', 'gender', 'admission_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO students (
                    admission_no,
                    first_name,
                    last_name,
                    date_of_birth,
                    gender,
                    stream_id,
                    user_id,
                    admission_date,
                    status,
                    photo_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['admission_no'],
                $data['first_name'],
                $data['last_name'],
                $data['date_of_birth'],
                $data['gender'],
                $data['stream_id'],
                $data['user_id'] ?? null,
                $data['admission_date'],
                $data['status'] ?? 'active',
                $data['photo_url'] ?? null
            ]);

            $id = $this->db->lastInsertId();

            $this->logAction('create', $id, "Created new student: {$data['first_name']} {$data['last_name']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Student created successfully',
                'data' => ['id' => $id]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update student
    public function update($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM students WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            $updates = [];
            $params = [];
            $allowedFields = [
                'admission_no', 'first_name', 'last_name', 'date_of_birth', 'gender', 'stream_id', 'user_id', 'admission_date', 'status', 'photo_url'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->logAction('update', $id, "Updated student details");

            return $this->response([
                'status' => 'success',
                'message' => 'Student updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete student (soft delete)
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE students SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            $this->logAction('delete', $id, "Deactivated student");

            return $this->response([
                'status' => 'success',
                'message' => 'Student deactivated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints
    public function handleCustomGet($id, $action, $params)
    {
        switch ($action) {
            case 'attendance':
                return $this->getAttendanceRecord($id, $params);
            case 'performance':
                return $this->getAcademicPerformance($id, $params);
            case 'fees':
                return $this->getFeeStatement($id);
            case 'report':
                return $this->generateTermReport($id, $params);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Custom POST endpoints
    public function handleCustomPost($id, $action, $data)
    {
        switch ($action) {
            case 'attendance':
                return $this->markAttendance($id, $data);
            case 'transfer':
                return $this->transferStudent($id, $data);
            case 'discipline':
                return $this->recordDisciplineCase($id, $data);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Helper methods
    private function generateAdmissionNumber()
    {
        $year = date('Y');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) + 1 as next_number 
            FROM students 
            WHERE admission_no LIKE ?
        ");
        $stmt->execute(["{$year}%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNumber = str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
        return "{$year}{$nextNumber}";
    }

    private function addParent($studentId, $parentData)
    {
        // Check if parent exists
        $stmt = $this->db->prepare("SELECT id FROM parents WHERE phone_1 = ? OR (email IS NOT NULL AND email = ?) LIMIT 1");
        $stmt->execute([$parentData['phone_1'], $parentData['email'] ?? null]);
        $existingParent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingParent) {
            $parentId = $existingParent['id'];
        } else {
            // Create new parent
            $sql = "INSERT INTO parents (first_name, last_name, gender, phone_1, phone_2, email, occupation, address) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $parentData['first_name'],
                $parentData['last_name'],
                $parentData['gender'],
                $parentData['phone_1'],
                $parentData['phone_2'] ?? null,
                $parentData['email'] ?? null,
                $parentData['occupation'] ?? null,
                $parentData['address'] ?? null
            ]);
            $parentId = $this->db->lastInsertId();
        }

        // Create student-parent relationship
        $sql = "INSERT INTO student_parents (student_id, parent_id, relationship) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $parentId, $parentData['relationship']]);
    }

    private function getStudentParents($studentId)
    {
        $sql = "
            SELECT 
                p.*,
                sp.relationship
            FROM parents p
            JOIN student_parents sp ON p.id = sp.parent_id
            WHERE sp.student_id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeeSummary($studentId)
    {
        $sql = "
            SELECT 
                fc.name as fee_category,
                sf.term,
                sf.year,
                sf.amount as charged_amount,
                sf.balance,
                COALESCE(SUM(fp.amount), 0) as paid_amount
            FROM student_fees sf
            JOIN fee_categories fc ON sf.category_id = fc.id
            LEFT JOIN fee_payments fp ON sf.id = fp.student_fee_id
            WHERE sf.student_id = ?
            GROUP BY sf.id
            ORDER BY sf.year DESC, sf.term DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAttendanceSummary($studentId)
    {
        $sql = "
            SELECT 
                MONTH(date) as month,
                YEAR(date) as year,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days
            FROM student_attendance
            WHERE student_id = ?
            GROUP BY YEAR(date), MONTH(date)
            ORDER BY year DESC, month DESC
            LIMIT 12
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAttendanceRecord($id, $params)
    {
        try {
            $month = isset($params['month']) ? $params['month'] : date('m');
            $year = isset($params['year']) ? $params['year'] : date('Y');

            $sql = "
                SELECT * FROM student_attendance
                WHERE student_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
                ORDER BY date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $month, $year]);
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $attendance
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getAcademicPerformance($id, $params)
    {
        try {
            $term = isset($params['term']) ? $params['term'] : CURRENT_TERM;
            $year = isset($params['year']) ? $params['year'] : CURRENT_YEAR;

            $sql = "
                SELECT 
                    ar.*,
                    la.name as subject_name,
                    a.assessment_type,
                    a.max_marks
                FROM assessment_results ar
                JOIN assessments a ON ar.assessment_id = a.id
                JOIN learning_areas la ON a.learning_area_id = la.id
                WHERE ar.student_id = ? AND a.term = ? AND a.year = ?
                ORDER BY la.name, a.assessment_date
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $term, $year]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $results
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getFeeStatement($id)
    {
        try {
            $sql = "
                SELECT 
                    sf.*,
                    fc.name as fee_category,
                    fp.payment_date,
                    fp.amount as paid_amount,
                    fp.payment_method,
                    fp.reference_no
                FROM student_fees sf
                JOIN fee_categories fc ON sf.category_id = fc.id
                LEFT JOIN fee_payments fp ON sf.id = fp.student_fee_id
                WHERE sf.student_id = ?
                ORDER BY sf.year DESC, sf.term DESC, fp.payment_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $statement = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $statement
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function generateTermReport($id, $params)
    {
        try {
            $term = isset($params['term']) ? $params['term'] : CURRENT_TERM;
            $year = isset($params['year']) ? $params['year'] : CURRENT_YEAR;

            // Get student details
            $stmt = $this->db->prepare("SELECT * FROM view_student_details WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Get academic performance
            $sql = "
                SELECT 
                    la.name as subject_name,
                    a.assessment_type,
                    ar.marks_obtained,
                    a.max_marks,
                    t.name as teacher_name
                FROM assessment_results ar
                JOIN assessments a ON ar.assessment_id = a.id
                JOIN learning_areas la ON a.learning_area_id = la.id
                LEFT JOIN teacher_subjects ts ON la.id = ts.subject_id
                LEFT JOIN staff t ON ts.teacher_id = t.id
                WHERE ar.student_id = ? AND a.term = ? AND a.year = ?
                ORDER BY la.name, a.assessment_date
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $term, $year]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get attendance summary
            $sql = "
                SELECT 
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days
                FROM student_attendance
                WHERE student_id = ? AND YEAR(date) = ? AND MONTH(date) BETWEEN ? AND ?
            ";

            $termMonths = [
                1 => [9, 10, 11],
                2 => [1, 2, 3],
                3 => [5, 6, 7]
            ];

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $year,
                min($termMonths[$term]),
                max($termMonths[$term])
            ]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get class teacher comments
            $sql = "
                SELECT 
                    comments,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name
                FROM term_reports tr
                JOIN staff s ON tr.class_teacher_id = s.id
                WHERE tr.student_id = ? AND tr.term = ? AND tr.year = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $term, $year]);
            $comments = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'student' => $student,
                    'academic_results' => $results,
                    'attendance' => $attendance,
                    'comments' => $comments
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function markAttendance($id, $data)
    {
        try {
            // Validate required fields
            $required = ['date', 'status'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO student_attendance (student_id, date, status, remarks)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $data['date'],
                $data['status'],
                $data['remarks'] ?? null
            ]);

            $this->logAction('create', null, "Marked attendance for student ID: $id");

            return $this->response([
                'status' => 'success',
                'message' => 'Attendance marked successfully'
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function transferStudent($id, $data)
    {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['new_stream_id', 'transfer_date', 'reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Record transfer history
            $sql = "
                INSERT INTO student_transfers (
                    student_id, 
                    from_stream_id, 
                    to_stream_id, 
                    transfer_date, 
                    reason, 
                    approved_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            // Get current stream
            $stmt = $this->db->prepare("SELECT stream_id FROM students WHERE id = ?");
            $stmt->execute([$id]);
            $currentStream = $stmt->fetchColumn();

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $currentStream,
                $data['new_stream_id'],
                $data['transfer_date'],
                $data['reason'],
                $this->user_id
            ]);

            // Update student's stream
            $stmt = $this->db->prepare("UPDATE students SET stream_id = ? WHERE id = ?");
            $stmt->execute([$data['new_stream_id'], $id]);

            $this->commit();
            $this->logAction('update', $id, "Transferred student to new stream");

            return $this->response([
                'status' => 'success',
                'message' => 'Student transferred successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function recordDisciplineCase($id, $data)
    {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['incident_date', 'incident_type', 'description'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO discipline_cases (
                    student_id,
                    incident_date,
                    incident_type,
                    description,
                    action_taken,
                    reported_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $data['incident_date'],
                $data['incident_type'],
                $data['description'],
                $data['action_taken'] ?? null,
                $this->user_id
            ]);

            $caseId = $this->db->lastInsertId();

            $this->commit();
            $this->logAction('create', $caseId, "Recorded discipline case for student ID: $id");

            return $this->response([
                'status' => 'success',
                'message' => 'Discipline case recorded successfully',
                'data' => ['id' => $caseId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    //generate unique QR code for student - should also conatin fees balance and transportation information
    public function generateQRCode($id)
    {
        try {
            // Get student details
            $stmt = $this->db->prepare("SELECT admission_no FROM students WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student not found'
                ], 404);
            }

            // Generate QR code
            $qrCode = new \Endroid\QrCode\QrCode('https://kingsway.ac.ke/student/qr/' . $id);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);

            // Create writer
            $writer = new \Endroid\QrCode\Writer\PngWriter();

            // Generate QR code image
            $result = $writer->write($qrCode);

            // Save QR code
            $qrPath = 'images/qr_codes/' . $student['admission_no'] . '.png';
            $result->saveToFile($qrPath);

            // Update student record with QR code path
            $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ? WHERE id = ?");
            $stmt->execute([$qrPath, $id]);

            return $this->response([
                'status' => 'success',
                'message' => 'QR code generated successfully',
                'data' => [
                    'qr_path' => $qrPath
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getProfile($id) {
        try {
            $sql = "
                SELECT 
                    s.*,
                    c.name as class_name,
                    cs.stream_name,
                    CONCAT(p.first_name, ' ', p.last_name) as parent_name,
                    p.phone as parent_phone,
                    p.email as parent_email,
                    p.occupation as parent_occupation,
                    p.address as parent_address
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                LEFT JOIN parents p ON s.parent_id = p.id
                WHERE s.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Get additional profile details
            $profile['parents'] = $this->getStudentParents($id);
            $profile['academic_history'] = $this->getAcademicPerformance($id, []);
            $profile['attendance_history'] = $this->getAttendanceRecord($id, []);
            $profile['discipline_records'] = $this->getDisciplineRecords($id);

            return $this->response(['status' => 'success', 'data' => $profile]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getAttendance($id) {
        try {
            return $this->response([
                'status' => 'success',
                'data' => $this->getAttendanceRecord($id, [])
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getPerformance($id) {
        try {
            return $this->response([
                'status' => 'success',
                'data' => $this->getAcademicPerformance($id, [])
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getFees($id) {
        try {
            $summary = $this->getFeeSummary($id);
            $statement = $this->getFeeStatement($id);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'summary' => $summary,
                    'statement' => $statement
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function promote($id, $data) {
        try {
            $required = ['new_class_id', 'new_stream_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Verify student exists
            $stmt = $this->db->prepare("SELECT stream_id FROM students WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Update student's class and stream
            $sql = "UPDATE students SET stream_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['new_stream_id'], $id]);

            // Record promotion history
            $sql = "
                INSERT INTO student_promotions (
                    student_id,
                    from_class_id,
                    to_class_id,
                    from_stream_id,
                    to_stream_id,
                    promotion_date,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $data['current_class_id'],
                $data['new_class_id'],
                $data['current_stream_id'],
                $data['new_stream_id'],
                date('Y-m-d'),
                $data['remarks'] ?? null
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Student promoted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function transfer($id, $data) {
        try {
            return $this->transferStudent($id, $data);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getDisciplineRecords($id) {
        $sql = "
            SELECT * FROM discipline_records
            WHERE student_id = ?
            ORDER BY incident_date DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bulkCreate($data) {
        try {
            if (empty($data['file'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No file uploaded'
                ], 400);
            }

            $bulkHelper = new \App\API\Includes\BulkOperationsHelper($this->db);
            $result = $bulkHelper->processUploadedFile($data['file']);

            if ($result['status'] === 'error') {
                return $this->response($result, 400);
            }

            // Process each student
            $processedData = [];
            foreach ($result['data'] as $row) {
                // Generate admission number if not provided
                if (empty($row['admission_number'])) {
                    $row['admission_number'] = $this->generateAdmissionNumber();
                }
                
                // Add required fields
                $row['status'] = 'active';
                $row['created_at'] = date('Y-m-d H:i:s');
                
                $processedData[] = $row;
            }

            // Perform bulk insert
            $uniqueColumns = ['admission_number', 'email'];
            $insertResult = $bulkHelper->bulkInsert('students', $processedData, $uniqueColumns);

            // Generate QR codes for new students
            foreach ($processedData as $student) {
                $this->generateQRCode($student['id']);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Bulk student creation completed',
                'data' => $insertResult
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function bulkUpdate($data) {
        try {
            if (empty($data['file'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No file uploaded'
                ], 400);
            }

            $bulkHelper = new \App\API\Includes\BulkOperationsHelper($this->db);
            $result = $bulkHelper->processUploadedFile($data['file']);

            if ($result['status'] === 'error') {
                return $this->response($result, 400);
            }

            // Update students
            $updateResult = $bulkHelper->bulkUpdate(
                'students',
                $result['data'],
                'admission_number'
            );

            return $this->response([
                'status' => 'success',
                'message' => 'Bulk student update completed',
                'data' => $updateResult
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getQRInfo($id) {
        try {
            $sql = "
                SELECT 
                    s.*,
                    c.name as class_name,
                    cs.stream_name,
                    COALESCE(fs.amount, 0) as fees_balance
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                LEFT JOIN fee_structures fs ON s.id = fs.student_id
                WHERE s.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student not found'
                ], 404);
            }

            // Remove sensitive data
            unset($student['password']);
            unset($student['created_at']);
            unset($student['updated_at']);

            return $this->response([
                'status' => 'success',
                'data' => $student
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
