<?php

namespace App\API\Modules\attendance;

use App\API\Includes\BaseAPI;

use PDO;
use Exception;

use App\API\Modules\attendance\StudentAttendanceManager;
use App\API\Modules\attendance\StaffAttendanceManager;
use App\API\Modules\attendance\AttendanceWorkflow;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
class AttendanceAPI extends BaseAPI {
    protected $studentManager;
    protected $staffManager;
    protected $workflow;

    public function __construct() {
        parent::__construct('attendance');
        $this->studentManager = new StudentAttendanceManager();
        $this->staffManager = new StaffAttendanceManager();
        $this->workflow = new AttendanceWorkflow();
    }
    // =============================
    // Workflow-driven attendance
    // =============================
    /**
     * Start a new attendance workflow instance (e.g., for a class, term, or department)
     */
    public function startAttendanceWorkflow($context = [])
    {
        try {
            // Expecting $context to have 'reference_type' and 'reference_id'
            $referenceType = $context['reference_type'] ?? 'attendance_session';
            $referenceId = $context['reference_id'] ?? null;
            $initialData = $context['initial_data'] ?? [];
            if (!$referenceId) {
                throw new Exception('Missing reference_id for attendance workflow');
            }
            $instance = $this->workflow->startWorkflow($referenceType, $referenceId, $initialData);
            return successResponse($instance);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Advance workflow to next stage (e.g., teacher marks, admin approves, etc.)
     */
    public function advanceAttendanceWorkflow($workflowInstanceId, $action, $data = [])
    {
        try {
            $result = $this->workflow->advanceStage($workflowInstanceId, $action, $data);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get workflow instance status and history
     */
    public function getAttendanceWorkflowStatus($workflowInstanceId)
    {
        try {
            $instance = $this->workflow->getWorkflowInstance($workflowInstanceId);
            return successResponse($instance);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * List all workflow instances for a context (e.g., class, term, department)
     */
    public function listAttendanceWorkflows($context = [])
    {
        try {
            $workflows = $this->workflow->listWorkflows($context);
            return successResponse($workflows);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    // List attendance records with pagination and filtering
    public function list($params = [])
    {
        // Implement your own listing logic or call a manager method
        return errorResponse('Not implemented', 501);
    }

    // Advanced: Expose student and staff attendance manager methods
    public function getStudentAttendanceHistory($studentId)
    {
        try {
            $data = $this->studentManager->getStudentAttendanceHistory($studentId);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function getStudentAttendanceSummary($studentId)
    {
        try {
            $data = $this->studentManager->getStudentAttendanceSummary($studentId);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function getClassAttendance($classId, $termId, $yearId)
    {
        try {
            $data = $this->studentManager->getClassAttendance($classId, $termId, $yearId);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function getStudentAttendancePercentage($studentId, $termId, $yearId)
    {
        try {
            $data = $this->studentManager->getAttendancePercentage($studentId, $termId, $yearId);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function getChronicStudentAbsentees($classId, $termId, $yearId, $threshold = 0.2)
    {
        try {
            $data = $this->studentManager->getChronicAbsentees($classId, $termId, $yearId, $threshold);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    // Staff
    public function getStaffAttendanceHistory($staffId)
    {
        try {
            $data = $this->staffManager->getStaffAttendanceHistory($staffId);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function getStaffAttendanceSummary($staffId)
    {
        try {
            $data = $this->staffManager->getStaffAttendanceSummary($staffId);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function getDepartmentAttendance($departmentId, $termId, $yearId)
    {
        try {
            $data = $this->staffManager->getDepartmentAttendance($departmentId, $termId, $yearId);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function getStaffAttendancePercentage($staffId, $termId, $yearId)
    {
        try {
            $data = $this->staffManager->getAttendancePercentage($staffId, $termId, $yearId);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function getChronicStaffAbsentees($departmentId, $termId, $yearId, $threshold = 0.2)
    {
        try {
            $data = $this->staffManager->getChronicAbsentees($departmentId, $termId, $yearId, $threshold);
            return successResponse($data);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    // =============================
    // CRUD Operations
    // =============================

    /**
     * Create attendance record (student or staff)
     */
    public function create($data = [])
    {
        try {
            $type = $data['type'] ?? null; // 'student' or 'staff'

            if (!$type || !in_array($type, ['student', 'staff'])) {
                throw new Exception('Invalid attendance type. Must be "student" or "staff"');
            }

            // Validate required fields
            $required = ['date', 'status'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // Validate status
            if (!in_array($data['status'], ['present', 'absent', 'late'])) {
                throw new Exception('Invalid status. Must be "present", "absent", or "late"');
            }

            if ($type === 'student') {
                if (empty($data['student_id'])) {
                    throw new Exception("Missing required field: student_id");
                }

                $sql = "INSERT INTO student_attendance (student_id, date, status, class_id, term_id, marked_by, created_at)
                        VALUES (:student_id, :date, :status, :class_id, :term_id, :marked_by, NOW())";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'student_id' => $data['student_id'],
                    'date' => $data['date'],
                    'status' => $data['status'],
                    'class_id' => $data['class_id'] ?? null,
                    'term_id' => $data['term_id'] ?? null,
                    'marked_by' => $this->user_id
                ]);

                $id = $this->db->lastInsertId();
                return successResponse(['id' => $id], 'Student attendance record created successfully');
            } else {
                if (empty($data['staff_id'])) {
                    throw new Exception("Missing required field: staff_id");
                }

                $sql = "INSERT INTO staff_attendance (staff_id, date, status, marked_by, created_at)
                        VALUES (:staff_id, :date, :status, :marked_by, NOW())";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'staff_id' => $data['staff_id'],
                    'date' => $data['date'],
                    'status' => $data['status'],
                    'marked_by' => $this->user_id
                ]);

                $id = $this->db->lastInsertId();
                return successResponse(['id' => $id], 'Staff attendance record created successfully');
            }
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Update attendance record
     */
    public function update($id, $data = [])
    {
        try {
            $type = $data['type'] ?? null; // 'student' or 'staff'

            if (!$type || !in_array($type, ['student', 'staff'])) {
                throw new Exception('Invalid attendance type. Must be "student" or "staff"');
            }

            if (empty($data['status'])) {
                throw new Exception("Missing required field: status");
            }

            if (!in_array($data['status'], ['present', 'absent', 'late'])) {
                throw new Exception('Invalid status. Must be "present", "absent", or "late"');
            }

            if ($type === 'student') {
                $sql = "UPDATE student_attendance SET status = :status WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['status' => $data['status'], 'id' => $id]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Student attendance record not found');
                }

                return successResponse(null, 'Student attendance record updated successfully');
            } else {
                $sql = "UPDATE staff_attendance SET status = :status WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['status' => $data['status'], 'id' => $id]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Staff attendance record not found');
                }

                return successResponse(null, 'Staff attendance record updated successfully');
            }
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Delete attendance record
     */
    public function delete($id, $data = [])
    {
        try {
            $type = $data['type'] ?? null; // 'student' or 'staff'

            if (!$type || !in_array($type, ['student', 'staff'])) {
                throw new Exception('Invalid attendance type. Must be "student" or "staff"');
            }

            if ($type === 'student') {
                $sql = "DELETE FROM student_attendance WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $id]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Student attendance record not found');
                }

                return successResponse(null, 'Student attendance record deleted successfully');
            } else {
                $sql = "DELETE FROM staff_attendance WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $id]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Staff attendance record not found');
                }

                return successResponse(null, 'Staff attendance record deleted successfully');
            }
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 400);
        }
    }

}
