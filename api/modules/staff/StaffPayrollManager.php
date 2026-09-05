<?php
namespace App\API\Modules\staff;

use App\API\Includes\BaseAPI;
use App\API\Modules\finance\FeeManager;
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
        // A payslip cannot be marked paid from a self-service/read model. The
        // Finance disbursement workflow is the only writer because it records
        // the provider transaction and waits for its callback.
        return formatResponse(false, null, 'Direct payroll payment recording is disabled. Use the approved Finance disbursement workflow.');
    }
    /**
     * Calculate payroll for a staff member for a given period
     * Used by PayrollWorkflow (admin payroll processing)
     * @param array $data [staff_id, payroll_month, payroll_year]
     * @return array Response with gross_salary, net_salary, breakdown, etc.
     */
    public function calculatePayroll($data)
    {
        // Payroll calculation is a Finance responsibility.  This method is
        // retained only for compatibility with old callers and must never
        // write a payslip or payroll run.
        return formatResponse(false, null, 'Legacy staff payroll calculation is disabled. Use the Finance payroll workflow.');

        /* Legacy writable calculator retained below temporarily for source
         * compatibility while old deployments are migrated. */
        try {
            $required = ['staff_id', 'payroll_month', 'payroll_year'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $staffId = $data['staff_id'];
            $month = $data['payroll_month'];
            $year = $data['payroll_year'];
            $period = sprintf('%04d-%02d', $year, $month);
            $periodStart = sprintf('%04d-%02d-01', $year, $month);
            $periodEnd = date('Y-m-t', strtotime($periodStart));

            $status = $data['status'] ?? 'pending';

            // Payroll eligibility gate: staff must have all statutory IDs,
            // payment details, at least one role, and a salary set.
            $eligibilitySql = "SELECT
                s.staff_no, COALESCE(spp.basic_salary, 0) AS salary, spp.bank_name, spp.bank_account,
                p.phone,
                spp.kra_pin, spp.nssf_no, spp.nhif_no,
                (SELECT COUNT(DISTINCT ur.role_id)
                 FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 WHERE u.person_id = s.person_id) AS role_count,
                (SELECT sda.department_id
                 FROM staff_department_assignments sda
                 WHERE sda.staff_id = s.id
                   AND sda.effective_to IS NULL
                 LIMIT 1) AS department_id
            FROM staff s
            INNER JOIN persons p ON p.id = s.person_id
            LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
            WHERE s.id = ? AND s.status = 'active'";
            $eligStmt = $this->db->prepare($eligibilitySql);
            $eligStmt->execute([$staffId]);
            $profile = $eligStmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                return formatResponse(false, null, 'Staff is not active or does not exist');
            }

            $checks = [
                'staff_no' => 'Staff number', 'department_id' => 'Department',
                'role_count' => 'Assigned role', 'salary' => 'Basic salary',
                'kra_pin' => 'KRA PIN', 'nssf_no' => 'NSSF number',
                'nhif_no' => 'NHIF/SHIF number', 'phone' => 'Phone number',
                'bank_name' => 'Bank name', 'bank_account' => 'Bank account',
            ];
            $eligMissing = [];
            foreach ($checks as $field => $label) {
                $val = $profile[$field] ?? null;
                if ($field === 'role_count' && (int) $val < 1) { $eligMissing[] = $label; continue; }
                if ($field === 'salary' && (float) $val <= 0) { $eligMissing[] = $label; continue; }
                if ($val === null || trim((string) $val) === '') { $eligMissing[] = $label; }
            }
            if (!empty($eligMissing)) {
                return formatResponse(false, null, 'Staff not payroll eligible. Missing: ' . implode(', ', $eligMissing));
            }

            $baseSalary = (float) ($profile['salary'] ?? 0);

            // Fetch allowances
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) AS total
                 FROM staff_allowances
                 WHERE staff_id = ?
                   AND status = 'active'
                   AND effective_date <= ?
                   AND (end_date IS NULL OR end_date >= ?)"
            );
            $stmt->execute([$staffId, $periodEnd, $periodStart]);
            $totalAllowances = (float) $stmt->fetchColumn();

            // Fetch deductions
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) AS total
                 FROM staff_deductions
                 WHERE staff_id = ?
                   AND status = 'active'
                   AND effective_date <= ?
                   AND (end_date IS NULL OR end_date >= ?)"
            );
            $stmt->execute([$staffId, $periodEnd, $periodStart]);
            $totalOtherDeductions = (float) $stmt->fetchColumn();

            // Loan deductions (active loans)
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(agreed_monthly_deduction), 0)
                 FROM staff_loans
                 WHERE staff_id = ?
                   AND status = 'active'"
            );
            $stmt->execute([$staffId]);
            $loanDeduction = (float) $stmt->fetchColumn();

            // Child fee deductions
            $childFeesResult = $this->calculateChildFeeDeductions($staffId, $month, $year);
            $childFeeDeduction = 0.0;
            if (!empty($childFeesResult['success']) && !empty($childFeesResult['data'])) {
                $childFeeDeduction = (float) ($childFeesResult['data']['total_child_fee_deduction'] ?? 0);
            }

            $grossSalary = $baseSalary + $totalAllowances;

            // Statutory deductions (use stored procedures where available)
            $payeTax = $this->getStatutoryAmount('sp_calculate_paye_tax', $grossSalary, $year);
            if ($payeTax === null) {
                $payeTax = $this->calculatePAYE($grossSalary);
            }

            $nssf = $this->getStatutoryAmount('sp_calculate_nssf_contribution', $grossSalary, $year);
            if ($nssf === null) {
                $nssf = $this->calculateNSSF($grossSalary);
            }

            $nhif = $this->getStatutoryAmount('sp_calculate_nhif_contribution', $grossSalary, $year);
            if ($nhif === null) {
                $nhif = $this->calculateNHIF($grossSalary);
            }

            $otherDeductions = $totalOtherDeductions + $loanDeduction + $childFeeDeduction;
            $totalDeductions = $payeTax + $nssf + $nhif + $otherDeductions;
            $netSalary = $grossSalary - $totalDeductions;

            $existingId = null;
            $stmt = $this->db->prepare(
                "SELECT id FROM payslips WHERE staff_id = ? AND payroll_month = ? AND payroll_year = ? LIMIT 1"
            );
            $stmt->execute([$staffId, $month, $year]);
            $existingId = $stmt->fetchColumn() ?: null;

            // Ensure a payroll run header exists for the period (manual-id master row).
            $runStmt = $this->db->prepare("SELECT id FROM payroll_runs WHERE month = ? AND year = ?");
            $runStmt->execute([$month, $year]);
            $runId = $runStmt->fetchColumn();
            if (!$runId) {
                $runId = $this->nextId('payroll_runs');
                $runStatus = in_array($status, ['draft', 'processing', 'approved', 'paid'], true) ? $status : 'draft';
                $runStmt = $this->db->prepare(
                    "INSERT INTO payroll_runs (id, month, year, status, created_by)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $runStmt->execute([$runId, $month, $year, $runStatus, $this->user_id]);
            }

            $payslipStatus = in_array($status, ['draft', 'approved', 'paid', 'cancelled'], true) ? $status : 'draft';
            $legacyExtras = json_encode([
                'total_deductions' => $totalDeductions,
                'other_deductions' => $otherDeductions,
                'deductions' => $otherDeductions,
                'status' => $status,
                'payroll_period' => $period,
            ]);

            if ($existingId) {
                $stmt = $this->db->prepare(
                    "UPDATE payslips SET
                        basic_salary = ?,
                        allowances_total = ?,
                        gross_salary = ?,
                        paye_tax = ?,
                        nssf_contribution = ?,
                        nhif_contribution = ?,
                        loan_deduction = ?,
                        child_fees_deduction = ?,
                        other_deductions_total = ?,
                        net_salary = ?,
                        payslip_status = ?,
                        deductions_breakdown = ?,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?"
                );
                $stmt->execute([
                    $baseSalary,
                    $totalAllowances,
                    $grossSalary,
                    $payeTax,
                    $nssf,
                    $nhif,
                    $loanDeduction,
                    $childFeeDeduction,
                    $otherDeductions,
                    $netSalary,
                    $payslipStatus,
                    $legacyExtras,
                    $existingId
                ]);
                $payrollId = (int) $existingId;
            } else {
                $stmt = $this->db->prepare(
                    "INSERT INTO payslips (
                        staff_id,
                        payroll_month,
                        payroll_year,
                        basic_salary,
                        allowances_total,
                        gross_salary,
                        paye_tax,
                        nssf_contribution,
                        nhif_contribution,
                        loan_deduction,
                        child_fees_deduction,
                        other_deductions_total,
                        net_salary,
                        payslip_status,
                        payment_status,
                        deductions_breakdown
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
                );
                $stmt->execute([
                    $staffId,
                    $month,
                    $year,
                    $baseSalary,
                    $totalAllowances,
                    $grossSalary,
                    $payeTax,
                    $nssf,
                    $nhif,
                    $loanDeduction,
                    $childFeeDeduction,
                    $otherDeductions,
                    $netSalary,
                    $payslipStatus,
                    $legacyExtras
                ]);
                $payrollId = (int) $this->db->lastInsertId();
            }

            // Optionally, insert or update payslip record here if needed by workflow

            return formatResponse(true, [
                'staff_id' => $staffId,
                'payroll_month' => $month,
                'payroll_year' => $year,
                'payroll_period' => $period,
                'payroll_id' => $payrollId,
                'gross_salary' => $grossSalary,
                'net_salary' => $netSalary,
                'base_salary' => $baseSalary,
                'total_allowances' => $totalAllowances,
                'total_deductions' => $totalDeductions,
                'paye_tax' => $payeTax,
                'nssf_deduction' => $nssf,
                'nhif_deduction' => $nhif,
                'other_deductions' => $otherDeductions,
                'status' => $status
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
                    CONCAT(p.first_name, ' ', p.last_name) AS staff_name,
                    s.position, s.bank_account, spp.nssf_no, spp.nhif_no, spp.kra_pin,
                    spp.bank_name, st.name AS staff_type, d.name AS department_name,
                    CONCAT(ap.first_name, ' ', ap.last_name) AS approved_by_name
                FROM payslips ps
                INNER JOIN staff s ON ps.staff_id = s.id
                INNER JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
                LEFT JOIN departments d ON d.id = sda.department_id
                LEFT JOIN users approver ON ps.signed_by = approver.id
                LEFT JOIN persons ap ON ap.id = approver.person_id
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
                // LIMIT placeholders are inconsistently handled by PDO MySQL
                // when execute(array) binds every value as a string. Normalise
                // and append a bounded integer instead.
                $limit = max(1, min(100, (int) $filters['limit']));
                $sql .= " LIMIT {$limit}";
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

            if (($result['status'] ?? '') === 'success') {
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

            if (($result['status'] ?? '') !== 'success') {
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
            $stmt = $this->db->prepare("
                SELECT s.id, p.first_name, p.last_name
                FROM students s
                INNER JOIN persons p ON p.id = s.person_id
                WHERE s.id = ? AND s.status = 'active'
            ");
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
                (staff_id, student_id, relationship, fee_deduction_enabled, fee_deduction_percentage, fee_deduction_amount, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $staffId,
                $data['student_id'],
                $data['relationship'],
                $data['fee_deduction_enabled'] ?? 1,
                $data['fee_deduction_percentage'] ?? 100.00,
                $data['fee_deduction_amount'] ?? null,
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

            $allowedFields = ['fee_deduction_enabled', 'fee_deduction_percentage', 'fee_deduction_amount', 'notes', 'relationship'];
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
            $stmt = $this->db->prepare("SELECT setting_key AS config_key, setting_value AS config_value, NULL AS description FROM school_settings WHERE setting_key LIKE 'staff_child_fee_%'");
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
            $stmt = $this->db->prepare("SELECT COALESCE(spp.basic_salary, 0) AS salary FROM staff s LEFT JOIN staff_payroll_profiles spp ON spp.staff_id=s.id WHERE s.id = ?");
            $stmt->execute([$staffId]);
            $staffRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$staffRow) {
                return formatResponse(false, null, 'Staff not found');
            }
            $staffSalary = floatval($staffRow['salary']);
            $maxDeductible = $staffSalary * ($maxDeductionPct / 100);

            $feeManager = new FeeManager();
            $invoiceWarnings = [];

            // Get active children
            $stmt = $this->db->prepare("
                SELECT
                    staff_child_id,
                    student_id,
                    fee_deduction_enabled,
                    fee_deduction_percentage,
                    fee_deduction_amount,
                    student_name,
                    is_sponsored,
                    sponsor_waiver_percentage,
                    class_name,
                    stream_name,
                    created_at
                FROM vw_staff_children_fees
                WHERE staff_id = ?
                  AND fee_deduction_enabled = 1
                ORDER BY created_at ASC
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

                $invoiceData = null;
                $invoiceResult = $feeManager->getStudentInvoice($child['student_id']);
                if (!empty($invoiceResult) && ($invoiceResult['status'] ?? '') === 'success') {
                    $invoiceData = $invoiceResult['data'] ?? null;
                } else {
                    $generateResult = $feeManager->generateStudentInvoice(
                        $child['student_id'],
                        null,
                        null,
                        $this->user_id
                    );
                    if (!empty($generateResult) && ($generateResult['status'] ?? '') === 'success') {
                        $invoiceData = $generateResult['data'] ?? null;
                    } else {
                        $invoiceWarnings[] = [
                            'student_id' => $child['student_id'],
                            'staff_child_id' => $child['staff_child_id'],
                            'message' => $generateResult['message'] ?? $invoiceResult['message'] ?? 'Invoice not available'
                        ];
                    }
                }

                $grossFees = floatval($invoiceData['balance'] ?? 0);
                $sponsorWaiver = 0;

                // Apply sponsor waiver if applicable
                if ($child['is_sponsored'] && $child['sponsor_waiver_percentage'] > 0) {
                    $sponsorWaiver = $grossFees * ($child['sponsor_waiver_percentage'] / 100);
                    $grossFees -= $sponsorWaiver;
                }

                // Apply staff discount
                $staffDiscount = $grossFees * ($discountRate / 100);
                $deductibleAmount = $grossFees - $staffDiscount;

                // The director may authorize either a fixed monthly amount or
                // a percentage. A fixed amount takes precedence when present.
                if (isset($child['fee_deduction_amount']) && (float) $child['fee_deduction_amount'] > 0) {
                    $monthlyDeduction = min((float) $child['fee_deduction_amount'], $deductibleAmount);
                } elseif ($child['fee_deduction_percentage'] < 100) {
                    $deductibleAmount = $deductibleAmount * ($child['fee_deduction_percentage'] / 100);
                    $monthlyDeduction = $deductibleAmount / 3;
                } else {
                    $monthlyDeduction = $deductibleAmount / 3;
                }

                $childDeductions[] = [
                    'staff_child_id' => $child['staff_child_id'],
                    'student_id' => $child['student_id'],
                    'student_name' => $child['student_name'],
                    'class' => $child['class_name'] . ' ' . ($child['stream_name'] ?? ''),
                    'child_number' => $childNumber,
                    'gross_fees' => $grossFees,
                    'sponsor_waiver' => $sponsorWaiver,
                    'staff_discount_percentage' => $discountRate,
                    'staff_discount_amount' => $staffDiscount,
                    'deductible_amount' => $deductibleAmount,
                    'monthly_deduction' => round($monthlyDeduction, 2),
                    'fee_invoice_id' => $invoiceData['id'] ?? null
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
                'children_breakdown' => $childDeductions,
                'invoice_warnings' => $invoiceWarnings
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
        // This compatibility method is now read-only.  Finance creates the
        // payslip; staff services may only retrieve the approved calculation.
        $existing = $this->db->prepare('SELECT id FROM payslips WHERE staff_id = ? AND payroll_month = ? AND payroll_year = ? LIMIT 1');
        $existing->execute([(int) $staffId, (int) $payrollMonth, (int) $payrollYear]);
        if (!$existing->fetchColumn()) {
            return formatResponse(false, null, 'No payslip exists for this period. Generate it through the Finance payroll workflow.');
        }
        return $this->viewPayslip($staffId, ['payroll_month' => $payrollMonth, 'payroll_year' => $payrollYear]);

        /* Legacy writable payslip generator retained below temporarily for
         * source compatibility while old deployments are migrated. */
        try {
            // Get staff details
            $stmt = $this->db->prepare("
                SELECT s.*, p.first_name, p.last_name, d.name AS department_name,
                       st.name AS staff_type_name,
                       spp.kra_pin, spp.nssf_no, spp.nhif_no,
                       COALESCE(spp.basic_salary, 0) AS salary
                FROM staff s
                INNER JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
                LEFT JOIN departments d ON d.id = sda.department_id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
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
            // Statutory values are effective-dated database rules.
            $nssfContribution = $this->calculateNSSF($grossSalary, (int)date('Y'));
            $taxableIncome -= $nssfContribution; // NSSF is tax deductible

            $housingRule = $this->statutoryRule('Housing Levy', 'employee_employer_contribution', (int)date('Y'));
            $housingLevy = round($grossSalary * ((float)($housingRule['employee_rate'] ?? 0)) / 100, 2);

            // NHIF is retained only as a legacy field name; the calculation is SHIF.
            $nhifContribution = $this->calculateSHIF($grossSalary, (int)date('Y'));

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

            if (($childFeesResult['status'] ?? '') === 'success' && !empty($childFeesResult['data']['children_breakdown'])) {
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
     * Generate the next id for manual-id tables (e.g. payroll_runs).
     */
    private function nextId($table)
    {
        $stmt = $this->db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM `{$table}`");
        return (int) $stmt->fetchColumn();
    }

    private function statutoryRule($agency, $ruleCode, $year = null)
    {
        $asOf = sprintf('%04d-12-31', (int)($year ?: date('Y')));
        $stmt = $this->db->prepare("SELECT id, employee_rate, employer_rate,
                    lower_earnings_limit, upper_earnings_limit, personal_relief
                FROM statutory_rule_versions
                WHERE agency = ? AND rule_code = ? AND active = 1
                  AND effective_from <= ?
                  AND (effective_to IS NULL OR effective_to >= ?)
                ORDER BY effective_from DESC, id DESC LIMIT 1");
        $stmt->execute([$agency, $ruleCode, $asOf, $asOf]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function calculateSHIF($grossSalary, $year = null)
    {
        $rule = $this->statutoryRule('SHIF', 'employee_contribution', $year);
        return round(max(0, (float)$grossSalary) * ((float)($rule['employee_rate'] ?? 0)) / 100, 2);
    }

    private function calculateNHIF($grossSalary, $year = null)
    {
        return $this->calculateSHIF($grossSalary, $year);
    }

    private function calculateNSSF($grossSalary, $year = null)
    {
        $rule = $this->statutoryRule('NSSF', 'employee_employer_contribution', $year);
        $rate = (float)($rule['employee_rate'] ?? 0);
        $lower = (float)($rule['lower_earnings_limit'] ?? 0);
        $upper = (float)($rule['upper_earnings_limit'] ?? 0);
        if ($rate <= 0 || $upper <= 0) return 0;
        $gross = max(0, (float)$grossSalary);
        return round((min($gross, $lower) + max(0, min($gross, $upper) - $lower)) * $rate / 100, 2);
    }

    /**
     * Use DB stored procedures for statutory calculations if available.
     */
    private function getStatutoryAmount($procedureName, $grossSalary, $year)
    {
        try {
            $stmt = $this->db->prepare("CALL {$procedureName}(?, ?, @amount)");
            $stmt->execute([$grossSalary, $year]);
            $stmt->closeCursor();

            $result = $this->db->query("SELECT @amount AS amount")->fetch(PDO::FETCH_ASSOC);
            if ($result && $result['amount'] !== null) {
                return round((float) $result['amount'], 2);
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    private function calculatePAYE($taxableIncome, $year = null)
    {
        $rule = $this->statutoryRule('KRA', 'paye_bands', $year);
        if (!$rule) return 0;
        $stmt = $this->db->prepare('SELECT lower_bound, upper_bound, tax_rate
            FROM statutory_tax_bands WHERE rule_version_id = ? ORDER BY band_order');
        $stmt->execute([(int)$rule['id']]);
        $income = max(0, (float)$taxableIncome);
        $tax = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $band) {
            $lower = (float)$band['lower_bound'];
            $upper = $band['upper_bound'] === null ? $income : (float)$band['upper_bound'];
            $tax += max(0, min($income, $upper) - $lower) * ((float)$band['tax_rate'] / 100);
        }
        return round(max(0, $tax - (float)($rule['personal_relief'] ?? 0)), 2);
    }
}
