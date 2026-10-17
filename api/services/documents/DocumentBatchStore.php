<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use RuntimeException;

/**
 * File-backed state and artifact store for bulk-document batches.
 *
 * Deliberately file-based (never a database table): batch progress and part
 * PDFs are transient operational state, not records or logs. Everything lives
 * under storage/document_batches/ (web-denied via storage/.htaccess) and is
 * removed by the retention sweep.
 *
 * Layout:
 *   storage/document_batches/{batch_id}.json        state
 *   storage/document_batches/{batch_id}/part-0001.pdf
 *   storage/document_batches/{batch_id}/{kind}_{ts}.pdf  final artifact
 */
final class DocumentBatchStore
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? (dirname(__DIR__, 3) . '/storage/document_batches');
    }

    public function root(): string
    {
        return $this->root;
    }

    public function batchDirectory(string $batchId): string
    {
        return $this->root . '/' . $this->assertId($batchId);
    }

    public function statePath(string $batchId): string
    {
        return $this->root . '/' . $this->assertId($batchId) . '.json';
    }

    /**
     * @param array<string, mixed> $state
     */
    public function create(string $batchId, array $state): void
    {
        $this->ensureDir($this->root);
        $this->ensureDir($this->batchDirectory($batchId));
        if (is_file($this->statePath($batchId))) {
            throw new RuntimeException('A document batch with that id already exists.');
        }
        $this->writeState($batchId, $state);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $batchId): ?array
    {
        $path = $this->statePath($batchId);
        if (!is_file($path)) {
            return null;
        }
        $raw = $this->readLocked($path);
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $state
     */
    public function save(string $batchId, array $state): void
    {
        $this->writeState($batchId, $state);
    }

    /** Persist a rendered part PDF; returns its filename. */
    public function writePart(string $batchId, int $index, string $bytes): string
    {
        $this->ensureDir($this->batchDirectory($batchId));
        $filename = sprintf('part-%04d.pdf', max(1, $index));
        $this->writeFile($this->batchDirectory($batchId) . '/' . $filename, $bytes);
        return $filename;
    }

    public function readPart(string $batchId, string $filename): string
    {
        return $this->readFile($this->batchDirectory($batchId) . '/' . $this->assertBasename($filename));
    }

    /** Persist the final artifact; returns its filename. */
    public function writeArtifact(string $batchId, string $filename, string $bytes): string
    {
        $this->ensureDir($this->batchDirectory($batchId));
        $safe = $this->assertBasename($filename);
        $this->writeFile($this->batchDirectory($batchId) . '/' . $safe, $bytes);
        return $safe;
    }

    public function readArtifact(string $batchId, string $filename): string
    {
        return $this->readFile($this->batchDirectory($batchId) . '/' . $this->assertBasename($filename));
    }

    public function artifactPath(string $batchId, string $filename): string
    {
        return $this->batchDirectory($batchId) . '/' . $this->assertBasename($filename);
    }

    /** Remove a batch's state and all its files. */
    public function delete(string $batchId): void
    {
        $dir = $this->batchDirectory($batchId);
        if (is_dir($dir)) {
            foreach ((array) glob($dir . '/*') as $file) {
                if (is_string($file) && is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }
        $state = $this->statePath($batchId);
        if (is_file($state)) {
            @unlink($state);
        }
    }

    /**
     * Delete batches whose state file is older than $hours.
     *
     * @return int batches purged
     */
    public function purgeExpired(int $hours = 48): int
    {
        if (!is_dir($this->root)) {
            return 0;
        }
        $hours = max(1, min(24 * 30, $hours));
        $cutoff = time() - ($hours * 3600);
        $purged = 0;
        foreach ((array) glob($this->root . '/*.json') as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }
            if ((int) @filemtime($file) >= $cutoff) {
                continue;
            }
            $decoded = json_decode((string) @file_get_contents($file), true);
            $batchId = is_array($decoded) ? (string) ($decoded['batch_id'] ?? '') : '';
            if ($batchId === '') {
                $batchId = basename($file, '.json');
            }
            if (preg_match('/^[a-f0-9]{16,64}$/', $batchId) === 1) {
                $this->delete($batchId);
                $purged++;
            } else {
                @unlink($file);
            }
        }
        return $purged;
    }

    private function writeState(string $batchId, array $state): void
    {
        $this->ensureDir($this->root);
        $this->writeFile(
            $this->statePath($batchId),
            (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function writeFile(string $path, string $contents): void
    {
        $this->ensureDir(dirname($path));
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $contents) === false) {
            throw new RuntimeException('Unable to write document batch file.');
        }
        @chmod($tmp, 0664);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to commit document batch file.');
        }
    }

    private function readFile(string $path): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('Document batch file is missing.');
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read document batch file.');
        }
        return $raw;
    }

    private function readLocked(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            @flock($handle, LOCK_SH);
            $raw = stream_get_contents($handle);
            return $raw === false ? null : $raw;
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create document batch directory.');
        }
    }

    private function assertId(string $batchId): string
    {
        if (preg_match('/^[a-f0-9]{16,64}$/', $batchId) !== 1) {
            throw new RuntimeException('Invalid document batch id.');
        }
        return $batchId;
    }

    private function assertBasename(string $filename): string
    {
        $safe = basename(trim($filename));
        if ($safe === '' || $safe === '.' || $safe === '..' || str_contains($safe, '/') || str_contains($safe, '\\')) {
            throw new RuntimeException('Invalid document batch filename.');
        }
        return $safe;
    }
}
