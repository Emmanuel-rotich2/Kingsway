<?php
namespace App\API\Modules\staff;

use App\Config;
use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Performance Manager
 * 
 * Handles CRUD operations for staff performance reviews and KPI tracking
 * - Creates and manages performance reviews
 * - Tracks KPIs per staff member
 * - Generates performance reports
 * - Links to academic years
 * - Respects staff types, categories, and departmental KPIs
 */
class StaffPerformanceManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Create performance review for a staff member
     * @param array $data Review data
     * @return array Response
     */
    public function createReview($data)
    {
        try {
            $required = ['staff_id', 'academic_year_id', 'review_period', 'reviewer_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $validPeriods = ['mid_year', 'end_of_year', 'quarterly', 'probation'];
            if (!in_array($data['review_period'], $validPeriods)) {
                return formatResponse(false, null, 'Invalid review period. Must be: ' . implode(', ', $validPeriods));
            }

            $this->db->beginTransaction();

            // Get staff details
            $stmt = $this->db->prepare("
                SELECT s.*, st.name as staff_type, sc.category_name, d.name as department_name,
                       CONCAT(sp.first_name, ' ', sp.last_name) as supervisor_name
                FROM staff s
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
                LEFT JOIN departments d ON d.id = sda.department_id
                LEFT JOIN staff sup ON s.supervisor_id = sup.id
                LEFT JOIN persons sp ON sp.id = sup.person_id
                WHERE s.id = ? AND s.status = 'active'
            ");
            $stmt->execute([$data['staff_id']]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active staff member not found');
            }

            // Check for existing review. academic_year_id no longer exists on
            // performance_reviews; period is UNIQUE per staff (uk_perf_staff_period),
            // so any existing row for the same period blocks a second one.
            $stmt = $this->db->prepare("
                SELECT id FROM performance_reviews
                WHERE staff_id = ? AND period = ?
                LIMIT 1
            ");
            $stmt->execute([$data['staff_id'], $data['review_period']]);
            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'A review already exists for this period');
            }

            // Create review. review_period maps to period, reviewer_id to
            // reviewed_by, overall_rating to rating, comments to notes, and the
            // legacy 'pending' status maps to the live 'draft' enum.
            $sql = "INSERT INTO performance_reviews (
                staff_id, period, rating, reviewed_by,
                review_date, status, notes
            ) VALUES (?, ?, ?, ?, ?, 'draft', ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['review_period'],
                $data['overall_rating'] ?? null,
                $data['reviewer_id'],
                $data['review_date'] ?? date('Y-m-d'),
                $data['comments'] ?? null
            ]);
            $reviewId = (int) $this->db->lastInsertId();

            // Auto-populate KPIs from templates based on staff category
            if (!empty($staff['staff_category_id'])) {
                $this->populateKPIsFromTemplates($reviewId, (int)$staff['staff_category_id']);
            }

            $this->db->commit();
            $this->logAction(
                'create',
                $reviewId,
                "Created performance review for {$staff['first_name']} {$staff['last_name']} - {$data['review_period']}"
            );

            return formatResponse(true, [
                'review_id' => $reviewId,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'staff_type' => $staff['staff_type'],
                'department' => $staff['department_name'],
                'review_period' => $data['review_period'],
                'status' => 'pending'
            ], 'Performance review created successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Populate KPIs from templates
     */
    private function populateKPIsFromTemplates($reviewId, $categoryId)
    {
        // Get KPI templates for the category. staff_kpi_templates has no
        // kpi_category column (weight is weight_percentage).
        $stmt = $this->db->prepare("
            SELECT id, kpi_name, target_value, weight_percentage
            FROM staff_kpi_templates
            WHERE staff_category_id = ? AND is_active = 1
            ORDER BY kpi_name
        ");
        $stmt->execute([$categoryId]);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($templates)) {
            return;
        }

        // performance_review_kpis stores kpi_name/kpi_category via the template
        // link (kpi_template_id), not as columns.
        $sql = "INSERT INTO performance_review_kpis (
            review_id, kpi_template_id, target_value, weight, status
        ) VALUES (?, ?, ?, ?, 'pending')";

        $stmt = $this->db->prepare($sql);
        foreach ($templates as $template) {
            $stmt->execute([
                $reviewId,
                $template['id'],
                $template['target_value'] ?? null,
                $template['weight_percentage'] ?? 0.0
            ]);
        }
    }

    /**
     * Update KPI score
     * @param int $kpiId KPI ID
     * @param array $data KPI update data
     * @return array Response
     */
    public function updateKPI($kpiId, $data)
    {
        try {
            $this->db->beginTransaction();

            $updates = [];
            $params = [];

            if (isset($data['actual_value'])) {
                $updates[] = "actual_value = ?";
                $params[] = $data['actual_value'];
            }

            if (isset($data['score'])) {
                $updates[] = "score = ?";
                $params[] = $data['score'];
            }

            // NOTE: performance_review_kpis has no rating column (per-KPI rating is
            // derived from score — see ratingFromScore()). A legacy 'rating' payload
            // is accepted but ignored so callers do not break.

            if (isset($data['comments'])) {
                $updates[] = "comments = ?";
                $params[] = $data['comments'];
            }

            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $kpiId;
            $sql = "UPDATE performance_review_kpis SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();
            $this->logAction('update', $kpiId, "Updated KPI score");

            return formatResponse(true, [
                'kpi_id' => $kpiId
            ], 'KPI updated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Get KPI summary for a review
     * @param int $reviewId Review ID
     * @return array Response
     */
    public function getKPISummary($reviewId)
    {
        try {
            // Get review details. academic_year_id is gone from performance_reviews,
            // so the academic_years join is dropped (period carries the context).
            $stmt = $this->db->prepare("
                SELECT pr.*,
                       s.staff_no, p.first_name, p.last_name, s.position,
                       st.name as staff_type, sc.category_name, d.name as department_name,
                       CONCAT(rp.first_name, ' ', rp.last_name) as reviewer_name
                FROM performance_reviews pr
                JOIN staff s ON pr.staff_id = s.id
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
                LEFT JOIN departments d ON d.id = sda.department_id
                LEFT JOIN users reviewer ON pr.reviewed_by = reviewer.id
                LEFT JOIN persons rp ON rp.id = reviewer.person_id
                WHERE pr.id = ?
            ");
            $stmt->execute([$reviewId]);
            $review = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$review) {
                return formatResponse(false, null, 'Performance review not found');
            }

            // Get all KPIs. kpi_name/kpi_category no longer live on
            // performance_review_kpis — they come from the linked template. There is
            // no category column anywhere, so KPIs are grouped under 'general'.
            $stmt = $this->db->prepare("
                SELECT prk.*,
                       skt.kpi_name,
                       'general' AS kpi_category,
                       skt.description as kpi_description,
                       skt.measurement_criteria
                FROM performance_review_kpis prk
                LEFT JOIN staff_kpi_templates skt ON prk.kpi_template_id = skt.id
                WHERE prk.review_id = ?
                ORDER BY skt.kpi_name, prk.id
            ");
            $stmt->execute([$reviewId]);
            $kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate statistics
            $totalKPIs = count($kpis);
            $completedKPIs = count(array_filter($kpis, fn($k) => $k['status'] === 'completed'));
            $totalWeight = array_sum(array_column($kpis, 'weight'));
            $weightedScore = 0;

            foreach ($kpis as $kpi) {
                if (!empty($kpi['score']) && !empty($kpi['weight'])) {
                    $weightedScore += ($kpi['score'] * $kpi['weight']);
                }
            }

            $overallScore = $totalWeight > 0 ? round($weightedScore / $totalWeight, 2) : 0;

            // Group KPIs by category
            $kpisByCategory = [];
            foreach ($kpis as $kpi) {
                $category = $kpi['kpi_category'] ?? 'general';
                if (!isset($kpisByCategory[$category])) {
                    $kpisByCategory[$category] = [];
                }
                $kpisByCategory[$category][] = $kpi;
            }

            return formatResponse(true, [
                'review' => $review,
                'kpis' => $kpis,
                'kpis_by_category' => $kpisByCategory,
                'summary' => [
                    'total_kpis' => $totalKPIs,
                    'completed_kpis' => $completedKPIs,
                    'overall_score' => $overallScore,
                    'completion_percent' => $totalKPIs > 0 ? round(($completedKPIs / $totalKPIs) * 100, 2) : 0
                ]
            ], 'KPI summary retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get academic KPI summary for a staff member
     * Uses sp_get_staff_kpi_summary for academic system KPIs
     * @param int $staffId Staff ID
     * @param int $academicYear Academic year
     * @return array Response
     */
    public function getAcademicKPISummary($staffId, $academicYear)
    {
        try {
            $stmt = $this->db->prepare("CALL sp_get_staff_kpi_summary(?, ?)");
            $stmt->execute([$staffId, $academicYear]);
            $kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return formatResponse(true, [
                'staff_id' => $staffId,
                'academic_year' => $academicYear,
                'kpis' => $kpis,
                'count' => count($kpis)
            ], 'Academic KPI summary retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get review history for a staff member
     * Uses vw_staff_performance_summary for automated calculations
     * @param int $staffId Staff ID
     * @param array $filters Optional filters
     * @return array Response
     */
    public function getReviewHistory($staffId, $filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_staff_performance_summary WHERE staff_id = ?";
            $params = [$staffId];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['review_period'])) {
                $sql .= " AND review_period = ?";
                $params[] = $filters['review_period'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY review_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'reviews' => $reviews,
                'count' => count($reviews)
            ], 'Review history retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Generate performance report
     * Uses sp_process_staff_performance_review for grade calculation
     * @param int $reviewId Review ID
     * @return array Response
     */
    public function generatePerformanceReport($reviewId)
    {
        try {
            // Get comprehensive review data
            $kpiSummary = $this->getKPISummary($reviewId);

            if (!$kpiSummary['status']) {
                return $kpiSummary;
            }

            $data = $kpiSummary['data'];

            // Process performance review and calculate grade using stored procedure
            $stmt = $this->db->prepare("CALL sp_process_staff_performance_review(?, @score, @grade)");
            $stmt->execute([$reviewId]);
            $stmt->closeCursor();

            // Get the calculated values
            $result = $this->db->query("SELECT @score AS overall_score, @grade AS performance_grade")->fetch(PDO::FETCH_ASSOC);
            $overallScore = $result['overall_score'] ?? 0;
            $performanceGrade = $this->formatPerformanceGrade($result['performance_grade'] ?? 'E');

            // Get strengths and areas for improvement. performance_review_kpis has
            // no rating column — the bucket is derived from the numeric score.
            $strengths = [];
            $improvements = [];

            foreach ($data['kpis'] as $kpi) {
                if (!empty($kpi['score'])) {
                    $rating = $this->ratingFromScore($kpi['score']);
                    if ($rating === 'exceeds') {
                        $strengths[] = $kpi['kpi_name'];
                    } elseif (in_array($rating, ['partially_meets', 'does_not_meet'])) {
                        $improvements[] = $kpi['kpi_name'];
                    }
                }
            }

            $report = [
                'review_details' => $data['review'],
                'performance_summary' => [
                    'overall_score' => $overallScore,
                    'performance_grade' => $performanceGrade,
                    'total_kpis' => $data['summary']['total_kpis'],
                    'completed_kpis' => $data['summary']['completed_kpis'],
                    'completion_percent' => $data['summary']['completion_percent']
                ],
                'kpis_by_category' => $data['kpis_by_category'],
                'strengths' => $strengths,
                'areas_for_improvement' => $improvements,
                'generated_at' => date('Y-m-d H:i:s')
            ];

            return formatResponse(true, $report, 'Performance report generated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Format performance grade with description
     */
    private function formatPerformanceGrade($grade)
    {
        $grades = [
            'A' => 'A - Outstanding',
            'B' => 'B - Exceeds Expectations',
            'C' => 'C - Meets Expectations',
            'D' => 'D - Partially Meets Expectations',
            'E' => 'E - Does Not Meet Expectations'
        ];
        return $grades[$grade] ?? 'E - Does Not Meet Expectations';
    }

    /**
     * Derive the legacy per-KPI rating bucket from the numeric score, since
     * performance_review_kpis has no rating column.
     */
    private function ratingFromScore($score): string
    {
        $score = (float)$score;
        if ($score >= 90) {
            return 'exceeds';
        }
        if ($score >= 70) {
            return 'meets';
        }
        if ($score >= 50) {
            return 'partially_meets';
        }
        return 'does_not_meet';
    }

    /**
     * Complete performance review
     * @param int $reviewId Review ID
     * @param array $data Completion data
     * @return array Response
     */
    public function completeReview($reviewId, $data = [])
    {
        try {
            $this->db->beginTransaction();

            // Check all KPIs are completed
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM performance_review_kpis
                WHERE review_id = ? AND status != 'completed'
            ");
            $stmt->execute([$reviewId]);
            $incompleteKPIs = $stmt->fetchColumn();

            if ($incompleteKPIs > 0 && empty($data['force_complete'])) {
                $this->db->rollBack();
                return formatResponse(
                    false,
                    null,
                    "Cannot complete review. {$incompleteKPIs} KPI(s) still pending. Use 'force_complete' to override."
                );
            }

            // Update review status. performance_reviews.status is enum('draft',
            // 'submitted', 'acknowledged') — completion maps to 'submitted'; the
            // legacy completion_date/final_comments columns are folded into
            // review_date/notes.
            $sql = "UPDATE performance_reviews 
                   SET status = 'submitted', 
                       review_date = COALESCE(review_date, NOW()),
                       notes = CONCAT(COALESCE(notes, ''), ' | Final comments: ', ?)
                   WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['final_comments'] ?? null,
                $reviewId
            ]);

            $this->db->commit();
            $this->logAction('update', $reviewId, "Completed performance review");

            return formatResponse(true, [
                'review_id' => $reviewId,
                'status' => 'completed'
            ], 'Performance review completed successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }
}
