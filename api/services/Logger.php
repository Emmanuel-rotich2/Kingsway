<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;

/**
 * Logger - the central logging service for the entire application.
 *
 * RULE (product command): ALL logs go to JSON-lines log files under
 * <project-root>/logs/<env>/<category>.log. No runtime telemetry is written to
 * the database. This is the single write/read facade; do not log directly via
 * \App\API\Services\Logger::legacyError() or ad-hoc file_put_contents() in application code.
 *
 * Every entry is enriched with request context (request_id, session_id,
 * user_id, ip, user_agent, route, method) so an auditor can answer "who did
 * what, on which page/route/session, from which machine, at what time".
 *
 * Convenience levels mirror PHP/logcat conventions and are colour-mapped in the
 * UI: info (blue), success (green), warning (amber), error (red), critical
 * (dark red), debug (grey), audit/event (purple).
 *
 * @see FileLogger for the underlying rotation + JSON-lines persistence.
 */
final class Logger
{
    /** Ordered severity levels (index 0 = lowest). */
    public const LEVELS = ['debug', 'info', 'success', 'warning', 'error', 'critical', 'audit', 'event'];

    /** Mapping of presentation level -> canonical severity for filtering. */
    private const SEVERITY = [
        'debug' => 0,
        'info' => 1,
        'success' => 1,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'audit' => 1,
        'event' => 1,
    ];

    /** Lazily-built request context for the current PHP execution. */
    private static ?array $context = null;

    /**
     * Optional explicit scope override, used by background jobs/queues so their
     * async log lines carry the originating request ID / browser session.
     * Cleared with resetScope() at job end.
     *
     * @var array<string,mixed>
     */
    private static array $scope = [];

    public static function setScope(array $scope): void
    {
        self::$scope = $scope;
    }

    public static function resetScope(): void
    {
        self::$scope = [];
    }

    private static function requestContext(): array
    {
        if (self::$context === null) {
            $ctx = [
                'request_id' => $_SERVER['REQUEST_ID'] ?? null,
                'session_id' => $_SERVER['auth_session_id'] ?? null,
                'user_id' => $_SERVER['auth_user']['user_id'] ?? $_SERVER['auth_user']['id'] ?? null,
                'browser_session_id' => $_SERVER['browser_session_id'] ?? null,
                'ip' => self::clientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'route' => $_SERVER['REQUEST_URI'] ?? null,
            ];

            // Redact obvious secrets from anything that might be echoed into a
            // request/route string (tokens, passwords, keys).
            foreach ($ctx as $k => $v) {
                if (is_string($v)) {
                    $v = preg_replace('/[?&](password|token|key|secret|auth)=[^&]*/i', '$1=***', $v);
                    $ctx[$k] = $v;
                }
            }

            // Never log full tokens/passwords that may ride in query strings.
            $ctx['route'] = self::redact($ctx['route']);
            self::$context = array_filter($ctx, static fn ($v) => $v !== null && $v !== '');
        }

        // Merge any explicit scope overrides (async jobs) on top of the
        // ambient request context without mutating the cached context.
        if (self::$scope !== []) {
            return array_merge(self::$context, self::$scope);
        }
        return self::$context;
    }

    public static function resetContext(): void
    {
        self::$context = null;
    }

    private static function clientIp(): ?string
    {
        // Same trusted-proxy rules as IP access control: Client-IP and the
        // left end of X-Forwarded-For are client-chosen and must not be what
        // the security journal records.
        $ip = IpAccessControlService::resolveClientIp();
        return $ip !== '' ? $ip : ($_SERVER['REMOTE_ADDR'] ?? null);
    }

    /**
     * Redact common secret parameter values in a query string.
     */
    private static function redact(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return preg_replace(
            '/[?&](password|token|access_token|key|secret|authorization|pin|otp)=[^&\s]*/i',
            '$1=***',
            $value
        );
    }

    /**
     * Entry point used by every helper. Writes a single enriched line.
     *
     * @param string      $category  Log category/file (e.g. 'audit', 'auth', 'http', 'errors').
     * @param string      $level     debug|info|success|warning|error|critical|audit|event.
     * @param string      $message   Human readable description.
     * @param array|null  $context   Additional structured fields (merged over the request context).
     */
    public static function log(string $category, string $level, string $message, ?array $context = []): void
    {
        $level = strtolower($level);
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'info';
        }

        $message = self::redactText(mb_substr($message, 0, 4000));
        $payload = array_merge(
            self::requestContext(),
            [
                'type' => $level,
                'message' => $message,
                'level' => $level,
            ],
            is_array($context) ? $context : []
        );

        // Guard against orphaned redundant keys when 'level'/'message' collide.
        $payload['level'] = $level;
        $payload['message'] = $message;

        // Mask sensitive/PII fields at the source, before anything is written.
        $payload = self::redactFields($payload);

        FileLogger::write($category, $payload, self::fileLevel($level));
    }

    /**
     * Redact secrets and common contact data embedded in free-form strings.
     * Key-based masking cannot protect exception messages, URLs or legacy
     * console text after values have already been flattened into a string.
     */
    public static function redactText(string $value): string
    {
        $patterns = [
            '/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i' => 'Bearer [redacted]',
            '/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/' => '[redacted-jwt]',
            '/([?&](?:password|token|access_token|refresh_token|secret|key|otp|code|authorization)=)[^&#\s]*/i' => '$1[redacted]',
            '/(["\'](?:password|pass|pwd|token|secret|authorization|credential|otp|pin|email|phone|mobile|msisdn|national_id|id_number|first_name|firstname|middle_name|middlename|last_name|lastname|recipient_name|address|account_number|accountnumber|card_number)["\']\s*:\s*["\'])[^"\']*/i' => '$1[redacted]',
            '/\b[a-f0-9]{40,}\b/i' => '[redacted-secret]',
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '[redacted-email]',
            '/(?<!\d)(?:\+?254[\s-]?(?:7|1)\d{8}|0(?:7|1)\d{8}|\+\d[\d ()-]{7,}\d)(?!\d)/' => '[redacted-phone]',
        ];
        return (string) preg_replace(array_keys($patterns), array_values($patterns), $value);
    }

    /**
     * Keys whose values are always masked before persistence. Broad match on
     * likely secret/crypto/PII field names. Values become a masked marker so
     * debugging retains structure without exposing the data.
     */
    public const REDACT_KEYS = [
        // Secrets / credentials
        'password', 'pass', 'pwd', 'password_hash', 'password_confirm',
        'token', 'access_token', 'refresh_token', 'id_token', 'auth_token',
        'api_key', 'apikey', 'secret', 'secret_key', 'client_secret',
        'consumer_secret', 'client_id', 'consumer_key', 'bearer',
        'authorization', 'pin', 'otp', 'otp_code', 'verification_code',
        'security_credential', 'mpesa_password', 'passkey', 'org_pass_key',
        'private_key', 'credential', 'credentials', 'signature',
        // Cards / financial
        'card_number', 'cvv', 'cvc', 'card_cvv', 'credit_card', 'card',
        'iban', 'swift', 'bank_account', 'account_no', 'account_number',
        'mpesa_receipt_enc', 'reversal_secret',
        // PII
        'email', 'phone', 'phone_number', 'phonenumber', 'mobile',
        'mobile_number', 'tel', 'telephone', 'msisdn', 'national_id',
        'id_number', 'id_no', 'idnumber', 'kra_pin', 'passport', 'address',
        'home_address', 'postal_address', 'dob', 'date_of_birth', 'ssn',
        'blood_group', 'medical_record', 'diagnosis', 'health_notes',
        'counseling_notes', 'discipline_notes', 'safeguarding_notes',
    ];

    /**
     * Deep-mask sensitive/PII field values in a log payload (or any array).
     * Applies to matching keys at any depth, including inside nested objects
     * and batches.
     */
    public static function redactFields(array $data): array
    {
        $out = [];
        foreach (array_slice($data, 0, 100, true) as $key => $value) {
            $lower = strtolower((string) $key);
            if (\in_array($lower, self::REDACT_KEYS, true)) {
                $out[$key] = self::maskValue($value);
            } elseif (is_array($value)) {
                $out[$key] = array_is_list($value)
                    ? array_map([self::class, 'redactListItem'], $value)
                    : self::redactFields($value);
            } elseif (is_string($value)) {
                $out[$key] = self::redactText(mb_substr($value, 0, 12000));
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private static function redactListItem($value)
    {
        return is_array($value) ? self::redactFields($value) : $value;
    }

    /**
     * Mask a scalar. Preserves type-shape (strings/numbers) but replaces the
     * sensitive content. Nested objects are collapsed to a marker.
     */
    private static function maskValue($value)
    {
        if (is_array($value)) {
            return '[redacted]';
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return '[redacted]';
        }
        if ($value === null) {
            return null;
        }
        if ((string) $value === '') {
            return '';
        }
        return '[redacted]';
    }

    private static function fileLevel(string $level): string
    {
        switch ($level) {
            case 'critical':
                return 'critical';
            case 'error':
                return 'error';
            case 'warning':
                return 'warning';
            case 'debug':
                return 'debug';
            default:
                return 'info';
        }
    }

    // ---- Convenience helpers -------------------------------------------------

    public static function debug(string $category, string $message, array $context = []): void
    {
        self::log($category, 'debug', $message, $context);
    }

    public static function info(string $category, string $message, array $context = []): void
    {
        self::log($category, 'info', $message, $context);
    }

    public static function success(string $category, string $message, array $context = []): void
    {
        self::log($category, 'success', $message, $context);
    }

    public static function warning(string $category, string $message, array $context = []): void
    {
        self::log($category, 'warning', $message, $context);
    }

    public static function error(string $category, string $message, array $context = []): void
    {
        self::log($category, 'error', $message, $context);
    }

    public static function critical(string $category, string $message, array $context = []): void
    {
        self::log($category, 'critical', $message, $context);
    }

    /**
     * Audit trail for compliance-relevant activity (create/update/delete,
     * permission changes, security events, financial postings).
     */
    public static function audit(string $action, string $entity, $entityId, string $message, array $context = []): void
    {
        self::log('audit', 'audit', $message, array_merge([
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
        ], $context));
    }

    /**
     * A named application/business event (e.g. 'payment_received',
     * 'admission_enrolled', 'class_promoted').
     */
    public static function event(string $name, string $message, array $context = []): void
    {
        self::log('events', 'event', $message, array_merge(['event' => $name], $context));
    }

    /**
     * Record one HTTP request/response trace.
     */
    public static function request(string $message, array $context = []): void
    {
        self::log('http', 'info', $message, $context);
    }

    /**
     * Structured compatibility target for migrated legacy \App\API\Services\Logger::legacyError() calls.
     * Extra native error_log arguments are accepted but never used to create
     * ad-hoc destination files.
     */
    public static function legacyError($message, int $messageType = 0, ?string $destination = null, ?string $additionalHeaders = null): bool
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? $trace[0] ?? [];
        $text = $message instanceof \Throwable
            ? $message->getMessage()
            : (is_scalar($message) ? (string) $message : json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        $level = preg_match('/\b(?:warning|deprecated|notice)\b/i', (string) $text) ? 'warning' : 'error';
        self::log('errors', $level, (string) $text, [
            'type' => 'legacy_application_error',
            'source_file' => isset($caller['file']) ? self::relativeSource((string) $caller['file']) : null,
            'source_line' => isset($caller['line']) ? (int) $caller['line'] : null,
            'legacy_message_type' => $messageType,
            'legacy_destination' => $destination ? basename($destination) : null,
        ]);
        return true;
    }

    private static function relativeSource(string $path): string
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 2)) . '/';
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with($normalized, $root) ? substr($normalized, strlen($root)) : basename($normalized);
    }

    /**
     * Return authenticated browser sessions that sent a presence heartbeat in
     * the recent window. This is derived from the file journal; no parallel
     * tracking database or sensitive browser payload is required.
     */
    public static function activePresence(int $withinSeconds = 90): array
    {
        $cutoff = time() - max(30, min(300, $withinSeconds));
        $latest = [];
        foreach (FileLogger::recent('client', 2000) as $entry) {
            $event = (string) ($entry['event'] ?? '');
            if (!in_array($event, ['page_view', 'page_heartbeat', 'page_visible', 'page_hidden', 'page_leave'], true)) {
                continue;
            }
            $key = (string) ($entry['browser_session_id'] ?? $entry['session_id'] ?? $entry['user_id'] ?? '');
            if ($key === '' || isset($latest[$key])) continue;
            $latest[$key] = $entry;
        }

        $sessions = [];
        foreach ($latest as $entry) {
            $seen = strtotime((string) ($entry['timestamp'] ?? '')) ?: 0;
            $event = (string) ($entry['event'] ?? '');
            if ($seen < $cutoff || in_array($event, ['page_hidden', 'page_leave'], true)) continue;
            $sessions[] = [
                'user_id' => isset($entry['user_id']) ? (int) $entry['user_id'] : null,
                'session_id' => (string) ($entry['session_id'] ?? ''),
                'browser_session_id' => (string) ($entry['browser_session_id'] ?? ''),
                'page' => (string) ($entry['client_route'] ?? $entry['route'] ?? ''),
                'ip' => (string) ($entry['ip'] ?? ''),
                'last_seen' => (string) ($entry['timestamp'] ?? ''),
            ];
        }
        usort($sessions, static fn ($a, $b) => strcmp($b['last_seen'], $a['last_seen']));
        return ['active_count' => count($sessions), 'window_seconds' => max(30, min(300, $withinSeconds)), 'sessions' => array_slice($sessions, 0, 50)];
    }

    /** Build operational/audit analytics from the governed file journal. */
    public static function analytics(array $filters = []): array
    {
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        $fromTs = $from !== '' ? strtotime($from . ' 00:00:00') : time() - 86400;
        $toTs = $to !== '' ? strtotime($to . ' 23:59:59') : time();
        if ($fromTs === false) $fromTs = time() - 86400;
        if ($toTs === false) $toTs = time();

        $metrics = [
            'requests' => 0, 'successful_requests' => 0, 'failed_requests' => 0,
            'warnings' => 0, 'errors' => 0, 'audit_actions' => 0,
            'login_successes' => 0, 'login_failures' => 0,
        ];
        $users = [];
        $routes = [];
        $actions = [];
        $timeline = [];
        $sessions = [];
        $integrity = [];
        $includeArchives = filter_var($filters['include_archives'] ?? false, FILTER_VALIDATE_BOOLEAN);

        foreach (FileLogger::journalPaths('', $includeArchives) as $path) {
            $fileName = basename($path);
            $category = preg_replace('/\.log(?:\..*)?$/', '', $fileName) ?: $fileName;
            if ($fileName === $category . '.log') {
                $integrity[] = FileLogger::verifyIntegrity($category);
            } elseif ($includeArchives) {
                $integrity[] = FileLogger::verifyJournalFile($path, $fileName, false);
            }
            foreach (self::readFileEntries($path) as $entry) {
                $ts = strtotime((string) ($entry['timestamp'] ?? '')) ?: 0;
                if ($ts < $fromTs || $ts > $toTs) continue;
                $level = strtolower((string) ($entry['level'] ?? 'info'));
                if ($level === 'warning') $metrics['warnings']++;
                if (in_array($level, ['error', 'critical'], true)) $metrics['errors']++;
                $bucket = date('Y-m-d H:00', $ts);
                if (!isset($timeline[$bucket])) $timeline[$bucket] = ['period' => $bucket, 'requests' => 0, 'failures' => 0, 'warnings' => 0, 'errors' => 0];
                if ($level === 'warning') $timeline[$bucket]['warnings']++;
                if (in_array($level, ['error', 'critical'], true)) $timeline[$bucket]['errors']++;

                $userId = (int) ($entry['user_id'] ?? 0);
                if ($userId > 0) {
                    if (!isset($users[$userId])) $users[$userId] = ['user_id' => $userId, 'events' => 0, 'requests' => 0, 'failures' => 0, 'audit_actions' => 0, 'last_seen' => ''];
                    $users[$userId]['events']++;
                    $users[$userId]['last_seen'] = max($users[$userId]['last_seen'], (string) ($entry['timestamp'] ?? ''));
                }
                $session = (string) ($entry['session_id'] ?? '');
                if ($session !== '') $sessions[$session] = true;

                if ($category === 'http' && isset($entry['status'])) {
                    $metrics['requests']++;
                    $success = (bool) ($entry['success'] ?? ((int) $entry['status'] < 400));
                    $metrics[$success ? 'successful_requests' : 'failed_requests']++;
                    if ($userId > 0) {
                        $users[$userId]['requests']++;
                        if (!$success) $users[$userId]['failures']++;
                    }
                    $route = (string) ($entry['route'] ?? 'unknown');
                    if (!isset($routes[$route])) $routes[$route] = ['route' => $route, 'requests' => 0, 'failures' => 0, 'duration_total_ms' => 0];
                    $routes[$route]['requests']++;
                    if (!$success) $routes[$route]['failures']++;
                    $routes[$route]['duration_total_ms'] += (int) ($entry['duration_ms'] ?? 0);
                    $timeline[$bucket]['requests']++;
                    if (!$success) $timeline[$bucket]['failures']++;
                }

                if ($category === 'audit' || $level === 'audit') {
                    $metrics['audit_actions']++;
                    $action = (string) ($entry['action'] ?? 'unspecified');
                    $actions[$action] = ($actions[$action] ?? 0) + 1;
                    if ($userId > 0) $users[$userId]['audit_actions']++;
                }
                if ($category === 'auth' && ($entry['type'] ?? '') === 'login_attempt') {
                    $ok = strtolower((string) ($entry['status'] ?? '')) === 'success';
                    $metrics[$ok ? 'login_successes' : 'login_failures']++;
                }
            }
        }

        foreach ($routes as &$route) {
            $route['average_duration_ms'] = $route['requests'] > 0 ? (int) round($route['duration_total_ms'] / $route['requests']) : 0;
            unset($route['duration_total_ms']);
        }
        unset($route);
        usort($routes, static fn ($a, $b) => $b['requests'] <=> $a['requests']);
        usort($users, static fn ($a, $b) => $b['events'] <=> $a['events']);
        arsort($actions);
        ksort($timeline);

        return [
            'period' => ['from' => date('c', $fromTs), 'to' => date('c', $toTs)],
            'metrics' => array_merge($metrics, ['unique_users' => count($users), 'unique_sessions' => count($sessions)]),
            'users' => array_slice(array_values($users), 0, 25),
            'routes' => array_slice(array_values($routes), 0, 25),
            'actions' => array_slice(array_map(static fn ($action, $count) => ['action' => $action, 'count' => $count], array_keys($actions), array_values($actions)), 0, 25),
            'timeline' => array_values($timeline),
            'integrity' => $integrity,
        ];
    }

    // ---- Reading (viewer support) --------------------------------------------

    /**
     * List log category files currently present.
     *
     * @return array<int,array{category:string,file_name:string,size:int,mtime:int,lines:int}>
     */
    public static function categories(): array
    {
        $dir = FileLogger::rootDir() . '/' . FileLogger::environment();
        $out = [];
        if (!is_dir($dir)) {
            return $out;
        }
        foreach (glob($dir . '/*.log') ?: [] as $file) {
            $base = basename($file, '.log');
            $archives = array_values(array_filter(FileLogger::journalPaths($base, true), static fn ($path) => basename($path) !== basename($file)));
            $out[] = [
                'category' => $base,
                // Never disclose the host's absolute filesystem layout.
                'file_name' => basename($file),
                'size' => is_file($file) ? filesize($file) : 0,
                'mtime' => is_file($file) ? filemtime($file) : 0,
                'lines' => self::countLines($file),
                'archives' => count($archives),
                'integrity' => FileLogger::verifyIntegrity($base)['status'] ?? 'unsealed',
                'archive_files' => array_map(static fn ($path) => ['file_name' => basename($path), 'size' => (int) @filesize($path), 'mtime' => (int) @filemtime($path)], $archives),
            ];
        }
        usort($out, static fn ($a, $b) => strcmp($a['category'], $b['category']));
        return $out;
    }

    /**
     * Read and filter log entries for the viewer.
     *
     * @param array $filters [
     *   category? => string,
     *   level? => string (min severity),
     *   search? => string,
     *   tag? => string (substring on 'type'/'action'/'event'),
     *   user_id? => int,
     *   date_from? / date_to? => YYYY-MM-DD,
     *   page? => int,
     *   limit? => int,
     *   order? => 'desc'|'asc',
     *   since? => int (unix ts, for live tail),
     * ]
     * @return array{entries:array,total:int,page:int,limit:int,total_pages:int,categories:int[]}
     */
    public static function read(array $filters = []): array
    {
        $category = trim((string) ($filters['category'] ?? ''));
        $minSeverity = self::severity(strtolower((string) ($filters['level'] ?? '')));
        $search = trim((string) ($filters['search'] ?? ''));
        $tag = trim((string) ($filters['tag'] ?? ''));
        $includeRegex = trim((string) ($filters['include_regex'] ?? ''));
        $excludeRegex = trim((string) ($filters['exclude_regex'] ?? ''));
        $userId = isset($filters['user_id']) && $filters['user_id'] !== '' ? (int) $filters['user_id'] : null;
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $order = strtolower((string) ($filters['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $maximum = !empty($filters['_trusted_export']) ? 10000 : 500;
        $limit = max(1, min($maximum, (int) ($filters['limit'] ?? 100)));
        $since = isset($filters['since']) ? (int) $filters['since'] : null;
        $includeArchives = filter_var($filters['include_archives'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Read the requested category, or all categories if none given.
        $files = FileLogger::journalPaths($category !== '' ? self::clean($category) : '', $includeArchives);

        // Read newest-first by default. Each file is read via FileLogger::recent
        // in chunks; for live tail we filter by 'since' after collection.
        $entries = [];
        $levelsSeen = [];
        foreach ($files as $path) {
            $fileName = basename($path);
            $fileCategory = preg_replace('/\.log(?:\..*)?$/', '', $fileName) ?: $fileName;
            foreach (self::readFileEntries($path) as $entry) {
                $entry['_category'] = $fileCategory;
                $entry['_archive'] = $fileName !== ($fileCategory . '.log');
                $entries[] = $entry;
                $lvl = strtolower((string) ($entry['level'] ?? 'info'));
                $levelsSeen[$lvl] = true;
            }
        }

        // Timestamp parse helper.
        $filtered = [];
        foreach ($entries as $entry) {
            $level = strtolower((string) ($entry['level'] ?? 'info'));
            if (!in_array($level, self::LEVELS, true)) {
                $level = 'info';
            }
            if ($minSeverity !== null && self::severity($level) < $minSeverity) {
                continue;
            }
            if ($userId !== null && (int) ($entry['user_id'] ?? 0) !== $userId) {
                continue;
            }
            $ts = (string) ($entry['timestamp'] ?? '');
            if ($since !== null && $ts !== '') {
                $entryTs = strtotime($ts);
                if ($entryTs === false || $entryTs < $since) {
                    continue;
                }
            }
            if ($dateFrom !== '' && $ts !== '' && $ts < ($dateFrom . ' 00:00:00')) {
                continue;
            }
            if ($dateTo !== '' && $ts !== '' && $ts > ($dateTo . ' 23:59:59')) {
                continue;
            }
            if ($search !== '') {
                if (stripos(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $search) === false) {
                    continue;
                }
            }
            if ($tag !== '') {
                $subject = strtolower(
                    (string) ($entry['type'] ?? '')
                    . ' ' . (string) ($entry['action'] ?? '')
                    . ' ' . (string) ($entry['event'] ?? '')
                );
                if ($subject !== '' && strpos($subject, strtolower($tag)) === false) {
                    continue;
                }
            }
            $encodedEntry = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            if ($includeRegex !== '' && @preg_match('~' . str_replace('~', '\\~', $includeRegex) . '~i', $encodedEntry) !== 1) continue;
            if ($excludeRegex !== '' && @preg_match('~' . str_replace('~', '\\~', $excludeRegex) . '~i', $encodedEntry) === 1) continue;
            $filtered[] = $entry;
        }

        // Sort by timestamp (desc default), stable fallback on no timestamp.
        usort($filtered, static function ($a, $b) use ($order) {
            $ta = (string) ($a['timestamp'] ?? '');
            $tb = (string) ($b['timestamp'] ?? '');
            $cmp = strcmp($ta, $tb);
            if ($order === 'asc') {
                return $cmp;
            }
            return -$cmp;
        });

        $total = count($filtered);
        $summary = ['errors' => 0, 'warnings' => 0, 'requests' => 0, 'audits' => 0];
        foreach ($filtered as $entry) {
            $entryLevel = strtolower((string)($entry['level'] ?? 'info'));
            $entryCategory = strtolower((string)($entry['_category'] ?? ''));
            if (in_array($entryLevel, ['error', 'critical'], true)) $summary['errors']++;
            if ($entryLevel === 'warning') $summary['warnings']++;
            if ($entryCategory === 'http') $summary['requests']++;
            if ($entryLevel === 'audit' || $entryCategory === 'audit') $summary['audits']++;
        }
        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;
        $slice = array_slice($filtered, ($page - 1) * $limit, $limit);

        return [
            'entries' => $slice,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'levels_seen' => array_keys($levelsSeen),
            'summary' => $summary,
        ];
    }

    /** Return a bounded, fully filtered redacted set for administrator exports. */
    public static function exportEntries(array $filters, int $maximum = 10000): array
    {
        $filters['page'] = 1;
        $filters['limit'] = max(1, min(10000, $maximum));
        $filters['_trusted_export'] = true;
        return self::read($filters);
    }

    private static function severity(string $level): ?int
    {
        if ($level === '' || $level === 'all') {
            return null;
        }
        return self::SEVERITY[$level] ?? null;
    }

    private static function clean(string $category): string
    {
        return preg_replace('/[^a-z0-9_-]/i', '_', $category) ?: 'app';
    }

    private static function countLines(string $path): int
    {
        $n = 0;
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return 0;
        }
        while (!feof($fh)) {
            $n += substr_count((string) fread($fh, 8192), "\n");
        }
        fclose($fh);
        return $n;
    }

    /**
     * Read every line of a JSON-lines log file, skipping malformed lines.
     * Reads the whole file (avoid on multi-GB files); acceptable for this size
     * and for the bounded live window the viewer requests.
     */
    private static function readFileEntries(string $path): array
    {
        $entries = [];
        $gzip = str_ends_with($path, '.gz');
        $fh = $gzip ? @gzopen($path, 'rb') : @fopen($path, 'rb');
        if (!$fh) {
            return $entries;
        }
        while (($line = $gzip ? gzgets($fh, 65536) : fgets($fh, 65536)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
                continue;
            }
            // PHP's native \App\API\Services\Logger::legacyError() format is plain text. Wrap it so legacy
            // module warnings/errors are still visible in the central viewer.
            $timestamp = date('Y-m-d H:i:s', (int)(@filemtime($path) ?: time()));
            $message = $line;
            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $line, $matches)) {
                $parsed = strtotime($matches[1]);
                if ($parsed !== false) $timestamp = date('Y-m-d H:i:s', $parsed);
                $message = $matches[2];
            }
            $entries[] = [
                'timestamp' => $timestamp,
                'level' => stripos($message, 'warning') !== false ? 'warning' : 'error',
                'type' => 'php_native',
                'message' => $message,
            ];
        }
        $gzip ? gzclose($fh) : fclose($fh);
        return $entries;
    }
}
