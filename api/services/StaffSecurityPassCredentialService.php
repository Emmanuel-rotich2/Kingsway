<?php

declare(strict_types=1);

namespace App\API\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;

/**
 * StaffSecurityPassCredentialService
 *
 * Creates and validates the signed credential carried by a staff security-pass
 * QR code. The QR contains no fingerprint data and no raw staff primary key.
 * The pass number is resolved against the canonical staff_id_cards registry
 * when a future gate/attendance scanner endpoint validates the credential.
 */
final class StaffSecurityPassCredentialService
{
    private const TOKEN_VERSION = 'KWS1';

    public function issue(string $passNumber, ?string $expiresAt): string
    {
        $passNumber = trim($passNumber);

        if ($passNumber === '') {
            throw new RuntimeException('A pass number is required.');
        }

        $payload = json_encode(
            [
                'v' => 1,
                'pass' => $passNumber,
                'exp' => $expiresAt ?: null,
            ],
            JSON_UNESCAPED_SLASHES
        );

        if ($payload === false) {
            throw new RuntimeException('Unable to encode security-pass credential.');
        }

        $encodedPayload = $this->base64UrlEncode($payload);
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, $this->signingKey(), true)
        );

        return self::TOKEN_VERSION
            . '.'
            . $encodedPayload
            . '.'
            . $signature;
    }

    /**
     * @return array{v:int,pass:string,exp:?string}
     */
    public function verify(string $credential): array
    {
        $parts = explode('.', trim($credential));

        if (count($parts) !== 3 || $parts[0] !== self::TOKEN_VERSION) {
            throw new RuntimeException('Invalid staff security-pass credential.');
        }

        [, $encodedPayload, $providedSignature] = $parts;

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, $this->signingKey(), true)
        );

        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Security-pass credential signature is invalid.');
        }

        $decodedPayload = $this->base64UrlDecode($encodedPayload);
        $payload = json_decode($decodedPayload, true);

        if (!is_array($payload) || empty($payload['pass'])) {
            throw new RuntimeException('Security-pass credential payload is invalid.');
        }

        return [
            'v' => (int) ($payload['v'] ?? 1),
            'pass' => (string) $payload['pass'],
            'exp' => isset($payload['exp']) && $payload['exp'] !== ''
                ? (string) $payload['exp']
                : null,
        ];
    }

    public function qrDataUri(string $credential): string
    {
        $qrCode = new QrCode(
            $credential,
            new Encoding('ISO-8859-1'),
            ErrorCorrectionLevel::Medium,
            360,
            12,
            RoundBlockSizeMode::Margin
        );

        return (new PngWriter())->write($qrCode)->getDataUri();
    }

    private function signingKey(): string
    {
        if (!defined('JWT_SECRET') || trim((string) JWT_SECRET) === '') {
            throw new RuntimeException(
                'JWT_SECRET is required to sign staff security-pass credentials.'
            );
        }

        return hash_hmac(
            'sha256',
            'kingsway-staff-security-pass-v1',
            (string) JWT_SECRET,
            true
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Security-pass credential encoding is invalid.');
        }

        return $decoded;
    }
}
