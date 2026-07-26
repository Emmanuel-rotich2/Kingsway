<?php
namespace App\API\Controllers;

use Exception;

/**
 * BoardingController
 * Handles boarding/hostel management endpoints.
 *
 * GET    /api/boarding                    → getStats()
 * GET    /api/boarding/stats              → getStats()
 * GET    /api/boarding/occupancy          → getOccupancy()
 * GET    /api/boarding/dormitories        → getDormitories()
 * POST   /api/boarding/dormitories        → postDormitories()
 * PUT    /api/boarding/dormitories/{id}   → putDormitories()
 * DELETE /api/boarding/dormitories/{id}   → deleteDormitories()
 * GET    /api/boarding/students           → getStudents()
 * GET    /api/boarding/roll-call          → getRollCall()
 * POST   /api/boarding/roll-call          → postRollCall()
 * GET    /api/boarding/exeats             → getExeats()
 * POST   /api/boarding/exeats             → postExeats()
 * PUT    /api/boarding/exeats/{id}        → putExeats()  (approve/reject)
 * GET    /api/boarding/activity           → getActivity()
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
            $summary = $this->db->query(
                "SELECT COUNT(*) AS dormitories,
                        COALESCE(SUM(capacity), 0) AS total_capacity,
                        COALESCE(SUM(current_occupancy), 0) AS assigned_beds
                 FROM dormitories
                 WHERE status = 'active'"
            )->fetch(\PDO::FETCH_ASSOC) ?: [];

            $roll = $this->db->query(
                "SELECT COUNT(DISTINCT student_id) AS marked_students,
                        SUM(status = 'present') AS present_students,
                        SUM(status IN ('absent', 'unknown')) AS absent_or_unknown
                 FROM boarding_attendance
                 WHERE date = CURDATE()"
            )->fetch(\PDO::FETCH_ASSOC) ?: [];

            $permissionCount = (int) ($this->db->query(
                "SELECT COUNT(*) FROM student_permissions
                 WHERE status = 'approved'
                   AND checked_out_at IS NOT NULL
                   AND checked_in_at IS NULL
                   AND end_date >= CURDATE()"
            )->fetchColumn() ?: 0);

            $pendingCount = (int) ($this->db->query(
                "SELECT COUNT(*) FROM student_permissions WHERE status = 'pending'"
            )->fetchColumn() ?: 0);

            $urgentNotes = (int) ($this->db->query(
                "SELECT COUNT(*) FROM student_boarding_notes
                 WHERE resolved = 0 AND priority IN ('high', 'urgent')"
            )->fetchColumn() ?: 0);

            $assigned = (int) ($summary['assigned_beds'] ?? 0);
            $capacity = (int) ($summary['total_capacity'] ?? 0);
            $marked = (int) ($roll['marked_students'] ?? 0);
            $present = (int) ($roll['present_students'] ?? 0);

            return $this->success([
                'total_boarders' => $assigned,
                'dormitories' => (int) ($summary['dormitories'] ?? 0),
                'total_capacity' => $capacity,
                'assigned_beds' => $assigned,
                'available_beds' => max(0, $capacity - $assigned),
                'occupancy_rate' => $capacity > 0
                    ? round(($assigned / $capacity) * 100, 1)
                    : 0,
                'present_tonight' => $present,
                'absent_or_unknown' => (int) ($roll['absent_or_unknown'] ?? 0),
                'on_leave' => $permissionCount,
                'pending_leaves' => $pendingCount,
                'urgent_notes' => $urgentNotes,
                'roll_call_pct' => $marked > 0
                    ? round(($present / $marked) * 100, 1)
                    : 0,
            ]);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }
    public function getOccupancy($id = null, $data = [], $segments = [])
    {
        try {
            $stmt = $this->db->query(
                "SELECT d.id, d.code, d.name AS dormitory_name, d.gender,
                        d.capacity, d.current_occupancy AS occupied,
                        GREATEST(d.capacity - d.current_occupancy, 0) AS available,
                        d.status,
                        CONCAT(hp.first_name, ' ', hp.last_name) AS house_parent
                 FROM dormitories d
                 LEFT JOIN staff hp ON hp.id = d.house_parent_id
                 WHERE d.status = 'active'
                 ORDER BY d.name"
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function getDormitories($id = null, $data = [], $segments = [])
    {
        try {
            $stmt = $this->db->query(
                "SELECT d.id, d.name, d.gender, d.capacity, d.location, d.description, d.status,
                        COUNT(da.id) AS occupied,
                        d.capacity - COUNT(da.id) AS available,
                        CONCAT(s.first_name,' ',s.last_name) AS patron_name,
                        d.patron_id
                 FROM dormitories d
                 LEFT JOIN dormitory_assignments da ON da.dormitory_id=d.id AND da.status='active'
                 LEFT JOIN staff s ON s.id=d.patron_id
                 WHERE d.deleted_at IS NULL
                 GROUP BY d.id
                 ORDER BY d.name"
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postDormitories($id = null, $data = [], $segments = [])
    {
        try {
            $name = trim($data['name'] ?? '');
            if (!$name) return $this->badRequest('name is required');

            $this->db->query(
                "INSERT INTO dormitories (name, gender, capacity, patron_id, location, description, status)
                 VALUES (:name, :gender, :capacity, :patron_id, :location, :description, 'active')",
                [
                    ':name'        => $name,
                    ':gender'      => $data['gender'] ?? 'male',
                    ':capacity'    => (int)($data['capacity'] ?? 0),
                    ':patron_id'   => $data['patron_id'] ? (int)$data['patron_id'] : null,
                    ':location'    => $data['location'] ?? null,
                    ':description' => $data['description'] ?? null,
                ]
            );
            $newId = (int)$this->db->lastInsertId();
            return $this->created(['id' => $newId], 'Dormitory created successfully');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function putDormitories($id = null, $data = [], $segments = [])
    {
        try {
            $dormId = (int)($id ?? $data['id'] ?? 0);
            if (!$dormId) return $this->badRequest('id is required');

            $fields = [];
            $params = [':id' => $dormId];
            foreach (['name','gender','capacity','patron_id','location','description','status'] as $f) {
                if (array_key_exists($f, $data)) {
                    $fields[] = "$f=:$f";
                    $params[":$f"] = $data[$f] === '' ? null : $data[$f];
                }
            }
            if (!$fields) return $this->badRequest('No fields to update');

            $this->db->query(
                "UPDATE dormitories SET " . implode(',', $fields) . ", updated_at=NOW() WHERE id=:id AND deleted_at IS NULL",
                $params
            );
            return $this->success([], 'Dormitory updated');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function deleteDormitories($id = null, $data = [], $segments = [])
    {
        try {
            $dormId = (int)($id ?? $data['id'] ?? 0);
            if (!$dormId) return $this->badRequest('id is required');

            $assigned = (int)$this->db->query(
                "SELECT COUNT(*) FROM dormitory_assignments WHERE dormitory_id=:id AND status='active'",
                [':id' => $dormId]
            )->fetchColumn();
            if ($assigned > 0) {
                return $this->error('Cannot delete: ' . $assigned . ' students are currently assigned to this dormitory', 409);
            }

            $this->db->query(
                "UPDATE dormitories SET deleted_at=NOW(), status='inactive' WHERE id=:id",
                [':id' => $dormId]
            );
            return $this->success([], 'Dormitory deleted');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function getStudents($id = null, $data = [], $segments = [])
    {
        try {
            $dormId = $_GET['dormitory_id'] ?? null;
            $search = $_GET['search'] ?? '';
            $where  = ["s.status='active'", "da.status='active'"];
            $params = [];

            if ($dormId) {
                $where[] = 'da.dormitory_id=:dormitory_id';
                $params[':dormitory_id'] = (int)$dormId;
            }
            if ($search) {
                $where[] = "(s.first_name LIKE :s OR s.last_name LIKE :s OR s.admission_no LIKE :s)";
                $params[':s'] = "%$search%";
            }

            $stmt = $this->db->query(
                "SELECT s.id, s.admission_no,
                        CONCAT(s.first_name,' ',s.last_name) AS student_name,
                        s.gender, c.name AS class_name,
                        d.name AS dormitory_name,
                        da.bed_number, da.room_number,
                        da.dormitory_id,
                        COALESCE(ba.status,'—') AS tonight_status
                 FROM students s
                 JOIN dormitory_assignments da ON da.student_id=s.id
                 JOIN dormitories d ON d.id=da.dormitory_id
                 LEFT JOIN classes c ON c.id=s.class_id
                 LEFT JOIN boarding_attendance ba ON ba.student_id=s.id AND ba.date=CURDATE()
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY d.name, s.last_name
                 LIMIT 500",
                $params
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
            if (strtotime($date) === false) {
                return $this->badRequest('Invalid roll-call date');
            }
            $stmt = $this->db->prepare(
                "SELECT dormitory_id, dormitory_name, dormitory_code,
                        house_parent, date, session_name, session_code,
                        total_students, present_count, absent_count,
                        permission_count, sick_bay_count,
                        attendance_percentage
                 FROM vw_boarding_roll_call
                 WHERE date = ?
                 ORDER BY dormitory_name, session_name"
            );
            $stmt->execute([date('Y-m-d', strtotime($date))]);
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postRollCall($id = null, $data = [], $segments = [])
    {
        try {
            $records  = $data['records'] ?? [];
            $date     = $data['date'] ?? date('Y-m-d');
            $markedBy = $this->user['user_id'] ?? null;

            if (empty($records)) return $this->badRequest('records array is required');

            $ins = $this->db->getConnection()->prepare(
                "INSERT INTO boarding_attendance (student_id, date, status, marked_by, notes)
                 VALUES (?,?,?,?,?)
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
                        CONCAT(s.first_name,' ',s.last_name) AS student_name,
                        s.admission_no,
                        d.name AS dormitory_name,
                        pt.name AS permission_type_name
                 FROM student_permissions e
                 JOIN students s ON s.id=e.student_id
                 LEFT JOIN dormitory_assignments da ON da.student_id=s.id AND da.status='active'
                 LEFT JOIN dormitories d ON d.id=da.dormitory_id
                 LEFT JOIN student_permission_types pt ON pt.id=e.permission_type_id
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

            $this->db->query(
                "INSERT INTO student_permissions (student_id, permission_type_id, start_date, end_date, reason, status)
                 VALUES (:sid, :tid, :dep, :ret, :reason, 'pending')",
                [
                    ':sid'    => $studentId,
                    ':tid'    => (int)($data['permission_type_id'] ?? 1),
                    ':dep'    => $data['departure_date'] ?? $data['start_date'] ?? date('Y-m-d'),
                    ':ret'    => $data['return_date']    ?? $data['end_date']   ?? date('Y-m-d'),
                    ':reason' => $data['reason'] ?? null,
                ]
            );
            return $this->created(['id' => (int)$this->db->lastInsertId()], 'Leave request submitted');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function putExeats($id = null, $data = [], $segments = [])
    {
        try {
            $exeatId = (int)($id ?? $data['id'] ?? 0);
            if (!$exeatId) return $this->badRequest('id is required');

            $action = $data['action'] ?? $segments[0] ?? 'approve';
            $status = $action === 'reject' ? 'rejected' : 'approved';

            $this->db->query(
                "UPDATE student_permissions SET status=:status, updated_at=NOW() WHERE id=:id",
                [':status' => $status, ':id' => $exeatId]
            );
            return $this->success([], ucfirst($status) . ' leave request');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function getActivity($id = null, $data = [], $segments = [])
    {
        try {
            $rows = [];

            // Recent roll-call entries
            $stmt = $this->db->query(
                "SELECT 'roll_call' AS type, ba.created_at AS ts,
                        CONCAT(s.first_name,' ',s.last_name) AS name,
                        ba.status AS detail
                 FROM boarding_attendance ba
                 JOIN students s ON s.id=ba.student_id
                 ORDER BY ba.created_at DESC LIMIT 10"
            );
            $rows = array_merge($rows, $stmt->fetchAll(\PDO::FETCH_ASSOC));

            // Recent exeat changes
            $stmt2 = $this->db->query(
                "SELECT 'leave_request' AS type, e.updated_at AS ts,
                        CONCAT(s.first_name,' ',s.last_name) AS name,
                        e.status AS detail
                 FROM student_permissions e
                 JOIN students s ON s.id=e.student_id
                 ORDER BY e.updated_at DESC LIMIT 10"
            );
            $rows = array_merge($rows, $stmt2->fetchAll(\PDO::FETCH_ASSOC));

            usort($rows, fn($a, $b) => strcmp($b['ts'] ?? '', $a['ts'] ?? ''));

            return $this->success(array_slice($rows, 0, 15));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
