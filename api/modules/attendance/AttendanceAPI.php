<?php

namespace App\API\Modules\Attendance;

use App\API\Includes\BaseAPI;

use PDO;
use Exception;

use App\API\Modules\Attendance\StudentAttendanceManager;
use App\API\Modules\Attendance\StaffAttendanceManager;
use App\API\Modules\Attendance\AttendanceWorkflow;
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

}
