<?php
/**
 * Content-Security-Policy for every HTML surface (staff shell, public site,
 * parent portal, role templates, API-rendered documents).
 *
 * Scripts run only from allowlisted origins or from inline <script> blocks
 * that carry this response's nonce. Inline event-handler attributes
 * (onclick=...) and eval are refused by the browser; the app writes
 * data-kw-on<event> attributes instead, which js/core/inline_actions.js runs
 * through a restricted interpreter. An injected <script>, onerror=, or
 * javascript: URL therefore cannot execute.
 *
 * CSP_MODE=report-only (config/.env) sends the strict policy as
 * Content-Security-Policy-Report-Only next to the previous permissive policy,
 * as an emergency fallback while violations are investigated. The default is
 * enforce. Violations are reported to /api/public/csp-report and journalled
 * under logs/<env>/security.log.
 *
 * Inline style attributes stay allowed (style-src 'unsafe-inline'): they are
 * used by most templates and cannot execute script under this policy.
 */
declare(strict_types=1);

if (!function_exists('csp_nonce')) {
    /** The per-response script nonce (random, base64, 128+ bits). */
    function csp_nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        }
        return $nonce;
    }

    /** ` nonce="..."` for an inline <script> tag. */
    function csp_nonce_attr(): string
    {
        return ' nonce="' . csp_nonce() . '"';
    }

    /**
     * The first script of every HTML document: js/core/inline_actions.js,
     * which runs data-kw-on* handlers and publishes the nonce to the
     * soft-navigation loaders. Carries the nonce so document.currentScript
     * can read it.
     */
    function csp_bootstrap_script(?string $appBase = null): string
    {
        $base = ($appBase === null || $appBase === '')
            ? (function_exists('app_base_path') ? (string) app_base_path() : '')
            : $appBase;
        $path = 'js/core/inline_actions.js';
        $version = function_exists('asset_version') ? asset_version($path) : '1';
        return '<script src="' . htmlspecialchars(rtrim($base, '/') . '/' . $path . '?v=' . $version, ENT_QUOTES, 'UTF-8')
            . '"' . csp_nonce_attr() . '></script>' . PHP_EOL;
    }

    /**
     * Build the policy for a surface.
     *
     * @param string $surface staff|public|api
     * @param array{connect?: string, strict?: bool} $options
     */
    function csp_policy(string $surface, array $options = []): string
    {
        $strict = $options['strict'] ?? true;
        $connect = trim("'self' " . ($options['connect'] ?? ''));
        $scriptHosts = [
            'staff' => "https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com https://code.jquery.com https://unpkg.com",
            'public' => "https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com",
            'api' => "https://cdnjs.cloudflare.com",
        ][$surface] ?? "https://cdnjs.cloudflare.com";
        $script = $strict
            ? "script-src 'self' 'nonce-" . csp_nonce() . "' {$scriptHosts}; script-src-attr 'none'"
            : "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$scriptHosts}";
        $base = function_exists('app_base_path') ? rtrim((string) app_base_path(), '/') : '';
        $directives = [
            "default-src 'self'",
            $surface === 'staff' ? "object-src 'self' blob:" : "object-src 'none'",
            $surface === 'staff' ? "frame-src 'self' blob:" : "frame-src 'self'",
            $script,
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "img-src 'self' data: blob: https://placehold.co https://images.unsplash.com https://www.kingswaypreparatoryschool.sc.ke",
            "connect-src {$connect}",
            "frame-ancestors " . ($surface === 'api' ? "'self'" : "'none'"),
            "form-action 'self'",
            "base-uri 'self'",
        ];
        if ($strict) {
            $directives[] = 'report-uri ' . $base . '/api/public/csp-report';
        }
        return implode('; ', $directives);
    }

    /**
     * Send the policy (and X-CSP-Nonce, which the soft-navigation loaders use
     * to tell the server's own fragment scripts from injected ones).
     */
    function csp_send(string $surface, array $options = []): void
    {
        if (headers_sent()) {
            return;
        }
        $mode = strtolower(trim((string) ($_ENV['CSP_MODE'] ?? getenv('CSP_MODE') ?: 'enforce')));
        if ($mode === 'report-only') {
            header('Content-Security-Policy: ' . csp_policy($surface, ['strict' => false] + $options));
            header('Content-Security-Policy-Report-Only: ' . csp_policy($surface, ['strict' => true] + $options));
        } else {
            header('Content-Security-Policy: ' . csp_policy($surface, ['strict' => true] + $options));
        }
        header('X-CSP-Nonce: ' . csp_nonce());
    }
}
