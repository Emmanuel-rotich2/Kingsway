<?php
namespace App\API\Modules\Attendance;

use App\Config\Database;
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
        $sql = "SELECT sa.*, c.class_name, t.term_name, ay.year_name
                FROM student_attendance sa
                JOIN classes c ON sa.class_id = c.id
                JOIN academic_terms t ON sa.term_id = t.id
                JOIN academic_years ay ON sa.academic_year = ay.id
                WHERE sa.student_id = ?
                ORDER BY ay.year_name, t.term_name, c.class_name, sa.date";
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
        $sql = "SELECT ay.year_name, t.term_name, c.class_name,
                       COUNT(*) as total_days,
                       SUM(sa.status = 'present') as present_days,
                       SUM(sa.status = 'absent') as absent_days,
                       SUM(sa.status = 'late') as late_days
                FROM student_attendance sa
                JOIN classes c ON sa.class_id = c.id
                JOIN academic_terms t ON sa.term_id = t.id
                JOIN academic_years ay ON sa.academic_year = ay.id
                WHERE sa.student_id = ?
                GROUP BY ay.year_name, t.term_name, c.class_name
                ORDER BY ay.year_name, t.term_name, c.class_name";
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
    public function getClassAttendance($classId, $termId, $yearId)
    {
        $sql = "SELECT sa.*, s.student_name
                FROM student_attendance sa
                JOIN students s ON sa.student_id = s.id
                WHERE sa.class_id = ? AND sa.term_id = ? AND sa.academic_year = ?
                ORDER BY sa.date, s.student_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$classId, $termId, $yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance percentage for a student for a given term and year.
     * @param int $studentId
     * @param int $termId
     * @param int $yearId
     * @return float
     */
    public function getAttendancePercentage($studentId, $termId, $yearId)
    {
        $sql = "SELECT COUNT(*) as total_days,
                       SUM(status = 'present') as present_days
                FROM student_attendance
                WHERE student_id = ? AND term_id = ? AND academic_year = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $termId, $yearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['total_days'] > 0) {
            return round(100 * $row['present_days'] / $row['total_days'], 2);
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
    public function getChronicAbsentees($classId, $termId, $yearId, $threshold = 0.2)
    {
        $sql = "SELECT sa.student_id, s.student_name,
                       COUNT(*) as total_days,
                       SUM(sa.status = 'absent') as absent_days,
                       (SUM(sa.status = 'absent') / COUNT(*)) as absent_ratio
                FROM student_attendance sa
                JOIN students s ON sa.student_id = s.id
                WHERE sa.class_id = ? AND sa.term_id = ? AND sa.academic_year = ?
                GROUP BY sa.student_id, s.student_name
                HAVING absent_ratio > ?
                ORDER BY absent_ratio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$classId, $termId, $yearId, $threshold]);
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
