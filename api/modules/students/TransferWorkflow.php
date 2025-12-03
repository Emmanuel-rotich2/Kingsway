<?php
namespace App\API\Modules\students;

use App\Config;
use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Student Transfer Workflow Handler
 * 
 * Implements complete 6-stage transfer workflow:
 * 1. Transfer Request - Initiate transfer request
 * 2. Clearance Check - Multi-department clearance verification
 * 3. Fee Settlement - Ensure all fees are settled or waived
 * 4. Transfer Approval - Head teacher/principal approval
 * 5. Document Preparation - Generate leaving certificate and clearance form
 * 6. Transfer Completion - Finalize transfer and update student status
 * 
 * Supports:
 * - External transfers (to another school)
 * - Internal transfers (class/stream changes)
 * - Graduation completion
 * - Multi-department clearance tracking
 * - Document generation
 */
class TransferWorkflow extends WorkflowHandler
{
    private $workflowCode = 'student_transfer';

    public function __construct()
    {
        parent::__construct('student_transfer');
    }

    // ========================================================================
    // STAGE 1: TRANSFER REQUEST
    // ========================================================================

    /**
     * Initiate a transfer request
     * @param array $data Transfer request data
     * @return array Response with transfer_id and transfer_no
     */
    public function initiateTransfer($data)
    {
        try {
            $required = ['student_id', 'transfer_type', 'transfer_reason', 'request_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // Validate transfer_type
            $validTypes = ['internal', 'external', 'graduation'];
            if (!in_array($data['transfer_type'], $validTypes)) {
                return formatResponse(false, null, 'Invalid transfer type. Must be: internal, external, or graduation');
            }

            // Type-specific validation
            if ($data['transfer_type'] === 'external' && empty($data['transfer_to_school'])) {
                return formatResponse(false, null, 'transfer_to_school is required for external transfers');
            }

            if ($data['transfer_type'] === 'internal') {
                if (empty($data['new_stream_id'])) {
                    return formatResponse(false, null, 'new_stream_id is required for internal transfers');
                }
            }

            $this->db->beginTransaction();

            // Get student current information
            $stmt = $this->db->prepare("
                SELECT s.*, cs.class_id, cs.name as stream_name, c.name as class_name
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE s.id = ?
            ");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Student not found');
            }

            // Check if student already has pending transfer
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM student_transfers 
                WHERE student_id = ? AND status IN ('draft', 'pending_clearance', 'clearance_in_progress', 'pending_approval')
            ");
            $stmt->execute([$data['student_id']]);
            if ($stmt->fetchColumn() > 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Student already has a pending transfer request');
            }

            // Generate transfer number
            $transferNo = $this->db->query("SELECT generate_transfer_number()")->fetchColumn();

            // Create transfer record
            $sql = "INSERT INTO student_transfers (
                transfer_no, student_id, transfer_type,
                current_class_id, current_stream_id,
                transfer_to_school, transfer_to_school_code, destination_address, destination_contact,
                new_class_id, new_stream_id,
                transfer_reason, parent_request_letter,
                requested_by, request_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $currentUserId = $this->getCurrentUserId();
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $transferNo,
                $data['student_id'],
                $data['transfer_type'],
                $student['class_id'],
                $student['stream_id'],
                $data['transfer_to_school'] ?? null,
                $data['transfer_to_school_code'] ?? null,
                $data['destination_address'] ?? null,
                $data['destination_contact'] ?? null,
                $data['new_class_id'] ?? null,
                $data['new_stream_id'] ?? null,
                $data['transfer_reason'],
                $data['parent_request_letter'] ?? null,
                $currentUserId,
                $data['request_date'],
                'pending_clearance' // Auto-start clearance process
            ]);

            $transferId = $this->db->lastInsertId();

            // Initialize clearance records for all mandatory departments
            $this->db->prepare("CALL sp_initialize_transfer_clearances(?)")->execute([$transferId]);

            $this->db->commit();
            $this->logAction('create', $transferId, "Transfer request initiated for student {$student['first_name']} {$student['last_name']} - Type: {$data['transfer_type']}");

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'transfer_no' => $transferNo,
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'status' => 'pending_clearance'
            ], 'Transfer request initiated successfully. Clearance process has started.');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('initiateTransfer', $e->getMessage());
            return formatResponse(false, null, 'Failed to initiate transfer: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // STAGE 2: CLEARANCE CHECK
    // ========================================================================

    /**
     * Get clearance status for a transfer
     * @param int $transferId Transfer ID
     * @return array Response with clearance details
     */
    public function getClearanceStatus($transferId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    sc.*,
                    cd.code as dept_code,
                    cd.name as dept_name,
                    cd.description as dept_description,
                    cd.is_mandatory,
                    u.first_name as cleared_by_name
                FROM student_clearances sc
                JOIN clearance_departments cd ON sc.department_id = cd.id
                LEFT JOIN users u ON sc.cleared_by = u.id
                WHERE sc.transfer_id = ?
                ORDER BY cd.sort_order
            ");
            $stmt->execute([$transferId]);
            $clearances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate summary
            $total = count($clearances);
            $cleared = 0;
            $blocked = 0;
            $pending = 0;

            foreach ($clearances as $clearance) {
                if ($clearance['status'] === 'cleared') {
                    $cleared++;
                } elseif ($clearance['status'] === 'blocked') {
                    $blocked++;
                } elseif ($clearance['status'] === 'pending') {
                    $pending++;
                }
            }

            $allCleared = ($total > 0 && $cleared === $total);

            return formatResponse(true, [
                'clearances' => $clearances,
                'summary' => [
                    'total' => $total,
                    'cleared' => $cleared,
                    'blocked' => $blocked,
                    'pending' => $pending,
                    'all_cleared' => $allCleared
                ]
            ], 'Clearance status retrieved successfully');

        } catch (Exception $e) {
            $this->logError('getClearanceStatus', $e->getMessage());
            return formatResponse(false, null, 'Failed to get clearance status: ' . $e->getMessage());
        }
    }

    /**
     * Process clearance for a specific department
     * @param int $transferId Transfer ID
     * @param string $departmentCode Department code (e.g., 'LIBRARY', 'FINANCE')
     * @param array $data Clearance data
     * @return array Response
     */
    public function processClearance($transferId, $departmentCode, $data)
    {
        try {
            $this->db->beginTransaction();

            // Get student_id from transfer
            $stmt = $this->db->prepare("SELECT student_id FROM student_transfers WHERE id = ?");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                $this->db->rollBack();
                $response = formatResponse(false, null, 'Transfer not found');
            } else {
                // Get department
                $stmt = $this->db->prepare("SELECT * FROM clearance_departments WHERE code = ? AND is_active = TRUE");
                $stmt->execute([$departmentCode]);
                $department = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$department) {
                    $this->db->rollBack();
                    $response = formatResponse(false, null, 'Department not found or inactive');
                } else {
                    // Run automated check if procedure exists
                    $hasIssues = false;
                    $issueDescription = null;
                    $outstandingAmount = 0.00;

                    if ($department['check_procedure']) {
                        $stmt = $this->db->prepare("CALL {$department['check_procedure']}(?, @is_cleared, @outstanding, @description)");
                        $stmt->execute([$transfer['student_id']]);

                        $result = $this->db->query("SELECT @is_cleared as is_cleared, @outstanding as outstanding, @description as description")->fetch(PDO::FETCH_ASSOC);

                        $hasIssues = !$result['is_cleared'];
                        $issueDescription = $result['description'];
                        $outstandingAmount = $result['outstanding'] ?? 0.00;
                    }

                    // Get clearance record
                    $stmt = $this->db->prepare("
                        SELECT * FROM student_clearances 
                        WHERE transfer_id = ? AND department_id = ?
                    ");
                    $stmt->execute([$transferId, $department['id']]);
                    $clearance = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$clearance) {
                        // Create if not exists
                        $stmt = $this->db->prepare("
                            INSERT INTO student_clearances (transfer_id, department_id, status)
                            VALUES (?, ?, 'pending')
                        ");
                        $stmt->execute([$transferId, $department['id']]);
                        $clearanceId = $this->db->lastInsertId();
                    } else {
                        $clearanceId = $clearance['id'];
                    }

                    // Update clearance status
                    $status = $data['status'] ?? ($hasIssues ? 'blocked' : 'cleared');
                    $currentUserId = $this->getCurrentUserId();

                    $sql = "UPDATE student_clearances SET
                        status = ?,
                        has_issues = ?,
                        issue_description = ?,
                        outstanding_amount = ?,
                        resolution_notes = ?,
                        waiver_granted = ?,
                        waiver_granted_by = ?,
                        waiver_reason = ?,
                        cleared_by = ?,
                        cleared_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $status,
                        $hasIssues,
                        $data['issue_description'] ?? $issueDescription,
                        $outstandingAmount,
                        $data['resolution_notes'] ?? null,
                        $data['waiver_granted'] ?? false,
                        ($data['waiver_granted'] ?? false) ? $currentUserId : null,
                        $data['waiver_reason'] ?? null,
                        $currentUserId,
                        $clearanceId
                    ]);

                    // Check if all clearances are done
                    $stmt = $this->db->prepare("
                        SELECT COUNT(*) as total,
                               SUM(CASE WHEN status = 'cleared' THEN 1 ELSE 0 END) as cleared
                        FROM student_clearances
                        WHERE transfer_id = ?
                    ");
                    $stmt->execute([$transferId]);
                    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Update transfer status if all cleared
                    if ($summary['total'] > 0 && $summary['cleared'] == $summary['total']) {
                        $this->db->prepare("UPDATE student_transfers SET status = 'fees_pending' WHERE id = ?")
                            ->execute([$transferId]);
                    } elseif ($hasIssues && $status === 'blocked') {
                        $this->db->prepare("UPDATE student_transfers SET status = 'clearance_in_progress' WHERE id = ?")
                            ->execute([$transferId]);
                    }

                    $this->db->commit();
                    $this->logAction('update', $transferId, "Clearance processed for {$department['name']} - Status: {$status}");

                    $response = formatResponse(true, [
                        'clearance_id' => $clearanceId,
                        'status' => $status,
                        'has_issues' => $hasIssues,
                        'all_cleared' => ($summary['cleared'] == $summary['total'])
                    ], "Clearance for {$department['name']} processed successfully");
                }
            }

            return $response;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('processClearance', $e->getMessage());
            return formatResponse(false, null, 'Failed to process clearance: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // STAGE 3: FEE SETTLEMENT
    // ========================================================================

    /**
     * Verify fee settlement for transfer
     * @param int $transferId Transfer ID
     * @return array Response with fee status
     */
    public function verifyFeeSettlement($transferId)
    {
        try {
            // Get student from transfer
            $stmt = $this->db->prepare("SELECT student_id FROM student_transfers WHERE id = ?");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return formatResponse(false, null, 'Transfer not found');
            }

            // Check finance clearance
            $stmt = $this->db->prepare("CALL sp_check_finance_clearance(?, @is_cleared, @outstanding, @description)");
            $stmt->execute([$transfer['student_id']]);

            $result = $this->db->query("SELECT @is_cleared as is_cleared, @outstanding as outstanding, @description as description")->fetch(PDO::FETCH_ASSOC);

            $isSettled = (bool) $result['is_cleared'];
            $outstandingAmount = $result['outstanding'];

            if ($isSettled) {
                // Update transfer status
                $this->db->prepare("UPDATE student_transfers SET status = 'pending_approval' WHERE id = ?")
                    ->execute([$transferId]);

                $this->logAction('update', $transferId, 'Fee settlement verified - Moving to approval stage');
            }

            return formatResponse(true, [
                'is_settled' => $isSettled,
                'outstanding_amount' => $outstandingAmount,
                'description' => $result['description']
            ], $isSettled ? 'All fees settled' : 'Outstanding fees found');

        } catch (Exception $e) {
            $this->logError('verifyFeeSettlement', $e->getMessage());
            return formatResponse(false, null, 'Failed to verify fee settlement: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // STAGE 4: TRANSFER APPROVAL
    // ========================================================================

    /**
     * Approve or reject transfer request
     * @param int $transferId Transfer ID
     * @param array $data Approval data
     * @return array Response
     */
    public function approveTransfer($transferId, $data)
    {
        try {
            $required = ['decision']; // 'approved' or 'rejected'
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: decision');
            }

            if (!in_array($data['decision'], ['approved', 'rejected'])) {
                return formatResponse(false, null, 'Invalid decision. Must be: approved or rejected');
            }

            $this->db->beginTransaction();

            $currentUserId = $this->getCurrentUserId();
            $newStatus = $data['decision'] === 'approved' ? 'approved' : 'rejected';

            $sql = "UPDATE student_transfers SET
                status = ?,
                approved_by = ?,
                approval_date = NOW(),
                approval_notes = ?,
                rejection_reason = ?,
                conduct_rating = ?,
                final_remarks = ?
            WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $newStatus,
                $currentUserId,
                $data['approval_notes'] ?? null,
                $data['decision'] === 'rejected' ? ($data['rejection_reason'] ?? 'Not approved') : null,
                $data['conduct_rating'] ?? null,
                $data['final_remarks'] ?? null,
                $transferId
            ]);

            // If approved, move to document preparation
            if ($data['decision'] === 'approved') {
                $this->db->prepare("UPDATE student_transfers SET status = 'documents_ready' WHERE id = ?")
                    ->execute([$transferId]);
            }

            $this->db->commit();
            $this->logAction('update', $transferId, "Transfer {$data['decision']} by user {$currentUserId}");

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'status' => $newStatus,
                'decision' => $data['decision']
            ], "Transfer {$data['decision']} successfully");

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('approveTransfer', $e->getMessage());
            return formatResponse(false, null, 'Failed to approve transfer: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // STAGE 5: DOCUMENT PREPARATION (Stub - Full implementation in DocumentGenerator)
    // ========================================================================

    /**
     * Mark documents as ready (actual generation happens in DocumentGenerator module)
     * @param int $transferId Transfer ID
     * @param array $data Document paths
     * @return array Response
     */
    public function markDocumentsReady($transferId, $data)
    {
        try {
            $this->db->beginTransaction();

            // Generate leaving certificate number if not exists
            $stmt = $this->db->prepare("SELECT leaving_certificate_no FROM student_transfers WHERE id = ?");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            $certNo = $transfer['leaving_certificate_no'];
            if (!$certNo) {
                $year = date('Y');
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM student_transfers WHERE YEAR(created_at) = ?");
                $stmt->execute([$year]);
                $count = $stmt->fetchColumn() + 1;
                $certNo = sprintf('LC-%d-%04d', $year, $count);
            }

            $sql = "UPDATE student_transfers SET
                leaving_certificate_no = ?,
                leaving_certificate_path = ?,
                leaving_certificate_generated_at = NOW(),
                clearance_form_path = ?
            WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $certNo,
                $data['leaving_certificate_path'] ?? null,
                $data['clearance_form_path'] ?? null,
                $transferId
            ]);

            $this->db->commit();
            $this->logAction('update', $transferId, 'Transfer documents marked as ready');

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'leaving_certificate_no' => $certNo
            ], 'Documents marked as ready');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('markDocumentsReady', $e->getMessage());
            return formatResponse(false, null, 'Failed to mark documents ready: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // STAGE 6: TRANSFER COMPLETION
    // ========================================================================

    /**
     * Complete the transfer and update student status
     * @param int $transferId Transfer ID
     * @param array $data Completion data
     * @return array Response
     */
    public function completeTransfer($transferId, $data)
    {
        try {
            $this->db->beginTransaction();

            // Get transfer details
            $stmt = $this->db->prepare("SELECT * FROM student_transfers WHERE id = ?");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Transfer not found');
            }

            if ($transfer['status'] !== 'approved' && $transfer['status'] !== 'documents_ready') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Transfer must be approved before completion');
            }

            // Update transfer status
            $sql = "UPDATE student_transfers SET
                status = 'completed',
                effective_date = ?,
                completed_at = NOW()
            WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['effective_date'] ?? date('Y-m-d'),
                $transferId
            ]);

            // Student status will be updated by trigger based on transfer_type
            // The trigger handles: external/graduation -> update status, internal -> update stream

            $this->db->commit();
            $this->logAction('update', $transferId, 'Transfer completed successfully');

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'transfer_no' => $transfer['transfer_no'],
                'status' => 'completed'
            ], 'Transfer completed successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('completeTransfer', $e->getMessage());
            return formatResponse(false, null, 'Failed to complete transfer: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Get transfer details
     * @param int $transferId Transfer ID
     * @return array Response with full transfer details
     */
    public function getTransferDetails($transferId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    st.*,
                    s.first_name, s.last_name, s.admission_no,
                    cc.name as current_class_name,
                    cs.name as current_stream_name,
                    nc.name as new_class_name,
                    ns.name as new_stream_name,
                    req.first_name as requested_by_name,
                    apr.first_name as approved_by_name
                FROM student_transfers st
                JOIN students s ON st.student_id = s.id
                LEFT JOIN classes cc ON st.current_class_id = cc.id
                LEFT JOIN class_streams cs ON st.current_stream_id = cs.id
                LEFT JOIN classes nc ON st.new_class_id = nc.id
                LEFT JOIN class_streams ns ON st.new_stream_id = ns.id
                LEFT JOIN users req ON st.requested_by = req.id
                LEFT JOIN users apr ON st.approved_by = apr.id
                WHERE st.id = ?
            ");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return formatResponse(false, null, 'Transfer not found');
            }

            // Get clearance status
            $clearanceResult = $this->getClearanceStatus($transferId);
            $transfer['clearances'] = $clearanceResult['data'] ?? [];

            return formatResponse(true, $transfer, 'Transfer details retrieved successfully');

        } catch (Exception $e) {
            $this->logError('getTransferDetails', $e->getMessage());
            return formatResponse(false, null, 'Failed to get transfer details: ' . $e->getMessage());
        }
    }

    /**
     * Cancel transfer
     * @param int $transferId Transfer ID
     * @param string $reason Cancellation reason
     * @return array Response
     */
    public function cancelTransfer($transferId, $reason)
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE student_transfers SET status = 'cancelled', approval_notes = ?
                WHERE id = ? AND status NOT IN ('completed', 'rejected')
            ");
            $stmt->execute([$reason, $transferId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Transfer cannot be cancelled (already completed or rejected)');
            }

            $this->db->commit();
            $this->logAction('update', $transferId, "Transfer cancelled: {$reason}");

            return formatResponse(true, ['transfer_id' => $transferId], 'Transfer cancelled successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('cancelTransfer', $e->getMessage());
            return formatResponse(false, null, 'Failed to cancel transfer: ' . $e->getMessage());
        }
    }
}

