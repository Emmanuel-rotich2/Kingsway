<?php

namespace App\API\Services;

/**
 * Master pin used after a mutation.
 *
 * The pin is request-local for CLI/internal work and is also carried in a
 * short-lived signed cookie for the next browser request. This makes the
 * policy effective behind a load balancer without using PHP session state as
 * a consistency mechanism.
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
