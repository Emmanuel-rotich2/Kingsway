<?php
namespace App\API\Modules\staff;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Payroll Manager (Self-Service)
 * 
 * Handles staff self-service payroll operations:
 * - View payslips and payroll history
 * - Request salary advances
 * - Apply for loans
 * - Download P9 forms
 * - View allowances and deductions
 * 
 * NOTE: Admin payroll operations (calculation, approval, processing) are in Finance module
 */
class StaffPayrollManager extends BaseAPI
{
    /**
     * Record payment for a payroll record (payslip)
     * Used by PayrollWorkflow during payment processing
     * @param int $payrollId
     * @param array $paymentData (e.g., payment_method, payment_reference, paid_at)
     * @return array
     */
    public function recordPayment($payrollId, $paymentData)
    {
        try {
            // Validate required fields
            if (empty($payrollId) || empty($paymentData['payment_method'])) {
                return formatResponse(false, null, 'Missing required payment fields');
            }

            // Update payslip/payment status
            $stmt = $this->db->prepare("UPDATE payslips SET payment_status = 'paid', payment_method = ?, payment_reference = ?, paid_at = ? WHERE id = ?");
            $paidAt = $paymentData['paid_at'] ?? date('Y-m-d H:i:s');
            $paymentRef = $paymentData['payment_reference'] ?? null;
            $result = $stmt->execute([
                $paymentData['payment_method'],
                $paymentRef,
                $paidAt,
                $payrollId
            ]);

            if (!$result) {
                return formatResponse(false, null, 'Failed to update payment record');
            }

            $this->logAction('payment', $payrollId, "Payroll payment recorded: method={$paymentData['payment_method']}, ref={$paymentRef}");

            return formatResponse(true, [
                'payroll_id' => $payrollId,
                'payment_method' => $paymentData['payment_method'],
                'payment_reference' => $paymentRef,
                'paid_at' => $paidAt
            ], 'Payment recorded successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    /**
     * Calculate payroll for a staff member for a given period
     * Used by PayrollWorkflow (admin payroll processing)
     * @param array $data [staff_id, payroll_month, payroll_year]
     * @return array Response with gross_salary, net_salary, breakdown, etc.
     */
    public function calculatePayroll($data)
    {
        try {
            $required = ['staff_id', 'payroll_month', 'payroll_year'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $staffId = $data['staff_id'];
            $month = $data['payroll_month'];
            $year = $data['payroll_year'];

            // Fetch base salary
            $stmt = $this->db->prepare("SELECT base_salary FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return formatResponse(false, null, 'Staff not found');
            }
            $baseSalary = (float) $row['base_salary'];

            // Fetch allowances
            $stmt = $this->db->prepare("SELECT amount FROM staff_allowances WHERE staff_id = ? AND (YEAR(effective_date) < ? OR (YEAR(effective_date) = ? AND MONTH(effective_date) <= ?))");
            $stmt->execute([$staffId, $year, $year, $month]);
            $allowances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalAllowances = array_sum(array_column($allowances, 'amount'));

            // Fetch deductions
            $stmt = $this->db->prepare("SELECT amount FROM staff_deductions WHERE staff_id = ? AND (YEAR(effective_date) < ? OR (YEAR(effective_date) = ? AND MONTH(effective_date) <= ?))");
            $stmt->execute([$staffId, $year, $year, $month]);
            $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalDeductions = array_sum(array_column($deductions, 'amount'));

            $grossSalary = $baseSalary + $totalAllowances;
            $netSalary = $grossSalary - $totalDeductions;

            // Optionally, insert or update payslip record here if needed by workflow

            return formatResponse(true, [
                'staff_id' => $staffId,
                'payroll_month' => $month,
                'payroll_year' => $year,
                'gross_salary' => $grossSalary,
                'net_salary' => $netSalary,
                'base_salary' => $baseSalary,
                'total_allowances' => $totalAllowances,
                'total_deductions' => $totalDeductions,
                'allowances_breakdown' => $allowances,
                'deductions_breakdown' => $deductions
            ], 'Payroll calculated successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * View payslip for a specific period
     */
    public function viewPayslip($staffId, $data)
    {
        try {
            $required = ['payroll_month', 'payroll_year'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $stmt = $this->db->prepare("
                SELECT ps.*, s.staff_no,
                    CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
                    s.position, s.bank_account, s.nssf_no, s.nhif_no, s.kra_pin,
                    st.name AS staff_type, d.name AS department_name,
                    CONCAT(approver.first_name, ' ', approver.last_name) AS approved_by_name
                FROM payslips ps
                INNER JOIN staff s ON ps.staff_id = s.id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN users approver ON ps.signed_by = approver.id
                WHERE ps.staff_id = ? AND ps.payroll_month = ? AND ps.payroll_year = ?
            ");
            $stmt->execute([$staffId, $data['payroll_month'], $data['payroll_year']]);
            $payslip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payslip) {
                return formatResponse(false, null, 'Payslip not found for the specified period');
            }

            $stmt = $this->db->prepare("SELECT * FROM staff_allowances WHERE staff_id = ? ORDER BY effective_date DESC");
            $stmt->execute([$staffId]);
            $allowances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("SELECT * FROM staff_deductions WHERE staff_id = ? ORDER BY effective_date DESC");
            $stmt->execute([$staffId]);
            $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('view', $payslip['id'], "Staff ID $staffId viewed payslip");

            return formatResponse(true, [
                'payslip' => $payslip,
                'allowances_breakdown' => $allowances,
                'deductions_breakdown' => $deductions
            ], 'Payslip retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get payroll history
     */
    public function getPayrollHistory($staffId, $filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_staff_payroll_summary WHERE staff_id = ?";
            $params = [$staffId];

            if (!empty($filters['year'])) {
                $sql .= " AND payroll_year = ?";
                $params[] = $filters['year'];
            }

            $sql .= " ORDER BY payroll_year DESC, payroll_month DESC";

            if (!empty($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = (int)$filters['limit'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $payrollHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('view', null, "Staff ID $staffId viewed payroll history");

            return formatResponse(true, [
                'payroll_history' => $payrollHistory,
                'count' => count($payrollHistory)
            ], 'Payroll history retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * View allowances
     */
    public function viewAllowances($staffId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM staff_allowances WHERE staff_id = ? ORDER BY effective_date DESC");
            $stmt->execute([$staffId]);
            $allowances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalActive = array_reduce($allowances, function($sum, $a) {
                return $sum + ($a['amount'] ?? 0);
            }, 0);

            $this->logAction('view', null, "Staff ID $staffId viewed allowances");

            return formatResponse(true, [
                'allowances' => $allowances,
                'total_active_allowances' => $totalActive,
                'count' => count($allowances)
            ], 'Allowances retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * View deductions
     */
    public function viewDeductions($staffId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM staff_deductions WHERE staff_id = ? ORDER BY effective_date DESC");
            $stmt->execute([$staffId]);
            $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalActive = array_reduce($deductions, function($sum, $d) {
                return $sum + ($d['amount'] ?? 0);
            }, 0);

            $this->logAction('view', null, "Staff ID $staffId viewed deductions");

            return formatResponse(true, [
                'deductions' => $deductions,
                'total_active_deductions' => $totalActive,
                'count' => count($deductions)
            ], 'Deductions retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get loan details
     */
    public function getLoanDetails($staffId, $loanId = null)
    {
        try {
            if ($loanId) {
                $stmt = $this->db->prepare("SELECT * FROM vw_staff_loan_details WHERE staff_id = ? AND loan_id = ?");
                $stmt->execute([$staffId, $loanId]);
                $loan = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$loan) {
                    return formatResponse(false, null, 'Loan not found');
                }

                $this->logAction('view', $loanId, "Staff ID $staffId viewed loan details");
                return formatResponse(true, ['loan' => $loan], 'Loan details retrieved successfully');
            } else {
                $stmt = $this->db->prepare("SELECT * FROM vw_staff_loan_details WHERE staff_id = ? ORDER BY loan_created_at DESC");
                $stmt->execute([$staffId]);
                $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $this->logAction('view', null, "Staff ID $staffId viewed all loans");
                return formatResponse(true, ['loans' => $loans, 'count' => count($loans)], 'Loan details retrieved successfully');
            }

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Request salary advance
     */
    public function requestAdvance($staffId, $data)
    {
        try {
            $required = ['amount', 'reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("CALL sp_request_staff_advance(?, ?, ?, @request_id)");
            $stmt->execute([$staffId, $data['amount'], $data['reason']]);
            
            $result = $this->db->query("SELECT @request_id AS request_id")->fetch(PDO::FETCH_ASSOC);
            $requestId = $result['request_id'];

            $this->db->commit();
            $this->logAction('create', $requestId, "Staff ID $staffId requested advance of KES {$data['amount']}");

            return formatResponse(true, [
                'request_id' => $requestId,
                'amount' => $data['amount'],
                'status' => 'pending',
                'message' => 'Advance request submitted. Awaiting Finance approval.'
            ], 'Advance request submitted successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Apply for loan
     */
    public function applyForLoan($staffId, $data)
    {
        try {
            $required = ['loan_type', 'principal_amount', 'agreed_monthly_deduction'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("CALL sp_apply_staff_loan(?, ?, ?, ?, @loan_id)");
            $stmt->execute([$staffId, $data['loan_type'], $data['principal_amount'], $data['agreed_monthly_deduction']]);
            
            $result = $this->db->query("SELECT @loan_id AS loan_id")->fetch(PDO::FETCH_ASSOC);
            $loanId = $result['loan_id'];

            $this->db->commit();
            $this->logAction('create', $loanId, "Staff ID $staffId applied for {$data['loan_type']} loan of KES {$data['principal_amount']}");

            $months = ceil($data['principal_amount'] / $data['agreed_monthly_deduction']);

            return formatResponse(true, [
                'loan_id' => $loanId,
                'loan_type' => $data['loan_type'],
                'principal_amount' => $data['principal_amount'],
                'monthly_deduction' => $data['agreed_monthly_deduction'],
                'repayment_months' => $months,
                'status' => 'suspended',
                'message' => 'Loan application submitted. Awaiting Finance approval.'
            ], 'Loan application submitted successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Download P9 form
     */
    public function downloadP9Form($staffId, $year)
    {
        try {
            $stmt = $this->db->prepare("CALL sp_generate_p9_form(?, ?)");
            $stmt->execute([$staffId, $year]);
            
            $p9Summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$p9Summary) {
                return formatResponse(false, null, 'No payroll data found for the specified year');
            }

            $stmt->nextRowset();
            $monthlyBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('download', null, "Staff ID $staffId downloaded P9 form for year $year");

            return formatResponse(true, [
                'p9_summary' => $p9Summary,
                'monthly_breakdown' => $monthlyBreakdown,
                'year' => $year
            ], 'P9 form generated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Download payslip
     */
    public function downloadPayslip($staffId, $payslipId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM payslips WHERE id = ? AND staff_id = ?");
            $stmt->execute([$payslipId, $staffId]);
            $payslip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payslip) {
                return formatResponse(false, null, 'Payslip not found or access denied');
            }

            $result = $this->viewPayslip($staffId, [
                'payroll_month' => $payslip['payroll_month'],
                'payroll_year' => $payslip['payroll_year']
            ]);

            if ($result['success']) {
                $this->logAction('download', $payslipId, "Staff ID $staffId downloaded payslip ID $payslipId");
                $result['message'] = 'Payslip ready for download';
            }

            return $result;

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Export payroll history
     */
    public function exportPayrollHistory($staffId, $year, $format = 'pdf')
    {
        try {
            $result = $this->getPayrollHistory($staffId, ['year' => $year]);

            if (!$result['success']) {
                return $result;
            }

            $this->logAction('export', null, "Staff ID $staffId exported payroll history for year $year as $format");

            $result['data']['export_format'] = $format;
            $result['data']['export_year'] = $year;
            $result['message'] = 'Payroll history ready for export';

            return $result;

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // STAFF CHILDREN MANAGEMENT
    // ========================================================================

    /**
     * Get staff children (students enrolled in the school)
     */
    public function getStaffChildren($staffId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM vw_staff_children_fees 
                WHERE staff_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$staffId]);
            $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'children' => $children,
                'count' => count($children)
            ], 'Staff children retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Link a student as staff child
     */
    public function addStaffChild($staffId, $data)
    {
        try {
            $required = ['student_id', 'relationship'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // Check if student exists and is active
            $stmt = $this->db->prepare("SELECT id, first_name, last_name FROM students WHERE id = ? AND status = 'active'");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found or not active');
            }

            // Check if already linked
            $stmt = $this->db->prepare("SELECT id FROM staff_children WHERE staff_id = ? AND student_id = ?");
            $stmt->execute([$staffId, $data['student_id']]);
            if ($stmt->fetch()) {
                return formatResponse(false, null, 'This student is already linked to this staff member');
            }

            $stmt = $this->db->prepare("
                INSERT INTO staff_children 
                (staff_id, student_id, relationship, fee_deduction_enabled, fee_deduction_percentage, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $staffId,
                $data['student_id'],
                $data['relationship'],
                $data['fee_deduction_enabled'] ?? 1,
                $data['fee_deduction_percentage'] ?? 100.00,
                $data['notes'] ?? null
            ]);

            $childId = $this->db->lastInsertId();
            $studentName = $student['first_name'] . ' ' . $student['last_name'];
            $this->logAction('create', $childId, "Linked student $studentName to staff ID $staffId");

            return formatResponse(true, [
                'staff_child_id' => $childId,
                'student_name' => $studentName
            ], 'Staff child added successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update staff child settings
     */
    public function updateStaffChild($staffId, $childId, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM staff_children WHERE id = ? AND staff_id = ?");
            $stmt->execute([$childId, $staffId]);
            if (!$stmt->fetch()) {
                return formatResponse(false, null, 'Staff child record not found');
            }

            $updates = [];
            $params = [];

            $allowedFields = ['fee_deduction_enabled', 'fee_deduction_percentage', 'notes', 'relationship'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $childId;
            $params[] = $staffId;

            $sql = "UPDATE staff_children SET " . implode(', ', $updates) . " WHERE id = ? AND staff_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logAction('update', $childId, "Updated staff child settings");

            return formatResponse(true, null, 'Staff child settings updated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Remove staff child link
     */
    public function removeStaffChild($staffId, $childId)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM staff_children WHERE id = ? AND staff_id = ?");
            $stmt->execute([$childId, $staffId]);

            if ($stmt->rowCount() === 0) {
                return formatResponse(false, null, 'Staff child record not found');
            }

            $this->logAction('delete', $childId, "Removed staff child link");

            return formatResponse(true, null, 'Staff child unlinked successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // CHILD FEE DEDUCTION CALCULATIONS
    // ========================================================================

    /**
     * Get staff child fee configuration
     */
    public function getChildFeeConfig()
    {
        try {
            $stmt = $this->db->prepare("SELECT config_key, config_value, description FROM staff_child_fee_config WHERE is_active = 1");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $configMap = [];
            foreach ($configs as $c) {
                $configMap[$c['config_key']] = [
                    'value' => $c['config_value'],
                    'description' => $c['description']
                ];
            }

            return formatResponse(true, $configMap, 'Configuration retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Calculate child fee deductions for a staff member for a given period
     */
    public function calculateChildFeeDeductions($staffId, $payrollMonth, $payrollYear)
    {
        try {
            // Get configuration
            $configResult = $this->getChildFeeConfig();
            $config = $configResult['data'] ?? [];

            $firstDiscount = floatval($config['first_child_discount_percentage']['value'] ?? 50);
            $secondDiscount = floatval($config['second_child_discount_percentage']['value'] ?? 40);
            $thirdDiscount = floatval($config['third_child_discount_percentage']['value'] ?? 30);
            $maxDeductionPct = floatval($config['max_monthly_deduction_percentage']['value'] ?? 30);

            // Get staff salary
            $stmt = $this->db->prepare("SELECT salary FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $staffRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$staffRow) {
                return formatResponse(false, null, 'Staff not found');
            }
            $staffSalary = floatval($staffRow['salary']);
            $maxDeductible = $staffSalary * ($maxDeductionPct / 100);

            // Get active children
            $stmt = $this->db->prepare("
                SELECT 
                    sc.id AS staff_child_id,
                    sc.student_id,
                    sc.fee_deduction_enabled,
                    sc.fee_deduction_percentage,
                    st.first_name,
                    st.last_name,
                    st.is_sponsored,
                    st.sponsor_waiver_percentage,
                    c.name AS class_name,
                    cs.stream_name
                FROM staff_children sc
                JOIN students st ON sc.student_id = st.id
                LEFT JOIN class_streams cs ON st.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE sc.staff_id = ? 
                AND sc.fee_deduction_enabled = 1
                AND st.status = 'active'
                ORDER BY sc.created_at ASC
            ");
            $stmt->execute([$staffId]);
            $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalDeduction = 0;
            $childDeductions = [];
            $childNumber = 0;

            foreach ($children as $child) {
                $childNumber++;

                // Determine discount rate based on child order
                if ($childNumber === 1) {
                    $discountRate = $firstDiscount;
                } elseif ($childNumber === 2) {
                    $discountRate = $secondDiscount;
                } else {
                    $discountRate = $thirdDiscount;
                }

                // Get current term fees for this student
                $stmt = $this->db->prepare("
                    SELECT 
                        fi.id AS invoice_id,
                        fi.total_amount,
                        fi.amount_paid,
                        fi.balance
                    FROM fee_invoices fi
                    JOIN academic_terms at ON fi.term_id = at.id
                    WHERE fi.student_id = ?
                    AND at.is_current = 1
                    AND fi.status IN ('pending', 'partial')
                    ORDER BY fi.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$child['student_id']]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

                $grossFees = floatval($invoice['balance'] ?? 0);
                $sponsorWaiver = 0;

                // Apply sponsor waiver if applicable
                if ($child['is_sponsored'] && $child['sponsor_waiver_percentage'] > 0) {
                    $sponsorWaiver = $grossFees * ($child['sponsor_waiver_percentage'] / 100);
                    $grossFees -= $sponsorWaiver;
                }

                // Apply staff discount
                $staffDiscount = $grossFees * ($discountRate / 100);
                $deductibleAmount = $grossFees - $staffDiscount;

                // Apply custom deduction percentage if set
                if ($child['fee_deduction_percentage'] < 100) {
                    $deductibleAmount = $deductibleAmount * ($child['fee_deduction_percentage'] / 100);
                }

                // Monthly amount (assuming 3 months per term)
                $monthlyDeduction = $deductibleAmount / 3;

                $childDeductions[] = [
                    'staff_child_id' => $child['staff_child_id'],
                    'student_id' => $child['student_id'],
                    'student_name' => $child['first_name'] . ' ' . $child['last_name'],
                    'class' => $child['class_name'] . ' ' . ($child['stream_name'] ?? ''),
                    'child_number' => $childNumber,
                    'gross_fees' => $invoice['balance'] ?? 0,
                    'sponsor_waiver' => $sponsorWaiver,
                    'staff_discount_percentage' => $discountRate,
                    'staff_discount_amount' => $staffDiscount,
                    'deductible_amount' => $deductibleAmount,
                    'monthly_deduction' => round($monthlyDeduction, 2),
                    'invoice_id' => $invoice['invoice_id'] ?? null
                ];

                $totalDeduction += $monthlyDeduction;
            }

            // Check if total exceeds max deductible
            $exceededLimit = false;
            if ($totalDeduction > $maxDeductible) {
                $exceededLimit = true;
                // Proportionally reduce each child's deduction
                $ratio = $maxDeductible / $totalDeduction;
                foreach ($childDeductions as &$cd) {
                    $cd['original_monthly_deduction'] = $cd['monthly_deduction'];
                    $cd['monthly_deduction'] = round($cd['monthly_deduction'] * $ratio, 2);
                }
                $totalDeduction = $maxDeductible;
            }

            return formatResponse(true, [
                'staff_id' => $staffId,
                'payroll_period' => sprintf('%04d-%02d', $payrollYear, $payrollMonth),
                'staff_salary' => $staffSalary,
                'max_deduction_percentage' => $maxDeductionPct,
                'max_deductible_amount' => $maxDeductible,
                'total_children' => count($children),
                'total_child_fee_deduction' => round($totalDeduction, 2),
                'exceeded_limit' => $exceededLimit,
                'children_breakdown' => $childDeductions
            ], 'Child fee deductions calculated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // DETAILED PAYSLIP GENERATION
    // ========================================================================

    /**
     * Generate detailed payslip with all breakdowns
     */
    public function generateDetailedPayslip($staffId, $payrollMonth, $payrollYear, $generatedBy = null)
    {
        try {
            // Get staff details
            $stmt = $this->db->prepare("
                SELECT s.*, d.name AS department_name, st.name AS staff_type_name
                FROM staff s
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                WHERE s.id = ?
            ");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                return formatResponse(false, null, 'Staff not found');
            }

            $basicSalary = floatval($staff['salary']);

            // Get allowances
            $stmt = $this->db->prepare("
                SELECT id, name, allowance_type, amount, is_taxable
                FROM staff_allowances 
                WHERE staff_id = ? 
                AND status = 'active'
                AND (end_date IS NULL OR end_date >= CURDATE())
            ");
            $stmt->execute([$staffId]);
            $allowances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalAllowances = 0;
            $taxableAllowances = 0;
            $allowancesBreakdown = [];

            foreach ($allowances as $a) {
                $totalAllowances += floatval($a['amount']);
                if ($a['is_taxable']) {
                    $taxableAllowances += floatval($a['amount']);
                }
                $allowancesBreakdown[] = [
                    'id' => $a['id'],
                    'name' => $a['name'] ?? ucfirst($a['allowance_type']) . ' Allowance',
                    'type' => $a['allowance_type'],
                    'amount' => floatval($a['amount']),
                    'is_taxable' => (bool) $a['is_taxable']
                ];
            }

            $grossSalary = $basicSalary + $totalAllowances;
            $taxableIncome = $basicSalary + $taxableAllowances;

            // Calculate statutory deductions
            // NSSF: Tier I (6% up to 7,000) + Tier II (6% of 7,001 - 36,000)
            $nssfTier1 = min($grossSalary * 0.06, 420); // Max Tier I
            $nssfTier2 = 0;
            if ($grossSalary > 7000) {
                $tier2Base = min($grossSalary - 7000, 29000);
                $nssfTier2 = $tier2Base * 0.06;
            }
            $nssfContribution = round($nssfTier1 + $nssfTier2, 2);
            $taxableIncome -= $nssfContribution; // NSSF is tax deductible

            // Housing Levy: 1.5% of gross
            $housingLevy = round($grossSalary * 0.015, 2);

            // NHIF rates (based on gross salary)
            $nhifContribution = $this->calculateNHIF($grossSalary);

            // PAYE calculation
            $paye = $this->calculatePAYE($taxableIncome);

            // Get other deductions (loans, SACCO, advances, etc.)
            $stmt = $this->db->prepare("
                SELECT sd.*, dt.name AS type_name, dt.code AS type_code, dt.category
                FROM staff_deductions sd
                LEFT JOIN deduction_types dt ON sd.deduction_type_id = dt.id
                WHERE sd.staff_id = ? 
                AND sd.status = 'active'
                AND (sd.end_date IS NULL OR sd.end_date >= CURDATE())
            ");
            $stmt->execute([$staffId]);
            $otherDeductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalOtherDeductions = 0;
            $loanDeduction = 0;
            $saccoDeduction = 0;
            $advanceDeduction = 0;
            $deductionsBreakdown = [];

            foreach ($otherDeductions as $d) {
                $amount = floatval($d['amount']);
                $totalOtherDeductions += $amount;

                $category = $d['category'] ?? 'other';
                if ($category === 'loan') {
                    $loanDeduction += $amount;
                } elseif ($d['type_code'] === 'SACCO') {
                    $saccoDeduction += $amount;
                } elseif ($category === 'advance') {
                    $advanceDeduction += $amount;
                }

                $deductionsBreakdown[] = [
                    'id' => $d['id'],
                    'name' => $d['name'] ?? $d['type_name'] ?? 'Deduction',
                    'code' => $d['type_code'] ?? 'OTHER',
                    'category' => $category,
                    'amount' => $amount,
                    'reference' => $d['reference_no'] ?? null
                ];
            }

            // Get child fee deductions
            $childFeesResult = $this->calculateChildFeeDeductions($staffId, $payrollMonth, $payrollYear);
            $childFeesDeduction = 0;
            $childFeesBreakdown = [];

            if ($childFeesResult['success'] && !empty($childFeesResult['data']['children_breakdown'])) {
                $childFeesDeduction = $childFeesResult['data']['total_child_fee_deduction'];
                $childFeesBreakdown = $childFeesResult['data']['children_breakdown'];
            }

            // Calculate totals
            $totalDeductions = $paye + $nssfContribution + $nhifContribution + $housingLevy +
                $loanDeduction + $saccoDeduction + $advanceDeduction +
                $childFeesDeduction + ($totalOtherDeductions - $loanDeduction - $saccoDeduction - $advanceDeduction);

            $netSalary = $grossSalary - $totalDeductions;

            // Prepare payslip data
            $payslipData = [
                'staff_id' => $staffId,
                'payroll_month' => $payrollMonth,
                'payroll_year' => $payrollYear,
                'basic_salary' => $basicSalary,
                'allowances_total' => $totalAllowances,
                'gross_salary' => $grossSalary,
                'paye_tax' => $paye,
                'nssf_contribution' => $nssfContribution,
                'nhif_contribution' => $nhifContribution,
                'housing_levy' => $housingLevy,
                'loan_deduction' => $loanDeduction,
                'sacco_deduction' => $saccoDeduction,
                'salary_advance_deduction' => $advanceDeduction,
                'child_fees_deduction' => $childFeesDeduction,
                'other_deductions_total' => $totalOtherDeductions - $loanDeduction - $saccoDeduction - $advanceDeduction,
                'net_salary' => $netSalary,
                'payslip_status' => 'draft',
                'payment_status' => 'pending',
                'allowances_breakdown' => json_encode($allowancesBreakdown),
                'deductions_breakdown' => json_encode($deductionsBreakdown),
                'child_fees_breakdown' => json_encode($childFeesBreakdown),
                'signed_by' => $generatedBy
            ];

            // Insert or update payslip
            $stmt = $this->db->prepare("
                INSERT INTO payslips (
                    staff_id, payroll_month, payroll_year, basic_salary, allowances_total,
                    gross_salary, paye_tax, nssf_contribution, nhif_contribution, housing_levy,
                    loan_deduction, sacco_deduction, salary_advance_deduction, child_fees_deduction,
                    other_deductions_total, net_salary, payslip_status, payment_status,
                    allowances_breakdown, deductions_breakdown, child_fees_breakdown, signed_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    basic_salary = VALUES(basic_salary),
                    allowances_total = VALUES(allowances_total),
                    gross_salary = VALUES(gross_salary),
                    paye_tax = VALUES(paye_tax),
                    nssf_contribution = VALUES(nssf_contribution),
                    nhif_contribution = VALUES(nhif_contribution),
                    housing_levy = VALUES(housing_levy),
                    loan_deduction = VALUES(loan_deduction),
                    sacco_deduction = VALUES(sacco_deduction),
                    salary_advance_deduction = VALUES(salary_advance_deduction),
                    child_fees_deduction = VALUES(child_fees_deduction),
                    other_deductions_total = VALUES(other_deductions_total),
                    net_salary = VALUES(net_salary),
                    allowances_breakdown = VALUES(allowances_breakdown),
                    deductions_breakdown = VALUES(deductions_breakdown),
                    child_fees_breakdown = VALUES(child_fees_breakdown),
                    signed_by = VALUES(signed_by),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $payslipData['staff_id'],
                $payslipData['payroll_month'],
                $payslipData['payroll_year'],
                $payslipData['basic_salary'],
                $payslipData['allowances_total'],
                $payslipData['gross_salary'],
                $payslipData['paye_tax'],
                $payslipData['nssf_contribution'],
                $payslipData['nhif_contribution'],
                $payslipData['housing_levy'],
                $payslipData['loan_deduction'],
                $payslipData['sacco_deduction'],
                $payslipData['salary_advance_deduction'],
                $payslipData['child_fees_deduction'],
                $payslipData['other_deductions_total'],
                $payslipData['net_salary'],
                $payslipData['payslip_status'],
                $payslipData['payment_status'],
                $payslipData['allowances_breakdown'],
                $payslipData['deductions_breakdown'],
                $payslipData['child_fees_breakdown'],
                $payslipData['signed_by']
            ]);

            $payslipId = $this->db->lastInsertId() ?: $this->getPayslipId($staffId, $payrollMonth, $payrollYear);

            // Store line items
            $this->storePayslipLineItems($payslipId, $allowancesBreakdown, $deductionsBreakdown, $childFeesBreakdown, [
                'paye' => $paye,
                'nssf' => $nssfContribution,
                'nhif' => $nhifContribution,
                'housing_levy' => $housingLevy
            ]);

            $this->logAction('create', $payslipId, "Generated detailed payslip for staff $staffId period $payrollYear-$payrollMonth");

            return formatResponse(true, [
                'payslip_id' => $payslipId,
                'staff' => [
                    'id' => $staffId,
                    'staff_no' => $staff['staff_no'],
                    'name' => $staff['first_name'] . ' ' . $staff['last_name'],
                    'position' => $staff['position'],
                    'department' => $staff['department_name'],
                    'bank_account' => $staff['bank_account'],
                    'kra_pin' => $staff['kra_pin'],
                    'nssf_no' => $staff['nssf_no'],
                    'nhif_no' => $staff['nhif_no']
                ],
                'period' => [
                    'month' => $payrollMonth,
                    'year' => $payrollYear,
                    'display' => date('F Y', mktime(0, 0, 0, $payrollMonth, 1, $payrollYear))
                ],
                'earnings' => [
                    'basic_salary' => $basicSalary,
                    'allowances' => $allowancesBreakdown,
                    'total_allowances' => $totalAllowances,
                    'gross_salary' => $grossSalary
                ],
                'statutory_deductions' => [
                    'paye' => $paye,
                    'nssf' => $nssfContribution,
                    'nhif' => $nhifContribution,
                    'housing_levy' => $housingLevy,
                    'total' => $paye + $nssfContribution + $nhifContribution + $housingLevy
                ],
                'other_deductions' => [
                    'loans' => $loanDeduction,
                    'sacco' => $saccoDeduction,
                    'salary_advance' => $advanceDeduction,
                    'breakdown' => $deductionsBreakdown
                ],
                'child_fees' => [
                    'total' => $childFeesDeduction,
                    'children_count' => count($childFeesBreakdown),
                    'breakdown' => $childFeesBreakdown
                ],
                'summary' => [
                    'gross_salary' => $grossSalary,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary
                ],
                'status' => 'draft'
            ], 'Detailed payslip generated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Store payslip line items
     */
    private function storePayslipLineItems($payslipId, $allowances, $deductions, $childFees, $statutory)
    {
        // Delete existing line items
        $stmt = $this->db->prepare("DELETE FROM payslip_items WHERE payslip_id = ?");
        $stmt->execute([$payslipId]);

        $insertStmt = $this->db->prepare("
            INSERT INTO payslip_items (payslip_id, item_type, item_code, item_name, description, amount, is_taxable, reference_id, reference_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Allowances
        foreach ($allowances as $a) {
            $insertStmt->execute([
                $payslipId,
                'allowance',
                strtoupper($a['type']),
                $a['name'],
                null,
                $a['amount'],
                $a['is_taxable'] ? 1 : 0,
                $a['id'] ?? null,
                'staff_allowances'
            ]);
        }

        // Statutory deductions
        $statutoryItems = [
            ['PAYE', 'Pay As You Earn (Tax)', $statutory['paye']],
            ['NSSF', 'NSSF Contribution', $statutory['nssf']],
            ['NHIF', 'NHIF Contribution', $statutory['nhif']],
            ['HOUSING', 'Housing Levy', $statutory['housing_levy']]
        ];
        foreach ($statutoryItems as $si) {
            if ($si[2] > 0) {
                $insertStmt->execute([
                    $payslipId,
                    'statutory',
                    $si[0],
                    $si[1],
                    null,
                    $si[2],
                    0,
                    null,
                    null
                ]);
            }
        }

        // Other deductions
        foreach ($deductions as $d) {
            $insertStmt->execute([
                $payslipId,
                'deduction',
                $d['code'],
                $d['name'],
                $d['reference'] ?? null,
                $d['amount'],
                0,
                $d['id'] ?? null,
                'staff_deductions'
            ]);
        }

        // Child fees
        foreach ($childFees as $cf) {
            $insertStmt->execute([
                $payslipId,
                'child_fees',
                'CHILD_FEES',
                'School Fees - ' . $cf['student_name'],
                $cf['class'] . ' (Child #' . $cf['child_number'] . ', ' . $cf['staff_discount_percentage'] . '% discount)',
                $cf['monthly_deduction'],
                0,
                $cf['staff_child_id'],
                'staff_children'
            ]);
        }
    }

    /**
     * Get payslip ID
     */
    private function getPayslipId($staffId, $month, $year)
    {
        $stmt = $this->db->prepare("SELECT id FROM payslips WHERE staff_id = ? AND payroll_month = ? AND payroll_year = ?");
        $stmt->execute([$staffId, $month, $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['id'] : null;
    }

    /**
     * Calculate NHIF contribution based on salary bands
     */
    private function calculateNHIF($grossSalary)
    {
        // NHIF rates as per Kenya rates 2024
        $bands = [
            [0, 5999, 150],
            [6000, 7999, 300],
            [8000, 11999, 400],
            [12000, 14999, 500],
            [15000, 19999, 600],
            [20000, 24999, 750],
            [25000, 29999, 850],
            [30000, 34999, 900],
            [35000, 39999, 950],
            [40000, 44999, 1000],
            [45000, 49999, 1100],
            [50000, 59999, 1200],
            [60000, 69999, 1300],
            [70000, 79999, 1400],
            [80000, 89999, 1500],
            [90000, 99999, 1600],
            [100000, PHP_INT_MAX, 1700]
        ];

        foreach ($bands as $band) {
            if ($grossSalary >= $band[0] && $grossSalary <= $band[1]) {
                return $band[2];
            }
        }

        return 1700; // Maximum
    }

    /**
     * Calculate PAYE (Kenya tax rates 2024)
     */
    private function calculatePAYE($taxableIncome)
    {
        // Monthly tax bands
        $bands = [
            [0, 24000, 0.10],       // 10% on first 24,000
            [24001, 32333, 0.25],   // 25% on 24,001 - 32,333
            [32334, 500000, 0.30],  // 30% on 32,334 - 500,000
            [500001, 800000, 0.325], // 32.5% on 500,001 - 800,000
            [800001, PHP_INT_MAX, 0.35] // 35% above 800,000
        ];

        $tax = 0;
        $remaining = $taxableIncome;

        foreach ($bands as $band) {
            if ($remaining <= 0)
                break;

            $bandMin = $band[0];
            $bandMax = $band[1];
            $rate = $band[2];

            $taxableInBand = min($remaining, $bandMax - $bandMin + 1);
            if ($taxableInBand > 0) {
                $tax += $taxableInBand * $rate;
                $remaining -= $taxableInBand;
            }
        }

        // Personal relief (monthly)
        $personalRelief = 2400;
        $tax = max(0, $tax - $personalRelief);

        return round($tax, 2);
    }
}

