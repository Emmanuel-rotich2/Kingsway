<?php
namespace App\API\Controllers;

use Exception;

/**
 * BoardingController
 * Handles boarding/hostel management endpoints.
 *
 * GET  /api/boarding          → get()       — summary stats
 * GET  /api/boarding/stats    → getStats()
 * GET  /api/boarding/occupancy → getOccupancy()
 * GET  /api/boarding/roll-call → getRollCall()
 * POST /api/boarding/roll-call → postRollCall()
 * GET  /api/boarding/exeats   → getExeats()
 * POST /api/boarding/exeats   → postExeats()
 */
class BoardingController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($id = null, $data = [], $segments = [])
    {
        return $this->getStats();
    }

    public function getStats($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;
            $totalBoarders = (int)$db->query(
                "SELECT COUNT(*) FROM students WHERE status='active' AND student_type_id IN (SELECT id FROM student_types WHERE LOWER(name) LIKE '%board%')"
            )->fetchColumn();
            $dormCount = (int)$db->query("SELECT COUNT(*) FROM dormitories WHERE status='active'")->fetchColumn();
            $assigned  = (int)$db->query("SELECT COUNT(*) FROM dormitory_assignments WHERE status='active'")->fetchColumn();
            $presentTonight = (int)$db->query(
                "SELECT COUNT(*) FROM boarding_attendance WHERE date=CURDATE() AND status='present'"
            )->fetchColumn();
            return $this->success([
                'total_boarders'     => $totalBoarders,
                'dormitories'        => $dormCount,
                'assigned_beds'      => $assigned,
                'present_tonight'    => $presentTonight,
            ]);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function getOccupancy($id = null, $data = [], $segments = [])
    {
        try {
            $stmt = $this->db->query(
                "SELECT d.id, d.name AS dormitory_name, d.capacity,
                        COUNT(da.id) AS occupied,
                        d.capacity - COUNT(da.id) AS available
                 FROM dormitories d
                 LEFT JOIN dormitory_assignments da ON da.dormitory_id = d.id AND da.status='active'
                 WHERE d.status='active'
                 GROUP BY d.id
                 ORDER BY d.name"
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function getRollCall($id = null, $data = [], $segments = [])
    {
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            $stmt = $this->db->query(
                "SELECT ba.*,
                        CONCAT(s.first_name,' ',s.last_name) AS student_name,
                        s.admission_no,
                        d.name AS dormitory_name
                 FROM boarding_attendance ba
                 JOIN students s ON s.id = ba.student_id
                 LEFT JOIN dormitory_assignments da ON da.student_id = s.id AND da.status='active'
                 LEFT JOIN dormitories d ON d.id = da.dormitory_id
                 WHERE ba.date = :date
                 ORDER BY d.name, s.last_name",
                [':date' => $date]
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postRollCall($id = null, $data = [], $segments = [])
    {
        try {
            $records   = $data['records'] ?? [];
            $date      = $data['date'] ?? date('Y-m-d');
            $markedBy  = $this->user['user_id'] ?? null;

            if (empty($records)) return $this->badRequest('records array is required');

            $ins = $this->db->getConnection()->prepare(
                "INSERT INTO boarding_attendance (student_id, date, status, marked_by, notes)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by), notes=VALUES(notes), updated_at=NOW()"
            );
            foreach ($records as $r) {
                $ins->execute([
                    (int)$r['student_id'],
                    $date,
                    $r['status'] ?? 'present',
                    $markedBy,
                    $r['notes'] ?? null,
                ]);
            }
            return $this->success(['saved' => count($records)], 'Roll call saved');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function getExeats($id = null, $data = [], $segments = [])
    {
        try {
            $status = $_GET['status'] ?? '';
            $where  = ['1=1'];
            $params = [];
            if ($status) { $where[] = 'e.status=:status'; $params[':status'] = $status; }

            $stmt = $this->db->query(
                "SELECT e.*,
                        CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
                        pt.name AS permission_type_name
                 FROM student_permissions e
                 JOIN students s ON s.id = e.student_id
                 LEFT JOIN student_permission_types pt ON pt.id = e.permission_type_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY e.created_at DESC LIMIT 200",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postExeats($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($data['student_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');

            $typeId = (int)($data['permission_type_id'] ?? 1);
            $this->db->query(
                "INSERT INTO student_permissions (student_id, permission_type_id, start_date, end_date, reason, status)
                 VALUES (:sid, :tid, :dep, :ret, :reason, 'pending')",
                [
                    ':sid'    => $studentId,
                    ':tid'    => $typeId,
                    ':dep'    => $data['departure_date'] ?? $data['start_date'] ?? date('Y-m-d'),
                    ':ret'    => $data['return_date']    ?? $data['end_date']   ?? date('Y-m-d'),
                    ':reason' => $data['reason']         ?? null,
                ]
            );
            return $this->created(['id' => (int)$this->db->lastInsertId()], 'Exeat request created');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
