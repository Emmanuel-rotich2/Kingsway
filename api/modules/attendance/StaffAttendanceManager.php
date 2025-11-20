<?php
namespace App\API\Modules\Attendance;

use App\Config\Database;
use PDO;

class StaffAttendanceManager
{
    protected $db;
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($staffId, $date, $status, $markedBy = null)
    {
        // Use procedure for insert
        $sql = "CALL sp_bulk_mark_staff_attendance(:department_id, :date, :status, :marked_by)";
        // department_id is required by procedure, you may need to fetch it for the staff
        // For demo, set to NULL
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'department_id' => null,
            'date' => $date,
            'status' => $status,
            'marked_by' => $markedBy
        ]);
        return true;
    }

    public function read($staffId, $date = null)
    {
        $sql = "SELECT * FROM staff_attendance WHERE staff_id = :staff_id";
        $params = ['staff_id' => $staffId];
        if ($date) {
            $sql .= " AND date = :date";
            $params['date'] = $date;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($attendanceId, $status)
    {
        $sql = "UPDATE staff_attendance SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status, 'id' => $attendanceId]);
        return true;
    }

    public function delete($attendanceId)
    {
        $sql = "DELETE FROM staff_attendance WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $attendanceId]);
        return true;
    }
    /**
     * Get full attendance history for a staff member, grouped by academic year, term, and department.
     * @param int $staffId
     * @return array
     */
    public function getStaffAttendanceHistory($staffId)
    {
        $sql = "SELECT sa.*, d.department_name, t.term_name, ay.year_name
                FROM staff_attendance sa
                LEFT JOIN departments d ON sa.department_id = d.id
                LEFT JOIN academic_terms t ON sa.term_id = t.id
                LEFT JOIN academic_years ay ON sa.academic_year = ay.id
                WHERE sa.staff_id = ?
                ORDER BY ay.year_name, t.term_name, d.department_name, sa.date";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance summary for a staff member by year, term, and department.
     * @param int $staffId
     * @return array
     */
    public function getStaffAttendanceSummary($staffId)
    {
        $sql = "SELECT ay.year_name, t.term_name, d.department_name,
                       COUNT(*) as total_days,
                       SUM(sa.status = 'present') as present_days,
                       SUM(sa.status = 'absent') as absent_days,
                       SUM(sa.status = 'late') as late_days
                FROM staff_attendance sa
                LEFT JOIN departments d ON sa.department_id = d.id
                LEFT JOIN academic_terms t ON sa.term_id = t.id
                LEFT JOIN academic_years ay ON sa.academic_year = ay.id
                WHERE sa.staff_id = ?
                GROUP BY ay.year_name, t.term_name, d.department_name
                ORDER BY ay.year_name, t.term_name, d.department_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance for all staff in a department for a given term and year.
     * @param int $departmentId
     * @param int $termId
     * @param int $yearId
     * @return array
     */
    public function getDepartmentAttendance($departmentId, $termId, $yearId)
    {
        $sql = "SELECT sa.*, s.staff_name
                FROM staff_attendance sa
                JOIN staff s ON sa.staff_id = s.id
                WHERE sa.department_id = ? AND sa.term_id = ? AND sa.academic_year = ?
                ORDER BY sa.date, s.staff_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departmentId, $termId, $yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance percentage for a staff member for a given term and year.
     * @param int $staffId
     * @param int $termId
     * @param int $yearId
     * @return float
     */
    public function getAttendancePercentage($staffId, $termId, $yearId)
    {
        $sql = "SELECT COUNT(*) as total_days,
                       SUM(status = 'present') as present_days
                FROM staff_attendance
                WHERE staff_id = ? AND term_id = ? AND academic_year = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$staffId, $termId, $yearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['total_days'] > 0) {
            return round(100 * $row['present_days'] / $row['total_days'], 2);
        }
        return 0.0;
    }

    /**
     * Get staff with chronic absenteeism (e.g., >20% absent in a term/year/department).
     * @param int $departmentId
     * @param int $termId
     * @param int $yearId
     * @param float $threshold (e.g., 0.2 for 20%)
     * @return array
     */
    public function getChronicAbsentees($departmentId, $termId, $yearId, $threshold = 0.2)
    {
        $sql = "SELECT sa.staff_id, s.staff_name,
                       COUNT(*) as total_days,
                       SUM(sa.status = 'absent') as absent_days,
                       (SUM(sa.status = 'absent') / COUNT(*)) as absent_ratio
                FROM staff_attendance sa
                JOIN staff s ON sa.staff_id = s.id
                WHERE sa.department_id = ? AND sa.term_id = ? AND sa.academic_year = ?
                GROUP BY sa.staff_id, s.staff_name
                HAVING absent_ratio > ?
                ORDER BY absent_ratio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departmentId, $termId, $yearId, $threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
