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
    private UploadService $storage;

    public function __construct(?string $dir = null)
    {
        $this->storage = new UploadService();
        $this->dir = $dir
            ?? (sys_get_temp_dir() . '/kingsway_cache');
        $this->storage->ensureDirectoryPath($this->dir);
    }

    /**
     * Fetch a cached value, or compute it with $compute() and store it.
     * $compute receives no args and must return a serializable value.
     */
    public function remember(string $key, callable $compute, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? self::DEFAULT_TTL;
        $path = $this->pathFor($key);

        $cached = $this->read($path);
        if ($cached !== null && $cached['expires'] > time()) {
            return $cached['value'];
        }

        $value = $compute();
        $this->write($path, $value, time() + $ttl);
        return $value;
    }

    public function forget(string $key): void
    {
        $path = $this->pathFor($key);
        if (is_file($path)) {
            $this->storage->deleteFile($path);
        }
    }

    public function clear(): void
    {
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
