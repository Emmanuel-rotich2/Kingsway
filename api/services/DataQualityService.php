<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;
use PDO;
use App\API\Services\FieldCleaner;
use App\API\Services\PhoneNumberNormalizer;

/**
 * DataQualityService — legacy data re-normalization.
 *
 * One-time and recurring hygiene pass over the live database so stored records
 * match the same canonical rules the application now enforces at collection
 * time (see FieldCleaner/PhoneNumberNormalizer). It covers the whole pipeline
 * leg from "storage" back to "analysis" readiness:
 *
 *   - names        : trim, collapse whitespace, title-case person names
 *                    (organizational names are only whitespace-collapsed to
 *                    avoid mangling brand labels such as "KCB").
 *   - emails       : trimmed + lowercased for case-insensitive identity/dedupe.
 *   - phones       : canonical E.164-without-plus 2547XXXXXXXX via the shared
 *                    PhoneNumberNormalizer.
 *   - national ids : digits-only 5-12 character canonical form.
 *   - dedupe       : persons grouped by normalized email, phone match-key and
 *                    identity (name+dob) so staff can review and merge; contact
 *                    points grouped by person/channel/value. Nothing is ever
 *                    merged automatically — dedupe is detection only.
 *
 * THIS SERVICE NEVER DELETES OR MERGES ROWS, and apply() only performs
 * value-level UPDATEs for exact, pre-audited canonical fixes. The intended
 * workflow is:
 *
 *   1. audit($db)                     -> read-only findings (no writes)
 *   2. staff review the fix list
 *   3. apply(fixes, ...)              -> transaction-wrapped, idempotent UPDATEs
 *
 * apply() skips a fix if the current value no longer matches the audited
 * original (optimistic concurrency + idempotency). Every apply() round is
 * journaled to the `data_quality` file journal with counts only — never the
 * personal values themselves.
 *
 * Schema access is allowlisted through the TARGETS registry; arbitrary table or
 * column names are rejected with a DomainException before any SQL is built.
 */
final class DataQualityService
{
    private \Closure $journal;
    /**
     * Table -> column collector specs.
     *
     * kind is one of: name, org_name, email, phone, national_id.
     * required marks a column whose empty value is a missing-data finding.
     */
    private const TARGETS = [
        'persons' => [
            'identity' => true,
            'columns' => [
                'first_name' => ['kind' => 'name', 'required' => true],
                'middle_name' => ['kind' => 'name', 'required' => false],
                'last_name' => ['kind' => 'name', 'required' => true],
                'email' => ['kind' => 'email', 'required' => false],
                'phone' => ['kind' => 'phone', 'required' => false],
                'national_id_no' => ['kind' => 'national_id', 'required' => false],
            ],
        ],
        'person_contact_points' => [
            'channel_aware' => true,
            'channel_col' => 'channel',
            'value_col' => 'contact_value',
            'required' => false,
        ],
        'emergency_contacts' => [
            'columns' => [
                'name' => ['kind' => 'name', 'required' => true],
                'phone' => ['kind' => 'phone', 'required' => false],
            ],
        ],
        'suppliers' => [
            'columns' => [
                'name' => ['kind' => 'org_name', 'required' => true],
                'contact_person' => ['kind' => 'name', 'required' => false],
                'phone' => ['kind' => 'phone', 'required' => false],
                'email' => ['kind' => 'email', 'required' => false],
            ],
        ],
        'job_applications' => [
            'columns' => [
                'first_name' => ['kind' => 'name', 'required' => true],
                'last_name' => ['kind' => 'name', 'required' => true],
                'email' => ['kind' => 'email', 'required' => true],
                'phone' => ['kind' => 'phone', 'required' => true],
            ],
        ],
        'contact_inquiries' => [
            'columns' => [
                'full_name' => ['kind' => 'name', 'required' => true],
                'email' => ['kind' => 'email', 'required' => true],
                'phone' => ['kind' => 'phone', 'required' => false],
            ],
        ],
        'newsletter_subscribers' => [
            'columns' => [
                'name' => ['kind' => 'name', 'required' => false],
                'email' => ['kind' => 'email', 'required' => true],
            ],
        ],
    ];

    public function __construct(private PDO $db, ?callable $journal = null)
    {
        $this->journal = $journal instanceof \Closure
            ? $journal
            : (static function (string $level, string $message, array $context = []): void {
                Logger::log('data_quality', $level, $message, $context);
            });
    }

    /**
     * Read-only audit: scan the targeted tables, compute the canonical value
     * for every governed column and report three kinds of finding:
     *
     *   - fixes  : rows whose current value differs from the canonical value
     *              (safe, deterministic UPDATE candidates for apply()).
     *   - review : rows whose value is unparseable or invalid (unresolvable
     *              phone, letters in a national ID, malformed email) — these
     *              are never auto-fixed; staff decides manually.
     *   - missing: required columns that are empty.
     *
     * Also returns duplicate candidate groups (persons by email/phone/identity
     * and contact-point rows by person/channel/value). No writes occur here.
     *
     * @param array $options ['tables'=>allowlated subset, 'maxRows'=>per-table cap]
     * @return array{
     *     scanned: array<string,int>,
     *     fixes: list<array{table:string,id:int,column:string,label:string,from:string,to:string}>,
     *     review: list<array{table:string,id:int,column:string,label:string,value:string,reason:string}>,
     *     missing: list<array{table:string,id:int,column:string,label:string}>,
     *     duplicates: array<string,mixed>
     * }
     */
    public function audit(array $options = []): array
    {
        $tables = $this->resolveTargets($options['tables'] ?? null);
        $maxRows = max(1, (int) ($options['maxRows'] ?? 100000));

        $scanned = [];
        $fixes = [];
        $review = [];
        $missing = [];

        foreach ($tables as $table => $spec) {
            $rows = $this->fetchRows($table, $spec, $maxRows);
            $scanned[$table] = count($rows);

            if (!empty($spec['channel_aware'])) {
                $this->auditChannelAware($rows, $table, $fixes, $review, $missing);
                continue;
            }

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                foreach ($spec['columns'] as $column => $config) {
                    $current = $row[$column] ?? null;
                    $this->auditColumn($current, $table, $id, $column, $config, $fixes, $review, $missing);
                }
            }
        }

        return [
            'scanned' => $scanned,
            'fixes' => $fixes,
            'review' => $review,
            'missing' => $missing,
            'duplicates' => $this->detectDuplicates($options),
        ];
    }

    /**
     * Apply a reviewed subset of the audit() fixes inside a single transaction.
     *
     * Every fix is validated against the TARGETS allowlist first. The UPDATE is
     * guarded with `AND column = :from`, so a row that changed since the audit
     * is skipped (optimistic concurrency, and applying the same list twice is a
     * no-op rather than a rewrite). Affected rows and skipped rows are returned
     * and the round is journaled (counts only — no personal values).
     *
     * @param array<int,array{table:string,id:int,column:string,to:string,from?:string}> $fixes
     * @return array{applied:int,skipped:int,errors:array<string,string>}
     */
    public function apply(array $fixes, array $options = []): array
    {
        $applied = 0;
        $skipped = 0;
        $errors = [];

        // Normalize each fix through the allowlist (id/column/table) and
        // re-derive the expected canonical value from the ORIGINAL value so a
        // tampered "to" can never bypass the cleaner.
        $validated = [];
        foreach ($fixes as $index => $fix) {
            try {
                $entry = $this->validateFix($fix);
            } catch (DomainException $e) {
                $errors['fix_' . $index] = $e->getMessage();
                continue;
            }
            [$table, $id, $column, $from, $channel] = $entry;

            $canonical = $this->canonicalFor($table, $column, $from, ['channel' => $channel]);
            if ($canonical === null) {
                $errors['fix_' . $index] = 'No canonical value could be derived (row left untouched)';
                continue;
            }

            $validated[] = ['table' => $table, 'id' => $id, 'column' => $column, 'from' => $from, 'to' => $canonical];
            $updateCache[$table . '.' . $column] = true;
        }

        if ($validated === []) {
            $this->journal('warning', 'Data quality apply round produced no valid fixes', [
                'applied' => 0, 'skipped' => 0, 'errors' => count($errors),
            ]);
            return ['applied' => 0, 'skipped' => 0, 'errors' => $errors];
        }

        $this->db->beginTransaction();
        try {
            foreach ($validated as $fix) {
                $sql = $this->updateSql($fix['table'], $fix['column']);
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':value' => $fix['to'],
                    ':id' => $fix['id'],
                    ':from' => $fix['from'],
                ]);
                if ($stmt->rowCount() === 1) {
                    $applied++;
                } else {
                    $skipped++;
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->journal('error', 'Data quality apply round failed and was rolled back', [
                'applied' => $applied, 'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->journal('info', 'Data quality apply round completed', [
            'applied' => $applied, 'skipped' => $skipped, 'errors' => count($errors),
        ]);

        return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Duplicate-candidate detection (read-only, never merges).
     *
     * persons are grouped by normalized email, phone match-key and "identity"
     * (title-cased first|middle|last names + dob when all are present). Contact
     * points are grouped by person_id + channel + trimmed-lowercased value.
     * Returns key -> list of group-arrays of person ids; groups with only one
     * member are omitted.
     *
     * @return array<string,mixed>
     */
    public function detectDuplicates(array $options = []): array
    {
        $tables = $this->resolveTargets($options['tables'] ?? null);
        $duplicates = [];

        if (isset($tables['persons']) && !empty($tables['persons']['identity'])) {
            $records = $this->fetchRows('persons', $tables['persons'], (int) ($options['maxRows'] ?? 20000));

            $byEmail = [];
            $byPhone = [];
            $byIdentity = [];
            foreach ($records as $r) {
                $email = FieldCleaner::cleanEmail($r['email'] ?? null);
                if ($email !== null && $email !== '') {
                    $byEmail[$email][] = (int) $r['id'];
                }
                $phoneKey = PhoneNumberNormalizer::matchKey($r['phone'] ?? null);
                if ($phoneKey !== '') {
                    $byPhone[$phoneKey][] = (int) $r['id'];
                }
                $parts = [];
                foreach (['first_name', 'middle_name', 'last_name'] as $c) {
                    $cleaned = FieldCleaner::cleanName($r[$c] ?? null, false);
                    if ($cleaned !== null && $cleaned !== '') {
                        $parts[] = strtolower($cleaned);
                    }
                }
                if (count($parts) >= 2 && !empty($r['dob'])) {
                    $key = implode(' ', $parts) . '|' . $r['dob'];
                    $byIdentity[$key][] = (int) $r['id'];
                }
            }

            $duplicates['persons'] = [
                'by_email' => $this->groups($byEmail),
                'by_phone' => $this->groups($byPhone),
                'by_identity' => $this->groups($byIdentity),
            ];
        }

        if (isset($tables['person_contact_points'])) {
            $points = $this->fetchRows('person_contact_points', $tables['person_contact_points'], (int) ($options['maxRows'] ?? 50000));
            $byKey = [];
            foreach ($points as $p) {
                $value = strtolower(trim((string) ($p['contact_value'] ?? '')));
                if ($value === '') {
                    continue;
                }
                $byKey[(int) $p['person_id'] . '|' . ($p['channel'] ?? '') . '|' . $value][] = (int) $p['id'];
            }
            $duplicates['person_contact_points'] = $this->groups($byKey);
        }

        return $duplicates;
    }

    private function auditColumn(
        $current,
        string $table,
        int $id,
        string $column,
        array $config,
        array &$fixes,
        array &$review,
        array &$missing
    ): void {
        $label = $this->label($column);
        $isEmpty = $current === null || trim((string) $current) === '';

        if ($isEmpty) {
            if (!empty($config['required'])) {
                $missing[] = ['table' => $table, 'id' => $id, 'column' => $column, 'label' => $label];
            }
            return;
        }

        $result = $this->canonicalFor($table, $column, (string) $current, []);
        if ($result === null) {
            $review[] = [
                'table' => $table,
                'id' => $id,
                'column' => $column,
                'label' => $label,
                'value' => (string) $current,
                'reason' => $this->reviewReason($config['kind'] ?? ''),
            ];
            return;
        }

        if ($result !== $current) {
            $fixes[] = [
                'table' => $table,
                'id' => $id,
                'column' => $column,
                'label' => $label,
                'from' => (string) $current,
                'to' => $result,
            ];
        }
    }

    private function auditChannelAware(array $rows, string $table, array &$fixes, array &$review, array &$missing): void
    {
        $spec = self::TARGETS[$table];
        $valueCol = $spec['value_col'];
        $channelCol = $spec['channel_col'];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $channel = (string) ($row[$channelCol] ?? '');
            $current = $row[$valueCol] ?? null;
            $isEmpty = $current === null || trim((string) $current) === '';
            if ($isEmpty) {
                if (!empty($spec['required'])) {
                    $missing[] = ['table' => $table, 'id' => $id, 'column' => $valueCol, 'label' => $this->label($valueCol)];
                }
                continue;
            }
            $result = $channel === 'email'
                ? FieldCleaner::cleanEmail((string) $current)
                : PhoneNumberNormalizer::normalize((string) $current);

            if ($result === null) {
                $review[] = [
                    'table' => $table,
                    'id' => $id,
                    'column' => $valueCol,
                    'label' => $this->label($valueCol),
                    'value' => (string) $current,
                    'reason' => $channel === 'email' ? 'Malformed contact email' : 'Unresolvable contact phone',
                ];
                continue;
            }
            if ($result !== $current) {
                $fixes[] = [
                    'table' => $table,
                    'id' => $id,
                    'column' => $valueCol,
                    'channel' => $channel,
                    'label' => $this->label($valueCol),
                    'from' => (string) $current,
                    'to' => $result,
                ];
            }
        }
    }

    /**
     * Canonical value for a governed column. Returns null when the value is
     * not normalizable (invalid name, unresolvable phone, etc.) so callers can
     * route it to review instead of destroying a legacy value.
     */
    private function canonicalFor(string $table, string $column, string $value, array $options): ?string
    {
        $spec = self::TARGETS[$table] ?? null;
        if ($spec === null) {
            throw new DomainException("Unknown data-quality target table: {$table}");
        }

        if (!empty($spec['channel_aware'])) {
            return $column === $spec['value_col']
                ? $this->cleanChannelValue($value, $options['channel'] ?? null)
                : null;
        }

        $config = $spec['columns'][$column] ?? null;
        if ($config === null) {
            throw new DomainException("Column {$column} is not governed on {$table}");
        }

        return match ($config['kind']) {
            'name' => FieldCleaner::cleanName($value),
            'org_name' => $this->cleanOrgName($value),
            'email' => $this->cleanEmailStrict($value),
            'phone' => PhoneNumberNormalizer::normalize($value),
            'national_id' => FieldCleaner::cleanNationalId($value),
            default => throw new DomainException("Unknown cleaner kind for {$table}.{$column}"),
        };
    }

    private function cleanChannelValue(string $value, ?string $channel): ?string
    {
        if ($channel === 'email') {
            return $this->cleanEmailStrict($value);
        }
        return PhoneNumberNormalizer::normalize($value);
    }

    private function cleanEmailStrict(string $value): ?string
    {
        $cleaned = FieldCleaner::cleanEmail($value);
        if ($cleaned === null) {
            return null;
        }
        return filter_var($cleaned, FILTER_VALIDATE_EMAIL) !== false ? $cleaned : null;
    }

    private function cleanOrgName(string $value): ?string
    {
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $value));
        if ($cleaned === '' || strlen($cleaned) > 255) {
            return null;
        }
        if (preg_match('/[^\x20-\x7E]/', $cleaned) === 1 || preg_match('/[=<>;{}\[\]' . "\x00" . ']/', $cleaned) === 1) {
            return null;
        }
        return $cleaned;
    }

    private function reviewReason(string $kind): string
    {
        return match ($kind) {
            'name', 'org_name' => 'Name contains characters that cannot be stored as-is (letters/spaces only for persons)',
            'email' => 'Malformed email (cannot be safely lowercased for identity)',
            'phone' => 'Unresolvable phone (not a canonical 2547XXXXXXXX Kenyan mobile)',
            'national_id' => 'Invalid national ID / passport number (digits only, 5-12 characters)',
            default => 'Value does not match the canonical rule',
        };
    }

    private function validateFix(array $fix): array
    {
        $table = (string) ($fix['table'] ?? '');
        $column = (string) ($fix['column'] ?? '');
        $id = (int) ($fix['id'] ?? 0);
        $from = (string) ($fix['from'] ?? ($fix['current'] ?? ''));

        $spec = self::TARGETS[$table] ?? null;
        if ($spec === null || $id <= 0) {
            throw new DomainException("Rejected fix: unknown table or invalid id");
        }
        if (!empty($spec['channel_aware']) && $column === $spec['value_col']) {
            // ok
        } elseif (!isset($spec['columns'][$column])) {
            throw new DomainException("Rejected fix: column {$column} is not governed on {$table}");
        }

        return [$table, $id, $column, $from, isset($fix['channel']) ? (string) $fix['channel'] : null];
    }

    private function updateSql(string $table, string $column): string
    {
        $spec = self::TARGETS[$table];
        if (empty($spec['channel_aware']) && !isset($spec['columns'][$column])) {
            throw new DomainException('Column not governed: ' . $table . '.' . $column);
        }
        return 'UPDATE `' . $table . '` SET `' . $column . '` = :value WHERE `id` = :id AND `' . $column . '` = :from';
    }

    /**
     * Keyset-paginated read of every governed row of a table (bounded by maxRows).
     */
    private function fetchRows(string $table, array $spec, int $maxRows): array
    {
        $columns = ['id'];
        if (!empty($spec['channel_aware'])) {
            $columns[] = $spec['channel_col'];
            $columns[] = $spec['value_col'];
            if ($table === 'person_contact_points') {
                $columns[] = 'person_id';
            }
        } elseif (!empty($spec['identity']) && $table === 'persons') {
            $columns[] = 'dob';
        }
        if (!empty($spec['columns'])) {
            foreach (array_keys($spec['columns']) as $column) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        $select = implode(',', array_map(static fn (string $c) => '`' . $c . '`', $columns));
        $rows = [];
        $lastId = 0;
        $chunk = 1000;
        while (count($rows) < $maxRows) {
            $take = min($chunk, $maxRows - count($rows));
            $stmt = $this->db->prepare("SELECT {$select} FROM `{$table}` WHERE `id` > :lastId ORDER BY `id` ASC LIMIT :take");
            $stmt->bindValue(':lastId', $lastId, PDO::PARAM_INT);
            $stmt->bindValue(':take', $take, PDO::PARAM_INT);
            $stmt->execute();
            $page = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($page === []) {
                break;
            }
            foreach ($page as $row) {
                $rows[] = $row;
            }
            $lastId = (int) end($rows)['id'];
            if (count($page) < $take) {
                break;
            }
        }
        return $rows;
    }

    private function resolveTargets(?array $filter): array
    {
        if ($filter === null) {
            return self::TARGETS;
        }
        $resolved = [];
        foreach ($filter as $table) {
            if (!isset(self::TARGETS[$table])) {
                throw new DomainException("Unknown data-quality target table: {$table}");
            }
            $resolved[$table] = self::TARGETS[$table];
        }
        return $resolved;
    }

    private function groups(array $buckets): array
    {
        $result = [];
        foreach ($buckets as $key => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (count($ids) > 1) {
                $result[] = ['key' => (string) $key, 'ids' => $ids, 'count' => count($ids)];
            }
        }
        usort($result, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        return $result;
    }

    private function label(string $column): string
    {
        return ucfirst(str_replace('_', ' ', $column));
    }

    private function journal(string $level, string $message, array $context = []): void
    {
        ($this->journal)($level, $message, $context);
    }
}