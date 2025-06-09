<?php

namespace App\API\Modules\staff;
require_once __DIR__ . '/../../includes/BaseAPI.php';

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

class StaffAPI extends BaseAPI {
    public function __construct() {
        parent::__construct('staff');
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
            $this->beginTransaction();

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

            $this->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Staff member created successfully',
                'data' => ['id' => $staffId, 'staff_no' => $staffNo]
            ], 201);

        } catch (Exception $e) {
            $this->rollback();
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
            $this->beginTransaction();

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

            $this->commit();
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
                    sc.name as category_name,
                    d.name as department_name,
                    COUNT(DISTINCT c.id) as assigned_classes,
                    COUNT(DISTINCT sub.id) as assigned_subjects
                FROM staff s
                LEFT JOIN staff_categories sc ON s.category_id = sc.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN class_teachers ct ON s.id = ct.teacher_id
                LEFT JOIN classes c ON ct.class_id = c.id
                LEFT JOIN subject_teachers st ON s.id = st.teacher_id
                LEFT JOIN subjects sub ON st.subject_id = sub.id
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
                    t.*,
                    s.name as subject_name,
                    c.name as class_name,
                    r.name as room_name
                FROM timetable t
                JOIN subjects s ON t.subject_id = s.id
                JOIN classes c ON t.class_id = c.id
                JOIN rooms r ON t.room_id = r.id
                WHERE t.teacher_id = ?
                ORDER BY t.day, t.start_time
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

            $sql = "INSERT INTO class_teachers (teacher_id, class_id) VALUES (?, ?)";
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
            if (empty($data['subject_id'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Subject ID is required'
                ], 400);
            }

            $sql = "INSERT INTO subject_teachers (teacher_id, subject_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $data['subject_id']]);

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
                    s.staff_id,
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
                    s.staff_id,
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
}