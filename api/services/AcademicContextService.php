<?php

namespace App\API\Services;

use PDO;
use PDOException;
use App\API\Services\SharedCache;
use App\API\Services\ReadReplicaService;
use App\API\Services\AcademicYearLifecycleService;

/**
 * Academic Context Service
 * 
 * Provides centralized access to current academic context information
 * including academic year, term, calendar period, and operational status.
 * 
 * This service maintains the current academic state and provides
 * methods to check if various academic operations are permitted.
 */
class AcademicContextService
{
    private $db;
    private $cache = [];
    private $cacheTTL = 300; // 5 minutes default cache

    /**
     * Shared, node-spanning cache. All 5 LB nodes warm one copy on disk so a
     * single DB read serves every node; this is what keeps the parallel
     * dashboard burst from saturating the lone MySQL.
     */
    private ?SharedCache $shared = null;

    /**
     * Per-request memo: year/term resolved once and reused by every context
     * sub-method (was an N+1 of ~10 academic_years / academic_terms reads
     * per request, which is what made the dashboard burst slow).
     */
    private array $memo = [];

    public function __construct($database = null)
    {
        if ($database === null) {
            // Always share the process singleton. A second PDO here meant a
            // second MySQL connection per request, halving the connection
            // budget for every request that resolved academic context.
            $this->db = \App\Database\Database::getInstance()->getConnection();
        } else {
            $this->db = $database;
        }
    }

    /**
     * Get current academic context
     * 
     * @return array Current academic context
     */
    public function getCurrentContext()
    {
        // Bumped when the payload shape changed so a warm 5-minute cache entry
        // cannot hide the academic-year dates from dashboards.
        $cacheKey = 'academic_context_v2';

        // Node-spanning shared cache: every LB node reads one warmed copy off
        // disk instead of each re-querying the lone MySQL. A cold miss computes
        // once; subsequent hits (this node and all siblings) are <5ms file reads.
        $this->shared ??= new SharedCache();

        try {
            return $this->shared->remember($cacheKey, function () {
                // Resolve year + term ONCE. Sub-methods below reuse these via
                // getCurrentTerm()/getCurrentAcademicYear(), which now read the
                // memoized pair instead of re-querying (was an N+1 of ~10 queries).
                $currentYear = $this->getCurrentAcademicYear();
                $currentTerm = $this->getCurrentTerm();

                return [
                    'current_year' => $currentYear ? $currentYear['year_name'] : null,
                    'academic_year_id' => $currentYear ? $currentYear['id'] : null,
                    // Lifecycle wording for the UI: "active - current",
                    // "active - upcoming", "active - completed". Archiving is a
                    // separate system-admin action and never appears here.
                    'current_year_label' => $currentYear
                        ? AcademicYearLifecycleService::label(
                            (string) $currentYear['status'],
                            (string) ($currentYear['state'] ?? '')
                        )
                        : null,
                    'academic_year_id' => $currentYear ? $currentYear['id'] : null,
                    // The school year's own window (not a calendar year), so
                    // dashboards can default "This Year" to the academic year.
                    'academic_year_start_date' => ($currentYear && $currentYear['start_date'])
                        ? substr((string) $currentYear['start_date'], 0, 10) : null,
                    'academic_year_end_date' => ($currentYear && $currentYear['end_date'])
                        ? substr((string) $currentYear['end_date'], 0, 10) : null,
                    // The term of the current year in session, or during a
                    // holiday the term that has just closed (records, reports
                    // and marks default to it). term_in_session tells which.
                    'current_term' => $currentTerm ? $currentTerm['name'] : null,
                    'term_id' => $currentTerm ? $currentTerm['id'] : null,
                    'term_in_session' => $currentTerm ? (bool) ($currentTerm['in_session'] ?? false) : false,
                    'term_start_date' => $currentTerm ? $currentTerm['start_date'] : null,
                    'term_end_date' => $currentTerm ? $currentTerm['end_date'] : null,
                    // Where the school year is today: a term, a half term or
                    // a holiday (April, August, December), and what follows.
                    'calendar_period' => $this->getCalendarPeriod(),
                    'current_period' => $this->periodSnapshot()['current_period'],
                    'next_period' => $this->periodSnapshot()['next_period'],
                    // The term to prepare for: in session, or during a holiday
                    // the next one to open (next year's Term 1 in December).
                    'planning_term_id' => $this->periodSnapshot()['planning_term']['academic_year_term_id'] ?? null,
                    'planning_academic_year_id' => $this->periodSnapshot()['planning_term']['academic_year_id'] ?? null,
                    'planning_term_label' => $this->periodSnapshot()['planning_term']['label'] ?? null,
                    'school_week' => $this->getSchoolWeek(),
                    'operations_open' => $this->areOperationsOpen(),
                    'grading_open' => $this->isGradingOpen(),
                    'timetable_editing_open' => $this->isTimetableEditingOpen(),
                    // Soft transition: the outgoing year stays operational until
                    // its last day while the target year is prepared alongside it.
                    'handover' => $this->getHandover(),
                    'last_updated' => date('c')
                ];
            }, $this->cacheTTL);
        } catch (PDOException $e) {
            \App\API\Services\Logger::legacyError('Error getting academic context: ' . $e->getMessage());
            return [
                'current_year' => null,
                'academic_year_id' => null,
                'academic_year_start_date' => null,
                'academic_year_end_date' => null,
                'current_term' => null,
                'term_id' => null,
                'term_start_date' => null,
                'term_end_date' => null,
                'calendar_period' => null,
                'school_week' => null,
                'operations_open' => false,
                'grading_open' => false,
                'timetable_editing_open' => false,
                'last_updated' => date('c')
            ];
        }
    }

    /**
     * Get current academic year
     * 
     * @return array|null Current academic year data
     */
    public function getCurrentAcademicYear()
    {
        // Memoized within the request: callers (getCurrentContext, getCurrentTerm,
        // calendar/school-week/operations checks) all share ONE DB read.
        if (isset($this->memo['year'])) {
            return $this->memo['year'];
        }

        $sql = "SELECT id, year_name, year_code, start_date, end_date, is_current, status, state
                FROM academic_years
                WHERE is_current = 1
                ORDER BY start_date DESC, id DESC
                LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            $this->memo['year'] = $result ?: null;

            return $this->memo['year'];
        } catch (PDOException $e) {
            \App\API\Services\Logger::legacyError('Error getting current academic year: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current term
     * 
     * @return array|null Current term data
     */
    public function getCurrentTerm()
    {
        if (isset($this->memo['term'])) {
            return $this->memo['term'];
        }

        $currentYear = $this->getCurrentAcademicYear();

        if (!$currentYear) {
            return null;
        }

        $academicTerm = ReadReplicaService::qualifiedRef('academic_term');
        $sql = "SELECT academic_year_term_id AS id, term_name AS name, academic_year_id,
                       opening_date AS start_date, closing_date AS end_date,
                       term_period_status AS status, term_code AS term_number
                FROM {$academicTerm}
                WHERE academic_year_id = :year_id AND term_period_status = 'current'
                ORDER BY CASE WHEN CURDATE() BETWEEN opening_date AND closing_date THEN 0 ELSE 1 END,
                         term_code ASC
                LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['year_id' => $currentYear['id']]);
            $result = $stmt->fetch();

            if (!$result && !empty($currentYear['year_code'])) {
                $fallbackSql = "SELECT academic_year_term_id AS id, term_name AS name, academic_year_id,
                                       opening_date AS start_date, closing_date AS end_date,
                                       term_period_status AS status, term_code AS term_number
                                FROM {$academicTerm}
                                WHERE year_code = :year_code AND term_period_status = 'current'
                                ORDER BY CASE WHEN CURDATE() BETWEEN opening_date AND closing_date THEN 0 ELSE 1 END,
                                         term_code ASC
                                LIMIT 1";
                $fallbackStmt = $this->db->prepare($fallbackSql);
                $fallbackStmt->execute(['year_code' => $currentYear['year_code']]);
                $result = $fallbackStmt->fetch();
            }

            if ($result) {
                $result['in_session'] = true;
            } else {
                // A holiday: the term of this year that has just closed.
                $last = $this->periods()->termInSessionOrLast();
                if ($last && (int) $last['academic_year_id'] === (int) $currentYear['id']) {
                    $result = [
                        'id' => (int) $last['id'],
                        'name' => 'Term ' . (int) $last['term_id'],
                        'academic_year_id' => (int) $last['academic_year_id'],
                        'start_date' => $last['opening_date'],
                        'end_date' => $last['closing_date'],
                        'status' => $last['status'],
                        'term_number' => 'T' . (int) $last['term_id'],
                        'in_session' => false,
                    ];
                }
            }
            $this->memo['term'] = $result ?: null;

            return $this->memo['term'];
        } catch (PDOException $e) {
            \App\API\Services\Logger::legacyError('Error getting current term: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The academic-year term a report should default to.
     *
     * A report must always be scoped to one term of one academic year: the
     * term number alone (Term 1/2/3) repeats every year, and leaving the term
     * out sums every year together. Between terms no term is 'current', so:
     * the term in session today, else the most recent term that has opened,
     * else the next term to open.
     *
     * @return int|null academic_year_terms.id
     */
    public function getReportingTermId(): ?int
    {
        if (array_key_exists('reporting_term_id', $this->memo)) {
            return $this->memo['reporting_term_id'];
        }
        $sql = "SELECT id FROM academic_year_terms
                ORDER BY
                    CASE
                        WHEN status = 'current' THEN 0
                        WHEN CURDATE() BETWEEN opening_date AND closing_date THEN 1
                        WHEN opening_date <= CURDATE() THEN 2
                        ELSE 3
                    END,
                    CASE WHEN opening_date <= CURDATE() THEN opening_date END DESC,
                    opening_date ASC,
                    id ASC
                LIMIT 1";
        try {
            $id = $this->db->query($sql)->fetchColumn();
            $this->memo['reporting_term_id'] = $id ? (int) $id : null;
        } catch (PDOException $e) {
            \App\API\Services\Logger::legacyError('Error resolving reporting term: ' . $e->getMessage());
            $this->memo['reporting_term_id'] = null;
        }
        return $this->memo['reporting_term_id'];
    }

    /**
     * Get current calendar period
     *
     * @return string Current calendar period
     */
    public function getCalendarPeriod()
    {
        // term | half_term | holiday (null when no academic year covers today)
        return $this->periodSnapshot()['current_period']['kind'] ?? null;
    }

    /** The shared period model (terms, half terms, holidays) of the school year. */
    private function periods(): AcademicPeriodService
    {
        return $this->memo['periods'] ??= new AcademicPeriodService($this->db);
    }

    private function periodSnapshot(): array
    {
        return $this->memo['period_snapshot'] ??= $this->periods()->snapshot();
    }

    /**
     * Get current school week
     *
     * @return int|null Week of the term in session; null during a holiday
     */
    public function getSchoolWeek()
    {
        $currentTerm = $this->getCurrentTerm();

        if (!$currentTerm || empty($currentTerm['in_session'])) {
            return null;
        }

        $now = new \DateTime();
        $startDate = new \DateTime($currentTerm['start_date']);

        $interval = $now->diff($startDate);
        $weekNumber = ceil($interval->days / 7) + 1;

        return $weekNumber;
    }

    /**
     * Academic operations run all year: holidays are periods of the academic
     * year too (admissions, fees, preparing the next term all continue).
     *
     * @return bool Whether operations are open
     */
    public function areOperationsOpen()
    {
        $currentYear = $this->getCurrentAcademicYear();
        return $currentYear && $currentYear['status'] === 'active'
            && $this->periodSnapshot()['current_period'] !== null;
    }

    /**
     * Marks can be entered while a term is in session and for two weeks after
     * it closes (into the holiday that follows it).
     *
     * @return bool Whether grading is open
     */
    public function isGradingOpen()
    {
        $currentTerm = $this->getCurrentTerm();

        if (!$currentTerm) {
            return false;
        }
        if (!empty($currentTerm['in_session'])) {
            return true;
        }
        $gradingDeadline = (new \DateTime($currentTerm['end_date']))->modify('+2 weeks');
        return new \DateTime() <= $gradingDeadline;
    }

    /**
     * The timetable of the term being prepared can be edited during the
     * holiday before it and until one week before it closes.
     *
     * @return bool Whether timetable editing is open
     */
    public function isTimetableEditingOpen()
    {
        $planning = $this->periodSnapshot()['planning_term'] ?? null;
        if (!$planning) {
            return false;
        }
        $closeDate = (new \DateTime($planning['end_date']))->modify('-1 week');
        return new \DateTime() <= $closeDate;
    }

    /**
     * Soft year-to-year handover state.
     *
     * Reports the year that is still operational, the year being prepared, and
     * the date the automatic switch is due. The switch itself is performed by
     * AcademicActivationService::sweep() when the calendar says so — never by
     * this read path.
     */
    public function getHandover(): array
    {
        try {
            $activation = new AcademicActivationService($this->db);
            $handover = $activation->handover();

            // The three clocks run on different boundaries. Admissions and fee
            // preparation may open before the year; attendance and marks may not.
            $handover['clocks'] = [
                'admissions_open' => $this->admissionsOpen($handover),
                'fees_open' => $this->feesOpen($handover),
                'operations_open' => $this->areOperationsOpen(),
            ];

            return $handover;
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('Error getting academic handover: ' . $e->getMessage());
            return [];
        }
    }

    /** Admissions may enter into the prepared year before it opens. */
    private function admissionsOpen(array $handover): bool
    {
        $prepared = $handover['prepared_year'] ?? null;
        if (!$prepared) {
            return false;
        }
        $start = (string) ($prepared['start_date'] ?? '');
        if ($start === '') {
            return false;
        }

        return strtotime($start) - strtotime($handover['on_date'] ?? date('Y-m-d')) <= 60 * 86400;
    }

    /** Fee schedules for the prepared year may be issued before it opens. */
    private function feesOpen(array $handover): bool
    {
        return $this->admissionsOpen($handover);
    }

    /**
     * Check if specific operation is permitted
     * 
     * @param string $operation Operation to check
     * @return bool Whether operation is permitted
     */
    public function canPerformOperation($operation)
    {
        $permissions = [
            'grade_entry' => $this->isGradingOpen(),
            'marks_entry' => $this->isGradingOpen(),
            'timetable_edit' => $this->isTimetableEditingOpen(),
            'class_assignment' => $this->areOperationsOpen(),
            'student_promotion' => $this->areOperationsOpen(),
            'report_card_generation' => $this->isGradingOpen()
        ];
        
        return isset($permissions[$operation]) ? $permissions[$operation] : false;
    }

    /**
     * Get all academic years
     * 
     * @return array All academic years
     */
    public function getAcademicYears()
    {
        // Every year stays visible: there is no archiving.
        $sql = "SELECT * FROM academic_years
                ORDER BY year_name DESC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            \App\API\Services\Logger::legacyError('Error getting academic years: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get academic terms for a given academic year
     * 
     * @param int $academicYearId Academic year ID
     * @return array Terms for the academic year
     */
    public function getTerms($academicYearId)
    {
        $sql = "SELECT academic_year_term_id AS id, term_name AS name, term_code AS term_number, academic_year_id, opening_date AS start_date, closing_date AS end_date, term_period_status AS status
                FROM " . ReadReplicaService::qualifiedRef('academic_term') . "
                WHERE academic_year_id = :year_id 
                ORDER BY term_code ASC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['year_id' => $academicYearId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            \App\API\Services\Logger::legacyError('Error getting terms: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set current academic year
     * 
     * @param int $yearId Academic year ID
     * @return bool Success status
     */
    public function setCurrentAcademicYear($yearId)
    {
        try {
            $this->db->beginTransaction();

            // Only the single current row moves. A year that loses the flag
            // falls back to upcoming; completed years keep their state so their
            // data stays readable and writable-by-privilege during the overlap.
            $sql = "UPDATE academic_years
                       SET is_current = 0,
                           state = CASE WHEN state = 'current' THEN 'upcoming' ELSE state END
                     WHERE id <> :year_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['year_id' => $yearId]);

            // Activate specified year. Every non-archived year is 'active'; the
            // lifecycle lives in `state`.
            $sql = "UPDATE academic_years
                       SET is_current = 1,
                           state = 'current',
                           status = 'active',
                           completed_at = NULL
                     WHERE id = :year_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['year_id' => $yearId]);

            $this->db->commit();

            // Clear cache
            $this->clearCache();

            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            \App\API\Services\Logger::legacyError('Error setting current academic year: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set current term
     * 
     * @param int $termId Term ID
     * @return bool Success status
     */
    public function setCurrentTerm($termId)
    {
        try {
            $this->db->beginTransaction();

            // Deactivate all terms for the current year
            $currentYear = $this->getCurrentAcademicYear();
            if ($currentYear) {
                $sql = "UPDATE academic_year_terms SET status = 'upcoming' 
                        WHERE academic_year_id = :year_id AND status = 'current'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['year_id' => $currentYear['id']]);
            }

            // Activate specified term
            $sql = "UPDATE academic_year_terms SET status = 'current' 
                    WHERE id = :term_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['term_id' => $termId]);

            $this->db->commit();

            // Clear cache
            $this->clearCache();

            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            \App\API\Services\Logger::legacyError('Error setting current term: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear cache
     */
    private function clearCache()
    {
        $this->cache = [];
    }

    /**
     * Year label for document filenames (e.g. the public calendar PDF download).
     */
    public function yearCode(int $yearId): ?string
    {
        try {
            $stmt = $this->db->prepare('SELECT year_code FROM academic_years WHERE id = ?');
            $stmt->execute([$yearId]);
            $code = $stmt->fetchColumn();
            return $code !== false ? (string) $code : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Display name of a class master row ('' when unknown). */
    public function className(int $classId): string
    {
        if ($classId <= 0) {
            return '';
        }
        $stmt = $this->db->prepare('SELECT name FROM classes WHERE id = ?');
        $stmt->execute([$classId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    /**
     * Active streams for a class (legacy class_streams table) — used by the
     * public enrolment-form cascade endpoint (streams.php).
     *
     * @return list<array<string,mixed>>
     */
    public function activeStreamsForClass(int $classId): array
    {
        // A class in year Y exposes the streams allocated to it through
        // academic_year_classes -> academic_year_class_streams -> streams.
        // That three-table walk is pre-composed into mmv_academic_calendar (one
        // row per class stream, carrying class_id/stream_name/class_stream_status),
        // so this is a single indexed table read with a live WHERE filter.
        $stmt = $this->db->prepare(
            "SELECT DISTINCT stream_id AS id, stream_name
             FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . "
             WHERE class_id = :cid AND class_stream_status = 'active'
             ORDER BY stream_name ASC"
        );
        $stmt->execute([':cid' => $classId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Distinct class names in the classes master (staff admission picker). */
    public function gradeNames(): array
    {
        return $this->db->query(
            "SELECT DISTINCT name FROM classes WHERE name IS NOT NULL AND name <> '' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** All academic years for the picker, newest first. */
    public function yearsForPicker(): array
    {
        return $this->db->query(
            'SELECT id,year_code,year_name,status,is_current,start_date,end_date FROM academic_years
             ORDER BY start_date DESC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Learning areas scoped to the given ids (proposal builder). */
    public function learningAreasIn(array $areaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $areaIds))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id,name,code,level_band,description,status,levels,is_optional FROM learning_areas WHERE id IN ($in) ORDER BY name"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** All learning areas (proposal builder unrestricted scope). */
    public function learningAreasAll(): array
    {
        return $this->db->query('SELECT id,name,code,level_band,description,status,levels,is_optional FROM learning_areas ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Instance rows for one academic year. */
    public function termsForYear(int $yearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT academic_year_term_id AS id, academic_year_id, term_period_status AS status, term_name, term_id AS term_number
             FROM " . ReadReplicaService::qualifiedRef('academic_term') . "
             WHERE academic_year_id = ? ORDER BY term_id"
        );
        $stmt->execute([$yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * All academic years, newest first (public printable-download pickers).
     *
     * @return list<array<string,mixed>>
     */
    public function yearsNewestFirst(): array
    {
        return $this->db->query(
            "SELECT id, year_code, year_name, status, is_current FROM academic_years
             ORDER BY id DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Distinct classes configured for an academic year.
     *
     * @return list<array{id:int, name:string}>
     */
    public function classesForYear(int $yearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT class_id AS id, class_name AS name
             FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . "
             WHERE academic_year_id = ?
             ORDER BY class_id"
        );
        $stmt->execute([$yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Set cache TTL
     * 
     * @param int $ttl Cache time in seconds
     */
public function setCacheTTL($ttl)
        {
            $this->cacheTTL = $ttl;
        }
}
