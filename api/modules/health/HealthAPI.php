<?php

namespace App\API\Modules\health;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

/**
 * HealthAPI
 *
 * Business logic for student health records, health visits (sick bay),
 * and vaccinations against the live schema.
 *
 * Note: the legacy `sick_bay_visits` table does not exist in the live
 * database; sick-bay visits map to `student_health_visits`. A visit is
 * considered "active" while `action_taken` is NULL and "dismissed"
 * once `action_taken` is set.
 */
class HealthAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('health');
    }

    private function studentJoin(): string
    {
        return "
            JOIN persons p ON p.id = s.person_id
            LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN classes c ON c.id = ayc.class_id
        ";
    }

    /**
     * Dashboard summary counts.
     */
    public function getSummary()
    {
        try {
            $activeVisits = (int)$this->db->query(
                "SELECT COUNT(*) FROM student_health_visits WHERE action_taken IS NULL"
            )->fetchColumn();
            $totalToday = (int)$this->db->query(
                "SELECT COUNT(*) FROM student_health_visits WHERE DATE(visit_date) = CURDATE()"
            )->fetchColumn();
            $referred = (int)$this->db->query(
                "SELECT COUNT(*) FROM student_health_visits WHERE referred_to_hospital = 1"
            )->fetchColumn();
            $hasRecords = (int)$this->db->query(
                "SELECT COUNT(DISTINCT student_id) FROM student_health_records"
            )->fetchColumn();
            $vaxDue = (int)$this->db->query(
                "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("student_vaccinations") . " sv
                  WHERE sv.next_due_date IS NOT NULL
                    AND sv.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            )->fetchColumn();

            return formatResponse(true, [
                'active_sick_bay'        => $activeVisits,
                'visits_today'           => $totalToday,
                'referred'               => $referred,
                'students_with_records'  => $hasRecords,
                'vaccinations_due'       => $vaxDue,
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== HEALTH RECORDS ====================

    /**
     * List health records; when $studentId is given, return that student's record.
     */
    public function listRecords($studentId = null, $search = '', $classId = null)
    {
        try {
            $base = "
                SELECT hr.*, p.first_name, p.last_name, s.admission_no, c.name AS class_name
                FROM " . ReadReplicaService::qualifiedRef("student_health_records") . " hr
                JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = hr.student_id
                " . $this->studentJoin() . "
            ";

            if ($studentId) {
                $stmt = $this->db->prepare("$base WHERE hr.student_id = ? LIMIT 1");
                $stmt->execute([(int)$studentId]);
                return formatResponse(true, $stmt->fetch(PDO::FETCH_ASSOC) ?: null);
            }

            $where = ['1=1'];
            $params = [];
            if ($search) {
                $where[] = '(p.first_name LIKE ? OR p.last_name LIKE ? OR s.admission_no LIKE ?)';
                $term = "%{$search}%";
                array_push($params, $term, $term, $term);
            }
            if ($classId) {
                $where[] = 'c.id = ?';
                $params[] = (int)$classId;
            }

            $stmt = $this->db->prepare(
                "$base WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.last_name, p.first_name LIMIT 500"
            );
            $stmt->execute($params);

            return formatResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Upsert a student's health record (one record per student).
     */
    public function upsertRecord($data, $createdBy)
    {
        try {
            $studentId = (int)($data['student_id'] ?? 0);
            if (!$studentId) {
                return formatResponse(false, null, 'student_id is required', 422);
            }

            $this->db->prepare(
                "INSERT INTO student_health_records
                    (student_id, blood_group, allergies, chronic_conditions, disability_notes,
                     special_diet, emergency_contact_name, emergency_contact_phone,
                     medical_aid_provider, medical_aid_number, doctor_name, doctor_phone,
                     notes, created_by, updated_by)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    blood_group=VALUES(blood_group), allergies=VALUES(allergies),
                    chronic_conditions=VALUES(chronic_conditions), disability_notes=VALUES(disability_notes),
                    special_diet=VALUES(special_diet), emergency_contact_name=VALUES(emergency_contact_name),
                    emergency_contact_phone=VALUES(emergency_contact_phone),
                    medical_aid_provider=VALUES(medical_aid_provider), medical_aid_number=VALUES(medical_aid_number),
                    doctor_name=VALUES(doctor_name), doctor_phone=VALUES(doctor_phone),
                    notes=VALUES(notes), updated_by=VALUES(updated_by), updated_at=NOW()"
            )->execute([
                $studentId,
                $data['blood_group'] ?? 'Unknown',
                $data['allergies'] ?? null,
                $data['chronic_conditions'] ?? null,
                $data['disability_notes'] ?? null,
                $data['special_diet'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
                $data['medical_aid_provider'] ?? null,
                $data['medical_aid_number'] ?? null,
                $data['doctor_name'] ?? null,
                $data['doctor_phone'] ?? null,
                $data['notes'] ?? null,
                $createdBy,
                $createdBy,
            ]);

            return formatResponse(true, ['message' => 'Health record saved']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== SICK BAY (HEALTH VISITS) ====================

    /**
     * List health visits, optionally filtered by status and date.
     */
    public function listVisits($status = '', $date = '')
    {
        try {
            $where = ['1=1'];
            $params = [];

            if ($status === 'dismissed') {
                $where[] = 'hv.action_taken IS NOT NULL';
            } elseif ($status === 'referred') {
                $where[] = 'hv.referred_to_hospital = 1';
            } elseif ($status === 'active') {
                $where[] = 'hv.action_taken IS NULL';
            } elseif ($status) {
                $where[] = 'hv.action_taken IS NULL';
            }

            if ($date) {
                $where[] = 'DATE(hv.visit_date) = ?';
                $params[] = $date;
            }

            $stmt = $this->db->prepare(
                "SELECT
                    hv.*,
                    p.first_name, p.last_name, s.admission_no, c.name AS class_name,
                    TIME(hv.visit_date) AS visit_time,
                    CASE WHEN hv.action_taken IS NULL THEN 'active' ELSE 'dismissed' END AS status,
                    NULL AS temperature,
                    NULL AS weight_kg,
                    NULL AS parent_notified
                 FROM " . ReadReplicaService::qualifiedRef("student_health_visits") . " hv
                 JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = hv.student_id
                 " . $this->studentJoin() . "
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY hv.visit_date DESC
                 LIMIT 500"
            );
            $stmt->execute($params);

            return formatResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Record a health visit (sick-bay admission).
     */
    public function createVisit($data, $recordedBy)
    {
        try {
            $studentId = (int)($data['student_id'] ?? 0);
            $complaint = trim($data['complaint'] ?? '');
            if (!$studentId || !$complaint) {
                return formatResponse(false, null, 'student_id and complaint are required', 422);
            }

            $visitDate = $data['visit_date'] ?? date('Y-m-d');
            $visitTime = $data['visit_time'] ?? date('H:i:s');
            $dateTime = $visitDate . ' ' . ($visitTime ?: '00:00:00');

            $this->db->prepare(
                "INSERT INTO student_health_visits
                    (student_id, visit_date, complaint, observation, action_taken,
                     medication_given, referred_to_hospital, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $studentId,
                $dateTime,
                $complaint,
                $data['symptoms'] ?? null,
                $data['treatment_given'] ?? null,
                $data['medication_given'] ?? null,
                !empty($data['referred_to_hospital']) ? 1 : 0,
                $recordedBy,
            ]);

            return formatResponse(true, ['id' => (int)$this->db->lastInsertId()], 'Visit recorded', 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update a health visit, or dismiss it when $dismiss is true.
     */
    public function updateVisit($id, $data, $dismiss = false)
    {
        try {
            if (!$id) {
                return formatResponse(false, null, 'Visit ID required', 422);
            }

            if ($dismiss) {
                $this->db->prepare(
                    "UPDATE student_health_visits
                     SET action_taken = 'Dismissed', updated_at = NOW() WHERE id = ?"
                )->execute([(int)$id]);
                return formatResponse(true, ['message' => 'Student dismissed from sick bay']);
            }

            $this->db->prepare(
                "UPDATE student_health_visits SET
                    complaint = ?, observation = ?, action_taken = ?,
                    medication_given = ?, referred_to_hospital = ?, updated_at = NOW()
                 WHERE id = ?"
            )->execute([
                trim($data['complaint'] ?? ''),
                $data['symptoms'] ?? null,
                $data['treatment_given'] ?? null,
                $data['medication_given'] ?? null,
                !empty($data['referred_to_hospital']) ? 1 : 0,
                (int)$id,
            ]);

            return formatResponse(true, ['message' => 'Visit updated']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== VACCINATIONS ====================

    /**
     * List vaccinations, optionally for one student or only those due.
     */
    public function listVaccinations($studentId = null, $dueOnly = false)
    {
        try {
            if ($studentId) {
                $stmt = $this->db->prepare(
                    "SELECT v.*, p.first_name, p.last_name, s.admission_no
                     FROM " . ReadReplicaService::qualifiedRef("student_vaccinations") . "
                     JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = v.student_id
                     " . $this->studentJoin() . "
                     WHERE v.student_id = ? ORDER BY v.date_given DESC"
                );
                $stmt->execute([(int)$studentId]);
            } else {
                $where = $dueOnly
                    ? "WHERE v.next_due_date IS NOT NULL AND v.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
                    : "WHERE 1=1";
                $stmt = $this->db->query(
                    "SELECT v.*, p.first_name, p.last_name, s.admission_no, c.name AS class_name
                     FROM " . ReadReplicaService::qualifiedRef("student_vaccinations") . "
                     JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = v.student_id
                     " . $this->studentJoin() . "
                     $where ORDER BY v.next_due_date, v.date_given DESC LIMIT 500"
                );
            }

            return formatResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Record a vaccination.
     */
    public function createVaccination($data, $createdBy)
    {
        try {
            $studentId = (int)($data['student_id'] ?? 0);
            $vaccine = trim($data['vaccine_name'] ?? '');
            if (!$studentId || !$vaccine) {
                return formatResponse(false, null, 'student_id and vaccine_name are required', 422);
            }

            $this->db->prepare(
                "INSERT INTO student_vaccinations
                    (student_id, vaccine_name, dose_number, date_given, next_due_date,
                     given_by, batch_number, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $studentId,
                $vaccine,
                (int)($data['dose_number'] ?? 1),
                $data['date_given'] ?? date('Y-m-d'),
                $data['next_due_date'] ?? null,
                $data['given_by'] ?? null,
                $data['batch_number'] ?? null,
                $data['notes'] ?? null,
                $createdBy,
            ]);

            return formatResponse(true, ['id' => (int)$this->db->lastInsertId()], 'Vaccination recorded', 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
