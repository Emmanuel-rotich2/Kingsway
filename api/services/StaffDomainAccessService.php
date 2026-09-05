<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * Canonical authorization and scope service for the Staff domain.
 *
 * This service does not trust browser-provided staff IDs. Self-service endpoints
 * resolve the staff record from the authenticated user. Administrative endpoints
 * require explicit permissions returned by RBACMiddleware.
 */
final class StaffDomainAccessService
{
    private $db;
    private array $user;

    public function __construct(?array $user = null)
    {
        $this->db = Database::getInstance();
        $this->user = $user ?? ($_SERVER['auth_user'] ?? []);
    }

    public function authenticated(): bool
    {
        return $this->userId() > 0;
    }

    public function userId(): int
    {
        return (int)($this->user['id'] ?? $this->user['user_id'] ?? 0);
    }

    public function staffId(): ?int
    {
        $direct = (int)($this->user['staff_id'] ?? 0);
        if ($direct > 0) {
            return $direct;
        }

        $userId = $this->userId();
        if ($userId <= 0) {
            return null;
        }

        // users.staff_id was dropped in the 4NF schema — the staff↔user link is
        // the shared persons row (staff.person_id = users.person_id).
        $row = $this->db->query(
            'SELECT s.id
             FROM staff s
             JOIN users u ON u.person_id = s.person_id
             WHERE u.id = ? LIMIT 1',
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id'] : null;
    }

    public function permissions(): array
    {
        $permissions = $this->user['effective_permissions'] ?? $this->user['permissions'] ?? [];
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $permissions);
        }
        return array_values(array_unique(array_filter(array_map(
            [$this, 'scalarAuthValue'],
            (array)$permissions
        ))));
    }

    public function roles(): array
    {
        $roles = $this->user['roles'] ?? $this->user['role_names'] ?? [];
        if (isset($this->user['role'])) {
            $roles[] = $this->user['role'];
        }
        if (isset($this->user['role_name'])) {
            $roles[] = $this->user['role_name'];
        }
        return array_values(array_unique(array_filter(array_map(
            fn($role) => strtolower($this->scalarAuthValue($role)),
            (array)$roles
        ))));
    }

    public function allows(string $permission, array $fallbackRoles = []): bool
    {
        $permissions = $this->permissions();
        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }

        if ($fallbackRoles) {
            $roles = $this->roles();
            foreach ($fallbackRoles as $role) {
                if (in_array(strtolower($role), $roles, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function require(string $permission, array $fallbackRoles = []): void
    {
        if (!$this->authenticated()) {
            throw new RuntimeException('Authentication required', 401);
        }
        if (!$this->allows($permission, $fallbackRoles)) {
            throw new RuntimeException("Permission required: {$permission}", 403);
        }
    }

    public function requireSelfOr(string $permission, int $requestedStaffId, array $fallbackRoles = []): int
    {
        $ownStaffId = $this->staffId();
        if ($ownStaffId && $ownStaffId === $requestedStaffId) {
            return $ownStaffId;
        }
        $this->require($permission, $fallbackRoles);
        return $requestedStaffId;
    }

    public function forceSelfScope(array $filters): array
    {
        if ($this->allows('staff.attendance.manage', ['system administrator', 'school administrator', 'headteacher', 'director'])) {
            return $filters;
        }
        $staffId = $this->staffId();
        if (!$staffId) {
            throw new RuntimeException('No staff profile is linked to this account', 403);
        }
        $filters['staff_id'] = $staffId;
        return $filters;
    }

    public function payrollEligibility(int $staffId): array
    {
        // Identity (first_name/last_name) now lives ONLY on persons; the payroll
        // Payroll identity and compensation are sourced from the normalized
        // payroll profile; staff is only the employment subtype.
        $staff = $this->db->query(
            'SELECT s.id, s.staff_no, s.status, COALESCE(spp.basic_salary,0) AS salary, s.employment_date,
                    p.first_name, p.last_name,
                    spp.bank_name, spp.bank_account, spp.kra_pin, spp.nssf_no, spp.nhif_no
             FROM staff s
             JOIN persons p ON p.id = s.person_id
             LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
             WHERE s.id = ? AND s.data_scope = ? LIMIT 1',
            [$staffId, DataScopeService::current()]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$staff) {
            return ['eligible' => false, 'reasons' => ['Staff member not found'], 'staff' => null];
        }

        $reasons = [];
        if ($staff['status'] !== 'active') $reasons[] = 'Staff status must be active';
        if ((float)$staff['salary'] <= 0) $reasons[] = 'Basic salary is missing or zero';
        if (empty($staff['employment_date'])) $reasons[] = 'Employment date is missing';
        if (empty($staff['bank_name']) || empty($staff['bank_account'])) $reasons[] = 'Bank payment details are incomplete';
        if (empty($staff['kra_pin'])) $reasons[] = 'KRA PIN is missing';
        if (empty($staff['nssf_no'])) $reasons[] = 'NSSF number is missing';
        if (empty($staff['nhif_no'])) $reasons[] = 'Health insurance number is missing';

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'staff' => $staff,
        ];
    }

    public function assertPayrollEligible(int $staffId): void
    {
        $result = $this->payrollEligibility($staffId);
        if (!$result['eligible']) {
            throw new RuntimeException('Staff member is not payroll eligible: ' . implode('; ', $result['reasons']), 422);
        }
    }

    public function audit(string $action, string $entityType, ?int $entityId, array $before = null, array $after = null): void
    {
        try {
            $details = [
                'before' => $before,
                'after' => $after,
            ];
            // staff_domain_audit was dropped; the domain audit trail now lives in the
            // file-based audit log. staff_id is preserved inside details, and the
            // browser user_agent is captured too.
            $details['staff_id'] = $this->staffId();

            \App\API\Includes\FileLogger::write('audit', [
                'type' => 'audit',
                'user_id' => $this->userId() ?: null,
                'action' => $action,
                'entity' => $entityType,
                'entity_id' => $entityId !== null ? (string) $entityId : null,
                'details' => $details,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'status' => 'success',
            ]);
        } catch (\Throwable $ignored) {
            // Audit failure must not corrupt the business transaction.
        }
    }

    private function scalarAuthValue($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value);
        }

        if (is_array($value)) {
            foreach (['permission_code', 'code', 'name', 'role_name', 'label'] as $key) {
                if (isset($value[$key]) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                    return trim((string)$value[$key]);
                }
            }
        }

        if (is_object($value)) {
            foreach (['permission_code', 'code', 'name', 'role_name', 'label'] as $key) {
                if (isset($value->{$key}) && (is_string($value->{$key}) || is_numeric($value->{$key}))) {
                    return trim((string)$value->{$key});
                }
            }
        }

        return '';
    }
}
