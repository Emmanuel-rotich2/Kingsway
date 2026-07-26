<?php
/**
 * Verify the canonical 30-minute idle-session policy end to end.
 *
 * This test creates a real test login, ages only that new auth_sessions row,
 * confirms the refresh endpoint returns HTTP 401, and confirms the related
 * refresh token/session are revoked. Use a non-production test account.
 *
 * Usage:
 *   /opt/lampp/bin/php scripts/test_auth_idle_timeout.php [username] [password]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/DashboardRouter.php';
require_once __DIR__ . '/../database/Database.php';

use App\Database\Database;

$base = 'https://localhost/Kingsway';
$username = $argv[1] ?? 'test_director';
$password = $argv[2] ?? 'TestPass123';
$idleTimeout = defined('AUTH_IDLE_TIMEOUT_SECONDS')
    ? max(300, (int) AUTH_IDLE_TIMEOUT_SECONDS)
    : 1800;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    echo ($ok ? '  PASS ' : '  FAIL ') . $label;
    if ($detail !== '') {
        echo '  (' . $detail . ')';
    }
    echo "\n";
    $ok ? $pass++ : $fail++;
}

function curlJson(string $url, array $body): array
{
    $command = "curl -k -s -m 20 -w '\n%{http_code}'";
    $command .= " -H 'Content-Type: application/json'";
    $command .= ' -X POST -d ' . escapeshellarg(json_encode($body));
    $command .= ' ' . escapeshellarg($url);

    $output = shell_exec($command) ?? '';
    $parts = explode("\n", $output);
    $http = (int) array_pop($parts);
    $raw = implode("\n", $parts);
    $json = json_decode($raw, true);

    return [
        'http' => $http,
        'json' => is_array($json) ? $json : ['__raw' => $raw],
    ];
}

echo "== Auth idle-timeout test (user: {$username}) ==\n";

$login = curlJson(
    $base . '/api/auth/login',
    ['username' => $username, 'password' => $password]
);
check('login returns HTTP 200', $login['http'] === 200, 'got ' . $login['http']);

$data = $login['json']['data'] ?? [];
$sessionId = (int) ($data['session_id'] ?? 0);
$refreshToken = (string) ($data['refresh_token'] ?? '');

check('login returns canonical session_id', $sessionId > 0, (string) $sessionId);
check('login returns refresh token', $refreshToken !== '');

if ($sessionId <= 0 || $refreshToken === '') {
    echo "\nABORT: test login did not create a canonical tracked session.\n";
    exit(1);
}

$db = Database::getInstance()->getConnection();
$ageSeconds = $idleTimeout + 5;
$stmt = $db->prepare(
    "UPDATE auth_sessions
     SET last_activity = DATE_SUB(NOW(), INTERVAL {$ageSeconds} SECOND)
     WHERE id = ?"
);
$stmt->execute([$sessionId]);
check('test session was aged beyond idle timeout', $stmt->rowCount() === 1);

$refresh = curlJson(
    $base . '/api/auth/refresh-token',
    ['refresh_token' => $refreshToken]
);
check(
    'idle refresh returns HTTP 401',
    $refresh['http'] === 401,
    'got ' . $refresh['http']
);

$stmt = $db->prepare(
    'SELECT revoked_at
     FROM refresh_tokens
     WHERE token = ?
     LIMIT 1'
);
$stmt->execute([$refreshToken]);
$revokedAt = $stmt->fetchColumn();
check('idle refresh token was revoked', is_string($revokedAt) && $revokedAt !== '');

$stmt = $db->prepare('SELECT COUNT(*) FROM auth_sessions WHERE id = ?');
$stmt->execute([$sessionId]);
check('idle canonical session was removed', (int) $stmt->fetchColumn() === 0);

echo "\n== RESULT: {$pass} passed, {$fail} failed ==\n";
exit($fail === 0 ? 0 : 1);
