<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;
use App\API\Services\ReadReplicaService;

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
        // vw_current_enrollments joins enrollments -> students -> persons and the
        // class stream, exposing gender/class_name/stream_name (normalized schema).
        $where = [];
        $params = [];
        if (!empty($filters['class_id'])) {
            $where[] = 'class_id = ?';
            $params[] = $filters['class_id'];
        }
        if (!empty($filters['stream_id'])) {
            $where[] = 'stream_id = ?';
            $params[] = $filters['stream_id'];
        }
        if (!empty($filters['year'])) {
            $where[] = 'YEAR(enrollment_date) = ?';
            $params[] = $filters['year'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT
                    class_id,
                    class_name,
                    stream_id,
                    stream_name,
                    COUNT(CASE WHEN gender = 'male' THEN 1 END) AS boys,
                    COUNT(CASE WHEN gender = 'female' THEN 1 END) AS girls,
                    COUNT(*) AS total
                FROM vw_current_enrollments
                {$whereSql}
                GROUP BY class_id, class_name, stream_id, stream_name
                ORDER BY class_name, stream_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getEnrollmentTrends($filters = [])
    {
        // Enrollment trends by month/year (students.admission_date still live)
        $where = ["status = 'active'"];
        $params = [];
        if (!empty($filters['year'])) { $where[] = 'YEAR(admission_date) = ?'; $params[] = (int) $filters['year']; }
        $sql = "SELECT YEAR(admission_date) as year, MONTH(admission_date) as month, COUNT(*) as total
                FROM " . ReadReplicaService::qualifiedRef("student_directory") . "  
                WHERE " . implode(' AND ', $where) . "
                GROUP BY year, month
                ORDER BY year DESC, month DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getAttendanceRates($filters = [])
    {
        // Attendance rates by class/term
        // vw_student_term_attendance_summary already aggregates present/absent/late
        // per (student, term, class) for class and boarding registers; we roll it up
        // per class/term here.
        try {
            $where = ["atsa.register_type = 'class'"];
            $params = [];
            if (!empty($filters['term_id'])) {
                $where[] = 'atsa.term_id = ?';
                $params[] = (int) $filters['term_id'];
            }
            if (!empty($filters['class_id'])) {
                $where[] = 'atsa.class_id = ?';
                $params[] = (int) $filters['class_id'];
            }
            if (!empty($filters['stream_id'])) {
                $where[] = "EXISTS (
                    SELECT 1
                    FROM student_academic_enrollments sae
                    JOIN academic_year_class_streams aycs
                      ON aycs.id = sae.academic_year_class_stream_id
                    JOIN academic_year_classes ayc
                      ON ayc.id = aycs.academic_year_class_id
                    WHERE sae.student_id = atsa.student_id
                      AND sae.academic_year_id = atsa.academic_year_id
                      AND ayc.class_id = atsa.class_id
                      AND aycs.stream_id = ?
                      AND sae.enrollment_status IN ('active','completed','transferred','graduated')
                )";
                $params[] = (int) $filters['stream_id'];
            }
            $sql = "SELECT
                        atsa.class_id,
                        atsa.class_name,
                        atsa.term_id,
                        atsa.term_number,
                        atsa.term_name,
                        SUM(atsa.class_days_marked) AS total_records,
                        SUM(atsa.class_days_present) AS present_days,
                        ROUND(
                            SUM(atsa.class_days_present) * 100.0 / NULLIF(SUM(atsa.class_days_marked), 0),
                            2
                        ) AS attendance_rate
                    FROM vw_student_term_attendance_summary atsa
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY atsa.class_id, atsa.class_name, atsa.term_id, atsa.term_number, atsa.term_name
                    ORDER BY atsa.class_name, atsa.term_number";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentReportManager::getAttendanceRates] ' . $e->getMessage());
            throw new \RuntimeException('Attendance report data could not be queried.', 0, $e);
        }
    }
    public function getPromotionRates($filters = [])
    {
        // Promotion rates by target year — rolled up from promotion_batches
        // (the batch totals are authoritative for promoted/rejected counts;
        // individual "retained" transitions are not written to student_transitions).
        try {
            $sql = "SELECT
                        to_academic_year,
                        SUM(total_students_processed) AS total_processed,
                        SUM(total_promoted) AS approved,
                        SUM(total_rejected) AS retained,
                        ROUND(
                            SUM(total_promoted) * 100.0 / NULLIF(SUM(total_students_processed), 0),
                            2
                        ) AS promotion_rate
                    FROM promotion_batches
                    WHERE status = 'completed'
                    GROUP BY to_academic_year
                    ORDER BY to_academic_year DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getDropoutRates($filters = [])
    {
        // Exit/dropout rates by year and reason — student exits are recorded as
        // non-promotion rows in student_transitions (transfer/graduation/etc).
        try {
            $sql = "SELECT
                        YEAR(COALESCE(st.executed_at, st.decided_at)) AS year,
                        st.transition_type AS reason,
                        COUNT(*) AS total
                    FROM student_transitions st
                    WHERE st.transition_type <> 'promotion'
                    GROUP BY YEAR(COALESCE(st.executed_at, st.decided_at)), st.transition_type
                    ORDER BY year DESC, total DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
        // CBC grade (EE/ME/AE/BE) distribution from percentage-normalized results
        // via vw_assessment_results_detail (marks/max_marks -> percentage -> band).
        try {
            $where = [];
            $params = [];
            if (!empty($filters['term_id'])) {
                $where[] = 'term_id = ?';
                $params[] = (int) $filters['term_id'];
            }
            if (!empty($filters['class_id'])) {
                $where[] = 'class_id = ?';
                $params[] = (int) $filters['class_id'];
            }
            if (!empty($filters['stream_id'])) {
                $where[] = 'stream_id = ?';
                $params[] = (int) $filters['stream_id'];
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $sql = "SELECT
                        year_code AS academic_year,
                        term_number,
                        grade_band,
                        COUNT(*) AS student_count,
                        ROUND(AVG(percentage), 2) AS avg_score
                    FROM vw_assessment_results_detail
                    {$whereSql}
                    GROUP BY year_code, term_number, grade_band
                    ORDER BY year_code DESC, term_number, grade_band";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentReportManager::getScoreDistributions] ' . $e->getMessage());
            return [];
        }
    }
    public function getStudentProgressionRates($filters = [])
    {
        // Student progression: promoted vs graduated per year from student_transitions.
        // 'retained' is not recorded as a transition row (tracked in promotion_batches),
        // so it is reported as 0 here.
        try {
            $sql = "SELECT
                        YEAR(COALESCE(st.executed_at, st.decided_at, NOW())) AS promotion_year,
                        COUNT(*) AS total_processed,
                        SUM(CASE WHEN st.transition_type = 'promotion' THEN 1 ELSE 0 END) AS promoted,
                        SUM(CASE WHEN st.transition_type = 'graduation' THEN 1 ELSE 0 END) AS graduated,
                        0 AS retained
                    FROM student_transitions st
                    GROUP BY promotion_year
                    ORDER BY promotion_year DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    public function getExamReports($filters = [])
    {
        // Assessment/exam results grouped by class and term via
        // vw_assessment_results_detail (scores normalized to a percentage).
        try {
            $termId  = $filters['term_id']  ?? null;
            $classId = $filters['class_id'] ?? null;
            $streamId = $filters['stream_id'] ?? null;
            $where   = [];
            $params  = [];
            if ($termId) {
                $where[] = 'term_id = ?';
                $params[] = $termId;
            }
            if ($classId) {
                $where[] = 'class_id = ?';
                $params[] = (int) $classId;
            }
            if ($streamId) {
                $where[] = 'stream_id = ?';
                $params[] = (int) $streamId;
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $sql = "SELECT
                        year_code AS academic_year,
                        term_number,
                        class_name,
                        stream_name,
                        COUNT(DISTINCT student_academic_enrollment_id) AS student_count,
                        ROUND(AVG(percentage), 2) AS avg_score,
                        ROUND(MAX(percentage), 2) AS max_score,
                        ROUND(MIN(percentage), 2) AS min_score,
                        SUM(CASE WHEN grade_band IN ('ME','EE') THEN 1 ELSE 0 END) AS meeting_or_exceeding_count
                    FROM vw_assessment_results_detail
                    {$whereSql}
                    GROUP BY year_code, term_number, class_id, class_name, stream_id, stream_name
                    ORDER BY year_code DESC, term_number, class_name, stream_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentReportManager::getExamReports] ' . $e->getMessage());
            return [];
        }
    }

    public function getAcademicYearReports($filters = [])
    {
        try {
            $sql = "SELECT
                        ay.id,
                        ay.year_code AS academic_year_code,
                        ay.year_name AS academic_year,
                        ay.start_date,
                        ay.end_date,
                        ay.status,
                        COUNT(DISTINCT e.student_id) AS enrolled_students
                    FROM " . ReadReplicaService::qualifiedRef("academic_years") . "
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " e ON e.academic_year_id = ay.id
                    GROUP BY ay.id, ay.year_code, ay.year_name, ay.start_date, ay.end_date, ay.status
                    ORDER BY ay.start_date DESC";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback: list years without enrollment count
            try {
                return $this->db->query(
                    "SELECT id, year_code AS academic_year_code, year_name AS academic_year,
                            start_date, end_date, status FROM academic_years ORDER BY start_date DESC"
                )->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e2) {
                return [];
            }
        }
    }
}
