<?php
namespace App\API\Modules\attendance;

use App\Database\Database;
use PDO;

class StudentAttendanceManager
{
    protected $db;
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($studentId, $date, $status, $classId = null, $termId = null, $markedBy = null)
    {
        // Use procedure for insert
        $sql = "CALL sp_bulk_mark_student_attendance(:class_id, :date, :attendance_json, :marked_by)";
        $attendance = [['student_id' => $studentId, 'status' => $status]];
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'class_id' => $classId,
            'date' => $date,
            'attendance_json' => json_encode($attendance),
            'marked_by' => $markedBy
        ]);
        return true;
    }

    /**
     * Get full attendance history for a student, grouped by academic year, term, and class.
     * @param int $studentId
     * @return array
     */
    public function getStudentAttendanceHistory($studentId)
    {
        $sql = "SELECT sa.*,
                       CONCAT(s.first_name, ' ', s.last_name) as student_name
                FROM student_attendance sa
                JOIN students s ON sa.student_id = s.id
                WHERE sa.student_id = ?
                ORDER BY sa.date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance summary for a student by year, term, and class.
     * @param int $studentId
     * @return array
     */
    public function getStudentAttendanceSummary($studentId)
    {
        $sql = "SELECT sa.term_id,
                       COUNT(*) as total_days,
                       SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_days,
                       SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                       SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_days
                FROM student_attendance sa
                WHERE sa.student_id = ?
                GROUP BY sa.term_id
                ORDER BY sa.term_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance for all students in a class for a given term and year.
     * @param int $classId
     * @param int $termId
     * @param int $yearId
     * @return array
     */
    public function getClassAttendance($classId, $termId = null, $yearId = null)
    {
        $sql = "SELECT sa.*,
                       CONCAT(s.first_name, ' ', s.last_name) as student_name
                FROM student_attendance sa
                JOIN students s ON sa.student_id = s.id
                WHERE sa.class_id = ?";
        $params = [$classId];
        if ($termId) {
            $sql .= " AND sa.term_id = ?";
            $params[] = $termId;
        }
        $sql .= " ORDER BY sa.date DESC, s.first_name, s.last_name LIMIT 500";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance percentage for a student for a given term and year.
     * @param int $studentId
     * @param int $termId
     * @param int $yearId
     * @return float
     */
    public function getAttendancePercentage($studentId, $termId = null, $yearId = null)
    {
        $sql = "SELECT COUNT(*) as total_days, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days FROM student_attendance WHERE student_id = ?";
        $params = [$studentId];
        if ($termId) {
            $sql .= " AND term_id = ?";
            $params[] = $termId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($row['total_days'] ?? 0);
        $present = (int) ($row['present_days'] ?? 0);
        if ($total > 0) {
            return round(100 * $present / $total, 2);
        }
        return 0.0;
    }

    /**
     * Get students with chronic absenteeism (e.g., >20% absent in a term/year/class).
     * @param int $classId
     * @param int $termId
     * @param int $yearId
     * @param float $threshold (e.g., 0.2 for 20%)
     * @return array
     */
    public function getChronicAbsentees($classId, $termId = null, $yearId = null, $threshold = 0.2)
    {
        $sql = "SELECT sa.student_id,
                       CONCAT(s.first_name, ' ', s.last_name) as student_name,
                       COUNT(*) as total_days,
                       SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                       (SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) / COUNT(*)) as absent_ratio
                FROM student_attendance sa
                JOIN students s ON sa.student_id = s.id
                WHERE sa.class_id = ?";
        $params = [$classId];
        if ($termId) {
            $sql .= " AND sa.term_id = ?";
            $params[] = $termId;
        }
        $sql .= " GROUP BY sa.student_id, s.first_name, s.last_name
                HAVING absent_ratio > ?
                ORDER BY absent_ratio DESC";
        $params[] = $threshold;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function read($studentId, $date = null, $classId = null, $termId = null)
    {
        $sql = "SELECT * FROM student_attendance WHERE student_id = :student_id";
        $params = ['student_id' => $studentId];
        if ($date) {
            $sql .= " AND date = :date";
            $params['date'] = $date;
        }
        if ($classId) {
            $sql .= " AND class_id = :class_id";
            $params['class_id'] = $classId;
        }
        if ($termId) {
            $sql .= " AND term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($attendanceId, $status)
    {
        $sql = "UPDATE student_attendance SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status, 'id' => $attendanceId]);
        return true;
    }

    public function delete($attendanceId)
    {
        $sql = "DELETE FROM student_attendance WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $attendanceId]);
        return true;
    }
}
