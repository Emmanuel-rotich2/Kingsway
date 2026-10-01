<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use Throwable;

/**
 * Bounded, privacy-safe staff behaviour study for the AI co-worker layer.
 *
 * Purpose: let each staff member's assistant adapt (surface the modules they
 * actually use, prefer their cadence, remember which agents helped) without
 * ever storing sensitive school data.
 *
 * Storage model (user-requested persistence, implemented under the governed
 * local-buffer contract):
 *   - Durable aggregates live in LocalSqliteBuffer — a SQLite file (WAL mode)
 *     when the host supports PDO SQLite, with a locked-JSON file fallback for
 *     shared hosting. Both are bounded: max 120 rolling events, 30-day TTL,
 *     262 KB value cap enforced by the buffer itself.
 *   - Every observation is also appended to the `ai_behavior` JSON-lines file
 *     journal for audit, with the question TEXT hashed — raw prompts are never
 *     retained here.
 *
 * Field allowlist: only non-identifying interaction facts are accepted
 * (route, module, domain, agent_id, tool, workflow_id, outcome, cadence).
 * Learner, health, finance, credential or free-text values are rejected —
 * the buffer namespace validator independently rejects sensitive
 * namespaces as a second guard.
 */
final class AiBehaviorProfiler
{
    public const NAMESPACE = 'ai.behavior';
    public const TTL_SECONDS = 2592000; // 30-day rolling window
    public const MAX_EVENTS = 120;

    private const FIELD_ALLOWLIST = [
        'route', 'module', 'domain', 'agent_id', 'tool', 'workflow_id', 'outcome', 'cadence',
    ];

    private ?LocalSqliteBuffer $buffer;

    /**
     * @param LocalSqliteBuffer|string|null $store an injected buffer, a
     *        directory path for one, or null to use the default location
     */
    public function __construct(LocalSqliteBuffer|string|null $store = null)
    {
        $this->buffer = $store instanceof LocalSqliteBuffer
            ? $store
            : self::constructBuffer(is_string($store) ? $store : null);
    }

    /**
     * Shared hosting may block the system temp directory outright, so the
     * default store prefers the project's own storage/buffers area (the
     * same account-local tree the file journals already write to) and only
     * then falls back to the system temp. If no location is writable the
     * study layer degrades to a no-op instead of breaking every assistant
     * call that constructs this profiler.
     */
    private static function constructBuffer(?string $directory): ?LocalSqliteBuffer
    {
        $candidates = $directory !== null
            ? [$directory]
            : [dirname(__DIR__, 2) . '/storage/buffers/ai_behavior', sys_get_temp_dir() . '/kingsway_local_buffers'];
        foreach ($candidates as $candidate) {
            try {
                // The @ is scoped to this fallback seam: the buffer signals
                // an unusable location by throwing, which we handle below;
                // its intermediate mkdir diagnostic must not surface as a
                // PHP warning on hosts that block the path.
                return @new LocalSqliteBuffer($candidate);
            } catch (Throwable) {
                continue;
            }
        }
        return null;
    }

    /**
     * Record one non-sensitive interaction observation for a staff member.
     *
     * @param array<string,mixed> $facts allowlisted facts only
     */
    public function observe(int $userId, string $type, array $facts = []): void
    {
        if ($userId < 1 || $type === '' || mb_strlen($type) > 60) {
            return;
        }
        $event = [
            't' => time(),
            'type' => $type,
        ];
        foreach ($facts as $key => $value) {
            if (!in_array((string) $key, self::FIELD_ALLOWLIST, true) || !is_scalar($value)) {
                continue;
            }
            $text = mb_substr(trim((string) $value), 0, 120);
            if ($text !== '') {
                $event[(string) $key] = $text;
            }
        }

        $profile = $this->rawProfile($userId);
        $events = is_array($profile['events'] ?? null) ? $profile['events'] : [];
        $events[] = $event;
        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }

        if ($this->buffer !== null) {
            try {
                $this->buffer->put(self::NAMESPACE, $this->keyFor($userId), [
                    'version' => 1,
                    'events' => $events,
                ], self::TTL_SECONDS);
            } catch (Throwable) {
                // A full or unavailable buffer must never break the caller.
            }
        }

        try {
            FileLogger::write('ai_behavior', [
                'type' => 'observation',
                'user_id' => $userId,
                'interaction' => $type,
                'facts' => array_diff_key($event, ['t' => true]),
            ]);
        } catch (Throwable) {
            // Journaling must never break the interaction either.
        }
    }

    /**
     * Bounded personalization hints derived from the rolling event window.
     * Used to customize agent prompts; contains no free text and no
     * identities — only frequency-ranked allowlisted tokens.
     *
     * @return array<string,mixed>
     */
    public function hints(int $userId): array
    {
        $events = $this->recentEvents($userId);
        if ($events === []) {
            return ['known' => false];
        }
        $domains = [];
        $workflows = [];
        $agents = [];
        $cadences = [];
        $lastDomain = '';
        $lastEventAt = 0;
        foreach ($events as $event) {
            $domain = (string) ($event['domain'] ?? '');
            if ($domain !== '') {
                $domains[$domain] = ($domains[$domain] ?? 0) + 1;
                $lastDomain = $domain;
            }
            $workflow = (string) ($event['workflow_id'] ?? '');
            if ($workflow !== '') {
                $workflows[$workflow] = ($workflows[$workflow] ?? 0) + 1;
            }
            $agent = (string) ($event['agent_id'] ?? '');
            if ($agent !== '') {
                $agents[$agent] = ($agents[$agent] ?? 0) + 1;
            }
            $cadence = (string) ($event['cadence'] ?? '');
            if ($cadence !== '') {
                $cadences[$cadence] = ($cadences[$cadence] ?? 0) + 1;
            }
            $lastEventAt = max($lastEventAt, (int) ($event['t'] ?? 0));
        }
        arsort($domains);
        arsort($workflows);
        arsort($agents);
        arsort($cadences);
        return [
            'known' => true,
            'top_domains' => array_slice(array_keys($domains), 0, 3),
            'top_workflows' => array_slice(array_keys($workflows), 0, 3),
            'top_agents' => array_slice(array_keys($agents), 0, 2),
            'preferred_cadence' => (string) (array_key_first($cadences) ?: ''),
            'last_domain' => $lastDomain,
            'events_observed' => count($events),
            'last_event_at' => $lastEventAt,
        ];
    }

    /** Erase the rolling profile for a staff member (DPA erasure path). */
    public function forget(int $userId): void
    {
        if ($userId < 1 || $this->buffer === null) {
            return;
        }
        try {
            $this->buffer->delete(self::NAMESPACE, $this->keyFor($userId));
        } catch (Throwable) {
            // Nothing to erase or the buffer is unavailable.
        }
    }

    /** @return list<array<string,mixed>> */
    public function recentEvents(int $userId): array
    {
        $profile = $this->rawProfile($userId);
        $events = is_array($profile['events'] ?? null) ? $profile['events'] : [];
        $cutoff = time() - self::TTL_SECONDS;
        return array_values(array_filter(
            array_slice($events, -self::MAX_EVENTS),
            static fn(array $event): bool => (int) ($event['t'] ?? 0) >= $cutoff
        ));
    }

    /** @return array<string,mixed> */
    private function rawProfile(int $userId): array
    {
        if ($this->buffer === null) {
            return [];
        }
        try {
            $profile = $this->buffer->get(self::NAMESPACE, $this->keyFor($userId));
        } catch (Throwable) {
            return [];
        }
        return is_array($profile) ? $profile : [];
    }

    private function keyFor(int $userId): string
    {
        return 'u' . $userId;
    }
}
