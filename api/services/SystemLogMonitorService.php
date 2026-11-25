<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;
use App\Database\Database;
use PDO;

/**
 * Round-the-clock log watch for the system administrator.
 *
 * The ticker calls the worker endpoint every few seconds. Each run reads only
 * the bytes appended since the last run to the error, audit, authentication
 * and client journals (errors.log, security.log, audit.log, auth.log,
 * client.log and today's php-errors file) plus the Python platform journals
 * (python_platform/logs/*.log) and the Node gateway log
 * (node_platform/server.log), grades every new entry and alerts the system
 * administrators:
 *
 *   critical  fatal/critical errors, database down, disk full, memory
 *             exhausted, or any one problem repeating fast (a spike):
 *             SMS and email at once;
 *   error     application errors and security warnings: an email digest at
 *             most every DIGEST_MINUTES;
 *   warning   PHP warnings: included in the digest, never SMS.
 *
 * Floods are impossible by design: one alert per problem signature per
 * COOLDOWN_MINUTES, at most SMS_PER_HOUR text messages an hour, and the
 * monitor ignores its own entries so a failed alert cannot loop.
 *
 * Positions and alert history live in a small state file beside the logs
 * (state, not a log). Recent findings are kept there for the system admin
 * assistant and the GET endpoint. Nothing here reads personal records;
 * journal messages are already redacted by Logger.
 */
final class SystemLogMonitorService
{
    private const MAX_BYTES_PER_FILE = 524288; // per run, so a log storm cannot stall the worker
    private const COOLDOWN_MINUTES = 30;
    private const DIGEST_MINUTES = 15;
    private const SMS_PER_HOUR = 6;
    private const SPIKE_COUNT = 20;          // same signature this many times in one run = critical
    private const RECENT_KEEP = 50;
    private const STATE_FILE = '.log_monitor_state.json';
    private const DEVICE_CHECK_SECONDS = 600;   // anomaly sweep cadence
    private const DEVICE_CHECK_CATEGORIES = ['http', 'client', 'audit', 'device'];

    private const CRITICAL_PATTERNS = [
        '/\b(PHP )?(Fatal error|Parse error)\b/i' => 'PHP fatal error',
        '/Uncaught (Error|Exception|TypeError)/i' => 'Uncaught exception',
        '/SQLSTATE\[HY000\] \[(2002|2003|2006|1045)\]|MySQL server has gone away|Connection refused/i' => 'Database unavailable',
        '/Too many connections/i' => 'Database connection limit reached',
        '/No space left on device|disk (is )?full/i' => 'Disk full',
        '/Allowed memory size .* exhausted|Out of memory/i' => 'Memory exhausted',
    ];

    private string $logDir;
    /** @var callable(string $channel, array $recipients, string $subject, string $body): bool */
    private $sender;
    /** @var callable(): int */
    private $clock;
    private ?PDO $pdo;
    private ?array $recipientsOverride;

    public function __construct(
        ?string $logDir = null,
        ?callable $sender = null,
        ?callable $clock = null,
        ?PDO $pdo = null,
        ?array $recipients = null
    ) {
        $this->logDir = $logDir ?? dirname(FileLogger::path('errors'));
        $this->sender = $sender ?? [$this, 'deliver'];
        $this->clock = $clock ?? static fn (): int => time();
        $this->pdo = $pdo;
        $this->recipientsOverride = $recipients;
    }

    /** One monitoring pass. Returns counts for the worker response. */
    public function run(): array
    {
        $now = ($this->clock)();
        $state = $this->loadState();
        // First time a watched source is covered (fresh install or a newly
        // added journal), seed its offset at end-of-file so history is never
        // replayed as one stale digest. php-errors is exempt: each daily file
        // is genuinely new and readNew() reads it from the top.
        $covered = (array) ($state['covered'] ?? []);
        foreach ($this->watchedFiles($now) as $name => $path) {
            if (isset($covered[$name])) {
                continue;
            }
            $covered[$name] = true;
            if ($name !== 'php-errors' && is_file($path) && is_readable($path)) {
                $state['offsets'][$name . ':' . basename($path)] = (int) filesize($path);
            }
        }
        $state['covered'] = $covered;
        $findings = [];
        foreach ($this->watchedFiles($now) as $name => $path) {
            foreach ($this->readNew($path, $name, $state) as $line) {
                $finding = $this->classify($line, $name);
                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        }

        // Periodic device/account anomaly sweep (rate-limit evasion signals).
        foreach ($this->deviceAnomalyFindings($now, $state) as $f) {
            $findings[] = $f;
        }

        // Group by signature; a fast repeat is a spike and becomes critical.
        $groups = [];
        foreach ($findings as $f) {
            $key = $f['signature'];
            if (!isset($groups[$key])) {
                $groups[$key] = $f + ['count' => 0];
            }
            $groups[$key]['count']++;
            if ($groups[$key]['count'] >= self::SPIKE_COUNT && $groups[$key]['severity'] !== 'critical') {
                $groups[$key]['severity'] = 'critical';
                $groups[$key]['title'] = 'Spike: ' . $groups[$key]['title'];
            }
        }

        $sent = ['sms' => 0, 'email' => 0];
        foreach ($groups as $key => $g) {
            $state['recent'][] = [
                'at' => date('c', $now), 'severity' => $g['severity'], 'title' => $g['title'],
                'detail' => $g['detail'], 'source' => $g['source'], 'count' => $g['count'],
            ];
            if ($g['severity'] === 'critical') {
                if ($this->coolingDown($state, $key, $now)) {
                    continue;
                }
                $state['last_alert'][$key] = $now;
                $sent = $this->alertCritical($g, $state, $now, $sent);
            } else {
                $state['digest'][$key] = [
                    'severity' => $g['severity'], 'title' => $g['title'], 'detail' => $g['detail'],
                    'source' => $g['source'],
                    'count' => (int) (($state['digest'][$key]['count'] ?? 0) + $g['count']),
                ];
            }
        }
        if (!empty($state['digest']) && $now - (int) ($state['last_digest'] ?? 0) >= self::DIGEST_MINUTES * 60) {
            if ($this->send('email', 'Kingsway system digest: ' . count($state['digest']) . ' issue(s)', $this->digestBody($state['digest'], $now))) {
                $sent['email']++;
            }
            $state['digest'] = [];
            $state['last_digest'] = $now;
        }

        $state['recent'] = array_slice($state['recent'] ?? [], -self::RECENT_KEEP);
        $state['last_run'] = $now;
        $this->saveState($state);
        return ['findings' => count($findings), 'problems' => count($groups), 'sms_sent' => $sent['sms'], 'emails_sent' => $sent['email']];
    }

    /** Recent findings and health of the monitor itself, for admins and the assistant. */
    public function status(): array
    {
        $state = $this->loadState();
        $now = ($this->clock)();
        $lastRun = (int) ($state['last_run'] ?? 0);
        return [
            'last_run' => $lastRun > 0 ? date('c', $lastRun) : null,
            'running' => $lastRun > 0 && ($now - $lastRun) < 120,
            'pending_digest' => array_values($state['digest'] ?? []),
            'recent' => array_reverse($state['recent'] ?? []),
        ];
    }

    // ------------------------------------------------------------------

    /** @return array<string,string> name => path */
    private function watchedFiles(int $now): array
    {
        $files = [
            'errors' => $this->logDir . '/errors.log',
            'security' => $this->logDir . '/security.log',
            'audit' => $this->logDir . '/audit.log',
            'auth' => $this->logDir . '/auth.log',
            'client' => $this->logDir . '/client.log',
            'php-errors' => $this->logDir . '/php-errors-' . date('Y-m-d', $now) . '.log',
        ];
        // Python platform journals (JSON-lines) and the Node gateway log live
        // outside logs/<env>; they are watched with the same offset logic.
        $root = dirname(dirname($this->logDir));
        $pyDir = $root . '/python_platform/logs';
        foreach (['ticker', 'ticker.out', 'ai_generation', 'ai_behavior', 'communications', 'document_generation', 'ai_performance', 'reads'] as $name) {
            $files['py-' . $name] = $pyDir . '/' . $name . (str_ends_with($name, '.out') ? '' : '.log');
        }
        $files['node-server'] = $root . '/node_platform/server.log';
        return $files;
    }

    /** New complete lines since the stored offset (handles rotation and truncation). */
    private function readNew(string $path, string $name, array &$state): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $size = (int) filesize($path);
        $key = $name . ':' . basename($path);
        $offset = $state['offsets'][$key] ?? null;
        if ($offset === null) {
            // First ever run: start at the end, never replay old history.
            // After that, a file we have not seen is new (today's php-errors
            // file after midnight, or a journal created later), so read it
            // from the top. Sources covered for the first time are seeded at
            // end-of-file by run() before this is reached.
            $firstRun = empty($state['last_run']);
            $offset = $firstRun ? $size : 0;
        }
        if ($size < $offset) {
            $offset = 0; // rotated or truncated
        }
        if ($size === $offset) {
            $state['offsets'][$key] = $offset;
            return [];
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }
        fseek($handle, $offset);
        $chunk = (string) fread($handle, min(self::MAX_BYTES_PER_FILE, $size - $offset));
        fclose($handle);
        $end = strrpos($chunk, "\n");
        if ($end === false) {
            return []; // wait for the line to finish
        }
        $state['offsets'][$key] = $offset + $end + 1;
        // Forget offsets of earlier daily files.
        foreach (array_keys($state['offsets']) as $k) {
            if (str_starts_with($k, $name . ':') && $k !== $key) {
                unset($state['offsets'][$k]);
            }
        }
        return array_filter(explode("\n", substr($chunk, 0, $end)), static fn ($l) => trim($l) !== '');
    }

    /** @return array{severity:string,title:string,detail:string,source:string,signature:string,location?:string}|null */
    private function classify(string $line, string $source): ?array
    {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            $type = (string) ($entry['type'] ?? '');
            if (str_starts_with($type, 'system_log_monitor')) {
                return null; // never alert on our own entries
            }
            $level = strtolower((string) ($entry['level'] ?? 'info'));
            $rawMessage = $entry['message'] ?? $entry['error'] ?? $entry['msg'] ?? $type;
            $message = trim(is_scalar($rawMessage) ? (string) $rawMessage : (string) json_encode($rawMessage));
            $extra = $entry['error'] ?? '';
            $text = $message . ' ' . (is_array($extra) ? (string) json_encode($extra) : (string) $extra);

            // Node gateway log is pino (JSON or ANSI pretty text); it must be
            // handled before the generic JSON path, which would misread the
            // numeric pino level as a string level.
            if ($source === 'node-server') {
                return $this->classifyNode($line);
            }
            // Audit journal: canonical security actions (failed_login,
            // security_incident, permission_denied, ...). These carry an
            // 'action' rather than an error level.
            if ($source === 'audit') {
                return $this->classifyAudit($entry, $message);
            }
            // Auth journal: login_attempt success/failure and lockouts.
            if ($source === 'auth') {
                return $this->classifyAuth($entry, $message);
            }
            // Browser telemetry: only genuine client errors are findings.
            if ($source === 'client') {
                if (!in_array($level, ['error', 'critical', 'warning'], true)) {
                    return null;
                }
                $severity = in_array($level, ['error', 'critical'], true) ? 'error' : 'warning';
                $route = trim((string) ($entry['client_route'] ?? $entry['route'] ?? ''));
                $title = 'Browser ' . ($level === 'warning' ? 'warning' : 'error') . ($route !== '' ? ' on ' . $route : '');
                return $this->finding($severity, $title, $message, $source);
            }
            // Python platform journals carry level + event/category fields.
            if (str_starts_with($source, 'py-')) {
                return $this->classifyPython($entry, $level, $message, $source);
            }
        } else {
            $level = preg_match('/PHP (Fatal error|Parse error)/i', $line) ? 'critical'
                : (preg_match('/PHP Warning/i', $line) ? 'warning'
                : (preg_match('/PHP (Notice|Deprecated)/i', $line) ? 'info' : 'error'));
            $message = trim(preg_replace('/^\[[^\]]+\]\s*/', '', $line) ?? $line);
            $text = $message;
            $type = '';
        }

        $title = null;
        $severity = null;
        foreach (self::CRITICAL_PATTERNS as $pattern => $label) {
            if (preg_match($pattern, $text)) {
                $severity = 'critical';
                $title = $label;
                break;
            }
        }
        if ($severity === null) {
            if (in_array($level, ['critical', 'alert', 'emergency'], true)) {
                $severity = 'critical';
            } elseif ($level === 'error') {
                $severity = 'error';
            } elseif ($level === 'warning') {
                $severity = $source === 'security' ? 'error' : 'warning';
            } else {
                return null;
            }
            $title = $source === 'security'
                ? 'Security: ' . ($type !== '' ? str_replace('_', ' ', $type) : 'warning')
                : ($type !== '' && $type !== 'error' ? str_replace('_', ' ', $type) : 'Application error');
        }
        return $this->finding($severity, $title, $message, $source);
    }

    /**
     * Audit journal entries carry a canonical security 'action'.
     * Failures and incidents are digest items; lockouts and throttles are
     * errors; anything else is ignored.
     */
    private function classifyAudit(array $entry, string $message): ?array
    {
        $action = strtolower((string) ($entry['action'] ?? ''));
        $map = [
            'failed_login' => ['warning', 'Failed login'],
            'login_failed' => ['warning', 'Failed login'],
            'permission_denied' => ['warning', 'Permission denied'],
            'unauthorized_access' => ['warning', 'Unauthorized access'],
            'policy_violation' => ['warning', 'Policy violation'],
            'security_incident' => ['error', 'Security incident'],
            'account_locked' => ['error', 'Account locked'],
            'account_lockout' => ['error', 'Account locked'],
            'rate_limit_exceeded' => ['error', 'Rate limit exceeded'],
            'rate_limited' => ['error', 'Rate limit exceeded'],
        ];
        if (!isset($map[$action])) {
            // Unknown audit actions still surface when logged at error+.
            $level = strtolower((string) ($entry['level'] ?? 'info'));
            if (!in_array($level, ['error', 'critical', 'warning'], true)) {
                return null;
            }
            return $this->finding(in_array($level, ['error', 'critical'], true) ? 'error' : 'warning',
                'Audit: ' . ($action !== '' ? str_replace('_', ' ', $action) : 'event'), $message, 'audit');
        }
        [$severity, $title] = $map[$action];
        // Include route/user context in the detail when the audit entry has it.
        $where = trim((string) ($entry['route'] ?? ''));
        if ($where !== '') {
            $message .= ' [' . $where . ']';
        }
        return $this->finding($severity, $title, $message, 'audit');
    }

    /** Auth journal: failed attempts are digest warnings; lockouts are errors. */
    private function classifyAuth(array $entry, string $message): ?array
    {
        $type = strtolower((string) ($entry['type'] ?? ''));
        if (str_contains($type, 'lock') || str_contains($type, 'disable') || str_contains($type, 'suspend')) {
            return $this->finding('error', 'Account security event', $message, 'auth');
        }
        if ($type === 'login_attempt') {
            $status = strtolower((string) ($entry['status'] ?? 'failed'));
            if ($status === 'success') {
                return null; // successful logins are noise
            }
            $reason = trim((string) ($entry['failure_reason'] ?? ''));
            return $this->finding('warning', 'Failed login attempt' . ($reason !== '' ? ': ' . $reason : ''), $message, 'auth');
        }
        $level = strtolower((string) ($entry['level'] ?? 'info'));
        if (in_array($level, ['error', 'critical'], true)) {
            return $this->finding('error', 'Auth error', $message, 'auth');
        }
        return null;
    }

    /** Python journals: JSON-lines with level, event, category and error. */
    private function classifyPython(array $entry, string $level, string $message, string $source): ?array
    {
        $event = strtolower((string) ($entry['event'] ?? ''));
        $category = (string) ($entry['category'] ?? '');
        $eventFailed = str_ends_with($event, '_failed') || $event === 'job_failed' || str_contains($event, 'failure');
        $failed = in_array($level, ['error', 'critical', 'warning'], true) || $eventFailed;
        if (!$failed) {
            return null;
        }
        // A *_failed/job_failed event is an operational error even when the
        // journal records it at info level; plain warnings stay warnings.
        $severity = (in_array($level, ['error', 'critical'], true) || $eventFailed) ? 'error' : 'warning';
        $job = trim((string) ($entry['job'] ?? ''));
        $title = 'Python ' . ($category !== '' ? $category : 'platform')
            . ($event !== '' ? ' ' . str_replace('_', ' ', $event) : ' failure')
            . ($job !== '' ? ' (' . $job . ')' : '');
        return $this->finding($severity, $title, $message, $source);
    }

    /** Node gateway log: pino (JSON or pretty) with ANSI escapes. */
    private function classifyNode(string $line): ?array
    {
        $clean = (string) preg_replace('/\x1b\[[0-9;]*m/', '', $line);
        $entry = json_decode($clean, true);
        if (is_array($entry)) {
            // pino numeric levels: 50 = error, 60 = fatal.
            $pino = (int) ($entry['level'] ?? 0);
            if ($pino >= 60) {
                return $this->finding('critical', 'Node fatal', (string) ($entry['msg'] ?? $entry['err']['message'] ?? 'fatal'), 'node-server');
            }
            if ($pino >= 50) {
                return $this->finding('error', 'Node error', (string) ($entry['msg'] ?? $entry['err']['message'] ?? 'error'), 'node-server');
            }
            if ($pino >= 40) {
                return $this->finding('warning', 'Node warning', (string) ($entry['msg'] ?? 'warning'), 'node-server');
            }
            return null;
        }
        if (preg_match('/\b(FATAL|fatal error|uncaught exception)\b/i', $clean)) {
            return $this->finding('critical', 'Node fatal', trim($clean), 'node-server');
        }
        if (preg_match('/\b(ERROR|error:|ERR!)\b/i', $clean) && !preg_match('/level.*info|"level":(30|20|10)\b/', $clean)) {
            return $this->finding('error', 'Node error', trim(mb_substr($clean, 0, 300)), 'node-server');
        }
        if (preg_match('/\b(WARN|warning)\b/i', $clean)) {
            return $this->finding('warning', 'Node warning', trim(mb_substr($clean, 0, 300)), 'node-server');
        }
        return null;
    }

    /** Build a finding with a repeat-friendly signature. */
    private function finding(string $severity, string $title, string $message, string $source): array
    {
        $detail = mb_substr(trim($message), 0, 300);
        // Signature without numbers/ids so repeats group together.
        $signature = hash('sha1', $source . '|' . $title . '|' . preg_replace('/\d+|0x[0-9a-f]+|[0-9a-f]{16,}/i', '#', mb_substr($detail, 0, 160)));
        return ['severity' => $severity, 'title' => $title, 'detail' => $detail, 'source' => $source, 'signature' => $signature];
    }

    /**
     * Device/account anomaly sweep over the recent journal window, run at
     * most every DEVICE_CHECK_SECONDS. IPs rotate legitimately (DHCP, mobile
     * networks), so a single address proves nothing - the signals are
     * aggregate: one device_id on many IPs, one account on many devices or
     * IPs, and one server-computed browser signature (signal_fp) claiming
     * several different device_ids, which is the fingerprint of an attacker
     * rotating the client-asserted X-Device-Id to evade per-device rate
     * limits. Findings flow through the normal grouping/cooldown/digest
     * pipeline; only extreme counts escalate to critical (SMS).
     */
    private function deviceAnomalyFindings(int $now, array &$state): array
    {
        // The sweep reads the shared journals via FileLogger, which always
        // resolves the real log root - not the injected $logDir. When the
        // monitor is pointed at a scratch dir (unit tests) the sweep must
        // not read production journals, so it only runs on the real root.
        if ($this->logDir !== dirname(FileLogger::path('errors'))) {
            return [];
        }
        if ($now - (int) ($state['last_device_check'] ?? 0) < self::DEVICE_CHECK_SECONDS) {
            return [];
        }
        $state['last_device_check'] = $now;

        $ipsPerDevice = [];
        $devicesPerUser = [];
        $ipsPerUser = [];
        $devicesPerSignal = [];
        foreach (self::DEVICE_CHECK_CATEGORIES as $category) {
            foreach (FileLogger::recent($category, 1200) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $ip = trim((string) ($entry['ip'] ?? ''));
                $deviceId = trim((string) ($entry['device_id'] ?? $entry['details']['device_id'] ?? ''));
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
                if ($signal !== '' && $deviceId !== '') {
                    $devicesPerSignal[$signal][$deviceId] = true;
                }
            }
        }

        $findings = [];
        foreach ($ipsPerDevice as $deviceId => $ips) {
            $n = count($ips);
            if ($n >= 4) {
                $label = 'device_' . substr(md5($deviceId), 0, 10);
                $findings[] = $this->finding(
                    $n >= 10 ? 'critical' : 'warning',
                    'Device seen on many IPs',
                    $label . ' used from ' . $n . ' distinct IP addresses in the recent window - possible IP rotation or credential sharing. Review account activity for this device.',
                    'device-signals'
                );
            }
        }
        foreach ($devicesPerUser as $uid => $devices) {
            $n = count($devices);
            if ($n >= 3) {
                $findings[] = $this->finding(
                    $n >= 8 ? 'critical' : 'warning',
                    'Account on many devices',
                    'user_' . $uid . ' authenticated from ' . $n . ' distinct device ids in the recent window - possible shared credentials or account compromise.',
                    'device-signals'
                );
            }
        }
        foreach ($ipsPerUser as $uid => $ips) {
            $n = count($ips);
            if ($n >= 6) {
                $findings[] = $this->finding(
                    $n >= 15 ? 'critical' : 'warning',
                    'Account on many IPs',
                    'user_' . $uid . ' seen from ' . $n . ' distinct IP addresses in the recent window.',
                    'device-signals'
                );
            }
        }
        foreach ($devicesPerSignal as $signal => $devices) {
            $n = count($devices);
            if ($n >= 2) {
                $findings[] = $this->finding(
                    $n >= 5 ? 'critical' : 'warning',
                    'Device-id rotation suspected',
                    'One browser signature (' . $signal . ') claimed ' . $n . ' different device ids - the client-asserted X-Device-Id is being rotated, likely to evade per-device rate limits.',
                    'device-signals'
                );
            }
        }
        return $findings;
    }

    /** Human-readable journal location for alerts (SMS + email). */
    private function locationFor(string $source, string $env): string
    {
        if (str_starts_with($source, 'py-')) {
            return 'python_platform/logs/' . substr($source, 3) . (str_ends_with($source, '.out') ? '' : '.log');
        }
        if ($source === 'node-server') {
            return 'node_platform/server.log';
        }
        if ($source === 'device-signals') {
            return 'aggregate of logs/*/http, client, audit and device journals';
        }
        return 'logs/' . $env . '/' . $source . '.log';
    }

    private function coolingDown(array $state, string $key, int $now): bool
    {
        return $now - (int) ($state['last_alert'][$key] ?? 0) < self::COOLDOWN_MINUTES * 60;
    }

    private function alertCritical(array $g, array &$state, int $now, array $sent): array
    {
        $env = (string) ($_ENV['APP_ENV'] ?? 'production');
        $sms = mb_substr(sprintf(
            'Kingsway %s ALERT: %s (x%d) at %s. Check %s.',
            strtoupper($env), $g['title'], $g['count'], date('H:i', $now), $this->locationFor($g['source'], $env)
        ), 0, 300);
        $hourAgo = $now - 3600;
        $state['sms_times'] = array_values(array_filter($state['sms_times'] ?? [], static fn ($t) => $t > $hourAgo));
        if (count($state['sms_times']) < self::SMS_PER_HOUR && $this->send('sms', 'Kingsway system alert', $sms)) {
            $state['sms_times'][] = $now;
            $sent['sms']++;
        }
        $body = '<p><strong>' . htmlspecialchars($g['title']) . '</strong> occurred ' . (int) $g['count']
            . ' time(s) at ' . date('Y-m-d H:i:s', $now) . ' (' . htmlspecialchars($env) . ').</p>'
            . '<p>Source: ' . htmlspecialchars($this->locationFor($g['source'], $env)) . '</p>'
            . '<pre style="white-space:pre-wrap">' . htmlspecialchars($g['detail']) . '</pre>'
            . '<p>Further alerts for this problem are paused for ' . self::COOLDOWN_MINUTES . ' minutes.</p>';
        if ($this->send('email', 'CRITICAL: ' . $g['title'] . ' (Kingsway ' . $env . ')', $body)) {
            $sent['email']++;
        }
        return $sent;
    }

    private function digestBody(array $digest, int $now): string
    {
        $rows = '';
        foreach ($digest as $d) {
            $rows .= '<tr><td>' . htmlspecialchars(strtoupper($d['severity'])) . '</td><td>' . htmlspecialchars($d['title'])
                . '</td><td>' . (int) $d['count'] . '</td><td>' . htmlspecialchars($d['source']) . '</td><td>'
                . htmlspecialchars(mb_substr($d['detail'], 0, 200)) . '</td></tr>';
        }
        return '<p>System issues logged in the last ' . self::DIGEST_MINUTES . ' minutes (up to ' . date('Y-m-d H:i', $now) . '):</p>'
            . '<table border="1" cellpadding="4" cellspacing="0"><tr><th>Severity</th><th>Problem</th><th>Count</th><th>Log</th><th>Latest detail</th></tr>'
            . $rows . '</table>';
    }

    private function send(string $channel, string $subject, string $body): bool
    {
        $recipients = $this->recipients();
        $list = $channel === 'sms' ? $recipients['phones'] : $recipients['emails'];
        if ($list === []) {
            return false;
        }
        try {
            $ok = (bool) ($this->sender)($channel, $list, $subject, $body);
        } catch (\Throwable $e) {
            $ok = false;
        }
        FileLogger::write('jobs', [
            'type' => 'system_log_monitor_alert', 'channel' => $channel,
            'recipients' => count($list), 'delivered' => $ok, 'subject' => mb_substr($subject, 0, 120),
        ], $ok ? 'info' : 'warning');
        return $ok;
    }

    /** Default delivery through the existing communications stack. */
    private function deliver(string $channel, array $recipients, string $subject, string $body): bool
    {
        $mail = Mailer::to($recipients)->via($channel)->category('system_alert')->body($body);
        if ($channel === 'email') {
            $mail = $mail->subject($subject);
        }
        $result = $mail->send();
        $status = strtolower((string) ($result['status'] ?? ''));
        return !in_array($status, ['error', 'unavailable'], true);
    }

    /**
     * SYSTEM_ALERT_EMAILS / SYSTEM_ALERT_PHONES (comma lists) when set, else
     * the active System Administrators' email and phone.
     *
     * @return array{emails:list<string>,phones:list<string>}
     */
    private function recipients(): array
    {
        if ($this->recipientsOverride !== null) {
            return $this->recipientsOverride + ['emails' => [], 'phones' => []];
        }
        $split = static fn (string $v): array => array_values(array_filter(array_map('trim', explode(',', $v))));
        $emails = $split((string) Config::get('SYSTEM_ALERT_EMAILS', ''));
        $phones = $split((string) Config::get('SYSTEM_ALERT_PHONES', ''));
        if ($emails !== [] || $phones !== []) {
            return $this->recipientsOverride = ['emails' => $emails, 'phones' => $phones];
        }
        try {
            $db = $this->pdo ?? Database::getInstance()->getConnection();
            // Single-table keyed reads (Hard Rule 1).
            $role = $db->prepare("SELECT id FROM roles WHERE name = 'System Administrator' LIMIT 1");
            $role->execute();
            $roleId = (int) $role->fetchColumn();
            $userIds = [];
            if ($roleId > 0) {
                $stmt = $db->prepare('SELECT user_id FROM user_roles WHERE role_id = ?');
                $stmt->execute([$roleId]);
                $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
            $personIds = [];
            if ($userIds !== []) {
                $stmt = $db->query("SELECT person_id FROM users WHERE status = 'active' AND id IN (" . implode(',', $userIds) . ')');
                $personIds = array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
            }
            if ($personIds !== []) {
                $stmt = $db->query('SELECT email, phone FROM persons WHERE id IN (' . implode(',', $personIds) . ')');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    if (filter_var((string) $p['email'], FILTER_VALIDATE_EMAIL)) $emails[] = (string) $p['email'];
                    if (trim((string) $p['phone']) !== '') $phones[] = trim((string) $p['phone']);
                }
            }
        } catch (\Throwable $e) {
            // Database down is itself a critical finding; alerts then need the env lists.
        }
        return $this->recipientsOverride = ['emails' => array_values(array_unique($emails)), 'phones' => array_values(array_unique($phones))];
    }

    private function loadState(): array
    {
        $raw = @file_get_contents($this->logDir . '/' . self::STATE_FILE);
        $state = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($state) ? $state : ['offsets' => [], 'last_alert' => [], 'digest' => [], 'recent' => [], 'sms_times' => []];
    }

    private function saveState(array $state): void
    {
        $path = $this->logDir . '/' . self::STATE_FILE;
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_SLASHES)) !== false) {
            @rename($tmp, $path);
        }
    }
}
