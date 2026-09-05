<?php
namespace App\API\Services\workflows;

use App\API\Includes\WorkflowHandler;
use App\API\Services\DataScopeService;
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

            // Create payroll record (3NF: payroll_runs)
            $payrollId = $this->db->insert('payroll_runs', [
                'month' => $data['month'],
                'year' => $data['year'],
                'data_scope' => DataScopeService::current(),
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
            $this->validateTransition('draft', 'pending_approval', ['payroll_id' => $payrollId, 'user_id' => $userId]);

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
            $this->validateTransition('pending_approval', 'approved', ['payroll_id' => $payrollId, 'user_id' => $userId]);

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
            $this->validateTransition('pending_approval', 'rejected', ['payroll_id' => $payrollId, 'user_id' => $userId, 'reason' => $reason]);

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
            $this->validateTransition('approved', 'processing', ['payroll_id' => $payrollId, 'user_id' => $userId]);

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
            $this->validateTransition('processing', 'completed', ['payroll_id' => $payrollId, 'user_id' => $userId]);

            // Verify all payments are successful (3NF: payslips.payment_status)
            $failedCount = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM payslips ps
                 JOIN payroll_runs pr ON pr.id = ?
                 WHERE ps.payroll_month = pr.month AND ps.payroll_year = pr.year
                   AND ps.data_scope COLLATE utf8mb4_unicode_ci = pr.data_scope COLLATE utf8mb4_unicode_ci
                   AND ps.payment_status = 'failed'",
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
            $this->validateTransition('processing', 'partial', ['payroll_id' => $payrollId, 'user_id' => $userId]);

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
        // Get all active staff (3NF: staff JOIN persons for name + payroll_profiles for salary)
        $staff = $this->db->fetchAll(
            "SELECT st.*, p.first_name, p.last_name, spp.basic_salary
             FROM staff st
             JOIN persons p ON p.id = st.person_id
             LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = st.id
             WHERE st.status = 'active' AND st.data_scope = ?",
            [DataScopeService::current()]
        );

        foreach ($staff as $member) {
            // Calculate salary components
            $basicSalary = (float) ($member['basic_salary'] ?? 0);
            $allowances = $this->calculateAllowances($member);
            $grossSalary = $basicSalary + $allowances;

            // Calculate deductions
            $periodDate = sprintf('%04d-%02d-01', (int)$data['year'], (int)$data['month']);
            $deductions = $this->calculateDeductions($member, $grossSalary, $periodDate);
            $netSalary = $grossSalary - $deductions;

            // Insert staff payment record (3NF: payslips)
            $this->db->insert('payslips', [
                'staff_id' => $member['id'],
                'payroll_month' => $data['month'],
                'payroll_year' => $data['year'],
                'basic_salary' => $basicSalary,
                'allowances_total' => $allowances,
                'gross_salary' => $grossSalary,
                'other_deductions_total' => $deductions,
                'net_salary' => $netSalary,
                'payslip_status' => 'draft',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
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
    private function calculateDeductions($staff, $grossSalary, $periodDate = null)
    {
        $nssf = $this->calculateNSSF($grossSalary, $periodDate);
        $shif = $this->calculateSHIF($grossSalary, $periodDate);
        $housing = $this->calculateHousingLevy($grossSalary, $periodDate);
        $paye = $this->calculatePAYE(max(0, $grossSalary - $nssf - $shif - $housing), $periodDate);
        $deductions = $nssf + $shif + $housing + $paye;

        // Loans and advances
        $deductions += $staff['loan_deduction'] ?? 0;

        return $deductions;
    }

    private function activeStatutoryRule(string $agency, string $ruleCode, $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: date('Y-m-d');
        $stmt = $this->db->prepare("SELECT id,calculation_method,employee_rate,employer_rate,
            lower_earnings_limit,upper_earnings_limit,cap_amount,personal_relief,deadline_day,deadline_basis
            FROM statutory_rule_versions
            WHERE agency=? AND rule_code=? AND active=1 AND effective_from<=?
            AND (effective_to IS NULL OR effective_to>=?)
            ORDER BY effective_from DESC, id DESC LIMIT 1");
        $stmt->execute([$agency, $ruleCode, $asOfDate, $asOfDate]);
        $rules = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rules) return [];
        $rules['employee_rate'] = $rules['employee_rate'] === null ? null : (float)$rules['employee_rate'];
        $rules['employer_rate'] = $rules['employer_rate'] === null ? null : (float)$rules['employer_rate'];
        $rules['lower_earnings_limit'] = $rules['lower_earnings_limit'] === null ? null : (float)$rules['lower_earnings_limit'];
        $rules['upper_earnings_limit'] = $rules['upper_earnings_limit'] === null ? null : (float)$rules['upper_earnings_limit'];
        $rules['personal_relief'] = $rules['personal_relief'] === null ? null : (float)$rules['personal_relief'];
        $bandStmt = $this->db->prepare('SELECT upper_bound up_to,tax_rate rate FROM statutory_tax_bands WHERE rule_version_id=? ORDER BY band_order');
        $bandStmt->execute([(int)$rules['id']]);
        $rules['bands'] = array_map(static function (array $band): array {
            return ['up_to' => $band['up_to'] === null ? null : (float)$band['up_to'], 'rate' => (float)$band['rate']];
        }, $bandStmt->fetchAll(PDO::FETCH_ASSOC));
        return $rules;
    }

    private function calculateNSSF($gross, $asOfDate = null)
    {
        $rule = $this->activeStatutoryRule('NSSF', 'employee_employer_contribution', $asOfDate);
        $rate = (float)($rule['employee_rate'] ?? 0);
        $lower = (float)($rule['lower_earnings_limit'] ?? 0);
        $upper = (float)($rule['upper_earnings_limit'] ?? 0);
        if ($rate <= 0 || $upper <= 0) return 0;
        return round(max(0, min($gross, $upper) - min($gross, $lower)) * $rate / 100
            + min($gross, $lower) * $rate / 100, 2);
    }

    private function calculateSHIF($gross, $asOfDate = null)
    {
        $rule = $this->activeStatutoryRule('SHIF', 'employee_contribution', $asOfDate);
        $amount = round(max(0, $gross) * ((float)($rule['employee_rate'] ?? 0)) / 100, 2);
        return $rule['cap_amount'] !== null ? min($amount, (float)$rule['cap_amount']) : $amount;
    }

    private function calculateHousingLevy($gross, $asOfDate = null)
    {
        $rule = $this->activeStatutoryRule('Housing Levy', 'employee_employer_contribution', $asOfDate);
        $amount = round(max(0, $gross) * ((float)($rule['employee_rate'] ?? 0)) / 100, 2);
        return $rule['cap_amount'] !== null ? min($amount, (float)$rule['cap_amount']) : $amount;
    }

    private function calculatePAYE($gross, $asOfDate = null)
    {
        $rule = $this->activeStatutoryRule('KRA', 'paye_bands', $asOfDate);
        $bands = is_array($rule['bands'] ?? null) ? $rule['bands'] : [];
        $remaining = max(0, (float)$gross);
        $previous = 0.0;
        $tax = 0.0;
        foreach ($bands as $band) {
            $limit = isset($band['up_to']) && $band['up_to'] !== null ? (float)$band['up_to'] : INF;
            $portion = min($remaining, max(0, $limit - $previous));
            $tax += $portion * ((float)($band['rate'] ?? 0)) / 100;
            $remaining -= $portion;
            $previous = $limit;
            if ($remaining <= 0) break;
        }
        return round(max(0, $tax - (float)($rule['personal_relief'] ?? 0)), 2);
    }

    /**
     * Validate payroll data
     */
    private function validatePayrollData($data)
    {
        if (empty($data['month']) || empty($data['year'])) {
            throw new Exception("Month and year are required");
        }

        // Check if payroll already exists for this month/year (3NF: payroll_runs)
        $exists = $this->db->fetchOne(
            "SELECT id FROM payroll_runs WHERE month = ? AND year = ? AND data_scope=? AND workflow != 'cancelled'",
            [$data['month'], $data['year'], DataScopeService::current()]
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
        // 3NF: check payslips for the payroll run month/year
        $run = $this->db->fetchOne("SELECT month, year, data_scope FROM payroll_runs WHERE id = ? AND data_scope=?", [$payrollId, DataScopeService::current()]);
        if (!$run) throw new Exception('Payroll not found in the active workspace');
        $paymentCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM payslips WHERE payroll_month = ? AND payroll_year = ? AND data_scope=?",
            [$run['month'] ?? 0, $run['year'] ?? 0, $run['data_scope']]
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
        // 3NF: payroll_runs doesn't store totals directly — derive from payslips
        $totals = $this->db->fetchOne(
            "SELECT COALESCE(SUM(ps.net_salary), 0) AS total_net
             FROM payslips ps
             JOIN payroll_runs pr ON pr.id = ?
             WHERE ps.payroll_month = pr.month AND ps.payroll_year = pr.year
               AND pr.data_scope=? AND ps.data_scope COLLATE utf8mb4_unicode_ci = pr.data_scope COLLATE utf8mb4_unicode_ci",
            [$payrollId, DataScopeService::current()]
        );

        if (($totals['total_net'] ?? 0) <= 0) {
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

        $runScope = $this->db->prepare('SELECT id FROM payroll_runs WHERE id=? AND data_scope=? LIMIT 1');
        $runScope->execute([$payrollId, DataScopeService::current()]);
        if (!$runScope->fetchColumn()) {
            throw new Exception('Payroll not found in the active workspace');
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
                // Ensure payroll is approved (3NF: payroll_runs)
                $sql = "SELECT status FROM payroll_runs WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, DataScopeService::current()]);
                $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$payroll || $payroll['status'] !== 'approved') {
                    throw new Exception("Payroll must be approved before processing");
                }
                break;

            case 'completed':
                // Verify all payments are successful (3NF: payslips)
                $sql = "SELECT COUNT(*) FROM payslips ps
                        JOIN payroll_runs pr ON pr.id = ?
                        WHERE ps.payroll_month = pr.month AND ps.payroll_year = pr.year
                          AND ps.data_scope COLLATE utf8mb4_unicode_ci = pr.data_scope COLLATE utf8mb4_unicode_ci AND pr.data_scope=?
                          AND ps.payment_status = 'failed'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, DataScopeService::current()]);
                $failed = $stmt->fetchColumn();

                if ($failed > 0) {
                    throw new Exception("Cannot mark as completed. {$failed} payments failed.");
                }
                break;

            case 'partial':
                // Verify some payments failed (3NF: payslips)
                $sql = "SELECT COUNT(*) FROM payslips ps
                        JOIN payroll_runs pr ON pr.id = ?
                        WHERE ps.payroll_month = pr.month AND ps.payroll_year = pr.year
                          AND ps.data_scope COLLATE utf8mb4_unicode_ci = pr.data_scope COLLATE utf8mb4_unicode_ci AND pr.data_scope=?
                          AND ps.payment_status = 'failed'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, DataScopeService::current()]);
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
        $dataScope = DataScopeService::current();

        // Execute stage-specific processing
        switch ($stage) {
            case 'draft':
                // Initialize payroll draft
                $sql = "UPDATE payroll_runs SET status = 'draft', workflow = 'draft' WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, $dataScope]);
                $this->logAction("Payroll draft initialized", "Initialized payroll #{$payrollId}", $userId);
                break;

            case 'pending_approval':
                // Mark as pending approval (payroll_runs.status has no pending_approval —
                // keep status=draft and record the workflow stage in the workflow column)
                $sql = "UPDATE payroll_runs SET status = 'draft', workflow = 'pending_approval' WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, $dataScope]);
                $this->logAction("Payroll submitted for approval", "Payroll #{$payrollId} submitted for Director approval", $userId);

                // Send notification to Director
                $this->sendNotificationToDirector($payrollId);
                break;

            case 'approved':
                // Mark as approved
                $sql = "UPDATE payroll_runs SET status = 'approved', workflow = 'approved' WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, $dataScope]);
                $this->logAction("Payroll approved by Director", "Payroll #{$payrollId} approved and ready for processing", $userId);

                // Notify HR/Accountant
                $this->sendApprovalNotification($payrollId);
                break;

            case 'rejected':
                // Mark as rejected (kept in draft status; stage recorded in workflow column)
                $reason = $data['reason'] ?? 'No reason provided';
                $sql = "UPDATE payroll_runs SET status = 'draft', workflow = 'rejected' WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, $dataScope]);
                $this->logAction("Payroll rejected", "Payroll #{$payrollId} rejected. Reason: {$reason}", $userId);

                // Notify creator
                $this->sendRejectionNotification($payrollId, $reason);
                break;

            case 'processing':
                // Mark as processing
                $sql = "UPDATE payroll_runs SET status = 'processing', workflow = 'processing' WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, $dataScope]);
                $this->logAction("Payroll disbursement started", "Disbursement process started for payroll #{$payrollId}", $userId);
                break;

            case 'completed':
                // Mark as completed (payroll_runs 'paid' = fully disbursed)
                $sql = "UPDATE payroll_runs SET status = 'paid', workflow = 'completed' WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, $dataScope]);
                $this->logAction("Payroll completed successfully", "All payments for payroll #{$payrollId} completed successfully", $userId);

                // Send completion notifications
                $this->sendCompletionNotification($payrollId);
                break;

            case 'partial':
                // Mark as partial
                $sql = "SELECT COUNT(*) FROM disbursement_transactions WHERE payroll_id = ? AND status = 'failed'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId]);
                $failedCount = $stmt->fetchColumn();

                $sql = "UPDATE payroll_runs SET status = 'paid', workflow = 'partial' WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, $dataScope]);
                $this->logAction("Payroll partially completed", "Payroll #{$payrollId} partially completed. {$failedCount} payments failed", $userId);

                // Send alert about failed payments
                $this->sendPartialCompletionAlert($payrollId, $failedCount);
                break;

            case 'cancelled':
                // Mark as cancelled
                $sql = "UPDATE payroll_runs SET status = 'draft', workflow = 'cancelled' WHERE id = ? AND data_scope=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$payrollId, $dataScope]);
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
        // Update payroll status in database (payroll_runs.status is a fixed enum;
        // the finer workflow stage lives in the workflow varchar column)
        $this->db->update('payroll_runs', [
            'status' => $this->mapStageToStatus($toStage),
            'workflow' => $toStage,
        ], ['id' => $payrollId]);

        // Log the transition
        $this->logAction('transition', $payrollId, "Transitioned to {$toStage}");
    }

    /**
     * Map a workflow stage to the closest valid payroll_runs.status enum value.
     */
    private function mapStageToStatus($stage)
    {
        switch ($stage) {
            case 'approved':
                return 'approved';
            case 'processing':
                return 'processing';
            case 'completed':
            case 'partial':
                return 'paid';
            case 'pending_approval':
            case 'rejected':
            case 'cancelled':
            case 'draft':
            default:
                return 'draft';
        }
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
