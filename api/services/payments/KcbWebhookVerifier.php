<?php

namespace App\API\Services\payments;

/** Verifies KCB Buni's SHA256withRSA callback signature. */
class KcbWebhookVerifier
{
    public function verify(array $headers, string $rawBody): bool
    {
        $reason = $this->failureReason($headers, $rawBody);
        if ($reason === '') {
            return $this->opensslVerify($headers, $rawBody);
        }

        // Every rejection is logged with its cause. A silent false here means
        // every Buni payment callback is refused with no way to diagnose why,
        // which looks identical to "the provider stopped calling us".
        \App\API\Services\Logger::legacyError(
            '[KcbWebhookVerifier] callback signature check not performed: ' . $reason
        );

        return false;
    }

    public function shouldEnforce(): bool
    {
        if (defined('KCB_VERIFY_CALLBACK_SIGNATURE')) {
            return (bool) KCB_VERIFY_CALLBACK_SIGNATURE;
        }

        return defined('KCB_ENVIRONMENT') && KCB_ENVIRONMENT === 'production';
    }

    /** Empty string means verification can proceed. */
    private function failureReason(array $headers, string $rawBody): string
    {
        $signature = $headers['Signature'] ?? $headers['signature'] ?? '';
        $keyPath = defined('KCB_PUBLIC_KEY_PATH') ? (string) KCB_PUBLIC_KEY_PATH : '';

        if ($signature === '') {
            return 'the callback carries no Signature header';
        }
        if ($rawBody === '') {
            return 'the raw request body is empty';
        }
        if ($keyPath === '') {
            return 'KCB_PUBLIC_KEY_PATH is not configured';
        }
        if (!is_file($keyPath)) {
            return 'KCB_PUBLIC_KEY_PATH does not point to a readable file (' . $keyPath . ')';
        }

        return '';
    }

    private function opensslVerify(array $headers, string $rawBody): bool
    {
        $keyPath = (string) KCB_PUBLIC_KEY_PATH;
        $publicKey = @openssl_pkey_get_public((string) @file_get_contents($keyPath));
        if ($publicKey === false) {
            \App\API\Services\Logger::legacyError(
                '[KcbWebhookVerifier] KCB_PUBLIC_KEY_PATH is not a valid public key (' . $keyPath . ')'
            );

            return false;
        }

        $decodedSignature = base64_decode((string) ($headers['Signature'] ?? $headers['signature'] ?? ''), true);
        if ($decodedSignature === false) {
            \App\API\Services\Logger::legacyError('[KcbWebhookVerifier] Signature header is not valid base64');

            return false;
        }

        return openssl_verify($rawBody, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
