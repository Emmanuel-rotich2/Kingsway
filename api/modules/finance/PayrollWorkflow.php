<?php
namespace App\API\Modules\finance;

use App\API\Includes\WorkflowHandler;
use App\API\Modules\staff\StaffPayrollManager;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Payroll Processing Workflow
 * 
 * Multi-stage approval workflow for payroll processing
 * Extends WorkflowHandler for workflow management
 * 
 * Workflow Stages:
 * 1. calculation - Compute payroll for period
 * 2. verification - Verify calculations
 * 3. approval - Management approval
 * 4. payment - Process payment
 */
class PayrollWorkflow extends WorkflowHandler
{
    protected $workflowType = 'payroll_processing';
    private $payrollManager;

    public function __construct()
    {
        parent::__construct('payroll_processing');
        $this->payrollManager = new StaffPayrollManager();
    }

    /**
     * Initiate payroll processing workflow
     * @param array $filters Staff filters (department_id, staff_type_id, etc.)
     * @param int $userId User initiating workflow
     * @param array $data Payroll period data
     * @return array Response
     */
    public function initiatePayroll($filters, $userId, $data = [])
    {
        try {
            $required = ['payroll_month', 'payroll_year'];
            $missing = [];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Check for existing active payroll workflow for this period
            $stmt = $this->db->prepare("
                SELECT wi.* FROM workflow_instances wi
                WHERE wi.workflow_type = 'payroll_processing'
                AND wi.status IN ('in_progress', 'pending')
                AND JSON_EXTRACT(wi.workflow_data, '$.payroll_month') = ?
                AND JSON_EXTRACT(wi.workflow_data, '$.payroll_year') = ?
            ");
            $stmt->execute([$data['payroll_month'], $data['payroll_year']]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active payroll workflow already exists for this period');
            }

            // Get staff list based on filters
            $sql = "SELECT s.id, s.staff_no, s.first_name, s.last_name, s.position,
                           d.name as department_name, st.name as staff_type
                    FROM staff s
                    LEFT JOIN departments d ON s.department_id = d.id
                    LEFT JOIN staff_types st ON s.staff_type_id = st.id
                    WHERE s.status = 'active'";

            $params = [];

            if (!empty($filters['department_id'])) {
                $sql .= " AND s.department_id = ?";
                $params[] = $filters['department_id'];
            }

            if (!empty($filters['staff_type_id'])) {
                $sql .= " AND s.staff_type_id = ?";
                $params[] = $filters['staff_type_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($staffList)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No active staff found matching the criteria');
            }

            // Calculate payroll for each staff member
            $payrollRecords = [];
            $totalGross = 0;
            $totalNet = 0;

            foreach ($staffList as $staff) {
                $payrollResult = $this->payrollManager->calculatePayroll([
                    'staff_id' => $staff['id'],
                    'payroll_month' => $data['payroll_month'],
                    'payroll_year' => $data['payroll_year']
                ]);

                if ($payrollResult['success']) {
                    $payrollRecords[] = $payrollResult['data'];
                    $totalGross += $payrollResult['data']['gross_salary'];
                    $totalNet += $payrollResult['data']['net_salary'];
                }
            }

            // Start workflow with reference to payroll batch
            $workflowData = [
                'payroll_month' => $data['payroll_month'],
                'payroll_year' => $data['payroll_year'],
                'department_filter' => $filters['department_id'] ?? null,
                'staff_type_filter' => $filters['staff_type_id'] ?? null,
                'total_staff' => count($payrollRecords),
                'total_gross_salary' => $totalGross,
                'total_net_salary' => $totalNet,
                'payroll_records' => array_column($payrollRecords, 'payroll_id'),
                'initiated_by' => $userId,
                'initiated_at' => date('Y-m-d H:i:s')
            ];

            $result = $this->startWorkflow(
                'payroll_processing',
                0, // No specific reference ID, using month/year as identifier
                $userId,
                $workflowData
            );

            if (!$result['success']) {
                $this->db->rollBack();
                return $result;
            }

            $workflowId = $result['data']['workflow_id'];

            $totalStaff = count($payrollRecords);

            $this->db->commit();
            $this->logAction(
                'create',
                $workflowId,
                "Initiated payroll workflow for {$data['payroll_month']}/{$data['payroll_year']} - {$totalStaff} staff members"
            );

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'payroll_period' => "{$data['payroll_month']}/{$data['payroll_year']}",
                'total_staff' => $totalStaff,
                'total_gross_salary' => $totalGross,
                'total_net_salary' => $totalNet,
                'current_stage' => 'calculation',
                'status' => 'in_progress'
            ], 'Payroll workflow initiated and calculated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Verify payroll calculations (Stage 2)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing verification
     * @param array $data Verification data
     * @return array Response
     */
    public function verifyPayroll($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'calculation' && $currentStage !== 'verification') {
                return formatResponse(false, null, "Cannot verify payroll. Current stage is: {$currentStage}");
            }

            $this->db->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Get payroll records for verification
            $payrollIds = $workflowData['payroll_records'] ?? [];

            if (empty($payrollIds)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No payroll records found');
            }

            // Verify each payroll record
            $verificationIssues = [];

            foreach ($payrollIds as $payrollId) {
                $stmt = $this->db->prepare("
                    SELECT * FROM staff_payroll WHERE id = ?
                ");
                $stmt->execute([$payrollId]);
                $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$payroll) {
                    $verificationIssues[] = "Payroll record {$payrollId} not found";
                    continue;
                }

                // Add custom verification checks here
                if ($payroll['net_salary'] < 0) {
                    $verificationIssues[] = "Staff #{$payroll['staff_id']} has negative net salary";
                }
            }

            $workflowData['verification'] = [
                'verified_by' => $userId,
                'verified_at' => date('Y-m-d H:i:s'),
                'issues_found' => count($verificationIssues),
                'issues' => $verificationIssues,
                'verified_records' => count($payrollIds),
                'verification_notes' => $data['verification_notes'] ?? ''
            ];

            if (!empty($verificationIssues)) {
                // Stay in verification stage if issues found
                $this->db->commit();
                return formatResponse(false, [
                    'workflow_id' => $workflowId,
                    'issues' => $verificationIssues
                ], 'Verification failed. Issues found in payroll calculations');
            }

            // Advance to approval stage
            $this->advanceStage(
                $workflowId,
                'approval',
                'verification_completed',
                $workflowData
            );

            $this->db->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['remarks'] ?? 'Payroll verified successfully'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Approve payroll (Stage 3)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing approval (must have authority)
     * @param array $data Approval data
     * @return array Response
     */
    public function approvePayroll($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'approval') {
                return formatResponse(false, null, "Cannot approve payroll. Current stage is: {$currentStage}");
            }

            $this->db->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            $workflowData['approval'] = [
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $data['approval_notes'] ?? ''
            ];

            // Advance to payment stage
            $this->advanceStage(
                $workflowId,
                'payment',
                'payroll_approved',
                $workflowData
            );

            $this->db->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['remarks'] ?? 'Payroll approved for payment'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Process payment (Stage 4)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User processing payment
     * @param array $data Payment data
     * @return array Response
     */
    public function processPayment($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'payment') {
                return formatResponse(false, null, "Cannot process payment. Current stage is: {$currentStage}");
            }

            $required = ['payment_method'];
            $missing = [];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $payrollIds = $workflowData['payroll_records'] ?? [];

            $paymentData = [
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null
            ];

            // Process payment for each payroll record
            $processedCount = 0;
            $failedPayments = [];

            foreach ($payrollIds as $payrollId) {
                $paymentResult = $this->payrollManager->recordPayment($payrollId, $paymentData);

                if ($paymentResult['success']) {
                    $processedCount++;
                } else {
                    $failedPayments[] = [
                        'payroll_id' => $payrollId,
                        'error' => $paymentResult['message']
                    ];
                }
            }

            if (!empty($failedPayments)) {
                $this->db->rollBack();
                return formatResponse(false, [
                    'failed_payments' => $failedPayments
                ], 'Some payments failed to process');
            }

            $workflowData['payment'] = [
                'processed_by' => $userId,
                'processed_at' => date('Y-m-d H:i:s'),
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'records_processed' => $processedCount
            ];

            // Complete workflow
            $result = $this->completeWorkflow(
                $workflowId,
                $userId,
                'Payroll processing completed successfully',
                $workflowData
            );

            if (!$result['success']) {
                $this->db->rollBack();
                return $result;
            }

            $this->db->commit();
            $this->logAction('update', $workflowId, "Processed payroll payments - {$processedCount} records");

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'status' => 'completed',
                'records_processed' => $processedCount,
                'total_amount_paid' => $workflowData['total_net_salary'],
                'completed_at' => date('Y-m-d H:i:s')
            ], 'Payroll processing completed successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Generate payroll report for workflow
     * @param int $workflowId Workflow instance ID
     * @return array Response
     */
    public function generatePayrollReport($workflowId)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $payrollIds = $workflowData['payroll_records'] ?? [];

            $report = [
                'payroll_period' => "{$workflowData['payroll_month']}/{$workflowData['payroll_year']}",
                'total_staff' => $workflowData['total_staff'],
                'total_gross_salary' => $workflowData['total_gross_salary'],
                'total_net_salary' => $workflowData['total_net_salary'],
                'workflow_status' => $workflow['data']['status'],
                'current_stage' => $workflow['data']['current_stage'],
                'initiated_at' => $workflowData['initiated_at'],
                'payroll_details' => []
            ];

            // Get detailed payroll records
            if (!empty($payrollIds)) {
                $placeholders = implode(',', array_fill(0, count($payrollIds), '?'));
                $stmt = $this->db->prepare("
                    SELECT sp.*, s.staff_no, s.first_name, s.last_name, s.position,
                           d.name as department_name
                    FROM staff_payroll sp
                    JOIN staff s ON sp.staff_id = s.id
                    LEFT JOIN departments d ON s.department_id = d.id
                    WHERE sp.id IN ($placeholders)
                    ORDER BY s.last_name, s.first_name
                ");
                $stmt->execute($payrollIds);
                $report['payroll_details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return formatResponse(true, $report, 'Payroll report generated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Reject payroll at any stage
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param string $reason Rejection reason
     * @return array Response
     */
    public function rejectPayroll($workflowId, $userId, $reason)
    {
        try {
            $this->db->beginTransaction();

            $this->cancelWorkflow($workflowId, $reason);

            // Delete unpaid payroll records
            $workflow = $this->getWorkflowInstance($workflowId);
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $payrollIds = $workflowData['payroll_records'] ?? [];

            if (!empty($payrollIds)) {
                $placeholders = implode(',', array_fill(0, count($payrollIds), '?'));
                $stmt = $this->db->prepare("
                    DELETE FROM staff_payroll 
                    WHERE id IN ($placeholders) AND status = 'pending'
                ");
                $stmt->execute($payrollIds);
            }

            $this->db->commit();
            $this->logAction('update', $workflowId, "Rejected payroll workflow: {$reason}");

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'status' => 'rejected'
            ], 'Payroll rejected and pending records deleted');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }
}
