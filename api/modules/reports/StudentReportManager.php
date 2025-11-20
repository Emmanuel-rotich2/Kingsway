<?php
namespace App\API\Modules\Reports;
use App\API\Includes\BaseAPI;

class StudentReportManager extends BaseAPI
{
    public function getAttendanceReport($filters = [])
    {
        // Example implementation: expects ['start_date', 'end_date', 'class_id', 'stream_id'] in $filters
        // Replace with actual DB logic as needed
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $classId = $filters['class_id'] ?? null;
        $streamId = $filters['stream_id'] ?? null;
        // Placeholder: return a mock attendance report
        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'class_id' => $classId,
            'stream_id' => $streamId,
            'attendance' => 'Attendance report data here.'
        ];
    }
    public function getTotalStudents($filters = [])
    {
        // Count students by class, stream, gender, year
        $where = [];
        $params = [];
        if (!empty($filters['class_id'])) {
            $where[] = 'cs.class_id = ?';
            $params[] = $filters['class_id'];
        }
        if (!empty($filters['stream_id'])) {
            $where[] = 's.stream_id = ?';
            $params[] = $filters['stream_id'];
        }
        if (!empty($filters['year'])) {
            $where[] = 'YEAR(s.admission_date) = ?';
            $params[] = $filters['year'];
        }
        $sql = "SELECT cs.class_id, s.stream_id, s.gender, COUNT(*) as total
                FROM students s
                JOIN class_streams cs ON s.stream_id = cs.id
                WHERE s.status = 'active'";
        if ($where) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY cs.class_id, s.stream_id, s.gender';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getEnrollmentTrends($filters = [])
    {
        // Enrollment trends by month/year
        $sql = "SELECT YEAR(admission_date) as year, MONTH(admission_date) as month, COUNT(*) as total
                FROM students
                WHERE status = 'active'
                GROUP BY year, month
                ORDER BY year DESC, month DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getAttendanceRates($filters = [])
    {
        // Attendance rates by class/stream/term
        $sql = "SELECT class_id, stream_id, term_id, 
                       SUM(status = 'present') as present_days,
                       COUNT(*) as total_days,
                       (SUM(status = 'present')/COUNT(*))*100 as attendance_rate
                FROM student_attendance
                GROUP BY class_id, stream_id, term_id";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getPromotionRates($filters = [])
    {
        // Promotion rates by class/stream/year
        $sql = "SELECT promoted_to_class_id, promoted_to_stream_id, to_academic_year, COUNT(*) as promoted_count
                FROM student_promotions
                WHERE promotion_status = 'approved'
                GROUP BY promoted_to_class_id, promoted_to_stream_id, to_academic_year";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getDropoutRates($filters = [])
    {
        // Dropout/transfer rates by year
        $sql = "SELECT YEAR(dropout_date) as year, reason, COUNT(*) as total
                FROM student_dropout_transfers
                GROUP BY year, reason";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getAcademicPerformanceSummary($filters = [])
    {
        // Example implementation: expects ['class_id', 'term', 'year'] in $filters
        // Replace with actual DB logic as needed
        $classId = $filters['class_id'] ?? null;
        $term = $filters['term'] ?? null;
        $year = $filters['year'] ?? null;
        // Placeholder: return a mock summary
        return [
            'class_id' => $classId,
            'term' => $term,
            'year' => $year,
            'summary' => 'Academic performance summary data here.'
        ];
    }
    public function getScoreDistributions($filters = [])
    {
        // Score distributions by year/term
        $sql = "SELECT academic_year, term_id, score_band, COUNT(*) as student_count
                FROM exam_score_distributions
                GROUP BY academic_year, term_id, score_band";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getStudentProgressionRates($filters = [])
    {
        // Student progression and retention rates by cohort
        $sql = "SELECT cohort_year, COUNT(*) as total, SUM(status = 'retained') as retained, (SUM(status = 'retained')/COUNT(*))*100 as retention_rate
                FROM student_cohorts
                GROUP BY cohort_year";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getExamReports($filters = [])
    {
        // All exam reports by year/term/class
        $sql = "SELECT exam_id, academic_year, term_id, class_id, stream_id, AVG(score) as avg_score
                FROM exam_results
                GROUP BY exam_id, academic_year, term_id, class_id, stream_id";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getAcademicYearReports($filters = [])
    {
        // Academic year summary reports
        $sql = "SELECT academic_year, COUNT(*) as total_students, AVG(final_score) as avg_score
                FROM academic_year_reports
                GROUP BY academic_year";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
