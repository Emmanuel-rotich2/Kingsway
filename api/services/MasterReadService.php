<?php

namespace App\API\Services;

use App\Database\ConnectionManager;
use PDO;

/**
 * Explicit entry point for strong-consistency reads.
 *
 * This service does not redirect or cache queries. It makes the consistency
 * decision visible at the call site and refuses to switch away from the
 * master namespace while a transaction is active.
 */
final class MasterReadService
{
    private function __construct()
    {
    }

    /**
     * @return mixed
     */
    public static function run(callable $callback)
    {
        return ConnectionManager::run($callback, ConnectionManager::NS_MASTER);
    }

    public static function transactionSafe(callable $callback): mixed
    {
        return self::run(static function (PDO $pdo) use ($callback): mixed {
            return $callback($pdo);
        });
    }
}
