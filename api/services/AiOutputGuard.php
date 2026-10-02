<?php
declare(strict_types=1);

namespace App\API\Services;

/**
 * Output confidentiality guard for AI provider responses.
 *
 * A model is given only minimized, allowlisted context, but it can still be
 * induced to echo internal detail: a database object name, a filesystem path,
 * a registry report code, a connection string or a credential. This guard is the
 * single response-side choke point that every workflow passes through, so the
 * filter cannot be forgotten by an individual adapter.
 *
 * Design rules:
 *  - Only high-signal, unambiguous internal markers are removed. Ordinary
 *    school vocabulary ("select", "create", "from the class list") must survive,
 *    because the assistant has to stay useful and conversational.
 *  - Replacements are readable phrases rather than blanks so sentences stay
 *    coherent for the reader.
 *  - Recursion is bounded (depth, node count, string length) so a large or
 *    hostile payload cannot exhaust memory or time.
 *  - Keys are scrubbed as well as values, because a model can disclose a source
 *    by naming it in an object key.
 */
final class AiOutputGuard
{
    private const MAX_DEPTH = 12;
    private const MAX_NODES = 5000;
    private const MAX_STRING = 20000;
    private const MAX_PHRASE = 200;

    /**
     * Prefixes used by this schema for database objects. These never occur in
     * ordinary school prose, so they are safe to redact without a context test.
     *
     * @var list<string>
     */
    private const OBJECT_PREFIXES = ['vw_', 'mmv_', 'mv_', 'sp_', 'fn_', 'trg_'];

    /** @var list<array{0:string,1:string}> */
    private const PATTERNS = [
        // Credentials and secrets.
        ['/\bsk-[A-Za-z0-9_\-]{16,}\b/u', '[redacted credential]'],
        ['/\beyJ[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{6,}/u', '[redacted token]'],
        ['/\bBearer\s+[A-Za-z0-9._~\+\/\-]{16,}=*/u', 'Bearer [redacted]'],
        ['#\b(?:mysql|mariadb|pgsql|postgres|sqlserver|redis|mongodb)://[^\s"\']+#iu', '[redacted connection]'],
        ['/\b(?:[A-Za-z0-9_.\-]*[_.\-])?(?:password|passwd|secret|api[_-]?key|auth[_-]?token|access[_-]?token|client[_-]?secret)\b(\s*[:=]\s*)(\S{4,})/iu', '$1[redacted]'],
        ['/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b(?=[^\s]*\b(?:kingsway|mysql|db|database))/iu', '[redacted address]'],

        // Filesystem paths and code file names.
        ['#\b[A-Za-z]:\\\\[^\s"\'<>|]+#u', '[redacted path]'],
        ['#(?<![\w/])(?:/(?:home|var|etc|usr|opt|srv|www|root|tmp|storage|logs|mnt|app)/)[^\s"\'`,;)\]]+#u', '[redacted path]'],
        ['#\b(?:api|js|pages|components|layouts|database|vendor|config|includes|tests|scripts|ai_platform)/[\w./\-]+\.(?:php|js|sql|json|py|env|ini|yml|yaml)\b#u', '[redacted file]'],
        ['#\b[\w\-]+\.(?:php|sql|env)\b#u', '[redacted file]'],

        // Network and deployment detail.
        ['/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/u', '[redacted host]'],
        ['/\blocalhost:\d+\b/u', '[redacted host]'],
        ['#\b[a-z0-9\-]+\.(?:kingswaypreparatoryschool\.sc\.ke|hostafrica\.com|ngrok\.io|trycloudflare\.com)\b#iu', '[redacted host]'],

        // Explicit source citation phrasing, which is not allowed on screen or
        // in a conversational answer.
        ['/\b(?:Source|Data source|Source view|Source table|Backed by|Pulled from|Fetched from)\s*:\s*[^\n.;]{0,120}/iu', 'Based on school records'],
    ];

    /**
     * SQL detection is structural, never a bare keyword match. "Select a class
     * from the list" is ordinary school language and must survive, so a
     * SELECT/FROM pair is only treated as a query when it also carries an
     * unmistakable SQL marker: a terminating semicolon, a quoted identifier, a
     * snake_case table name, or a projection wildcard.
     */
    private const SQL_CANDIDATE = '/\bSELECT\b[^;\n]{0,200}?\bFROM\b[^;\n]{0,200};?/i';
    private const SQL_MARKER = '/(?:;|`[^`]+`|\bFROM\s+[A-Za-z_]\w*_\w+|\*)/i';
    private const SQL_DDL = '/\b(?:INSERT\s+INTO|DELETE\s+FROM|DROP\s+TABLE|ALTER\s+TABLE|TRUNCATE\s+TABLE|UPDATE\s+[a-z_]+\s+SET)\b[^;\n]{0,200}?(?:;|$)/i';

    /**
     * Scrub a decoded provider response.
     *
     * @param mixed $payload Decoded provider payload (array/scalar).
     * @return mixed
     */
    public static function scrub($payload)
    {
        $budget = self::MAX_NODES;

        return self::walk($payload, 0, $budget);
    }

    /**
     * Bounded recursive worker. Both depth and node count are capped so a
     * hostile or malformed payload cannot exhaust the stack or memory.
     *
     * @param mixed $payload
     * @return mixed
     */
    private static function walk($payload, int $depth, int &$budget)
    {
        if ($budget <= 0) {
            return '[truncated]';
        }
        $budget--;

        if (is_string($payload)) {
            return self::scrubText($payload);
        }
        if (!is_array($payload)) {
            return $payload;
        }
        if ($depth >= self::MAX_DEPTH) {
            return ['_truncated' => 'nested response truncated'];
        }

        $out = [];
        foreach ($payload as $key => $value) {
            if ($budget <= 0) {
                $out['_truncated'] = 'response truncated';
                break;
            }
            $cleanKey = is_string($key) ? self::scrubText($key) : $key;
            if (array_key_exists($cleanKey, $out)) {
                $cleanKey .= '_';
            }
            $out[$cleanKey] = self::walk($value, $depth + 1, $budget);
        }

        return $out;
    }

    /**
     * Remove database object names, registry codes and secret material from one
     * string, then apply the pattern list.
     */
    public static function scrubText(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        if (strlen($text) > self::MAX_STRING) {
            $text = substr($text, 0, self::MAX_STRING) . '…';
        }

        // Database object names: unambiguous prefixes, safe without context.
        foreach (self::OBJECT_PREFIXES as $prefix) {
            $text = preg_replace('/\b' . preg_quote($prefix, '/') . '[A-Za-z0-9_]+\b/u', 'school data', $text) ?? $text;
        }

        // Governed registry report codes, e.g. FIN_FEE_SUMMARY_CLASS: uppercase
        // identifiers built from two or more underscore segments.
        $text = preg_replace('/\b[A-Z]{2,}(?:_[A-Z0-9]+){2,}\b/u', 'the school report', $text) ?? $text;

        foreach (self::PATTERNS as [$pattern, $replacement]) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return self::scrubSql($text);
    }

    /**
     * Redact only text that is structurally a SQL statement. DDL additionally
     * requires an uppercase verb or a terminating semicolon, so prose such as
     * "insert into the register" is left alone.
     */
    private static function scrubSql(string $text): string
    {
        $text = preg_replace_callback(
            self::SQL_CANDIDATE,
            static function (array $match): string {
                return preg_match(self::SQL_MARKER, $match[0]) === 1 ? '[redacted query]' : $match[0];
            },
            $text
        ) ?? $text;

        return preg_replace_callback(
            self::SQL_DDL,
            static function (array $match): string {
                $parts = preg_split('/\s+/', trim($match[0])) ?: [''];
                $verb = $parts[0];
                $uppercase = $verb === strtoupper($verb) && ctype_alpha($verb);
                $terminated = str_ends_with(rtrim($match[0]), ';');
                return ($uppercase || $terminated) ? '[redacted query]' : $match[0];
            },
            $text
        ) ?? $text;
    }

    /**
     * True when a payload still contains internal disclosure, so an adapter can
     * fail closed instead of silently shipping a redacted but odd answer.
     */
    public static function containsInternalReference($payload): bool
    {
        $encoded = json_encode(self::strings($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        $prefixes = implode('|', array_map(static fn (string $p): string => preg_quote($p, '/'), self::OBJECT_PREFIXES));
        if (preg_match('/\b(?:' . $prefixes . ')[A-Za-z0-9_]+\b/u', $encoded)) {
            return true;
        }
        if (preg_match('/\b[A-Z]{2,}(?:_[A-Z0-9]+){2,}\b/u', $encoded)) {
            return true;
        }
        foreach (self::PATTERNS as [$pattern]) {
            if (preg_match($pattern, $encoded) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect every string in a payload, including keys, for inspection.
     *
     * @return list<string>
     */
    private static function strings($payload, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [];
        }
        if (is_string($payload)) {
            return [$payload];
        }
        if (!is_array($payload)) {
            return [];
        }
        $out = [];
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $out[] = $key;
            }
            foreach (self::strings($value, $depth + 1) as $nested) {
                $out[] = $nested;
            }
        }

        return $out;
    }
}