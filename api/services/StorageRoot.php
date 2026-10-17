<?php

declare(strict_types=1);

namespace App\API\Services;

use RuntimeException;

/** Resolve subsystem-owned paths beneath the shared, non-authoritative buffer root. */
final class StorageRoot
{
    private static ?string $root = null;

    /**
     * Subsystem namespaces beneath the shared root. PHP provisions them at
     * runtime so PHP, Python and Node never require a manual setup step.
     */
    private const RUNTIME_NAMESPACES = [
        'php',
        'php/file_cache',
        'php/response_cache',
        'php/cache',
        'php/buffers',
        'php/automations',
        'php/ai_behavior',
        'php/offline_replay',
        'python',
        'python/data',
        'node',
        'node/bus',
        'node/logs',
    ];

    public static function root(): string
    {
        if (self::$root !== null) {
            return self::$root;
        }

        $configured = getenv('STORAGE_ROOT');
        if (!is_string($configured) || trim($configured) === '') {
            $configured = \App\Config\Config::get('STORAGE_ROOT');
        }

        // The shared root defaults to <project>/storage when the variable is
        // absent; PHP creates the directory tree at runtime, so a missing
        // value is never fatal.
        $root = is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : dirname(__DIR__, 2) . '/storage';

        if ($root[0] !== DIRECTORY_SEPARATOR) {
            throw new RuntimeException('STORAGE_ROOT must be an absolute path.');
        }

        self::$root = rtrim($root, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
        return self::$root;
    }

    /** Create the shared root and every subsystem namespace when missing. */
    public static function ensureRuntimeDirectories(): void
    {
        $directories = array_merge(
            [self::root()],
            array_map(
                static fn(string $relative): string => self::path($relative),
                self::RUNTIME_NAMESPACES
            )
        );

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }
    }

    public static function path(string $relative = ''): string
    {
        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === '') {
            return self::root();
        }

        if (str_starts_with($relative, '/') || str_contains($relative, "\0")) {
            throw new RuntimeException('Storage path must be relative to STORAGE_ROOT.');
        }

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Storage path contains an invalid segment.');
            }
        }

        return self::root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
