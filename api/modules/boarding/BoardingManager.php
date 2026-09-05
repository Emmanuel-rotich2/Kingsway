<?php

namespace App\API\Modules\boarding;

use App\API\Includes\BaseAPI;
use PDO;
use PDOStatement;
use Exception;

/**
 * BoardingManager - owns all boarding/dormitory SQL against the live
 * normalised schema (persons, enrollments chain, dormitory_assignments via
 * student_academic_enrollment_id, boarding_attendance with session_id).
 *
 * Legacy refs fixed:
 *   - staff.first_name/last_name        → persons (staff.person_id)
 *   - students.first_name/last_name     → persons (students.person_id)
 *   - dormitories.patron_id             → house_parent_id
 *   - dormitories.description           → facilities
 *   - dormitories.deleted_at            → soft delete via status='inactive'
 *   - dormitory_assignments.student_id  → student_academic_enrollment_id
 *   - dormitory_assignments.room_number → removed (bed_number only)
 *   - students.class_id                 → enrollment → academic_year_class_streams
 *                                          → academic_year_classes → classes
 */
class BoardingManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('boarding');
    }

    private function allRows(PDOStatement $stmt)
    {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function firstRow(PDOStatement $stmt)
    {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function getStats()
    {
        try {
            $summary = $this->firstRow($this->db->query(
                "SELECT COUNT(*) AS dormitories,
                        COALESCE(SUM(capacity), 0) AS total_capacity,
                        COALESCE(SUM(current_occupancy), 0) AS assigned_beds
                 FROM dormitories
                 WHERE status = 'active'"
            ));

            $roll = $this->firstRow($this->db->query(
                "SELECT COUNT(DISTINCT student_id) AS marked_students,
                        SUM(status = 'present') AS present_students,
                        SUM(status IN ('absent', 'unknown')) AS absent_or_unknown
                 FROM boarding_attendance
                 WHERE date = CURDATE()"
            ));

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

            return $this->successResponse([
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
            $this->logError($e, 'BoardingManager::getStats');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getOccupancy()
    {
        try {
            $stmt = $this->db->query(
                "SELECT d.id, d.code, d.name AS dormitory_name, d.name, d.gender,
                        d.capacity, d.current_occupancy AS occupied,
                        GREATEST(d.capacity - d.current_occupancy, 0) AS available,
                        d.status,
                        CONCAT(hp.first_name, ' ', hp.last_name) AS house_parent,
                        d.house_parent_id
                 FROM dormitories d
                 LEFT JOIN staff hps ON hps.id = d.house_parent_id
                 LEFT JOIN persons hp ON hp.id = hps.person_id
                 WHERE d.status = 'active'
                 ORDER BY d.name"
            );
            return $this->successResponse($this->allRows($stmt));
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::getOccupancy');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function listDormitories()
    {
        try {
            $stmt = $this->db->query(
                "SELECT d.id, d.name, d.gender, d.capacity, d.location, d.status,
                        d.house_parent_id AS patron_id,
                        CONCAT(hp.first_name, ' ', hp.last_name) AS patron_name,
                        COUNT(da.id) AS occupied,
                        d.capacity - COUNT(da.id) AS available
                 FROM dormitories d
                 LEFT JOIN dormitory_assignments da
                        ON da.dormitory_id = d.id AND da.status = 'active'
                 LEFT JOIN staff hps ON hps.id = d.house_parent_id
                 LEFT JOIN persons hp ON hp.id = hps.person_id
                 WHERE d.status = 'active'
                 GROUP BY d.id
                 ORDER BY d.name"
            );
            return $this->successResponse($this->allRows($stmt));
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::listDormitories');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createDormitory($data)
    {
        try {
            $name = trim($data['name'] ?? '');
            if ($name === '') {
                return $this->errorResponse('name is required', 400);
            }

            $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));
            $code = $base . '-' . rand(1000, 9999);

            $houseParentId = isset($data['patron_id']) && $data['patron_id'] !== '' && $data['patron_id'] !== null
                ? (int) $data['patron_id']
                : null;
            if ($houseParentId > 0 && !$this->staffExists($houseParentId)) {
                return $this->errorResponse('Invalid patron/house parent', 400);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO dormitories
                    (code, name, gender, capacity, house_parent_id, location, facilities, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $stmt->execute([
                $code,
                $name,
                $data['gender'] ?? 'male',
                (int) ($data['capacity'] ?? 0),
                $houseParentId,
                $data['location'] ?? null,
                $data['description'] ?? $data['facilities'] ?? null,
            ]);
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Dormitory created successfully', 201);
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::createDormitory');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateDormitory($id, $data)
    {
        try {
            $dormId = (int) $id;
            if ($dormId <= 0) {
                return $this->errorResponse('id is required', 400);
            }

            $allowed = ['name', 'gender', 'capacity', 'location', 'facilities', 'status'];
            if (isset($data['description'])) {
                $data['facilities'] = $data['description'];
            }
            if (isset($data['patron_id'])) {
                $data['house_parent_id'] = $data['patron_id'];
            }

            $fields = [];
            $params = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $fields[] = "$f = ?";
                    $params[] = $data[$f] === '' ? null : $data[$f];
                }
            }
            if (isset($data['house_parent_id'])) {
                $fields[] = 'house_parent_id = ?';
                $params[] = ($data['house_parent_id'] === '' || $data['house_parent_id'] === null)
                    ? null
                    : (int) $data['house_parent_id'];
            }
            if (!$fields) {
                return $this->errorResponse('No fields to update', 400);
            }

            $params[] = $dormId;
            $this->db->prepare("UPDATE dormitories SET " . implode(',', $fields) . " WHERE id = ?")
                ->execute($params);
            return $this->successResponse([], 'Dormitory updated');
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::updateDormitory');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteDormitory($id)
    {
        try {
            $dormId = (int) $id;
            if ($dormId <= 0) {
                return $this->errorResponse('id is required', 400);
            }

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM dormitory_assignments WHERE dormitory_id = ? AND status = 'active'"
            );
            $stmt->execute([$dormId]);
            $assigned = (int) $stmt->fetchColumn();
            if ($assigned > 0) {
                return $this->errorResponse(
                    'Cannot delete: ' . $assigned . ' students are currently assigned to this dormitory',
                    409
                );
            }

            $this->db->prepare("UPDATE dormitories SET status = 'inactive' WHERE id = ?")
                ->execute([$dormId]);
            return $this->successResponse([], 'Dormitory deleted');
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::deleteDormitory');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getStudents($dormitoryId = null, $search = '')
    {
        try {
            $where = ["s.status = 'active'", "da.status = 'active'"];
            $params = [];
            if ($dormitoryId) {
                $where[] = 'da.dormitory_id = ?';
                $params[] = (int) $dormitoryId;
            }
            if ($search !== '') {
                $where[] = '(p.first_name LIKE ? OR p.last_name LIKE ? OR s.admission_no LIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $sql = "SELECT s.id, s.admission_no,
                           CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                           p.gender,
                           c.name AS class_name,
                           d.name AS dormitory_name,
                           da.bed_number,
                           da.dormitory_id,
                           COALESCE(ba.status, '—') AS tonight_status
                    FROM students s
                    JOIN persons p ON p.id = s.person_id
                    JOIN student_academic_enrollments sae
                         ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    JOIN dormitory_assignments da
                         ON da.student_academic_enrollment_id = sae.id
                    JOIN dormitories d ON d.id = da.dormitory_id
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN boarding_attendance ba
                         ON ba.student_id = s.id AND ba.date = CURDATE()
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY d.name, p.last_name, p.first_name
                    LIMIT 500";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->successResponse($this->allRows($stmt));
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::getStudents');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getRollCall($date, $dateFrom = null, $dateTo = null)
    {
        try {
            $validDate = date('Y-m-d', strtotime($date));
            if ($validDate === false) {
                return $this->errorResponse('Invalid roll-call date', 400);
            }
            $validFrom = $dateFrom ? date('Y-m-d', strtotime($dateFrom)) : $validDate;
            $validTo = $dateTo ? date('Y-m-d', strtotime($dateTo)) : $validDate;
            $stmt = $this->db->prepare(
                "SELECT dormitory_id, dormitory_name, dormitory_code,
                        house_parent, MIN(date) AS date, session_name, session_code,
                        SUM(total_students) AS total_students,
                        SUM(present_count) AS present_count,
                        SUM(absent_count) AS absent_count,
                        SUM(permission_count) AS permission_count,
                        SUM(sick_bay_count) AS sick_bay_count,
                        ROUND(100 * SUM(present_count) / NULLIF(SUM(total_students), 0), 1) AS attendance_percentage
                 FROM vw_boarding_roll_call
                 WHERE date BETWEEN ? AND ?
                 GROUP BY dormitory_id, dormitory_name, dormitory_code, house_parent, session_name, session_code
                 ORDER BY dormitory_name, session_name"
            );
            $stmt->execute([$validFrom, $validTo]);
            return $this->successResponse($this->allRows($stmt));
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::getRollCall');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function markRollCall($data, $markedBy)
    {
        try {
            $records = $data['records'] ?? [];
            if (empty($records)) {
                return $this->errorResponse('records array is required', 400);
            }
            $date = $data['date'] ?? date('Y-m-d');

            $validStatuses = ['present', 'absent', 'permission', 'sick_bay', 'unknown'];

            $sessionStmt = $this->db->prepare(
                "SELECT id FROM attendance_sessions WHERE type = 'boarding' ORDER BY id LIMIT 1"
            );
            $sessionStmt->execute();
            $sessionId = (int) $sessionStmt->fetchColumn();
            if ($sessionId <= 0) {
                return $this->errorResponse('No boarding attendance session configured', 500);
            }

            $dormStmt = $this->db->prepare(
                "SELECT da.dormitory_id
                 FROM dormitory_assignments da
                 JOIN student_academic_enrollments sae ON sae.id = da.student_academic_enrollment_id
                 WHERE sae.student_id = ? AND da.status = 'active'
                 ORDER BY da.id DESC LIMIT 1"
            );
            $ins = $this->db->prepare(
                "INSERT INTO boarding_attendance
                    (student_id, dormitory_id, session_id, date, status, marked_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by),
                     notes = VALUES(notes), updated_at = NOW()"
            );

            $saved = 0;
            foreach ($records as $r) {
                $studentId = (int) ($r['student_id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }
                $status = $r['status'] ?? 'present';
                if (!in_array($status, $validStatuses, true)) {
                    $status = 'present';
                }

                $dormStmt->execute([$studentId]);
                $dormId = (int) $dormStmt->fetchColumn();
                if ($dormId <= 0) {
                    continue;
                }

                $ins->execute([
                    $studentId,
                    $dormId,
                    $sessionId,
                    $date,
                    $status,
                    $markedBy,
                    $r['notes'] ?? null,
                ]);
                $saved++;
            }
            return $this->successResponse(['saved' => $saved], 'Roll call saved');
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::markRollCall');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getExeats($status = '', $dateFrom = null, $dateTo = null)
    {
        try {
            $where = ['1=1'];
            $params = [];
            if ($status !== '') {
                $where[] = 'e.status = ?';
                $params[] = $status;
            }
            if ($dateFrom) {
                $where[] = 'e.end_date >= ?';
                $params[] = date('Y-m-d', strtotime($dateFrom));
            }
            if ($dateTo) {
                $where[] = 'e.start_date <= ?';
                $params[] = date('Y-m-d', strtotime($dateTo));
            }

            $sql = "SELECT e.*,
                           CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                           s.admission_no,
                           d.name AS dormitory_name,
                           pt.name AS permission_type_name
                    FROM student_permissions e
                    JOIN students s ON s.id = e.student_id
                    JOIN persons p ON p.id = s.person_id
                    LEFT JOIN student_academic_enrollments sae
                         ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    LEFT JOIN dormitory_assignments da
                         ON da.student_academic_enrollment_id = sae.id AND da.status = 'active'
                    LEFT JOIN dormitories d ON d.id = da.dormitory_id
                    LEFT JOIN student_permission_types pt ON pt.id = e.permission_type_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY e.created_at DESC LIMIT 200";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->successResponse($this->allRows($stmt));
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::getExeats');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createExeat($data)
    {
        try {
            $studentId = (int) ($data['student_id'] ?? 0);
            if ($studentId <= 0) {
                return $this->errorResponse('student_id is required', 400);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO student_permissions
                    (student_id, permission_type_id, start_date, end_date, reason, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([
                $studentId,
                (int) ($data['permission_type_id'] ?? 1),
                $data['departure_date'] ?? $data['start_date'] ?? date('Y-m-d'),
                $data['return_date'] ?? $data['end_date'] ?? date('Y-m-d'),
                $data['reason'] ?? '',
            ]);
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Leave request submitted', 201);
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::createExeat');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateExeat($id, $action)
    {
        try {
            $exeatId = (int) $id;
            if ($exeatId <= 0) {
                return $this->errorResponse('id is required', 400);
            }
            $status = $action === 'reject' ? 'rejected' : 'approved';
            $this->db->prepare(
                "UPDATE student_permissions SET status = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?"
            )->execute([$status, $exeatId]);
            return $this->successResponse([], ucfirst($status) . ' leave request');
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::updateExeat');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getActivity()
    {
        try {
            $rows = [];

            $stmt = $this->db->query(
                "SELECT 'roll_call' AS type, ba.created_at AS ts,
                        CONCAT(p.first_name, ' ', p.last_name) AS name,
                        ba.status AS detail
                 FROM boarding_attendance ba
                 JOIN students s ON s.id = ba.student_id
                 JOIN persons p ON p.id = s.person_id
                 ORDER BY ba.created_at DESC LIMIT 10"
            );
            $rows = array_merge($rows, $this->allRows($stmt));

            $stmt2 = $this->db->query(
                "SELECT 'leave_request' AS type, e.updated_at AS ts,
                        CONCAT(p.first_name, ' ', p.last_name) AS name,
                        e.status AS detail
                 FROM student_permissions e
                 JOIN students s ON s.id = e.student_id
                 JOIN persons p ON p.id = s.person_id
                 ORDER BY e.updated_at DESC LIMIT 10"
            );
            $rows = array_merge($rows, $this->allRows($stmt2));

            usort($rows, function ($a, $b) {
                return strcmp($b['ts'] ?? '', $a['ts'] ?? '');
            });

            return $this->successResponse(array_slice($rows, 0, 15));
        } catch (Exception $e) {
            $this->logError($e, 'BoardingManager::getActivity');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    private function staffExists($staffId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        return (bool) $stmt->fetchColumn();
    }
}
