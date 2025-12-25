<?php

namespace App\API\Modules\staff;
require_once __DIR__ . '/../../includes/BaseAPI.php';
require_once __DIR__ . '/StaffService.php';

use App\API\Includes\BaseAPI;
use App\API\Modules\system\MediaManager;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
class StaffAPI extends BaseAPI {
    private $service;
    private $mediaManager;

    public function __construct() {
        parent::__construct('staff');
        $this->service = new StaffService();
        $this->mediaManager = new MediaManager($this->db);
    }

    // --- Media Operations ---
    // Upload staff document or photo
    public function uploadStaffMedia($staffId, $file, $type = 'document', $uploaderId = null, $description = '', $tags = '')
    {
        $context = 'staff';
        $entityId = $staffId;
        $albumId = null;
        return $this->mediaManager->upload($file, $context, $entityId, $albumId, $uploaderId, $description, $tags);
    }

    // List staff media
    public function listStaffMedia($staffId, $filters = [])
    {
        $filters['context'] = 'staff';
        $filters['entity_id'] = $staffId;
        return $this->mediaManager->listMedia($filters);
    }

    // Delete staff media
    public function deleteStaffMedia($mediaId)
    {
        return $this->mediaManager->deleteMedia($mediaId);
    }

    // List all staff members with pagination and search
    public function list($params = []) {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE s.staff_no LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM staff s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN departments d ON s.department_id = d.id
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results with user data
            $sql = "
                SELECT 
                    s.*,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.status as user_status,
                    d.name as department_name,
                    d.code as department_code
                FROM staff s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN departments d ON s.department_id = d.id
                $where
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed staff members');
            
            return $this->response([
                'status' => 'success',
                'data' => [
                    'staff' => $staff,
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

    // Get single staff member
    public function get($id) {
        try {
            $staff = $this->getStaffWithUserData($id);

            if (!$staff) {
                return $this->response(['status' => 'error', 'message' => 'Staff not found'], 404);
            }

            // Get staff qualifications
            $sql = "
                SELECT 
                    qualification_type,
                    title,
                    institution,
                    year_obtained,
                    description,
                    document_url
                FROM staff_qualifications
                WHERE staff_id = ?
                ORDER BY year_obtained DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $staff['qualifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get staff experience
            $sql = "
                SELECT 
                    organization,
                    position,
                    start_date,
                    end_date,
                    responsibilities,
                    document_url
                FROM staff_experience
                WHERE staff_id = ?
                ORDER BY start_date DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $staff['experience'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', $id, "Retrieved staff member: {$staff['first_name']} {$staff['last_name']}");
            
            return $this->response(['status' => 'success', 'data' => $staff]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new staff member
    public function create($data) {
        try {
            $required = ['first_name', 'last_name', 'email', 'department_id', 'position', 'employment_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Start transaction
            $this->db->beginTransaction();

            // Create user account first
            $sql = "
                INSERT INTO users (
                    first_name,
                    last_name,
                    email,
                    password,
                    role,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                password_hash($data['password'] ?? 'changeme123', PASSWORD_DEFAULT),
                'staff',
                'active'
            ]);

            $userId = $this->db->lastInsertId();

            // Generate staff number
            $staffNo = $this->generateStaffNumber();

            // Create staff record
            $sql = "
                INSERT INTO staff (
                    staff_no,
                    user_id,
                    department_id,
                    position,
                    employment_date,
                    nssf_no,
                    kra_pin,
                    nhif_no,
                    bank_account,
                    salary,
                    gender,
                    marital_status,
                    tsc_no,
                    address,
                    profile_pic_url,
                    documents_folder,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $staffNo,
                $userId,
                $data['department_id'],
                $data['position'],
                $data['employment_date'],
                $data['nssf_no'] ?? null,
                $data['kra_pin'] ?? null,
                $data['nhif_no'] ?? null,
                $data['bank_account'] ?? null,
                $data['salary'] ?? null,
                $data['gender'] ?? null,
                $data['marital_status'] ?? null,
                $data['tsc_no'] ?? null,
                $data['address'] ?? null,
                $data['profile_pic_url'] ?? null,
                $data['documents_folder'] ?? null,
                'active'
            ]);

            $staffId = $this->db->lastInsertId();

            // Add qualifications if provided
            if (!empty($data['qualifications'])) {
                    $sql = "
                        INSERT INTO staff_qualifications (
                            staff_id,
                        qualification_type,
                        title,
                            institution,
                        year_obtained,
                        description,
                        document_url
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                foreach ($data['qualifications'] as $qual) {
                    $stmt->execute([
                        $staffId,
                        $qual['type'],
                        $qual['title'],
                        $qual['institution'],
                        $qual['year'],
                        $qual['description'] ?? null,
                        $qual['document_url'] ?? null
                    ]);
                }
            }

            // Add experience if provided
            if (!empty($data['experience'])) {
                    $sql = "
                        INSERT INTO staff_experience (
                            staff_id,
                            organization,
                            position,
                            start_date,
                            end_date,
                        responsibilities,
                        document_url
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                foreach ($data['experience'] as $exp) {
                    $stmt->execute([
                        $staffId,
                        $exp['organization'],
                        $exp['position'],
                        $exp['start_date'],
                        $exp['end_date'] ?? null,
                        $exp['responsibilities'] ?? null,
                        $exp['document_url'] ?? null
                    ]);
                }
            }

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Staff member created successfully',
                'data' => ['id' => $staffId, 'staff_no' => $staffNo]
            ], 201);

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    // Update staff member
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM staff WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'Staff not found'], 404);
            }

            // Check if staff_id or email already exists for other staff
            if (isset($data['staff_id']) || isset($data['email'])) {
                $sql = "SELECT id FROM staff WHERE (staff_id = ? OR email = ?) AND id != ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $data['staff_id'] ?? '',
                    $data['email'] ?? '',
                    $id
                ]);
                if ($stmt->fetch()) {
                    return $this->response([
                        'status' => 'error',
                        'message' => 'Staff ID or email already exists'
                    ], 400);
                }
            }

            $updates = [];
            $params = [];
            $allowedFields = [
                'staff_id', 'first_name', 'last_name', 'email', 'phone',
                'department_id', 'position', 'join_date', 'status', 'address',
                'emergency_contact', 'blood_group', 'qualifications', 'salary'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE staff SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Update qualifications if provided
            if (!empty($data['qualification_details'])) {
                // Remove existing qualifications
                $stmt = $this->db->prepare("DELETE FROM staff_qualifications WHERE staff_id = ?");
                $stmt->execute([$id]);

                // Add new qualifications
                foreach ($data['qualification_details'] as $qual) {
                    $sql = "
                        INSERT INTO staff_qualifications (
                            staff_id,
                            degree,
                            institution,
                            year,
                            details
                        ) VALUES (?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $id,
                        $qual['degree'],
                        $qual['institution'],
                        $qual['year'],
                        $qual['details'] ?? null
                    ]);
                }
            }

            // Update experience if provided
            if (!empty($data['experience_details'])) {
                // Remove existing experience
                $stmt = $this->db->prepare("DELETE FROM staff_experience WHERE staff_id = ?");
                $stmt->execute([$id]);

                // Add new experience
                foreach ($data['experience_details'] as $exp) {
                    $sql = "
                        INSERT INTO staff_experience (
                            staff_id,
                            organization,
                            position,
                            start_date,
                            end_date,
                            description
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $id,
                        $exp['organization'],
                        $exp['position'],
                        $exp['start_date'],
                        $exp['end_date'] ?? null,
                        $exp['description'] ?? null
                    ]);
                }
            }

            $this->logAction('update', $id, "Updated staff member details");

            return $this->response([
                'status' => 'success',
                'message' => 'Staff updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete staff member (soft delete)
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE staff SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Staff not found'], 404);
            }

            $this->logAction('delete', $id, "Deactivated staff member");
            
            return $this->response([
                'status' => 'success',
                'message' => 'Staff deleted successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints
    public function handleCustomGet($id, $action, $params) {
        switch ($action) {
            case 'schedule':
                return $this->getTeachingSchedule($id);
            case 'attendance':
                return $this->getAttendanceRecord($id, $params);
            case 'leave':
                return $this->getLeaveHistory($id);
            case 'departments':
                return $this->getDepartmentAssignments($id);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Custom POST endpoints
    public function handleCustomPost($id, $action, $data) {
        switch ($action) {
            case 'leave':
                return $this->submitLeaveRequest($id, $data);
            case 'attendance':
                return $this->markAttendance($id, $data);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Helper methods
    private function generateStaffNumber() {
        $year = date('Y');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) + 1 as next_number 
            FROM staff 
            WHERE staff_no LIKE ?
        ");
        $stmt->execute(["{$year}%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNumber = str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
        return "{$year}{$nextNumber}";
    }

    // Implementation of custom endpoint methods
    private function getTeachingSchedule($id) {
        try {
            $sql = "
                SELECT 
                    ts.*, 
                    la.name as subject_name,
                    c.name as class_name,
                    cs.stream_name
                FROM teacher_subjects ts
                JOIN learning_areas la ON ts.subject_id = la.id
                JOIN classes c ON ts.class_id = c.id
                JOIN class_streams cs ON c.id = cs.class_id
                WHERE ts.teacher_id = ? AND c.academic_year = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, CURRENT_YEAR]);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $schedule
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getAttendanceRecord($id, $params) {
        try {
            $month = isset($params['month']) ? $params['month'] : date('m');
            $year = isset($params['year']) ? $params['year'] : date('Y');

            $sql = "
                SELECT * FROM view_staff_attendance_summary
                WHERE staff_id = ? AND month = ? AND year = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $month, $year]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $attendance
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getLeaveHistory($id) {
        try {
            $sql = "
                SELECT 
                    sl.*,
                    lt.name as leave_type,
                    lt.days_allowed,
                    CONCAT(s.first_name, ' ', s.last_name) as approved_by_name
                FROM staff_leave sl
                JOIN leave_types lt ON sl.leave_type_id = lt.id
                LEFT JOIN staff s ON sl.approved_by = s.id
                WHERE sl.staff_id = ?
                ORDER BY sl.start_date DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $leaveHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $leaveHistory
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getDepartmentAssignments($id) {
        try {
            $sql = "
                SELECT 
                    sd.*,
                    d.name as department_name,
                    CASE WHEN d.hod_id = ? THEN true ELSE false END as is_hod
                FROM staff_departments sd
                JOIN departments d ON sd.department_id = d.id
                WHERE sd.staff_id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $id]);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $departments
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function submitLeaveRequest($id, $data) {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['leave_type_id', 'start_date', 'end_date', 'reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO staff_leave (staff_id, leave_type_id, start_date, end_date, reason)
                VALUES (?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $data['leave_type_id'],
                $data['start_date'],
                $data['end_date'],
                $data['reason']
            ]);

            $leaveId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $leaveId, "Submitted leave request for staff ID: $id");

            return $this->response([
                'status' => 'success',
                'message' => 'Leave request submitted successfully',
                'data' => ['id' => $leaveId]
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }


    public function getProfile($id) {
        try {
            $sql = "
                SELECT 
                    s.*,
                    sc.category_name,
                    d.name as department_name,
                    COUNT(DISTINCT sca.class_id) as assigned_classes,
                    COUNT(DISTINCT cs.subject_id) as assigned_subjects
                FROM staff s
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN staff_class_assignments sca ON s.id = sca.staff_id AND sca.status = 'active'
                LEFT JOIN classes c ON sca.class_id = c.id
                LEFT JOIN class_schedules cs ON s.id = cs.teacher_id AND cs.status = 'active'
                WHERE s.id = ?
                GROUP BY s.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                return $this->response(['status' => 'error', 'message' => 'Staff not found'], 404);
            }

            return $this->response(['status' => 'success', 'data' => $profile]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSchedule($id) {
        try {
            $sql = "
                SELECT 
                    cs.*,
                    cu.name as subject_name,
                    c.name as class_name,
                    r.name as room_name
                FROM class_schedules cs
                LEFT JOIN curriculum_units cu ON cs.subject_id = cu.id
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE cs.teacher_id = ?
                ORDER BY 
                    FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    cs.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $schedule]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignClass($id, $data) {
        try {
            if (empty($data['class_id'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Class ID is required'
                ], 400);
            }

            $sql = "INSERT INTO staff_class_assignments (staff_id, class_id, academic_year_id, role, start_date) 
                    VALUES (?, ?, 
                        (SELECT id FROM academic_years WHERE status = 'active' LIMIT 1), 
                        'class_teacher', CURDATE())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $data['class_id']]);

            return $this->response([
                'status' => 'success',
                'message' => 'Class assigned successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignSubject($id, $data) {
        try {
            if (empty($data['subject_id']) || empty($data['class_id'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Subject ID and Class ID are required'
                ], 400);
            }

            // Note: Subjects are now assigned via class_schedules table
            // This creates a basic schedule entry - adjust day/time as needed
            $sql = "INSERT INTO class_schedules (teacher_id, subject_id, class_id, day_of_week, start_time, end_time) 
                    VALUES (?, ?, ?, 'Monday', '08:00:00', '09:00:00')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $data['subject_id'], $data['class_id']]);

            return $this->response([
                'status' => 'success',
                'message' => 'Subject assigned successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getDepartments() {
        try {
            $sql = "SELECT * FROM departments WHERE status = 'active' ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $departments]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getAttendance($params) {
        try {
            $sql = "
                SELECT 
                    sa.*,
                    s.first_name,
                    s.last_name,
                    s.staff_no,
                    s.id as staff_id,
                    d.name as department_name
                FROM staff_attendance sa
                JOIN staff s ON sa.staff_id = s.id
                JOIN departments d ON s.department_id = d.id
                WHERE sa.date BETWEEN ? AND ?
                ORDER BY sa.date DESC, s.first_name, s.last_name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $params['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
                $params['end_date'] ?? date('Y-m-d')
            ]);

            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $attendance]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function markAttendance($data) {
        try {
            $required = ['staff_id', 'date', 'status'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO staff_attendance (
                    staff_id,
                    date,
                    status,
                    check_in,
                    check_out,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    check_in = VALUES(check_in),
                    check_out = VALUES(check_out),
                    remarks = VALUES(remarks)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['date'],
                $data['status'],
                $data['check_in'] ?? null,
                $data['check_out'] ?? null,
                $data['remarks'] ?? null
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Attendance marked successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getLeaves($params) {
        try {
            $sql = "
                SELECT 
                    sl.*,
                    s.first_name,
                    s.last_name,
                    s.staff_no,
                    s.id as staff_id,
                    d.name as department_name
                FROM staff_leaves sl
                JOIN staff s ON sl.staff_id = s.id
                JOIN departments d ON s.department_id = d.id
                WHERE sl.start_date >= ? AND sl.end_date <= ?
                ORDER BY sl.start_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $params['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
                $params['end_date'] ?? date('Y-m-d', strtotime('+30 days'))
            ]);

            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $leaves]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function applyLeave($data) {
        try {
            $required = ['staff_id', 'start_date', 'end_date', 'type', 'reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO staff_leaves (
                    staff_id,
                    start_date,
                    end_date,
                    type,
                    reason,
                    status,
                    documents
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['start_date'],
                $data['end_date'],
                $data['type'],
                $data['reason'],
                $data['status'] ?? 'pending',
                $data['documents'] ?? null
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Leave application submitted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateLeaveStatus($id, $data) {
        try {
            if (empty($data['status'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Status is required'
                ], 400);
            }

            $sql = "
                UPDATE staff_leaves 
                SET status = ?, remarks = ?
                WHERE id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['status'],
                $data['remarks'] ?? null,
                $id
            ]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Leave not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Leave status updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getStaffWithUserData($id) {
        $sql = "
            SELECT 
                s.*,
                u.first_name,
                u.last_name,
                u.email,
                u.status as user_status,
                d.name as department_name,
                d.code as department_code
            FROM staff s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ===============================================================
    // PAYROLL OPERATIONS (using StaffPayrollManager)
    // ===============================================================

    /**
     * View staff payslip details
     */
    public function viewPayslip($staffId, $month, $year) {
        try {
            $result = $this->service->getPayrollManager()->viewPayslip($staffId, $month, $year);
            return formatResponse('success', 'Payslip retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get staff payroll history
     */
    public function getPayrollHistory($staffId, $startDate = null, $endDate = null) {
        try {
            $result = $this->service->getPayrollManager()->getPayrollHistory($staffId, $startDate, $endDate);
            return formatResponse('success', 'Payroll history retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * View staff allowances
     */
    public function viewAllowances($staffId) {
        try {
            $result = $this->service->getPayrollManager()->viewAllowances($staffId);
            return formatResponse('success', 'Allowances retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * View staff deductions
     */
    public function viewDeductions($staffId) {
        try {
            $result = $this->service->getPayrollManager()->viewDeductions($staffId);
            return formatResponse('success', 'Deductions retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get loan details
     */
    public function getLoanDetails($staffId, $loanId = null) {
        try {
            $result = $this->service->getPayrollManager()->getLoanDetails($staffId, $loanId);
            return formatResponse('success', 'Loan details retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Request salary advance
     */
    public function requestAdvance($staffId, $userId, $data) {
        try {
            $result = $this->service->getPayrollManager()->requestAdvance($staffId, $userId, $data);
            return formatResponse('success', 'Advance request submitted successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Apply for loan
     */
    public function applyForLoan($staffId, $userId, $data) {
        try {
            $result = $this->service->getPayrollManager()->applyForLoan($staffId, $userId, $data);
            return formatResponse('success', 'Loan application submitted successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Download P9 form
     */
    public function downloadP9Form($staffId, $year) {
        try {
            $result = $this->service->getPayrollManager()->downloadP9Form($staffId, $year);
            return formatResponse('success', 'P9 form generated successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Download payslip PDF
     */
    public function downloadPayslip($staffId, $month, $year) {
        try {
            $result = $this->service->getPayrollManager()->downloadPayslip($staffId, $month, $year);
            return formatResponse('success', 'Payslip downloaded successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Export payroll history to Excel
     */
    public function exportPayrollHistory($staffId, $startDate = null, $endDate = null) {
        try {
            $result = $this->service->getPayrollManager()->exportPayrollHistory($staffId, $startDate, $endDate);
            return formatResponse('success', 'Payroll history exported successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    // ===============================================================
    // PERFORMANCE OPERATIONS (using StaffPerformanceManager)
    // ===============================================================

    /**
     * Get staff performance review history
     */
    public function getReviewHistory($staffId) {
        try {
            $result = $this->service->getPerformanceManager()->getReviewHistory($staffId);
            return formatResponse('success', 'Review history retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport($reviewId) {
        try {
            $result = $this->service->getPerformanceManager()->generatePerformanceReport($reviewId);
            return formatResponse('success', 'Performance report generated successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get academic KPI summary
     */
    public function getAcademicKPISummary($staffId, $academicYearId = null) {
        try {
            $result = $this->service->getPerformanceManager()->getAcademicKPISummary($staffId, $academicYearId);
            return formatResponse('success', 'Academic KPI summary retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    // ===============================================================
    // ASSIGNMENT OPERATIONS (using StaffAssignmentManager)
    // ===============================================================

    /**
     * Get staff assignments
     */
    public function getStaffAssignments($staffId, $academicYearId = null, $includeHistory = false) {
        try {
            $result = $this->service->getAssignmentManager()->getStaffAssignments($staffId, $academicYearId, $includeHistory);
            return formatResponse('success', 'Assignments retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get staff workload summary
     */
    public function getStaffWorkload($staffId, $academicYearId = null) {
        try {
            $result = $this->service->getAssignmentManager()->getStaffWorkload($staffId, $academicYearId);
            return formatResponse('success', 'Workload summary retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get current staff assignments
     */
    public function getCurrentAssignments($staffId) {
        try {
            $result = $this->service->getAssignmentManager()->getCurrentAssignments($staffId);
            return formatResponse('success', 'Current assignments retrieved successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    // ===============================================================
    // WORKFLOW OPERATIONS
    // ===============================================================

    /**
     * Initiate leave request workflow
     */
    public function initiateLeaveRequest($staffId, $userId, $data) {
        try {
            $result = $this->service->getLeaveWorkflow()->initiateLeaveRequest($staffId, $userId, $data);
            return formatResponse('success', 'Leave request submitted successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Initiate assignment workflow
     */
    public function initiateAssignment($staffId, $classStreamId, $academicYearId, $userId, $data) {
        try {
            $result = $this->service->getAssignmentWorkflow()->initiateAssignment(
                $staffId, $classStreamId, $academicYearId, $userId, $data
            );
            return formatResponse('success', 'Assignment request submitted successfully', $result);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }
}
     