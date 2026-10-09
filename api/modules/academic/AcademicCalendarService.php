<?php
namespace App\API\Modules\academic;

use App\API\Services\NotificationService;
use App\API\Services\ReadReplicaService;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Academic Calendar Service
 *
 * Automates the generation of term calendars from the government-issued term
 * dates recorded on academic_year_terms (opening_date, half_term_start,
 * half_term_end, closing_date). The admin only enters the dates; weeks, school
 * days and half-term holidays are derived automatically.
 *
 * Standard Kenyan school year: Term 1 (14 weeks), Term 2 (14 weeks),
 * Term 3 (10 weeks). Week counts are derived from the recorded dates unless
 * explicitly overridden per term.
 *
 * Generation itself runs through the sp_generate_year_calendar stored
 * procedure (migration 010), which is idempotent and also callable from CLI
 * for backfills.
 */
class AcademicCalendarService
{
    private PDO $db;

    /** Standard week counts per term (term_number => weeks). */
    private const DEFAULT_WEEKS = [1 => 14, 2 => 14, 3 => 10];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate (or regenerate) the full term calendar for an academic year.
     *
     * Week counts are DERIVED from the recorded opening/closing dates using
     * real Monday-Friday school weeks (see countWeeks()), so terms that run
     * shorter or longer than the standard 14/14/10 Kenyan template produce a
     * correct week grid. The standard counts are only used as a fallback when
     * an explicit $weekCounts override is passed (e.g. from the year-rollover
     * prefill).
     *
     * Manually-marked days (is_manual = 1: emergency/national holidays,
     * closures, special events) are snapshotted before regeneration and
     * re-applied afterwards, so editing term dates never wipes them.
     *
     * @param int   $academicYearId
     * @param array $weekCounts      Optional explicit week counts keyed by term
     *                               number (e.g. [1 => 14, 2 => 14, 3 => 10]).
     *                               Missing terms fall back to the derived count.
     * @return array formatResponse payload with per-term summary
     */
    public function generateYearCalendar(int $academicYearId, array $weekCounts = []): array
    {
        try {
            if (!$this->academicYearExists($academicYearId)) {
                return formatResponse(false, null, 'Academic year not found');
            }

            $terms = $this->getYearTerms($academicYearId);
            if (empty($terms)) {
                return formatResponse(false, null, 'No terms exist for this academic year');
            }

            $missing = [];
            foreach ($terms as $term) {
                if (empty($term['opening_date']) || empty($term['closing_date'])) {
                    $missing[] = $term['name'];
                }
            }
            if (!empty($missing)) {
                return formatResponse(
                    false,
                    null,
                    'Opening/closing dates are required before generating the calendar for: ' . implode(', ', $missing)
                );
            }

            $manualDays = $this->getManualDays($academicYearId);

            // Derive per-term week counts from the actual date span unless an
            // explicit override was supplied for that term.
            $derived = $weekCounts;
            foreach ($terms as $term) {
                $termNo = (int) $term['term_id'];
                if (isset($derived[$termNo])) {
                    continue;
                }
                $derived[$termNo] = $this->countWeeks(
                    $term['opening_date'],
                    $term['closing_date'],
                    self::DEFAULT_WEEKS[$termNo] ?? 14
                );
            }

            $t1 = max(1, (int) ($derived[1] ?? self::DEFAULT_WEEKS[1]));
            $t2 = max(1, (int) ($derived[2] ?? self::DEFAULT_WEEKS[2]));
            $t3 = max(1, (int) ($derived[3] ?? self::DEFAULT_WEEKS[3]));

            $stmt = $this->db->prepare('CALL sp_generate_year_calendar(:year_id, :t1, :t2, :t3)');
            $stmt->execute([
                'year_id' => $academicYearId,
                't1' => $t1,
                't2' => $t2,
                't3' => $t3,
            ]);

            $this->reapplyManualDays($academicYearId, $manualDays);

            $this->notifyCalendarPublished($academicYearId);

            $summary = $this->getCalendar($academicYearId);

            return formatResponse(true, $summary, 'Academic calendar generated successfully');
        } catch (Exception $e) {
            return formatResponse(false, null, 'Calendar generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Number of Monday-Friday school weeks spanned by an opening/closing range.
     *
     * Mirrors sp_generate_year_calendar (migration 032): week 1 starts on the
     * opening day when that is a Mon-Fri (a weekend opening starts the first
     * week the following Monday); each week then runs Monday-Friday. The count
     * is the number of school weeks whose start falls on or before the closing
     * date, so a midweek close yields a short final week but still counts.
     */
    private function countWeeks(string $opening, string $closing, int $fallback): int
    {
        $start = strtotime($opening);
        $end = strtotime($closing);
        if (!$start || !$end || $end < $start) {
            return max(1, $fallback);
        }
        // Anchor to the Monday of the opening week (or the Monday after a
        // weekend opening). date('N'): 1=Mon .. 7=Sun.
        $dow = (int) date('N', $start);
        if ($dow >= 6) {
            $start = strtotime('next monday', $start);
        } else {
            $start = strtotime('-' . ($dow - 1) . ' days', $start);
        }
        $weeks = 0;
        for ($cursor = $start; $cursor !== false && $cursor <= $end; $cursor = strtotime('+7 days', $cursor)) {
            $weeks++;
        }
        return max(1, $weeks);
    }

    /**
     * Snapshot manually-marked calendar days for a year so regeneration can
     * re-apply them (the stored procedure rebuilds every term day).
     */
    private function getManualDays(int $academicYearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.date, d.calendar_day_type_id, d.title, d.description
             FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar_days") . " d
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " c ON c.id = d.academic_year_calendar_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = c.academic_year_term_id
             WHERE ayt.academic_year_id = ? AND d.is_manual = 1"
        );
        $stmt->execute([$academicYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Re-apply manually-marked days onto the freshly generated calendar by
     * matching each one to the regenerated day row for its date.
     */
    private function reapplyManualDays(int $academicYearId, array $days): void
    {
        if (empty($days)) {
            return;
        }

        $find = $this->db->prepare(
            "SELECT d.id
             FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar_days") . " d
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " c ON c.id = d.academic_year_calendar_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = c.academic_year_term_id
             WHERE ayt.academic_year_id = ? AND d.date = ?
             LIMIT 1"
        );
        $update = $this->db->prepare(
            "UPDATE academic_year_calendar_days
             SET calendar_day_type_id = ?, title = ?, description = ?, is_manual = 1
             WHERE id = ?"
        );

        foreach ($days as $day) {
            $find->execute([$academicYearId, $day['date']]);
            $id = $find->fetchColumn();
            if ($id) {
                $update->execute([
                    $day['calendar_day_type_id'],
                    $day['title'],
                    $day['description'],
                    $id,
                ]);
            }
        }
    }

    /**
     * Return the generated calendar summary for an academic year.
     */
    public function getCalendar(int $academicYearId): array
    {
        $sql = "
            SELECT
                ayt.id                         AS term_id,
                ayt.term_id                    AS term_number,
                t.name                         AS term_name,
                ayt.opening_date,
                ayt.half_term_start,
                ayt.half_term_end,
                ayt.closing_date,
                ayt.status,
                COUNT(c.id)                    AS weeks,
                COALESCE(SUM(DATEDIFF(c.week_end, c.week_start) + 1), 0) AS days
            FROM " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt
            JOIN terms t ON t.id = ayt.term_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " c ON c.academic_year_term_id = ayt.id
            WHERE ayt.academic_year_id = ?
            GROUP BY ayt.id, ayt.term_id, t.name, ayt.opening_date,
                     ayt.half_term_start, ayt.half_term_end, ayt.closing_date, ayt.status
            ORDER BY ayt.term_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$academicYearId]);
        $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalWeeks = 0;
        foreach ($terms as &$term) {
            $totalWeeks += (int) $term['weeks'];
        }
        unset($term);

        return [
            'academic_year_id' => $academicYearId,
            'standard_weeks' => self::DEFAULT_WEEKS,
            'total_weeks' => $totalWeeks,
            'terms' => $terms,
        ];
    }

    /**
     * Readiness check: every term must have dates and a generated calendar.
     *
     * @return array ['pass' => bool, 'checks' => [...]]
     */
    public function validateCalendar(int $academicYearId): array
    {
        $terms = $this->getYearTerms($academicYearId);
        if (empty($terms)) {
            return ['pass' => false, 'reason' => 'No academic_year_terms found for the year', 'checks' => []];
        }

        $checks = [];
        foreach ($terms as $term) {
            $hasDates = !empty($term['opening_date']) && !empty($term['closing_date']);

            $weeks = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_calendar WHERE academic_year_term_id = ?"
            );
            $weeks->execute([$term['id']]);
            $weekCount = (int) $weeks->fetchColumn();

            $checks[] = [
                'term' => $term['name'],
                'has_dates' => $hasDates,
                'weeks' => $weekCount,
                'pass' => $hasDates && $weekCount > 0,
            ];
        }

        $pass = !empty($checks) && count(array_filter($checks, fn ($c) => $c['pass'])) === count($checks);
        return ['pass' => $pass, 'reason' => $pass ? null : 'One or more terms lack dates or calendar weeks', 'checks' => $checks];
    }

    /**
     * Push a staff-wide notification that the school calendar is out.
     * De-duplicated so regenerations don't spam.
     */
    private function notifyCalendarPublished(int $academicYearId): void
    {
        try {
            $stmt = $this->db->prepare('SELECT name FROM academic_years WHERE id = ?');
            $stmt->execute([$academicYearId]);
            $year = $stmt->fetchColumn();
            $label = $year !== null && $year !== ''
                ? (string) $year . ' academic calendar'
                : 'Academic calendar';
            $service = new NotificationService($this->db);
            $service->push(
                'all_staff',
                'calendar',
                'Academic calendar released',
                'The ' . $label . ' is now available.',
                'high',
                ['dedup_minutes' => 60]
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicCalendarService] Notification push failed: ' . $e->getMessage());
        }
    }

    private function academicYearExists(int $academicYearId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM academic_years WHERE id = ?');
        $stmt->execute([$academicYearId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function getYearTerms(int $academicYearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT academic_year_term_id AS id, term_id, term_name AS name, term_code AS code,
                    opening_date, half_term_start, half_term_end,
                    closing_date, term_period_status AS status
             FROM " . ReadReplicaService::qualifiedRef('academic_term') . "
             WHERE academic_year_id = ?
             ORDER BY term_id"
        );
        $stmt->execute([$academicYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
