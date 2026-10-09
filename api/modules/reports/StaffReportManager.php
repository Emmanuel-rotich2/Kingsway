<?php
namespace App\API\Modules\reports;
use App\API\Services\ReadReplicaService;
use App\API\Includes\BaseAPI;

class StaffReportManager extends BaseAPI
{
    public function getTotalStaff($filters = [])
    {
        // Count staff by type (teaching/non-teaching) with department breakdown
        try {
            $sql = "SELECT
                        st.name AS staff_type,
                        d.name AS department,
                        COUNT(*) AS total
                    FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " 
                    WHERE s.status = 'active'
                    GROUP BY st.name, d.id, d.name
                    ORDER BY st.name, d.name";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback without departments
            try {
                $sql2 = "SELECT s.staff_type_name AS staff_type, COUNT(*) as total
                         FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " s
                         WHERE s.staff_status = 'active'
                         GROUP BY s.staff_type_name";
                $stmt2 = $this->db->query($sql2);
                return $stmt2->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e2) {
                return [];
            }
        }
    }

    public function getStaffAttendanceRates($filters = [])
    {
        try {
            $where = ['1=1'];
            $params = [];
            if (!empty($filters['date_from'])) { $where[] = 'sa.date >= ?'; $params[] = $filters['date_from']; }
            if (!empty($filters['date_to'])) { $where[] = 'sa.date <= ?'; $params[] = $filters['date_to']; }
            if (!empty($filters['department_id'])) { $where[] = 'sda.department_id = ?'; $params[] = (int) $filters['department_id']; }
            $sql = "SELECT
                        COALESCE(d.name, 'Unassigned') AS department,
                        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS present,
                        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) AS absent,
                        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) AS late,
                        ROUND(
                            SUM(CASE WHEN sa.status IN ('present','late') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100,
                            2
                        ) AS attendance_rate
                    FROM staff_attendance sa
                    JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.id = sa.staff_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("staff_department_assignments") . " sda ON sda.staff_id = s.id
                      AND sda.effective_from <= sa.date
                      AND (sda.effective_to IS NULL OR sda.effective_to >= sa.date)
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("departments") . " d ON d.id = sda.department_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY d.id, d.name
                    ORDER BY department";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
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
                        SUM(CASE WHEN s.staff_type_id = 1 THEN 1 ELSE 0 END) AS teaching_staff,
                        SUM(CASE WHEN s.staff_type_id IS NULL OR s.staff_type_id != 1 THEN 1 ELSE 0 END) AS non_teaching_staff
                    FROM staff s
                    WHERE s.status = 'active'";
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
                    FROM vw_staff_payroll_summary
                    WHERE payslip_status IN ('approved','paid')
                    GROUP BY payroll_year, payroll_month
                    ORDER BY payroll_year DESC, payroll_month DESC";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
