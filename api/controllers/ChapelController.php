<?php
declare(strict_types=1);

namespace App\API\Controllers;

use Exception;

/**
 * ChapelController
 *
 * ROUTES:
 * GET /api/chapel/services  → getServices()  query: limit=N, upcoming=1
 */
class ChapelController extends BaseController
{
    public function index($id = null, $data = [], $segments = [])
    {
        return $this->getServices($id, $data, $segments);
    }

    /**
     * GET /api/chapel/services?limit=N&upcoming=1
     */
    public function getServices($id = null, $data = [], $segments = [])
    {
        $limit    = min((int)($_GET['limit'] ?? 10), 50);
        $upcoming = !empty($_GET['upcoming']);

        try {
            $db = \App\Database\Database::getInstance();

            // Try chapel_services table first
            try {
                $where = $upcoming ? "WHERE service_date >= CURDATE()" : "";
                $stmt  = $db->prepare(
                    "SELECT * FROM chapel_services {$where} ORDER BY service_date ASC LIMIT :lim"
                );
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                return $this->success($rows ?: []);
            } catch (\Exception $e) {
                // Fall back to school_events with type='chapel'
            }

            $tables = ['school_events', 'calendar_events', 'events'];
            foreach ($tables as $table) {
                try {
                    $dateClause = $upcoming ? "AND date >= CURDATE()" : "";
                    $stmt = $db->prepare(
                        "SELECT id, title, date, type, description FROM {$table}
                         WHERE type IN ('chapel','Chapel','CHAPEL') {$dateClause}
                         ORDER BY date ASC LIMIT :lim"
                    );
                    $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                    $stmt->execute();
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    return $this->success($rows ?: []);
                } catch (\Exception $e) {
                    continue;
                }
            }

            return $this->success([]);
        } catch (\Exception $e) {
            return $this->success([]);
        }
    }
}
