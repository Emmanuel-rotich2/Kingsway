<?php
declare(strict_types=1);

namespace App\API\Controllers;

use Exception;

/**
 * EventsController
 *
 * ROUTES:
 * GET /api/events          → getEvents()   query params: upcoming=1, limit=N
 */
class EventsController extends BaseController
{
    public function index($id = null, $data = [], $segments = [])
    {
        return $this->getEvents($id, $data, $segments);
    }

    /**
     * GET /api/events
     * Returns school/calendar events.
     * Query params: upcoming=1, limit=N
     */
    public function getEvents($id = null, $data = [], $segments = [])
    {
        $upcoming = !empty($_GET['upcoming']);
        $limit    = min((int)($_GET['limit'] ?? 20), 100);

        try {
            $db = \App\Database\Database::getInstance();

            // Try school_events table first
            $table = null;
            foreach (['school_events', 'calendar_events', 'events'] as $t) {
                try {
                    $db->query("SELECT 1 FROM {$t} LIMIT 1");
                    $table = $t;
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$table) {
                return $this->success([]);
            }

            $where = $upcoming ? "WHERE date >= CURDATE()" : "";
            $stmt  = $db->prepare(
                "SELECT id, title, date, type, description FROM {$table} {$where} ORDER BY date ASC LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows ?: []);
        } catch (\Exception $e) {
            return $this->success([]);
        }
    }
}
