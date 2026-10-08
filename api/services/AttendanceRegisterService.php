<?php

namespace App\API\Services;

use App\API\Services\ReadReplicaService;
use App\Database\Database;
use PDO;
use Throwable;

/**
 * Reconciles the registers that should exist for a date.
 *
 * This service deliberately does not manufacture absent attendance rows.
 * An uncompleted register remains visible as overdue until an authorised
 * teacher or administrator completes/closes it.
 */
class AttendanceRegisterService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        // Attendance session times and MySQL NOW() are school-local times.
        date_default_timezone_set('Africa/Nairobi');
    }

    public function process(?string $date = null, ?string $now = null): array
    {
        $date = $date ?: date('Y-m-d');
        $now = $now ?: date('Y-m-d H:i:s');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Invalid attendance date');
        }

        $context = $this->context($date);
        if (!$context) {
            return ['date' => $date, 'registers' => 0, 'reminders' => 0, 'escalations' => 0, 'message' => 'No active academic term for date'];
        }
        if (!$context['is_school_day']) {
            return ['date' => $date, 'registers' => 0, 'reminders' => 0, 'escalations' => 0, 'message' => $context['reason']];
        }

        $streams = $this->streams((int) $context['year_id']);
        $sessions = $this->sessions($context['day_name'], (bool) $context['saturday_classes'], (int) $context['term_id']);
        // A session-class rule can be changed by administration. Existing
        // scheduled/open rows that no longer apply must not remain actionable.
        $this->retireNotApplicable($date, (int) $context['term_id']);
        $registers = 0;
        foreach ($streams as $stream) {
            foreach ($sessions as $session) {
                if ($session['type'] === 'academic' && !$this->sessionAppliesToClass((int) $session['id'], (int) $stream['class_id'], (int) $context['term_id'])) continue;
                $expected = $this->expectedCount((int) $stream['id'], $session['applies_to'], $date);
                if ($expected < 1) continue;
                $registerId = $this->upsertRegister($context, $stream, $session, $date, $session['type'] === 'boarding' ? 'boarding' : 'class');
                $this->reconcile($registerId, (int) $stream['id'], (int) $session['id'], $date, $expected, $now);
                $registers++;
            }
        }

        return $this->notify($date, $now, $registers);
    }

    public function list(array $filters = []): array
    {
        $date = $filters['date'] ?? date('Y-m-d');
        $sql = "SELECT ar.*, ass.code AS session_code, COALESCE(cfg.name, ass.name) AS session_name,
                       CONCAT(COALESCE(csd.class_name,''),' - ',COALESCE(csd.stream_name,'')) AS stream_name,
                       CONCAT(COALESCE(sctx.first_name,''),' ',COALESCE(sctx.last_name,'')) AS teacher_name
                  FROM attendance_registers ar
                  JOIN " . ReadReplicaService::qualifiedRef("attendance_sessions") . " ass ON ass.id=ar.session_id
                  LEFT JOIN attendance_session_term_configs cfg
                    ON cfg.session_id=ar.session_id AND cfg.academic_year_term_id=ar.academic_year_term_id
                  JOIN " . ReadReplicaService::qualifiedRef("class_stream_directory") . " csd ON csd.id=ar.stream_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("staff_context") . " sctx ON sctx.staff_id=ar.assigned_staff_id
                 WHERE ar.register_date=? AND ar.status <> 'not_required'";
        $params = [$date];
        if (!empty($filters['status'])) { $sql .= " AND ar.status=?"; $params[] = $filters['status']; }
        if (!empty($filters['staff_id'])) { $sql .= " AND ar.assigned_staff_id=?"; $params[] = (int)$filters['staff_id']; }
        if (array_key_exists('stream_ids', $filters)) {
            $ids = array_values(array_filter(array_map('intval', (array)$filters['stream_ids'])));
            if (!$ids) return ['date'=>$date, 'registers'=>[]];
            $sql .= ' AND ar.stream_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
        $sql .= " ORDER BY ar.opens_at, stream_name";
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        return ['date'=>$date, 'registers'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * List the registers that should exist across a date range without
     * creating or changing any register/attendance rows.  Reports must be
     * able to distinguish "no attendance was marked" from "no register was
     * generated"; both are operational exceptions, not zero attendance.
     */
    public function listRange(array $filters = []): array
    {
        $from = (string) ($filters['date_from'] ?? date('Y-m-01'));
        $to = (string) ($filters['date_to'] ?? date('Y-m-d'));
        if ($from > $to) [$from, $to] = [$to, $from];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            throw new \InvalidArgumentException('Invalid attendance date range');
        }
        if ((strtotime($to) - strtotime($from)) > 366 * 86400) {
            throw new \InvalidArgumentException('Attendance register range cannot exceed one year');
        }

        $streamIds = array_values(array_filter(array_map('intval', (array) ($filters['stream_ids'] ?? []))));
        $sessionId = !empty($filters['session_id']) ? (int) $filters['session_id'] : null;
$existingSql = "SELECT ar.*, ass.code AS session_code,
                               COALESCE(cfg.name, ass.name) AS session_name,
                               CONCAT(COALESCE(csd.class_name,''),' - ',COALESCE(csd.stream_name,'')) AS stream_name
                        FROM attendance_registers ar
                        JOIN " . ReadReplicaService::qualifiedRef("attendance_sessions") . " ass ON ass.id=ar.session_id
                        LEFT JOIN attendance_session_term_configs cfg
                          ON cfg.session_id=ar.session_id AND cfg.academic_year_term_id=ar.academic_year_term_id
                        JOIN " . ReadReplicaService::qualifiedRef("class_stream_directory") . " csd ON csd.id=ar.stream_id
                        WHERE ar.register_date BETWEEN ? AND ?
                           AND ar.status <> 'not_required'
                           AND ar.register_type='class'";
        $params = [$from, $to];
        if ($sessionId) {
            $existingSql .= ' AND ar.session_id = ?';
            $params[] = $sessionId;
        }
        if ($streamIds) {
            $existingSql .= ' AND ar.stream_id IN (' . implode(',', array_fill(0, count($streamIds), '?')) . ')';
            $params = array_merge($params, $streamIds);
        }
        $stmt = $this->db->prepare($existingSql);
        $stmt->execute($params);
        $existing = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['register_date'] . ':' . (int) $row['stream_id'] . ':' . (int) $row['session_id']] = $row;
        }

        $registers = [];
        $date = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        for (; $date <= $end; $date = $date->modify('+1 day')) {
            $dateString = $date->format('Y-m-d');
            $context = $this->context($dateString);
            if (!$context || !$context['class_day']) continue;
            $streams = $this->streams((int) $context['year_id']);
            if ($streamIds) $streams = array_values(array_filter($streams, static fn(array $s): bool => in_array((int) $s['id'], $streamIds, true)));
            $sessions = $this->sessions($context['day_name'], (bool) $context['saturday_classes'], (int) $context['term_id']);
            foreach ($streams as $stream) {
                foreach ($sessions as $session) {
                    if ($session['type'] !== 'academic') continue;
                    if ($sessionId && (int) $session['id'] !== $sessionId) continue;
                    if (!$this->sessionAppliesToClass((int) $session['id'], (int) $stream['class_id'], (int) $context['term_id'])) continue;
                    $expected = $this->expectedCount((int) $stream['id'], $session['applies_to'], $dateString);
                    if ($expected < 1) continue;
                    $key = $dateString . ':' . (int) $stream['id'] . ':' . (int) $session['id'];
                    $row = $existing[$key] ?? null;
                    $storedStatus = $row['status'] ?? ($dateString > date('Y-m-d') ? 'scheduled' : 'missing');
                    $markedCount = $row ? (int) $row['marked_count'] : 0;
                    // Register status may predate a corrected effective
                    // roster. Re-evaluate completion using learners who were
                    // fully enrolled on this date.
                    $effectiveStatus = ($row && $markedCount >= $expected && $expected > 0)
                        ? 'completed'
                        : (($row && $markedCount < $expected && $dateString <= date('Y-m-d')
                            && !in_array($storedStatus, ['overdue', 'open'], true))
                            ? ($dateString < date('Y-m-d') ? 'overdue' : 'open')
                            : $storedStatus);
                    $registers[] = [
                        'id' => $row ? (int) $row['id'] : null,
                        'register_date' => $dateString,
                        'stream_id' => (int) $stream['id'],
                        'session_id' => (int) $session['id'],
                        'stream_name' => $row['stream_name'] ?? $stream['stream_name'],
                        'session_name' => $row['session_name'] ?? $session['name'],
                        'applies_to' => (string) ($session['applies_to'] ?? 'all'),
                        'teacher_name' => $row['teacher_name'] ?? null,
                        // Recalculate this for the report date. A learner who
                        // joined after this date must not be counted here,
                        // even if the register row was created later.
                        'expected_count' => $expected,
                        'marked_count' => $markedCount,
                        'status' => $effectiveStatus,
                    ];
                    $lastIndex = count($registers) - 1;
                    if (in_array($registers[$lastIndex]['status'], ['missing', 'not_marked', 'overdue', 'open'], true)) {
                        $registers[$lastIndex]['unmarked_learners'] = $this->unmarkedLearners(
                            (int) $stream['id'], (int) $session['id'], $dateString,
                            (string) ($session['applies_to'] ?? 'all')
                        );
                    }
                }
            }
        }

        $counts = ['completed' => 0, 'open' => 0, 'overdue' => 0, 'not_marked' => 0, 'missing' => 0, 'scheduled' => 0];
        $missingDates = [];
        $exceptions = [];
        foreach ($registers as $register) {
            $status = $register['status'];
            if (isset($counts[$status])) $counts[$status]++;
            if (in_array($status, ['missing', 'not_marked', 'overdue', 'open'], true)) {
                $missingDates[$register['register_date']] = true;
                $exceptions[] = [
                    'date' => $register['register_date'],
                    'stream_name' => $register['stream_name'],
                    'session_name' => $register['session_name'],
                    'applies_to' => $register['applies_to'],
                    'status' => $status,
                    'expected_count' => $register['expected_count'],
                    'marked_count' => $register['marked_count'],
                    'unmarked_learners' => $register['unmarked_learners'] ?? [],
                ];
            }
        }
        return [
            'date_from' => $from,
            'date_to' => $to,
            'registers' => $registers,
            'exception_registers' => $exceptions,
            'counts' => $counts,
            'exception_dates' => array_keys($missingDates),
        ];
    }

    /** Return named learners with no attendance row for this session/date. */
    private function unmarkedLearners(int $streamId, int $sessionId, string $date, string $appliesTo): array
    {
        $sql = "SELECT s.id, s.admission_no,
                       CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,'')) AS learner_name,
                       st.name AS student_type
                  FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " en
                  JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id=en.student_id
                  LEFT JOIN " . ReadReplicaService::qualifiedRef("admission_applications") . " aa ON aa.id=s.application_id
                  JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " p ON p.person_id = s.person_id
                  LEFT JOIN student_types st ON st.id=s.student_type_id
                 WHERE en.academic_year_class_stream_id=?
                   AND s.status='active' AND en.enrollment_status='active'
                   AND COALESCE(
                         CASE WHEN s.entry_source='admission' THEN aa.enrolled_at END,
                         CASE WHEN s.entry_source IS NULL OR s.entry_source <> 'admission' THEN en.enrolled_on END
                       ) <= ?
                   AND NOT EXISTS (
                       SELECT 1 FROM " . ReadReplicaService::qualifiedRef("student_attendance") . " sa
                        WHERE sa.student_academic_enrollment_id=en.id
                          AND sa.date=? AND sa.session_id=? AND sa.register_type='class'
                   )";
        if ($appliesTo === 'day_only') $sql .= " AND COALESCE(st.code, 'DAY')='DAY'";
        if ($appliesTo === 'boarders_only') $sql .= " AND COALESCE(st.code, '')='BOARD'";
        $sql .= ' ORDER BY p.first_name, p.last_name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$streamId, $date, $date, $sessionId]);
        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'admission_no' => $row['admission_no'],
            'learner_name' => trim((string) $row['learner_name']),
            'student_type' => $row['student_type'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Reconcile one register immediately after a teacher submits its marks.
     * The scheduled worker remains responsible for reminders and escalation,
     * but a successful submission must update the register without waiting
     * for the next five-minute worker run.
     */
    public function reconcileForMarking(int $streamId, int $sessionId, string $date): void
    {
        $stmt = $this->db->prepare(
            "SELECT ar.id, COALESCE(cfg.applies_to, ass.applies_to) AS applies_to
             FROM attendance_registers ar
             JOIN " . ReadReplicaService::qualifiedRef("attendance_sessions") . " ass ON ass.id = ar.session_id
             LEFT JOIN attendance_session_term_configs cfg
               ON cfg.session_id=ar.session_id AND cfg.academic_year_term_id=ar.academic_year_term_id
             WHERE ar.stream_id = ? AND ar.session_id = ? AND ar.register_date = ?
             LIMIT 1"
        );
        $stmt->execute([$streamId, $sessionId, $date]);
        $register = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$register) {
            return;
        }

        $expected = $this->expectedCount($streamId, (string) ($register['applies_to'] ?? 'all'), $date);
        $this->reconcile(
            (int) $register['id'],
            $streamId,
            $sessionId,
            $date,
            $expected,
            date('Y-m-d H:i:s')
        );
    }

    private function context(string $date): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ay.id AS year_id, ayt.id AS term_id, acd.id AS calendar_day_id,
                    DAYNAME(?) AS day_name,                     COALESCE(acd.type, 'school_day') AS day_type,
                    COALESCE(acd.requires_attendance, 1) AS requires_attendance,
                    COALESCE(acd.affects_day_students, 1) AS affects_day_students,
                    COALESCE(acd.affects_boarders, 1) AS affects_boarders,
                    COALESCE(acd.title, '') AS day_title
               FROM " . ReadReplicaService::qualifiedRef("academic_years") . " ay
               JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.academic_year_id = ay.id
                AND ? BETWEEN ayt.opening_date AND ayt.closing_date
               LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar") . " ac ON ac.academic_year_term_id = ayt.id
                AND ? BETWEEN ac.week_start AND ac.week_end
               LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_calendar_days_typed") . " acd ON acd.academic_year_calendar_id = ac.id
                AND acd.date = ?
              WHERE (ay.is_current = 1 OR ay.status = 'active')
              ORDER BY ay.is_current DESC, ay.id DESC LIMIT 1"
        );
        $stmt->execute([$date, $date, $date, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $dayNumber = (int) date('N', strtotime($date));
        $isWeekend = $dayNumber >= 6;
        $isSaturdayClass = $dayNumber === 6 && $this->saturdayClasses((int) $row['year_id']);
        $classDay = !in_array($row['day_type'], ['public_holiday', 'school_holiday', 'holiday'], true)
            && (($dayNumber < 6) || $isSaturdayClass);
        $boardingDay = !in_array($row['day_type'], ['public_holiday', 'school_holiday', 'holiday'], true)
            && (bool) $row['affects_boarders'] && (bool) $row['requires_attendance'];
        $blocked = !$classDay && !$boardingDay;
        return [
            'year_id' => (int) $row['year_id'], 'term_id' => (int) $row['term_id'],
            'calendar_day_id' => $row['calendar_day_id'] ? (int) $row['calendar_day_id'] : null,
            'day_name' => $row['day_name'], 'day_type' => $row['day_type'],
            'is_school_day' => !$blocked,
            'class_day' => $classDay,
            'boarding_day' => $boardingDay,
            'saturday_classes' => $isSaturdayClass,
            'reason' => $blocked ? ($row['day_title'] ?: ($isWeekend ? 'Weekend' : 'School closure')) : null,
        ];
    }

    private function saturdayClasses(int $yearId): bool
    {
        $stmt = $this->db->prepare("SELECT saturday_classes FROM school_week_config WHERE academic_year_id = ? LIMIT 1");
        try { $stmt->execute([$yearId]); return (bool) $stmt->fetchColumn(); } catch (Throwable $e) { return false; }
    }

    private function streams(int $yearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT class_stream_id AS id, class_teacher_id, stream_id, class_id,
                    CONCAT(COALESCE(class_name, ''), ' - ', COALESCE(stream_name, '')) AS stream_name
               FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . "
              WHERE academic_year_id = ? AND class_stream_status = 'active'"
        );
        $stmt->execute([$yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function sessions(string $dayName, bool $saturdayClasses, int $termId): array
    {
        $stmt = $this->db->prepare(
            "SELECT *,
                    configured_name AS term_name, configured_description AS term_description,
                    configured_start_time AS term_start_time, configured_end_time AS term_end_time,
                    configured_applicable_days AS term_applicable_days,
                    configured_applies_to AS term_applies_to,
                    configured_is_mandatory AS term_is_mandatory,
                    configured_display_order AS term_display_order,
                    configured_status AS term_status
             FROM " . ReadReplicaService::qualifiedRef("attendance_sessions_config") . "
             WHERE academic_year_term_id = ? AND type IN ('academic','boarding')
             ORDER BY COALESCE(configured_display_order, display_order), id"
        );
        $stmt->execute([$termId]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach (['name','description','start_time','end_time','applicable_days','applies_to','is_mandatory','display_order','status'] as $field) {
                $cfgField = 'configured_' . $field;
                if ($row[$cfgField] !== null) $row[$field] = $row[$cfgField];
                unset($row[$cfgField]);
            }
            if (($row['status'] ?? 'inactive') !== 'active') continue;
            $days = json_decode((string) ($row['applicable_days'] ?? '[]'), true) ?: [];
            if (!in_array($dayName, $days, true)) continue;
            if ($row['type'] === 'academic' && $dayName === 'Saturday' && !$saturdayClasses) continue;
            $result[] = $row;
        }
        return $result;
    }

    private function expectedCount(int $streamId, string $appliesTo, string $date): int
    {
        $sql = "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " en
                  JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = en.student_id
                  LEFT JOIN " . ReadReplicaService::qualifiedRef("admission_applications") . " aa ON aa.id=s.application_id
                  LEFT JOIN student_types ty ON ty.id = s.student_type_id
                 WHERE en.academic_year_class_stream_id = ? AND en.enrollment_status = 'active' AND s.status = 'active'
                   AND COALESCE(
                         CASE WHEN s.entry_source='admission' THEN aa.enrolled_at END,
                         CASE WHEN s.entry_source IS NULL OR s.entry_source <> 'admission' THEN en.enrolled_on END
                       ) <= ?";
        if ($appliesTo === 'day_only') $sql .= " AND COALESCE(ty.code, 'DAY') = 'DAY'";
        if ($appliesTo === 'boarders_only') $sql .= " AND COALESCE(ty.code, '') = 'BOARD'";
        $stmt = $this->db->prepare($sql); $stmt->execute([$streamId, $date]);
        return (int) $stmt->fetchColumn();
    }

    private function sessionAppliesToClass(int $sessionId, int $classId, int $termId): bool
    {
        $term = $this->db->prepare("SELECT class_id, enabled FROM attendance_session_term_class_rules WHERE academic_year_term_id=? AND session_id=?");
        $term->execute([$termId, $sessionId]);
        $rules = $term->fetchAll(PDO::FETCH_ASSOC);
        if ($rules !== []) {
            foreach ($rules as $rule) {
                if ((int) $rule['class_id'] === $classId) return (int) $rule['enabled'] === 1;
            }
            return false;
        }
        $stmt = $this->db->prepare("SELECT enabled FROM attendance_session_class_rules WHERE session_id=? AND class_id=? LIMIT 1");
        $stmt->execute([$sessionId, $classId]);
        $enabled = $stmt->fetchColumn();
        return $enabled !== false && (int) $enabled === 1;
    }

    private function retireNotApplicable(string $date, int $termId): void
    {
        $this->db->prepare("UPDATE attendance_registers ar
              JOIN academic_year_class_streams aycs ON aycs.id=ar.stream_id
              JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
             SET ar.status='not_required', ar.updated_at=NOW()
             WHERE ar.register_date=? AND (ar.status IN ('scheduled','open','overdue')
                    OR (ar.status='not_marked' AND ar.register_date=CURDATE()))
               AND ar.register_type='class'
               AND NOT (
                    EXISTS (SELECT 1 FROM attendance_session_term_class_rules tr
                            WHERE tr.academic_year_term_id=? AND tr.session_id=ar.session_id
                              AND tr.class_id=ayc.class_id AND tr.enabled=1)
                    OR (
                        NOT EXISTS (SELECT 1 FROM attendance_session_term_class_rules tx
                                    WHERE tx.academic_year_term_id=? AND tx.session_id=ar.session_id)
                        AND EXISTS (SELECT 1 FROM attendance_session_class_rules r
                                    WHERE r.session_id=ar.session_id AND r.class_id=ayc.class_id AND r.enabled=1)
                    )
               )")
            ->execute([$date, $termId, $termId]);
    }

    private function upsertRegister(array $context, array $stream, array $session, string $date, string $registerType): int
    {
        $start = date('Y-m-d H:i:s', strtotime($date . ' ' . $session['start_time']));
        $due = date('Y-m-d H:i:s', strtotime($date . ' ' . $session['end_time'] . ' +' . (int) $session['grace_minutes_after'] . ' minutes'));
        $overdue = date('Y-m-d H:i:s', strtotime($due . ' +' . (int) $session['escalation_minutes_after'] . ' minutes'));
        $sql = "INSERT INTO attendance_registers
                 (academic_year_id, academic_year_term_id, academic_year_calendar_day_id, stream_id, session_id,
                  register_type, register_date, assigned_staff_id, opens_at, due_at, overdue_at)
                VALUES (?,?,?,?,?,?,?, ?,?,?,?)
                ON DUPLICATE KEY UPDATE assigned_staff_id=VALUES(assigned_staff_id), opens_at=VALUES(opens_at),
                  due_at=VALUES(due_at), overdue_at=VALUES(overdue_at), updated_at=NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$context['year_id'], (int)$context['term_id'], $context['calendar_day_id'], (int)$stream['id'],
            (int)$session['id'], $registerType, $date, $stream['class_teacher_id'] ?: null, $start, $due, $overdue]);
        $find = $this->db->prepare("SELECT id FROM attendance_registers WHERE register_date=? AND stream_id=? AND session_id=? AND register_type=?");
        $find->execute([$date, (int)$stream['id'], (int)$session['id'], $registerType]);
        return (int) $find->fetchColumn();
    }

    private function reconcile(int $id, int $streamId, int $sessionId, string $date, int $expected, string $now): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT sa.student_academic_enrollment_id)
              FROM " . ReadReplicaService::qualifiedRef("student_attendance_enrollment") . " 
             WHERE sa.date=? AND sa.session_id=? AND en.academic_year_class_stream_id=?");
        $stmt->execute([$date, $sessionId, $streamId]); $marked = (int) $stmt->fetchColumn();
        $row = $this->db->prepare("SELECT opens_at, due_at, overdue_at, status FROM attendance_registers WHERE id=?"); $row->execute([$id]); $current = $row->fetch(PDO::FETCH_ASSOC);
        $dayEnded = strtotime($now) >= strtotime($date . ' 23:59:59');
        if ($marked >= $expected) {
            $status = 'completed';
        } elseif ($dayEnded) {
            $status = 'not_marked';
        } elseif (strtotime($now) >= strtotime($current['overdue_at'])) {
            $status = 'overdue';
        } elseif (strtotime($now) >= strtotime($current['opens_at'])) {
            $status = 'open';
        } else {
            $status = 'scheduled';
        }
        $upd = $this->db->prepare("UPDATE attendance_registers SET expected_count=?, marked_count=?, status=?, completed_at=CASE WHEN ?='completed' THEN COALESCE(completed_at,NOW()) ELSE NULL END, last_reconciled_at=NOW() WHERE id=?");
        $upd->execute([$expected, $marked, $status, $status, $id]);
        if ($status === 'overdue' && $current['status'] !== 'overdue') $this->event($id, 'overdue');
        if ($status === 'completed') $this->event($id, 'completed');
        if ($status === 'not_marked' && $current['status'] !== 'not_marked') $this->event($id, 'not_marked');
    }

    private function dbDate(int $id, string $column): string
    {
        $stmt = $this->db->prepare("SELECT {$column} FROM attendance_registers WHERE id=?"); $stmt->execute([$id]); return (string)$stmt->fetchColumn();
    }

    private function notify(string $date, string $now, int $registers): array
    {
        $service = new NotificationService($this->db); $reminders = 0; $escalations = 0;
        $q = $this->db->prepare("SELECT ar.*, ass.name AS session_name, CONCAT(COALESCE(csd.class_name,''),' - ',COALESCE(csd.stream_name,'')) AS stream_name
              FROM attendance_registers ar JOIN " . ReadReplicaService::qualifiedRef("attendance_sessions") . " ass ON ass.id=ar.session_id
              JOIN " . ReadReplicaService::qualifiedRef("class_stream_directory") . " csd ON csd.id=ar.stream_id
             WHERE ar.register_date=? AND ar.status IN ('open','overdue','not_marked')");
        $q->execute([$date]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $nowTs = strtotime($now);
            if ($r['assigned_staff_id'] && $nowTs >= strtotime($r['opens_at']) && empty($r['reminder_sent_at'])) {
                $service->push('staff:' . (int)$r['assigned_staff_id'], 'attendance_register', 'Attendance register due',
                    "Mark {$r['session_name']} attendance for {$r['stream_name']}.", 'high', ['reference_type'=>'attendance_register','reference_id'=>(int)$r['id'],'reminder_window'=>'due','action_url'=>'home.php?route=attendance']);
                $this->db->prepare("UPDATE attendance_registers SET reminder_sent_at=NOW() WHERE id=?")->execute([(int)$r['id']]); $reminders++; $this->event((int)$r['id'], 'reminder');
            }
            if (in_array($r['status'], ['overdue', 'not_marked'], true) && empty($r['escalation_sent_at']) && ($r['status'] === 'not_marked' || $nowTs >= strtotime($r['overdue_at']))) {
                $label = $r['status'] === 'not_marked' ? 'Attendance not marked' : 'Overdue attendance register';
                $service->push(['role:School Administrator', 'role:Headteacher', 'role:Deputy Head - Academic', 'role:Director'], 'attendance_register', $label,
                    "{$r['session_name']} for {$r['stream_name']} was not marked before the attendance day ended.", 'high', ['reference_type'=>'attendance_register','reference_id'=>(int)$r['id'],'reminder_window'=>'escalated','action_url'=>'home.php?route=attendance']);
                $this->db->prepare("UPDATE attendance_registers SET escalation_sent_at=NOW() WHERE id=?")->execute([(int)$r['id']]); $escalations++; $this->event((int)$r['id'], 'escalated');
            }
        }
        return ['date'=>$date, 'registers'=>$registers, 'reminders'=>$reminders, 'escalations'=>$escalations];
    }

    private function event(int $id, string $type): void
    {
        $stmt = $this->db->prepare("INSERT IGNORE INTO attendance_register_events (register_id,event_type,details) VALUES (?,?,?)");
        $stmt->execute([$id, $type, 'Attendance register lifecycle event']);
    }
}
