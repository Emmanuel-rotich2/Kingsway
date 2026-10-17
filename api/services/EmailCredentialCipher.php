<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;

/**
 * Optional at-rest encryption for mailbox credentials.
 *
 * When EMAIL_ENCRYPTION_KEY is configured, every SMTP/IMAP password written
 * by EmailProfileService is stored as an authenticated AES-256-GCM envelope
 * (`enc:v1:` + base64(iv | tag | ciphertext)). When the key is absent the
 * value passes through unchanged, so existing deployments keep working until
 * an administrator provisions the key — the encrypt/decrypt pair is applied
 * symmetrically on read and write, never opportunistically.
 */
final class EmailCredentialCipher
{
    private const PREFIX = 'enc:v1:';

    /** Whether a key is configured (writes will encrypt, reads will decrypt). */
    public static function enabled(): bool
    {
        return self::key() !== '';
    }

    /** Encrypt a plaintext credential, or return it unchanged when no key is set. */
    public static function encrypt(string $plain): string
    {
        if ($plain === '' || str_starts_with($plain, self::PREFIX)) {
            return $plain;
        }
        $key = self::key();
        if ($key === '') {
            return $plain;
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Mailbox credential encryption failed.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    /** Decrypt a stored credential; plain legacy values pass through unchanged. */
    public static function decrypt(string $stored): string
    {
        if ($stored === '' || !str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }
        $key = self::key();
        if ($key === '') {
            // The value was encrypted elsewhere; without the key the caller
            // must treat the credential as unusable rather than guess.
            return '';
        }

        $payload = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) <= 28) {
            return '';
        }
        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);

        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    /** 32-byte key derived from the configured secret (sha-256 normalisation). */
    private static function key(): string
    {
        $configured = (string) Config::get(
            'EMAIL_ENCRYPTION_KEY',
            (string) Config::get('TFA_ENCRYPTION_KEY', '')
        );

        return $configured === '' ? '' : hash('sha256', $configured, true);
    }
}
