<?php

namespace App\API\Services;

use PDO;
use PDOException;
use App\API\Services\SharedCache;

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
            // Use database singleton if available
            if (class_exists('App\Database\Database')) {
                $this->db = \App\Database\Database::getInstance()->getConnection();
            } else {
                // Fallback to direct PDO connection
                $this->db = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            }
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
        $cacheKey = 'academic_context';

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
                    'current_term' => $currentTerm ? $currentTerm['name'] : null,
                    'term_id' => $currentTerm ? $currentTerm['id'] : null,
                    'calendar_period' => $this->getCalendarPeriod(),
                    'school_week' => $this->getSchoolWeek(),
                    'operations_open' => $this->areOperationsOpen(),
                    'grading_open' => $this->isGradingOpen(),
                    'timetable_editing_open' => $this->isTimetableEditingOpen(),
                    'last_updated' => date('c')
                ];
            }, $this->cacheTTL);
        } catch (PDOException $e) {
            error_log('Error getting academic context: ' . $e->getMessage());
            return [
                'current_year' => null,
                'academic_year_id' => null,
                'current_term' => null,
                'term_id' => null,
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

        $sql = "SELECT id, year_name, year_code, start_date, end_date, is_current, status
                FROM academic_years
                WHERE (is_current = 1 OR status = 'active')
                ORDER BY is_current DESC, start_date DESC, id DESC
                LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            $this->memo['year'] = $result ?: null;

            return $this->memo['year'];
        } catch (PDOException $e) {
            error_log('Error getting current academic year: ' . $e->getMessage());
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

        $sql = "SELECT id, name, academic_year_id, start_date, end_date, status, term_number
                FROM academic_terms
                WHERE academic_year_id = :year_id
                AND status IN ('active', 'current')
                ORDER BY
                    CASE WHEN status = 'current' THEN 0 ELSE 1 END,
                    CASE WHEN CURDATE() BETWEEN start_date AND end_date THEN 0 ELSE 1 END,
                    term_number ASC
                LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['year_id' => $currentYear['id']]);
            $result = $stmt->fetch();

            if (!$result && !empty($currentYear['year_code'])) {
                $fallbackSql = "SELECT id, name, academic_year_id, start_date, end_date, status, term_number
                        FROM academic_terms
                        WHERE academic_year_id IS NULL
                        AND year = :year_code
                        AND status IN ('active', 'current')
                        ORDER BY
                            CASE WHEN status = 'current' THEN 0 ELSE 1 END,
                            CASE WHEN CURDATE() BETWEEN start_date AND end_date THEN 0 ELSE 1 END,
                            term_number ASC
                        LIMIT 1";
                $fallbackStmt = $this->db->prepare($fallbackSql);
                $fallbackStmt->execute(['year_code' => $currentYear['year_code']]);
                $result = $fallbackStmt->fetch();
            }

            $this->memo['term'] = $result ?: null;

            return $this->memo['term'];
        } catch (PDOException $e) {
            error_log('Error getting current term: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current calendar period
     * 
     * @return string Current calendar period
     */
    public function getCalendarPeriod()
    {
        $currentTerm = $this->getCurrentTerm();
        
        if (!$currentTerm) {
            return 'no_active_term';
        }

        $now = new \DateTime();
        $startDate = new \DateTime($currentTerm['start_date']);
        $endDate = new \DateTime($currentTerm['end_date']);

        if ($now < $startDate) {
            return 'before_term';
        } elseif ($now > $endDate) {
            return 'after_term';
        } else {
            return 'during_term';
        }
    }

    /**
     * Get current school week
     * 
     * @return int|null Current week number
     */
    public function getSchoolWeek()
    {
        $currentTerm = $this->getCurrentTerm();
        
        if (!$currentTerm) {
            return null;
        }

        $now = new \DateTime();
        $startDate = new \DateTime($currentTerm['start_date']);
        
        $interval = $now->diff($startDate);
        $weekNumber = ceil($interval->days / 7) + 1;
        
        return $weekNumber;
    }

    /**
     * Check if academic operations are open
     * 
     * @return bool Whether operations are open
     */
    public function areOperationsOpen()
    {
        $currentYear = $this->getCurrentAcademicYear();
        $currentTerm = $this->getCurrentTerm();
        
        // Operations are open if there's an active year and term
        return $currentYear && $currentTerm &&
               $currentYear['status'] === 'active' &&
               in_array($currentTerm['status'], ['active', 'current'], true);
    }

    /**
     * Check if grading is open
     * 
     * @return bool Whether grading is open
     */
    public function isGradingOpen()
    {
        $currentTerm = $this->getCurrentTerm();
        
        if (!$currentTerm) {
            return false;
        }

        // Grading is open if term is active and we're not in holiday period
        $now = new \DateTime();
        $endDate = new \DateTime($currentTerm['end_date']);
        
        // Allow grading until 2 weeks after term ends
        $gradingDeadline = (clone $endDate)->modify('+2 weeks');
        
        return $now <= $gradingDeadline;
    }

    /**
     * Check if timetable editing is open
     * 
     * @return bool Whether timetable editing is open
     */
    public function isTimetableEditingOpen()
    {
        $currentTerm = $this->getCurrentTerm();
        
        if (!$currentTerm) {
            return false;
        }

        // Timetable editing is open if term is active and we're not in exam period
        $now = new \DateTime();
        $startDate = new \DateTime($currentTerm['start_date']);
        $endDate = new \DateTime($currentTerm['end_date']);
        
        // Close timetable editing 1 week before term ends
        $timetableCloseDate = (clone $endDate)->modify('-1 week');
        
        return $now >= $startDate && $now <= $timetableCloseDate;
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
        $sql = "SELECT * FROM academic_years 
                ORDER BY year_name DESC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error getting academic years: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get terms for a specific academic year
     * 
     * @param int $academicYearId Academic year ID
     * @return array Terms for the academic year
     */
    public function getTerms($academicYearId)
    {
        $sql = "SELECT * FROM academic_terms 
                WHERE academic_year_id = :year_id 
                ORDER BY term_number ASC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['year_id' => $academicYearId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error getting terms: ' . $e->getMessage());
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

            // Deactivate all years
            $sql = "UPDATE academic_years SET is_current = 0 WHERE is_current = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            // Activate specified year
            $sql = "UPDATE academic_years SET is_current = 1, status = 'active' 
                    WHERE id = :year_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['year_id' => $yearId]);

            $this->db->commit();

            // Clear cache
            $this->clearCache();

            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Error setting current academic year: ' . $e->getMessage());
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
                $sql = "UPDATE academic_terms SET status = 'upcoming' 
                        WHERE academic_year_id = :year_id AND status = 'active'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['year_id' => $currentYear['id']]);
            }

            // Activate specified term
            $sql = "UPDATE academic_terms SET status = 'active' 
                    WHERE id = :term_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['term_id' => $termId]);

            $this->db->commit();

            // Clear cache
            $this->clearCache();

            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Error setting current term: ' . $e->getMessage());
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
     * Set cache TTL
     * 
     * @param int $ttl Cache time in seconds
     */
    public function setCacheTTL($ttl)
    {
        $this->cacheTTL = $ttl;
    }
}
