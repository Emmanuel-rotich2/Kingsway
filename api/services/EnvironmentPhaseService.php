<?php

namespace App\API\Services;

use App\Config\Config;
use App\Database\Database;
use DomainException;
use InvalidArgumentException;
use PDO;

/**
 * Owns the runtime phase label for a given host.
 *
 * Kingsway runs on two hosts with named phases:
 *   - localhost : localhost-test (default), localhost-live (ngrok release-like label)
 *   - production: production-test, production-live (release), production-maintenance
 *
 * Phase is a per-server (browser-independent) setting, so it is resolved from
 * configuration whose authority the System Administrator can change from the
 * user-accounts page. Phase never turns on real payment providers: localhost and
 * production provider traffic remains governed by MPESA_ENVIRONMENT/KCB_ENVIRONMENT.
 *
 * Test-account policy derived from phase:
 *   - localhost (any phase)        -> test accounts always allowed, no grants needed.
 *   - production test/maintenance  -> grants gate access (per-account; you decide).
 *   - production live              -> test accounts hard-blocked (sessions revoked).
 *   - production auto_lock         -> when enabled, hard-block applies to whichever
 *                                     production phase is active, overriding the manual phase.
 */
final class EnvironmentPhaseService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        Config::init();
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /** temporary alias so existing callers keep resolving the same three buckets */
    public static function environment(): string
    {
        Config::init();
        return self::isProductionHost() ? 'production' : 'development';
    }

    public static function isProductionHost(): bool
    {
        $env = strtolower((string) Config::get('APP_ENV', ''));
        if ($env !== '') {
            return $env !== 'development' && $env !== 'staging';
        }
        Config::init();
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return !(strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'ngrok') !== false);
    }

    /** Phase labels valid on any host. */
    public static function phases(): array
    {
        return ['test', 'live', 'maintenance'];
    }

    /** Human-readable, user-facing phase name. */
    public static function label(string $phase): string
    {
        return [
            'test' => 'test',
            'live' => 'live',
            'maintenance' => 'maintenance',
        ][$phase] ?? 'test';
    }

    public function current(): array
    {
        $host = self::isProductionHost() ? 'production' : 'localhost';
        $phase = $this->persistedPhase($host);
        $autoLock = (bool) $this->persistedAutoLock($host);

        $testAccountsAllowed = $host === 'localhost'
            || $phase === 'test'
            || $phase === 'maintenance';
        $hardBlock = $host === 'production' && ($phase === 'live' || $autoLock);

        return [
            'host' => $host,
            'phase' => $phase,
            'phase_label' => self::label($phase),
            'auto_lock' => $autoLock,
            'test_accounts_allowed' => $testAccountsAllowed,
            'hard_block_test_accounts' => $hardBlock,
            'providers_live' => false,
        ];
    }

    /** Persist the manual phase/auto-lock choice per host (browser-independent). */
    public function setPhase(int $actorId, string $host, ?string $phase = null, ?bool $autoLock = null): array
    {
        $host = strtolower(trim($host));
        if (!in_array($host, ['localhost', 'production'], true)) {
            throw new InvalidArgumentException('Host must be localhost or production');
        }
        if ($actorId <= 0) throw new InvalidArgumentException('A valid System Administrator is required');

        if ($phase !== null) {
            $phase = strtolower(trim($phase));
            if (!in_array($phase, self::phases(), true)) {
                throw new InvalidArgumentException('Phase must be one of test, live or maintenance');
            }
        }
        if ($autoLock !== null) {
            $autoLock = (bool) $autoLock;
        }

        $isProductionHost = $host === 'production';

        $this->db->beginTransaction();
        try {
            // Upsert the singleton phase row for this host.
            $stmt = $this->db->prepare(
                "INSERT INTO environment_phases (host, phase, auto_lock, updated_by, updated_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    phase = ?, auto_lock = ?, updated_by = ?, updated_at = NOW()"
            );
            $newPhase = $phase ?? $this->persistedPhase($host);
            $newAuto = ($autoLock !== null ? $autoLock : $this->persistedAutoLock($host)) ? 1 : 0;
            $stmt->execute([
                $host, $newPhase, $newAuto, $actorId,
                $newPhase, $newAuto, $actorId,
            ]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }

        // When switching into a hard-blocked production phase, revoke test sessions.
        $now = $this->current();
        if ($now['hard_block_test_accounts']) {
            $this->db->exec(
                "UPDATE user_sessions s JOIN users u ON u.id=s.user_id
                 SET s.session_status='revoked', s.logout_time=NOW()
                 WHERE (u.account_type='test' OR u.is_test_user=1)
                   AND s.session_status='active' AND s.logout_time IS NULL"
            );
            $this->db->exec(
                "DELETE rt FROM refresh_tokens rt JOIN users u ON u.id=rt.user_id
                 WHERE u.account_type='test' OR u.is_test_user=1"
            );
        }

        Logger::audit('environment_phase_changed', 'system', null, 'Environment phase changed.', [
            'host' => $host, 'phase' => ($phase ?? $newPhase), 'auto_lock' => $newAuto,
            'updated_by' => $actorId,
        ]);
        return $this->current();
    }

    private function persistedPhase(string $host): string
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT phase FROM environment_phases WHERE host=? LIMIT 1'
            );
            $stmt->execute([$host]);
            $phase = $stmt->fetchColumn();
            if ($phase && in_array($phase, self::phases(), true)) {
                return (string) $phase;
            }
        } catch (\Throwable $error) {
            // fall back to defaults if the table has not been migrated yet
        }
        return $host === 'localhost' ? 'test' : 'live';
    }

    private function persistedAutoLock(string $host): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT auto_lock FROM environment_phases WHERE host=? LIMIT 1'
            );
            $stmt->execute([$host]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $error) {
            return false;
        }
    }

    /** Number of test accounts currently deployed, for the env-control card. */
    public function testInventory(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT
                    SUM(u.account_type='test' OR u.is_test_user=1) AS test_accounts,
                    COUNT(*) AS total_accounts
                 FROM users u"
            );
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $error) {
            return [];
        }
    }
}