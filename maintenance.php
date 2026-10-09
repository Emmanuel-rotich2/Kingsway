<?php
// Deprecated webroot entry point. Data/state flows through the API surface
// (login.php architecture): frontend JS calls the route below, pages never
// hold SQL. This stub only redirects callers to the API route.
//
//   → GET /api/realtime/maintenance (POST, X-Kingsway-Worker-Secret)
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base === '.') $base = '';
header('Location: ' . $base . '/api/realtime/maintenance (POST, X-Kingsway-Worker-Secret)', true, 308);
exit;
