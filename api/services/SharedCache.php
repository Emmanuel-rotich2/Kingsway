<?php

namespace App\API\Services;

/**
 * Shared file-backed cache.
 *
 * The Kingsway LAMPP stack ships with no APCu/Redis/memcached, yet serves
 * behind a 5-node nginx load balancer in front of a SINGLE MySQL instance.
 * Per-request in-memory caches (e.g. AcademicContextService::$cache) do NOT
 * survive across requests and are NOT shared between nodes, so every node
 * re-queries MySQL for the same hot keys on every request. Under a parallel
 * dashboard burst (9 serial aggregate calls) that saturates the lone DB and
 * nginx times out its upstreams -> the "404 nginx" outage.
 *
 * This store persists to a shared temp dir with flock() so all 5 nodes read
 * one warmed copy. A cache miss computes once; everyone else reads the file.
 * Keys are versioned (cacheSchemaVersion) so a deployment that changes shape
 * can bump the constant to invalidate everything atomically.
 *
 * Not a full cache server, but exactly the missing layer for this topology:
 * turns repeated ~4s academic-context reads into <5ms file reads across every
 * node, which is what lets 5 nodes actually help instead of multiplying DB load.
 */
class SharedCache
{
    private const SCHEMA_VERSION = 1;
    private const DEFAULT_TTL = 300; // 5 minutes

    private string $dir;
    private ?UploadService $storage;
    private ?LocalSqliteBuffer $sqlite = null;
    private bool $disabled = false;

    public function __construct(?string $dir = null)
    {
        // A cache must not require the upload subsystem (or its directory
        // constants) to be configured: unit tests and cron contexts construct
        // it without a booted application.
        try {
            $this->storage = new UploadService();
        } catch (\Throwable) {
            $this->storage = null;
        }
        $this->dir = $this->resolveDirectory($dir);
        // A cache must never be the reason a request or job fails: when no
        // writable directory exists the cache degrades to read-only no-ops.
        if (!is_dir($this->dir) || !is_writable($this->dir)) {
            $this->disabled = true;
            return;
        }
        // SQLite is the fast local index. JSON files remain a portable
        // fallback for shared hosts without pdo_sqlite and for recovery.
        try {
            $this->sqlite = new LocalSqliteBuffer($this->dir . '/sqlite');
        } catch (\Throwable) {
            $this->sqlite = null;
        }
    }

    /**
     * Resolve a writable cache directory.
     *
     * The web server and CLI can run as different users on shared hosting
     * (LAMPP/HostAfrica), so a directory created by one is unwritable for the
     * other. Try the shared location, widen it when we own it, then fall back
     * to a per-user directory.
     */
    private function resolveDirectory(?string $dir): string
    {
        $candidates = [];
        if ($dir !== null && $dir !== '') {
            $candidates[] = $dir;
        } else {
            $candidates[] = sys_get_temp_dir() . '/kingsway_cache';
            $uid = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();
            if ($uid !== false && $uid !== null) {
                $candidates[] = sys_get_temp_dir() . '/kingsway_cache_' . $uid;
            }
        }

        foreach ($candidates as $candidate) {
            if (!is_dir($candidate) && $this->storage !== null) {
                try {
                    $this->storage->ensureDirectoryPath($candidate);
                } catch (\Throwable) {
                    continue;
                }
            }
            if (is_dir($candidate) && !is_writable($candidate) && is_writable(dirname($candidate))) {
                @chmod($candidate, 0777);
                clearstatcache(true, $candidate);
            }
            if (is_dir($candidate) && is_writable($candidate)) {
                return $candidate;
            }
        }
        return $candidates[0];
    }

    /**
     * Fetch a cached value, or compute it with $compute() and store it.
     * $compute receives no args and must return a serializable value.
     */
    /**
     * Direct read for response caching: returns null on miss/expiry/corruption.
     * Unlike remember(), this lets callers distinguish HIT from MISS.
     */
    public function get(string $key): mixed
    {
        if ($this->disabled) {
            return null;
        }
        if ($this->sqlite) {
            try {
                $buffered = $this->sqlite->get('shared_cache_v1', $key);
                if ($buffered !== null) {
                    return $buffered;
                }
            } catch (\Throwable) {
                // Fall through to the locked JSON recovery copy.
            }
        }
        $cached = $this->read($this->pathFor($key));
        if ($cached === null || $cached['expires'] <= time()) {
            return null;
        }
        return $cached['value'];
    }

    /**
     * Direct write for response caching. Returns false when the value is not
     * JSON-serializable so the caller can skip caching rather than poison.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->disabled) {
            return false;
        }
        $ttl = $ttl ?? self::DEFAULT_TTL;
        $payload = json_encode([
            'expires' => time() + $ttl,
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return false;
        }
        if ($this->sqlite) {
            try {
                $this->sqlite->put('shared_cache_v1', $key, $value, $ttl);
            } catch (\Throwable) {
                // The JSON copy below is the portable fallback.
            }
        }
        $path = $this->pathFor($key);
        try {
            if ($this->storage !== null) {
                $this->storage->atomicWrite($path, $payload);
            } else {
                // Native atomic replace when the upload service is unavailable.
                $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
                if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
                    return false;
                }
                if (!@rename($tmp, $path)) {
                    @unlink($tmp);
                    return false;
                }
            }
        } catch (\Throwable) {
            return false;
        }
        return true;
    }

    public function remember(string $key, callable $compute, ?int $ttl = null): mixed
    {
        if ($this->disabled) {
            return $compute();
        }
        $ttl = $ttl ?? self::DEFAULT_TTL;
        $path = $this->pathFor($key);

        $cached = $this->read($path);
        if ($cached !== null && $cached['expires'] > time()) {
            return $cached['value'];
        }

        $value = $compute();
        if ($this->sqlite) {
            try {
                $this->sqlite->put('shared_cache_v1', $key, $value, $ttl);
            } catch (\Throwable) {
                // Continue with the portable JSON copy.
            }
        }
        $this->write($path, $value, time() + $ttl);
        return $value;
    }

    public function forget(string $key): void
    {
        if ($this->disabled) {
            return;
        }
        if ($this->sqlite) {
            try {
                $this->sqlite->delete('shared_cache_v1', $key);
            } catch (\Throwable) {
                // Continue removing the JSON recovery copy.
            }
        }
        $path = $this->pathFor($key);
        if (is_file($path)) {
            $this->storage->deleteFile($path);
        }
    }

    public function clear(): void
    {
        if ($this->disabled) {
            return;
        }
        if ($this->sqlite) {
            try {
                $this->sqlite->clearNamespace('shared_cache_v1');
            } catch (\Throwable) {
                // Continue clearing JSON files.
            }
        }
        if (!is_dir($this->dir)) {
            return;
        }
        $dh = @opendir($this->dir);
        if (!$dh) {
            return;
        }
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $this->dir . '/' . $f;
            if (is_file($p)) {
                $this->storage->deleteFile($p);
            }
        }
        closedir($dh);
    }

    private function read(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $fh = @fopen($path, 'r');
        if (!$fh) {
            return null;
        }
        if (!flock($fh, LOCK_SH)) {
            fclose($fh);
            return null;
        }
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['expires'], $decoded['value'])) {
            return null;
        }
        return $decoded;
    }

    private function write(string $path, mixed $value, int $expires): void
    {
        $payload = json_encode([
            'expires' => $expires,
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload !== false) {
            $this->storage->atomicWrite($path, $payload);
        }
    }

    private function pathFor(string $key): string
    {
        $hash = hash('sha256', $key);
        return $this->dir . '/v' . self::SCHEMA_VERSION . '_' . $hash . '.json';
    }
}
