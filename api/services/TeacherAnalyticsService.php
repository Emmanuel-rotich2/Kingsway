<?php
namespace App\API\Services;

use App\Database\Database;
use Exception;

class TeacherAnalyticsService
{
    protected $db;
    protected $userId;

    public function __construct($userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
    }

public function getMyClass()
    {
        // Find teacher's assigned class
        $sql = "SELECT cacs.class_name, cacs.grade_level as form, cacs.stream_name as stream,
                       COUNT(DISTINCT sae.id) as student_count
               FROM " . ReadReplicaService::qualifiedRef("academic_year_classes_streams") . " cacs
               LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON cacs.academic_year_class_stream_id = sae.academic_year_class_stream_id
                 AND sae.enrollment_status = 'active'
               WHERE cacs.class_teacher_id = ?
               GROUP BY cacs.academic_year_class_stream_id
               LIMIT 1";
        $stmt = $this->db->query($sql, [$this->userId]);
        $classData = $stmt->fetch();
        if (!$classData) {
            return null;
        }
        return [
            'total_students' => (int) ($classData['student_count'] ?? 0),
            'class_name' => $classData['class_name'] ?? '',
            'form' => $classData['form'] ?? '',
            'stream' => $classData['stream'] ?? ''
        ];
    }

    public function getMyAttendanceToday()
    {
        // Query DB for today's attendance for this teacher's class
        $sql = "SELECT 
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN a.status = 'on_leave' THEN 1 ELSE 0 END) as on_leave,
                    IFNULL(ROUND(100 * SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id),0)),0) as percentage
                FROM " . ReadReplicaService::qualifiedRef("student_attendance") . " a
                JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments_streams") . " saes ON a.student_academic_enrollment_id = saes.student_academic_enrollment_id
                WHERE saes.class_teacher_id = ? AND a.date = CURDATE()";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();
        return [
            'present' => (int) ($row['present'] ?? 0),
            'absent' => (int) ($row['absent'] ?? 0),
            'on_leave' => (int) ($row['on_leave'] ?? 0),
            'percentage' => (int) ($row['percentage'] ?? 0)
        ];
    }
}
