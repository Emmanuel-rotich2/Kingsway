<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class StudentReportManager extends BaseAPI
{
    public function getAttendanceReport($filters = [])
    {
        // Detailed per-student attendance report — delegates to getAttendanceRates with filter passthrough
        return $this->getAttendanceRates($filters);
    }
    public function getTotalStudents($filters = [])
    {
        // Count students by class with gender breakdown
        $where = ['s.status = \'active\''];
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
        $sql = "SELECT
                    c.id AS class_id,
                    c.name AS class_name,
                    cs.id AS stream_id,
                    cs.stream_name AS stream_name,
                    COUNT(CASE WHEN s.gender IN ('male','M','m','boy') THEN 1 END) AS boys,
                    COUNT(CASE WHEN s.gender IN ('female','F','f','girl') THEN 1 END) AS girls,
                    COUNT(*) AS total
                FROM students s
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON c.id = cs.class_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY c.id, c.name, cs.id, cs.stream_name
                ORDER BY c.name, cs.stream_name";
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
        // Attendance rates by class/term
        try {
            $sql = "SELECT
                        c.id AS class_id,
                        c.name AS class_name,
                        sa.term_id,
                        COUNT(*) AS total_records,
                        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS present_days,
                        ROUND(
                            SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100,
                            2
                        ) AS attendance_rate
                    FROM student_attendance sa
                    JOIN students s ON s.id = sa.student_id
                    JOIN class_streams cs ON cs.id = s.stream_id
                    JOIN classes c ON c.id = cs.class_id
                    GROUP BY c.id, c.name, sa.term_id
                    ORDER BY c.name, sa.term_id";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    public function getPromotionRates($filters = [])
    {
        // Promotion rates by year
        try {
            $sql = "SELECT
                        sp.to_academic_year,
                        COUNT(*) AS total_promotions,
                        SUM(CASE WHEN sp.promotion_status = 'approved' THEN 1 ELSE 0 END) AS approved,
                        SUM(CASE WHEN sp.promotion_status = 'retained' THEN 1 ELSE 0 END) AS retained,
                        ROUND(
                            SUM(CASE WHEN sp.promotion_status = 'approved' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100,
                            2
                        ) AS promotion_rate
                    FROM student_promotions sp
                    GROUP BY sp.to_academic_year
                    ORDER BY sp.to_academic_year DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getDropoutRates($filters = [])
    {
        try {
            $sql = "SELECT
                        YEAR(s.updated_at) AS year,
                        s.status AS reason,
                        COUNT(*) AS total
                    FROM students s
                    WHERE s.status IN ('withdrawn', 'transferred', 'expelled', 'dropout')
                    GROUP BY YEAR(s.updated_at), s.status
                    ORDER BY year DESC, total DESC";
            $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                return $rows;
            }
        } catch (\Exception $e) {
            // students table query failed; try alternate table below
        }
        try {
            $sql2 = "SELECT YEAR(created_at) AS year, type AS reason, COUNT(*) AS total
                     FROM student_transfers
                     GROUP BY YEAR(created_at), type
                     ORDER BY year DESC";
            return $this->db->query($sql2)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    public function getAcademicPerformanceSummary($filters = [])
    {
        // Summary-level view — delegates to getExamReports
        return $this->getExamReports($filters);
    }
    public function getScoreDistributions($filters = [])
    {
        // CBC grade (EE/ME/AE/BE) distribution from assessment results
        try {
            $sql = "SELECT
                        ay.name AS academic_year,
                        at2.term_number AS term_number,
                        CASE
                            WHEN ar.score >= 80 THEN 'EE'
                            WHEN ar.score >= 50 THEN 'ME'
                            WHEN ar.score >= 25 THEN 'AE'
                            ELSE 'BE'
                        END AS grade_band,
                        COUNT(*) AS student_count,
                        ROUND(AVG(ar.score), 2) AS avg_score
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    JOIN academic_terms at2 ON at2.id = a.term_id
                    JOIN academic_years ay ON ay.id = at2.academic_year_id
                    WHERE ar.score IS NOT NULL
                    GROUP BY ay.name, at2.term_number, grade_band
                    ORDER BY ay.name DESC, at2.term_number, grade_band";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    public function getStudentProgressionRates($filters = [])
    {
        // Student progression: promoted vs retained vs dropped out per year
        try {
            $sql = "SELECT
                        YEAR(sp.created_at) AS promotion_year,
                        COUNT(*) AS total_processed,
                        SUM(CASE WHEN sp.promotion_status = 'approved' THEN 1 ELSE 0 END) AS promoted,
                        SUM(CASE WHEN sp.promotion_status = 'retained' THEN 1 ELSE 0 END) AS retained,
                        SUM(CASE WHEN sp.promotion_status = 'graduated' THEN 1 ELSE 0 END) AS graduated
                    FROM student_promotions sp
                    GROUP BY YEAR(sp.created_at)
                    ORDER BY promotion_year DESC";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    public function getExamReports($filters = [])
    {
        // Assessment/exam results grouped by class and term
        try {
            $termId  = $filters['term_id']  ?? null;
            $classId = $filters['class_id'] ?? null;
            $where   = [];
            $params  = [];
            if ($termId) {
                $where[] = 'a.term_id = ?';
                $params[] = $termId;
            }
            if ($classId) {
                $where[] = 'cs.class_id = ?';
                $params[] = $classId;
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $sql = "SELECT
                        ay.name AS academic_year,
                        at2.term_number,
                        c.name AS class_name,
                        COUNT(DISTINCT ar.student_id) AS student_count,
                        ROUND(AVG(ar.score), 2) AS avg_score,
                        ROUND(MAX(ar.score), 2) AS max_score,
                        ROUND(MIN(ar.score), 2) AS min_score,
                        SUM(CASE WHEN ar.score >= 50 THEN 1 ELSE 0 END) AS passing_count
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    JOIN academic_terms at2 ON at2.id = a.term_id
                    JOIN academic_years ay ON ay.id = at2.academic_year_id
                    JOIN students s ON s.id = ar.student_id
                    JOIN class_streams cs ON cs.id = s.stream_id
                    JOIN classes c ON c.id = cs.class_id
                    $whereSql
                    GROUP BY ay.name, at2.term_number, c.id, c.name
                    ORDER BY ay.name DESC, at2.term_number, c.name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAcademicYearReports($filters = [])
    {
        try {
            $sql = "SELECT
                        ay.id,
                        ay.name AS academic_year,
                        ay.start_date,
                        ay.end_date,
                        ay.status,
                        COUNT(DISTINCT e.student_id) AS enrolled_students
                    FROM academic_years ay
                    LEFT JOIN academic_terms at2 ON at2.academic_year_id = ay.id
                    LEFT JOIN student_term_enrollments e ON e.term_id = at2.id
                    GROUP BY ay.id, ay.name, ay.start_date, ay.end_date, ay.status
                    ORDER BY ay.start_date DESC";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback: list years without enrollment count
            try {
                return $this->db->query(
                    "SELECT id, name AS academic_year, start_date, end_date, status FROM academic_years ORDER BY start_date DESC"
                )->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e2) {
                return [];
            }
        }
    }
}
