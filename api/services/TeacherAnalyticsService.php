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
        $sql = "SELECT c.*, COUNT(s.id) as student_count
                FROM classes c
                LEFT JOIN class_assignments ca ON c.id = ca.class_id
                LEFT JOIN students s ON c.id = s.class_id AND s.status = 'active'
                WHERE ca.user_id = ? AND ca.role = 'class_teacher'
                GROUP BY c.id
                LIMIT 1";
        $stmt = $this->db->query($sql, [$this->userId]);
        $classData = $stmt->fetch();
        if (!$classData) {
            return null;
        }
        return [
            'total_students' => (int) ($classData['student_count'] ?? 0),
            'class_name' => $classData['name'] ?? '',
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
                FROM student_attendance a
                JOIN students s ON a.student_id = s.id
                JOIN class_assignments ca ON s.class_id = ca.class_id
                WHERE ca.user_id = ? AND ca.role = 'class_teacher' AND a.date = CURDATE()";
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
