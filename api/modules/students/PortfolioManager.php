<?php

namespace App\API\Modules\students;

use PDO;
use Exception;
use App\API\Services\ReadReplicaService;

/**
 * PortfolioManager
 *
 * Owns all DB access behind the student-portfolio PDF generation
 * (PrintController::postPortfolio). Student identity + current class resolve
 * through the canonical live chain persons -> students ->
 * student_academic_enrollments -> academic_year_class_streams ->
 * academic_year_classes -> classes/streams.
 */
class PortfolioManager
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Assemble the cumulative portfolio payload for a student, or null when the
     * student does not exist.
     *
     * @return array|null
     */
    public function getStudentPortfolioData($studentId)
    {
        $student = $this->fetchStudent($studentId);
        if (!$student) {
            return null;
        }

        $artifacts = $this->fetchArtifacts($studentId);
        $compSummary = $this->fetchCompetencySummary($studentId);
        $valsSummary = $this->fetchValuesSummary($studentId);
        $feedbackRows = $this->fetchTeacherFeedback($studentId);

        $portfolios = $this->fetchPortfolios($studentId);

        $teacherFeedback = implode("\n---\n", array_column($feedbackRows, 'teacher_feedback'));

        $years = array_unique(array_filter(array_column($artifacts, 'academic_year')));
        sort($years);
        $yearRange = count($years) > 1
            ? min($years) . ' \u2013 ' . max($years)
            : (string)(min($years) ?: date('Y'));

        return [
            'student' => $student,
            'portfolio' => $portfolios[0] ?? [],
            'allArtifacts' => $artifacts,
            'competencySummary' => $compSummary,
            'valuesSummary' => $valsSummary,
            'teacherFeedback' => $teacherFeedback,
            'yearRange' => $yearRange,
            'totalArtifacts' => count($artifacts),
        ];
    }

private function fetchStudent($studentId)
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, p.first_name, p.last_name, s.admission_no, p.photo_url AS photo,
                    c.name AS class_name, st.name AS stream_name
             FROM " . ReadReplicaService::qualifiedRef("students") . " s
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " e
               ON e.student_id = s.id
               AND e.enrollment_status IN ('active','pending','completed')
               AND e.id = (
                   SELECT MAX(e2.id)
                   FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " e2
                   WHERE e2.student_id = s.id
                     AND e2.enrollment_status IN ('active','pending','completed')
               )
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id = e.academic_year_class_stream_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("streams") . " st ON st.id = aycs.stream_id
             WHERE s.id = ?"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function fetchPortfolios($studentId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM portfolios WHERE student_id = ? ORDER BY academic_year DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchArtifacts($studentId)
    {
        $stmt = $this->db->prepare(
            "SELECT pa.*, cc.name AS competency_name, cv.name AS value_name,
                    p.academic_year
             FROM " . ReadReplicaService::qualifiedRef("portfolio_artifacts") . " pa
             JOIN portfolios p ON p.id = pa.portfolio_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("core_competencies") . " cc ON cc.id = pa.competency_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("core_values") . " cv ON cv.id = pa.value_id
             WHERE p.student_id = ?
             ORDER BY pa.upload_date DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchCompetencySummary($studentId)
    {
        $stmt = $this->db->prepare(
            "SELECT cc.name AS competency_name,
                    COUNT(pa.id) AS artifact_count,
                    ROUND(AVG(pa.rating), 1) AS avg_rating,
                    MAX(pa.rating) AS highest_rating
             FROM " . ReadReplicaService::qualifiedRef("portfolio_artifacts") . " pa
             JOIN portfolios p ON p.id = pa.portfolio_id
             JOIN " . ReadReplicaService::qualifiedRef("core_competencies") . " cc ON cc.id = pa.competency_id
             WHERE p.student_id = ? AND pa.competency_id IS NOT NULL
             GROUP BY cc.id, cc.name
             ORDER BY artifact_count DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchValuesSummary($studentId)
    {
        $stmt = $this->db->prepare(
            "SELECT cv.name AS value_name, COUNT(pa.id) AS artifact_count
             FROM " . ReadReplicaService::qualifiedRef("portfolio_artifacts") . " pa
             JOIN portfolios p ON p.id = pa.portfolio_id
             JOIN " . ReadReplicaService::qualifiedRef("core_values") . " cv ON cv.id = pa.value_id
             WHERE p.student_id = ? AND pa.value_id IS NOT NULL
             GROUP BY cv.id, cv.name
             ORDER BY artifact_count DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchTeacherFeedback($studentId)
    {
        $stmt = $this->db->prepare(
            "SELECT pa.teacher_feedback
             FROM " . ReadReplicaService::qualifiedRef("portfolio_artifacts") . "
             JOIN portfolios p ON p.id = pa.portfolio_id
             WHERE p.student_id = ?
               AND pa.teacher_feedback IS NOT NULL
               AND pa.teacher_feedback != ''
             ORDER BY pa.upload_date DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
