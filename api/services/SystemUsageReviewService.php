<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use PDO;

/**
 * Deterministic staff-usage and adoption signals for workflow
 * `system.usage_brief`.
 *
 * Answers "which pages, routes, features and roles are staff actually
 * using" from the governed file journals: the http journal (API routes,
 * status, duration, user), the client journal (page_view telemetry with
 * route and user) and the audit journal (feature actions). Role affinity is
 * resolved server-side from users -> user_roles -> roles.
 *
 * The AI payload is aggregate-only: roles are named (they are organisational
 * labels, not personal data) but users appear only as opaque `user_<id>`
 * labels with event counts - names and emails never leave the server.
 */
final class SystemUsageReviewService
{
    public const WORKFLOW = 'system.usage_brief';

    private const MAX_LIST_ITEMS = 12;
    private const MAX_STRING = 180;

    /** @var callable fn(array): array */
    private $analyticsProvider;

    /** @var callable fn(): array */
    private $presenceProvider;

    /** @var callable fn(string,int): array */
    private $journalReader;

    private ?PDO $pdo;

    public function __construct(
        ?callable $analyticsProvider = null,
        ?callable $presenceProvider = null,
        ?callable $journalReader = null,
        ?PDO $pdo = null
    ) {
        $this->analyticsProvider = $analyticsProvider ?: static fn(array $f): array => Logger::analytics($f);
        $this->presenceProvider = $presenceProvider ?: static fn(): array => Logger::activePresence();
        $this->journalReader = $journalReader ?: static fn(string $c, int $l): array => FileLogger::recent($c, $l);
        $this->pdo = $pdo;
    }

    /** Bounded aggregates for the admin UI and the AI payload. */
    public function summary(): array
    {
        $analytics = (array) ($this->analyticsProvider)([]);
        $metrics = (array) ($analytics['metrics'] ?? []);
        $routes = array_slice((array) ($analytics['routes'] ?? []), 0, self::MAX_LIST_ITEMS);
        $users = array_slice((array) ($analytics['users'] ?? []), 0, 15);
        $actions = array_slice((array) ($analytics['actions'] ?? []), 0, self::MAX_LIST_ITEMS);
        $pages = $this->topPages();
        $roles = $this->rolesForUsers(array_map(static fn($u): int => (int) ($u['user_id'] ?? 0), $users));

        return [
            'period' => (array) ($analytics['period'] ?? []),
            'metrics' => $metrics,
            'routes' => $routes,
            'pages' => $pages,
            'actions' => $actions,
            'users' => $users,
            'user_roles' => $roles,
            'active_now' => (int) (($this->presenceProvider)()['active_count'] ?? 0),
            'error_roles' => $this->errorRoles(),
            'device_signals' => $this->deviceSignals(),
        ];
    }

    /** Flatten to the governed string/list shape policy.minimize accepts. */
    public function aiPayload(array $summary): array
    {
        $m = (array) ($summary['metrics'] ?? []);
        $period = (array) ($summary['period'] ?? []);
        $days = 1;
        if (!empty($period['from']) && !empty($period['to'])) {
            $days = max(1, (int) round((strtotime((string) $period['to']) - strtotime((string) $period['from'])) / 86400));
        }

        return [
            'period_days' => (string) $days,
            'total_requests' => (string) (int) ($m['requests'] ?? 0),
            'unique_users' => (string) (int) ($m['unique_users'] ?? 0),
            'unique_sessions' => (string) (int) ($m['unique_sessions'] ?? 0),
            'login_successes' => (string) (int) ($m['login_successes'] ?? 0),
            'login_failures' => (string) (int) ($m['login_failures'] ?? 0),
            'error_count' => (string) (int) ($m['errors'] ?? 0),
            'warning_count' => (string) (int) ($m['warnings'] ?? 0),
            'audit_actions' => (string) (int) ($m['audit_actions'] ?? 0),
            'active_users_now' => (string) (int) ($summary['active_now'] ?? 0),
            'top_routes' => $this->routeLines((array) ($summary['routes'] ?? [])),
            'top_pages' => $this->pageLines((array) ($summary['pages'] ?? [])),
            'top_roles' => $this->roleLines((array) ($summary['user_roles'] ?? [])),
            'top_users' => $this->userLines((array) ($summary['users'] ?? []), (array) ($summary['user_roles'] ?? [])),
            'top_actions' => $this->actionLines((array) ($summary['actions'] ?? [])),
            'error_roles' => $this->errorRoleLines((array) ($summary['error_roles'] ?? [])),
            'device_signals' => $this->deviceSignalLines((array) ($summary['device_signals'] ?? [])),
            'follow_up_intent' => 'Prepare an advisory staff-usage review: which areas are used most and least, adoption gaps worth attention, which roles hit the most errors, and device/IP rotation signals that may indicate credential sharing or abuse. Operators verify before acting; AI must never change permissions, roles or records.',
        ];
    }

    /** page_view events from the client journal -> top pages. */
    private function topPages(): array
    {
        $counts = [];
        foreach ((array) ($this->journalReader)('client', 2000) as $entry) {
            if (!is_array($entry) || (string) ($entry['event'] ?? '') !== 'page_view') {
                continue;
            }
            $page = trim((string) ($entry['client_route'] ?? $entry['route'] ?? ''));
            if ($page === '') {
                continue;
            }
            $counts[$page] = ($counts[$page] ?? 0) + 1;
        }
        arsort($counts);
        return array_map(
            static fn(string $page, int $n): array => ['page' => $page, 'views' => $n],
            array_keys(array_slice($counts, 0, self::MAX_LIST_ITEMS, true)),
            array_slice($counts, 0, self::MAX_LIST_ITEMS, true)
        );
    }

    /** user_id => role names, single keyed lookup. */
    private function rolesForUsers(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter($userIds)));
        if ($ids === [] || $this->pdo === null) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT u.id AS user_id, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR \', \') AS roles'
                . ' FROM users u'
                . ' JOIN user_roles ur ON ur.user_id = u.id'
                . ' JOIN roles r ON r.id = ur.role_id'
                . ' WHERE u.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
                . ' GROUP BY u.id'
            );
            $stmt->execute($ids);
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(int) $row['user_id']] = (string) ($row['roles'] ?? '');
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private function routeLines(array $routes): array
    {
        return array_map(static function (array $r): string {
            $route = mb_substr((string) ($r['route'] ?? 'unknown'), 0, self::MAX_STRING);
            return $route . ' — ' . (int) ($r['requests'] ?? 0) . ' requests, ' . (int) ($r['failures'] ?? 0) . ' failures';
        }, $routes);
    }

    private function pageLines(array $pages): array
    {
        return array_map(static fn(array $p): string =>
            mb_substr((string) ($p['page'] ?? ''), 0, self::MAX_STRING) . ' — ' . (int) ($p['views'] ?? 0) . ' views',
            $pages);
    }

    private function roleLines(array $userRoles): array
    {
        $counts = [];
        foreach ($userRoles as $roles) {
            foreach (array_filter(array_map('trim', explode(',', (string) $roles))) as $role) {
                $counts[$role] = ($counts[$role] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_map(static fn(string $role, int $n): string => $role . ' — ' . $n . ' active user(s)',
            array_keys(array_slice($counts, 0, self::MAX_LIST_ITEMS, true)),
            array_slice($counts, 0, self::MAX_LIST_ITEMS, true));
    }

    private function userLines(array $users, array $userRoles): array
    {
        return array_map(static function (array $u) use ($userRoles): string {
            $id = (int) ($u['user_id'] ?? 0);
            $roles = trim((string) ($userRoles[$id] ?? ''));
            return 'user_' . $id . ($roles !== '' ? ' (' . mb_substr($roles, 0, 80) . ')' : '')
                . ' — ' . (int) ($u['events'] ?? 0) . ' events, ' . (int) ($u['requests'] ?? 0) . ' requests';
        }, $users);
    }

    /**
     * Failed API requests grouped by the ROLE of the user who hit them.
     * Triage is role-level on purpose: "Bursar accounts hit 40% of fee-route
     * failures" is actionable; a named user is not needed to route the fix.
     * Entries predating identity logging land in the unattributed bucket.
     */
    private function errorRoles(): array
    {
        $perUser = [];
        $inferredUsers = [];
        $unattributed = 0;
        $anonymousFailures = [];
        foreach ((array) ($this->journalReader)('http', 2000) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $status = (int) ($entry['status'] ?? 0);
            $isFailure = ($entry['success'] ?? null) === false || $status >= 400;
            if (!$isFailure) {
                continue;
            }
            $uid = (int) ($entry['user_id'] ?? 0);
            if ($uid <= 0) {
                $anonymousFailures[] = [
                    'ip' => trim((string) ($entry['ip'] ?? '')),
                    'ua' => trim((string) ($entry['user_agent'] ?? '')),
                ];
                continue;
            }
            $perUser[$uid] = ($perUser[$uid] ?? 0) + 1;
        }

        // Historical entries predate user_id logging (fixed in Logger), so
        // attribute them by matching ip + user_agent to the most recent
        // successful login in the auth journal. IP alone is not enough on a
        // shared school NAT; the pair is a strong same-device signal. These
        // attributions are marked "(incl. inferred)" so reviewers know they are
        // reconstructed, not recorded.
        if ($anonymousFailures !== []) {
            $loginUsers = $this->loginUsersByIpUa();
            foreach ($anonymousFailures as $f) {
                if ($f['ip'] === '' || $f['ua'] === '') {
                    $unattributed++;
                    continue;
                }
                $uid = $loginUsers[$f['ip'] . '|' . $f['ua']] ?? 0;
                if ($uid <= 0) {
                    $unattributed++;
                    continue;
                }
                $perUser[$uid] = ($perUser[$uid] ?? 0) + 1;
                $inferredUsers[$uid] = true;
            }
        }

        $roles = $this->rolesForUsers(array_keys($perUser));
        $byRole = [];
        foreach ($perUser as $uid => $n) {
            $role = trim((string) ($roles[$uid] ?? ''));
            $key = ($role !== '' ? $role : 'user_' . $uid)
                . (!empty($inferredUsers[$uid]) ? ' (incl. inferred)' : '');
            $byRole[$key] = ($byRole[$key] ?? 0) + $n;
        }
        arsort($byRole);
        $out = [];
        foreach (array_slice($byRole, 0, self::MAX_LIST_ITEMS, true) as $label => $n) {
            $out[] = ['label' => (string) $label, 'failures' => (int) $n];
        }
        if ($unattributed > 0) {
            $out[] = ['label' => 'unattributed (anonymous/pre-auth)', 'failures' => $unattributed];
        }
        return $out;
    }

    /**
     * ip|user_agent => user_id from the most recent successful logins in the
     * auth journal. Used only to attribute journal entries written before
     * request-context identity logging was fixed.
     */
    private function loginUsersByIpUa(): array
    {
        $map = [];
        foreach ((array) ($this->journalReader)('auth', 2000) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['type'] ?? '') !== 'login_attempt' || ($entry['status'] ?? '') !== 'success') {
                continue;
            }
            $uid = (int) ($entry['user_id'] ?? 0);
            $ip = trim((string) ($entry['ip'] ?? ''));
            $ua = trim((string) ($entry['user_agent'] ?? ''));
            if ($uid > 0 && $ip !== '' && $ua !== '') {
                $map[$ip . '|' . $ua] = $uid; // chronological: later logins win
            }
        }
        return $map;
    }

    /**
     * IP-rotation and multi-device signals. IPs rotate legitimately, so a
     * single device or account seen on several addresses is a signal to
     * review - not proof of abuse. device_id is the persistent
     * localStorage-backed id; entries without it fall back to nothing (they
     * simply do not contribute).
     */
    private function deviceSignals(): array
    {
        $ipsPerDevice = [];
        $devicesPerUser = [];
        $ipsPerUser = [];
        $devicesPerSignal = [];
        $signalsPerDevice = [];
        foreach (['http', 'client', 'audit'] as $category) {
            foreach ((array) ($this->journalReader)($category, 1500) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $ip = trim((string) ($entry['ip'] ?? ''));
                $deviceId = trim((string) ($entry['device_id'] ?? ''));
                $signal = trim((string) ($entry['signal_fp'] ?? ''));
                $uid = (int) ($entry['user_id'] ?? 0);
                if ($deviceId !== '' && $ip !== '') {
                    $ipsPerDevice[$deviceId][$ip] = true;
                }
                if ($uid > 0 && $deviceId !== '') {
                    $devicesPerUser[$uid][$deviceId] = true;
                }
                if ($uid > 0 && $ip !== '') {
                    $ipsPerUser[$uid][$ip] = true;
                }
                // A browser signature claiming several different device_ids
                // is the signature of device_id rotation; one device_id
                // showing several signatures is a shared or unstable id.
                if ($signal !== '' && $deviceId !== '') {
                    $devicesPerSignal[$signal][$deviceId] = true;
                    $signalsPerDevice[$deviceId][$signal] = true;
                }
            }
        }

        $rotating = [];
        foreach ($ipsPerDevice as $deviceId => $ips) {
            $count = count($ips);
            if ($count > 1) {
                $rotating[] = ['device' => 'device_' . substr(md5($deviceId), 0, 10), 'distinct_ips' => $count];
            }
        }
        usort($rotating, static fn(array $a, array $b): int => $b['distinct_ips'] <=> $a['distinct_ips']);
        $rotating = array_slice($rotating, 0, 8);

        $multiDeviceUsers = 0;
        foreach ($devicesPerUser as $devices) {
            if (count($devices) > 1) {
                $multiDeviceUsers++;
            }
        }
        $manyIpUsers = 0;
        foreach ($ipsPerUser as $ips) {
            if (count($ips) > 3) {
                $manyIpUsers++;
            }
        }

        $rotatingSignals = [];
        foreach ($devicesPerSignal as $signal => $devices) {
            $count = count($devices);
            if ($count > 1) {
                $rotatingSignals[] = ['signal' => $signal, 'claimed_devices' => $count];
            }
        }
        usort($rotatingSignals, static fn(array $a, array $b): int => $b['claimed_devices'] <=> $a['claimed_devices']);
        $multiSignalDevices = 0;
        foreach ($signalsPerDevice as $signals) {
            if (count($signals) > 1) {
                $multiSignalDevices++;
            }
        }

        return [
            'devices_with_multiple_ips' => $rotating,
            'users_with_multiple_devices' => $multiDeviceUsers,
            'users_with_many_ips' => $manyIpUsers,
            'signals_with_multiple_devices' => array_slice($rotatingSignals, 0, 8),
            'devices_with_multiple_signals' => $multiSignalDevices,
        ];
    }

    private function errorRoleLines(array $errorRoles): array
    {
        return array_map(static fn(array $r): string =>
            mb_substr((string) ($r['label'] ?? ''), 0, self::MAX_STRING)
            . ' — ' . (int) ($r['failures'] ?? 0) . ' failed request(s)',
            $errorRoles);
    }

    private function deviceSignalLines(array $signals): array
    {
        $lines = [
            'devices seen on multiple IPs: ' . count((array) ($signals['devices_with_multiple_ips'] ?? [])),
            'users on multiple devices: ' . (int) ($signals['users_with_multiple_devices'] ?? 0),
            'users on many IPs (>3): ' . (int) ($signals['users_with_many_ips'] ?? 0),
            'browser signatures claiming multiple device ids: ' . count((array) ($signals['signals_with_multiple_devices'] ?? [])),
            'devices showing multiple browser signatures: ' . (int) ($signals['devices_with_multiple_signals'] ?? 0),
        ];
        foreach ((array) ($signals['devices_with_multiple_ips'] ?? []) as $d) {
            $lines[] = (string) ($d['device'] ?? 'device') . ' — ' . (int) ($d['distinct_ips'] ?? 0) . ' distinct IPs';
        }
        foreach ((array) ($signals['signals_with_multiple_devices'] ?? []) as $s) {
            $lines[] = (string) ($s['signal'] ?? 'signal') . ' — claims ' . (int) ($s['claimed_devices'] ?? 0) . ' device ids';
        }
        return $lines;
    }

    private function actionLines(array $actions): array
    {
        return array_map(static function (array $a): string {
            return mb_substr((string) ($a['action'] ?? ''), 0, self::MAX_STRING)
                . ' — ' . (int) ($a['count'] ?? 0);
        }, $actions);
    }
}
