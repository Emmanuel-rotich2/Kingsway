<?php
/**
 * AcademicCohortProjectionService
 * =================================
 * Admission Stage 5 — period-aware, cohort-aware class capacity projection.
 *
 * Scope (per spec):
 *   - Resolve the current academic context (active year / current term, future years/terms).
 *   - Determine whether an applied period is current, future, or past.
 *   - Identify the FEEDER class that occupies a future target class (progression).
 *   - Project student progression + reservations -> projected occupancy + vacancies.
 *   - Class-and-stream capacity, recommended stream.
 *   - Capacity status (available/limited/full/over_capacity/setup_required/...).
 *
 * Design decisions grounded in the live DB:
 *   - classes.academic_year is YEAR(4); academic_years.id is the FK used everywhere else.
 *     We bridge via academic_years.year_code / year_name.
 *   - Progression is stored per-class in academic_class_progression
 *     (source_class_id -> target_class_id). A level-hierarchy fallback
 *     (school_levels.id order) is used when no explicit row exists.
 *   - Enrollment counts come from students JOIN class_streams (NOT a hard dependency
 *     on a view that may not exist in every environment).
 *   - academic_capacity_config holds the status thresholds (DB-driven, configurable).
 *
 * One shared calculation is used by: admissions placement, class capacity,
 * enrollment planning, and academic forecasting.
 */

namespace App\API\Modules\academic;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

class AcademicCohortProjectionService extends BaseAPI
{
    /** Canonical grade ordering used for the fallback progression chain. */
    private const GRADE_ORDER = [
        'Playgroup' => 0, 'PP1' => 1, 'PP2' => 2,
        'Grade1' => 3, 'Grade2' => 4, 'Grade3' => 5, 'Grade4' => 6,
        'Grade5' => 7, 'Grade6' => 8, 'Grade7' => 9, 'Grade8' => 10, 'Grade9' => 11,
    ];

    protected $db;

    public function __construct($db = null)
    {
        parent::__construct('academic');
        $this->db = $db ?? \App\Database\Database::getInstance()->getConnection();
    }

    // ========================================================================
    // PUBLIC ENTRY POINTS
    // ========================================================================

    /**
     * Project capacity for a specific target period/class.
     *
     * @param int      $targetAcademicYearId  academic_years.id
     * @param int|null $targetTermId          academic_terms.id (optional)
     * @param int      $targetClassId         classes.id
     * @param int|null $targetStreamId        class_streams.id (optional -> only that stream)
     * @param int|null $appliedYearValue      the YEAR(4) the applicant applied for
     *                                        (used to decide current vs future cohort)
     * @return array normalized response
     */
    public function projectClassCapacity(
        int $targetAcademicYearId,
        ?int $targetTermId,
        int $targetClassId,
        ?int $targetStreamId = null,
        ?int $appliedYearValue = null
    ): array {
        try {
            // ---- 1. Resolve current academic context -----------------------
            $now = $this->resolveCurrentContext();

            // ---- 2. Load target period + class ------------------------------
            $year = $this->fetchAcademicYear($targetAcademicYearId);
            if (!$year) {
                return $this->errorResponse(
                    'Academic year not found.',
                    404,
                    ['academic_year_id' => $targetAcademicYearId, 'resolution' => 'unknown']
                );
            }
            $class = $this->fetchClass($targetClassId);
            if (!$class) {
                return $this->errorResponse(
                    'Target class not found.',
                    404,
                    ['class_id' => $targetClassId, 'resolution' => 'unknown']
                );
            }
            $term = $targetTermId ? $this->fetchTerm($targetTermId) : null;

            // ---- 3. Decide current vs future (period semantics) ------------
            $appliedYearValue = $appliedYearValue ?? (int) ($year['year_code'] ?? $year['year_name'] ?? 0);
            $periodResult = $this->classifyPeriod($now, $year, $term, $appliedYearValue);

            // ---- 4. Capacity source ----------------------------------------
            $capacity = (int) ($class['capacity'] ?? 0);

            $source = $this->resolveCohortSource($now, $year, $class, $periodResult);

            // ---- 5. Projection math ----------------------------------------
            $projection = $this->computeProjection($source, $capacity, $targetClassId, $targetStreamId, $year);

            // ---- 6. Status + confidence ------------------------------------
            $status = $this->computeCapacityStatus($projection['projected_occupancy'], $capacity, $now['year_value'], (int) $year['year_code']);

            $warnings = $source['warnings'];
            if ($status['status'] === 'over_capacity' || $status['status'] === 'projected_over') {
                $warnings[] = 'Projected occupancy exceeds configured capacity — promotion or intake decisions needed.';
            }

            $payload = [
                'target_academic_year'   => ['id' => (int) $year['id'], 'name' => (string) ($year['year_name'] ?? $year['year_code'])],
                'target_term'            => $term ? ['id' => (int) $term['id'], 'name' => (string) ($term['name'] ?? '')] : null,
                'target_class'           => ['id' => (int) $class['id'], 'name' => (string) $class['name']],
                'applied_academic_year'  => $appliedYearValue ?: null,
                'capacity_source'        => $source['type'],
                'source_academic_year'   => $source['source_year'],
                'source_class'           => $source['source_class'],
                'configured_capacity'    => $capacity,
                'current_source_enrollment' => $source['current_source_enrollment'],
                'expected_progressions'  => $projection['expected_progressions'],
                'confirmed_repeaters_into_target' => $projection['confirmed_repeaters_into_target'],
                'confirmed_new_admissions' => $projection['confirmed_new_admissions'],
                'confirmed_transfers_in' => $projection['confirmed_transfers_in'],
                'confirmed_transfers_out' => $projection['confirmed_transfers_out'],
                'confirmed_withdrawals'  => $projection['confirmed_withdrawals'],
                'projected_occupancy'    => $projection['projected_occupancy'],
                'available_spaces'       => $projection['available_spaces'],
                'capacity_status'        => $status['status'],
                'confidence'             => $status['confidence'],
                'streams'               => $projection['streams'],
                'recommended_stream_id'  => $projection['recommended_stream_id'],
                'resolution'             => $periodResult['resolution'],
                'warnings'               => array_values(array_unique($warnings)),
            ];

            return $this->successResponse($payload, 'Capacity projected.');
        } catch (Exception $e) {
            return $this->errorResponse('Projection failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Project capacity for an admission application (Stage 5 placement context).
     * Reads the period + target class straight from admission_applications.
     *
     * @param int $applicationId
     * @return array
     */
    public function projectCapacityForApplication(int $applicationId): array
    {
        $app = $this->fetchApplication($applicationId);
        if (!$app) {
            return $this->errorResponse('Application not found.', 404, ['application_id' => $applicationId]);
        }

        $yearValue = (int) ($app['academic_year'] ?? 0);
        $targetClassId = $this->resolveClassForApplication($app);
        if (!$targetClassId) {
            return $this->errorResponse(
                'Could not resolve a target class for this application (grade: ' . ($app['grade_applying_for'] ?? '?') . ', year: ' . $yearValue . ').',
                422,
                ['application_id' => $applicationId, 'resolution' => 'academic_year_setup_required',
                 'warning' => 'No active class exists for the applied grade in the applied year. Set up the academic year/class before final placement.']
            );
        }

        // term_id on the application (target_term_id) if present
        $termId = isset($app['target_term_id']) && $app['target_term_id'] ? (int) $app['target_term_id'] : null;
        $yearRow = $this->fetchAcademicYearByValue($yearValue);
        $yearId = $yearRow ? (int) $yearRow['id'] : null;

        if (!$yearId) {
            return $this->errorResponse(
                'Academic Year ' . $yearValue . ' has not been configured.',
                422,
                ['application_id' => $applicationId, 'resolution' => 'academic_year_setup_required',
                 'warning' => 'Capacity can be estimated from the current progression cohort, but final placement requires Academic Year ' . $yearValue . ' setup.']
            );
        }

        $result = $this->projectClassCapacity($yearId, $termId, $targetClassId, null, $yearValue);
        // attach the application context
        if (isset($result['data']) && is_array($result['data'])) {
            $result['data']['application_id'] = $applicationId;
        }
        return $result;
    }

    // ========================================================================
    // PERIOD RESOLUTION
    // ========================================================================

    private function resolveCurrentContext(): array
    {
        // Prefer is_current, else the active year with a 'current' term.
        $year = $this->fetchRow(
            "SELECT * FROM academic_years WHERE is_current = 1 AND status = 'active' LIMIT 1"
        );
        if (!$year) {
            $year = $this->fetchRow(
                "SELECT * FROM academic_years WHERE status = 'active' ORDER BY year_code DESC LIMIT 1"
            );
        }
        $term = null;
        if ($year) {
            $term = $this->fetchRow(
                "SELECT * FROM academic_terms WHERE academic_year_id = ? AND status = 'current' LIMIT 1",
                [$year['id']]
            );
        }
        return [
            'year' => $year,                         // null if none
            'term' => $term,                         // null if none
            'year_value' => $year ? (int) ($year['year_code'] ?? $year['year_name']) : null,
        ];
    }

    private function classifyPeriod(array $now, array $year, ?array $term, int $appliedYearValue): array
    {
        $curYear = $now['year_value'];
        $targetYear = (int) ($year['year_code'] ?? $year['year_name'] ?? 0);

        if ($appliedYearValue && $targetYear !== $appliedYearValue) {
            $targetYear = $appliedYearValue; // trust what the applicant applied for
        }

        if ($curYear === null) {
            return ['resolution' => 'unknown', 'relation' => 'unknown', 'year_delta' => null];
        }
        $delta = $targetYear - $curYear;

        if ($delta < 0) {
            return ['resolution' => 'invalid_past_period', 'relation' => 'past', 'year_delta' => $delta];
        }
        if ($delta === 0) {
            return ['resolution' => 'current_period_used', 'relation' => 'current', 'year_delta' => 0];
        }
        if ($delta === 1) {
            return ['resolution' => 'future_year_found', 'relation' => 'next', 'year_delta' => 1];
        }
        return ['resolution' => 'unsupported_future_period', 'relation' => 'distant_future', 'year_delta' => $delta];
    }

    // ========================================================================
    // COHORT SOURCE
    // ========================================================================

    private function resolveCohortSource(array $now, array $year, array $class, array $period): array
    {
        $warnings = [];
        $relation = $period['relation'] ?? 'current';

        // SAME academic year (current or later term in same year):
        //   source = the target class's CURRENT cohort.
        if ($relation === 'current' || $relation === 'past') {
            $sourceClass = $class;
            $sourceYearVal = (int) ($year['year_code'] ?? $year['year_name'] ?? 0);
            $type = 'current_class_enrollment';
            $sourceYear = ['id' => (int) $year['id'], 'name' => (string) ($year['year_name'] ?? $year['year_code'])];
        } else {
            // FUTURE academic year: feeder = the class expected to progress INTO target.
            $sourceClass = $this->findFeederClass((int) $class['id']);
            if (!$sourceClass) {
                // No configured predecessor; fall back to level-based guess but flag manual.
                $type = 'manual_projection';
                $sourceClass = $class;
                $warnings[] = 'No configured feeder class for progression into ' . $class['name'] .
                    '. Capacity is a manual estimate — academic review required.';
            } else {
                $type = 'projected_feeder_cohort';
            }
            $srcYear = $this->fetchAcademicYearByValue(((int) ($year['year_code'] ?? $year['year_name'] ?? 0)) - 1);
            $sourceYear = $srcYear
                ? ['id' => (int) $srcYear['id'], 'name' => (string) ($srcYear['year_name'] ?? $srcYear['year_code'])]
                : null;
            if (!$sourceYear) {
                $warnings[] = 'Source academic year (previous year) has not been configured. Projection is provisional.';
            }
        }

        $currentSourceEnrollment = $this->countActiveEnrollment((int) $sourceClass['id']);

        return [
            'type' => $type,
            'source_class' => ['id' => (int) $sourceClass['id'], 'name' => (string) $sourceClass['name']],
            'source_year' => $sourceYear,
            'current_source_enrollment' => $currentSourceEnrollment,
            'warnings' => $warnings,
        ];
    }

    /**
     * Find the class that feeds INTO $targetClassId per academic_class_progression.
     * Falls back to level-order progression (previous grade in the chain).
     */
    private function findFeederClass(int $targetClassId): ?array
    {
        $row = $this->fetchRow(
            "SELECT c.* FROM academic_class_progression p
             JOIN classes c ON c.id = p.source_class_id
             WHERE p.target_class_id = ? AND p.active = 1
             ORDER BY p.id DESC LIMIT 1",
            [$targetClassId]
        );
        if ($row) {
            return $row;
        }
        // Fallback: previous grade by canonical order + same/previous level.
        $target = $this->fetchClass($targetClassId);
        if (!$target) {
            return null;
        }
        // class.name is "Grade 1" (with space) but GRADE_ORDER keys are "Grade1" (no space).
        $targetNameNorm = preg_replace('/\s+/', '', $target['name']);
        $targetGradeRank = self::GRADE_ORDER[$targetNameNorm] ?? null;
        if ($targetGradeRank === null || $targetGradeRank === 0) {
            return null; // Playgroup has no predecessor
        }
        // find the class in the same applied year whose grade rank is one less
        $prevName = array_search($targetGradeRank - 1, self::GRADE_ORDER, true);
        if (!$prevName) {
            return null;
        }
        return $this->fetchRow(
            "SELECT * FROM classes WHERE name = ? AND status = 'active'
             ORDER BY academic_year DESC LIMIT 1",
            [$prevName]
        ) ?: null;
    }

    // ========================================================================
    // PROJECTION MATH
    // ========================================================================

    private function computeProjection(array $source, int $capacity, int $targetClassId, ?int $targetStreamId, array $year): array
    {
        $sourceClassId = (int) $source['source_class']['id'];
        $baseEnrollment = $source['current_source_enrollment'];

        // Expected progressions = active learners in feeder class (those who normally move up).
        $expectedProgressions = ($source['type'] === 'projected_feeder_cohort' || $source['type'] === 'manual_projection')
            ? $baseEnrollment
            : $baseEnrollment;

        // Reservations against the TARGET class (confirmed + provisional).
        $res = $this->fetchRow(
            "SELECT
                COALESCE(SUM(CASE WHEN reservation_status IN ('confirmed') THEN 1 ELSE 0 END),0) AS confirmed,
                COALESCE(SUM(CASE WHEN reservation_status IN ('provisional') THEN 1 ELSE 0 END),0) AS provisional
             FROM academic_capacity_reservations
             WHERE class_id = ? AND reservation_status IN ('confirmed','provisional')",
            [$targetClassId]
        );
        $confirmedReservations = (int) ($res['confirmed'] ?? 0);
        $provisionalReservations = (int) ($res['provisional'] ?? 0);

        // Approved admissions already reserving space (admission_placements approved/recommended
        // for this class, not yet enrolled).
        $approvedAdmissions = (int) $this->fetchValue(
            "SELECT COUNT(*) FROM admission_placements ap
             JOIN admission_applications aa ON aa.id = ap.application_id
             WHERE ap.final_class_id = ? AND ap.placement_status IN ('recommended','approved')
               AND aa.enrolled_student_id IS NULL",
            [$targetClassId]
        );

        $confirmedNewAdmissions = $confirmedReservations + $approvedAdmissions;

        // Repeaters into target: students currently in target class but flagged to repeat.
        // (No explicit repeater flag column exists; approximate as 0 unless a non-promoting marker is present.)
        $confirmedRepeaters = 0;

        // Transfers in/out and withdrawals are not auto-derivable in this schema; default 0
        // but included so the formula is explicit and auditable.
        $transfersIn = 0;
        $transfersOut = 0;
        $withdrawals = 0;

        $projectedOccupancy = $expectedProgressions
            + $confirmedRepeaters
            + $confirmedNewAdmissions
            + $transfersIn
            - $transfersOut
            - $withdrawals;

        if ($targetStreamId) {
            // Single-stream projection
            $stream = $this->fetchRow(
                "SELECT * FROM class_streams WHERE id = ? AND class_id = ?",
                [$targetStreamId, $targetClassId]
            );
            $streamCap = $stream ? (int) ($stream['capacity'] ?? 0) : 0;
            $streamOcc = $stream ? (int) ($stream['current_students'] ?? 0) : 0;
            $streams = [[
                'id' => $targetStreamId,
                'name' => $stream['stream_name'] ?? null,
                'capacity' => $streamCap,
                'projected_occupancy' => $streamOcc,
                'vacancies' => max($streamCap - $streamOcc, 0),
            ]];
            $recommendedStreamId = $targetStreamId;
        } else {
            // Whole-class: compute per-stream then class totals.
            $streamRows = $this->fetchAll(
                "SELECT * FROM class_streams WHERE class_id = ? AND status = 'active' ORDER BY id",
                [$targetClassId]
            );
            $streams = [];
            $recommendedStreamId = null;
            $bestVacancies = -1;
            $classProjected = 0;
            foreach ($streamRows as $s) {
                $sCap = (int) ($s['capacity'] ?? 0);
                $sOcc = (int) ($s['current_students'] ?? 0);
                $vac = max($sCap - $sOcc, 0);
                $streams[] = [
                    'id' => (int) $s['id'],
                    'name' => $s['stream_name'],
                    'capacity' => $sCap,
                    'projected_occupancy' => $sOcc,
                    'vacancies' => $vac,
                ];
                // For a future target, occupancy is provisional; for current it's actual.
                $classProjected += ($source['type'] === 'current_class_enrollment') ? $sOcc : 0;
                if ($vac > $bestVacancies) {
                    $bestVacancies = $vac;
                    $recommendedStreamId = (int) $s['id'];
                }
            }
            // For a future class, projected occupancy is class-level (set above), not stream sums.
            if ($source['type'] !== 'current_class_enrollment') {
                $classProjected = $projectedOccupancy;
            }
        }

        // For current-year projections, ensure projected occupancy reflects live count.
        if ($source['type'] === 'current_class_enrollment') {
            $projectedOccupancy = $baseEnrollment;
        }

        $availableSpaces = max($capacity - $projectedOccupancy, 0);

        return [
            'expected_progressions' => $expectedProgressions,
            'confirmed_repeaters_into_target' => $confirmedRepeaters,
            'confirmed_new_admissions' => $confirmedNewAdmissions,
            'confirmed_transfers_in' => $transfersIn,
            'confirmed_transfers_out' => $transfersOut,
            'confirmed_withdrawals' => $withdrawals,
            'projected_occupancy' => $projectedOccupancy,
            'available_spaces' => $availableSpaces,
            'streams' => $streams,
            'recommended_stream_id' => $recommendedStreamId,
        ];
    }

    // ========================================================================
    // CAPACITY STATUS
    // ========================================================================

    private function computeCapacityStatus(int $projectedOccupancy, int $capacity, int $currentYearValue, int $targetYearValue): array
    {
        $cfg = $this->fetchRow("SELECT * FROM academic_capacity_config ORDER BY id DESC LIMIT 1")
            ?: ['available_pct_threshold' => 20.00, 'limited_pct_threshold' => 1.00];
        $availPct = (float) ($cfg['available_pct_threshold'] ?? 20.00);
        $limPct = (float) ($cfg['limited_pct_threshold'] ?? 1.00);

        // A projected/forecast period is one whose target year is ahead of the
        // CURRENT active academic year (promotion results not yet finalized).
        $future = $currentYearValue !== null && $targetYearValue > $currentYearValue;

        if ($capacity <= 0) {
            return ['status' => 'setup_required', 'confidence' => 'low'];
        }
        if ($projectedOccupancy > $capacity) {
            return ['status' => $future ? 'projected_over' : 'over_capacity', 'confidence' => $future ? 'projected' : 'high'];
        }
        if ($projectedOccupancy >= $capacity) {
            return ['status' => $future ? 'projected_full' : 'full', 'confidence' => $future ? 'projected' : 'high'];
        }

        $remainingPct = (($capacity - $projectedOccupancy) / $capacity) * 100;
        $projectedPrefix = $future ? 'projected_' : '';

        if ($remainingPct > $availPct) {
            $status = $projectedPrefix . 'available';
            $confidence = $future ? 'projected' : 'high';
        } elseif ($remainingPct >= $limPct) {
            $status = $projectedPrefix . 'limited';
            $confidence = $future ? 'projected' : 'high';
        } else {
            $status = $projectedPrefix . 'limited';
            $confidence = $future ? 'projected' : 'high';
        }

        return ['status' => $status, 'confidence' => $confidence];
    }

    // ========================================================================
    // DB HELPERS
    // ========================================================================

    private function fetchApplication(int $id): ?array
    {
        return $this->fetchRow(
            "SELECT * FROM admission_applications WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    private function resolveClassForApplication(array $app): ?int
    {
        $grade = $app['grade_applying_for'] ?? null;
        $yearVal = (int) ($app['academic_year'] ?? 0);
        if (!$grade) {
            return null;
        }
        // DB stores grade_applying_for as 'Grade1'..'Grade9' (no space) but class
        // names are 'Grade 1'..'Grade 9' (with space). Normalize to the class name.
        $className = preg_replace('/^Grade(\d+)$/', 'Grade $1', (string) $grade);
        $className = str_replace('Playground', 'Playgroup', $className);
        // exact: name + academic_year; fallback: active class with that name.
        $row = $this->fetchRow(
            "SELECT id FROM classes WHERE name = ? AND CAST(academic_year AS UNSIGNED) = ? AND status = 'active' LIMIT 1",
            [$className, $yearVal]
        );
        if ($row) {
            return (int) $row['id'];
        }
        $row = $this->fetchRow(
            "SELECT id FROM classes WHERE name = ? AND status = 'active' ORDER BY academic_year DESC LIMIT 1",
            [$className]
        );
        return $row ? (int) $row['id'] : null;
    }

    private function fetchAcademicYear(int $id): ?array
    {
        return $this->fetchRow("SELECT * FROM academic_years WHERE id = ? LIMIT 1", [$id]);
    }

    private function fetchAcademicYearByValue(int $yearValue): ?array
    {
        if (!$yearValue) {
            return null;
        }
        return $this->fetchRow(
            "SELECT * FROM academic_years WHERE CAST(year_code AS UNSIGNED) = ? OR CAST(year_name AS UNSIGNED) = ? LIMIT 1",
            [$yearValue, $yearValue]
        );
    }

    private function fetchTerm(int $id): ?array
    {
        return $this->fetchRow("SELECT * FROM academic_terms WHERE id = ? LIMIT 1", [$id]);
    }

    private function fetchClass(int $id): ?array
    {
        return $this->fetchRow("SELECT * FROM classes WHERE id = ? LIMIT 1", [$id]);
    }

    private function countActiveEnrollment(int $classId): int
    {
        return (int) $this->fetchValue(
            "SELECT COUNT(*) FROM students s
             JOIN class_streams cs ON cs.id = s.stream_id
             WHERE cs.class_id = ? AND s.status = 'active'",
            [$classId]
        );
    }

    // ---- generic accessors --------------------------------------------------

    private function fetchRow(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchValue(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
