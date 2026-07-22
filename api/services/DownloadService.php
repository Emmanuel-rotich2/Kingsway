<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Database\Database;
use RuntimeException;

/**
 * Canonical download-access service.
 *
 * Public school documents use stable random tokens stored in page_downloads.
 * Generated print files use short-lived encrypted tokens.
 */
final class DownloadService
{
    private const PRINT_TTL_SECONDS = 1800;
    private const GENERATED_TTL_SECONDS = 1800;
    private const CIPHER = 'aes-256-gcm';

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function createPublicToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function publicDownloadUrl(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new RuntimeException('Invalid public download token.');
        }

        return rtrim((string) BASE_URL, '/')
            . '/api/download/public?token='
            . rawurlencode($token);
    }

    /**
     * @return array<string,mixed>
     */
    public function normalizedPublicDocument(array $row): array
    {
        $token = trim((string) ($row['public_token'] ?? ''));
        $row['download_url'] = $token !== ''
            ? $this->publicDownloadUrl($token)
            : null;

        unset(
            $row['storage_filename'],
            $row['file_url'],
            $row['token_revoked_at']
        );

        return $row;
    }

    public function printUrlForAbsolutePath(
        string $absolutePath,
        ?int $ttl = null
    ): string {
        $filename = $this->assertInsideRoot(
            $absolutePath,
            (string) PRINT_OUTPUT_PATH
        );

        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new RuntimeException(
                'The print endpoint only serves generated PDF files.'
            );
        }

        return $this->temporaryUrl(
            'print',
            $filename,
            time() + max(60, $ttl ?? self::PRINT_TTL_SECONDS),
            'print'
        );
    }

    public function generatedDownloadUrlForAbsolutePath(
        string $absolutePath,
        ?int $ttl = null
    ): string {
        $filename = $this->assertInsideRoot(
            $absolutePath,
            (string) PRINT_OUTPUT_PATH
        );

        return $this->temporaryUrl(
            'generated_download',
            $filename,
            time() + max(60, $ttl ?? self::GENERATED_TTL_SECONDS),
            'generated'
        );
    }

    /**
     * @return array{path:string,filename:string,mime_type:string,size:int}
     */
    public function resolvePublicDocument(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', trim($token))) {
            throw new RuntimeException('Invalid public download token.');
        }

        $row = $this->db->query(
            "SELECT id, storage_filename, original_filename, mime_type,
                    file_size_bytes, is_active, token_revoked_at
             FROM page_downloads
             WHERE public_token = ?
             LIMIT 1",
            [$token]
        )->fetch();

        if (
            !$row
            || !(int) ($row['is_active'] ?? 0)
            || !empty($row['token_revoked_at'])
        ) {
            throw new RuntimeException(
                'The requested school document is unavailable.'
            );
        }

        $storageFilename = basename(
            (string) ($row['storage_filename'] ?? '')
        );

        if ($storageFilename === '') {
            throw new RuntimeException(
                'The school document has no stored file.'
            );
        }

        $path = $this->resolveInsideRoot(
            (string) SCHOOL_ASSETS_DOCUMENTS,
            $storageFilename
        );

        $this->db->query(
            "UPDATE page_downloads
             SET download_count = download_count + 1,
                 last_downloaded_at = NOW()
             WHERE id = ?",
            [(int) $row['id']]
        );

        return [
            'path' => $path,
            'filename' => $this->downloadFilename(
                (string) ($row['original_filename'] ?? ''),
                $storageFilename
            ),
            'mime_type' => $this->detectMimeType(
                $path,
                (string) ($row['mime_type'] ?? '')
            ),
            'size' => (int) filesize($path),
        ];
    }

    /**
     * @return array{path:string,filename:string,mime_type:string,size:int}
     */
    public function resolveTemporary(
        string $token,
        string $expectedPurpose
    ): array {
        $payload = $this->decrypt($token);
        $purpose = (string) ($payload['purpose'] ?? '');
        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        $filename = basename((string) ($payload['filename'] ?? ''));

        if ($purpose !== $expectedPurpose) {
            throw new RuntimeException(
                'The file token is not valid for this operation.'
            );
        }

        if ($expiresAt <= time()) {
            throw new RuntimeException('This file access link has expired.');
        }

        $path = $this->resolveInsideRoot(
            (string) PRINT_OUTPUT_PATH,
            $filename
        );

        if (
            $purpose === 'print'
            && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf'
        ) {
            throw new RuntimeException(
                'The print endpoint only serves PDF files.'
            );
        }

        return [
            'path' => $path,
            'filename' => $filename,
            'mime_type' => $this->detectMimeType($path),
            'size' => (int) filesize($path),
        ];
    }

    private function temporaryUrl(
        string $purpose,
        string $filename,
        int $expiresAt,
        string $endpoint
    ): string {
        $token = $this->encrypt([
            'version' => 1,
            'purpose' => $purpose,
            'filename' => basename($filename),
            'expires_at' => $expiresAt,
        ]);

        return rtrim((string) BASE_URL, '/')
            . '/api/download/'
            . $endpoint
            . '?token='
            . rawurlencode($token);
    }

    private function assertInsideRoot(
        string $absolutePath,
        string $root
    ): string {
        $resolvedRoot = realpath($root);
        $resolvedPath = realpath($absolutePath);

        if (
            $resolvedRoot === false
            || $resolvedPath === false
            || !$this->isWithin($resolvedPath, $resolvedRoot)
            || !is_file($resolvedPath)
        ) {
            throw new RuntimeException(
                'The file is outside the allowed download directory.'
            );
        }

        return basename($resolvedPath);
    }

    private function resolveInsideRoot(
        string $root,
        string $filename
    ): string {
        $resolvedRoot = realpath($root);
        $resolvedPath = realpath(
            rtrim($root, '/\\')
            . DIRECTORY_SEPARATOR
            . basename($filename)
        );

        if (
            $resolvedRoot === false
            || $resolvedPath === false
            || !$this->isWithin($resolvedPath, $resolvedRoot)
            || !is_file($resolvedPath)
            || !is_readable($resolvedPath)
        ) {
            throw new RuntimeException('The requested file is unavailable.');
        }

        return $resolvedPath;
    }

    private function downloadFilename(
        string $originalFilename,
        string $storageFilename
    ): string {
        $candidate = trim($originalFilename) !== ''
            ? basename($originalFilename)
            : basename($storageFilename);

        return str_replace(['"', "\r", "\n"], '', $candidate);
    }

    private function detectMimeType(
        string $path,
        string $storedMime = ''
    ): string {
        if ($storedMime !== '') {
            return $storedMime;
        }

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    private function isWithin(string $path, string $root): bool
    {
        $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        return $path === $root || str_starts_with($path, $normalizedRoot);
    }

    private function encrypt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode download token.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $json,
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to create download token.');
        }

        return $this->base64UrlEncode($iv . $tag . $ciphertext);
    }

    private function decrypt(string $token): array
    {
        $binary = $this->base64UrlDecode(trim($token));
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $tagLength = 16;

        if (strlen($binary) <= $ivLength + $tagLength) {
            throw new RuntimeException('Invalid download token.');
        }

        $iv = substr($binary, 0, $ivLength);
        $tag = substr($binary, $ivLength, $tagLength);
        $ciphertext = substr($binary, $ivLength + $tagLength);

        $json = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (!is_string($json)) {
            throw new RuntimeException('Invalid download token.');
        }

        $payload = json_decode($json, true);
        if (
            !is_array($payload)
            || (int) ($payload['version'] ?? 0) !== 1
        ) {
            throw new RuntimeException('Invalid download token payload.');
        }

        return $payload;
    }

    private function key(): string
    {
        if (!defined('JWT_SECRET') || trim((string) JWT_SECRET) === '') {
            throw new RuntimeException(
                'JWT_SECRET is required for temporary download tokens.'
            );
        }

        return hash(
            'sha256',
            'kingsway-download-service|' . (string) JWT_SECRET,
            true
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '='
        );
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(
            strtr($value, '-_', '+/'),
            true
        );

        if ($decoded === false) {
            throw new RuntimeException('Invalid download token encoding.');
        }

        return $decoded;
    }
    /**
     * Canonical streaming primitive. Authorization and record ownership are
     * checked by the calling controller before invoking this method.
     */
    public function streamAbsolutePath(
        string $absolutePath,
        ?string $filename = null,
        ?string $mimeType = null,
        string $disposition = 'attachment'
    ): never {
        $resolved = realpath($absolutePath);
        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            throw new RuntimeException('The requested file is unavailable.');
        }
        if (!in_array($disposition, ['inline', 'attachment'], true)) {
            throw new RuntimeException('Invalid download disposition.');
        }
        $safeFilename = str_replace(['"', "\r", "\n"], '', basename($filename ?: $resolved));
        $resolvedMime = $mimeType ?: $this->detectMimeType($resolved);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        header('Content-Type: ' . $resolvedMime);
        header('Content-Length: ' . (string) filesize($resolved));
        header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($resolved);
        exit;
    }

    /** @param array<string,mixed> $file */
    public function streamResolved(array $file, string $disposition = 'attachment'): never
    {
        $this->streamAbsolutePath(
            (string) $file['path'],
            (string) ($file['filename'] ?? ''),
            (string) ($file['mime_type'] ?? ''),
            $disposition
        );
    }

}
