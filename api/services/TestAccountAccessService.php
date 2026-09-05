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
        $grantActive = !empty($row['grant_id'])
            && $row['test_access_status'] === 'active'
            && strtotime((string) $row['test_access_starts_at']) <= time()
            && strtotime((string) $row['test_access_expires_at']) > time();

        $row['is_test_user'] = $isTest ? 1 : 0;
        $row['data_scope'] = $isTest ? 'test' : 'live';
        $row['test_access_active'] = $grantActive;
        $row['test_access_required'] = $isTest && self::environment() !== 'development';
        $mode = (new OperatingModeService($this->db))->current();
        $row['operating_mode'] = $mode['mode'];
        $row['access_allowed'] = (!$row['test_access_required'] || $grantActive)
            && (!$isTest || $mode['test_accounts_allowed']);
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
            if (($context['operating_mode'] ?? '') === 'live' && !empty($context['is_test_user'])) {
                throw new DomainException('Test accounts are blocked while production is in live mode');
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
