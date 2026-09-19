<?php
namespace App\API\Modules\staff;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Assignment Workflow — 3NF/4NF schema.
 *
 * Multi-stage approval for a teaching assignment. In the new schema the operational assignment
 * row (academic_year_class_learning_area_teachers / academic_year_class_streams.class_teacher_id)
 * carries NO status/workflow columns — approval state lives entirely in `workflow_instances` +
 * `workflow_stage_history` (append-only). The assignment row is only *created* once the workflow
 * reaches 'approved'; a rejection simply never writes it. This keeps the operational table clean
 * and the decision trail in history, per the master→context→operational→history architecture.
 *
 * Stages: assignment_request → validation → head_teacher_approval → approved | rejected
 */
class AssignmentWorkflow extends WorkflowHandler
{
    protected $workflowType = 'staff_assignment';

    /**
     * Initiate an assignment-request workflow. The proposed assignment is carried as workflow
     * data (staff/class/subject/role) and only materialised into the normalized tables on approval.
     */
    public function initiateAssignmentRequest($proposal, $userId, $data = [])
    {
        try {
            // $proposal: staff_id, class_id, subject_id?, stream_id?, academic_year_id, role
            $staffId = (int)($proposal['staff_id'] ?? 0);
            $classId = (int)($proposal['class_id'] ?? 0);
            $role = $proposal['role'] ?? 'subject_teacher';
            if (!$staffId || !$classId) {
                return formatResponse(false, null, 'staff_id and class_id are required');
            }

            // Resolve display context from masters/context (read-only).
            $stmt = $this->db->prepare("
                SELECT s.id AS staff_id, s.staff_no, p.first_name, p.last_name, s.position,
                       c.name AS class_name, la.name AS subject_name, ay.year_name AS academic_year
                FROM staff s
                JOIN persons p ON p.id = s.person_id
                JOIN classes c ON c.id = ?
                LEFT JOIN learning_areas la ON la.id = ?
                LEFT JOIN academic_years ay ON ay.id = ?
                WHERE s.id = ?
            ");
            $stmt->execute([$classId, (int)($proposal['subject_id'] ?? 0), (int)($proposal['academic_year_id'] ?? 0), $staffId]);
            $ctx = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ctx) {
                return formatResponse(false, null, 'Staff member not found');
            }

            $workflowData = [
                'staff_id'         => $staffId,
                'staff_no'         => $ctx['staff_no'],
                'staff_name'       => $ctx['first_name'] . ' ' . $ctx['last_name'],
                'position'         => $ctx['position'],
                'class_id'         => $classId,
                'class_name'       => $ctx['class_name'],
                'stream_id'        => (int)($proposal['stream_id'] ?? 0) ?: null,
                'subject_id'       => (int)($proposal['subject_id'] ?? 0) ?: null,
                'subject'          => $ctx['subject_name'],
                'academic_year_id' => (int)($proposal['academic_year_id'] ?? 0) ?: null,
                'academic_year'    => $ctx['academic_year'],
                'role'             => $role,
                'requested_by'     => $userId,
            ];

            // startWorkflow($reference_type, $reference_id, $initial_data, $userId).
            // reference_id is 0 until the assignment row exists (created on approval).
            $instanceId = $this->startWorkflow('staff_assignment', 0, $workflowData, $userId);

            $this->logAction('create', $instanceId,
                "Initiated assignment workflow for {$workflowData['staff_name']} to {$ctx['class_name']} as {$role}");

            return formatResponse(true, [
                'workflow_id'   => $instanceId,
                'staff_name'    => $workflowData['staff_name'],
                'class'         => $ctx['class_name'],
                'role'          => $role,
                'current_stage' => 'assignment_request',
                'next_stage'    => 'validation',
                'status'        => 'pending'
            ], 'Assignment workflow initiated successfully');
        } catch (Exception $e) {
            $this->handleException($e);
            return [];
        }
    }

    /**
     * Validate the proposed assignment (Stage 2). Validation is explicit here — the legacy
     * sp_validate_staff_assignment stored procedure was repurposed for department appointments
     * in the new schema and no longer matches this use case, so we validate the context directly.
     */
    public function validateAssignment($instanceId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($instanceId);
            if (!$workflow) {
                return formatResponse(false, null, 'Workflow instance not found');
            }
            if ($workflow['current_stage'] !== 'validation') {
                return formatResponse(false, null, "Cannot validate assignment. Current stage is: {$workflow['current_stage']}");
            }

            $workflowData = json_decode($workflow['data_json'], true);
            [$ok, $error] = $this->validateProposedAssignment($workflowData);

            if (!$ok) {
                $workflowData['rejection_reason'] = $error;
                $this->advanceStage($instanceId, 'rejected', 'validation_failed', $workflowData);
                return formatResponse(false, [
                    'workflow_id' => $instanceId, 'status' => 'rejected', 'reason' => $error
                ], 'Assignment validation failed: ' . $error);
            }

            $workflowData['validation_passed'] = true;
            $workflowData['validated_by'] = $userId;
            $workflowData['validated_at'] = date('Y-m-d H:i:s');
            $workflowData['validation_remarks'] = $data['remarks'] ?? 'Assignment validated successfully';

            $this->advanceStage($instanceId, 'head_teacher_approval', 'validation_passed', $workflowData);

            return formatResponse(true, [
                'workflow_id' => $instanceId, 'current_stage' => 'head_teacher_approval', 'status' => 'pending_approval'
            ], 'Assignment validated, forwarded to Head Teacher for approval');
        } catch (Exception $e) {
            $this->handleException($e);
            return [];
        }
    }

    /**
     * Head Teacher approval (Stage 3). On approval the assignment is materialised into the
     * normalized tables via StaffTeachingAssignmentService (the single normalized writer).
     */
    public function headTeacherApproval($instanceId, $userId, $action, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($instanceId);
            if (!$workflow) {
                return formatResponse(false, null, 'Workflow instance not found');
            }
            if ($workflow['current_stage'] !== 'head_teacher_approval') {
                return formatResponse(false, null, "Cannot perform head teacher approval. Current stage is: {$workflow['current_stage']}");
            }
            if (!$this->userHasRole($userId, 'Head Teacher')) {
                return formatResponse(false, null, 'Only Head Teacher can approve assignments');
            }

            $workflowData = json_decode($workflow['data_json'], true);

            if ($action === 'reject') {
                $workflowData['rejection_reason'] = $data['remarks'] ?? 'Rejected by Head Teacher';
                $this->advanceStage($instanceId, 'rejected', 'head_teacher_rejected', $workflowData);
                return formatResponse(true, [
                    'workflow_id' => $instanceId, 'status' => 'rejected', 'stage' => 'rejected'
                ], 'Assignment rejected by Head Teacher');
            }

            // Approve → write the operational row now.
            $service = new \App\API\Services\StaffTeachingAssignmentService();
            $save = [
                'teacher_id'       => $workflowData['staff_id'],
                'class_id'         => $workflowData['class_id'],
                'subject_id'       => $workflowData['subject_id'] ?? null,
                'stream_id'        => $workflowData['stream_id'] ?? null,
                'academic_year_id' => $workflowData['academic_year_id'] ?? null,
                'role'             => $workflowData['role'] ?? 'subject_teacher',
            ];
            $assignmentId = ($workflowData['role'] ?? '') === 'class_teacher'
                ? $service->saveClassTeacher($save, null, $userId)
                : $service->saveSubjectAssignment($save, null, $userId);

            $workflowData['assignment_id'] = $assignmentId;
            $workflowData['approved_by'] = $userId;
            $workflowData['approved_at'] = date('Y-m-d H:i:s');
            $workflowData['approval_remarks'] = $data['remarks'] ?? null;
            $this->advanceStage($instanceId, 'approved', 'head_teacher_approved', $workflowData);

            return formatResponse(true, [
                'workflow_id' => $instanceId, 'assignment_id' => $assignmentId, 'status' => 'approved', 'stage' => 'approved'
            ], 'Assignment approved and activated');
        } catch (Exception $e) {
            $this->handleException($e);
            return [];
        }
    }

    /**
     * Workload analysis — reads vw_staff_workload (built on the normalized teacher tables).
     * The view is keyed to the active academic year, so it is filtered by staff only.
     */
    public function getWorkloadAnalysis($staffId, $academicYearId = null)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('staff_workload') . " WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            return formatResponse(true, $stmt->fetch(PDO::FETCH_ASSOC) ?: [], 'Workload analysis retrieved');
        } catch (Exception $e) {
            $this->handleException($e);
            return [];
        }
    }

    // ---- helpers ---------------------------------------------------------

    /** Validate that the proposed assignment's context rows exist (require-pre-existing model). */
    private function validateProposedAssignment(array $d): array
    {
        $staff = $this->db->prepare("SELECT COUNT(*) FROM staff WHERE id = ? AND status = 'active'");
        $staff->execute([$d['staff_id'] ?? 0]);
        if (!$staff->fetchColumn()) return [false, 'Staff member not found or inactive'];

        $yearId = (int)($d['academic_year_id'] ?? 0);
        $classId = (int)($d['class_id'] ?? 0);
        $ayc = $this->db->prepare("SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ?");
        $ayc->execute([$yearId, $classId]);
        $aycId = (int)$ayc->fetchColumn();
        if (!$aycId) return [false, 'This class is not set up for the selected academic year'];

        if (($d['role'] ?? '') !== 'class_teacher') {
            $area = $this->db->prepare("SELECT COUNT(*) FROM academic_year_class_learning_areas WHERE academic_year_class_id = ? AND learning_area_id = ?");
            $area->execute([$aycId, (int)($d['subject_id'] ?? 0)]);
            if (!$area->fetchColumn()) return [false, 'This learning area is not set up for the class in the selected academic year'];
        }
        return [true, ''];
    }

    /** Resolve whether a user holds a named role via the user_roles → roles junction (RBAC). */
    private function userHasRole($userId, string $roleName): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND r.name = ?
        ");
        $stmt->execute([$userId, $roleName]);
        return (bool)$stmt->fetchColumn();
    }

    protected function validateTransition($fromStage, $toStage, $data)
    {
        $validTransitions = [
            'assignment_request' => ['validation', 'rejected'],
            'validation' => ['head_teacher_approval', 'rejected'],
            'head_teacher_approval' => ['approved', 'rejected']
        ];
        return isset($validTransitions[$fromStage]) && in_array($toStage, $validTransitions[$fromStage], true);
    }

    protected function processStage($instanceId, $stage, $data)
    {
        switch ($stage) {
            case 'assignment_request':
                return true;
            case 'validation':
                $this->createNotification($instanceId, null, 'Assignment Pending Validation',
                    "{$data['staff_name']} to {$data['class_name']} as {$data['role']}", 'workflow');
                return true;
            case 'head_teacher_approval':
                $this->createNotification($instanceId, null, 'Assignment Pending Approval',
                    "{$data['staff_name']} to {$data['class_name']} as {$data['role']}", 'workflow');
                return true;
            case 'approved':
                $this->createNotification($instanceId, $data['staff_id'], 'Assignment Approved',
                    "You have been assigned to {$data['class_name']} as {$data['role']}", 'workflow');
                return true;
            case 'rejected':
                $this->createNotification($instanceId, $data['requested_by'], 'Assignment Rejected',
                    "Assignment of {$data['staff_name']} to {$data['class_name']} has been rejected", 'workflow');
                return true;
            default:
                return false;
        }
    }
}
