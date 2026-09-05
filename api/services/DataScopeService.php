<?php

namespace App\API\Services;

use DomainException;
use PDO;

/** Central request-scoped boundary between live and synthetic school data. */
final class DataScopeService
{
    public static function current(): string
    {
        $scope = (string) ($_SERVER['data_scope'] ?? ($_SERVER['auth_user']['data_scope'] ?? 'live'));
        return $scope === 'test' ? 'test' : 'live';
    }

    public static function requireStaff(PDO $db, int $staffId): string
    {
        $stmt = $db->prepare('SELECT data_scope FROM staff WHERE id=? LIMIT 1');
        $stmt->execute([$staffId]);
        $scope = $stmt->fetchColumn();
        if ($scope === false) throw new DomainException('Staff record not found');
        if ($scope !== self::current()) {
            throw new DomainException('Cross-workspace staff access is not permitted');
        }
        return (string) $scope;
    }

    public static function requireLiveExternalAction(string $operation): void
    {
        if (self::current() !== 'live') {
            throw new DomainException($operation . ' is blocked in the test workspace');
        }
    }

    /**
     * Production/staging test accounts fail closed outside modules whose reads
     * and writes have been reviewed for the test realm. Development uses its
     * separate database and is intentionally unrestricted.
     */
    public static function requireReviewedTestRoute(string $method, string $requestUri): void
    {
        if (self::current() !== 'test' || TestAccountAccessService::environment() === 'development') {
            return;
        }

        $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
        $path = preg_replace('#^/(?:Kingsway/)?api#i', '', $path) ?: '/';
        $method = strtoupper($method);

        if (str_starts_with($path, '/staff') && $method !== 'GET') {
            throw new DomainException(
                "{$method} {$path} is not enabled in the isolated production test workspace"
            );
        }

        $safe = [
            '#^/auth(?:/|$)#',
            '#^/users/profile(?:/|$)#',
            '#^/staff/?$#',
            '#^/staff/(?:list|stats|departments|key-contacts|teachers|non-teaching)(?:/|$)#',
            '#^/finance/(?:payroll|payrolls|staff-for-payroll|staff-payroll-details|bulk-payroll-preview|process-bulk-payroll|process-payroll-with-deductions|detailed-payslip)(?:-|/|$)#',
        ];

        foreach ($safe as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return;
            }
        }

        throw new DomainException(
            "{$method} {$path} is not enabled in the isolated production test workspace"
        );
    }
}
