<?php

namespace App\API\Services;

use App\Database\ConnectionManager;
use App\Database\Database;
use DomainException;
use PDO;

/**
 * Deterministic, aggregate-only school data reads exposed through the MCP
 * bridge. Returns counts, rates, and distributions only - never learner
 * identity, payment details, health/discipline content, or individual grades.
 *
 * Every method builds a bounded, allowlisted filter query over the existing
 * vw_* analytic views (routed through ReadReplicaService where a projection
 * exists, otherwise the identical master view). This is the deterministic
 * layer: no provider call is ever made.
 */
final class McpDataToolsService
{
    private const MAX_LIMIT = 200;
    private const MAX_FILTER_VALUES = 20;

    /** @var PDO|null */
    private $connection;

    /** @var callable(string):string */
    private $refResolver;

    /**
     * @param PDO|null $connection injected for tests; defaults to the app DB
     * @param callable(string):string|null $refResolver resolves projection name
     *        to a schema-qualified view reference; defaults to the governed
     *        ReadReplicaService lookup. Inject a stub in hermetic unit tests.
     */
    public function __construct(?PDO $connection = null, ?callable $refResolver = null)
    {
        $this->connection = $connection;
        $this->refResolver = $refResolver ?? static fn (string $projection): string =>
            ReadReplicaService::qualifiedRef($projection);
    }

    private function pdo(): PDO
    {
        return $this->connection ?: Database::getInstance()->getConnection();
    }

    private function view(string $projection): string
    {
        return ($this->refResolver)($projection);
    }

    /**
     * Aggregate attendance signal: per-day status counts for the selected
     * scope, plus overall rate. Never includes learner columns.
     *
     * @param array $filters allowed keys: date_from, date_to, level, status (array ok)
     */
    public function attendanceSummary(array $filters = []): array
    {
        $dateFrom = $this->dateValue($filters, 'date_from', null);
        $dateTo = $this->dateValue($filters, 'date_to', null);
        $level = $this->singleString($filters, 'level');
        $status = $this->enumList($filters, 'status', ['present', 'absent', 'late']);

        $where = [];
        $params = [];
        if ($dateFrom !== null) {
            $where[] = 'date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $where[] = 'date <= ?';
            $params[] = $dateTo;
        }
        if ($level !== null) {
            $where[] = 'class_name = ?';
            $params[] = $level;
        }
        if ($status !== []) {
            $where[] = 'status IN (' . implode(',', array_fill(0, count($status), '?')) . ')';
            $params = array_merge($params, $status);
        }

        $sql = 'SELECT date, status, COUNT(*) AS count FROM ' . $this->view('student_attendance_summary');
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY date, status ORDER BY date DESC LIMIT ' . self::MAX_LIMIT;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byDate = [];
        $presentTotal = 0;
        $markedTotal = 0;
        foreach ($rows as $row) {
            $date = (string) $row['date'];
            if (!isset($byDate[$date])) {
                $byDate[$date] = ['date' => $date, 'present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
            }
            $key = (string) $row['status'];
            $count = (int) $row['count'];
            $byDate[$date][$key] = $count;
            $byDate[$date]['total'] += $count;
            if ($key === 'present') {
                $presentTotal += $count;
            }
            $markedTotal += $count;
        }

        krsort($byDate);

        return [
            'granularity' => 'day',
            'scope' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'level' => $level,
            ],
            'marked_records' => $markedTotal,
            'present_rate_percent' => $markedTotal > 0 ? round($presentTotal / $markedTotal * 100, 1) : 0,
            'by_date' => array_values($byDate),
        ];
    }

    /**
     * Aggregate fee collection: per-level collection rates for the selected
     * term and level. No payment references, account numbers, or balances per
     * payer are exposed.
     *
     * @param array $filters allowed keys: term, level
     */
    public function feeCollectionSummary(array $filters = []): array
    {
        $term = $this->singleString($filters, 'term');
        $level = $this->singleString($filters, 'level');

        $where = [];
        $params = [];
        if ($term !== null) {
            $where[] = 'academic_term = ?';
            $params[] = $term;
        }
        if ($level !== null) {
            $where[] = 'level_name = ?';
            $params[] = $level;
        }

        $sql = 'SELECT level_name, level_code, academic_term, total_students,
                       total_fees_due, total_fees_paid, total_fees_waived,
                       collection_rate_percent, students_paid_in_full,
                       students_partial_payment, students_no_payment,
                       average_payment_per_student
                FROM ' . $this->view('collection_rate_by_class');
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY level_code ASC LIMIT ' . self::MAX_LIMIT;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $due = 0.0;
        $paid = 0.0;
        $students = 0;
        foreach ($rows as $row) {
            $due += (float) ($row['total_fees_due'] ?? 0);
            $paid += (float) ($row['total_fees_paid'] ?? 0);
            $students += (int) ($row['total_students'] ?? 0);
        }

        return [
            'granularity' => 'level',
            'scope' => ['term' => $term, 'level' => $level],
            'levels' => $rows,
            'totals' => [
                'students' => $students,
                'fees_due' => round($due, 2),
                'fees_paid' => round($paid, 2),
                'collection_rate_percent' => $due > 0 ? round($paid / $due * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Aggregate CBC academic performance: per level and learning area average,
     * with competency band distribution. No individual grades or learner
     * columns.
     *
     * @param array $filters allowed keys: class, learning_area
     */
    public function academicPerformanceSummary(array $filters = []): array
    {
        $class = $this->singleString($filters, 'class');
        $learningArea = $this->singleString($filters, 'learning_area');

        $where = [];
        $params = [];
        if ($class !== null) {
            $where[] = 'class_name = ?';
            $params[] = $class;
        }
        if ($learningArea !== null) {
            $where[] = 'learning_area = ?';
            $params[] = $learningArea;
        }

        $sql = 'SELECT class_name, stream_name, learning_area, term_name,
                       assessments_count, students_assessed, average_percentage,
                       ee_count, me_count, ae_count, be_count
                FROM ' . $this->view('class_learning_area_performance');
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY class_name ASC, learning_area ASC LIMIT ' . self::MAX_LIMIT;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $assessed = 0;
        $ee = 0;
        $me = 0;
        $ae = 0;
        $be = 0;
        foreach ($rows as $row) {
            $assessed += (int) ($row['students_assessed'] ?? 0);
            $ee += (int) ($row['ee_count'] ?? 0);
            $me += (int) ($row['me_count'] ?? 0);
            $ae += (int) ($row['ae_count'] ?? 0);
            $be += (int) ($row['be_count'] ?? 0);
        }
        $meetingOrAbove = $ee + $me;

        return [
            'granularity' => 'level_learning_area',
            'scope' => ['class' => $class, 'learning_area' => $learningArea],
            'rows' => $rows,
            'totals' => [
                'students_assessed' => $assessed,
                'below_expectation' => $be,
                'approaching_expectation' => $ae,
                'meeting_expectation' => $me,
                'exceeding_expectation' => $ee,
                'mastery_rate_percent' => $assessed > 0 ? round($meetingOrAbove / $assessed * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Aggregate enrollment: current headcount by class stream, student type,
     * and gender, plus the overall active-enrollment count. Never returns
     * learner identity.
     *
     * @param array $filters allowed keys: class, status
     */
    public function enrollmentStats(array $filters = []): array
    {
        $class = $this->singleString($filters, 'class');
        $status = $this->singleString($filters, 'status');

        $where = ['is_current = 1'];
        $params = [];
        if ($class !== null) {
            $where[] = 'class_stream = ?';
            $params[] = $class;
        }
        if ($status !== null) {
            $where[] = 'student_status = ?';
            $params[] = $status;
        }

        $master = ConnectionManager::schemaFor(ConnectionManager::NS_MASTER);
        $table = $master . '.vw_current_enrollments';
        $whereSql = implode(' AND ', $where);

        $stmt = $this->pdo()->prepare(
            "SELECT class_stream, gender, COUNT(*) AS count
             FROM {$table} WHERE {$whereSql}
             GROUP BY class_stream, gender
             ORDER BY class_stream ASC LIMIT " . self::MAX_LIMIT
        );
        $stmt->execute($params);
        $byClass = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $this->pdo()->prepare("SELECT COUNT(*) AS count FROM {$table} WHERE {$whereSql}");
        $stmtTotal->execute($params);
        $total = (int) ($stmtTotal->fetchColumn() ?: 0);

        return [
            'granularity' => 'class_stream_gender',
            'total_active_enrollments' => $total,
            'scope' => ['class' => $class, 'status' => $status],
            'by_class_gender' => $byClass,
        ];
    }

    private function dateValue(array $filters, string $key, ?string $default): ?string
    {
        if (!isset($filters[$key])) {
            return $default;
        }
        $value = preg_replace('/[^0-9-]/', '', (string) $filters[$key]);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new DomainException("Filter '{$key}' must be a YYYY-MM-DD date.", 422);
        }
        return $value;
    }

    private function singleString(array $filters, string $key): ?string
    {
        if (!isset($filters[$key])) {
            return null;
        }
        $value = trim((string) $filters[$key]);
        if ($value === '' || mb_strlen($value) > 100) {
            throw new DomainException("Filter '{$key}' is invalid.", 422);
        }
        return mb_substr($value, 0, 100);
    }

    private function enumList(array $filters, string $key, array $allowed): array
    {
        if (!isset($filters[$key])) {
            return [];
        }
        $value = $filters[$key];
        $items = is_array($value) ? array_values($value) : [$value];
        if (count($items) > self::MAX_FILTER_VALUES) {
            throw new DomainException("Filter '{$key}' contains too many values.", 422);
        }
        $clean = [];
        foreach ($items as $item) {
            $item = (string) $item;
            if (!in_array($item, $allowed, true)) {
                throw new DomainException("Filter '{$key}' contains an unsupported value.", 422);
            }
            $clean[] = $item;
        }
        return $clean;
    }
}