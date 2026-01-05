<?php
namespace App\API\Modules\students;

use App\Config;
use App\API\Includes\BaseAPI;
use App\API\Modules\admission\StudentAdmissionWorkflow;
use App\API\Modules\academic\AcademicYearManager;
use App\API\Modules\students\PromotionManager;
use PDO;
use Exception;

use App\API\Modules\students\StudentIDCardGenerator;

class StudentsAPI extends BaseAPI
{
    private $admissionWorkflow;
    private $idCardGenerator;
    private $yearManager;
    private $promotionManager;

    public function __construct()
    {
        parent::__construct('students');
        $this->admissionWorkflow = new StudentAdmissionWorkflow();
        $this->idCardGenerator = new StudentIDCardGenerator();
        $this->yearManager = new AcademicYearManager($this->db);
        $this->promotionManager = new PromotionManager($this->db, $this->yearManager);
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
            $required = ['admission_no', 'first_name', 'last_name', 'stream_id', 'date_of_birth', 'gender', 'admission_date', 'parent_info'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // parent_info must include at least first_name and phone_1 or email
            if (empty($data['parent_info']['first_name']) || (empty($data['parent_info']['phone_1']) && empty($data['parent_info']['email']))) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Parent information must include parent first name and either phone_1 or email'
                ], 400);
            }

            // Validate gender enum
            $validGenders = ['male', 'female', 'other'];
            if (!in_array($data['gender'], $validGenders)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            // Validate status enum if provided
            if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'graduated', 'transferred', 'suspended'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid status value. Must be: active, inactive, graduated, transferred, or suspended'
                ], 400);
            }

            // BUSINESS RULE: Student must be either sponsored OR have initial payment
            // If not sponsored and no initial_payment_amount provided, reject
            $isSponsored = !empty($data['is_sponsored']) && $data['is_sponsored'] == 1;
            $initialPayment = $data['initial_payment_amount'] ?? 0;
            $skipPaymentCheck = $data['skip_payment_check'] ?? false; // Admin override flag

            if (!$isSponsored && $initialPayment <= 0 && !$skipPaymentCheck) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student cannot be enrolled without payment. Either mark as sponsored or provide initial payment amount.',
                    'hint' => 'Use is_sponsored=1 for sponsored students, or provide initial_payment_amount with payment details'
                ], 400);
            }

            // Start transaction so parent linking and student insert are atomic
            $this->db->beginTransaction();

            $sql = "
                INSERT INTO students (
                    admission_no,
                    first_name,
                    middle_name,
                    last_name,
                    date_of_birth,
                    gender,
                    stream_id,
                    student_type_id,
                    admission_date,
                    assessment_number,
                    assessment_status,
                    status,
                    photo_url,
                    qr_code_path,
                    is_sponsored,
                    sponsor_name,
                    sponsor_type,
                    sponsor_waiver_percentage,
                    blood_group
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['admission_no'],
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
                $data['date_of_birth'],
                $data['gender'],
                $data['stream_id'],
                $data['student_type_id'] ?? null,
                $data['admission_date'],
                $data['assessment_number'] ?? null,
                $data['assessment_status'] ?? 'not_assigned',
                $data['status'] ?? 'active',
                $data['photo_url'] ?? null,
                $data['qr_code_path'] ?? null,
                $data['is_sponsored'] ?? 0,
                $data['sponsor_name'] ?? null,
                $data['sponsor_type'] ?? null,
                $data['sponsor_waiver_percentage'] ?? null,
                $data['blood_group'] ?? null
            ]);

            $studentId = $this->db->lastInsertId();

            // Link parent as part of student creation
            try {
                $this->addParent($studentId, $data['parent_info']);
            } catch (Exception $e) {
                // If parent creation/link fails, rollback student and return error
                $this->db->rollBack();
                return $this->handleException($e);
            }

            // Get class_id from stream_id for enrollment
            $stmt = $this->db->prepare("SELECT class_id FROM class_streams WHERE id = ?");
            $stmt->execute([$data['stream_id']]);
            $classId = $stmt->fetchColumn();

            // Create class enrollment and fee obligations using stored procedure
            $enrollmentId = null;
            $feeObligationsCreated = 0;

            if ($classId && $data['stream_id']) {
                try {
                    $stmt = $this->db->prepare("CALL sp_complete_student_enrollment(?, ?, ?, NULL, @enr_id, @fees)");
                    $stmt->execute([$studentId, $classId, $data['stream_id']]);

                    $result = $this->db->query("SELECT @enr_id as enrollment_id, @fees as fee_obligations")->fetch(\PDO::FETCH_ASSOC);
                    $enrollmentId = $result['enrollment_id'];
                    $feeObligationsCreated = $result['fee_obligations'];
                } catch (Exception $e) {
                    // Log but don't fail - enrollment and fees can be created later
                    error_log("Warning: Could not create enrollment/fees for student $studentId: " . $e->getMessage());
                }
            }

            // Record initial payment if provided
            if ($initialPayment > 0 && !empty($data['payment_method'])) {
                try {
                    $this->recordInitialPayment($studentId, [
                        'amount' => $initialPayment,
                        'method' => $data['payment_method'],
                        'reference' => $data['payment_reference'] ?? null,
                        'receipt_no' => $data['receipt_no'] ?? null
                    ]);
                } catch (Exception $e) {
                    error_log("Warning: Could not record initial payment for student $studentId: " . $e->getMessage());
                }
            }

            $this->logAction('create', $studentId, "Created new student: {$data['first_name']} {$data['last_name']}");

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Student created successfully',
                'data' => [
                    'id' => $studentId,
                    'enrollment_id' => $enrollmentId,
                    'fee_obligations_created' => $feeObligationsCreated
                ]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Record initial payment for a newly enrolled student
     */
    private function recordInitialPayment($studentId, $paymentData)
    {
        // Get current academic year and term
        $stmt = $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1");
        $academicYearId = $stmt->fetchColumn();

        $stmt = $this->db->query("SELECT id FROM terms WHERE is_current = 1 LIMIT 1");
        $termId = $stmt->fetchColumn();

        // Record in payment_transactions
        $sql = "INSERT INTO payment_transactions (
            student_id, academic_year_id, term_id, amount, 
            payment_method, reference_no, receipt_no, 
            payment_date, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'confirmed', NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $studentId,
            $academicYearId,
            $termId,
            $paymentData['amount'],
            $paymentData['method'],
            $paymentData['reference'],
            $paymentData['receipt_no']
        ]);

        $paymentId = $this->db->lastInsertId();

        // Update fee obligations with this payment (distribute across obligations)
        $remainingAmount = $paymentData['amount'];

        $stmt = $this->db->prepare("
            SELECT id, balance FROM student_fee_obligations 
            WHERE student_id = ? AND balance > 0 
            ORDER BY due_date ASC
        ");
        $stmt->execute([$studentId]);
        $obligations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($obligations as $obligation) {
            if ($remainingAmount <= 0)
                break;

            $paymentForThis = min($remainingAmount, $obligation['balance']);

            $stmt = $this->db->prepare("
                UPDATE student_fee_obligations 
                SET amount_paid = amount_paid + ?,
                    balance = balance - ?,
                    payment_status = CASE 
                        WHEN balance - ? <= 0 THEN 'paid'
                        ELSE 'partial'
                    END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$paymentForThis, $paymentForThis, $paymentForThis, $obligation['id']]);

            $remainingAmount -= $paymentForThis;
        }

        return $paymentId;
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

            // Validate gender enum if provided
            if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female', 'other'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            // Validate status enum if provided
            if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'graduated', 'transferred', 'suspended'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid status value. Must be: active, inactive, graduated, transferred, or suspended'
                ], 400);
            }

            $updates = [];
            $params = [];
            $allowedFields = [
                'admission_no',
                'first_name',
                'middle_name',
                'last_name',
                'date_of_birth',
                'gender',
                'stream_id',
                'student_type_id',
                'admission_date',
                'status',
                'photo_url',
                'assessment_number',
                'assessment_status',
                'is_sponsored',
                'sponsor_name',
                'sponsor_type',
                'sponsor_waiver_percentage',
                'blood_group'
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
        // Validate gender if provided
        if (isset($parentData['gender']) && !in_array($parentData['gender'], ['male', 'female', 'other'])) {
            throw new Exception('Invalid gender value. Must be: male, female, or other');
        }

        // Robust parent lookup: check by phone_1, phone_2, email, or name+phone
        $stmt = $this->db->prepare("SELECT id, phone_1, phone_2, email, first_name, last_name FROM parents WHERE phone_1 = ? OR phone_2 = ? OR (email IS NOT NULL AND email = ?) LIMIT 1");
        $stmt->execute([$parentData['phone_1'] ?? null, $parentData['phone_2'] ?? null, $parentData['email'] ?? null]);
        $existingParent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingParent) {
            $parentId = $existingParent['id'];
            // Update missing info if provided
            $updates = [];
            $params = [];
            if (!empty($parentData['first_name']) && $parentData['first_name'] !== $existingParent['first_name']) {
                $updates[] = 'first_name = ?';
                $params[] = $parentData['first_name'];
            }
            if (!empty($parentData['last_name']) && $parentData['last_name'] !== $existingParent['last_name']) {
                $updates[] = 'last_name = ?';
                $params[] = $parentData['last_name'];
            }
            if (!empty($parentData['phone_1']) && $parentData['phone_1'] !== $existingParent['phone_1']) {
                $updates[] = 'phone_1 = ?';
                $params[] = $parentData['phone_1'];
            }
            if (!empty($parentData['phone_2']) && $parentData['phone_2'] !== $existingParent['phone_2']) {
                $updates[] = 'phone_2 = ?';
                $params[] = $parentData['phone_2'];
            }
            if (!empty($parentData['email']) && $parentData['email'] !== $existingParent['email']) {
                $updates[] = 'email = ?';
                $params[] = $parentData['email'];
            }
            if (!empty($updates)) {
                $params[] = $existingParent['id'];
                $sql = 'UPDATE parents SET ' . implode(', ', $updates) . ' WHERE id = ?';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
        } else {
            // Create new parent
            $sql = "INSERT INTO parents (first_name, last_name, gender, phone_1, phone_2, email, occupation, address, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $parentData['first_name'],
                $parentData['last_name'],
                $parentData['gender'] ?? 'other',
                $parentData['phone_1'] ?? null,
                $parentData['phone_2'] ?? null,
                $parentData['email'] ?? null,
                $parentData['occupation'] ?? null,
                $parentData['address'] ?? null
            ]);
            $parentId = $this->db->lastInsertId();
        }

        // Create student-parent relationship if not exists
        $sql = "SELECT id FROM student_parents WHERE student_id = ? AND parent_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $parentId]);
        if (!$stmt->fetch()) {
            $sql = "INSERT INTO student_parents (student_id, parent_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$studentId, $parentId]);
        }
    }

    private function getStudentParents($studentId)
    {
        $sql = "
            SELECT 
                p.*
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
            $this->db->beginTransaction();

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

            $this->db->commit();
            $this->logAction('update', $id, "Transferred student to new stream");

            return $this->response([
                'status' => 'success',
                'message' => 'Student transferred successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function recordDisciplineCase($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['incident_date', 'description', 'severity'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate severity enum
            $validSeverity = ['low', 'medium', 'high'];
            if (!in_array($data['severity'], $validSeverity)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid severity value. Must be: low, medium, or high'
                ], 400);
            }

            $sql = "
                INSERT INTO student_discipline (
                    student_id,
                    incident_date,
                    description,
                    severity,
                    action_taken,
                    status
                ) VALUES (?, ?, ?, ?, ?, 'pending')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $data['incident_date'],
                $data['description'],
                $data['severity'],
                $data['action_taken'] ?? null
            ]);

            $caseId = $this->db->lastInsertId();

            $this->db->commit();
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

            // Ensure QR code library is available
            if (!class_exists('\Endroid\QrCode\QrCode') || !class_exists('\Endroid\QrCode\Writer\PngWriter')) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'QR generation library not available. Please install "endroid/qr-code" via Composer (composer require endroid/qr-code).'
                ], 500);
            }

            // Instantiate classes dynamically to avoid static analyzer errors if library is missing
            $qrClass = '\Endroid\QrCode\QrCode';
            $writerClass = '\Endroid\QrCode\Writer\PngWriter';

            // Generate QR code
            $qrCode = new $qrClass('https://kingsway.ac.ke/student/qr/' . $id);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);

            // Create writer
            $writer = new $writerClass();

            // Generate QR code image
            $result = $writer->write($qrCode);

            // Save QR code to images folder
            $qrPath = 'images/qr_codes/' . $student['admission_no'] . '.png';
            $dir = dirname($qrPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $result->saveToFile($qrPath);

            // Import generated QR into MediaManager so it's managed under uploads/students/{id}
            try {
                $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
                $projectRoot = realpath(__DIR__ . '/../../..');
                if ($projectRoot) {
                    $fullSource = $projectRoot . DIRECTORY_SEPARATOR . $qrPath;
                    if (file_exists($fullSource)) {
                        $mediaId = $mediaManager->import($fullSource, 'students', $id, basename($fullSource), null, 'student qr code');
                        $preview = $mediaManager->getPreviewUrl($mediaId);
                        // Update student record with managed preview URL if available
                        if ($preview) {
                            $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ? WHERE id = ?");
                            $stmt->execute([$preview, $id]);
                        } else {
                            $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ? WHERE id = ?");
                            $stmt->execute(['/' . $qrPath, $id]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Fallback: store local path
                $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ? WHERE id = ?");
                $stmt->execute(['/' . $qrPath, $id]);
            }

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
            SELECT * FROM student_discipline
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

    // ==================== WORKFLOW METHODS ====================
    // NOTE: Admission workflow methods delegate to StudentAdmissionWorkflow module
    // to avoid duplication. We adapt the response format to match StudentsAPI conventions.

    /**
     * Start admission workflow - delegates to StudentAdmissionWorkflow
     * @param array $data Application data
     * @return array Response in StudentsAPI format
     */
    public function startAdmissionWorkflow($data)
    {
        // Delegate to the dedicated admission module
        $result = $this->admissionWorkflow->submitApplication($data);

        // Adapt response format to match StudentsAPI conventions
        if ($result['success']) {
            return $this->response([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result['data']
            ], 201);
        } else {
            return $this->response([
                'status' => 'error',
                'message' => $result['message']
            ], 400);
        }
    }

    /**
     * Verify admission documents - delegates to StudentAdmissionWorkflow
     * @param array $data Document verification data
     * @return array Response in StudentsAPI format
     */
    public function verifyAdmissionDocuments($data)
    {
        try {
            $required = ['application_id', 'documents'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $verifiedCount = 0;
            $errors = [];

            // Verify each document using the admission workflow
            foreach ($data['documents'] as $docId => $verificationStatus) {
                $notes = $data['notes'][$docId] ?? '';
                $result = $this->admissionWorkflow->verifyDocument($docId, $verificationStatus, $notes);

                if ($result['success']) {
                    $verifiedCount++;
                } else {
                    $errors[] = "Document $docId: " . $result['message'];
                }
            }

            if (!empty($errors)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Some documents failed verification',
                    'errors' => $errors
                ], 400);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Documents verification updated successfully',
                'data' => ['verified_count' => $verifiedCount]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Conduct admission interview - delegates to StudentAdmissionWorkflow
     * @param array $data Interview data including application_id and interview_notes
     * @return array Response in StudentsAPI format
     */
    public function conductAdmissionInterview($data)
    {
        try {
            $required = ['application_id', 'interview_date', 'interview_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Delegate to admission workflow for scheduling
            $result = $this->admissionWorkflow->scheduleInterview(
                $data['application_id'],
                $data['interview_date'],
                $data['interview_time'],
                $data['venue'] ?? 'Main Office'
            );

            // Adapt response
            if ($result['success']) {
                return $this->response([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data']
                ]);
            } else {
                return $this->response([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Approve admission - delegates to StudentAdmissionWorkflow
     * @param array $data Approval data
     * @return array Response in StudentsAPI format
     */
    public function approveAdmission($data)
    {
        try {
            $required = ['application_id', 'assigned_class_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Delegate to admission workflow for placement offer generation
            $result = $this->admissionWorkflow->generatePlacementOffer(
                $data['application_id'],
                $data['assigned_class_id']
            );

            // Adapt response
            if ($result['success']) {
                return $this->response([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data']
                ]);
            } else {
                return $this->response([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Complete registration - delegates to StudentAdmissionWorkflow
     * @param array $data Registration data
     * @return array Response in StudentsAPI format
     */
    public function completeRegistration($data)
    {
        try {
            $required = ['application_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Delegate to admission workflow for enrollment completion
            $result = $this->admissionWorkflow->completeEnrollment($data['application_id']);

            // Adapt response
            if ($result['success']) {
                return $this->response([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data']
                ], 201);
            } else {
                return $this->response([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get admission workflow status - provides enhanced status with parent info
     * @param int $applicationId Application ID
     * @return array Response with workflow status
     */
    public function getAdmissionWorkflowStatus($applicationId)
    {
        try {
            // Get basic workflow status from admission module
            // But we enhance it with additional student-specific info
            $sql = "SELECT aa.*, 
                           p.first_name as parent_first_name, 
                           p.last_name as parent_last_name,
                           p.phone_1, p.email,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as total_documents,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_documents
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    WHERE aa.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->response(['status' => 'error', 'message' => 'Application not found'], 404);
            }

            // Get document details
            $stmt = $this->db->prepare("SELECT * FROM admission_documents WHERE application_id = ? ORDER BY is_mandatory DESC, document_type");
            $stmt->execute([$applicationId]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $application['documents'] = $documents;

            return $this->response([
                'status' => 'success',
                'data' => $application
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Transfer Workflow Methods
    public function startTransferWorkflow($data)
    {
        try {
            $required = ['student_id', 'transfer_to_school', 'transfer_reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Get student current class/stream info
            $stmt = $this->db->prepare("SELECT stream_id FROM students WHERE id = ?");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Get current class and stream
            $stmt = $this->db->prepare("SELECT class_id FROM class_streams WHERE id = ?");
            $stmt->execute([$student['stream_id']]);
            $streamInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            // Create transfer record in student_promotions
            $sql = "INSERT INTO student_promotions 
                    (student_id, current_class_id, current_stream_id, promotion_status, transfer_to_school, rejection_reason) 
                    VALUES (?, ?, ?, 'pending_approval', ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['student_id'],
                $streamInfo['class_id'] ?? null,
                $student['stream_id'],
                $data['transfer_to_school'],
                $data['transfer_reason']
            ]);
            $transferId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $transferId, "Started transfer request for student ID: {$data['student_id']} to {$data['transfer_to_school']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Transfer request started successfully',
                'data' => ['transfer_id' => $transferId]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function verifyTransferEligibility($data)
    {
        try {
            $required = ['transfer_id', 'student_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Check for outstanding fees or other blockers
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as pending_fees 
                FROM student_fees 
                WHERE student_id = ? AND balance > 0
            ");
            $stmt->execute([$data['student_id']]);
            $feeCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            $eligible = ($feeCheck['pending_fees'] == 0);
            $notes = $eligible ? 'No pending fees - eligible for transfer' : 'Has outstanding fees';

            return $this->response([
                'status' => 'success',
                'data' => [
                    'eligible' => $eligible,
                    'notes' => $notes,
                    'pending_fees' => $feeCheck['pending_fees']
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function approveTransfer($data)
    {
        try {
            $required = ['transfer_id', 'decision'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Validate decision
            if ($data['decision'] === 'approved') {
                $newStatus = 'approved';
            } elseif ($data['decision'] === 'rejected') {
                $newStatus = 'rejected';
            } else {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid decision. Must be: approved or rejected'
                ], 400);
            }

            $currentUserId = $this->getCurrentUserId();
            $sql = "UPDATE student_promotions 
                    SET promotion_status = ?, 
                        approved_by = ?, 
                        approval_date = NOW(), 
                        approval_notes = ? 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $newStatus,
                $currentUserId,
                $data['notes'] ?? null,
                $data['transfer_id']
            ]);

            $this->db->commit();
            $this->logAction('update', $data['transfer_id'], "Transfer decision: {$data['decision']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Transfer decision recorded successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function executeTransfer($data)
    {
        try {
            $required = ['transfer_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Get transfer data
            $stmt = $this->db->prepare("SELECT * FROM student_promotions WHERE id = ?");
            $stmt->execute([$data['transfer_id']]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return $this->response(['status' => 'error', 'message' => 'Transfer not found'], 404);
            }

            if ($transfer['promotion_status'] !== 'approved') {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Cannot execute transfer - not approved'
                ], 400);
            }

            // Update promotion status to transferred
            $sql = "UPDATE student_promotions SET promotion_status = 'transferred' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['transfer_id']]);

            // Update student status to transferred
            $sql = "UPDATE students SET status = 'transferred' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$transfer['student_id']]);

            $this->db->commit();
            $this->logAction('update', $data['transfer_id'], "Transfer executed - student moved to {$transfer['transfer_to_school']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Transfer executed successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function getTransferWorkflowStatus($instanceId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT sp.*, s.first_name, s.last_name, s.admission_no, s.status as student_status
                FROM student_promotions sp
                JOIN students s ON sp.student_id = s.id
                WHERE sp.id = ? AND sp.promotion_status IN ('pending_approval', 'approved', 'rejected', 'transferred')
            ");
            $stmt->execute([$instanceId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return $this->response(['status' => 'error', 'message' => 'Transfer not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'data' => $transfer
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== ADDITIONAL PUBLIC METHODS ====================

    public function getStudentParentsInfo($id)
    {
        try {
            $parents = $this->getStudentParents($id);
            return $this->response([
                'status' => 'success',
                'data' => $parents
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getMedicalRecords($id)
    {
        try {
            // Get student's admission application
            $stmt = $this->db->prepare("
                SELECT aa.id as application_id
                FROM students s
                LEFT JOIN admission_applications aa ON aa.applicant_name = CONCAT(s.first_name, ' ', s.last_name) 
                    AND aa.status = 'enrolled'
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->response([
                    'status' => 'success',
                    'message' => 'No medical records found',
                    'data' => []
                ]);
            }

            // Get medical records from admission documents
            $stmt = $this->db->prepare("
                SELECT * FROM admission_documents 
                WHERE application_id = ? AND document_type = 'medical_records'
                ORDER BY created_at DESC
            ");
            $stmt->execute([$application['application_id']]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $records
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getDisciplineRecordsInfo($id)
    {
        try {
            $records = $this->getDisciplineRecords($id);
            return $this->response([
                'status' => 'success',
                'data' => $records
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getTransferHistory($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM student_promotions 
                WHERE student_id = ? AND promotion_status = 'transferred'
                ORDER BY approval_date DESC
            ");
            $stmt->execute([$id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getPromotionHistory($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM student_promotions 
                WHERE student_id = ? AND promotion_status IN ('approved', 'graduated', 'retained')
                ORDER BY approval_date DESC
            ");
            $stmt->execute([$id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStudentDocuments($id)
    {
        try {
            // Get student's admission application
            $stmt = $this->db->prepare("
                SELECT aa.id as application_id, aa.application_no, aa.status
                FROM students s
                LEFT JOIN admission_applications aa ON aa.applicant_name = CONCAT(s.first_name, ' ', s.last_name) 
                    AND aa.status = 'enrolled'
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->response([
                    'status' => 'success',
                    'message' => 'No admission documents found for this student',
                    'data' => []
                ]);
            }

            // Get admission documents
            $stmt = $this->db->prepare("SELECT * FROM admission_documents WHERE application_id = ? ORDER BY created_at DESC");
            $stmt->execute([$application['application_id']]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'application_info' => $application,
                    'documents' => $documents
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStudentsByClass($classId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, cs.stream_name 
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                WHERE cs.class_id = ? AND s.status = 'active'
                ORDER BY s.last_name, s.first_name
            ");
            $stmt->execute([$classId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $students
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStudentsByStream($streamId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM students 
                WHERE stream_id = ? AND status = 'active'
                ORDER BY last_name, first_name
            ");
            $stmt->execute([$streamId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $students
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStudentStatistics($params = [])
    {
        try {
            // Total students
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
            $total = $stmt->fetchColumn();

            // By gender
            $stmt = $this->db->query("SELECT gender, COUNT(*) as count FROM students WHERE status = 'active' GROUP BY gender");
            $byGender = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // By class
            $stmt = $this->db->query("
                SELECT c.name as class_name, COUNT(s.id) as count
                FROM classes c
                LEFT JOIN class_streams cs ON c.id = cs.class_id
                LEFT JOIN students s ON cs.id = s.stream_id AND s.status = 'active'
                GROUP BY c.id
                ORDER BY c.name
            ");
            $byClass = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'total' => $total,
                    'by_gender' => $byGender,
                    'by_class' => $byClass
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function bulkPromoteStudents($data)
    {
        try {
            $required = ['student_ids', 'to_class_id', 'to_stream_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            $promoted = 0;
            foreach ($data['student_ids'] as $studentId) {
                $result = $this->promote($studentId, [
                    'new_class_id' => $data['to_class_id'],
                    'new_stream_id' => $data['to_stream_id'],
                    'current_class_id' => $data['from_class_id'] ?? null,
                    'current_stream_id' => $data['from_stream_id'] ?? null
                ]);

                if ($result['status'] === 'success') {
                    $promoted++;
                }
            }

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => "Promoted $promoted students successfully",
                'data' => ['promoted_count' => $promoted]
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function addMedicalRecord($data)
    {
        try {
            $required = ['application_id', 'file'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Handle file upload
            $filePath = "documents/admissions/{$data['application_id']}/medical/" . $data['file']['name'];

            $sql = "INSERT INTO admission_documents (application_id, document_type, document_path, is_mandatory, verification_status, notes) 
                    VALUES (?, 'medical_records', ?, ?, 'pending', ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['application_id'],
                $filePath,
                $data['is_mandatory'] ?? false,
                $data['notes'] ?? null
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Medical record document added successfully',
                'data' => ['id' => $this->db->lastInsertId()]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateMedicalRecord($id, $data)
    {
        try {
            $updates = [];
            $params = [];
            $allowedFields = ['verification_status', 'notes'];

            // Validate verification_status if provided
            if (isset($data['verification_status']) && !in_array($data['verification_status'], ['pending', 'verified', 'rejected'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid verification_status. Must be: pending, verified, or rejected'
                ], 400);
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (isset($data['verification_status']) && $data['verification_status'] === 'verified') {
                $updates[] = "verified_by = ?";
                $updates[] = "verified_at = NOW()";
                $params[] = $this->getCurrentUserId();
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE admission_documents SET " . implode(', ', $updates) . " WHERE id = ? AND document_type = 'medical_records'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Medical record document updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateDisciplineCase($id, $data)
    {
        try {
            $updates = [];
            $params = [];
            $allowedFields = ['description', 'severity', 'action_taken', 'status'];

            // Validate severity if provided
            if (isset($data['severity']) && !in_array($data['severity'], ['low', 'medium', 'high'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid severity value. Must be: low, medium, or high'
                ], 400);
            }

            // Validate status if provided
            if (isset($data['status']) && !in_array($data['status'], ['pending', 'resolved', 'escalated'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid status value. Must be: pending, resolved, or escalated'
                ], 400);
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE student_discipline SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Discipline case updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function resolveDisciplineCase($id, $data)
    {
        try {
            $currentUserId = $this->getCurrentUserId();

            $sql = "UPDATE student_discipline 
                    SET status = 'resolved', 
                        action_taken = ?, 
                        resolved_by = ?, 
                        resolution_date = NOW() 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['action_taken'] ?? 'Resolved',
                $currentUserId,
                $id
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Discipline case resolved successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function uploadStudentDocument($data)
    {
        try {
            $required = ['application_id', 'document_type', 'file'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate document_type enum
            $validDocTypes = [
                'birth_certificate',
                'immunization_card',
                'progress_report',
                'medical_records',
                'passport_photo',
                'nemis_upi',
                'leaving_certificate',
                'transfer_letter',
                'behavior_report',
                'other'
            ];
            if (!in_array($data['document_type'], $validDocTypes)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid document type'
                ], 400);
            }

            // Handle file upload logic here
            $filePath = "documents/admissions/{$data['application_id']}/" . $data['file']['name'];

            $sql = "INSERT INTO admission_documents (application_id, document_type, document_path, is_mandatory, verification_status) 
                    VALUES (?, ?, ?, ?, 'pending')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['application_id'],
                $data['document_type'],
                $filePath,
                $data['is_mandatory'] ?? false
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Document uploaded successfully',
                'data' => ['id' => $this->db->lastInsertId()]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteStudentDocument($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM admission_documents WHERE id = ?");
            $stmt->execute([$id]);

            return $this->response([
                'status' => 'success',
                'message' => 'Document deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function addParentToStudent($data)
    {
        try {
            $required = ['student_id', 'parent_info'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->addParent($data['student_id'], $data['parent_info']);

            return $this->response([
                'status' => 'success',
                'message' => 'Parent added successfully'
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateParentInfo($id, $data)
    {
        try {
            $updates = [];
            $params = [];
            $allowedFields = ['first_name', 'last_name', 'gender', 'phone_1', 'phone_2', 'email', 'occupation', 'address', 'status'];

            // Validate gender if provided
            if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female', 'other'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE parents SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Parent information updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function removeParentFromStudent($data)
    {
        try {
            $required = ['student_id', 'parent_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $stmt = $this->db->prepare("DELETE FROM student_parents WHERE student_id = ? AND parent_id = ?");
            $stmt->execute([$data['student_id'], $data['parent_id']]);

            return $this->response([
                'status' => 'success',
                'message' => 'Parent relationship removed successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function bulkDelete($data)
    {
        try {
            $required = ['student_ids'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($data['student_ids']), '?'));
            $stmt = $this->db->prepare("UPDATE students SET status = 'inactive' WHERE id IN ($placeholders)");
            $stmt->execute($data['student_ids']);

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Students deleted successfully',
                'data' => ['count' => $stmt->rowCount()]
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function markAttendanceForStudent($data)
    {
        try {
            $required = ['student_id', 'date', 'status'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            return $this->markAttendance($data['student_id'], $data);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    protected function getCurrentUserId()
    {
        return $_SESSION['user_id'] ?? $this->user_id ?? 1;
    }

    // ========================================================================
    // EXISTING STUDENT IMPORT METHODS
    // ========================================================================

    /**
     * Quick add existing student (bypasses admission workflow)
     * Use this when school starts using the system with already enrolled students
     * 
     * @param array $data Student data
     * @return array Response
     */
    public function addExistingStudent($data)
    {
        try {
            // Required fields for existing students
            $required = ['first_name', 'last_name', 'date_of_birth', 'gender', 'class_id', 'admission_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate gender
            $validGenders = ['male', 'female', 'other'];
            if (!in_array($data['gender'], $validGenders)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            $this->db->beginTransaction();

            // Generate admission number if not provided
            if (empty($data['admission_no'])) {
                $data['admission_no'] = $this->generateAdmissionNumber();
            }

            // Get stream_id from class_id and stream_name (if provided)
            $streamId = $this->getOrCreateStreamId($data['class_id'], $data['stream_name'] ?? 'A');

            // Insert student record
            $sql = "
                INSERT INTO students (
                    admission_no, first_name, middle_name, last_name, 
                    date_of_birth, gender, stream_id, admission_date,
                    assessment_number, birth_certificate_no, nationality,
                    religion, blood_group, special_needs,
                    previous_school, previous_class,
                    status, created_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['admission_no'],
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
                $data['date_of_birth'],
                $data['gender'],
                $streamId,
                $data['admission_date'],
                $data['assessment_number'] ?? null,
                $data['birth_certificate_no'] ?? null,
                $data['nationality'] ?? 'Kenyan',
                $data['religion'] ?? null,
                $data['blood_group'] ?? null,
                $data['special_needs'] ?? null,
                $data['previous_school'] ?? null,
                $data['previous_class'] ?? null,
                $this->getCurrentUserId()
            ]);

            $studentId = $this->db->lastInsertId();

            // Add parent/guardian if provided
            if (!empty($data['parent'])) {
                $this->addStudentParent($studentId, $data['parent']);
            }

            // Add address if provided
            if (!empty($data['address'])) {
                $this->addStudentAddress($studentId, $data['address']);
            }

            // Generate QR code
            $this->generateQRCode($studentId);

            $this->db->commit();

            $this->logAction('create', $studentId, "Added existing student: {$data['first_name']} {$data['last_name']} (Quick Add)");

            return $this->response([
                'status' => 'success',
                'message' => 'Existing student added successfully',
                'data' => [
                    'id' => $studentId,
                    'admission_no' => $data['admission_no']
                ]
            ], 201);

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Add multiple existing students at once
     * Accepts array of student data
     * 
     * @param array $data Array of students
     * @return array Response with success/failure details
     */
    public function addMultipleExistingStudents($data)
    {
        try {
            if (empty($data['students']) || !is_array($data['students'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Students array is required'
                ], 400);
            }

            $results = [
                'total' => count($data['students']),
                'successful' => 0,
                'failed' => 0,
                'errors' => [],
                'students' => []
            ];

            foreach ($data['students'] as $index => $studentData) {
                try {
                    $response = $this->addExistingStudent($studentData);

                    if ($response['status'] === 'success') {
                        $results['successful']++;
                        $results['students'][] = [
                            'index' => $index,
                            'status' => 'success',
                            'data' => $response['data']
                        ];
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'index' => $index,
                            'student' => $studentData['first_name'] . ' ' . $studentData['last_name'],
                            'error' => $response['message']
                        ];
                    }
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'index' => $index,
                        'student' => ($studentData['first_name'] ?? 'Unknown') . ' ' . ($studentData['last_name'] ?? ''),
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->logAction('create', null, "Bulk added {$results['successful']} existing students");

            return $this->response([
                'status' => $results['failed'] > 0 ? 'partial' : 'success',
                'message' => "Processed {$results['total']} students: {$results['successful']} successful, {$results['failed']} failed",
                'data' => $results
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Import existing students from CSV/Excel file
     * Enhanced version with better validation and error reporting
     * 
     * @param array $data File upload data
     * @return array Response
     */
    public function importExistingStudents($data)
    {
        try {
            if (empty($data['file'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No file uploaded'
                ], 400);
            }

            $bulkHelper = new \App\API\Includes\BulkOperationsHelper($this->db);
            $fileResult = $bulkHelper->processUploadedFile($data['file']);

            if ($fileResult['status'] === 'error') {
                return $this->response($fileResult, 400);
            }

            $results = [
                'total' => count($fileResult['data']),
                'successful' => 0,
                'failed' => 0,
                'skipped' => 0,
                'errors' => [],
                'warnings' => []
            ];

            $this->db->beginTransaction();

            foreach ($fileResult['data'] as $index => $row) {
                $rowNum = $index + 2; // +2 for header row and 0-based index

                try {
                    // Validate required fields
                    $requiredFields = ['first_name', 'last_name', 'date_of_birth', 'gender', 'class_id'];
                    $missingFields = [];

                    foreach ($requiredFields as $field) {
                        if (empty($row[$field])) {
                            $missingFields[] = $field;
                        }
                    }

                    if (!empty($missingFields)) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'row' => $rowNum,
                            'error' => 'Missing required fields: ' . implode(', ', $missingFields)
                        ];
                        continue;
                    }

                    // Check for duplicate admission number
                    if (!empty($row['admission_no'])) {
                        $stmt = $this->db->prepare("SELECT id FROM students WHERE admission_no = ?");
                        $stmt->execute([$row['admission_no']]);
                        if ($stmt->fetch()) {
                            $results['skipped']++;
                            $results['warnings'][] = [
                                'row' => $rowNum,
                                'message' => "Student with admission number {$row['admission_no']} already exists"
                            ];
                            continue;
                        }
                    } else {
                        $row['admission_no'] = $this->generateAdmissionNumber();
                    }

                    // Set default admission date if not provided
                    if (empty($row['admission_date'])) {
                        $row['admission_date'] = date('Y-m-d');
                    }

                    // Prepare student data
                    $studentData = [
                        'admission_no' => $row['admission_no'],
                        'first_name' => $row['first_name'],
                        'middle_name' => $row['middle_name'] ?? null,
                        'last_name' => $row['last_name'],
                        'date_of_birth' => $row['date_of_birth'],
                        'gender' => strtolower($row['gender']),
                        'class_id' => $row['class_id'],
                        'stream_name' => $row['stream_name'] ?? 'A',
                        'admission_date' => $row['admission_date'],
                        'assessment_number' => $row['assessment_number'] ?? null,
                        'nationality' => $row['nationality'] ?? 'Kenyan',
                        'religion' => $row['religion'] ?? null
                    ];

                    // Add parent data if available
                    if (!empty($row['parent_first_name']) && !empty($row['parent_last_name'])) {
                        $studentData['parent'] = [
                            'first_name' => $row['parent_first_name'],
                            'last_name' => $row['parent_last_name'],
                            'phone_1' => $row['parent_phone'] ?? null,
                            'email' => $row['parent_email'] ?? null,
                            'relationship' => $row['parent_relationship'] ?? 'parent'
                        ];
                    }

                    // Add the student
                    $response = $this->addExistingStudent($studentData);

                    if ($response['status'] === 'success') {
                        $results['successful']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'row' => $rowNum,
                            'error' => $response['message']
                        ];
                    }

                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->db->commit();

            $this->logAction('create', null, "Imported {$results['successful']} existing students from file");

            return $this->response([
                'status' => $results['failed'] > 0 ? 'partial' : 'success',
                'message' => "Import completed: {$results['successful']} successful, {$results['failed']} failed, {$results['skipped']} skipped",
                'data' => $results
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Download template for importing existing students
     * 
     * @return array CSV template data
     */
    public function getImportTemplate()
    {
        $headers = [
            'admission_no',
            'first_name',
            'middle_name',
            'last_name',
            'date_of_birth',
            'gender',
            'class_id',
            'stream_name',
            'admission_date',
            'assessment_number',
            'birth_certificate_no',
            'nationality',
            'religion',
            'blood_group',
            'previous_school',
            'previous_class',
            'parent_first_name',
            'parent_last_name',
            'parent_phone',
            'parent_email',
            'parent_relationship',
            'address_line1',
            'address_line2',
            'city',
            'county',
            'postal_code'
        ];

        $sampleData = [
            [
                'KWA/2024/001',
                'John',
                'Kamau',
                'Doe',
                '2010-05-15',
                'male',
                '5',
                'A',
                '2020-01-15',
                'NEM123456',
                'BC123456',
                'Kenyan',
                'Christian',
                'O+',
                'Previous Primary School',
                'Grade 4',
                'Jane',
                'Doe',
                '0712345678',
                'jane.doe@email.com',
                'mother',
                '123 Main Street',
                'Apartment 4B',
                'Nairobi',
                'Nairobi',
                '00100'
            ]
        ];

        return $this->response([
            'status' => 'success',
            'data' => [
                'headers' => $headers,
                'sample' => $sampleData,
                'instructions' => [
                    'Required fields: first_name, last_name, date_of_birth, gender, class_id',
                    'Date format: YYYY-MM-DD',
                    'Gender: male, female, or other',
                    'class_id: Numeric ID of the class (e.g., 1 for Grade 1)',
                    'If admission_no is empty, it will be auto-generated',
                    'If admission_date is empty, current date will be used'
                ]
            ]
        ]);
    }

    // ========================================================================
    // HELPER METHODS FOR EXISTING STUDENT IMPORT
    // ========================================================================

    /**
     * Get or create stream ID for a class
     */
    private function getOrCreateStreamId($classId, $streamName = 'A')
    {
        // Check if stream exists
        $stmt = $this->db->prepare("SELECT id FROM class_streams WHERE class_id = ? AND stream_name = ?");
        $stmt->execute([$classId, $streamName]);
        $stream = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stream) {
            return $stream['id'];
        }

        // Create new stream
        $stmt = $this->db->prepare("INSERT INTO class_streams (class_id, stream_name, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$classId, $streamName]);
        return $this->db->lastInsertId();
    }

    /**
     * Add parent/guardian for student
     */
    private function addStudentParent($studentId, $parentData)
    {
        // Check if parent already exists by phone or email
        $parentId = null;

        if (!empty($parentData['phone_1'])) {
            $stmt = $this->db->prepare("SELECT id FROM parents WHERE phone_1 = ? OR phone_2 = ?");
            $stmt->execute([$parentData['phone_1'], $parentData['phone_1']]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($parent) {
                $parentId = $parent['id'];
            }
        }

        // Create new parent if not found
        if (!$parentId) {
            $stmt = $this->db->prepare("
                INSERT INTO parents (first_name, last_name, phone_1, email, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $parentData['first_name'],
                $parentData['last_name'],
                $parentData['phone_1'] ?? null,
                $parentData['email'] ?? null
            ]);
            $parentId = $this->db->lastInsertId();
        }

        // Link student to parent
        $stmt = $this->db->prepare("
            INSERT INTO student_parents (student_id, parent_id, relationship, is_primary, created_at)
            VALUES (?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE relationship = VALUES(relationship)
        ");
        $stmt->execute([
            $studentId,
            $parentId,
            $parentData['relationship'] ?? 'parent'
        ]);

        return $parentId;
    }

    /**
     * Add address for student
     */
    private function addStudentAddress($studentId, $addressData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO student_addresses (
                student_id, address_line1, address_line2, 
                city, county, postal_code, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $studentId,
            $addressData['address_line1'] ?? null,
            $addressData['address_line2'] ?? null,
            $addressData['city'] ?? null,
            $addressData['county'] ?? null,
            $addressData['postal_code'] ?? null
        ]);
    }

    // ========================================================================
    // STUDENT ID CARD & PHOTO MANAGEMENT
    // ========================================================================

    /**
     * Upload student photo
     */
    public function uploadPhoto($studentId, $fileData)
    {
        return $this->idCardGenerator->uploadStudentPhoto($studentId, $fileData);
    }

    /**
     * Generate or regenerate QR code for student
     */
    public function generateQRCodeEnhanced($studentId)
    {
        return $this->idCardGenerator->generateEnhancedQRCode($studentId);
    }

    /**
     * Generate student ID card
     */
    public function generateStudentIDCard($studentId)
    {
        return $this->idCardGenerator->generateIDCard($studentId);
    }

    /**
     * Generate ID cards for entire class
     */
    public function generateClassIDCards($classId, $streamId = null)
    {
        return $this->idCardGenerator->generateBulkIDCards($classId, $streamId);
    }

    // ============================================================
    // ACADEMIC YEAR MANAGEMENT METHODS
    // ============================================================

    /**
     * Get current academic year
     */
    public function getCurrentAcademicYear()
    {
        return $this->yearManager->getCurrentAcademicYear();
    }

    /**
     * Get academic year by ID
     */
    public function getAcademicYear($yearId)
    {
        return $this->yearManager->getAcademicYear($yearId);
    }

    /**
     * Get all academic years
     */
    public function getAllAcademicYears($filters = [])
    {
        return $this->yearManager->getAllYears($filters);
    }

    /**
     * Create new academic year
     */
    public function createAcademicYear($data)
    {
        return $this->yearManager->createAcademicYear($data);
    }

    /**
     * Create next academic year
     */
    public function createNextAcademicYear($userId)
    {
        return $this->yearManager->createNextYear($userId);
    }

    /**
     * Set year as current
     */
    public function setCurrentAcademicYear($yearId)
    {
        return $this->yearManager->setCurrentYear($yearId);
    }

    /**
     * Update academic year status
     */
    public function updateAcademicYearStatus($yearId, $status)
    {
        return $this->yearManager->updateYearStatus($yearId, $status);
    }

    /**
     * Archive academic year
     */
    public function archiveAcademicYear($yearId, $userId, $notes = null)
    {
        return $this->yearManager->archiveYear($yearId, $userId, $notes);
    }

    /**
     * Get terms for academic year
     */
    public function getTermsForYear($yearId)
    {
        return $this->yearManager->getTermsForYear($yearId);
    }

    /**
     * Get current term
     */
    public function getCurrentTerm()
    {
        return $this->yearManager->getCurrentTerm();
    }

    // ============================================================
    // NEW PROMOTION SYSTEM METHODS (5 Scenarios)
    // ============================================================

    /**
     * SCENARIO 1: Promote single student
     */
    public function promoteSingleStudent($data)
    {
        $userId = $_REQUEST['user']['id'] ?? null;

        return $this->promotionManager->promoteSingleStudent(
            $data['student_id'],
            $data['to_class_id'],
            $data['to_stream_id'],
            $data['from_year_id'],
            $data['to_year_id'],
            $userId,
            $data['remarks'] ?? null
        );
    }

    /**
     * SCENARIO 2: Promote multiple students to same class
     */
    public function promoteMultipleStudents($data)
    {
        $userId = $_REQUEST['user']['id'] ?? null;

        return $this->promotionManager->promoteMultipleStudents(
            $data['student_ids'],
            $data['to_class_id'],
            $data['to_stream_id'],
            $data['from_year_id'],
            $data['to_year_id'],
            $userId,
            $data['remarks'] ?? null
        );
    }

    /**
     * SCENARIO 3: Promote entire class with teacher/room assignment
     */
    public function promoteEntireClass($data)
    {
        $userId = $_REQUEST['user']['id'] ?? null;

        return $this->promotionManager->promoteEntireClass(
            $data['from_class_id'],
            $data['from_stream_id'],
            $data['to_class_id'],
            $data['to_stream_id'],
            $data['from_year_id'],
            $data['to_year_id'],
            $userId,
            $data['teacher_id'] ?? null,
            $data['classroom'] ?? null,
            $data['remarks'] ?? null
        );
    }

    /**
     * SCENARIO 4: Bulk promote multiple classes (whole school)
     */
    public function promoteMultipleClasses($data)
    {
        $userId = $_REQUEST['user']['id'] ?? null;

        return $this->promotionManager->promoteMultipleClasses(
            $data['class_map'],
            $data['from_year_id'],
            $data['to_year_id'],
            $userId,
            $data['remarks'] ?? null
        );
    }

    /**
     * SCENARIO 5: Graduate Grade 9 students to alumni
     */
    public function graduateGrade9Students($data)
    {
        $userId = $_REQUEST['user']['id'] ?? null;

        return $this->promotionManager->graduateGrade9Students(
            $data['class_id'],
            $data['stream_id'],
            $data['academic_year_id'],
            $userId,
            $data['graduation_data'] ?? []
        );
    }

    /**
     * Get promotion batches
     */
    public function getPromotionBatches($filters = [])
    {
        $sql = "SELECT * FROM promotion_batches WHERE 1=1";
        $params = [];

        if (!empty($filters['academic_year_from'])) {
            $sql .= " AND academic_year_from = ?";
            $params[] = $filters['academic_year_from'];
        }

        if (!empty($filters['promotion_type'])) {
            $sql .= " AND promotion_type = ?";
            $params[] = $filters['promotion_type'];
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get alumni (graduated students)
     */
    public function getAlumni($filters = [])
    {
        $sql = "SELECT a.*, s.first_name, s.middle_name, s.last_name, s.admission_number,
                c.name as class_name, cs.name as stream_name
                FROM alumni a
                JOIN students s ON a.student_id = s.id
                LEFT JOIN classes c ON a.final_class_id = c.id
                LEFT JOIN class_streams cs ON a.final_stream_id = cs.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['academic_year_id'])) {
            $sql .= " AND a.academic_year_id = ?";
            $params[] = $filters['academic_year_id'];
        }

        if (!empty($filters['graduation_year'])) {
            $sql .= " AND YEAR(a.graduation_date) = ?";
            $params[] = $filters['graduation_year'];
        }

        $sql .= " ORDER BY a.graduation_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get current enrollments for an academic year
     */
    public function getCurrentEnrollments($yearId = null)
    {
        if (!$yearId) {
            $currentYear = $this->yearManager->getCurrentAcademicYear();
            $yearId = $currentYear['id'] ?? null;
        }

        $sql = "SELECT * FROM vw_current_enrollments WHERE academic_year_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get class roster for specific year
     */
    public function getClassRoster($classId, $streamId, $yearId = null)
    {
        if (!$yearId) {
            $currentYear = $this->yearManager->getCurrentAcademicYear();
            $yearId = $currentYear['id'] ?? null;
        }

        $sql = "SELECT ce.*, s.first_name, s.middle_name, s.last_name, s.admission_number, s.gender
                FROM class_enrollments ce
                JOIN students s ON ce.student_id = s.id
                WHERE ce.class_id = ? AND ce.stream_id = ? AND ce.academic_year_id = ?
                AND ce.enrollment_status IN ('enrolled', 'active')
                ORDER BY s.last_name, s.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$classId, $streamId, $yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}

