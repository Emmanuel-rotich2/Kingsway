<?php
/**
 * OTP Delivery Service
 *
 * Sends 2FA verification codes via Email (PHPMailer) and SMS (Africa's Talking).
 *
 * @package App\Services
 */

namespace App\API\Services;

class OTPDeliveryService
{
    private ?\App\API\Services\MessageService $messageService = null;
    private ?\App\API\Services\sms\SMSGateway $smsGateway = null;
    private ?\App\API\Services\whatsapp\WhatsAppGateway $whatsappGateway = null;

    public function __construct()
    {
        // Lazy-load services — only instantiate if classes exist
        if (class_exists(\App\API\Services\MessageService::class)) {
            try {
                $db = \App\Database\Database::getInstance()->getConnection();
                $this->messageService = new \App\API\Services\MessageService($db);
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError("[OTPDelivery] MessageService not available: " . $e->getMessage());
            }
        }
        if (class_exists(\App\API\Services\sms\SMSGateway::class)) {
            $this->smsGateway = new \App\API\Services\sms\SMSGateway();
        }
        if (class_exists(\App\API\Services\whatsapp\WhatsAppGateway::class)) {
            try { $this->whatsappGateway = new \App\API\Services\whatsapp\WhatsAppGateway(); } catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[OTPDelivery] WhatsApp not available: ' . $e->getMessage()); }
        }
    }

    /**
     * Send a 2FA OTP code via email.
     */
    public function sendEmailOTP(string $email, string $code, string $context = 'login'): bool
    {
        $subject = $this->getSubject($context);
        $body = $this->buildEmailBody($code, $context);

        if ($this->messageService) {
            try {
                return $this->messageService->sendEmail($email, $subject, $body);
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError("[OTPDelivery] Email failed: " . $e->getMessage());
                return false;
            }
        }

        // Never log authentication secrets or claim delivery when the mail
        // transport is unavailable. The controller invalidates this OTP.
        \App\API\Services\Logger::legacyError('[OTPDelivery] Email transport is unavailable.');
        return false;
    }

    /**
     * Send a 2FA OTP code via SMS.
     */
    public function sendSMSOTP(string $phone, string $code, string $context = 'login'): bool
    {
        $message = $this->buildSMSMessage($code, $context);

        if ($this->smsGateway) {
            try {
                $result = $this->smsGateway->send($phone, $message);
                return is_bool($result) ? $result : ($result['success'] ?? false);
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError("[OTPDelivery] SMS failed: " . $e->getMessage());
                return false;
            }
        }

        \App\API\Services\Logger::legacyError('[OTPDelivery] SMS transport is unavailable.');
        return false;
    }

    public function sendWhatsAppOTP(string $phone, string $code, string $context = 'login'): bool
    {
        if (!$this->whatsappGateway) return false;
        try {
            $templateId = defined('WHATSAPP_2FA_TEMPLATE_ID') ? (string) WHATSAPP_2FA_TEMPLATE_ID : '';
            $result = $templateId !== ''
                ? $this->whatsappGateway->sendTemplate($phone, $templateId, ['bodyValues' => [$code]])
                : $this->whatsappGateway->sendMessage($phone, $this->buildSMSMessage($code, $context));
            return is_array($result) && in_array(strtolower((string) ($result['status'] ?? '')), ['success','sent','queued','accepted'], true);
        } catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[OTPDelivery] WhatsApp failed: ' . $e->getMessage()); return false; }
    }

    /**
     * Send backup codes via email.
     */
    public function sendBackupCodesEmail(string $email, array $codes): bool
    {
        $codeList = implode("\n", array_map(fn($c) => "  • {$c}", $codes));
        $subject = "Kingsway Preparatory School — Your 2FA Backup Codes";
        $body = <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #1e3a5f;">Your 2FA Backup Codes</h2>
            <p>Here are your 10 one-time-use backup codes. Store them securely — you'll need them if you lose access to your authenticator app.</p>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; font-family: monospace; font-size: 16px; line-height: 2;">
                {$codeList}
            </div>
            <p style="color: #dc3545; font-weight: bold;">Each code can only be used once. After using a code, it is permanently consumed.</p>
            <p style="color: #6c757d; font-size: 12px;">If you did not request these codes, please change your password immediately.</p>
        </div>
        HTML;

        if ($this->messageService) {
            try {
                return $this->messageService->sendEmail($email, $subject, $body);
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError("[OTPDelivery] Backup codes email failed: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    private function getSubject(string $context): string
    {
        return match($context) {
            'invitation_setup' => 'Kingsway Preparatory School — Verify Your Staff Account',
            'setup' => 'Kingsway Preparatory School — Verify Your 2FA Setup',
            'disable' => 'Kingsway Preparatory School — Confirm 2FA Disable',
            default => 'Kingsway Preparatory School — Your Login Verification Code',
        };
    }

    private function buildEmailBody(string $code, string $context): string
    {
        $instruction = match($context) {
            'invitation_setup' => 'Enter this code to verify your email and continue setting up your staff account.',
            'setup' => 'Enter this code to complete your two-factor authentication setup.',
            'disable' => 'Enter this code to confirm disabling two-factor authentication.',
            default => 'Enter this code to complete your login.',
        };

        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #1e3a5f;">Your Verification Code</h2>
            <p>{$instruction}</p>
            <div style="background: #f8f9fa; padding: 30px; border-radius: 8px; text-align: center; margin: 20px 0;">
                <span style="font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #1e3a5f; font-family: monospace;">{$code}</span>
            </div>
            <p style="color: #6c757d; font-size: 12px;">This code expires in 10 minutes. If you did not request this, please secure your account.</p>
        </div>
        HTML;
    }

    private function buildSMSMessage(string $code, string $context): string
    {
        return match($context) {
            'setup' => "Kingsway Preparatory School: Your 2FA setup code is {$code}. It expires in 10 minutes.",
            'disable' => "Kingsway Preparatory School: Your code to disable 2FA is {$code}.",
            default => "Kingsway Preparatory School: Your login code is {$code}. It expires in 10 minutes.",
        };
    }
}
