<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class StaffReportManager extends BaseAPI
{
    public function getTotalStaff($filters = [])
    {
        // Example: Count staff by type (teaching/non-teaching)
        $sql = "SELECT staff_type, COUNT(*) as total
                FROM staff
                WHERE status = 'active'
                GROUP BY staff_type";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getStaffAttendanceRates($filters = [])
    {
        // Example: Calculate attendance rates by month
        $sql = "SELECT staff_id, YEAR(date) as year, MONTH(date) as month,
                       SUM(status = 'present') as present_days,
                       COUNT(*) as total_days,
                       (SUM(status = 'present')/COUNT(*))*100 as attendance_rate
                FROM staff_attendance
                GROUP BY staff_id, year, month";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getActiveStaffCount($filters = [])
    {
        // Example: Count active staff
        $sql = "SELECT COUNT(*) as active_staff FROM staff WHERE status = 'active'";
        $stmt = $this->db->query($sql);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    public function getStaffLoanStats($filters = [])
    {
        // Example: Sum staff loans by status
        $sql = "SELECT status, COUNT(*) as loan_count, SUM(principal_amount) as total_principal
                FROM staff_loans
                GROUP BY status";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getPayrollSummary($filters = [])
    {
        // Example: Sum payroll by month
        $sql = "SELECT payroll_month, payroll_year, SUM(net_salary) as total_payroll
                FROM staff_payroll
                GROUP BY payroll_year, payroll_month
                ORDER BY payroll_year DESC, payroll_month DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
