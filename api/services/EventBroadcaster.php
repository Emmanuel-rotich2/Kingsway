<?php

namespace App\API\Services;

use PDO;

/**
 * EventBroadcaster - static-first, role-scoped real-time engine writer.
 *
 * Shared-hosting safe and privacy-aware architecture:
 *
 * 1. Persists the event to the system_realtime_events outbox.
 * 2. Rebakes a small rolling window of events into role-scoped static buffer
 *    files whose filenames embed a per-scope HMAC slug.
 *
 * Performance: the static buffers are served directly by Apache/Nginx with ZERO
 * PHP entry processes, so thousands of clients can poll them. Payloads are
 * already in the file, so clients never call back into PHP to pick them up —
 * avoiding the "thundering herd" that would blow the ~40 concurrent-process
 * limit on shared hosting.
 *
 * Privacy: a sensitive event (finance, discipline, grades, health) is baked
 * only into the buffer(s) for the scopes that may see it. Each scope's file
 * lives under an UNGUESSABLE slug:
 *
 *     buffers/<scope>_<firstN hex of HMAC(scope, REALTIME_BUFFER_SECRET)>.json
 *
 * The slug is handed to a client only through an authenticated handshake
 * (RealtimeController::getMyBuffer). An outsider cannot guess a role file's URL,
 * so static serving stays zero-PHP while the filename alone is worthless.
 *
 * Honest limits: an unguessable filename is not a per-row ACL. Any client that
 * has been given a scope's slug can read the whole scope buffer (i.e. a logged-in
 * bursar can read other bursars' screens). True row-level access control stays in
 * the authenticated API layer (see AGENTS.md reporting/scoping rules).
 *
 * Buffer semantics (per scope file):
 *   { "scope": <string>, "latest_id": <int>, "timestamp": <int>, "events": [...] }
 */
class EventBroadcaster
{
    /** @var int Rolling window of events baked into each static buffer. */
    private const BUFFER_SIZE = 50;

    /** @var string Default scope for events without an explicit target. */
    public const DEFAULT_SCOPE = 'all';

    /**
     * Dispatch a real-time event to the outbox and rebake the affected buffers.
     *
     * @param PDO    $pdo
     * @param string $domain       Namespaced domain, e.g. 'attendance'.
     * @param string $eventName    Action within the domain, e.g. 'marked'.
     * @param array  $payload      Event payload to bake into the buffer.
     * @param string[] $targetScopes Scopes authorized to receive this event.
     *                               Default ['all'] (authenticated staff only).
     * @return int Newly inserted event id.
     */
    public static function dispatch(PDO $pdo, string $domain, string $eventName, array $payload = [], array $targetScopes = [self::DEFAULT_SCOPE]): int
    {
        if (empty($targetScopes)) {
            $targetScopes = [self::DEFAULT_SCOPE];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO system_realtime_events (domain, event_name, payload, target_scope) VALUES (?, ?, ?, ?)"
        );
        $lastId = 0;
        foreach (array_values(array_unique($targetScopes)) as $scope) {
            $resolved = self::normalizeScope($scope);
            $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt->execute([
                $domain,
                $eventName,
                $encodedPayload,
                $resolved,
            ]);
            $lastId = (int) $pdo->lastInsertId();
            self::appendBufferEvent($resolved, [
                'id' => $lastId,
                'domain' => $domain,
                'event_name' => $eventName,
                'payload' => $payload,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $lastId;
    }

    /**
     * Rebuild a single scope's public static buffer (atomic temp + rename).
     *
     * Failures are swallowed: an unwritable buffer must never break the mutating
     * request that triggered the event.
     */
    public static function rebakeBuffer(PDO $pdo, string $scope = self::DEFAULT_SCOPE, int $size = self::BUFFER_SIZE): void
    {
        $scope = self::normalizeScope($scope);
        try {
            $limit = max(1, min(500, $size));
            $stmt = $pdo->prepare(
                "SELECT id, domain, event_name, payload, created_at
                 FROM system_realtime_events
                 WHERE (target_scope = ? OR target_scope IS NULL)
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$scope]);
            $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

            $events = [];
            foreach ($rows as $row) {
                $decoded = json_decode((string) $row['payload'], true);
                $events[] = [
                    'id'         => (int) $row['id'],
                    'domain'     => $row['domain'],
                    'event_name' => $row['event_name'],
                    'payload'    => is_array($decoded) ? $decoded : null,
                    'created_at' => $row['created_at'],
                ];
            }

            self::writeBufferFile($scope, [
                'scope'     => $scope,
                'latest_id' => $events ? (int) $events[count($events) - 1]['id'] : 0,
                'timestamp' => time(),
                'events'    => $events,
            ]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError("EventBroadcaster::rebakeBuffer({$scope}) failed: " . $e->getMessage());
        }
    }

    /**
     * Public URL path to a scope's static buffer file.
     *
     * This is safe to expose only through the authenticated handshake
     * (getMyBuffer). The path embeds the secret-derived slug.
     */
    public static function bufferUrl(string $scope = self::DEFAULT_SCOPE): string
    {
        $scope = self::normalizeScope($scope);
        $relative = '/buffers/' . self::fileName($scope);
        if (defined('BASE_URL') && \constant('BASE_URL') !== '') {
            // BASE_URL is the full origin + base path (e.g. http://localhost/Kingsway
            // locally, https://kingswaypreparatoryschool.sc.ke at the domain root), so
            // the returned URL stays correct whether the app is served from a
            // subdirectory or from the root.
            return rtrim((string) \constant('BASE_URL'), '/') . $relative;
        }
        // CLI or unconfigured contexts fall back to a webroot-relative path.
        return $relative;
    }

    /**
     * Absolute filesystem path to a scope's static buffer file (webroot root).
     */
    public static function bufferPath(string $scope = self::DEFAULT_SCOPE): string
    {
        return dirname(__DIR__, 2) . '/buffers/' . self::fileName(self::normalizeScope($scope));
    }

    /**
     * Normalize a scope key to a safe identifier.
     */
    public static function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));
        return $scope === '' ? self::DEFAULT_SCOPE : preg_replace('/[^a-z0-9_-]/', '', $scope);
    }

    /**
     * Derive the unguessable file name for a scope.
     */
    private static function fileName(string $scope): string
    {
        $secret = self::secret();
        $epoch = date('Ymd');
        $slug = substr(hash_hmac('sha256', 'realtime-buffer:' . $scope . ':' . $epoch, $secret), 0, 24);
        return $scope . '_' . $epoch . '_' . $slug . '.json';
    }

    /** Remove expired rotated buffers without touching database state. */
    public static function purgeOldBufferFiles(int $retentionHours = 48): int
    {
        $retentionHours = max(24, min(720, $retentionHours));
        $dir = dirname(__DIR__, 2) . '/buffers';
        if (!is_dir($dir)) {
            return 0;
        }
        $cutoff = time() - ($retentionHours * 3600);
        $removed = 0;
        foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (!is_file($file) || basename($file) === '.htaccess' || @filemtime($file) >= $cutoff) {
                continue;
            }
            if (preg_match('/\.(?:json|tmp|lock)$/', $file) && @unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Stable server secret backing the slug derivation. May be overridden via
     * the REALTIME_BUFFER_SECRET env/config; falls back to the JWT secret so it
     * is always present and stable for the deployed environment.
     */
    private static function secret(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }
        if (defined('REALTIME_BUFFER_SECRET') && \constant('REALTIME_BUFFER_SECRET') !== '') {
            $resolved = (string) \constant('REALTIME_BUFFER_SECRET');
        } elseif (defined('JWT_SECRET') && \constant('JWT_SECRET') !== '') {
            $resolved = (string) \constant('JWT_SECRET');
        } else {
            $resolved = 'kingsway-realtime-default';
            \App\API\Services\Logger::legacyError('EventBroadcaster: REALTIME_BUFFER_SECRET/JWT_SECRET unset; using built-in default.');
        }
        return $resolved;
    }

    private static function writeBufferFile(string $scope, array $payload): void
    {
        $dir = dirname(__DIR__, 2) . '/buffers';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) {
                \App\API\Services\Logger::legacyError("EventBroadcaster: cannot create buffers dir {$dir}");
                return;
            }
            @chmod($dir, 0775);
        }
        $path = $dir . '/' . self::fileName($scope);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }
        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $encoded, LOCK_EX) !== false) {
            @rename($tmp, $path);
        }
    }

    /**
     * Append one event under a per-scope lock. This keeps the mutation hot path
     * to one INSERT plus one small file update instead of querying the outbox.
     */
    private static function appendBufferEvent(string $scope, array $event): void
    {
        $dir = dirname(__DIR__, 2) . '/buffers';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $lock = @fopen($dir . '/.' . self::fileName($scope) . '.lock', 'c');
        if ($lock === false) {
            return;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return;
            }
            $path = self::bufferPath($scope);
            $buffer = null;
            if (is_file($path)) {
                $decoded = json_decode((string) @file_get_contents($path), true);
                if (is_array($decoded)) {
                    $buffer = $decoded;
                }
            }
            $events = is_array($buffer['events'] ?? null) ? $buffer['events'] : [];
            $events[] = $event;
            if (count($events) > self::BUFFER_SIZE) {
                $events = array_slice($events, -self::BUFFER_SIZE);
            }
            self::writeBufferFile($scope, [
                'scope' => $scope,
                'latest_id' => (int) $event['id'],
                'timestamp' => time(),
                'events' => $events,
            ]);
            flock($lock, LOCK_UN);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError("EventBroadcaster::appendBufferEvent({$scope}) failed: " . $e->getMessage());
        } finally {
            fclose($lock);
        }
    }
    /** Sync batch of in-scope events after $lastId (RealtimeController GET). */
    public static function syncBatch(PDO $pdo, int $lastId, array $allowedScopes, int $limit): array
    {
        $scopeMarks = implode(',', array_fill(0, count($allowedScopes), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, domain, event_name, payload, created_at
             FROM system_realtime_events
             WHERE id > ? AND (target_scope IN ({$scopeMarks}) OR target_scope IS NULL)
             ORDER BY id ASC
             LIMIT " . max(1, $limit)
        );
        $stmt->execute(array_merge([$lastId], $allowedScopes));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Highest event id currently visible to the caller's scopes (cursor init). */
    public static function latestVisibleId(PDO $pdo, array $allowedScopes): int
    {
        try {
            $allowedScopes = array_values(array_unique(array_map([self::class, 'normalizeScope'], $allowedScopes)));
            if ($allowedScopes === []) return 0;
            $marks = implode(',', array_fill(0, count($allowedScopes), '?'));
            $stmt = $pdo->prepare(
                "SELECT COALESCE(MAX(id), 0) FROM system_realtime_events
                 WHERE target_scope IN ({$marks}) OR target_scope IS NULL"
            );
            $stmt->execute($allowedScopes);
            return (int) $stmt->fetchColumn();
        } catch (\Exception) {
            return 0;
        }
    }

    /** Purge events older than 12 hours (retention endpoint). Returns removed rows. */
    public static function purgeOldEvents(PDO $pdo): int
    {
        $stmt = $pdo->prepare(
            "DELETE FROM system_realtime_events WHERE created_at < NOW() - INTERVAL 12 HOUR"
        );
        $stmt->execute();
        return (int) $stmt->rowCount();
    }

}
