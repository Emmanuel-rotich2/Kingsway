<?php

namespace App\API\Middleware;

use App\API\Services\SharedCache;

/**
 * g4: Server-side response cache for cacheable authenticated GETs.
 *
 * Wraps the router dispatch (see api/index.php). On an allowlisted GET from an
 * authenticated user it serves a cached response copy keyed on
 * user id + path + normalized query string, or caches the freshly computed one.
 *
 * Design constraints:
 * - Per-user keys: row-level scope (getAccessibleStaffScope() etc.) differs
 *   per user, so responses must never be shared across users.
 * - Only success responses are cached; errors are never replayed.
 * - Bypassed entirely for non-GET methods, anonymous requests, and any path
 *   outside the allowlist. Mutating endpoints therefore pay zero overhead.
 * - Only stores/replays the response body array + HTTP code; headers are
 *   regenerated per request (auth/request-id headers must stay fresh).
 * - Emits X-Cache: HIT|MISS so behavior is observable from the client
 *   (frontend precedent: js/api.js reads X-Request-ID the same way).
 * - Storage is SharedCache on a dedicated family directory: flock'd reads and
 *   atomic writes make entries safe to share across the nginx LB nodes that
 *   front the single MySQL instance.
 * - Never caches responses with the X-Kingsway-Worker-Secret path
 *   (/realtime/*) or any path carrying a token in the query string.
 */
final class ResponseCacheMiddleware
{
    /** Endpoint families safe to cache, with TTLs in seconds. Keys are the
     * canonical ControllerRouter form (project and /api prefix stripped, no
     * leading slash) — see allowlistTtl() which ltrims before lookup. */
    private const ALLOWLIST = [
        // Reference data: changes only with curriculum/term admin actions.
        'academic/years-list' => 600,
        'academic/terms-list' => 600,
        'academic/subjects-list' => 600,
        'academic/classes-list' => 300,
        'academic/learning-areas/list' => 600,
        'academic/curriculum-units-list' => 600,
        'academic/teachers-list' => 300,
        'academic/assessments-list' => 120,
        'students/parents/list' => 300,
        'students/family-groups/list' => 300,
        'inventory/suppliers-list' => 300,
        'inventory/items-list' => 300,
        'inventory/categories-list' => 300,
        'finance/fees-bundle-list' => 300,
        'staff/payroll-list' => 60,
        'staff/leaves-list' => 60,

        // Aggregates already offloaded to the read replica (mv_* views):
        // short TTL keeps freshness while absorbing dashboard tab bursts.
        'dashboard/config' => 120,
        'dashboard/insight-brief' => 60,
        'staff/stats' => 120,
        'payments/stats' => 120,
        'finance/fees-annual-summary' => 120,
        'finance/department-budgets-summary' => 120,
        'reports/audit-trail-summary' => 120,
        'staff/payroll-summary' => 60,
        'transport/student-summary' => 120,
        'transport/route-summary' => 120,
        'transport/route-payment-summary' => 120,
        'transport/payment-summary' => 120,
        'transport/bills-summary' => 120,
        'students/family-groups/stats' => 120,
    ];

    /** Query params that must bust a hit if present (cache-poisoning vectors). */
    private const EXCLUDE_PARAMS = ['token', 'api_key', 'secret', 'refresh', 'no_cache', '_'];

    private static ?SharedCache $cache = null;

    public static function handle(callable $dispatch): array
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method !== 'GET') {
            return $dispatch();
        }

        $userId = (int)($_SERVER['auth_user']['user_id'] ?? $_SERVER['auth_user']['id'] ?? 0);
        $path = self::canonicalPath();

        $ttl = self::allowlistTtl($path);
        if ($userId === 0 || $ttl === null) {
            return $dispatch();
        }

        // Refuse to key on secret-bearing query strings; drop them from the
        // key entirely so they can never collide with or poison entries.
        $query = $_GET;
        foreach (self::EXCLUDE_PARAMS as $param) {
            if (array_key_exists($param, $query)) {
                return $dispatch();
            }
        }

        $key = self::cacheKey($userId, $path, $query);

        $cached = self::cache()->get($key);
        if (is_array($cached)
            && is_array($cached['response'] ?? null)
            && ($cached['response']['success'] ?? false) === true) {
            header('X-Cache: HIT');
            http_response_code((int)($cached['http_code'] ?? 200));
            return $cached['response'];
        }

        $response = $dispatch();

        if (is_array($response) && ($response['success'] ?? false) === true) {
            self::cache()->set($key, [
                'response' => $response,
                'http_code' => http_response_code() ?: 200,
            ], $ttl);
        }

        header('X-Cache: MISS');
        return $response;
    }

    private static function cache(): SharedCache
    {
        if (self::$cache === null) {
            self::$cache = new SharedCache(sys_get_temp_dir() . '/kingsway_cache_responses');
        }
        return self::$cache;
    }

    /** Canonical ControllerRouter path: strip project folder + /api, no query. */
    private static function canonicalPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $path = ltrim($path, '/');
        $segments = explode('/', $path);

        if (count($segments) > 2
            && in_array(strtolower($segments[0]), ['kingsway'], true)
            && strtolower($segments[1]) === 'api') {
            $segments = array_slice($segments, 2);
        } elseif (count($segments) > 1 && strtolower($segments[0]) === 'api') {
            $segments = array_slice($segments, 1);
        }

        $normalized = rtrim(implode('/', $segments), '/');
        return '/' . $normalized;
    }

    /** Exact match first (fast path), then prefix form without leading slash. */
    private static function allowlistTtl(string $path): ?int
    {
        if (isset(self::ALLOWLIST[ltrim($path, '/')])) {
            return self::ALLOWLIST[ltrim($path, '/')];
        }
        if (isset(self::ALLOWLIST[$path])) {
            return self::ALLOWLIST[$path];
        }
        return null;
    }

    private static function cacheKey(int $userId, string $path, array $query): string
    {
        ksort($query);
        // Sorted http_build_query normalizes ?b=2&a=1 to a single entry.
        return 'resp:v1:' . $userId . ':' . $path . ':' . http_build_query($query);
    }

    /** Test seam: reset the memoized cache instance between test runs. */
    public static function resetForTests(): void
    {
        self::$cache = null;
    }
}
