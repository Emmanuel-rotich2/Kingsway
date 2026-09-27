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

    public function create($studentId, $date, $status, $streamId = null, $markedBy = null)
    {
        $sql = "INSERT INTO student_attendance (student_academic_enrollment_id, date, status, marked_by, created_at)
                SELECT sae.id, :date, :status, :marked_by, NOW()
                FROM student_academic_enrollments sae
                JOIN students s ON s.id=sae.student_id
                LEFT JOIN admission_applications aa ON aa.id=s.application_id
                JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                WHERE sae.student_id = :student_id AND sae.enrollment_status = 'active'
                AND COALESCE(CASE WHEN s.entry_source = 'admission' THEN aa.enrolled_at END, CASE WHEN s.entry_source IS NULL OR s.entry_source <> 'admission' THEN sae.enrolled_on END) <= :eligible_date
                AND (aycs.id = :stream_id OR :stream_id_null IS NULL)
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'date' => $date,
            'status' => $status,
            'marked_by' => $markedBy,
            'student_id' => $studentId,
            'stream_id' => $streamId,
            'stream_id_null' => $streamId,
            'eligible_date' => $date,
        ]);
        return true;
    }

    /**
     * Get full attendance history for a student, grouped by academic year, term, and class.
     */
    public function getStudentAttendanceHistory($studentId)
    {
        $sql = "SELECT sa.*,
                       CONCAT(p.first_name, ' ', p.last_name) as student_name
                FROM student_attendance sa
                JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id
                JOIN students s ON sae.student_id = s.id
                JOIN persons p ON p.id = s.person_id
                WHERE sae.student_id = ?
                ORDER BY sa.date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance summary for a student grouped by academic year.
     */
    public function getStudentAttendanceSummary($studentId)
    {
        $sql = "SELECT sae.academic_year_id,
                       COUNT(*) as total_days,
                       SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_days,
                       SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                       SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late_days
                FROM student_attendance sa
                JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id
                WHERE sae.student_id = ?
                GROUP BY sae.academic_year_id
                ORDER BY sae.academic_year_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance for all students in a class stream context for a given academic year.
     */
    public function getClassAttendance($streamId, $yearId = null)
    {
        $sql = "SELECT sa.*,
                       CONCAT(p.first_name, ' ', p.last_name) as student_name
                FROM student_attendance sa
                JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id
                JOIN students s ON sae.student_id = s.id
                JOIN persons p ON p.id = s.person_id
                WHERE sae.academic_year_class_stream_id = ?";
        $params = [$streamId];
        if ($yearId) {
            $sql .= " AND sae.academic_year_id = ?";
            $params[] = $yearId;
        }
        $sql .= " ORDER BY sa.date DESC, p.last_name LIMIT 500";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance percentage for a student.
     */
    public function getAttendancePercentage($studentId, $yearId = null)
    {
        $sql = "SELECT COUNT(*) as total_days, SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_days
                FROM student_attendance sa
                JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id
                WHERE sae.student_id = ?";
        $params = [$studentId];
        if ($yearId) {
            $sql .= " AND sae.academic_year_id = ?";
            $params[] = $yearId;
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
     * Get students with chronic absenteeism (e.g., >20% absent) in a stream context.
     */
    public function getChronicAbsentees($streamId, $yearId = null, $threshold = 0.2)
    {
        $sql = "SELECT sae.student_id,
                        CONCAT(p.first_name, ' ', p.last_name) as student_name,
                        COUNT(*) as total_days,
                        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                        (SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) / COUNT(*)) as absent_ratio
                FROM student_attendance sa
                JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id
                JOIN students s ON sae.student_id = s.id
                JOIN persons p ON p.id = s.person_id
                WHERE sae.academic_year_class_stream_id = ?";
        $params = [$streamId];
        if ($yearId) {
            $sql .= " AND sae.academic_year_id = ?";
            $params[] = $yearId;
        }
        $sql .= " GROUP BY sae.student_id, p.first_name, p.last_name
                HAVING absent_ratio > ?
                ORDER BY absent_ratio DESC";
        $params[] = $threshold;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function read($studentId, $date = null, $streamId = null)
    {
        $sql = "SELECT sa.* FROM student_attendance sa
                JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id
                WHERE sae.student_id = :student_id";
        $params = ['student_id' => $studentId];
        if ($date) {
            $sql .= " AND sa.date = :date";
            $params['date'] = $date;
        }
        if ($streamId) {
            $sql .= " AND sae.academic_year_class_stream_id = :stream_id";
            $params['stream_id'] = $streamId;
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
