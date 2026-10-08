<?php
/**
 * Two-Factor Authentication Service
 *
 * Supports three 2FA methods:
 *  - TOTP (Time-based One-Time Password) for authenticator apps
 *  - Email OTP (6-digit code sent via PHPMailer/SMTP)
 *  - SMS OTP (6-digit code sent via Africa's Talking)
 *
 * Also manages backup recovery codes (10 one-time-use codes).
 *
 * @package App\API\Services
 */

namespace App\API\Services;

use App\Database\Database;
use PDO;

class TwoFactorService
{
    private PDO $db;

    /** TOTP defaults (RFC 6238) */
    private const TOTP_PERIOD = 30;      // seconds per code
    private const TOTP_DIGITS = 6;       // code length
    private const TOTP_ALGORITHM = 'sha1';
    private const TOTP_ISSUER = 'Kingsway Preparatory School';

    /** OTP delivery defaults */
    private const OTP_LENGTH = 6;
    private const OTP_TTL_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;

    /** Backup codes */
    private const BACKUP_CODE_COUNT = 10;
    private const BACKUP_CODE_LENGTH = 8;

    /**
     * Methods that can actually deliver or verify a code during login.
     *
     * `users.two_factor_method` is an ENUM that also carries the sentinel
     * value 'none' (meaning "flag set, but no usable method"). That sentinel
     * is truthy in PHP, so it must never be treated as a required method: it
     * would gate the login and then fail to store a challenge, because
     * `user_two_factor_challenges.method` does not accept 'none'.
     */
    public const DELIVERABLE_METHODS = ['totp', 'email', 'sms', 'whatsapp', 'passkey'];

    /** Fallback used when a stored method is missing or not deliverable. */
    private const FALLBACK_METHOD = 'email';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    // ========================================================================
    // TOTP — Authenticator App
    // ========================================================================

    /**
     * Generate a random TOTP secret (Base32-encoded, 20 bytes).
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        return $this->base32Encode($bytes);
    }

    /**
     * Build the otpauth:// URI for QR code generation.
     */
    public function getTOTPUri(string $secret, string $email, ?string $label = null): string
    {
        $label = $label ?? $email;
        $issuer = rawurlencode(self::TOTP_ISSUER);
        $account = rawurlencode($label);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => self::TOTP_ISSUER,
            'algorithm' => 'SHA1',
            'digits' => self::TOTP_DIGITS,
            'period' => self::TOTP_PERIOD,
        ]);
        return "otpauth://totp/{$issuer}:{$account}?{$params}";
    }

    /**
     * Verify a TOTP code against a secret.
     * Allows ±1 time step drift (±30 seconds).
     */
    public function verifyTOTP(string $secret, string $code): bool
    {
        $decodedSecret = $this->base32Decode($secret);
        if ($decodedSecret === false) return false;

        $timeSlice = floor(time() / self::TOTP_PERIOD);

        // Check current, previous, and next time step
        for ($offset = -1; $offset <= 1; $offset++) {
            $calculatedCode = $this->generateTOTPCode(
                $decodedSecret,
                $timeSlice + $offset
            );
            if (hash_equals($calculatedCode, str_pad($code, self::TOTP_DIGITS, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    public function verifyUserTOTP(int $userId, string $code): bool
    {
        $stmt = $this->db->prepare("SELECT id, secret_ciphertext, last_used_timestep FROM user_two_factor_methods WHERE user_id=? AND method='totp' AND is_enabled=1 LIMIT 1");
        $stmt->execute([$userId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $secret = $row ? $this->decryptSecret($row['secret_ciphertext']) : $this->getSecret($userId);
        if (!$secret) return false;
        $slice = (int) floor(time() / self::TOTP_PERIOD);
        $normalized = str_pad(preg_replace('/\D/', '', $code), self::TOTP_DIGITS, '0', STR_PAD_LEFT);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->generateTOTPCode($this->base32Decode($secret), $slice + $offset), $normalized)) {
                if ($row && (int) ($row['last_used_timestep'] ?? -1) >= ($slice + $offset)) return false;
                if ($row) $this->db->prepare("UPDATE user_two_factor_methods SET last_used_timestep=? WHERE id=? AND (last_used_timestep IS NULL OR last_used_timestep<?)")->execute([$slice + $offset, $row['id'], $slice + $offset]);
                return true;
            }
        }
        return false;
    }

    private function encryptionKey(): string
    {
        $configured = defined('TFA_ENCRYPTION_KEY') ? (string) TFA_ENCRYPTION_KEY : '';
        if ($configured === '') throw new \RuntimeException('TFA_ENCRYPTION_KEY is not configured');
        // Decode only a complete 256-bit hexadecimal key. Human-readable
        // secrets can happen to contain hex characters only; those must still
        // be derived through SHA-256 rather than decoded into a short key.
        $key = strlen($configured) === 64 && ctype_xdigit($configured)
            ? hex2bin($configured)
            : hash('sha256', $configured, true);
        if ($key === false || strlen($key) !== 32) throw new \RuntimeException('Invalid TFA encryption key');
        return $key;
    }

    public function encryptSecret(string $secret): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($secret, 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new \RuntimeException('Unable to encrypt 2FA secret');
        return base64_encode($iv . $tag . $cipher);
    }

    public function decryptSecret(?string $encoded): ?string
    {
        if (!$encoded) return null;
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) return null;
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    /**
     * Generate a TOTP code for a given time slice (RFC 6238).
     */
    private function generateTOTPCode(string $secret, int $timeSlice): string
    {
        // Pack time into 8-byte big-endian
        $time = pack('N*', 0) . pack('N*', $timeSlice);

        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secret, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $binary = ((ord($hmac[$offset]) & 0x7F) << 24)
                | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
                | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
                | (ord($hmac[$offset + 3]) & 0xFF);

        $otp = $binary % pow(10, self::TOTP_DIGITS);
        return str_pad((string) $otp, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    // ========================================================================
    // Email / SMS OTP
    // ========================================================================

    /**
     * Generate and store an OTP for the given user and method.
     * Returns the plaintext code (to be sent by the delivery service).
     */
    public function generateOTP(int $userId, string $method, string $otpType = 'login'): ?string
    {
        if (!in_array($method, ['email', 'sms', 'whatsapp'], true)) return null;

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
        // Serialize requests per user. Without this row lock, two concurrent
        // resend clicks could both pass the cooldown check and create codes.
        $lock = $this->db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
        $lock->execute([$userId]);
        if (!$lock->fetchColumn()) throw new \RuntimeException('User account not found.');

        $recent = $this->db->prepare(
            "SELECT COUNT(*) AS window_count,
                    TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS seconds_since_last
             FROM user_2fa_otp_sessions
             WHERE user_id=? AND otp_type=? AND method=?
               AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $recent->execute([$userId, $otpType, $method]);
        $rate = $recent->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($rate['seconds_since_last'] !== null && (int)$rate['seconds_since_last'] < 60) {
            throw new \RuntimeException('Please wait 60 seconds before requesting another verification code.', 429);
        }
        if ((int)($rate['window_count'] ?? 0) >= 5) {
            throw new \RuntimeException('Too many verification codes requested. Try again in 15 minutes.', 429);
        }

        $code = str_pad(
            (string) random_int(0, pow(10, self::OTP_LENGTH) - 1),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT
        );

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // Invalidate any previous unverified OTPs for this user/type
        $this->db->prepare(
            "UPDATE user_2fa_otp_sessions
             SET verified = 1
             WHERE user_id = ? AND otp_type = ? AND verified = 0"
        )->execute([$userId, $otpType]);

        $stmt = $this->db->prepare(
            "INSERT INTO user_2fa_otp_sessions
             (user_id, otp_code, otp_type, method, otp_expires_at, ip_address)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL " . self::OTP_TTL_MINUTES . " MINUTE), ?)"
        );
        $stmt->execute([
            $userId,
            password_hash($code, PASSWORD_DEFAULT),
            $otpType,
            $method,
            $ip,
        ]);

        if ($ownsTransaction) $this->db->commit();
        return $code;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function invalidateLatestOTP(int $userId, string $otpType, string $method): void
    {
        $this->db->prepare(
            "UPDATE user_2fa_otp_sessions SET verified=1
             WHERE user_id=? AND otp_type=? AND method=? AND verified=0"
        )->execute([$userId, $otpType, $method]);
    }

    /**
     * Verify an OTP code. Returns true on success.
     */
    public function verifyOTP(int $userId, string $code, string $otpType = 'login'): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id, otp_code, attempts
             FROM user_2fa_otp_sessions
             WHERE user_id = ? AND otp_type = ? AND verified = 0
             AND otp_expires_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $otpType]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) return false;

        if ((int) $session['attempts'] >= self::OTP_MAX_ATTEMPTS) {
            // Lock this OTP
            $this->db->prepare(
                "UPDATE user_2fa_otp_sessions SET verified = 1 WHERE id = ?"
            )->execute([$session['id']]);
            return false;
        }

        $attempts = (int) $session['attempts'] + 1;
        $this->db->prepare(
            "UPDATE user_2fa_otp_sessions SET attempts = ? WHERE id = ?"
        )->execute([$attempts, $session['id']]);

        if (password_verify($code, $session['otp_code'])) {
            $consume = $this->db->prepare(
                "UPDATE user_2fa_otp_sessions SET verified = 1 WHERE id = ? AND verified = 0"
            );
            $consume->execute([$session['id']]);
            return $consume->rowCount() === 1;
        }

        return false;
    }

    // ========================================================================
    // Backup Codes
    // ========================================================================

    /**
     * Generate backup codes, store hashes, return plaintext codes.
     */
    public function generateBackupCodes(int $userId): array
    {
        $codes = [];
        $stmt = $this->db->prepare(
            "INSERT INTO user_2fa_backup_codes (user_id, code_hash) VALUES (?, ?)"
        );

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            // Format: XXXX-XXXX (8 chars with dash)
            $raw = bin2hex(random_bytes(4));
            $formatted = strtoupper(substr($raw, 0, 4) . '-' . substr($raw, 4, 4));
            $codes[] = $formatted;
            $stmt->execute([$userId, password_hash($formatted, PASSWORD_DEFAULT)]);
        }

        // Mark when backup codes were generated
        $this->db->prepare(
            "UPDATE users SET backup_codes_generated_at = NOW() WHERE id = ?"
        )->execute([$userId]);

        return $codes;
    }

    /**
     * Verify and consume a backup code. Returns true on success.
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id, code_hash FROM user_2fa_backup_codes
             WHERE user_id = ? AND used_at IS NULL"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (password_verify(strtoupper(trim($code)), $row['code_hash'])) {
                $this->db->prepare(
                    "UPDATE user_2fa_backup_codes SET used_at = NOW() WHERE id = ?"
                )->execute([$row['id']]);
                return true;
            }
        }
        return false;
    }

    /**
     * Get count of remaining backup codes.
     */
    public function getBackupCodeCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM user_2fa_backup_codes
             WHERE user_id = ? AND used_at IS NULL"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // ========================================================================
    // 2FA Enable / Disable / Status
    // ========================================================================

    /**
     * Check if 2FA is enabled for a user.
     */
    public function is2FAEnabled(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT two_factor_enabled FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get user's 2FA status.
     */
    public function get2FAStatus(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT two_factor_enabled, two_factor_method, two_factor_verified_at,
                    backup_codes_generated_at
             FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $methods = $this->db->prepare("SELECT method, label, is_primary, verified_at FROM user_two_factor_methods WHERE user_id = ? AND is_enabled = 1 ORDER BY is_primary DESC, id ASC");
        $methods->execute([$userId]);
        $flagEnabled = (bool) ($row['two_factor_enabled'] ?? 0);
        $storedMethod = strtolower(trim((string)($row['two_factor_method'] ?? '')));
        $deliverable = in_array($storedMethod, self::DELIVERABLE_METHODS, true);
        return [
            // `two_factor_enabled` is the authoritative switch: only 0 turns the
            // second-factor step off. `method_configured` reports separately
            // whether a usable channel exists, so the interface can prompt for
            // enrolment when the step is on but nothing can deliver a code.
            'enabled' => $flagEnabled,
            'method_configured' => $deliverable,
            'method' => $deliverable ? $storedMethod : null,
            'methods' => $methods->fetchAll(PDO::FETCH_ASSOC),
            'verified_at' => $row['two_factor_verified_at'] ?? null,
            'backup_codes_generated_at' => $row['backup_codes_generated_at'] ?? null,
            'backup_codes_remaining' => $this->getBackupCodeCount($userId),
        ];
    }

    /**
     * Enable 2FA for a user after they verify their first code.
     */
    public function enable2FA(int $userId, string $method, string $secret): bool
    {
        if (!in_array($method, ['totp', 'email', 'sms', 'whatsapp'], true)) return false;

        $ciphertext = $method === 'totp' ? $this->encryptSecret($secret) : null;
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE user_two_factor_methods SET is_primary = 0 WHERE user_id = ?")->execute([$userId]);
            $stmt = $this->db->prepare("INSERT INTO user_two_factor_methods (user_id, method, secret_ciphertext, is_primary, verified_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE secret_ciphertext = VALUES(secret_ciphertext), is_enabled = 1, is_primary = 1, verified_at = NOW()");
            $stmt->execute([$userId, $method, $ciphertext]);
            $legacy = $ciphertext ?: null;
            $this->db->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_method = ?, two_factor_verified_at = NOW() WHERE id = ?")->execute([$legacy, $method, $userId]);
            $this->audit($userId, 'factor_enabled', $method, true);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    /**
     * Disable 2FA for a user (requires password verification first).
     */
    public function disable2FA(int $userId): bool
    {
        $this->db->prepare(
            "DELETE FROM user_2fa_backup_codes WHERE user_id = ?"
        )->execute([$userId]);

        $this->db->prepare(
            "DELETE FROM user_2fa_otp_sessions WHERE user_id = ?"
        )->execute([$userId]);

        $stmt = $this->db->prepare(
            "UPDATE users
             SET two_factor_secret = NULL,
                 two_factor_enabled = 0,
                 two_factor_method = NULL,
                 two_factor_verified_at = NULL,
                 backup_codes_generated_at = NULL
             WHERE id = ?"
        );
        return $stmt->execute([$userId]);
    }

    /**
     * Get the TOTP secret for a user (encrypted in production).
     */
    public function getSecret(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT two_factor_secret FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn() ?: null;
        if (!$value) return null;
        return $this->decryptSecret($value) ?: $value; // compatibility for pre-hardening records
    }

    public function createLoginChallenge(int $userId, string $method): string
    {
        // `user_two_factor_challenges.method` is a strict ENUM. Storing a
        // non-member (for example the 'none' sentinel) aborts the INSERT, so
        // the challenge is never persisted and every follow-up request reports
        // "Invalid or expired 2FA challenge". Normalise before writing.
        $method = strtolower(trim($method));
        if (!in_array($method, self::DELIVERABLE_METHODS, true)) {
            $method = self::FALLBACK_METHOD;
        }
        $raw = bin2hex(random_bytes(32));
        $this->db->prepare("UPDATE user_two_factor_challenges SET status='expired' WHERE user_id=? AND status='pending'")
            ->execute([$userId]);
        $stmt = $this->db->prepare("INSERT INTO user_two_factor_challenges (user_id, challenge_hash, method, expires_at, ip_address, user_agent) VALUES (?, ?, ?, " . $this->challengeExpiryExpression() . ", ?, ?)");
        $stmt->execute([$userId, hash('sha256', $raw), $method, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512)]);
        return $raw;
    }

    /**
     * Expiry expression for a login challenge. It is expressed in SQL so the
     * deadline uses the database clock rather than the application clock.
     */
    protected function challengeExpiryExpression(): string
    {
        return 'DATE_ADD(NOW(), INTERVAL ' . self::OTP_TTL_MINUTES . ' MINUTE)';
    }

    private function challenge(string $raw, ?int $userId = null, array $statuses = ['pending']): ?array
    {
        $marks = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT * FROM user_two_factor_challenges WHERE challenge_hash = ? AND status IN ($marks) AND expires_at > NOW()";
        $params = array_merge([hash('sha256', $raw)], $statuses);
        if ($userId !== null) { $sql .= ' AND user_id = ?'; $params[] = $userId; }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->db->prepare($sql); $stmt->execute($params); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getLoginChallenge(string $raw, ?int $userId = null): ?array { return $this->challenge($raw, $userId); }

    public function markChallengeVerified(string $raw, int $userId): bool
    {
        $row = $this->challenge($raw, $userId); if (!$row) return false;
        $update = $this->db->prepare("UPDATE user_two_factor_challenges SET status='verified', verified_at=NOW() WHERE id=? AND status='pending'");
        $update->execute([$row['id']]);
        if ($update->rowCount() !== 1) return false;
        $this->audit($userId, 'challenge_verified', $row['method'], true, (int) $row['id']); return true;
    }

    public function consumeLoginChallenge(string $raw, int $userId): bool
    {
        $row = $this->challenge($raw, $userId, ['verified']); if (!$row) return false;
        $consume = $this->db->prepare("UPDATE user_two_factor_challenges SET status='consumed', consumed_at=NOW() WHERE id=? AND status='verified'");
        $consume->execute([$row['id']]);
        return $consume->rowCount() === 1;
    }

    public function registerChallengeFailure(string $raw, ?int $userId = null): void
    {
        $row = $this->challenge($raw, $userId); if (!$row) return;
        $this->db->prepare("UPDATE user_two_factor_challenges SET attempts=attempts+1, status=IF(attempts+1>=5,'locked',status) WHERE id=?")->execute([$row['id']]);
        $this->audit($row['user_id'], 'challenge_failed', $row['method'], false, (int) $row['id']);
    }

    private function audit(?int $userId, string $event, ?string $method, bool $success, ?int $challengeId = null): void
    {
        $this->db->prepare("INSERT INTO user_two_factor_audit_events (user_id,event_type,method,challenge_id,success,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)")->execute([$userId,$event,$method,$challengeId,$success?1:0,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,512)]);
    }

    // ========================================================================
    // 2FA Challenge — called during login flow
    // ========================================================================

    /**
     * Determine if a user needs 2FA verification after password login.
     * Returns the method or null if 2FA is not required.
     */
    public function getRequiredMethod(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT two_factor_enabled, two_factor_method
             FROM users WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['two_factor_enabled']) return null;

        // 'none' is the catalogued "no method" sentinel, and any value outside
        // the deliverable set cannot produce a code. Returning it would gate
        // the login on a challenge that can never be stored or delivered.
        $method = strtolower(trim((string)($row['two_factor_method'] ?? '')));
        if ($method !== '' && in_array($method, self::DELIVERABLE_METHODS, true)) return $method;

        // The stored method is unusable, but the account may still have a
        // factor enrolled. Prefer a real enrolled channel over forcing the
        // account back through setup. The flag above remains the only thing
        // that switches the second factor off.
        $enrolled = $this->db->prepare(
            "SELECT method FROM user_two_factor_methods
             WHERE user_id = ? AND is_enabled = 1
               AND method IN ('" . implode("','", self::DELIVERABLE_METHODS) . "')
             ORDER BY is_primary DESC, id ASC LIMIT 1"
        );
        $enrolled->execute([$userId]);
        $fallback = strtolower(trim((string)($enrolled->fetchColumn() ?: '')));
        return in_array($fallback, self::DELIVERABLE_METHODS, true) ? $fallback : null;
    }

    /**
     * Whether the second-factor step is active for this account.
     *
     * `users.two_factor_enabled` is the single authoritative switch: only a
     * value of 0 disables the step. When the flag is set but no method can
     * deliver a code, the step is still active and the account must enrol
     * rather than being let through on a password alone.
     */
    public function isTwoFactorStepActive(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT two_factor_enabled FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (bool) ($row['two_factor_enabled'] ?? 0);
    }

    public function getEnabledMethods(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT method FROM user_two_factor_methods WHERE user_id=? AND is_enabled=1 ORDER BY is_primary DESC, id ASC");
        $stmt->execute([$userId]);
        $methods = array_values(array_unique(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'method')));
        if (!$methods) { $primary = $this->getRequiredMethod($userId); if ($primary) $methods[] = $primary; }
        $passkeys = $this->db->prepare("SELECT COUNT(*) FROM user_passkeys WHERE user_id=?"); $passkeys->execute([$userId]);
        if ((int) $passkeys->fetchColumn() > 0) $methods[] = 'passkey';
        return $methods;
    }

    public function setPrimaryMethod(int $userId, string $method): bool
    {
        if (!in_array($method, $this->getEnabledMethods($userId), true)) return false;
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE user_two_factor_methods SET is_primary=0 WHERE user_id=?')->execute([$userId]);
            if ($method !== 'passkey') {
                $this->db->prepare('UPDATE user_two_factor_methods SET is_primary=1 WHERE user_id=? AND method=? AND is_enabled=1')->execute([$userId, $method]);
            }
            $this->db->prepare('UPDATE users SET two_factor_enabled=1,two_factor_method=?,two_factor_verified_at=NOW() WHERE id=?')->execute([$method, $userId]);
            $this->audit($userId, 'primary_method_changed', $method, true);
            $this->db->commit(); return true;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function administrativeReset(int $targetUserId, int $actorUserId): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM user_two_factor_methods WHERE user_id=? AND method<>'email'")->execute([$targetUserId]);
            $this->db->prepare(
                "INSERT INTO user_two_factor_methods(user_id,method,is_primary,is_enabled,verified_at)
                 VALUES(?,'email',1,1,NULL)
                 ON DUPLICATE KEY UPDATE is_enabled=1,is_primary=1,verified_at=NULL,
                    secret_ciphertext=NULL,last_used_timestep=NULL"
            )->execute([$targetUserId]);
            $this->db->prepare('DELETE FROM user_passkeys WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare('DELETE FROM user_passkey_challenges WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare('DELETE FROM user_2fa_backup_codes WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare('DELETE FROM user_2fa_otp_sessions WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare("UPDATE user_two_factor_challenges SET status='expired' WHERE user_id=? AND status='pending'")->execute([$targetUserId]);
            // An MFA reset is an account-recovery event. Existing sessions must
            // not survive it, otherwise a lost or stolen device remains logged in.
            $this->db->prepare('UPDATE refresh_tokens SET revoked_at=COALESCE(revoked_at,NOW()) WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare("UPDATE user_sessions SET session_status='logged_out',logout_time=COALESCE(logout_time,NOW()) WHERE user_id=? AND session_status='active'")->execute([$targetUserId]);
            $this->db->prepare("UPDATE users SET two_factor_enabled=1,two_factor_method='email',two_factor_secret=NULL,two_factor_verified_at=NULL,backup_codes_generated_at=NULL WHERE id=?")->execute([$targetUserId]);
            $this->db->prepare("INSERT INTO user_two_factor_audit_events(user_id,event_type,method,success,ip_address,user_agent,metadata) VALUES(?,'administrative_reset','email',1,?,?,?)")
                ->execute([$targetUserId,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,512),json_encode(['actor_user_id'=>$actorUserId])]);
            $this->db->commit();
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    /** Disable every enrolled factor when a System Administrator manages recovery. */
    public function administrativeDisable(int $targetUserId, int $actorUserId): void
    {
        if ($this->is2FARequiredByPolicy($targetUserId)) {
            throw new \DomainException('2FA cannot be disabled while this account has a role that requires it.');
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM user_two_factor_methods WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare('DELETE FROM user_passkeys WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare('DELETE FROM user_passkey_challenges WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare('DELETE FROM user_2fa_backup_codes WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare('DELETE FROM user_2fa_otp_sessions WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare("UPDATE user_two_factor_challenges SET status='expired' WHERE user_id=? AND status='pending'")->execute([$targetUserId]);
            $this->db->prepare('UPDATE refresh_tokens SET revoked_at=COALESCE(revoked_at,NOW()) WHERE user_id=?')->execute([$targetUserId]);
            $this->db->prepare("UPDATE user_sessions SET session_status='logged_out',logout_time=COALESCE(logout_time,NOW()) WHERE user_id=? AND session_status='active'")->execute([$targetUserId]);
            $this->db->prepare("UPDATE users SET two_factor_enabled=0,two_factor_method='none',two_factor_secret=NULL,two_factor_verified_at=NULL,backup_codes_generated_at=NULL WHERE id=?")->execute([$targetUserId]);
            $this->db->prepare("INSERT INTO user_two_factor_audit_events(user_id,event_type,method,success,ip_address,user_agent,metadata) VALUES(?,'administrative_disable','none',1,?,?,?)")
                ->execute([$targetUserId, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512), json_encode(['actor_user_id' => $actorUserId])]);
            $this->db->commit();
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function setChallengeMethod(string $raw, int $userId, string $method): bool
    {
        if (!in_array($method, $this->getEnabledMethods($userId), true)) return false;
        $row = $this->challenge($raw, $userId); if (!$row) return false;
        return $this->db->prepare("UPDATE user_two_factor_challenges SET method=? WHERE id=? AND status='pending'")->execute([$method, $row['id']]);
    }

    /**
     * Check if the user is forced to use 2FA by school policy.
     */
    public function is2FARequiredByPolicy(int $userId): bool
    {
        // Check if any of the user's roles are in the forced-2FA list
        $stmt = $this->db->prepare(
            "SELECT setting_value FROM school_settings
             WHERE setting_key = 'security.require_2fa_roles'"
        );
        $stmt->execute();
        $requiredRoles = $stmt->fetchColumn();

        if (!$requiredRoles) return false;

        $roleIds = array_map('intval', array_filter(explode(',', $requiredRoles)));
        if (!$roleIds) return false;

        $stmt = $this->db->prepare(
            "SELECT ur.role_id FROM user_roles ur
             WHERE ur.user_id = ? AND ur.role_id IN (" .
             implode(',', array_fill(0, count($roleIds), '?')) . ")"
        );
        $stmt->execute(array_merge([$userId], $roleIds));

        return $stmt->fetchColumn() !== false;
    }

    // ========================================================================
    // Supporting operations (password check, backup rotation, pending setup)
    // ========================================================================

    /**
     * Verify a user's password against the stored hash.
     */
    public function verifyUserPassword(int $userId, string $password): bool
    {
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        return (bool) $hash && password_verify($password, $hash);
    }

    /**
     * Rotate backup codes: delete existing codes and generate a fresh set.
     *
     * @return string[] New backup codes.
     */
    public function rotateBackupCodes(int $userId): array
    {
        $stmt = $this->db->prepare("DELETE FROM user_2fa_backup_codes WHERE user_id = ?");
        $stmt->execute([$userId]);

        return $this->generateBackupCodes($userId);
    }

    /**
     * Fetch a user's contact channels for OTP delivery.
     *
     * @return array ['email' => ?string, 'phone_1' => ?string]
     */
    public function getUserContact(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT email, phone AS phone_1 FROM " . ReadReplicaService::qualifiedRef("person_directory") . " WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'email'   => $row['email'] ?? null,
            'phone_1' => $row['phone_1'] ?? null,
        ];
    }

    /** Store an encrypted pending TOTP setup secret with a 15-minute TTL. */
    public function storePendingSecret(int $userId, string $secret, string $method): void
    {
        if ($method !== 'totp') return;

        $stmt = $this->db->prepare(
            "DELETE FROM user_2fa_otp_sessions WHERE user_id = ? AND otp_type = 'setup_pending'"
        );
        $stmt->execute([$userId]);

        $stmt = $this->db->prepare(
            "INSERT INTO user_2fa_otp_sessions
             (user_id, otp_code, otp_type, method, otp_expires_at)
             VALUES (?, ?, 'setup_pending', 'totp', DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
        );
        $stmt->execute([$userId, $this->encryptSecret($secret)]);
    }

    /**
     * Retrieve a pending 2FA setup state.
     *
     * @return array|null ['secret' => ?string, 'method' => string]
     */
    public function getPendingSecret(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT otp_code AS secret, method FROM user_2fa_otp_sessions
             WHERE user_id = ? AND otp_type = 'setup_pending'
             AND otp_expires_at > NOW() ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['method'] === 'totp') {
            $decrypted = $this->decryptSecret((string)$row['secret']);
            // Compatibility with setup rows created before encrypted pending storage.
            $row['secret'] = $decrypted ?: $row['secret'];
            return $row;
        }

        $stmt = $this->db->prepare(
            "SELECT method FROM user_2fa_otp_sessions
             WHERE user_id = ? AND otp_type = 'setup'
             AND otp_expires_at > NOW() ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return ['secret' => null, 'method' => $row['method']];
        }

        return null;
    }

    /**
     * Clear pending 2FA setup state for a user.
     */
    public function clearPendingSecret(int $userId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_2fa_otp_sessions
             WHERE user_id = ? AND otp_type IN ('setup_pending', 'setup')"
        );
        $stmt->execute([$userId]);
    }

    // ========================================================================
    // Base32 helpers (RFC 4648)
    // ========================================================================

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        // Pad to multiple of 5
        $bits = str_pad($bits, ceil(strlen($bits) / 5) * 5, '0');

        $result = '';
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $index = bindec(substr($bits, $i, 5));
            $result .= $alphabet[$index];
        }
        return $result;
    }

    private function base32Decode(string $input): ?string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = rtrim(strtoupper($input), '=');

        $bits = '';
        for ($i = 0; $i < strlen($input); $i++) {
            $pos = strpos($alphabet, $input[$i]);
            if ($pos === false) return null;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $result .= chr(bindec(substr($bits, $i, 8)));
        }
        return $result;
    }

    /** Mark the account passkey-enabled after a successful first registration. */
    public function enablePasskeyOnAccount(int $userId): void
    {
        $stmt = $this->db->prepare("UPDATE users SET two_factor_enabled=1, two_factor_method='passkey', two_factor_verified_at=NOW() WHERE id=?");
        $stmt->execute([$userId]);
    }


}
