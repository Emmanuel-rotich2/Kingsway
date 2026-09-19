<?php

namespace App\API\Services;

use App\Database\Database;
use DomainException;
use PDO;

/**
 * Central request-scoped boundary between live and synthetic school data.
 *
 * A single database-driven decision resolves every scoped query:
 *   - users.account_type (real|test|service) is the identity of the account.
 *   - users.data_scope (live|test|both) is a per-account visibility knob the
 *     System Administrator adjusts from the user-accounts page.
 *   - Shared/reference tables (no data_scope column, no person_id) are visible
 *     to every account regardless of side.
 *   - Column-scoped tables carry their own data_scope (staff, persons,
 *     payslips, payroll_runs, staff_payroll_profiles).
 *   - Person-rooted tables (students, parents) inherit the scope of the linked
 *     persons.data_scope row through person_id.
 *
 * predicateFor() derives the classification from the live schema once and caches
 * it per request, so no registry table and no per-query drift is possible.
 */
final class DataScopeService
{
    /** @var array<string, array{class:string,has_column:bool,has_person:bool}> */
    private static array $schema = [];

    public static function current(): string
    {
        $scope = (string) ($_SERVER['data_scope'] ?? ($_SERVER['auth_user']['data_scope'] ?? 'live'));
        return in_array($scope, ['live', 'test', 'both'], true) ? $scope : 'live';
    }

    /**
     * The set of record scopes the current account is allowed to see.
     *
     * Policy is environment-first: on localhost every account resolves to
     * 'both' (live + test) so the whole workspace is visible while the system
     * is still being developed. On the production host the account-level
     * visibility knob (users.data_scope: live|test|both) governs, with 'both'
     * expanding to live + test.
     */
    public static function scopes(): array
    {
        if (!EnvironmentPhaseService::isProductionHost()) {
            return ['live', 'test'];
        }
        $scope = self::current();
        if ($scope === 'both') return ['live', 'test'];
        return [$scope === 'test' ? 'test' : 'live'];
    }

    /**
     * The single scope to stamp on a record created by the current account.
     * Records are only ever live or test; a 'both' account writes to live.
     */
    public static function recordScope(): string
    {
        return in_array('live', self::scopes(), true) ? 'live' : 'test';
    }

    /**
     * Build a WHERE fragment (plus bound params) that restricts rows of $table
     * (aliased $alias) to the current account's scopes.
     *
     * Returns ['1=1', []] for shared/reference tables.
     *
     * @return array{0:string,1:array<int,string>}
     */
    public static function predicateFor(string $table, string $alias = ''): array
    {
        $alias = $alias !== '' ? rtrim($alias, '.') : $table;
        $kind = self::classify($table);

        if (!$kind['has_column'] && !$kind['has_person']) {
            return ['1=1', []];
        }

        $scopes = self::scopes();
        if (!$kind['has_column'] && $kind['has_person']) {
            // Person-rooted: inherit the scope of the linked persons row.
            $ph = implode(',', array_fill(0, count($scopes), '?'));
            return [
                "$alias.person_id IN (SELECT id FROM persons WHERE data_scope IN ($ph))",
                $scopes,
            ];
        }

        if (count($scopes) === 1) {
            return ["$alias.data_scope = ?", $scopes];
        }
        $ph = implode(',', array_fill(0, count($scopes), '?'));
        return ["$alias.data_scope IN ($ph)", $scopes];
    }

    /**
     * Classify a table once per request from the live schema:
     *   class 'column'     -> has its own data_scope column
     *   class 'person'     -> no data_scope column but references persons (person_id)
     *   class 'shared'     -> neither: reference/master data visible to all
     *
     * @return array{class:string,has_column:bool,has_person:bool}
     */
    private static function classify(string $table): array
    {
        if (isset(self::$schema[$table])) {
            return self::$schema[$table];
        }

        $hasColumn = false;
        $hasPerson = false;
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $stmt->execute([$table]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
                if ($column === 'data_scope') $hasColumn = true;
                if ($column === 'person_id') $hasPerson = true;
            }
        } catch (\Throwable $error) {
            // Fall back to the conservative default: treat unknown tables as
            // column-scoped so the caller never leaks rows by accident.
            return self::$schema[$table] = [
                'class' => 'column',
                'has_column' => true,
                'has_person' => false,
            ];
        }

        $class = $hasColumn ? 'column' : ($hasPerson ? 'person' : 'shared');
        return self::$schema[$table] = [
            'class' => $class,
            'has_column' => $hasColumn,
            'has_person' => $hasPerson,
        ];
    }

    public static function requireStaff(PDO $db, int $staffId): string
    {
        $stmt = $db->prepare('SELECT data_scope FROM staff WHERE id=? LIMIT 1');
        $stmt->execute([$staffId]);
        $scope = $stmt->fetchColumn();
        if ($scope === false) throw new DomainException('Staff record not found');
        if (!in_array($scope, self::scopes(), true)) {
            throw new DomainException('Cross-workspace staff access is not permitted');
        }
        return (string) $scope;
    }

    public static function requireLiveExternalAction(string $operation): void
    {
        if (!in_array('live', self::scopes(), true)) {
            throw new DomainException($operation . ' is blocked in the test workspace');
        }
    }

    /**
     * @deprecated The hardcoded route allowlist is removed. Row-level scoping
     *             through predicateFor() is the only boundary, so external
     *             money-moving and identity operations still use
     *             requireLiveExternalAction() where safety demands live scope.
     */
    public static function requireReviewedTestRoute(string $method, string $requestUri): void
    {
        return;
    }
}