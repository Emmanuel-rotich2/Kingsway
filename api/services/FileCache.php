<?php

namespace App\API\Services;

/**
 * FileCache - Laravel-like file-backed cache (Cache::remember equivalent).
 *
 * Protects MySQL from redundant heavy queries during peak multi-user load.
 * Values are serialized to compact files under <project-root>/storage/cache/.
 * Each entry carries its own expiry, so a lightweight reader can enforce TTL
 * without scanning the whole cache directory.
 *
 * All read/write operations are guarded with flock/LOCK_EX so concurrent PHP
 * processes from shared hosting cannot corrupt entries. Failures degrade
 * gracefully: on read errors the cached value is ignored (the caller re-runs
 * the callback); on write errors the callback value is still returned.
 */
class FileCache
{
    /** @var string Absolute cache directory (project root /storage/cache). */
    private static $dir;

    /**
     * Return a value from cache or, on miss/expiry, run $callback and store it.
     *
     * @param string   $key      Cache key (must be a stable, namespaced string).
     * @param int      $minutes  Validity window in minutes (must be >= 0).
     * @param callable $callback Produces the value when the cache is cold.
     * @return mixed
     */
    public static function remember(string $key, int $minutes, callable $callback)
    {
        self::ensureDir();
        $filePath = self::path($key);

        $cached = self::read($filePath, $minutes);
        if ($cached !== self::$MISS) {
            return $cached;
        }

        // Serialize cache fills per key. Without this lock, 40 PHP workers
        // missing the same dashboard key would all execute the expensive SQL
        // callback at once (a classic cache stampede).
        $lock = @fopen($filePath . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            $value = $callback();
            self::write($filePath, $value);
            if (is_resource($lock)) {
                fclose($lock);
            }
            return $value;
        }

        try {
            $cached = self::read($filePath, $minutes);
            if ($cached !== self::$MISS) {
                return $cached;
            }
            $value = $callback();
            self::write($filePath, $value);
            return $value;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Read a value from cache only if it exists and has not expired.
     *
     * @return mixed Cached value, or FileCache::MISS() sentinel when cold/absent.
     */
    public static function get(string $key, int $minutes, $default = null)
    {
        self::ensureDir();
        $value = self::read(self::path($key), $minutes);
        return $value === self::$MISS ? $default : $value;
    }

    /**
     * Store a value immediately (overwriting any existing entry).
     */
    public static function put(string $key, $value): void
    {
        self::ensureDir();
        self::write(self::path($key), $value);
    }

    /**
     * Remove a single cache entry.
     */
    public static function forget(string $key): bool
    {
        $file = self::path($key);
        return is_file($file) ? @unlink($file) : false;
    }

    /**
     * Remove all cache entries.
     */
    public static function flush(): void
    {
        self::ensureDir();
        foreach (glob(self::$dir . '/*.cache') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        foreach (glob(self::$dir . '/*.cache.lock') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Whether a stored entry is present and still within its TTL.
     */
    public static function has(string $key, int $minutes): bool
    {
        $file = self::path($key);
        return is_file($file) && self::isFresh($file, $minutes);
    }

    /** @var string Sentinel returned on a missing/expired read. */
    private static $MISS = "\0__KWA_CACHE_MISS__\0";

    private static function read(string $filePath, int $minutes)
    {
        if (!is_file($filePath) || !self::isFresh($filePath, $minutes)) {
            return self::$MISS;
        }
        $fh = @fopen($filePath, 'rb');
        if ($fh === false) {
            return self::$MISS;
        }
        try {
            if (!flock($fh, LOCK_SH)) {
                return self::$MISS;
            }
            $raw = stream_get_contents($fh);
            flock($fh, LOCK_UN);
            if ($raw === false) {
                return self::$MISS;
            }
            $payload = self::decode($raw);
            return $payload === self::$MISS ? self::$MISS : $payload['value'];
        } finally {
            fclose($fh);
        }
    }

    private static function write(string $filePath, $value): void
    {
        $tmp = $filePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $data = self::encode($value);
        $ok = @file_put_contents($tmp, $data, LOCK_EX);
        if ($ok === false) {
            return;
        }
        // Atomic rename so readers never observe a partially-written file.
        if (!@rename($tmp, $filePath)) {
            @unlink($tmp);
        }
    }

    private static function encode($value): string
    {
        $meta = [
            'created' => time(),
            'value' => $value,
        ];
        return @serialize($meta);
    }

    private static function decode(string $raw)
    {
        $stored = @unserialize($raw);
        if (!is_array($stored) || !array_key_exists('value', $stored)) {
            return self::$MISS;
        }
        return $stored;
    }

    private static function isFresh(string $filePath, int $minutes): bool
    {
        $expires = time() - ($minutes * 60);
        return @filemtime($filePath) >= $expires;
    }

    private static function path(string $key): string
    {
        return self::$dir . '/' . md5($key) . '.cache';
    }

    private static function ensureDir(): void
    {
        if (self::$dir !== null) {
            return;
        }
        $dir = StorageRoot::path('php/file_cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            @chmod($dir, 0775);
        }
        self::$dir = $dir;
    }
}
