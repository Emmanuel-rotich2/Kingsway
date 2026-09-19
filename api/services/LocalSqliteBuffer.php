<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Bounded local buffer for non-authoritative temporary state.
 *
 * This class is deliberately not a database replacement. Callers must only
 * store cacheable/derivable data and must include the effective audience and
 * row-scope in the namespace. When PDO SQLite is unavailable it falls back to
 * locked JSON files so shared hosting can still use the buffer contract.
 */
final class LocalSqliteBuffer
{
    private const MAX_BYTES = 262144;
    private const DEFAULT_TTL = 900;
    private const MAX_TTL = 86400;

    private string $directory;
    private ?PDO $pdo = null;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?: (sys_get_temp_dir() . '/kingsway_local_buffers');
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create local buffer directory.');
        }
        @chmod($this->directory, 0700);
        $this->connect();
    }

    public function put(string $namespace, string $key, mixed $value, int $ttl = self::DEFAULT_TTL): void
    {
        $this->validateKey($namespace, $key);
        $ttl = max(1, min(self::MAX_TTL, $ttl));
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::MAX_BYTES) {
            throw new RuntimeException('Local buffer value exceeds the bounded size limit.');
        }
        $expires = time() + $ttl;
        if ($this->pdo) {
            $stmt = $this->pdo->prepare('INSERT INTO buffer_entries (namespace, cache_key, value_json, expires_at, updated_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT(namespace, cache_key) DO UPDATE SET value_json=excluded.value_json, expires_at=excluded.expires_at, updated_at=excluded.updated_at');
            $stmt->execute([$namespace, $this->hash($key), $encoded, $expires, time()]);
            return;
        }
        $path = $this->jsonPath($namespace, $key);
        $handle = fopen($path, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Unable to lock local JSON buffer.');
        try {
            ftruncate($handle, 0);
            fwrite($handle, json_encode(['namespace' => $namespace, 'stored_at' => time(), 'expires_at' => $expires, 'value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            fflush($handle);
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    public function get(string $namespace, string $key): mixed
    {
        $this->validateKey($namespace, $key);
        if ($this->pdo) {
            $stmt = $this->pdo->prepare('SELECT value_json, expires_at FROM buffer_entries WHERE namespace = ? AND cache_key = ? LIMIT 1');
            $stmt->execute([$namespace, $this->hash($key)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int) $row['expires_at'] <= time()) { if ($row) $this->delete($namespace, $key); return null; }
            try {
                return json_decode((string) $row['value_json'], true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $this->quarantineSqlite($namespace, $this->hash($key), (string) $row['value_json']);
                return null;
            }
        }
        $path = $this->jsonPath($namespace, $key);
        if (!is_file($path)) return null;
        $raw = $this->readLocked($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) { $this->quarantineJson($path); return null; }
        if ((int) ($decoded['expires_at'] ?? 0) <= time()) { @unlink($path); return null; }
        return $decoded['value'] ?? null;
    }

    /**
     * Return a cache value with freshness metadata without deleting an expired
     * snapshot. Callers must label stale data and must never treat it as
     * authoritative school state.
     */
    public function snapshot(string $namespace, string $key): ?array
    {
        $this->validateKey($namespace, $key);
        $now = time();
        if ($this->pdo) {
            $stmt = $this->pdo->prepare('SELECT value_json, expires_at, updated_at FROM buffer_entries WHERE namespace = ? AND cache_key = ? LIMIT 1');
            $stmt->execute([$namespace, $this->hash($key)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            try {
                $value = json_decode((string) $row['value_json'], true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $this->quarantineSqlite($namespace, $this->hash($key), (string) $row['value_json']);
                return null;
            }
            return ['value' => $value, 'stored_at' => (int) $row['updated_at'], 'expires_at' => (int) $row['expires_at'], 'stale' => (int) $row['expires_at'] <= $now];
        }
        $path = $this->jsonPath($namespace, $key);
        if (!is_file($path)) return null;
        $decoded = json_decode($this->readLocked($path) ?? '', true);
        if (!is_array($decoded)) { $this->quarantineJson($path); return null; }
        $expires = (int) ($decoded['expires_at'] ?? 0);
        return ['value' => $decoded['value'] ?? null, 'stored_at' => (int) ($decoded['stored_at'] ?? @filemtime($path) ?: $now), 'expires_at' => $expires, 'stale' => $expires <= $now];
    }

    public function delete(string $namespace, string $key): void
    {
        $this->validateKey($namespace, $key);
        if ($this->pdo) {
            $stmt = $this->pdo->prepare('DELETE FROM buffer_entries WHERE namespace = ? AND cache_key = ?');
            $stmt->execute([$namespace, $this->hash($key)]);
            return;
        }
        @unlink($this->jsonPath($namespace, $key));
    }

    /** Remove every buffered entry for one logical cache namespace. */
    public function clearNamespace(string $namespace): int
    {
        $this->validateNamespace($namespace);
        if ($this->pdo) {
            $stmt = $this->pdo->prepare('DELETE FROM buffer_entries WHERE namespace = ?');
            $stmt->execute([$namespace]);
            return $stmt->rowCount();
        }
        $count = 0;
        foreach (glob($this->directory . '/entry-*.json') ?: [] as $path) {
            $decoded = json_decode((string) @file_get_contents($path), true);
            if (is_array($decoded) && ($decoded['namespace'] ?? null) === $namespace && @unlink($path)) {
                $count++;
            }
        }
        return $count;
    }

    /** Return non-expired values in a logical namespace for replay workers. */
    public function list(string $namespace): array
    {
        $this->validateNamespace($namespace);
        $now = time();
        if ($this->pdo) {
            $stmt = $this->pdo->prepare('SELECT value_json, expires_at FROM buffer_entries WHERE namespace = ? ORDER BY updated_at ASC');
            $stmt->execute([$namespace]);
            $values = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ((int) $row['expires_at'] <= $now) {
                    continue;
                }
                $values[] = json_decode((string) $row['value_json'], true, 32, JSON_THROW_ON_ERROR);
            }
            return $values;
        }
        $values = [];
        foreach (glob($this->directory . '/entry-*.json') ?: [] as $path) {
            $decoded = json_decode($this->readLocked($path) ?? '', true);
            if (!is_array($decoded)) { $this->quarantineJson($path); continue; }
            if (($decoded['namespace'] ?? null) === $namespace && (int) ($decoded['expires_at'] ?? 0) > $now) {
                $values[] = $decoded['value'] ?? null;
            }
        }
        return $values;
    }

    public function prune(): int
    {
        $now = time();
        if ($this->pdo) {
            $stmt = $this->pdo->prepare('DELETE FROM buffer_entries WHERE expires_at <= ?');
            $stmt->execute([$now]);
            return $stmt->rowCount();
        }
        $count = 0;
        foreach (glob($this->directory . '/entry-*.json') ?: [] as $path) {
            $decoded = json_decode((string) @file_get_contents($path), true);
            if (!is_array($decoded) || (int) ($decoded['expires_at'] ?? 0) <= $now) { if (@unlink($path)) $count++; }
        }
        return $count;
    }

    /** Return non-sensitive operational health metadata for maintenance checks. */
    public function health(): array
    {
        $permissions = @fileperms($this->directory);
        $permissionsOk = $permissions === false || (($permissions & 0777) === 0700);
        $writable = is_writable($this->directory);
        return [
            'status' => $writable && $permissionsOk ? 'ok' : 'degraded',
            'storage' => $this->pdo ? 'sqlite' : 'json',
            'directory_writable' => $writable,
            'permissions_ok' => $permissionsOk,
        ];
    }

    private function connect(): void
    {
        if (!extension_loaded('pdo_sqlite')) return;
        try {
            $this->pdo = new PDO('sqlite:' . $this->directory . '/buffers.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->pdo->exec('PRAGMA busy_timeout = 3000');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS buffer_entries (namespace TEXT NOT NULL, cache_key TEXT NOT NULL, value_json TEXT NOT NULL, expires_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, PRIMARY KEY(namespace, cache_key))');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS buffer_quarantine (namespace TEXT NOT NULL, cache_key TEXT NOT NULL, value_json TEXT NOT NULL, quarantined_at INTEGER NOT NULL)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_buffer_entries_expiry ON buffer_entries(expires_at)');
            @chmod($this->directory . '/buffers.sqlite', 0600);
        } catch (\Throwable) { $this->pdo = null; }
    }

    private function validateKey(string $namespace, string $key): void
    {
        $this->validateNamespace($namespace);
        if ($key === '' || strlen($key) > 500) throw new RuntimeException('Invalid local buffer key.');
    }

    private function validateNamespace(string $namespace): void
    {
        $sensitive = preg_match('/(learner|student|health|medical|finance|payroll|credential|secret|token)/i', $namespace) === 1;
        $approvedMetadataNamespace = $namespace === 'ai.provider.health';
        if ($namespace === '' || strlen($namespace) > 120 || preg_match('/^[a-z0-9_.:-]+$/i', $namespace) !== 1 || ($sensitive && !$approvedMetadataNamespace)) {
            throw new RuntimeException('Invalid or sensitive local buffer namespace.');
        }
    }

    private function quarantineSqlite(string $namespace, string $key, string $value): void
    {
        if (!$this->pdo) return;
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO buffer_quarantine (namespace, cache_key, value_json, quarantined_at) VALUES (?, ?, ?, ?)')->execute([$namespace, $key, $value, time()]);
            $this->pdo->prepare('DELETE FROM buffer_entries WHERE namespace = ? AND cache_key = ?')->execute([$namespace, $key]);
            $this->pdo->commit();
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        }
    }

    private function quarantineJson(string $path): void
    {
        $dir = $this->directory . '/quarantine';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        $target = $dir . '/' . pathinfo($path, PATHINFO_FILENAME) . '-' . time() . '.json';
        if (!@rename($path, $target)) @unlink($path);
        @chmod($target, 0600);
    }

    private function readLocked(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if (!$handle || !flock($handle, LOCK_SH)) { if (is_resource($handle)) fclose($handle); return null; }
        try { $raw = stream_get_contents($handle); return is_string($raw) ? $raw : null; }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    private function hash(string $key): string { return hash('sha256', $key); }
    private function jsonPath(string $namespace, string $key): string { return $this->directory . '/entry-' . hash('sha256', $namespace . "\0" . $key) . '.json'; }
}
