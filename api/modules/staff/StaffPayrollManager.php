<?php
namespace App\API\Modules\Staff;

use App\Config\Database;
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
    public function __construct()
    {
        parent::__construct();
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

            $this->beginTransaction();

            $stmt = $this->db->prepare("CALL sp_request_staff_advance(?, ?, ?, @request_id)");
            $stmt->execute([$staffId, $data['amount'], $data['reason']]);
            
            $result = $this->db->query("SELECT @request_id AS request_id")->fetch(PDO::FETCH_ASSOC);
            $requestId = $result['request_id'];

            $this->commit();
            $this->logAction('create', $requestId, "Staff ID $staffId requested advance of KES {$data['amount']}");

            return formatResponse(true, [
                'request_id' => $requestId,
                'amount' => $data['amount'],
                'status' => 'pending',
                'message' => 'Advance request submitted. Awaiting Finance approval.'
            ], 'Advance request submitted successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
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

            $this->beginTransaction();

            $stmt = $this->db->prepare("CALL sp_apply_staff_loan(?, ?, ?, ?, @loan_id)");
            $stmt->execute([$staffId, $data['loan_type'], $data['principal_amount'], $data['agreed_monthly_deduction']]);
            
            $result = $this->db->query("SELECT @loan_id AS loan_id")->fetch(PDO::FETCH_ASSOC);
            $loanId = $result['loan_id'];

            $this->commit();
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
                $this->rollback();
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
}
