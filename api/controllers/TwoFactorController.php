<?php
/**
 * Two-Factor Authentication Controller
 *
 * Thin endpoint layer; all business logic lives in TwoFactorService
 * (TOTP/OTP, backup codes) and OTPDeliveryService (email/SMS delivery).
 *
 * API endpoints:
 *   POST /api/2fa/status          — Get 2FA status for current user
 *   POST /api/2fa/setup/totp      — Start TOTP setup (returns secret + QR URL)
 *   POST /api/2fa/setup/verify    — Verify first TOTP/OTP code to enable 2FA
 *   POST /api/2fa/setup/email     — Start email OTP setup (sends code)
 *   POST /api/2fa/setup/sms       — Start SMS OTP setup (sends code)
 *   POST /api/2fa/disable         — Disable 2FA (requires password + code)
 *   POST /api/2fa/backup/generate — Generate new backup codes
 *   POST /api/2fa/backup/verify   — Verify a backup code (used during login)
 *   POST /api/2fa/challenge       — Send OTP for login challenge
 *   POST /api/2fa/verify          — Verify OTP during login
 *
 * @package App\API\Controllers
 */

namespace App\API\Controllers;

use App\API\Services\TwoFactorService;
use App\API\Services\OTPDeliveryService;
use App\API\Services\PasskeyService;

class TwoFactorController extends BaseController
{
    private TwoFactorService $tfa;
    private OTPDeliveryService $otpDelivery;
    private PasskeyService $passkeys;

    public function __construct()
    {
        parent::__construct();
        $this->tfa = $this->contract('App\API\Services\TwoFactorService');
        $this->otpDelivery = $this->contract('App\API\Services\OTPDeliveryService');
        $this->passkeys = $this->contract('App\API\Services\PasskeyService');
    }

    private function currentUser(): array
    {
        return $_SERVER['auth_user'] ?? [];
    }

    private function requireReauthentication(int $userId, array $data): ?array
    {
        $password = (string) ($data['current_password'] ?? '');
        if ($password === '' || !$this->tfa->verifyUserPassword($userId, $password)) {
            return $this->badRequest('Your current password is required to change 2FA settings.');
        }
        return null;
    }

    /**
     * POST /api/2fa/status
     */
    public function postStatus()
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');

        $status = $this->tfa->get2FAStatus($userId);
        $status['passkeys'] = $this->passkeys->list($userId);
        return $this->success($status);
    }

    public function postPasskeyOptions($id = null, $data = [])
    {
        $userId = $this->getUserId(); if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;
        $user = $this->currentUser();
        return $this->success($this->passkeys->startRegistration($userId, $user['username'] ?? (string) $userId, trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Staff user'));
    }

    public function postPasskeyRegister($id = null, $data = [])
    {
        $userId = $this->getUserId(); if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;
        try {
            $ok = $this->passkeys->finishRegistration($userId, (array) ($data['credential'] ?? []), (string) ($data['label'] ?? 'Passkey'));
            if ($ok && !$this->tfa->is2FAEnabled($userId)) {
                $this->db->getConnection()->prepare("UPDATE users SET two_factor_enabled=1, two_factor_method='passkey', two_factor_verified_at=NOW() WHERE id=?")->execute([$userId]);
            }
            return $ok ? $this->success(null, 'Passkey registered.') : $this->badRequest('Passkey registration failed.');
        }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('Passkey registration failed: ' . $e->getMessage()); return $this->badRequest('Passkey registration failed.'); }
    }

    public function deletePasskey($id = null, $data = [])
    {
        $userId = $this->getUserId(); if (!$userId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('Passkey ID is required.');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;
        return $this->passkeys->revoke($userId, (int)$id)
            ? $this->success(null, 'Passkey revoked.')
            : $this->notFound('Passkey not found.');
    }

    public function postPrimaryMethod($id = null, $data = [])
    {
        $userId = $this->getUserId(); if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;
        $method = strtolower(trim((string)($data['method'] ?? '')));
        return $this->tfa->setPrimaryMethod($userId, $method)
            ? $this->success(['method'=>$method], 'Primary verification method updated.')
            : $this->badRequest('That verification method is not enrolled.');
    }

    public function postAdminReset($id = null, $data = [])
    {
        if (!$this->userHasRole('System Administrator')) return $this->forbidden('System Administrator access required.');
        $target = (int)($data['user_id'] ?? 0); if (!$target) return $this->badRequest('User ID is required.');
        $this->tfa->administrativeReset($target, $this->getUserId());
        return $this->success(['user_id'=>$target], 'MFA was reset to the account email and all device factors were revoked.');
    }

    public function postPasswordlessOptions($id = null, $data = [])
    {
        try { return $this->success($this->passkeys->startPasswordlessAuthentication()); }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('Passwordless options failed: '.$e->getMessage()); return $this->serverError('Passwordless sign-in is unavailable.'); }
    }

    public function postPasswordlessVerify($id = null, $data = [])
    {
        try {
            $userId = $this->passkeys->finishPasswordlessAuthentication((array)($data['credential'] ?? []));
            if (!$userId) return $this->forbidden('Passkey verification failed.');
            $challenge = $this->tfa->createLoginChallenge($userId, 'passkey');
            if (!$this->tfa->markChallengeVerified($challenge, $userId)) return $this->serverError('Passkey session could not be established.');
            return $this->success(['verified'=>true,'user_id'=>$userId,'challenge_token'=>$challenge], 'Passkey verified.');
        } catch (\Throwable $e) { \App\API\Services\Logger::legacyError('Passwordless verify failed: '.$e->getMessage()); return $this->forbidden('Passkey verification failed.'); }
    }

    /**
     * POST /api/2fa/setup/totp
     * Returns the TOTP secret and otpauth:// URL for QR code generation.
     */
    public function postSetupTotp($id = null, $data = [])
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;

        $secret = $this->tfa->generateSecret();
        $user = $this->currentUser();
        $email = $user['email'] ?? '';
        $uri = $this->tfa->getTOTPUri($secret, $email);
        $qrDataUri = \Endroid\QrCode\Builder\Builder::create()
            ->data($uri)->size(260)->margin(10)->build()->getDataUri();

        $this->tfa->storePendingSecret($userId, $secret, 'totp');

        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $qrDataUri,
            'otpauth_uri' => $uri,
            'manual_entry_key' => $secret,
            'digits' => 6,
            'period' => 30,
        ], 'Scan the QR code with your authenticator app, then verify with a code.');
    }

    /**
     * POST /api/2fa/setup/email
     * Sends an OTP to the user's email for email-based 2FA setup.
     */
    public function postSetupEmail($id = null, $data = [])
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;

        $user = $this->currentUser();
        $email = $user['email'] ?? '';
        if (!$email) return $this->badRequest('No email address on file');

        try { $code = $this->tfa->generateOTP($userId, 'email', 'setup'); }
        catch (\RuntimeException $e) { return $this->respond(null, $e->getMessage(), $e->getCode() === 429 ? 429 : 400, false); }
        if (!$code) return $this->serverError('Failed to generate OTP');

        $sent = $this->otpDelivery->sendEmailOTP($email, $code, 'setup');
        if (!$sent) { $this->tfa->invalidateLatestOTP($userId, 'setup', 'email'); return $this->serverError('Failed to send verification email'); }

        return $this->success(['method' => 'email', 'expires_in' => 600],
            'A verification code has been sent to your email.');
    }

    /**
     * POST /api/2fa/setup/sms
     * Sends an OTP to the user's phone for SMS-based 2FA setup.
     */
    public function postSetupSms($id = null, $data = [])
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;

        $user = $this->currentUser();
        $phone = $user['phone'] ?? $user['phone_1'] ?? '';
        if (!$phone) return $this->badRequest('No phone number on file');

        try { $code = $this->tfa->generateOTP($userId, 'sms', 'setup'); }
        catch (\RuntimeException $e) { return $this->respond(null, $e->getMessage(), $e->getCode() === 429 ? 429 : 400, false); }
        if (!$code) return $this->serverError('Failed to generate OTP');

        $sent = $this->otpDelivery->sendSMSOTP($phone, $code, 'setup');
        if (!$sent) { $this->tfa->invalidateLatestOTP($userId, 'setup', 'sms'); return $this->serverError('Failed to send SMS'); }

        return $this->success(['method' => 'sms', 'expires_in' => 600],
            'A verification code has been sent to your phone.');
    }

    public function postSetupWhatsapp($id = null, $data = [])
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;
        $user = $this->currentUser();
        $phone = $user['phone'] ?? $user['phone_1'] ?? '';
        if (!$phone) return $this->badRequest('No phone number on file');
        try { $code = $this->tfa->generateOTP($userId, 'whatsapp', 'setup'); }
        catch (\RuntimeException $e) { return $this->respond(null, $e->getMessage(), $e->getCode() === 429 ? 429 : 400, false); }
        if (!$code || !$this->otpDelivery->sendWhatsAppOTP($phone, $code, 'setup')) { $this->tfa->invalidateLatestOTP($userId, 'setup', 'whatsapp'); return $this->serverError('Failed to send WhatsApp verification code'); }
        return $this->success(['method' => 'whatsapp', 'expires_in' => 600], 'A verification code has been sent to your WhatsApp.');
    }

    /**
     * POST /api/2fa/setup/verify
     * Verify the first code to complete 2FA setup.
     * Body: { code: "123456" }
     */
    public function postSetupVerify($id = null, $data = [])
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;

        $code = trim($data['code'] ?? '');
        if (!$code) return $this->badRequest('Verification code is required');

        $pending = $this->tfa->getPendingSecret($userId);
        if (!$pending) return $this->badRequest('No pending 2FA setup. Start setup again.');

        $verified = false;
        $secret = $pending['secret'];

        if ($pending['method'] === 'totp') {
            $verified = $this->tfa->verifyTOTP($secret, $code);
        } elseif (in_array($pending['method'], ['email', 'sms', 'whatsapp'])) {
            $verified = $this->tfa->verifyOTP($userId, $code, 'setup');
        }

        if (!$verified) {
            return $this->badRequest('Invalid verification code. Please try again.');
        }

        $storeSecret = $secret ?? strtoupper(bin2hex(random_bytes(16)));
        $this->tfa->enable2FA($userId, $pending['method'], $storeSecret);

        $backupCodes = $this->tfa->generateBackupCodes($userId);

        $user = $this->currentUser();
        if (!empty($user['email'])) {
            $this->otpDelivery->sendBackupCodesEmail($user['email'], $backupCodes);
        }

        $this->tfa->clearPendingSecret($userId);

        return $this->success([
            'method' => $pending['method'],
            'backup_codes' => $backupCodes,
            'backup_codes_remaining' => 10,
        ], 'Two-factor authentication has been enabled. Save your backup codes!');
    }

    /**
     * POST /api/2fa/disable
     * Disable 2FA. Requires password + current TOTP/OTP code.
     * Body: { password: "...", code: "123456" }
     */
    public function postDisable($id = null, $data = [])
    {
        if (!$this->getUserId()) return $this->unauthorized('Not authenticated');
        return $this->forbidden('Email MFA is required for every Kingsway account and cannot be disabled. You may change your primary method or ask a System Administrator to reset device factors.');
    }

    /**
     * POST /api/2fa/backup/generate
     * Generate new backup codes (invalidates old ones).
     */
    public function postBackupGenerate($id = null, $data = [])
    {
        $userId = $this->getUserId();
        if (!$userId) return $this->unauthorized('Not authenticated');
        if ($error = $this->requireReauthentication($userId, $data)) return $error;

        if (!$this->tfa->is2FAEnabled($userId)) {
            return $this->badRequest('Enable 2FA first');
        }

        $codes = $this->tfa->rotateBackupCodes($userId);

        $user = $this->currentUser();
        if (!empty($user['email'])) {
            $this->otpDelivery->sendBackupCodesEmail($user['email'], $codes);
        }

        return $this->success([
            'backup_codes' => $codes,
            'backup_codes_remaining' => count($codes),
        ], 'New backup codes generated. Old codes have been invalidated.');
    }

    /**
     * POST /api/2fa/challenge
     * Send an OTP challenge during login (after password verified).
     * Body: { method: "email"|"sms", user_id: int }
     * Called by the login flow when 2FA is required.
     */
    public function postChallenge($id = null, $data = [])
    {
        $challengeToken = trim((string) ($data['challenge_token'] ?? ''));
        $challenge = $challengeToken ? $this->tfa->getLoginChallenge($challengeToken) : null;
        if (!$challenge) return $this->badRequest('Invalid or expired 2FA challenge');
        $targetUserId = (int) $challenge['user_id'];
        $method = (string) $challenge['method'];
        $requestedMethod = trim((string) ($data['method'] ?? ''));
        if ($requestedMethod !== '') {
            if (!$this->tfa->setChallengeMethod($challengeToken, $targetUserId, $requestedMethod)) return $this->badRequest('That 2FA method is not enrolled');
            $method = $requestedMethod;
        }

        if ($method === 'totp') {
            return $this->success(['method' => 'totp', 'challenge_sent' => false],
                'Enter the code from your authenticator app.');
        }

        $contact = $this->tfa->getUserContact($targetUserId);

        if ((int) $challenge['attempts'] >= 5) return $this->forbidden('Too many 2FA attempts');
        try {
            $code = $this->tfa->generateOTP($targetUserId, $method, 'login');
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), $e->getCode() === 429 ? 429 : 400, false);
        }
        if (!$code) return $this->serverError('Failed to generate OTP');

        $sent = false;
        if ($method === 'email' && !empty($contact['email'])) {
            $sent = $this->otpDelivery->sendEmailOTP($contact['email'], $code, 'login');
        } elseif ($method === 'sms' && !empty($contact['phone_1'])) {
            $sent = $this->otpDelivery->sendSMSOTP($contact['phone_1'], $code, 'login');
        } elseif ($method === 'whatsapp' && !empty($contact['phone_1'])) {
            $sent = $this->otpDelivery->sendWhatsAppOTP($contact['phone_1'], $code, 'login');
        }

        if (!$sent) {
            $this->tfa->invalidateLatestOTP($targetUserId, 'login', $method);
            return $this->serverError("Failed to send {$method} verification code");
        }

        return $this->success([
            'method' => $method,
            'challenge_sent' => true,
            'expires_in' => 600,
        ], "A verification code has been sent to your {$method}.");
    }

    /**
     * POST /api/2fa/verify
     * Verify 2FA code during login.
     * Body: { code: "123456", user_id: int, method: "totp"|"email"|"sms"|"backup" }
     */
    public function postVerify($id = null, $data = [])
    {
        $challengeToken = trim((string) ($data['challenge_token'] ?? ''));
        $code = trim($data['code'] ?? '');
        $method = $data['method'] ?? 'totp';

        $challenge = $challengeToken ? $this->tfa->getLoginChallenge($challengeToken) : null;
        if (!$challenge) return $this->badRequest('Invalid or expired 2FA challenge');
        $targetUserId = (int) $challenge['user_id'];
        if ($method !== $challenge['method'] && !($method === 'backup')) return $this->badRequest('Challenge method mismatch');
        if (!$code) return $this->badRequest('Verification code is required');

        $verified = false;

        switch ($method) {
            case 'totp':
                $verified = $this->tfa->verifyUserTOTP($targetUserId, $code);
                break;

            case 'email':
            case 'sms':
            case 'whatsapp':
                $verified = $this->tfa->verifyOTP($targetUserId, $code, 'login');
                break;

            case 'passkey':
                $verified = $this->passkeys->finishAuthentication($targetUserId, (array) ($data['credential'] ?? []));
                break;

            case 'backup':
                $verified = $this->tfa->verifyBackupCode($targetUserId, $code);
                break;

            default:
                return $this->badRequest('Invalid 2FA method');
        }

        if (!$verified) {
            $this->tfa->registerChallengeFailure($challengeToken, $targetUserId);
            return $this->forbidden('Invalid verification code');
        }

        if (!$this->tfa->markChallengeVerified($challengeToken, $targetUserId)) {
            return $this->conflict('This verification challenge was already used.');
        }

        return $this->success([
            'verified' => true,
            'user_id' => $targetUserId,
            'challenge_token' => $challengeToken,
        ], 'Verification successful.');
    }
}
