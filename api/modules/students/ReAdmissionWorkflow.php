<?php

namespace App\API\Modules\Students;

use App\Config;
use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Student Re-Admission Workflow
 * 
 * Handles re-admission of students who previously left the school
 * (transferred out, dropped out, or graduated)
 * 
 * Simpler workflow than full admission since student record already exists
 */
class ReAdmissionWorkflow extends WorkflowHandler
{
    public function __construct()
    {
        parent::__construct('readmission');
    }

    /**
     * Submit re-admission request
     * @param array $data Re-admission data
     * @return array Response
     */
    public function submitReAdmission($data)
    {
        try {
            $required = ['student_id', 'readmission_class_id', 'readmission_stream_id', 'readmission_date', 'readmission_reason', 'parent_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->beginTransaction();

            // Get student current status
            $stmt = $this->db->prepare("
                SELECT s.*, cs.class_id as previous_class_id, cs.id as previous_stream_id
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                WHERE s.id = ?
            ");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $this->rollback();
                return formatResponse(false, null, 'Student not found');
            }

            // Validate that student is eligible for re-admission
            $validStatuses = ['transferred', 'graduated', 'suspended', 'inactive'];
            if (!in_array($student['status'], $validStatuses)) {
                $this->rollback();
                return formatResponse(false, null, "Student status '{$student['status']}' is not eligible for re-admission");
            }

            // Check for existing pending re-admission
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM student_readmissions 
                WHERE student_id = ? AND status IN ('pending_review', 'documents_verification', 'pending_approval')
            ");
            $stmt->execute([$data['student_id']]);
            if ($stmt->fetchColumn() > 0) {
                $this->rollback();
                return formatResponse(false, null, 'Student already has a pending re-admission request');
            }

            // Check for outstanding fees from previous enrollment
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(balance), 0) as outstanding
                FROM student_fees
                WHERE student_id = ? AND balance > 0
            ");
            $stmt->execute([$data['student_id']]);
            $feeCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate re-admission number
            $readmissionNo = $this->db->query("SELECT generate_readmission_number()")->fetchColumn();

            // Create re-admission record
            $sql = "INSERT INTO student_readmissions (
                readmission_no, student_id, previous_status, previous_class_id, previous_stream_id,
                exit_date, exit_reason,
                readmission_class_id, readmission_stream_id, readmission_date, readmission_reason,
                parent_id, transfer_certificate_path, parent_request_letter_path,
                previous_fee_balance, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $readmissionNo,
                $data['student_id'],
                $student['status'],
                $student['previous_class_id'],
                $student['previous_stream_id'],
                $data['exit_date'] ?? null,
                $data['exit_reason'] ?? "Previous status: {$student['status']}",
                $data['readmission_class_id'],
                $data['readmission_stream_id'],
                $data['readmission_date'],
                $data['readmission_reason'],
                $data['parent_id'],
                $data['transfer_certificate_path'] ?? null,
                $data['parent_request_letter_path'] ?? null,
                $feeCheck['outstanding'],
                'pending_review'
            ]);

            $readmissionId = $this->db->lastInsertId();

            $this->commit();
            $this->logAction('create', $readmissionId, "Re-admission request submitted for {$student['first_name']} {$student['last_name']}");

            return formatResponse(true, [
                'readmission_id' => $readmissionId,
                'readmission_no' => $readmissionNo,
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'previous_fee_balance' => $feeCheck['outstanding'],
                'status' => 'pending_review'
            ], 'Re-admission request submitted successfully');
        } catch (Exception $e) {
            $this->rollback();
            $this->logError('submitReAdmission', $e->getMessage());
            return formatResponse(false, null, 'Failed to submit re-admission: ' . $e->getMessage());
        }
    }

    /**
     * Review re-admission request
     * @param int $readmissionId Re-admission ID
     * @param array $data Review data
     * @return array Response
     */
    public function reviewReAdmission($readmissionId, $data)
    {
        try {
            $this->beginTransaction();

            $currentUserId = $this->getCurrentUserId();

            $sql = "UPDATE student_readmissions SET
                status = 'documents_verification',
                reviewed_by = ?,
                review_date = NOW(),
                review_notes = ?
            WHERE id = ? AND status = 'pending_review'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $currentUserId,
                $data['review_notes'] ?? 'Documents under verification',
                $readmissionId
            ]);

            if ($stmt->rowCount() === 0) {
                $this->rollback();
                return formatResponse(false, null, 'Re-admission not found or already reviewed');
            }

            $this->commit();
            $this->logAction('update', $readmissionId, 'Re-admission reviewed - documents verification stage');

            return formatResponse(true, ['readmission_id' => $readmissionId], 'Re-admission reviewed successfully');
        } catch (Exception $e) {
            $this->rollback();
            $this->logError('reviewReAdmission', $e->getMessage());
            return formatResponse(false, null, 'Failed to review re-admission: ' . $e->getMessage());
        }
    }

    /**
     * Approve or reject re-admission
     * @param int $readmissionId Re-admission ID
     * @param array $data Approval data
     * @return array Response
     */
    public function approveReAdmission($readmissionId, $data)
    {
        try {
            $required = ['decision']; // 'approved' or 'rejected'
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required field: decision');
            }

            if (!in_array($data['decision'], ['approved', 'rejected'])) {
                return formatResponse(false, null, 'Invalid decision. Must be: approved or rejected');
            }

            $this->beginTransaction();

            $currentUserId = $this->getCurrentUserId();
            $newStatus = $data['decision'] === 'approved' ? 'approved' : 'rejected';

            $sql = "UPDATE student_readmissions SET
                status = ?,
                approved_by = ?,
                approval_date = NOW(),
                approval_notes = ?,
                rejection_reason = ?,
                fee_waiver_granted = ?,
                fee_waiver_amount = ?,
                fee_waiver_reason = ?
            WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $newStatus,
                $currentUserId,
                $data['approval_notes'] ?? null,
                $data['decision'] === 'rejected' ? ($data['rejection_reason'] ?? 'Not approved') : null,
                $data['fee_waiver_granted'] ?? false,
                $data['fee_waiver_amount'] ?? 0.00,
                $data['fee_waiver_reason'] ?? null,
                $readmissionId
            ]);

            if ($stmt->rowCount() === 0) {
                $this->rollback();
                return formatResponse(false, null, 'Re-admission not found');
            }

            $this->commit();
            $this->logAction('update', $readmissionId, "Re-admission {$data['decision']}");

            return formatResponse(true, [
                'readmission_id' => $readmissionId,
                'decision' => $data['decision'],
                'status' => $newStatus
            ], "Re-admission {$data['decision']} successfully");
        } catch (Exception $e) {
            $this->rollback();
            $this->logError('approveReAdmission', $e->getMessage());
            return formatResponse(false, null, 'Failed to approve re-admission: ' . $e->getMessage());
        }
    }

    /**
     * Complete re-admission and update student record
     * @param int $readmissionId Re-admission ID
     * @return array Response
     */
    public function completeReAdmission($readmissionId)
    {
        try {
            $this->beginTransaction();

            // Get re-admission details
            $stmt = $this->db->prepare("SELECT * FROM student_readmissions WHERE id = ?");
            $stmt->execute([$readmissionId]);
            $readmission = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$readmission) {
                $this->rollback();
                return formatResponse(false, null, 'Re-admission not found');
            }

            if ($readmission['status'] !== 'approved') {
                $this->rollback();
                return formatResponse(false, null, 'Re-admission must be approved before completion');
            }

            // Update student record
            $sql = "UPDATE students SET
                stream_id = ?,
                status = 'active',
                updated_at = NOW()
            WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $readmission['readmission_stream_id'],
                $readmission['student_id']
            ]);

            // Mark re-admission as completed
            $this->db->prepare("UPDATE student_readmissions SET status = 'completed', completed_at = NOW() WHERE id = ?")
                ->execute([$readmissionId]);

            $this->commit();
            $this->logAction('update', $readmissionId, 'Re-admission completed - Student re-activated');

            return formatResponse(true, [
                'readmission_id' => $readmissionId,
                'readmission_no' => $readmission['readmission_no'],
                'student_id' => $readmission['student_id'],
                'status' => 'completed'
            ], 'Re-admission completed successfully. Student is now active.');
        } catch (Exception $e) {
            $this->rollback();
            $this->logError('completeReAdmission', $e->getMessage());
            return formatResponse(false, null, 'Failed to complete re-admission: ' . $e->getMessage());
        }
    }

    /**
     * Get re-admission details
     * @param int $readmissionId Re-admission ID
     * @return array Response
     */
    public function getReAdmissionDetails($readmissionId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    sr.*,
                    s.first_name, s.last_name, s.admission_no,
                    pc.name as previous_class_name,
                    rc.name as readmission_class_name,
                    rs.name as readmission_stream_name,
                    p.first_name as parent_first_name,
                    p.last_name as parent_last_name,
                    rev.first_name as reviewed_by_name,
                    apr.first_name as approved_by_name
                FROM student_readmissions sr
                JOIN students s ON sr.student_id = s.id
                LEFT JOIN classes pc ON sr.previous_class_id = pc.id
                JOIN classes rc ON sr.readmission_class_id = rc.id
                JOIN class_streams rs ON sr.readmission_stream_id = rs.id
                JOIN parents p ON sr.parent_id = p.id
                LEFT JOIN users rev ON sr.reviewed_by = rev.id
                LEFT JOIN users apr ON sr.approved_by = apr.id
                WHERE sr.id = ?
            ");
            $stmt->execute([$readmissionId]);
            $readmission = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$readmission) {
                return formatResponse(false, null, 'Re-admission not found');
            }

            return formatResponse(true, $readmission, 'Re-admission details retrieved successfully');
        } catch (Exception $e) {
            $this->logError('getReAdmissionDetails', $e->getMessage());
            return formatResponse(false, null, 'Failed to get re-admission details: ' . $e->getMessage());
        }
    }
}
