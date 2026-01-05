<?php
namespace App\API\Services;


use App\Database\Database;
use Exception;
use PDO;

class HeadteacherAnalyticsService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getOverview()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total_students FROM students WHERE status = 'active'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_students' => (int) ($row['total_students'] ?? 0),
            'card_type' => 'total_students'
        ];
    }

    public function getAttendanceToday()
    {
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave,
            COUNT(*) as total
            FROM attendance WHERE attendance_date = CURDATE()");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $present = (int) ($row['present'] ?? 0);
        $absent = (int) ($row['absent'] ?? 0);
        $on_leave = (int) ($row['on_leave'] ?? 0);
        $total = (int) ($row['total'] ?? 0);
        $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
        return [
            'present' => $present,
            'absent' => $absent,
            'on_leave' => $on_leave,
            'percentage' => $percentage,
            'card_type' => 'attendance_today'
        ];
    }

    public function getSchedules()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total_sessions FROM schedules WHERE schedule_date = CURDATE()");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // You may want to expand this with more fields as needed
        return [
            'total_sessions' => (int) ($row['total_sessions'] ?? 0),
            'in_progress' => 0,
            'completed' => 0,
            'upcoming' => 0,
            'card_type' => 'schedules'
        ];
    }

    public function getAdmissionsStats()
    {
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
            SUM(CASE WHEN status = 'interview_scheduled' THEN 1 ELSE 0 END) as interviews_scheduled,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM admission_applications WHERE YEAR(created_at) = YEAR(NOW())");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'pending_applications' => (int) ($row['pending_applications'] ?? 0),
            'interviews_scheduled' => (int) ($row['interviews_scheduled'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'card_type' => 'admissions'
        ];
    }

    public function getDisciplineStats()
    {
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_cases,
            SUM(CASE WHEN status = 'resolved' AND MONTH(resolved_at) = MONTH(NOW()) THEN 1 ELSE 0 END) as resolved_this_month,
            SUM(CASE WHEN type = 'warning' THEN 1 ELSE 0 END) as warnings,
            SUM(CASE WHEN type = 'suspension' THEN 1 ELSE 0 END) as suspensions
            FROM discipline_cases");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'open_cases' => (int) ($row['open_cases'] ?? 0),
            'resolved_this_month' => (int) ($row['resolved_this_month'] ?? 0),
            'warnings' => (int) ($row['warnings'] ?? 0),
            'suspensions' => (int) ($row['suspensions'] ?? 0),
            'card_type' => 'discipline'
        ];
    }

    public function getCommunicationsStats()
    {
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN type = 'email' THEN 1 ELSE 0 END) as emails,
            SUM(CASE WHEN type = 'message' THEN 1 ELSE 0 END) as messages,
            SUM(CASE WHEN status = 'pending_response' THEN 1 ELSE 0 END) as pending_responses,
            SUM(CASE WHEN WEEK(sent_at) = WEEK(NOW()) THEN 1 ELSE 0 END) as sent_this_week
            FROM parent_communications");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'sent_this_week' => (int) ($row['sent_this_week'] ?? 0),
            'pending_responses' => (int) ($row['pending_responses'] ?? 0),
            'emails' => (int) ($row['emails'] ?? 0),
            'messages' => (int) ($row['messages'] ?? 0),
            'card_type' => 'communications'
        ];
    }

    public function getAssessmentsStats()
    {
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status = 'graded' AND MONTH(graded_at) = MONTH(NOW()) THEN 1 ELSE 0 END) as graded_this_month,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_marking,
            AVG(score) as average_score,
            SUM(CASE WHEN score >= 75 THEN 1 ELSE 0 END) as high_performers
            FROM assessments");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'graded_this_month' => (int) ($row['graded_this_month'] ?? 0),
            'pending_marking' => (int) ($row['pending_marking'] ?? 0),
            'average_score' => round($row['average_score'] ?? 0, 2),
            'high_performers' => (int) ($row['high_performers'] ?? 0),
            'card_type' => 'assessments'
        ];
    }

    public function getPerformanceStats()
    {
        $stmt = $this->db->query("SELECT 
            AVG(score) as average_performance,
            SUM(CASE WHEN score >= 75 THEN 1 ELSE 0 END) as high_performers,
            SUM(CASE WHEN score BETWEEN 50 AND 74 THEN 1 ELSE 0 END) as average_performers,
            SUM(CASE WHEN score < 50 THEN 1 ELSE 0 END) as low_performers
            FROM assessments");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // Example: chart_data can be built with another query if needed
        return [
            'average_performance' => round($row['average_performance'] ?? 0, 2),
            'high_performers' => (int) ($row['high_performers'] ?? 0),
            'average_performers' => (int) ($row['average_performers'] ?? 0),
            'low_performers' => (int) ($row['low_performers'] ?? 0),
            'card_type' => 'performance',
            'chart_data' => []
        ];
    }

    public function getPendingAdmissions()
    {
        $stmt = $this->db->query("SELECT id, name, form, status, created_at as date FROM admission_applications WHERE status = 'pending'");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'data' => $data,
            'total' => count($data)
        ];
    }

    public function getDisciplineCases()
    {
        $stmt = $this->db->query("SELECT id, student, violation, date, status FROM discipline_cases WHERE status = 'open'");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'data' => $data,
            'total' => count($data)
        ];
    }
}
