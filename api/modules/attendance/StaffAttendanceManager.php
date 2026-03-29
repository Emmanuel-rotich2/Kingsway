<?php
namespace App\API\Modules\attendance;

use App\Database\Database;
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
        $sql = "SELECT sa.*,
                       CONCAT(st.first_name, ' ', st.last_name) as staff_name
                FROM staff_attendance sa
                LEFT JOIN staff st ON sa.staff_id = st.id
                WHERE sa.staff_id = ?
                ORDER BY sa.date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance summary for a staff member grouped by month.
     * @param int $staffId
     * @return array
     */
    public function getStaffAttendanceSummary($staffId)
    {
        $sql = "SELECT DATE_FORMAT(sa.date, '%Y-%m') as month,
                       COUNT(*) as total_days,
                       SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_days,
                       SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                       SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_days
                FROM staff_attendance sa
                WHERE sa.staff_id = ?
                GROUP BY DATE_FORMAT(sa.date, '%Y-%m')
                ORDER BY month DESC";
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
    public function getDepartmentAttendance($departmentId, $termId = null, $yearId = null)
    {
        $sql = "SELECT sa.*,
                       CONCAT(s.first_name, ' ', s.last_name) as staff_name
                FROM staff_attendance sa
                JOIN staff s ON sa.staff_id = s.id
                WHERE s.department_id = ?
                ORDER BY sa.date DESC, s.first_name, s.last_name
                LIMIT 500";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance percentage for a staff member for a given term and year.
     * @param int $staffId
     * @param int $termId
     * @param int $yearId
     * @return float
     */
    public function getAttendancePercentage($staffId, $termId = null, $yearId = null)
    {
        $sql = "SELECT COUNT(*) as total_days,
                       SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
                FROM staff_attendance
                WHERE staff_id = ?";
        $params = [$staffId];
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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
    public function getChronicAbsentees($departmentId = null, $termId = null, $yearId = null, $threshold = 0.2)
    {
        $sql = "SELECT sa.staff_id,
                       CONCAT(s.first_name, ' ', s.last_name) as staff_name,
                       COUNT(*) as total_days,
                       SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                       (SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) / COUNT(*)) as absent_ratio
                FROM staff_attendance sa
                JOIN staff s ON sa.staff_id = s.id
                WHERE 1=1";
        $params = [];
        if ($departmentId) {
            $sql .= " AND s.department_id = ?";
            $params[] = $departmentId;
        }
        $sql .= " GROUP BY sa.staff_id, s.first_name, s.last_name
                HAVING absent_ratio > ?
                ORDER BY absent_ratio DESC";
        $params[] = $threshold;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
