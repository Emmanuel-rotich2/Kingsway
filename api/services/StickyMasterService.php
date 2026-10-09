<?php

namespace App\API\Services;

/**
 * Legacy, explicit master-pin token support.
 *
 * Do not apply this globally after mutations: explicit qualifiedRef reads
 * remain routed to their materialized reads schema, while strong-consistency
 * decisions must use masterRef()/masterSourceRef() at the query boundary.
 * The signed cookie remains readable for compatibility with browsers that
 * still hold a pin issued by an older application version.
 */
final class StickyMasterService
{
    private const COOKIE = 'kingsway_sticky_master';
    private static ?int $until = null;

    private function __construct()
    {
    }

    public static function pin(int $seconds = 60): void
    {
        self::$until = time() + max(1, min($seconds, 900));
        $expires = self::$until;
        if (PHP_SAPI !== 'cli' && !headers_sent() && defined('JWT_SECRET') && JWT_SECRET !== '') {
            $payload = $expires . '.' . hash_hmac('sha256', (string) $expires, JWT_SECRET);
            setcookie(self::COOKIE, $payload, [
                'expires' => $expires,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function clear(): void
    {
        self::$until = null;
        unset($_COOKIE[self::COOKIE]);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(self::COOKIE, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function isPinned(): bool
    {
        $now = time();
        if (self::$until !== null && self::$until > $now) {
            return true;
        }
        self::$until = null;
        $cookie = (string) ($_COOKIE[self::COOKIE] ?? '');
        if (!defined('JWT_SECRET') || JWT_SECRET === '' || !preg_match('/^(\d+)\.([a-f0-9]{64})$/', $cookie, $matches)) {
            return false;
        }
        $expires = (int) $matches[1];
        if ($expires <= $now || !hash_equals(hash_hmac('sha256', (string) $expires, JWT_SECRET), $matches[2])) {
            return false;
        }
        self::$until = $expires;
        return true;
    }

    public static function remainingSeconds(): int
    {
        return self::isPinned() && self::$until !== null
            ? max(0, self::$until - time())
            : 0;
    }
}
