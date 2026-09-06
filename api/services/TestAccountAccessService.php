<?php

namespace App\API\Services;

use App\Config\Config;
use App\Database\Database;
use DomainException;
use InvalidArgumentException;
use PDO;

/** Owns temporary test-account access and the request's live/test boundary. */
final class TestAccountAccessService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        // Config initialization also applies the school's Africa/Nairobi
        // timezone before any grant date is parsed.
        self::environment();
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public static function environment(): string
    {
        $environment = strtolower((string) Config::getEnvironment());
        return in_array($environment, ['development', 'staging', 'production'], true)
            ? $environment
            : 'development';
    }

    public function contextForUser(int $userId): ?array
    {
        $this->expireDueGrants($userId);
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.status, u.is_test_user, u.account_type, u.data_scope,
                    g.id AS grant_id, g.purpose AS test_access_purpose,
                    g.starts_at AS test_access_starts_at,
                    g.expires_at AS test_access_expires_at,
                    g.status AS test_access_status
             FROM users u
             LEFT JOIN test_account_access_grants g ON g.id = (
                 SELECT tg.id
                 FROM test_account_access_grants tg
                 WHERE tg.user_id = u.id
                   AND tg.environment = ?
                   AND tg.status IN ('scheduled','active')
                   AND tg.revoked_at IS NULL
                 ORDER BY tg.expires_at DESC, tg.id DESC
                 LIMIT 1
             )
             WHERE u.id = ?
             LIMIT 1"
        );
        $stmt->execute([self::environment(), $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $isTest = (int) $row['is_test_user'] === 1 || $row['account_type'] === 'test';

        $phase = (new EnvironmentPhaseService($this->db))->current();
        // On the localhost host, any phase is a dev workspace: test accounts are
        // always allowed and need no grant. Only the production host gates access.
        $onLocalhost = $phase['host'] === 'localhost';
        $grantActive = !empty($row['grant_id'])
            && $row['test_access_status'] === 'active'
            && strtotime((string) $row['test_access_starts_at']) <= time()
            && strtotime((string) $row['test_access_expires_at']) > time();

        $row['is_test_user'] = $isTest ? 1 : 0;
        $row['data_scope'] = $isTest ? 'test' : 'live';
        $row['test_access_active'] = $grantActive;
        $row['test_access_required'] = $isTest && !$onLocalhost && $phase['test_accounts_allowed'];
        $row['operating_mode'] = $phase['phase'];
        $row['environment_host'] = $phase['host'];
        $row['access_allowed'] = $onLocalhost
            || !$isTest
            || ($phase['test_accounts_allowed'] && $grantActive);
        return $row;
    }

    public function requireAccess(int $userId): array
    {
        $context = $this->contextForUser($userId);
        if (!$context) throw new DomainException('User account not found');
        if (($context['status'] ?? '') !== 'active') {
            throw new DomainException('This account is not active');
        }
        if (!$context['access_allowed']) {
            $this->revokeSessions($userId);
            if (($context['environment_host'] ?? '') === 'production'
                && !empty($context['is_test_user'])
                && ($context['operating_mode'] ?? '') === 'live'
                && !empty($context['test_access_required'])
            ) {
                throw new DomainException('Test accounts are blocked while the production release phase is live');
            }
            throw new DomainException('Temporary test access has expired or has not been approved');
        }
        return $context;
    }

    public function grant(
        int $userId,
        string $purpose,
        string $startsAt,
        string $expiresAt,
        int $approvedBy
    ): array {
        if ($userId <= 0 || $approvedBy <= 0) {
            throw new InvalidArgumentException('A valid test user and approver are required');
        }
        $purpose = trim($purpose);
        if ($purpose === '') throw new InvalidArgumentException('A testing purpose is required');
        $start = strtotime($startsAt);
        $expiry = strtotime($expiresAt);
        if ($start === false || $expiry === false || $expiry <= $start || $expiry <= time()) {
            throw new InvalidArgumentException('The test access expiry must be later than its start time and in the future');
        }

        $target = $this->db->prepare("SELECT id FROM users WHERE id=? AND (is_test_user=1 OR account_type='test')");
        $target->execute([$userId]);
        if (!$target->fetchColumn()) throw new DomainException('Temporary access can only be granted to a test account');

        $environment = self::environment();
        $status = $start <= time() ? 'active' : 'scheduled';
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE test_account_access_grants
                 SET status='revoked', revoked_at=NOW(), revoked_by=?,
                     revocation_reason='Superseded by a new access grant'
                 WHERE user_id=? AND environment=?
                   AND status IN ('scheduled','active') AND revoked_at IS NULL"
            )->execute([$approvedBy, $userId, $environment]);
            $stmt = $this->db->prepare(
                "INSERT INTO test_account_access_grants
                    (user_id,environment,purpose,starts_at,expires_at,status,approved_by)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $userId, $environment, $purpose,
                date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $expiry),
                $status, $approvedBy,
            ]);
            $grantId = (int) $this->db->lastInsertId();
            if ($ownsTransaction) $this->db->commit();
            Logger::audit('test_access_granted', 'user', $userId, 'Temporary test-account access granted.', [
                'grant_id' => $grantId, 'environment' => $environment,
                'starts_at' => date('c', $start), 'expires_at' => date('c', $expiry),
                'approved_by' => $approvedBy, 'purpose' => $purpose,
            ]);
            return $this->contextForUser($userId) ?? [];
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /**
     * Grant one temporary test-access window to several test accounts at once.
     * Non-test accounts are skipped and reported; every supplied user_id must be
     * an integer. Existing scheduled/active grants on the same accounts are
     * superseded, mirroring the single-account grant() behaviour.
     */
    public function grantBulk(
        array $userIds,
        string $purpose,
        string $startsAt,
        string $expiresAt,
        int $approvedBy
    ): array {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));
        if (empty($ids)) throw new InvalidArgumentException('At least one valid test user is required');
        if ($approvedBy <= 0) throw new InvalidArgumentException('A valid approver is required');

        $purpose = trim($purpose);
        if ($purpose === '') throw new InvalidArgumentException('A testing purpose is required');
        $start = strtotime($startsAt);
        $expiry = strtotime($expiresAt);
        if ($start === false || $expiry === false || $expiry <= $start || $expiry <= time()) {
            throw new InvalidArgumentException('The test access expiry must be later than its start time and in the future');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $target = $this->db->prepare(
            "SELECT u.id, COUNT(g.id) AS has_live_grant
             FROM users u
             LEFT JOIN test_account_access_grants g ON g.user_id = u.id AND g.environment = ?
                 AND g.status IN ('scheduled','active') AND g.revoked_at IS NULL
             WHERE u.id IN ($placeholders)
               AND (u.is_test_user=1 OR u.account_type='test')
             GROUP BY u.id"
        );
        $bind = [self::environment()];
        foreach ($ids as $id) $bind[] = $id;
        $target->execute($bind);
        $rows = $target->fetchAll(PDO::FETCH_ASSOC);
        $validIds = array_column($rows, 'id');

        $granted = [];
        $skipped = array_values(array_diff($ids, array_map('intval', $validIds)));
        if (empty($validIds)) {
            return ['granted' => [], 'skipped' => $skipped];
        }

        $status = $start <= time() ? 'active' : 'scheduled';
        $placeholdersValid = implode(',', array_fill(0, count($validIds), '?'));
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE test_account_access_grants
                 SET status='revoked', revoked_at=NOW(), revoked_by=?,
                     revocation_reason='Superseded by a bulk access grant'
                 WHERE user_id IN ($placeholdersValid) AND environment=?
                   AND status IN ('scheduled','active') AND revoked_at IS NULL"
            )->execute(array_merge(array_map('intval', $validIds), [self::environment()]));

            $stmt = $this->db->prepare(
                "INSERT INTO test_account_access_grants
                    (user_id,environment,purpose,starts_at,expires_at,status,approved_by)
                 VALUES (?,?,?,?,?,?,?)"
            );
            foreach ($validIds as $grantUserId) {
                $stmt->execute([
                    $grantUserId, self::environment(), $purpose,
                    date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $expiry),
                    $status, $approvedBy,
                ]);
                $granted[] = (int) $this->db->lastInsertId();
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }

        Logger::audit('test_access_bulk_granted', 'user', ($validIds[0] ?? null), 'Bulk temporary test-account access granted.', [
            'environment' => self::environment(), 'user_ids' => array_map('intval', $validIds),
            'grant_ids' => $granted, 'skipped_ids' => $skipped,
            'starts_at' => date('c', $start), 'expires_at' => date('c', $expiry),
            'approved_by' => $approvedBy, 'purpose' => $purpose,
        ]);
        return ['granted' => $validIds, 'skipped' => $skipped];
    }

    /**
     * Revoke the active/scheduled test-access grant for many test accounts at
     * once and terminate their sessions. Returns the touched user IDs.
     */
    public function revokeBulk(array $userIds, int $revokedBy, string $reason): array
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));
        if (empty($ids)) throw new InvalidArgumentException('At least one valid test user is required');

        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException('A revocation reason is required');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "UPDATE test_account_access_grants
             SET status='revoked', revoked_at=NOW(), revoked_by=?, revocation_reason=?
             WHERE user_id IN ($placeholders) AND environment=?
               AND status IN ('scheduled','active') AND revoked_at IS NULL"
        );
        $stmt->execute(array_merge([$revokedBy, $reason], array_map('intval', $ids), [self::environment()]));

        foreach ($ids as $userId) $this->revokeSessions((int) $userId);

        Logger::audit('test_access_bulk_revoked', 'user', ($ids[0] ?? null), 'Bulk temporary test-account access revoked.', [
            'environment' => self::environment(), 'user_ids' => array_map('intval', $ids),
            'revoked_by' => $revokedBy, 'reason' => $reason,
        ]);
        return $ids;
    }

    public function revoke(int $userId, int $revokedBy, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException('A revocation reason is required');
        $stmt = $this->db->prepare(
            "UPDATE test_account_access_grants
             SET status='revoked', revoked_at=NOW(), revoked_by=?, revocation_reason=?
             WHERE user_id=? AND environment=?
               AND status IN ('scheduled','active') AND revoked_at IS NULL"
        );
        $stmt->execute([$revokedBy, $reason, $userId, self::environment()]);
        $this->revokeSessions($userId);
        Logger::audit('test_access_revoked', 'user', $userId, 'Temporary test-account access revoked.', [
            'environment' => self::environment(), 'revoked_by' => $revokedBy, 'reason' => $reason,
        ]);
    }

    public function expireDueGrants(?int $userId = null): int
    {
        $params = [self::environment()];
        $userSql = '';
        if ($userId !== null) {
            $userSql = ' AND user_id=?';
            $params[] = $userId;
        }
        $activateParams = $params;
        $activate = $this->db->prepare(
            "UPDATE test_account_access_grants
             SET status='active'
             WHERE environment=? AND status='scheduled'
               AND starts_at <= NOW() AND expires_at > NOW(){$userSql}"
        );
        $activate->execute($activateParams);

        $stmt = $this->db->prepare(
            "UPDATE test_account_access_grants
             SET status='expired'
             WHERE environment=? AND status IN ('scheduled','active')
               AND expires_at <= NOW(){$userSql}"
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function revokeSessions(int $userId): void
    {
        $this->db->prepare(
            "UPDATE user_sessions SET session_status='expired', logout_time=COALESCE(logout_time,NOW())
             WHERE user_id=? AND session_status='active' AND logout_time IS NULL"
        )->execute([$userId]);
        $this->db->prepare(
            'UPDATE refresh_tokens SET revoked_at=COALESCE(revoked_at,NOW()) WHERE user_id=? AND revoked_at IS NULL'
        )->execute([$userId]);
    }
}
