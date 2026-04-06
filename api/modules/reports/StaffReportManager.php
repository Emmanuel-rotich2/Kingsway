<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class StaffReportManager extends BaseAPI
{
    public function getTotalStaff($filters = [])
    {
        // Count staff by type (teaching/non-teaching) with department breakdown
        try {
            $sql = "SELECT
                        staff_type,
                        d.name AS department,
                        COUNT(*) AS total
                    FROM staff s
                    LEFT JOIN departments d ON d.id = s.department_id
                    WHERE s.status = 'active'
                    GROUP BY s.staff_type, d.id, d.name
                    ORDER BY s.staff_type, d.name";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback without departments
            try {
                $sql2 = "SELECT staff_type, COUNT(*) as total FROM staff WHERE status = 'active' GROUP BY staff_type";
                $stmt2 = $this->db->query($sql2);
                return $stmt2->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e2) {
                return [];
            }
        }
    }

    public function getStaffAttendanceRates($filters = [])
    {
        // Calculate attendance rates by month
        try {
            $sql = "SELECT
                        staff_id,
                        YEAR(date) as year,
                        MONTH(date) as month,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
                        COUNT(*) AS total_days,
                        ROUND(
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100,
                            2
                        ) AS attendance_rate
                    FROM staff_attendance
                    GROUP BY staff_id, year, month
                    ORDER BY year DESC, month DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getActiveStaffCount($filters = [])
    {
        // Count active staff with breakdown
        try {
            $sql = "SELECT
                        COUNT(*) AS active_staff,
                        SUM(CASE WHEN staff_type = 'teaching' THEN 1 ELSE 0 END) AS teaching_staff,
                        SUM(CASE WHEN staff_type != 'teaching' THEN 1 ELSE 0 END) AS non_teaching_staff
                    FROM staff
                    WHERE status = 'active'";
            $stmt = $this->db->query($sql);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return ['active_staff' => 0, 'teaching_staff' => 0, 'non_teaching_staff' => 0];
        }
    }

    public function getStaffLoanStats($filters = [])
    {
        // Sum staff loans by status
        try {
            $sql = "SELECT status, COUNT(*) as loan_count, COALESCE(SUM(principal_amount), 0) as total_principal
                    FROM staff_loans
                    GROUP BY status
                    ORDER BY total_principal DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getPayrollSummary($filters = [])
    {
        // Sum payroll by month with headcount
        try {
            $sql = "SELECT
                        payroll_month,
                        payroll_year,
                        COUNT(DISTINCT staff_id) AS staff_count,
                        COALESCE(SUM(gross_salary), 0) AS total_gross,
                        COALESCE(SUM(total_deductions), 0) AS total_deductions,
                        COALESCE(SUM(net_salary), 0) AS total_net
                    FROM staff_payroll
                    GROUP BY payroll_year, payroll_month
                    ORDER BY payroll_year DESC, payroll_month DESC";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
