<?php

namespace App\API\Modules\attendance;

require_once __DIR__ . '/../../includes/BaseAPI.php';
use App\API\Includes\BaseAPI;

use PDO;
use Exception;

class AttendanceAPI extends BaseAPI {
    public function __construct() {
        parent::__construct('attendance');
    }

    // List attendance records with pagination and filtering
    public function list($params = []) {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $type = isset($params['type']) ? $params['type'] : 'student';
            $date = isset($params['date']) ? $params['date'] : date('Y-m-d');
            $class_id = isset($params['class_id']) ? $params['class_id'] : null;

            if ($type === 'student') {
                return $this->listStudentAttendance($page, $limit, $offset, $date, $class_id);
            } else {
                return $this->listStaffAttendance($page, $limit, $offset, $date);
            }

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single attendance record
    public function get($id) {
        try {
            $type = isset($_GET['type']) ? $_GET['type'] : 'student';
            $table = $type === 'student' ? 'student_attendance' : 'staff_attendance';

            $sql = "SELECT * FROM $table WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return $this->response(['status' => 'error', 'message' => 'Attendance record not found'], 404);
            }

            $this->logAction('read', $id, "Retrieved attendance record");
            
            return $this->response(['status' => 'success', 'data' => $record]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new attendance record
    public function create($data) {
        try {
            $this->beginTransaction();

            $type = isset($data['type']) ? $data['type'] : 'student';
            
            if ($type === 'student') {
                $result = $this->createStudentAttendance($data);
            } else {
                $result = $this->createStaffAttendance($data);
            }

            $this->commit();
            return $result;

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update attendance record
    public function update($id, $data) {
        try {
            $this->beginTransaction();

            $type = isset($data['type']) ? $data['type'] : 'student';
            $table = $type === 'student' ? 'student_attendance' : 'staff_attendance';

            // Check if record exists
            $stmt = $this->db->prepare("SELECT id FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'Attendance record not found'], 404);
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['status', 'remarks'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->commit();
            $this->logAction('update', $id, "Updated attendance record");

            return $this->response([
                'status' => 'success',
                'message' => 'Attendance record updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete attendance record
    public function delete($id) {
        try {
            $type = isset($_GET['type']) ? $_GET['type'] : 'student';
            $table = $type === 'student' ? 'student_attendance' : 'staff_attendance';

            $stmt = $this->db->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Attendance record not found'], 404);
            }

            $this->logAction('delete', $id, "Deleted attendance record");
            
            return $this->response([
                'status' => 'success',
                'message' => 'Attendance record deleted successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints
    public function handleCustomGet($id, $action, $params) {
        switch ($action) {
            case 'summary':
                return $this->getAttendanceSummary($id, $params);
            case 'report':
                return $this->generateAttendanceReport($id, $params);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Custom POST endpoints
    public function handleCustomPost($id, $action, $data) {
        switch ($action) {
            case 'bulk':
                return $this->bulkMarkAttendance($data);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Helper methods
    private function listStudentAttendance($page, $limit, $offset, $date, $class_id) {
        $where = "WHERE sa.date = ?";
        $bindings = [$date];

        if ($class_id) {
            $where .= " AND cs.class_id = ?";
            $bindings[] = $class_id;
        }

        // Get total count
        $sql = "
            SELECT COUNT(*) 
            FROM student_attendance sa
            JOIN students s ON sa.student_id = s.id
            JOIN class_streams cs ON s.stream_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            $where
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $total = $stmt->fetchColumn();

        // Get paginated results
        $sql = "
            SELECT 
                sa.*,
                s.admission_number,
                s.first_name,
                s.last_name,
                s.gender,
                cs.stream_name,
                c.name as class_name,
                t.name as term_name
            FROM student_attendance sa
            JOIN students s ON sa.student_id = s.id
            JOIN class_streams cs ON s.stream_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            LEFT JOIN academic_terms t ON sa.term_id = t.id
            $where
            ORDER BY c.name, cs.stream_name, s.admission_number
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($bindings, [$limit, $offset]));
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->response([
            'status' => 'success',
            'data' => [
                'attendance' => $records,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ]);
    }

    private function listStaffAttendance($page, $limit, $offset, $date) {
        $where = "WHERE sa.date = ?";
        $bindings = [$date];

        // Get total count
        $sql = "
            SELECT COUNT(*) 
            FROM staff_attendance sa
            JOIN staff s ON sa.staff_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN departments d ON s.department_id = d.id
            $where
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $total = $stmt->fetchColumn();

        // Get paginated results
        $sql = "
            SELECT 
                sa.*,
                s.staff_no,
                s.position,
                s.employment_date,
                u.first_name,
                u.last_name,
                u.email,
                d.name as department_name,
                d.code as department_code
            FROM staff_attendance sa
            JOIN staff s ON sa.staff_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN departments d ON s.department_id = d.id
            $where
            ORDER BY d.name, s.staff_no
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($bindings, [$limit, $offset]));
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->response([
            'status' => 'success',
            'data' => [
                'attendance' => $records,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ]);
    }

    private function validateAttendanceStatus($status, $type = 'student') {
        $validStatuses = [
            'student' => ['present', 'absent', 'late'],
            'staff' => ['present', 'absent', 'late', 'half_day']
        ];
        
        if (!in_array($status, $validStatuses[$type])) {
            throw new Exception('Invalid attendance status: ' . $status);
        }
        return $status;
    }

    private function createStudentAttendance($data) {
        try {
            $required = ['student_id', 'date', 'status', 'class_id', 'term_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate status
            $status = $this->validateAttendanceStatus($data['status'], 'student');

            $sql = "
                INSERT INTO student_attendance (
                    student_id,
                    date,
                    status,
                    class_id,
                    term_id,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['student_id'],
                $data['date'],
                $status,
                $data['class_id'],
                $data['term_id'],
                $data['remarks'] ?? null
            ]);

            $id = $this->db->lastInsertId();
            $this->logAction('create', $id, "Created student attendance record");

            return $this->response([
                'status' => 'success',
                'message' => 'Attendance record created successfully',
                'data' => ['id' => $id]
            ]);

        } catch (Exception $e) {
            $this->rollback();
            return $this->handleException($e);
        }
    }

    private function createStaffAttendance($data) {
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

            // Validate status
            $status = $this->validateAttendanceStatus($data['status'], 'staff');

            $sql = "
                INSERT INTO staff_attendance (
                    staff_id,
                    date,
                    status,
                    remarks
                ) VALUES (?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['date'],
                $status,
                $data['remarks'] ?? null
            ]);

            $id = $this->db->lastInsertId();
            $this->logAction('create', $id, "Created staff attendance record");

            return $this->response([
                'status' => 'success',
                'message' => 'Attendance record created successfully',
                'data' => ['id' => $id]
            ]);

        } catch (Exception $e) {
            $this->rollback();
            return $this->handleException($e);
        }
    }

    private function getAttendanceSummary($id, $params) {
        try {
            $type = isset($params['type']) ? $params['type'] : 'student';
            $month = isset($params['month']) ? $params['month'] : date('m');
            $year = isset($params['year']) ? $params['year'] : date('Y');

            if ($type === 'student') {
                $sql = "
                    SELECT 
                        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                        COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days
                    FROM student_attendance
                    WHERE student_id = ? 
                    AND MONTH(date) = ? 
                    AND YEAR(date) = ?
                ";
            } else {
                $sql = "
                    SELECT 
                        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                        COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                        COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days
                    FROM staff_attendance
                    WHERE staff_id = ? 
                    AND MONTH(date) = ? 
                    AND YEAR(date) = ?
                ";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $month, $year]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $summary
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function generateAttendanceReport($id, $params) {
        try {
            $type = isset($params['type']) ? $params['type'] : 'student';
            $start_date = isset($params['start_date']) ? $params['start_date'] : date('Y-m-01');
            $end_date = isset($params['end_date']) ? $params['end_date'] : date('Y-m-t');

            if ($type === 'student') {
                $sql = "
                    SELECT 
                        sa.*,
                        s.admission_no,
                        s.first_name,
                        s.last_name,
                        c.name as class_name,
                        cs.stream_name
                    FROM student_attendance sa
                    JOIN students s ON sa.student_id = s.id
                    JOIN class_streams cs ON s.stream_id = cs.id
                    JOIN classes c ON cs.class_id = c.id
                    WHERE sa.student_id = ? 
                    AND sa.date BETWEEN ? AND ?
                    ORDER BY sa.date
                ";
            } else {
                $sql = "
                    SELECT 
                        sa.*,
                        s.staff_no,
                        s.first_name,
                        s.last_name,
                        sc.name as category_name
                    FROM staff_attendance sa
                    JOIN staff s ON sa.staff_id = s.id
                    JOIN staff_categories sc ON s.category_id = sc.id
                    WHERE sa.staff_id = ? 
                    AND sa.date BETWEEN ? AND ?
                    ORDER BY sa.date
                ";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $start_date, $end_date]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'records' => $records,
                    'period' => [
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function bulkMarkAttendance($data) {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['type', 'date', 'records'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $type = $data['type'];
            $date = $data['date'];
            $table = $type === 'student' ? 'student_attendance' : 'staff_attendance';
            $id_field = $type === 'student' ? 'student_id' : 'staff_id';

            // Prepare bulk insert
            $values = [];
            $params = [];
            foreach ($data['records'] as $record) {
                if (!isset($record[$id_field]) || !isset($record['status'])) {
                    continue;
                }
                
                $values[] = "($id_field = ?, date = ?, status = ?, remarks = ?)";
                $params[] = $record[$id_field];
                $params[] = $date;
                $params[] = $record['status'];
                $params[] = $record['remarks'] ?? null;
            }

            if (!empty($values)) {
                $sql = "INSERT INTO $table ($id_field, date, status, remarks) VALUES " . implode(', ', $values);
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->commit();
            $this->logAction('create', null, "Bulk marked attendance for " . count($data['records']) . " records");

            return $this->response([
                'status' => 'success',
                'message' => 'Attendance records created successfully'
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
