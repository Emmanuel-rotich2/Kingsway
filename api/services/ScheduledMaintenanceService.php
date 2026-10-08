<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Database\Database;

/**
 * ScheduledMaintenanceService — owns the stored-procedure housekeeping call so
 * the thin maintenance.php entry point carries no SQL. Realtime/queue
 * maintenance runs through POST /api/realtime/cleanup on the curl crontab;
 * this procedure runner is retained for the legacy entry point.
 */
final class ScheduledMaintenanceService
{
    public function runDailyMaintenance(): void
    {
        Database::getInstance()->getConnection()->exec('CALL sp_run_maintenance()');
    }
}
