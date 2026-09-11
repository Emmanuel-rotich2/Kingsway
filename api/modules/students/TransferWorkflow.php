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
 * Implements the 6-stage transfer workflow on the normalized schema:
 * transfer requests live in `student_transitions` (transition_type =
 * 'transfer'/'internal'/'graduation'); per-department clearances live in
 * `student_clearances` keyed by `transfer_request_id` + `clearance_type`.
 * The retired `student_transfers` / `clearance_departments` tables do not
 * exist; `student_transitions` carries no status column, so workflow state is
 * derived (pending until executed_at is set).
 */
class TransferWorkflow extends WorkflowHandler
{
    private $workflowCode = 'student_transfer';

    private function clearanceTypes(): array
    {
        return ['finance', 'library', 'uniform', 'property', 'academic'];
    }

    private function typeForCode(string $code): ?string
    {
        $type = strtolower(trim($code));
        return in_array($type, $this->clearanceTypes(), true) ? $type : null;
    }

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

            $this->db->beginTransaction();

            // Get student current information
            $stmt = $this->db->prepare("
                SELECT s.*, per.first_name, per.last_name, per.middle_name,
                       ayc.class_id AS class_id, sm.name as stream_name, c.name as class_name,
                       aycs.id AS academic_year_class_stream_id
                FROM students s
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN academic_years ay ON ay.is_current = 1
                LEFT JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.academic_year_id = ay.id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN streams sm ON sm.id = aycs.stream_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                WHERE s.id = ?
            ");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Student not found');
            }

            // Check if student already has a pending transfer
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM student_transitions
                WHERE student_id = ? AND transition_type IN ('transfer', 'internal', 'graduation')
                  AND executed_at IS NULL
            ");
            $stmt->execute([$data['student_id']]);
            if ($stmt->fetchColumn() > 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Student already has a pending transfer request');
            }

            $transitionType = $data['transfer_type'] === 'internal' ? 'internal' : ($data['transfer_type'] === 'graduation' ? 'graduation' : 'transfer');

            $currentUserId = $this->getCurrentUserId();

            // Create the transition (transfer request)
            $stmt = $this->db->prepare("
                INSERT INTO student_transitions (student_id, academic_year_id, transition_type, reason, decided_by, decided_at)
                SELECT ?, ay.id, ?, ?, ?, ?
                FROM academic_years ay
                WHERE ay.is_current = 1
                LIMIT 1
            ");
            $stmt->execute([
                $data['student_id'],
                $transitionType,
                $data['transfer_reason'],
                $currentUserId,
                $data['request_date'],
            ]);
            $transferId = (int) $this->db->lastInsertId();

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No active academic year is configured');
            }

            // Initialize clearance records for all departments
            $stmt = $this->db->prepare("
                INSERT INTO student_clearances (student_id, transfer_request_id, clearance_type, status)
                VALUES (?, ?, ?, 'pending')
            ");
            foreach ($this->clearanceTypes() as $type) {
                $stmt->execute([$data['student_id'], $transferId, $type]);
            }

            $this->db->commit();
            $this->logAction('create', $transferId, "Transfer request initiated for student {$student['first_name']} {$student['last_name']} - Type: {$data['transfer_type']}");

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'transfer_no' => $transferId,
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'status' => 'pending_clearance'
            ], 'Transfer request initiated successfully. Clearance process has started.');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('initiateTransfer', $e->getMessage());
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
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
                    sc.clearance_type AS dept_code,
                    sc.clearance_type AS dept_name,
                    NULL AS dept_description,
                    NULL AS is_mandatory,
                    p.first_name as cleared_by_name
                FROM student_clearances sc
                LEFT JOIN users u ON sc.checked_by = u.id
                LEFT JOIN persons p ON p.id = u.person_id
                WHERE sc.transfer_request_id = ?
                ORDER BY sc.id
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
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
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
            $type = $this->typeForCode((string) $departmentCode);
            if (!$type) {
                return formatResponse(false, null, 'Department not found or inactive');
            }

            $this->db->beginTransaction();

            // Get student_id from transfer
            $stmt = $this->db->prepare("SELECT student_id FROM student_transitions WHERE id = ?");
            $stmt->execute([$transferId]);
            $studentId = $stmt->fetchColumn();

            if (!$studentId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Transfer not found');
            }

            $hasIssues = false;
            $issueDescription = null;
            $outstandingAmount = 0.00;

            // Automated finance check
            if ($type === 'finance') {
                $stmt = $this->db->prepare("CALL sp_check_finance_clearance(?, @is_cleared, @outstanding, @description)");
                $stmt->execute([$studentId]);

                $result = $this->db->query("SELECT @is_cleared as is_cleared, @outstanding as outstanding, @description as description")->fetch(PDO::FETCH_ASSOC);

                $hasIssues = !$result['is_cleared'];
                $issueDescription = $result['description'];
                $outstandingAmount = (float) ($result['outstanding'] ?? 0);
            }

            // Get clearance record
            $stmt = $this->db->prepare("
                SELECT * FROM student_clearances
                WHERE transfer_request_id = ? AND clearance_type = ?
            ");
            $stmt->execute([$transferId, $type]);
            $clearance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$clearance) {
                // Create if not exists
                $stmt = $this->db->prepare("
                    INSERT INTO student_clearances (student_id, transfer_request_id, clearance_type, status)
                    VALUES (?, ?, ?, 'pending')
                ");
                $stmt->execute([$studentId, $transferId, $type]);
                $clearanceId = (int) $this->db->lastInsertId();
            } else {
                $clearanceId = (int) $clearance['id'];
            }

            // Update clearance status
            $status = $data['status'] ?? ($hasIssues ? 'blocked' : 'cleared');
            if (!in_array($status, ['cleared', 'blocked', 'pending'], true)) {
                $status = $hasIssues ? 'blocked' : 'cleared';
            }
            $currentUserId = $this->getCurrentUserId();
            $notes = $data['resolution_notes'] ?? $data['issue_description'] ?? $issueDescription;

            $stmt = $this->db->prepare("
                UPDATE student_clearances SET
                    status = ?,
                    checked_by = ?,
                    checked_at = NOW(),
                    amount_outstanding = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $status,
                $currentUserId,
                (float) ($data['outstanding_amount'] ?? $outstandingAmount),
                $notes,
                $clearanceId,
            ]);

            // Check if all clearances are done
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'cleared' THEN 1 ELSE 0 END) as cleared,
                       SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked
                FROM student_clearances
                WHERE transfer_request_id = ?
            ");
            $stmt->execute([$transferId]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            $allCleared = ((int) $summary['total'] > 0 && (int) $summary['cleared'] === (int) $summary['total']);

            $this->db->commit();
            $this->logAction('update', $transferId, "Clearance processed for {$type} - Status: {$status}");

            return formatResponse(true, [
                'clearance_id' => $clearanceId,
                'status' => $status,
                'has_issues' => $status === 'blocked',
                'all_cleared' => $allCleared
            ], "Clearance for {$type} processed successfully");

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('processClearance', $e->getMessage());
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
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
            $stmt = $this->db->prepare("SELECT student_id FROM student_transitions WHERE id = ?");
            $stmt->execute([$transferId]);
            $studentId = $stmt->fetchColumn();

            if (!$studentId) {
                return formatResponse(false, null, 'Transfer not found');
            }

            // Check finance clearance
            $stmt = $this->db->prepare("CALL sp_check_finance_clearance(?, @is_cleared, @outstanding, @description)");
            $stmt->execute([$studentId]);

            $result = $this->db->query("SELECT @is_cleared as is_cleared, @outstanding as outstanding, @description as description")->fetch(PDO::FETCH_ASSOC);

            $isSettled = (bool) $result['is_cleared'];
            $outstandingAmount = $result['outstanding'];

            if ($isSettled) {
                $this->logAction('update', $transferId, 'Fee settlement verified - Moving to approval stage');
            }

            return formatResponse(true, [
                'is_settled' => $isSettled,
                'outstanding_amount' => $outstandingAmount,
                'description' => $result['description']
            ], $isSettled ? 'All fees settled' : 'Outstanding fees found');

        } catch (Exception $e) {
            $this->logError('verifyFeeSettlement', $e->getMessage());
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
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

            if ($data['decision'] === 'approved') {
                $stmt = $this->db->prepare("
                    UPDATE student_transitions
                    SET decided_by = ?, decided_at = NOW(), executed_at = NOW()
                    WHERE id = ? AND executed_at IS NULL
                ");
                $stmt->execute([$currentUserId, $transferId]);

                if ($stmt->rowCount() === 0) {
                    $this->db->rollBack();
                    return formatResponse(false, null, 'Transfer request not found or already processed');
                }
            }

            $this->db->commit();
            $this->logAction('update', $transferId, "Transfer {$data['decision']} by user {$currentUserId}");

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'status' => $newStatus,
                'decision' => $data['decision']
            ], "Transfer {$data['decision']} successfully");

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('approveTransfer', $e->getMessage());
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    // ========================================================================
    // STAGE 5: DOCUMENT PREPARATION (Stub - Full implementation in DocumentGenerator)
    // ========================================================================

    /**
     * Mark documents as ready (actual generation happens in DocumentGenerator module).
     * The normalized `student_transitions` table has no certificate columns; the
     * certificate number is derived and returned without a schema write.
     * @param int $transferId Transfer ID
     * @param array $data Document paths
     * @return array Response
     */
    public function markDocumentsReady($transferId, $data)
    {
        try {
            $year = date('Y');
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM student_transitions
                WHERE transition_type = 'transfer' AND YEAR(decided_at) = ?
            ");
            $stmt->execute([$year]);
            $count = (int) $stmt->fetchColumn();
            $certNo = sprintf('LC-%d-%04d', $year, $count + 1);

            $this->logAction('update', $transferId, 'Transfer documents marked as ready');

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'leaving_certificate_no' => $certNo
            ], 'Documents marked as ready');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('markDocumentsReady', $e->getMessage());
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
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
            $stmt = $this->db->prepare("SELECT * FROM student_transitions WHERE id = ?");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Transfer not found');
            }

            if ($transfer['executed_at'] === null) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Transfer must be approved before completion');
            }

            // Finalize: mark student + enrollment as transferred (normalized proc)
            $stmt = $this->db->prepare("CALL sp_initialize_transfer_clearances(?)");
            $stmt->execute([$transferId]);

            $this->db->commit();
            $this->logAction('update', $transferId, 'Transfer completed successfully');

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'transfer_no' => $transfer['id'],
                'status' => 'completed'
            ], 'Transfer completed successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('completeTransfer', $e->getMessage());
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
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
                    tr.id,
                    tr.id AS transfer_no,
                    tr.student_id,
                    tr.transition_type AS transfer_type,
                    tr.reason AS transfer_reason,
                    tr.decided_at AS request_date,
                    tr.executed_at AS approval_date,
                    per.first_name, per.last_name, s.admission_no,
                    ay.year_code AS academic_year,
                    ayc.class_id AS current_class_id,
                    c.name as current_class_name,
                    sm.name as current_stream_name,
                    NULL AS new_class_name,
                    NULL AS new_stream_name,
                    rq.first_name AS requested_by_name,
                    ap.first_name AS approved_by_name,
                    CASE WHEN tr.executed_at IS NOT NULL THEN 'completed' ELSE 'pending' END AS status
                FROM student_transitions tr
                JOIN students s ON tr.student_id = s.id
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN academic_years ay ON ay.id = tr.academic_year_id
                LEFT JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.academic_year_id = tr.academic_year_id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams sm ON sm.id = aycs.stream_id
                LEFT JOIN users ur ON tr.decided_by = ur.id
                LEFT JOIN persons rq ON rq.id = ur.person_id
                LEFT JOIN users ua ON tr.decided_by = ua.id
                LEFT JOIN persons ap ON ap.id = ua.person_id
                WHERE tr.id = ?
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
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
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
                DELETE FROM student_clearances
                WHERE transfer_request_id = ? AND transfer_request_id IN (
                    SELECT id FROM student_transitions WHERE executed_at IS NULL
                )
            ");
            $stmt->execute([$transferId]);

            $stmt = $this->db->prepare("
                DELETE FROM student_transitions
                WHERE id = ? AND executed_at IS NULL
            ");
            $stmt->execute([$transferId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Transfer cannot be cancelled (already completed or rejected)');
            }

            $this->db->commit();
            $this->logAction('update', $transferId, "Transfer cancelled: {$reason}");

            return formatResponse(true, ['transfer_id' => $transferId], 'Transfer cancelled successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('cancelTransfer', $e->getMessage());
            \App\API\Services\Logger::legacyError('[TransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }
}
