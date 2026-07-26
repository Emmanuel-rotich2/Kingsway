<?php
/**
 * scripts/test_auth_refresh.php
 *
 * DB-backed auth smoke test. Runs against the LIVE Apache server by shelling out
 * to the `curl` binary (CLI php's curl extension cannot reach localhost over
 * TLS in this environment, but the curl binary can; the DB-backed middleware is
 * still exercised end-to-end through the real HTTP stack).
 *
 * Verifies the three things the legacy cleanup depends on:
 *   1. POST /api/auth/login         -> 200 + token + refresh_token
 *   2. POST /api/auth/refresh-token -> 200 + (rotated) token
 *   3. A repaired orphan permission (term_dates_view, displayed by role 3) is
 *      present in the logged-in user's permission set (proves the migration
 *      grant flowed through EnhancedRBACMiddleware).
 *
 * Usage (run with the LAMPP php so password_hash/openssl are available):
 *   /opt/lampp/bin/php scripts/test_auth_refresh.php [username] [password]
 * Defaults to the seeded role-3 test account.
 */

$base     = 'https://localhost/Kingsway';
$username = $argv[1] ?? 'test_director';
$password = $argv[2] ?? 'TestPass123';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    echo ($ok ? "  PASS " : "  FAIL ") . $label . ($detail ? "  ($detail)" : "") . "\n";
    $ok ? $pass++ : $fail++;
}

function curlJson(string $url, ?array $body = null, ?string $bearer = null): array {
    $cmd = "curl -k -s -m 15 -w '\n%{http_code}'";
    $cmd .= " -H 'Content-Type: application/json'";
    if ($bearer) $cmd .= " -H 'Authorization: Bearer " . escapeshellarg($bearer) . "'";
    if ($body !== null) $cmd .= " -X POST -d " . escapeshellarg(json_encode($body));
    $cmd .= " " . escapeshellarg($url);
    $out  = shell_exec($cmd) ?? '';
    $parts = explode("\n", $out);
    $http = (int) array_pop($parts);
    $raw  = implode("\n", $parts);
    $json = json_decode($raw, true) ?: ['__raw' => $raw];
    return ['http' => $http, 'json' => $json];
}

echo "== Auth refresh smoke test (user: $username) ==\n";

// 1. LOGIN
$r = curlJson("$base/api/auth/login", ['username' => $username, 'password' => $password]);
check("login returns HTTP 200", $r['http'] === 200, "got {$r['http']}");
$access  = $r['json']['data']['token'] ?? $r['json']['data']['access_token'] ?? null;
$refresh = $r['json']['data']['refresh_token'] ?? null;
check("login returns an access token",  is_string($access)  && $access !== '',  substr($access ?? '', 0, 12) . '…');
check("login returns a refresh token",  is_string($refresh) && $refresh !== '', substr($refresh ?? '', 0, 12) . '…');
check("login reports success",           ($r['json']['success'] ?? false) === true);

if (!$access || !$refresh) {
    echo "\nABORT: cannot proceed without tokens.\n";
    exit(1);
}

// 2. REFRESH
$r = curlJson("$base/api/auth/refresh-token", ['refresh_token' => $refresh]);
check("refresh returns HTTP 200", $r['http'] === 200, "got {$r['http']}");
$newAccess = $r['json']['data']['token'] ?? $r['json']['data']['access_token'] ?? null;
check("refresh returns a new access token", is_string($newAccess) && $newAccess !== '');
check("refreshed token differs from original", is_string($newAccess) && $newAccess !== $access);


// 2b. Invalid refresh credentials must produce a real HTTP 401 so the browser
// can distinguish session expiry from validation or server failures.
$invalid = curlJson(
    "$base/api/auth/refresh-token",
    ['refresh_token' => 'invalid-refresh-token-for-session-policy-test']
);
check(
    "invalid refresh token returns HTTP 401",
    $invalid['http'] === 401,
    "got {$invalid['http']}"
);

// 3. repaired orphan permission present in the login payload permissions
$loginPerms = $r['json']['data']['user']['permissions'] ?? [];
$termDatesGranted = in_array('term_dates_view', $loginPerms, true);
check("repaired orphan permission term_dates_view present for role 3", $termDatesGranted,
      count($loginPerms) . " perms; term_dates_view=" . ($termDatesGranted ? 'yes' : 'no'));

// 4. refreshed token authorizes an authenticated route (auth gate works).
//    /api/auth/logout: 401 without a token, 200 with a valid one.
$r = curlJson("$base/api/auth/logout", ['refresh_token' => $refresh], $newAccess);
check("refreshed token authorizes an authenticated route", $r['http'] === 200, "got {$r['http']}");

echo "\n== RESULT: $pass passed, $fail failed ==\n";
exit($fail === 0 ? 0 : 1);
