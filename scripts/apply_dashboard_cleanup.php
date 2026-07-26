<?php

declare(strict_types=1);

/**
 * Kingsway role-dashboard redundant file cleanup.
 *
 * Dry-run is the default. Pass --apply to remove the exact, audited files.
 * No wildcard or recursive deletion is performed.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This cleanup may only be run from the command line.\n");
    exit(1);
}

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false || !is_file($projectRoot . '/config/DashboardRouter.php')) {
    fwrite(STDERR, "Unable to resolve the Kingsway project root.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;

$redundantFiles = [
    'js/dashboards/accountant_accounts_cash_dashboard.js',
    'js/dashboards/accountant_assets_dashboard.js',
    'js/dashboards/accountant_controls_dashboard.js',
    'js/dashboards/accountant_mpesa_dashboard.js',
    'js/dashboards/accountant_vendors_dashboard.js',
    'components/dashboards/teacher_dashboard.php',
    'js/dashboards/teacher_dashboard.js',
    'pages/dashboard.php',
    'js/dashboards/dashboard_router.js',
    'js/dashboards/director_dashboard.js',
    'pages/system_administrator_dashboard.php',
];

$deleted = 0;
$absent = 0;
$failed = 0;

printf(
    "Kingsway dashboard cleanup (%s)\nProject root: %s\n\n",
    $dryRun ? 'dry-run' : 'apply',
    $projectRoot
);

foreach ($redundantFiles as $relativePath) {
    $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!file_exists($fullPath)) {
        printf("[already clean] %s\n", $relativePath);
        $absent++;
        continue;
    }

    if (!is_file($fullPath) || is_link($fullPath)) {
        fprintf(STDERR, "[refused] %s is not a regular file.\n", $relativePath);
        $failed++;
        continue;
    }

    $resolvedDirectory = realpath(dirname($fullPath));
    if ($resolvedDirectory === false || strpos($resolvedDirectory, $projectRoot . DIRECTORY_SEPARATOR) !== 0) {
        fprintf(STDERR, "[refused] %s resolves outside the project root.\n", $relativePath);
        $failed++;
        continue;
    }

    if ($dryRun) {
        printf("[would delete] %s\n", $relativePath);
        continue;
    }

    if (!unlink($fullPath)) {
        fprintf(STDERR, "[failed] %s\n", $relativePath);
        $failed++;
        continue;
    }

    printf("[deleted] %s\n", $relativePath);
    $deleted++;
}

printf(
    "\nSummary: deleted=%d, already_clean=%d, failed=%d, mode=%s\n",
    $deleted,
    $absent,
    $failed,
    $dryRun ? 'dry-run' : 'apply'
);

if ($dryRun) {
    echo "Run again with --apply after reviewing this exact list.\n";
}

exit($failed > 0 ? 1 : 0);
