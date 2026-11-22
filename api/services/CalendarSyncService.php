<?php
namespace App\API\Services;

use PDO;
use Exception;

/**
 * CalendarSyncService
 *
 * Keeps academic_year_calendar_days, school_events and exam_schedules in
 * sync so the school calendar is a single source of truth:
 *
 *   * calendar day  -> school_events mirror (holidays, exam days, special
 *     events, half-term breaks, public holidays ...). A day typed exam_day
 *     also generates one exam_schedules skeleton row per active class-stream,
 *     ready for per-class timings to be filled in.
 *   * school_events -> calendar day write-back. Editing/creating an event
 *     whose type maps to a day type updates (or moves) the linked calendar day.
 *   * term boundary awareness: opening/closing days are surfaced as events.
 *   * status normalisation: events and exam rows are auto-completed once their
 *     date passes, so closed-term entries never stay "upcoming".
 *
 * All operations are idempotent and safe to re-run (e.g. after a calendar
 * regeneration or on page load).
 */
class CalendarSyncService
{
    private PDO $db;

    /** calendar_day_type code -> school_events.type used for mirror events. */
    private const DAY_TYPE_TO_EVENT = [
        'half_day' => 'half_day',
        'exam_day' => 'exam',
        'special_event' => 'special_event',
        'holiday' => 'holiday',
        'public_holiday' => 'public_holiday',
        'school_holiday' => 'school_holiday',
    ];

    /** school_events.type -> calendar_day_type code for write-backs. */
    private const EVENT_TO_DAY_TYPE = [
        'exam' => 'exam_day',
        'holiday' => 'school_holiday',
        'school_holiday' => 'school_holiday',
        'public_holiday' => 'public_holiday',
        'special_event' => 'special_event',
        'event' => 'special_event',
        'half_day' => 'half_day',
    ];

    /** Day types that never carry a mirror event on their own. */
    private const PLAIN_DAY_TYPES = ['school_day', 'weekend'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ==================== PUBLIC API ====================

    /**
     * Reconcile a single calendar day against school_events + exam_schedules.
     *
     * An exam day on the calendar is a planning entry: it records that exams
     * will run on that date and shows on every calendar. It does not create
     * exam_schedules rows, so marking next term's or next year's exam dates
     * never starts the exams workflow (timetables, candidates, results). The
     * exams module creates those when the period is actually prepared.
     */
    public function syncDay(int $dayId, bool $createExamScheduleRows = false): array
    {
        return $this->retryOnLockFailure(fn () => $this->syncDayOnce($dayId, $createExamScheduleRows));
    }

    private function syncDayOnce(int $dayId, bool $createExamScheduleRows): array
    {
        $day = $this->fetchDay($dayId);
        if (!$day) {
            return ['day' => $dayId, 'event' => null, 'exams' => 0];
        }

        $dayType = $day['day_type'] ?: 'school_day';
        $exams = 0;

        if (in_array($dayType, self::PLAIN_DAY_TYPES, true)) {
            $this->deleteMirrorEvent($dayId);
            if ($day['academic_year_term_id']) {
                $this->cancelCalendarExams($day['academic_year_term_id'], $day['date']);
            }
            return ['day' => $dayId, 'event' => 'removed', 'exams' => 0];
        }

        $eventType = self::DAY_TYPE_TO_EVENT[$dayType] ?? 'special_event';
        $this->upsertMirrorEvent($day, $eventType);

        if ($createExamScheduleRows && $dayType === 'exam_day' && $day['academic_year_term_id']) {
            $exams = $this->syncExamSchedules($day['date'], (int) $day['academic_year_term_id'], $day['title']);
        }

        return ['day' => $dayId, 'event' => 'synced', 'exams' => $exams];
    }

    /**
     * Reconcile every calendar day between $from and $to (inclusive).
     */
    public function syncRange(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM academic_year_calendar_days WHERE date BETWEEN ? AND ? ORDER BY date"
        );
        $stmt->execute([$from, $to]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $synced = 0;
        $exams = 0;
        foreach ($ids as $dayId) {
            $r = $this->syncDay((int) $dayId);
            $synced += $r['event'] === 'synced' ? 1 : 0;
            $exams += (int) $r['exams'];
        }
        return ['days' => count($ids), 'events_synced' => $synced, 'exam_rows_created' => $exams];
    }

    /**
     * Full reconciliation for an academic year (defaults to the current year).
     */
    public function syncAcademicYear(?int $yearId = null): array
    {
        $year = $this->currentYear($yearId);
        if (!$year) {
            return ['days' => 0, 'events_synced' => 0, 'exam_rows_created' => 0, 'boundary_events' => 0];
        }

        $range = $this->syncRange($year['start_date'], $year['end_date']);
        // Opening/closing days are no longer free-form events: they are titled
        // calendar-day rows produced by sp_generate_year_calendar straight from
        // academic_year_terms dates (single source of truth - no way for the
        // events list to contradict the term dates).
        return $range + ['boundary_events' => 0];
    }

    /**
     * Auto-complete events and exam rows whose dates have passed.
     */
    public function normalizeStatuses(): array
    {
        return $this->retryOnLockFailure(fn () => $this->normalizeStatusesOnce());
    }

    /**
     * The reconciliation writes are idempotent and run from page loads, the
     * events-sync action and term setup, so two can overlap and InnoDB picks
     * one as a deadlock victim. Retry that victim, but only outside a caller's
     * transaction: a deadlock rolls the whole transaction back, so retrying
     * one statement there would commit half of the caller's work.
     */
    private function retryOnLockFailure(callable $work): array
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return $work();
            } catch (\PDOException $e) {
                $driverCode = (int) ($e->errorInfo[1] ?? 0);
                $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
                $retryable = $driverCode === 1213 || $driverCode === 1205 || $sqlState === '40001';
                if (!$retryable || $attempt >= 2 || $this->db->inTransaction()) {
                    throw $e;
                }
                usleep(100000 * ($attempt + 1));
            }
        }
    }

    private function normalizeStatusesOnce(): array
    {
        $events = 0;
        $exams = 0;

        $events += (int) $this->db->exec(
            "UPDATE school_events SET status = 'past'
             WHERE status IN ('upcoming','ongoing') AND end_at < NOW()"
        );
        $events += (int) $this->db->exec(
            "UPDATE school_events SET status = 'ongoing'
             WHERE status = 'upcoming' AND start_at <= NOW() AND end_at >= NOW()"
        );
        $exams += (int) $this->db->exec(
            "UPDATE exam_schedules SET status = 'completed'
             WHERE status IN ('scheduled','upcoming','in_progress') AND exam_date < CURDATE()"
        );

        // Remove mirror rows whose linked calendar day no longer exists
        // (left behind when the day grid was regenerated) so they don't
        // accumulate with every calendar rebuild.
        $orphans = (int) $this->db->exec(
            "DELETE ev FROM school_events ev
             LEFT JOIN academic_year_calendar_days d ON d.id = ev.calendar_day_id
             WHERE ev.calendar_day_id IS NOT NULL AND d.id IS NULL"
        );

        return ['events' => $events, 'exams' => $exams, 'orphans_pruned' => $orphans];
    }

    /**
     * Mark every Mon-Fri day of the week containing $anchorDate as exam_day
     * and generate the exam skeletons for those dates.
     */
    public function markExamWeek(string $anchorDate): array
    {
        $week = $this->db->prepare(
            "SELECT id, academic_year_term_id, week_start, week_end
             FROM academic_year_calendar
             WHERE week_start <= ? AND week_end >= ? ORDER BY week_start LIMIT 1"
        );
        $week->execute([$anchorDate, $anchorDate]);
        $row = $week->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['week' => null, 'dates' => [], 'exams' => 0, 'message' => 'No calendar week found for the given date'];
        }

        $examTypeId = (int) $this->db->query("SELECT id FROM calendar_day_types WHERE code = 'exam_day'")->fetchColumn();

        $days = $this->db->prepare(
            "SELECT id, date, title FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar_days") . "
             WHERE date BETWEEN ? AND ?
               AND DAYOFWEEK(date) BETWEEN 2 AND 6
               AND calendar_day_type_id IN (
                   SELECT id FROM calendar_day_types WHERE code = 'school_day'
               )"
        );
        $days->execute([$row['week_start'], $row['week_end']]);
        $dayRows = $days->fetchAll(PDO::FETCH_ASSOC);

        $updated = [];
        $exams = 0;
        foreach ($dayRows as $day) {
            $this->db->prepare(
                "UPDATE academic_year_calendar_days
                 SET calendar_day_type_id = ?, title = ?, is_manual = 1 WHERE id = ?"
            )->execute([$examTypeId, 'Examinations', $day['id']]);
            $r = $this->syncDay((int) $day['id']);
            $updated[] = $day['id'];
            $exams += (int) $r['exams'];
        }

        return [
            'week' => $row['week_start'] . ' / ' . $row['week_end'],
            'dates' => $updated,
            'exams' => $exams,
            'message' => count($updated) . ' day(s) marked as exam week',
        ];
    }

    /**
     * Write an event's changes back to its linked calendar day. When the event
     * type maps to a day type and no calendar day is linked yet, the day for the
     * event's date is updated and linked.
     */
    public function applyEventToCalendar(int $eventId, array $data): array
    {
        $event = $this->db->prepare(
            "SELECT id, calendar_day_id, title, type, DATE(start_at) AS date, DATE(end_at) AS end_date FROM school_events WHERE id = ?"
        );
        $event->execute([$eventId]);
        $ev = $event->fetch(PDO::FETCH_ASSOC);
        if (!$ev) {
            return ['event' => $eventId, 'calendar_day' => null];
        }

        $newDate = $data['start_date'] ?? $data['date'] ?? $ev['date'];
        $newTitle = $data['name'] ?? $data['title'] ?? null;
        $newType = strtolower(trim((string) ($data['type'] ?? $ev['type'])));
        $dayTypeCode = self::EVENT_TO_DAY_TYPE[$newType] ?? null;

        if ($ev['calendar_day_id']) {
            $this->updateLinkedDay($ev['calendar_day_id'], $newDate, $newTitle, $data['description'] ?? null, $dayTypeCode);
            if ($dayTypeCode !== null && !empty($data['end_date'])) {
                $this->markRange($newDate, (string) $data['end_date'], $newTitle ?? $ev['title'], $data['description'] ?? null, $dayTypeCode);
            }
            return ['event' => $eventId, 'calendar_day' => $ev['calendar_day_id']];
        }

        if ($dayTypeCode === null) {
            return ['event' => $eventId, 'calendar_day' => null];
        }

        $dayId = $this->findDayForDate($newDate);
        if (!$dayId) {
            return ['event' => $eventId, 'calendar_day' => null];
        }

        $title = $newTitle ?? $ev['title'];
        // Link the event to its first day before the day is updated, so the
        // day adopts this event instead of creating a second entry for the
        // same date. The first day carries the event as a one-day entry; the
        // remaining days of the range get their own calendar entry below, and
        // the events list folds the run back into one dated range.
        $link = $this->db->prepare(
            "UPDATE school_events SET calendar_day_id = ?, source = 'manual',
                    end_at = CONCAT(DATE(start_at), ' 23:59:59') WHERE id = ?"
        );
        $link->execute([$dayId, $eventId]);
        $this->updateLinkedDay($dayId, $newDate, $title, $data['description'] ?? null, $dayTypeCode);
        $this->db->prepare("UPDATE school_events SET source = 'manual' WHERE id = ?")->execute([$eventId]);

        $endDate = (string) ($data['end_date'] ?? $ev['end_date'] ?? $newDate);
        $this->markRange($newDate, $endDate, $title, $data['description'] ?? null, $dayTypeCode);
        return ['event' => $eventId, 'calendar_day' => $dayId];
    }

    /**
     * Mark the days after $from, up to $to, with the same day type and title:
     * an exam period, a holiday or a special event that runs for several days.
     * Weekends stay weekends unless the entry is a holiday, and a day that
     * already holds a different manual entry is left alone.
     */
    private function markRange(string $from, string $to, ?string $title, ?string $description, string $dayTypeCode): void
    {
        if ($to <= $from) {
            return;
        }
        $isHoliday = in_array($dayTypeCode, ['school_holiday', 'public_holiday', 'holiday'], true);
        $limit = (new \DateTimeImmutable($from))->modify('+120 days')->format('Y-m-d');
        $to = min($to, $limit);
        $cursor = (new \DateTimeImmutable($from))->modify('+1 day');
        $end = new \DateTimeImmutable($to);
        for (; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            if (!$isHoliday && (int) $cursor->format('N') >= 6) {
                continue;
            }
            $date = $cursor->format('Y-m-d');
            $dayId = $this->findDayForDate($date);
            if (!$dayId) {
                continue;
            }
            $day = $this->fetchDay($dayId);
            if (!$day) {
                continue;
            }
            $taken = (int) $day['is_manual'] === 1
                && !in_array((string) $day['day_type'], self::PLAIN_DAY_TYPES, true)
                && trim((string) $day['title']) !== ''
                && trim((string) $day['title']) !== trim((string) $title);
            if ($taken) {
                continue;
            }
            $this->updateLinkedDay($dayId, $date, $title, $description, $dayTypeCode);
        }
    }

    /**
     * When a calendar-linked event is deleted, revert its calendar day to a
     * normal school day (or weekend) and cancel calendar-generated exams.
     */
    public function handleEventDelete(int $eventId): array
    {
        $stmt = $this->db->prepare("SELECT id, calendar_day_id FROM school_events WHERE id = ?");
        $stmt->execute([$eventId]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ev || !$ev['calendar_day_id']) {
            return ['event' => $eventId, 'calendar_day' => null];
        }

        $dayId = (int) $ev['calendar_day_id'];
        $dow = (int) $this->db->query("SELECT DAYOFWEEK(date) FROM academic_year_calendar_days WHERE id = $dayId")->fetchColumn();
        $weekend = (int) $this->db->query("SELECT id FROM calendar_day_types WHERE code = 'weekend'")->fetchColumn();
        $schoolDay = (int) $this->db->query("SELECT id FROM calendar_day_types WHERE code = 'school_day'")->fetchColumn();
        $code = ($dow === 1 || $dow === 7) ? $weekend : $schoolDay;

        $this->db->prepare(
            "UPDATE academic_year_calendar_days
             SET calendar_day_type_id = ?, title = NULL, description = NULL, is_manual = 0 WHERE id = ?"
        )->execute([$code, $dayId]);
        $this->db->prepare("DELETE FROM school_events WHERE id = ?")->execute([$eventId]);

        $day = $this->fetchDay($dayId);
        if ($day && $day['academic_year_term_id']) {
            $this->cancelCalendarExams($day['academic_year_term_id'], $day['date']);
        }
        return ['event' => $eventId, 'calendar_day' => $dayId];
    }

    /**
     * Unified event list for the events pages: calendar-derived events plus
     * free-form school_events, all with status computed from their dates.
     *
     * $sync controls whether a full year reconciliation runs first. Writable
     * pages (events CRUD) pass true so linked days/mirrors stay in sync; the
     * read-only calendar view passes false because it never mutates anything
     * and the per-day reconciliation is expensive (hundreds of queries).
     */
    public function getUnifiedEvents(bool $sync = true, ?int $yearId = null): array
    {
        $this->normalizeStatuses();
        if ($sync) {
            $this->syncAcademicYear($yearId);
        }

        // No year: the current year's terms plus every unlinked day and free
        // event (the long-standing behaviour other callers rely on). With a
        // year: that year's terms, and unlinked days / free events dated in
        // its calendar years (school years run January to December).
        $yearWhere = '(aterm.is_current_year = 1 OR d.academic_year_calendar_id = 0)';
        $freeWhere = '';
        $yearBind = [];
        $freeBind = [];
        if ($yearId !== null) {
            $span = $this->db->prepare("SELECT start_date, end_date FROM academic_years WHERE id = ?");
            $span->execute([$yearId]);
            $years = $span->fetch(PDO::FETCH_ASSOC);
            if (!$years) {
                return [];
            }
            // The year's own start and end dates bound everything that is not
            // tied to one of its terms. Matching on the calendar year number
            // listed the neighbouring year's events as well whenever a year
            // did not run exactly January to December.
            $yearWhere = '(aterm.academic_year_id = ? OR (d.academic_year_calendar_id = 0 AND d.date BETWEEN ? AND ?))';
            $yearBind = [$yearId, $years['start_date'], $years['end_date']];
            $freeWhere = ' AND DATE(start_at) <= ? AND DATE(COALESCE(end_at, start_at)) >= ?';
            $freeBind = [$years['end_date'], $years['start_date']];
        }

        $rows = [];
        $stmt = $this->db->prepare(
            "SELECT
                d.id AS calendar_day_id,
                d.date,
                COALESCE(cdt.code, 'school_day') AS day_type,
                d.title AS day_title,
                d.description AS day_desc,
                d.is_manual,
                ac.week_number,
                aterm.term_id,
                aterm.academic_year_term_id,
                aterm.academic_year_id,
                aterm.term_name AS term_name,
                ev.id AS event_id,
                ev.title AS event_title,
                ev.type AS event_type,
                ev.location,
                ev.description AS event_desc,
                ev.start_at,
                ev.end_at,
                ev.status AS event_status,
                ev.source AS event_source
             FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar_days") . " d
             LEFT JOIN calendar_day_types cdt ON cdt.id = d.calendar_day_type_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " ac ON ac.id = d.academic_year_calendar_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_term") . " aterm ON aterm.academic_year_term_id = ac.academic_year_term_id
             LEFT JOIN school_events ev ON ev.calendar_day_id = d.id AND ev.status <> 'cancelled'
             WHERE {$yearWhere}
               AND (COALESCE(cdt.code, '') NOT IN ('school_day', 'weekend')
                    OR (COALESCE(cdt.code, '') = 'school_day'
                        AND d.title IS NOT NULL AND d.title <> '')
                    OR ev.id IS NOT NULL)
             ORDER BY d.date, d.id"
        );
        $stmt->execute($yearBind);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = $this->buildEventRow($r);
        }

        $free = $this->db->prepare(
            "SELECT id, title, description, type, location, start_at, end_at, status, source
             FROM school_events
             WHERE calendar_day_id IS NULL AND status <> 'cancelled'{$freeWhere}
             ORDER BY start_at"
        );
        $free->execute($freeBind);
        foreach ($free->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $rows[] = $this->buildFreeFormRow($f);
        }

        usort($rows, function ($a, $b) {
            return strcmp($a['start_date'], $b['start_date']);
        });

        // Every event sits in a period of the academic year: a term, a half
        // term or a holiday (April, August, December). Holiday days have no
        // term week, so their year comes from the period too.
        $periods = new AcademicPeriodService($this->db);
        foreach ($rows as &$row) {
            $period = $periods->periodForDate((string) ($row['date'] ?? $row['start_date'] ?? ''));
            $row['period_key'] = $period['key'] ?? null;
            $row['period_kind'] = $period['kind'] ?? null;
            $row['period_name'] = $period['name'] ?? null;
            $row['period_label'] = $period['label'] ?? null;
            if (empty($row['academic_year_id']) && $period) {
                $row['academic_year_id'] = (int) $period['academic_year_id'];
            }
        }
        unset($row);

        // Collapse contiguous same-title/type days (e.g. a multi-week holiday)
        // into ONE event row carrying the full start -> end range, so the
        // events pages stay readable instead of listing every single day.
        $grouped = [];
        $count = 0;
        foreach ($rows as $row) {
            $key = ($row['title'] ?? '') . '|' . ($row['type'] ?? '') . '|' . ($row['day_type'] ?? '');
            $idx = $count - 1;
            if ($idx >= 0
                && $grouped[$idx]['_key'] === $key
                && $this->daysAreConsecutive($grouped[$idx]['_date'], $row['date'])) {
                $grouped[$idx]['end_date'] = $row['date'];
                if (!empty($row['end_time'])) {
                    $grouped[$idx]['end_time'] = $row['end_time'];
                }
                if ($row['id'] !== null) {
                    $grouped[$idx]['ids'][] = (int) $row['id'];
                }
                $grouped[$idx]['_date'] = $row['date'];
            } else {
                $row['_key'] = $key;
                $row['_date'] = $row['date'];
                $row['ids'] = $row['id'] !== null ? [(int) $row['id']] : [];
                $grouped[] = $row;
                $count++;
            }
        }
        foreach ($grouped as &$g) {
            unset($g['_key'], $g['_date']);
        }
        unset($g);

        // Merge non-contiguous runs of the SAME logical event (e.g. the
        // December holiday split around national exams / gazetted public
        // holidays) into a single row spanning the full start -> end range.
        // Same-named holidays falling in DIFFERENT terms (e.g. the two
        // half-term breaks) stay separate because their term differs.
        $merged = [];
        foreach ($grouped as $row) {
            // The period keeps apart same-named runs of different periods:
            // two half terms, or the December Holiday's first days of the
            // year and the December Holiday at its end.
            $key = ($row['title'] ?? '') . '|' . ($row['type'] ?? '') . '|' . ($row['day_type'] ?? '') . '|' . ($row['academic_year_term_id'] ?? '') . '|' . ($row['period_key'] ?? '');
            if (isset($merged[$key])) {
                $m = &$merged[$key];
                if ($row['start_date'] < $m['start_date']) {
                    $m['start_date'] = $row['start_date'];
                    $m['date'] = $row['start_date'];
                    $m['event_date'] = $row['start_date'];
                    $m['start_time'] = $row['start_time'];
                }
                if ($row['end_date'] > $m['end_date']) {
                    $m['end_date'] = $row['end_date'];
                    $m['end_time'] = $row['end_time'];
                }
                foreach ($row['ids'] as $rid) {
                    if (!in_array($rid, $m['ids'], true)) {
                        $m['ids'][] = $rid;
                    }
                }
                unset($m);
            } else {
                $merged[$key] = $row;
            }
        }
        $grouped = array_values($merged);

        return $grouped;
    }

    // ==================== INTERNALS ====================

    /**
     * One calendar day, read from the primary store with keyed lookups. The
     * read projection lags a write by a tick, and after a calendar rebuild it
     * does not hold the new day ids yet, which left freshly generated years
     * (and days edited a moment ago) without their events.
     */
    private function fetchDay(int $dayId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, date, title, description, is_manual, calendar_day_type_id, academic_year_calendar_id
             FROM academic_year_calendar_days WHERE id = ?"
        );
        $stmt->execute([$dayId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $type = $this->db->prepare("SELECT code FROM calendar_day_types WHERE id = ?");
        $type->execute([(int) $row['calendar_day_type_id']]);
        $row['day_type'] = $type->fetchColumn() ?: null;
        $row['academic_year_term_id'] = null;
        if ((int) $row['academic_year_calendar_id'] > 0) {
            $week = $this->db->prepare("SELECT academic_year_term_id FROM academic_year_calendar WHERE id = ?");
            $week->execute([(int) $row['academic_year_calendar_id']]);
            $termId = $week->fetchColumn();
            $row['academic_year_term_id'] = $termId ? (int) $termId : null;
        }
        return $row;
    }

    private function currentYear(?int $yearId): ?array
    {
        $sql = "SELECT id, start_date, end_date FROM academic_years";
        if ($yearId) {
            $stmt = $this->db->prepare($sql . " WHERE id = ?");
            $stmt->execute([$yearId]);
        } else {
            $stmt = $this->db->query($sql . " WHERE is_current = 1 ORDER BY id DESC LIMIT 1");
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function defaultTitle(string $dayType): string
    {
        $labels = [
            'half_day' => 'Half Day',
            'exam_day' => 'Examination Day',
            'special_event' => 'Special Event',
            'holiday' => 'Holiday',
            'public_holiday' => 'Public Holiday',
            'school_holiday' => 'School Holiday',
            'weekend' => 'Weekend',
        ];
        return $labels[$dayType] ?? 'School Event';
    }

    private function statusFor(string $date): string
    {
        $today = date('Y-m-d');
        if ($date < $today) return 'past';
        if ($date === $today) return 'ongoing';
        return 'upcoming';
    }

    private function upsertMirrorEvent(array $day, string $eventType): void
    {
        $title = trim((string) ($day['title'] ?? ''));
        if ($title === '') {
            $title = $this->defaultTitle($day['day_type']);
        }

        $exists = $this->db->prepare("SELECT id, title FROM school_events WHERE calendar_day_id = ?");
        $exists->execute([$day['id']]);
        $existing = $exists->fetch(PDO::FETCH_ASSOC);

        $status = $this->statusFor($day['date']);
        if ($existing) {
            $this->db->prepare(
                "UPDATE school_events
                 SET title = ?, type = ?, start_at = ?, end_at = ?, status = ?, source = 'calendar', updated_at = NOW()
                 WHERE id = ?"
            )->execute([$title, $eventType, $day['date'] . ' 00:00:00', $day['date'] . ' 23:59:59', $status, $existing['id']]);
            return;
        }

        $this->db->prepare(
            "INSERT INTO school_events (title, description, start_at, end_at, type, location, status, calendar_day_id, source)
             VALUES (?, ?, ?, ?, ?, NULL, ?, ?, 'calendar')"
        )->execute([
            $title,
            $day['description'] ?? null,
            $day['date'] . ' 00:00:00',
            $day['date'] . ' 23:59:59',
            $eventType,
            $status,
            $day['id'],
        ]);
    }

    private function deleteMirrorEvent(int $dayId): void
    {
        $this->db->prepare(
            "DELETE FROM school_events
             WHERE calendar_day_id = ? AND source = 'calendar'
               AND type IN ('exam','holiday','school_holiday','public_holiday','special_event','event','half_day')"
        )->execute([$dayId]);
    }

    private function syncExamSchedules(string $date, int $termId, ?string $examName): int
    {
        $name = trim((string) ($examName ?? ''));
        if ($name === '' || $name === 'Examinations') {
            $termStmt = $this->db->prepare("SELECT term_id FROM academic_year_terms WHERE id = ?");
            $termStmt->execute([$termId]);
            $name = 'Term ' . $this->termOrdinal((int) $termStmt->fetchColumn()) . ' Examinations';
        }

        $sql = "INSERT INTO exam_schedules
                    (academic_year_term_id, academic_year_class_stream_id, learning_area_id,
                     exam_name, exam_type, exam_date, start_time, end_time, status, source)
                SELECT :term, aycs.id, NULL, :name, 'calendar', :date, '08:00:00', '10:00:00', 'scheduled', 'calendar'
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id
                WHERE ay.is_current = 1 AND aycs.status = 'active'
                  AND NOT EXISTS (
                      SELECT 1 FROM exam_schedules es
                      WHERE es.academic_year_class_stream_id = aycs.id
                        AND es.exam_date = :date2
                        AND es.source = 'calendar'
                  )";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':term', $termId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':date2', $date);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function cancelCalendarExams(int $termId, string $date): void
    {
        $this->db->prepare(
            "UPDATE exam_schedules
             SET status = 'cancelled'
             WHERE academic_year_term_id = ? AND exam_date = ? AND source = 'calendar'
               AND status IN ('scheduled','upcoming')"
        )->execute([$termId, $date]);
    }

    private function termOrdinal(int $n): string
    {
        return ['1' => 'One', '2' => 'Two', '3' => 'Three'][$n] ?? (string) $n;
    }

    private function daysAreConsecutive(string $a, string $b): bool
    {
        $ts = strtotime($a . ' +1 day');
        return $ts !== false && date('Y-m-d', $ts) === $b;
    }

    /**
     * The calendar day for a date, in whichever academic year holds it (dates
     * never repeat across years). A term day is preferred over the year's
     * base row for the same date. Looking only at the current year meant an
     * event planned for the incoming year was never placed on its calendar.
     */
    private function findDayForDate(string $date): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM academic_year_calendar_days
             WHERE date = ? ORDER BY academic_year_calendar_id DESC LIMIT 1"
        );
        $stmt->execute([$date]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function updateLinkedDay(int $dayId, string $date, ?string $title, ?string $description, ?string $dayTypeCode): void
    {
        $day = $this->fetchDay($dayId);
        if (!$day) {
            return;
        }

        $set = [];
        $params = [];

        if ($title !== null && $title !== '') {
            $set[] = "title = ?";
            $params[] = mb_substr($title, 0, 100);
        }
        if ($description !== null) {
            $set[] = "description = ?";
            $params[] = mb_substr($description, 0, 500);
        }
        if ($dayTypeCode !== null) {
            $typeStmt = $this->db->prepare("SELECT id FROM calendar_day_types WHERE code = ?");
            $typeStmt->execute([$dayTypeCode]);
            $typeId = (int) $typeStmt->fetchColumn();
            if (!$typeId) {
                return;
            }
            $set[] = "calendar_day_type_id = ?";
            $params[] = $typeId;
            $set[] = "is_manual = 1";
        }

        if ($date !== $day['date']) {
            $week = $this->db->prepare(
                "SELECT ac.id FROM " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " ac
                 JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.id = ac.academic_year_term_id
                 WHERE ayt.academic_year_id = (
                     SELECT ay.id FROM " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt2
                     JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON ay.id = ayt2.academic_year_id
                     JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " ac2 ON ac2.academic_year_term_id = ayt2.id
                     WHERE ac2.id = ? LIMIT 1
                 ) AND ? BETWEEN ac.week_start AND ac.week_end LIMIT 1"
            );
            $week->execute([$day['academic_year_calendar_id'] ?? 0, $date]);
            $weekId = $week->fetchColumn();
            if ($weekId) {
                $set[] = "academic_year_calendar_id = ?";
                $params[] = $weekId;
            }
            $set[] = "date = ?";
            $params[] = $date;
            $set[] = "is_manual = 1";
        }

        if (empty($set)) {
            return;
        }

        $params[] = $dayId;
        $this->db->prepare("UPDATE academic_year_calendar_days SET " . implode(', ', $set) . " WHERE id = ?")
            ->execute($params);

        $this->syncDay($dayId);
    }

    private function buildEventRow(array $r): array
    {
        $dayType = $r['day_type'];
        $eventType = $r['event_type'] ?: (self::DAY_TYPE_TO_EVENT[$dayType] ?? 'special_event');
        $title = $r['event_title'] ?: ($r['day_title'] ?: $this->defaultTitle($dayType));
        // Opening/closing days are titled school-day rows now - surface them
        // with the 'opening'/'closing' types the events pages already style.
        if ($dayType === 'school_day' && $title !== '') {
            if (stripos($title, 'Opening Day') !== false) {
                $eventType = 'opening';
            } elseif (stripos($title, 'Closing Day') !== false) {
                $eventType = 'closing';
            }
        }
        $date = substr((string) ($r['start_at'] ?? ''), 0, 10) ?: $r['date'];
        $status = $r['event_status'] ?: $this->statusFor($r['date']);

        return [
            'id' => $r['event_id'] !== null ? (int) $r['event_id'] : null,
            'calendar_day_id' => (int) $r['calendar_day_id'],
            'title' => $title,
            'name' => $title,
            'event_name' => $title,
            'description' => $r['event_desc'] ?: $r['day_desc'],
            'date' => $date,
            'event_date' => $date,
            'start_date' => $date,
            'end_date' => substr((string) ($r['end_at'] ?? ''), 0, 10) ?: $date,
            'start_time' => substr((string) ($r['start_at'] ?? ''), 11, 5) ?: '',
            'end_time' => substr((string) ($r['end_at'] ?? ''), 11, 5) ?: '',
            'type' => $eventType,
            'event_type' => $eventType,
            'category' => $eventType,
            'day_type' => $dayType,
            'location' => $r['location'],
            'venue' => $r['location'],
            'status' => $status,
            'source' => $r['event_source'] ?: 'calendar',
            'is_manual' => (bool) $r['is_manual'],
            'term_id' => $r['term_id'] !== null ? (int) $r['term_id'] : null,
            // term_id is the term number (1-3); filters use this, the
            // academic_year_terms id that is unique across years.
            'academic_year_term_id' => $r['academic_year_term_id'] !== null ? (int) $r['academic_year_term_id'] : null,
            'academic_year_id' => $r['academic_year_id'] !== null ? (int) $r['academic_year_id'] : null,
            'term_name' => $r['term_name'],
            'week_number' => $r['week_number'] !== null ? (int) $r['week_number'] : null,
        ];
    }

    private function buildFreeFormRow(array $f): array
    {
        $date = substr((string) ($f['start_at'] ?? ''), 0, 10);
        $endDate = substr((string) ($f['end_at'] ?? ''), 0, 10) ?: $date;
        return [
            'id' => (int) $f['id'],
            'calendar_day_id' => null,
            'title' => $f['title'],
            'name' => $f['title'],
            'event_name' => $f['title'],
            'description' => $f['description'],
            'date' => $date,
            'event_date' => $date,
            'start_date' => $date,
            'end_date' => $endDate,
            'start_time' => substr((string) ($f['start_at'] ?? ''), 11, 5) ?: '',
            'end_time' => substr((string) ($f['end_at'] ?? ''), 11, 5) ?: '',
            'type' => $f['type'],
            'event_type' => $f['type'],
            'category' => $f['type'],
            'day_type' => null,
            'location' => $f['location'],
            'venue' => $f['location'],
            'status' => $f['status'],
            'source' => $f['source'],
            'is_manual' => true,
            'term_id' => null,
            'academic_year_term_id' => null,
            'academic_year_id' => null,
            'term_name' => null,
            'week_number' => null,
        ];
    }
}
