<?php
namespace App\API\Services\workflows;

use App\API\Includes\WorkflowHandler;
use Exception;
use PDO;

/**
 * PayrollApprovalWorkflow
 * 
 * Workflow States:
 * 1. draft - HR/Accountant prepares payroll (15th-20th)
 * 2. pending_approval - Submitted for Director review (20th-23rd)
 * 3. approved - Director approves, ready for disbursement (23rd-24th)
 * 4. processing - System is disbursing payments (24th-30th)
 * 5. completed - All payments successful
 * 6. partial - Some payments failed, needs attention
 * 7. rejected - Director rejected payroll
 * 8. cancelled - Payroll cancelled
 */
class PayrollApprovalWorkflow extends WorkflowHandler
{
    protected $states = [
        'draft',
        'pending_approval',
        'approved',
        'processing',
        'completed',
        'partial',
        'rejected',
        'cancelled'
    ];

    protected $transitions = [
        'draft' => ['pending_approval', 'cancelled'],
        'pending_approval' => ['approved', 'rejected', 'draft'],
        'approved' => ['processing', 'cancelled'],
        'processing' => ['completed', 'partial'],
        'partial' => ['processing', 'completed'], // Can retry failed payments
        'rejected' => ['draft'], // Can revise and resubmit
        'cancelled' => []
    ];

    protected $requiredPermissions = [
        'draft' => ['manage_payroll'],
        'pending_approval' => ['manage_payroll'],
        'approved' => ['approve_payroll'],
        'processing' => ['process_disbursements'],
        'cancelled' => ['manage_payroll', 'approve_payroll']
    ];

    /**
     * Initialize payroll
     * HR/Accountant starts creating payroll (15th-20th of month)
     */
    public function initiateDraft($data)
    {
        try {
            $this->db->beginTransaction();

            // Validate payroll data
            $this->validatePayrollData($data);

            // Create payroll record
            $payrollId = $this->db->insert('payrolls', [
                'month' => $data['month'],
                'year' => $data['year'],
                'total_gross' => 0,
                'total_deductions' => 0,
                'total_net' => 0,
                'status' => 'draft',
                'created_by' => $data['created_by'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Create workflow instance
            $this->createWorkflowInstance($payrollId, 'payroll', 'draft', $data['created_by']);

            // Calculate staff payments
            $this->calculateStaffPayments($payrollId, $data);

            $this->db->commit();

            $this->log("Payroll draft created", $payrollId, 'draft');

            return [
                'success' => true,
                'payroll_id' => $payrollId,
                'status' => 'draft',
                'message' => 'Payroll draft created successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Submit for approval
     * Accountant submits payroll to Director (around 20th)
     */
    public function submitForApproval($payrollId, $userId)
    {
        try {
            $this->validateTransition($payrollId, 'draft', 'pending_approval', $userId);

            // Validate all staff payments calculated
            $this->validatePayrollComplete($payrollId);

            // Update status
            $this->transition($payrollId, 'pending_approval', $userId, [
                'submitted_at' => date('Y-m-d H:i:s'),
                'submitted_by' => $userId
            ]);

            // Notify Director
            $this->notifyApprovers($payrollId);

            $this->log("Payroll submitted for approval", $payrollId, 'pending_approval');

            return [
                'success' => true,
                'status' => 'pending_approval',
                'message' => 'Payroll submitted to Director for approval'
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Director approves payroll
     * Director reviews and approves (20th-24th)
     */
    public function approve($payrollId, $userId, $comments = '')
    {
        try {
            $this->validateTransition($payrollId, 'pending_approval', 'approved', $userId);

            // Final validation before approval
            $this->validatePayrollForApproval($payrollId);

            // Update status
            $this->transition($payrollId, 'approved', $userId, [
                'approved_at' => date('Y-m-d H:i:s'),
                'approved_by' => $userId,
                'approval_comments' => $comments
            ]);

            $this->log("Payroll approved by Director", $payrollId, 'approved');

            return [
                'success' => true,
                'status' => 'approved',
                'message' => 'Payroll approved. Ready for disbursement.',
                'next_action' => 'Process disbursement between 24th-30th'
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Director rejects payroll
     * Send back to HR for correction
     */
    public function reject($payrollId, $userId, $reason)
    {
        try {
            $this->validateTransition($payrollId, 'pending_approval', 'rejected', $userId);

            $this->transition($payrollId, 'rejected', $userId, [
                'rejected_at' => date('Y-m-d H:i:s'),
                'rejected_by' => $userId,
                'rejection_reason' => $reason
            ]);

            // Notify HR/Accountant
            $this->notifyCreator($payrollId, $reason);

            $this->log("Payroll rejected: $reason", $payrollId, 'rejected');

            return [
                'success' => true,
                'status' => 'rejected',
                'message' => 'Payroll rejected and returned for correction'
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Start disbursement process
     * Triggered between 24th-30th of month
     */
    public function startDisbursement($payrollId, $userId)
    {
        try {
            $this->validateTransition($payrollId, 'approved', 'processing', $userId);

            // Check if within disbursement window (24th-30th)
            $currentDay = (int) date('d');
            if ($currentDay < 24) {
                throw new Exception("Disbursement can only be processed from 24th-30th of the month");
            }

            // Update status to processing
            $this->transition($payrollId, 'processing', $userId, [
                'disbursement_started_at' => date('Y-m-d H:i:s'),
                'disbursement_initiated_by' => $userId
            ]);

            $this->log("Payroll disbursement started", $payrollId, 'processing');

            // Trigger actual disbursement via DisbursementManager
            // This is called externally by the disbursement system

            return [
                'success' => true,
                'status' => 'processing',
                'message' => 'Disbursement started. Processing payments...'
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Mark as completed
     * All payments successful
     */
    public function markCompleted($payrollId, $userId)
    {
        try {
            $this->validateTransition($payrollId, 'processing', 'completed', $userId);

            // Verify all payments are successful
            $failedCount = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM staff_payments 
                 WHERE payroll_id = ? AND status = 'failed'",
                [$payrollId]
            );

            if ($failedCount > 0) {
                throw new Exception("Cannot mark as completed. $failedCount payments failed.");
            }

            $this->transition($payrollId, 'completed', $userId, [
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            $this->log("Payroll completed successfully", $payrollId, 'completed');

            return [
                'success' => true,
                'status' => 'completed',
                'message' => 'All payments completed successfully'
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Mark as partial (some payments failed)
     */
    public function markPartial($payrollId, $userId, $failedCount)
    {
        try {
            $this->validateTransition($payrollId, 'processing', 'partial', $userId);

            $this->transition($payrollId, 'partial', $userId, [
                'partial_marked_at' => date('Y-m-d H:i:s'),
                'failed_payment_count' => $failedCount
            ]);

            $this->log("Payroll marked as partial. $failedCount payments failed.", $payrollId, 'partial');

            return [
                'success' => true,
                'status' => 'partial',
                'message' => "$failedCount payments failed. Requires retry.",
                'failed_count' => $failedCount
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Calculate staff payments for payroll
     */
    private function calculateStaffPayments($payrollId, $data)
    {
        // Get all active staff
        $staff = $this->db->fetchAll(
            "SELECT * FROM staff WHERE status = 'active'"
        );

        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        foreach ($staff as $member) {
            // Calculate salary components
            $basicSalary = $member['basic_salary'];
            $allowances = $this->calculateAllowances($member);
            $grossSalary = $basicSalary + $allowances;

            // Calculate deductions
            $deductions = $this->calculateDeductions($member, $grossSalary);
            $netSalary = $grossSalary - $deductions;

            // Insert staff payment record
            $this->db->insert('staff_payments', [
                'payroll_id' => $payrollId,
                'staff_id' => $member['id'],
                'basic_salary' => $basicSalary,
                'allowances' => $allowances,
                'gross_salary' => $grossSalary,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $totalGross += $grossSalary;
            $totalDeductions += $deductions;
            $totalNet += $netSalary;
        }

        // Update payroll totals
        $this->db->query(
            "UPDATE payrolls 
             SET total_gross = ?, total_deductions = ?, total_net = ?
             WHERE id = ?",
            [$totalGross, $totalDeductions, $totalNet, $payrollId]
        );
    }

    /**
     * Calculate allowances (transport, housing, etc.)
     */
    private function calculateAllowances($staff)
    {
        // This would pull from staff allowances table
        return $staff['total_allowances'] ?? 0;
    }

    /**
     * Calculate deductions (NSSF, NHIF, PAYE, loans)
     */
    private function calculateDeductions($staff, $grossSalary)
    {
        $deductions = 0;

        // NSSF (mock calculation)
        $deductions += min($grossSalary * 0.06, 1080); // 6% capped at 1080

        // NHIF (mock calculation based on gross)
        $deductions += $this->calculateNHIF($grossSalary);

        // PAYE (mock calculation)
        $deductions += $this->calculatePAYE($grossSalary);

        // Loans and advances
        $deductions += $staff['loan_deduction'] ?? 0;

        return $deductions;
    }

    /**
     * Calculate NHIF based on salary bands
     */
    private function calculateNHIF($gross)
    {
        if ($gross <= 5999)
            return 150;
        if ($gross <= 7999)
            return 300;
        if ($gross <= 11999)
            return 400;
        if ($gross <= 14999)
            return 500;
        if ($gross <= 19999)
            return 600;
        if ($gross <= 24999)
            return 750;
        if ($gross <= 29999)
            return 850;
        if ($gross <= 34999)
            return 900;
        if ($gross <= 39999)
            return 950;
        if ($gross <= 44999)
            return 1000;
        if ($gross <= 49999)
            return 1100;
        if ($gross <= 59999)
            return 1200;
        if ($gross <= 69999)
            return 1300;
        if ($gross <= 79999)
            return 1400;
        if ($gross <= 89999)
            return 1500;
        if ($gross <= 99999)
            return 1600;
        return 1700;
    }

    /**
     * Calculate PAYE (simplified)
     */
    private function calculatePAYE($gross)
    {
        $taxable = $gross - 2400; // Personal relief
        if ($taxable <= 0)
            return 0;

        $tax = 0;
        if ($taxable <= 24000) {
            $tax = $taxable * 0.10;
        } elseif ($taxable <= 32333) {
            $tax = 2400 + (($taxable - 24000) * 0.25);
        } else {
            $tax = 2400 + 2083.25 + (($taxable - 32333) * 0.30);
        }

        return max(0, $tax - 2400); // Deduct personal relief
    }

    /**
     * Validate payroll data
     */
    private function validatePayrollData($data)
    {
        if (empty($data['month']) || empty($data['year'])) {
            throw new Exception("Month and year are required");
        }

        // Check if payroll already exists for this month/year
        $exists = $this->db->fetchOne(
            "SELECT id FROM payrolls WHERE month = ? AND year = ? AND status != 'cancelled'",
            [$data['month'], $data['year']]
        );

        if ($exists) {
            throw new Exception("Payroll already exists for this period");
        }
    }

    /**
     * Validate payroll is complete before submission
     */
    private function validatePayrollComplete($payrollId)
    {
        $paymentCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM staff_payments WHERE payroll_id = ?",
            [$payrollId]
        );

        if ($paymentCount === 0) {
            throw new Exception("No staff payments found. Cannot submit empty payroll.");
        }
    }

    /**
     * Validate payroll before approval
     */
    private function validatePayrollForApproval($payrollId)
    {
        $payroll = $this->db->fetchOne(
            "SELECT total_net FROM payrolls WHERE id = ?",
            [$payrollId]
        );

        if ($payroll['total_net'] <= 0) {
            throw new Exception("Invalid payroll total. Cannot approve.");
        }
    }

    // ============================================================================
    // ABSTRACT METHOD IMPLEMENTATIONS (Required by WorkflowBase)
    // ============================================================================

    /**
     * Validate transition between workflow stages
     * 
     * @param string $fromStage Current stage
     * @param string $toStage Target stage
     * @param array $data Transition data
     * @throws Exception if transition is invalid
     * @return bool
     */
    protected function validateTransition($fromStage, $toStage, $data)
    {
        // Check if transition is allowed
        if (!isset($this->transitions[$fromStage]) || !in_array($toStage, $this->transitions[$fromStage])) {
            throw new Exception("Invalid transition from {$fromStage} to {$toStage}");
        }

        $payrollId = $data['payroll_id'] ?? null;
        if (!$payrollId) {
            throw new Exception("Payroll ID is required for workflow transition");
        }

        // Validate specific transitions
        switch ($toStage) {
            case 'pending_approval':
                // Validate payroll is complete before submission
                $this->validatePayrollComplete($payrollId);
                break;

            case 'approved':
                // Validate payroll before approval
                $this->validatePayrollForApproval($payrollId);
                break;

            case 'processing':
                // Ensure payroll is approved
                $sql = "SELECT status FROM payrolls WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$payroll || $payroll['status'] !== 'approved') {
                    throw new Exception("Payroll must be approved before processing");
                }
                break;

            case 'completed':
                // Verify all payments are successful
                $sql = "SELECT COUNT(*) FROM staff_payments WHERE payroll_id = ? AND payment_status = 'failed'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $failed = $stmt->fetchColumn();

                if ($failed > 0) {
                    throw new Exception("Cannot mark as completed. {$failed} payments failed.");
                }
                break;

            case 'partial':
                // Verify some payments failed
                $sql = "SELECT COUNT(*) FROM staff_payments WHERE payroll_id = ? AND payment_status = 'failed'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $failed = $stmt->fetchColumn();

                if ($failed === 0) {
                    throw new Exception("No failed payments. Use 'completed' status instead.");
                }
                break;

            case 'rejected':
                // Ensure rejection reason is provided
                if (empty($data['reason'])) {
                    throw new Exception("Rejection reason is required");
                }
                break;

            case 'cancelled':
                // Can only cancel from draft or rejected state
                if (!in_array($fromStage, ['draft', 'pending_approval', 'approved'])) {
                    throw new Exception("Cannot cancel payroll in {$fromStage} state");
                }
                break;
        }

        return true;
    }

    /**
     * Process stage-specific actions when entering a new stage
     * 
     * @param string $stage Stage being entered
     * @param array $data Stage processing data
     * @return void
     */
    protected function processStage($stage, $data)
    {
        $payrollId = $data['payroll_id'] ?? null;
        $userId = $data['user_id'] ?? null;

        if (!$payrollId) {
            $this->logError("No payroll_id provided for stage processing", $stage);
            return;
        }

        // Execute stage-specific processing
        switch ($stage) {
            case 'draft':
                // Initialize payroll draft
                $sql = "UPDATE payrolls SET status = 'draft', updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $this->logAction("Payroll draft initialized", "Initialized payroll #{$payrollId}", $userId);
                break;

            case 'pending_approval':
                // Mark as pending approval
                $sql = "UPDATE payrolls SET status = 'pending_approval', submitted_at = NOW(), submitted_by = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$userId, $payrollId]);
                $this->logAction("Payroll submitted for approval", "Payroll #{$payrollId} submitted for Director approval", $userId);

                // Send notification to Director
                $this->sendNotificationToDirector($payrollId);
                break;

            case 'approved':
                // Mark as approved
                $sql = "UPDATE payrolls SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$userId, $payrollId]);
                $this->logAction("Payroll approved by Director", "Payroll #{$payrollId} approved and ready for processing", $userId);

                // Notify HR/Accountant
                $this->sendApprovalNotification($payrollId);
                break;

            case 'rejected':
                // Mark as rejected
                $reason = $data['reason'] ?? 'No reason provided';
                $sql = "UPDATE payrolls SET status = 'rejected', rejected_at = NOW(), rejected_by = ?, rejection_reason = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$userId, $reason, $payrollId]);
                $this->logAction("Payroll rejected", "Payroll #{$payrollId} rejected. Reason: {$reason}", $userId);

                // Notify creator
                $this->sendRejectionNotification($payrollId, $reason);
                break;

            case 'processing':
                // Mark as processing
                $sql = "UPDATE payrolls SET status = 'processing', processing_started_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $this->logAction("Payroll disbursement started", "Disbursement process started for payroll #{$payrollId}", $userId);
                break;

            case 'completed':
                // Mark as completed
                $sql = "UPDATE payrolls SET status = 'completed', completed_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $this->logAction("Payroll completed successfully", "All payments for payroll #{$payrollId} completed successfully", $userId);

                // Send completion notifications
                $this->sendCompletionNotification($payrollId);
                break;

            case 'partial':
                // Mark as partial
                $sql = "SELECT COUNT(*) FROM staff_payments WHERE payroll_id = ? AND payment_status = 'failed'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $failedCount = $stmt->fetchColumn();

                $sql = "UPDATE payrolls SET status = 'partial', completed_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $this->logAction("Payroll partially completed", "Payroll #{$payrollId} partially completed. {$failedCount} payments failed", $userId);

                // Send alert about failed payments
                $this->sendPartialCompletionAlert($payrollId, $failedCount);
                break;

            case 'cancelled':
                // Mark as cancelled
                $sql = "UPDATE payrolls SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$userId, $payrollId]);
                $this->logAction("Payroll cancelled", "Payroll #{$payrollId} cancelled", $userId);
                break;
        }
    }

    /**
     * Send notification to Director for approval
     */
    private function sendNotificationToDirector($payrollId)
    {
        // Implementation would send email/SMS to Director
        $this->logAction("Notification sent", "Approval notification sent to Director for payroll #{$payrollId}", null);
    }

    /**
     * Send approval notification
     */
    private function sendApprovalNotification($payrollId)
    {
        // Implementation would notify HR/Accountant
        $this->logAction("Notification sent", "Approval notification sent to HR/Accountant for payroll #{$payrollId}", null);
    }

    /**
     * Send rejection notification
     */
    private function sendRejectionNotification($payrollId, $reason)
    {
        // Implementation would notify creator with reason
        $this->logAction("Notification sent", "Rejection notification sent for payroll #{$payrollId}. Reason: {$reason}", null);
    }

    /**
     * Send completion notification
     */
    private function sendCompletionNotification($payrollId)
    {
        // Implementation would notify all relevant parties
        $this->logAction("Notification sent", "Completion notification sent for payroll #{$payrollId}", null);
    }

    /**
     * Send partial completion alert
     */
    private function sendPartialCompletionAlert($payrollId, $failedCount)
    {
        // Implementation would alert about failed payments
        $this->logAction("Alert sent", "Partial completion alert sent for payroll #{$payrollId}. {$failedCount} payments failed", null);
    }

    /**
     * Helper: Create workflow instance (maps to startWorkflow)
     */
    private function createWorkflowInstance($payrollId, $type, $stage, $userId)
    {
        // Note: WorkflowHandler doesn't use startWorkflow for this pattern
        // Just log the creation
        $this->logAction('workflow_created', $payrollId, "Workflow instance created for payroll #{$payrollId}");
    }

    /**
     * Helper: Transition workflow (simplified - just update DB)
     */
    private function transition($payrollId, $toStage, $userId, $data = [])
    {
        // Update payroll status in database
        $this->db->update('payrolls', ['status' => $toStage], ['id' => $payrollId]);

        // Log the transition
        $this->logAction('transition', $payrollId, "Transitioned to {$toStage}");
    }

    /**
     * Helper: Log action (maps to logAction)
     */
    private function log($message, $payrollId, $stage)
    {
        $this->logAction($stage, $payrollId, $message);
    }

    /**
     * Helper: Notify approvers
     */
    private function notifyApprovers($payrollId)
    {
        $this->sendApprovalNotification($payrollId);
    }

    /**
     * Helper: Notify creator
     */
    private function notifyCreator($payrollId, $reason)
    {
        $this->sendRejectionNotification($payrollId, $reason);
    }
}
