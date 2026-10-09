<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Database\Database;
use PDO;

/**
 * SchoolProfileService — sole reader of the school_profile identity row.
 * Centralizing it ends the repeated ad-hoc "SELECT ... FROM school_profile"
 * copies across controllers and print services.
 */
final class SchoolProfileService
{
    /** The one school_profile row ([] when missing). */
    public static function current(): array
    {
        try {
            $stmt = Database::getInstance()->getConnection()
                ->query('SELECT * FROM school_profile ORDER BY id ASC LIMIT 1');
            return $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
