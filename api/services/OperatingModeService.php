<?php

namespace App\API\Services;

use App\Config\Config;
use App\Database\Database;
use DomainException;
use InvalidArgumentException;
use PDO;

/** Authoritative infrastructure-environment and production operating-mode policy. */
final class OperatingModeService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        Config::init();
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public static function environment(): string
    {
        $environment = strtolower((string) Config::getEnvironment());
        return in_array($environment, ['development', 'staging', 'production'], true)
            ? $environment
            : 'development';
    }

    public function current(): array
    {
        $environment = self::environment();
        if ($environment === 'development') {
            return $this->describe('development', 'development', null, null, null);
        }

        if ($environment === 'staging') {
            return $this->describe('staging', 'test', null, null, null);
        }

        // Production fails closed to live mode if the private schema change has
        // not yet been applied or the singleton row cannot be read.
        try {
            $stmt = $this->db->prepare(
                'SELECT mode, reason, updated_by, updated_at
                 FROM system_operating_modes WHERE environment=? LIMIT 1'
            );
            $stmt->execute(['production']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $error) {
            $row = [];
        }

        $mode = ($row['mode'] ?? '') === 'test' ? 'test' : 'live';
        return $this->describe(
            'production',
            $mode,
            $row['reason'] ?? null,
            isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            $row['updated_at'] ?? null
        );
    }

    public function change(string $mode, int $actorId, string $reason, string $confirmation): array
    {
        if (self::environment() !== 'production') {
            throw new DomainException('The production operating-mode switch is only available in production');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['test', 'live'], true)) {
            throw new InvalidArgumentException('Operating mode must be test or live');
        }
        if ($actorId <= 0) throw new InvalidArgumentException('A valid System Administrator is required');
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException('A reason is required');

        $expected = $mode === 'live' ? 'SWITCH TO LIVE' : 'ENABLE TEST MODE';
        if (trim($confirmation) !== $expected) {
            throw new InvalidArgumentException('Type ' . $expected . ' to confirm this mode change');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO system_operating_modes (environment,mode,reason,updated_by,updated_at)
             VALUES ('production',?,?,?,NOW())
             ON DUPLICATE KEY UPDATE mode=VALUES(mode),reason=VALUES(reason),
                 updated_by=VALUES(updated_by),updated_at=NOW()"
        );
        $stmt->execute([$mode, $reason, $actorId]);

        if ($mode === 'live') {
            $this->db->exec(
                "UPDATE user_sessions s JOIN users u ON u.id=s.user_id
                 SET s.session_status='revoked',s.logout_time=NOW()
                 WHERE (u.account_type='test' OR u.is_test_user=1)
                   AND s.session_status='active' AND s.logout_time IS NULL"
            );
            $this->db->exec(
                "DELETE rt FROM refresh_tokens rt JOIN users u ON u.id=rt.user_id
                 WHERE u.account_type='test' OR u.is_test_user=1"
            );
        }

        Logger::audit('production_operating_mode_changed', 'system', null, 'Production operating mode changed.', [
            'mode' => $mode,
            'reason' => $reason,
            'updated_by' => $actorId,
        ]);
        return $this->current();
    }

    public function allowsTestAccounts(): bool
    {
        $state = $this->current();
        return $state['environment'] !== 'production' || $state['mode'] === 'test';
    }

    private function describe(
        string $environment,
        string $mode,
        ?string $reason,
        ?int $updatedBy,
        ?string $updatedAt
    ): array {
        return [
            'environment' => $environment,
            'mode' => $mode,
            'mutable' => $environment === 'production',
            'test_accounts_allowed' => $environment !== 'production' || $mode === 'test',
            'real_accounts_allowed' => true,
            'real_account_scope' => 'live',
            'test_account_scope' => 'test',
            'reason' => $reason,
            'updated_by' => $updatedBy,
            'updated_at' => $updatedAt,
        ];
    }
}
