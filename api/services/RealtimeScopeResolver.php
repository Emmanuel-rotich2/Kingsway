<?php

namespace App\API\Services;

use PDO;

/**
 * RealtimeScopeResolver - maps an authenticated user's roles to the real-time
 * buffer scopes they may poll.
 *
 * This is the server-side source of truth for which role-scoped static buffers
 * a user may be handed. It must stay in sync with the school's role model and
 * with the target_scope values writers use when dispatching events.
 *
 * Principle (AGENTS.md): least privilege. Staff are given only the scopes their
 * duty requires; 'all' covers non-sensitive operational ticks shared across
 * authenticated staff. Do not broaden these blindly.
 *
 * Parents/guardians are a SEPARATE audience: a parent-only account must never
 * be handed the staff 'all' channel or any staff duty scope — its capability
 * carries only family:<studentId> channels for the learners linked to it
 * (the engine then enforces that family channels can never receive 'all'
 * broadcasts by construction).
 */
class RealtimeScopeResolver
{
    /** Canonical Parent/Guardian role id (see ParentPortalManager). */
    public const PARENT_ROLE_ID = 73;

    /** Hard ceiling on minted channels per capability (mirrors the controller gate). */
    private const MAX_FAMILY_CHANNELS = 100;
    /**
     * Resolve the set of scopes a user may poll given their role ids.
     *
     * @param int[] $roleIds
     * @param bool  $includeAll Whether the non-sensitive 'all' scope is included
     *                          for every authenticated staff member.
     * @return string[] Normalized scope keys.
     */
    public static function scopesForRoles(array $roleIds, bool $includeAll = true): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        $scopes = [];
        if ($includeAll) {
            $scopes[] = EventBroadcaster::DEFAULT_SCOPE;
        }

        $map = self::roleScopeMap();
        foreach ($roleIds as $roleId) {
            $assigned = $map[$roleId] ?? [];
            foreach ($assigned as $scope) {
                $scopes[] = EventBroadcaster::normalizeScope($scope);
            }
        }

        return array_values(array_unique($scopes));
    }

    /**
     * Role id => scopes map.
     *
     * Role ids follow the repo's canonical ints (see migration 183/188 comments):
     *   2  System Admin (infrastructure, minimal school content)
     *   3  Director / 4 School Administrator
     *   5  Headteacher
     *   6  Deputy Head - Academic
     *   10 Accountant / Finance
     *   12 or 63 Discipline
     *   14 Inventory Manager
     *   16 Cateress
     *   18 Boarding Master
     *   30+ Transport, Health, Counselling, Security, etc.
     *
     * Keep conservative: scope = the smallest set that lets a role do its job.
     *
     * @return array<int, string[]>
     */
    private static function roleScopeMap(): array
    {
        return [
            2  => [],
            3  => ['finance', 'discipline', 'academic', 'boarding'],
            4  => ['finance', 'discipline', 'academic', 'boarding'],
            5  => ['discipline', 'academic', 'boarding'],
            6  => ['academic'],
            10 => ['finance'],
            12 => ['discipline'],
            14 => ['inventory'],
            16 => ['catering'],
            18 => ['boarding'],
            30 => ['health'],
            31 => ['transport'],
            63 => ['discipline'],
        ];
    }

    /**
     * Resolve the scopes for a concrete authenticated user, honouring the
     * parent/staff audience boundary that scopesForRoles() cannot see.
     *
     * Every user — staff and parent alike — also receives its OWN
     * `users:<userId>` channel: job telemetry, AI-draft completions and any
     * future per-user signal reach exactly the account that started them and
     * nobody else (the engine routes by exact channel match).
     *
     * A parent-ONLY account (no role besides Parent) is a family audience:
     * it gets family:<studentId> channels for its linked learners and no
     * staff scope at all — never 'all', never a duty scope. A dual-hatted
     * user (staff who is also a guardian) keeps its staff scopes; the parent
     * portal is a separate surface with separate sessions.
     *
     * @param int[]      $roleIds
     * @param callable|null $childrenFinder Test hook: (PDO, int userId) => int[]
     */
    public static function scopesForUser(PDO $db, array $user, array $roleIds, ?callable $childrenFinder = null): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
        $isParentOnly = $roleIds !== []
            && array_filter($roleIds, static fn(int $role): bool => $role !== self::PARENT_ROLE_ID) === [];

        if ($isParentOnly) {
            $finder = $childrenFinder ?? static fn (PDO $db, int $userId): array => self::linkedStudentIds($db, $userId);
            $studentIds = array_slice($finder($db, $userId), 0, self::MAX_FAMILY_CHANNELS);

            // Minted VERBATIM (family:<id>): the SSE engine matches channels by
            // exact string and its channel pattern allows colons, and
            // parent-facing events are published with the same family:<id>
            // scope. The static buffer normalizer strips colons, so it must
            // not be applied here.
            $channels = [];
            foreach ($studentIds as $studentId) {
                $studentId = (int) $studentId;
                if ($studentId > 0) {
                    $channels[] = 'family:' . $studentId;
                }
            }
            if ($userId > 0) {
                $channels[] = 'users:' . $userId;
            }
            return array_values(array_unique($channels));
        }

        $scopes = self::scopesForRoles($roleIds);
        if ($userId > 0) {
            $scopes[] = 'users:' . $userId;
        }
        return array_values(array_unique($scopes));
    }

    /**
     * Learners linked to a guardian's user account, through the canonical
     * guardian bridge (users.person_id -> parents.person_id -> student_parents).
     */
    private static function linkedStudentIds(PDO $db, int $userId): array
    {
        try {
            $statement = $db->prepare(
                'SELECT sp.student_id'
                . ' FROM ' . ReadReplicaService::qualifiedRef('student_parents') . ' sp'
                . ' JOIN ' . ReadReplicaService::qualifiedRef('parents') . ' pr ON pr.id = sp.parent_id AND pr.status = \'active\''
                . ' JOIN users u ON u.person_id = pr.person_id AND u.status = \'active\''
                . ' WHERE u.id = ?'
                . ' LIMIT ' . self::MAX_FAMILY_CHANNELS
            );
            $statement->execute([$userId]);
            return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $error) {
            // Fail closed: an unreadable link table means no family channels,
            // never a fallback to staff scopes.
            return [];
        }
    }
}
