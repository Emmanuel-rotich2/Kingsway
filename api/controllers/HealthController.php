<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Database\Database;
use Exception;

/**
 * HealthController
 * Student health records, sick bay visits, and vaccinations.
 *
 * ROUTES:
 * GET  /api/health/summary                  → getSummary()
 * GET  /api/health/records                  → getRecords()
 * GET  /api/health/records/{id}             → getRecords($id)  — by student_id
 * POST /api/health/records                  → postRecords()
 * PUT  /api/health/records/{id}             → putRecords($id)
 * GET  /api/health/sick-bay                 → getSickBay()
 * POST /api/health/sick-bay                 → postSickBay()
 * PUT  /api/health/sick-bay/{id}            → putSickBay($id)  — update / dismiss
 * GET  /api/health/vaccinations             → getVaccinations()
 * GET  /api/health/vaccinations/{id}        → getVaccinations($id)  — by student_id
 * POST /api/health/vaccinations             → postVaccinations()
 */
class HealthController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    // ----------------------------------------------------------------
    // SUMMARY
    // ----------------------------------------------------------------

    public function getSummary($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;
            $activeVisits = (int)$db->query("SELECT COUNT(*) FROM sick_bay_visits WHERE status='active'")->fetchColumn();
            $totalToday   = (int)$db->query("SELECT COUNT(*) FROM sick_bay_visits WHERE DATE(visit_date)=CURDATE()")->fetchColumn();
            $referred     = (int)$db->query("SELECT COUNT(*) FROM sick_bay_visits WHERE status='referred'")->fetchColumn();
            $hasRecords   = (int)$db->query("SELECT COUNT(DISTINCT student_id) FROM student_health_records")->fetchColumn();
            $vaxDue       = (int)$db->query("SELECT COUNT(*) FROM student_vaccinations WHERE next_due_date IS NOT NULL AND next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND next_due_date >= CURDATE()")->fetchColumn();
            return $this->success([
                'active_sick_bay'  => $activeVisits,
                'visits_today'     => $totalToday,
                'referred'         => $referred,
                'students_with_records' => $hasRecords,
                'vaccinations_due' => $vaxDue,
            ]);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // HEALTH RECORDS
    // ----------------------------------------------------------------

    public function getRecords($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;
            if ($id) {
                $stmt = $db->query(
                    "SELECT hr.*, s.first_name, s.last_name, s.admission_no,
                            c.name AS class_name
                     FROM student_health_records hr
                     JOIN students s ON s.id = hr.student_id
                     LEFT JOIN class_streams cs ON cs.id = s.stream_id LEFT JOIN classes c ON c.id = cs.class_id
                     WHERE hr.student_id = :sid LIMIT 1",
                    [':sid' => (int)$id]
                );
                return $this->success($stmt->fetch(\PDO::FETCH_ASSOC) ?: null);
            }

            $search  = $_GET['search']  ?? '';
            $classId = $_GET['class_id'] ?? '';
            $where   = ['1=1'];
            $params  = [];
            if ($search) {
                $where[]      = "(s.first_name LIKE :s1 OR s.last_name LIKE :s2 OR s.admission_no LIKE :s3)";
                $params[':s1'] = "%{$search}%";
                $params[':s2'] = "%{$search}%";
                $params[':s3'] = "%{$search}%";
            }
            if ($classId) {
                $where[]       = "s.class_id = :cid";
                $params[':cid'] = (int)$classId;
            }
            $stmt = $db->query(
                "SELECT hr.*, s.first_name, s.last_name, s.admission_no, c.name AS class_name
                 FROM student_health_records hr
                 JOIN students s ON s.id = hr.student_id
                 LEFT JOIN class_streams cs ON cs.id = s.stream_id LEFT JOIN classes c ON c.id = cs.class_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY s.last_name, s.first_name
                 LIMIT 500",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postRecords($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($data['student_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');

            $createdBy = $this->user['user_id'] ?? $this->user['id'] ?? null;

            $this->db->query(
                "INSERT INTO student_health_records
                    (student_id, blood_group, allergies, chronic_conditions, disability_notes,
                     special_diet, emergency_contact_name, emergency_contact_phone,
                     medical_aid_provider, medical_aid_number, doctor_name, doctor_phone,
                     notes, created_by, updated_by)
                 VALUES
                    (:sid, :bg, :allg, :chron, :disab, :diet, :ecn, :ecp,
                     :map, :man, :dn, :dp, :notes, :crby, :upby)
                 ON DUPLICATE KEY UPDATE
                    blood_group=:bg, allergies=:allg, chronic_conditions=:chron,
                    disability_notes=:disab, special_diet=:diet,
                    emergency_contact_name=:ecn, emergency_contact_phone=:ecp,
                    medical_aid_provider=:map, medical_aid_number=:man,
                    doctor_name=:dn, doctor_phone=:dp, notes=:notes,
                    updated_by=:upby, updated_at=NOW()",
                [
                    ':sid'   => $studentId,
                    ':bg'    => $data['blood_group']             ?? 'Unknown',
                    ':allg'  => $data['allergies']               ?? null,
                    ':chron' => $data['chronic_conditions']      ?? null,
                    ':disab' => $data['disability_notes']        ?? null,
                    ':diet'  => $data['special_diet']            ?? null,
                    ':ecn'   => $data['emergency_contact_name']  ?? null,
                    ':ecp'   => $data['emergency_contact_phone'] ?? null,
                    ':map'   => $data['medical_aid_provider']    ?? null,
                    ':man'   => $data['medical_aid_number']      ?? null,
                    ':dn'    => $data['doctor_name']             ?? null,
                    ':dp'    => $data['doctor_phone']            ?? null,
                    ':notes' => $data['notes']                   ?? null,
                    ':crby'  => $createdBy,
                    ':upby'  => $createdBy,
                ]
            );

            return $this->success(['message' => 'Health record saved']);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function putRecords($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Student ID required');
        $data['student_id'] = $id;
        return $this->postRecords(null, $data, $segments);
    }

    // ----------------------------------------------------------------
    // SICK BAY
    // ----------------------------------------------------------------

    public function getSickBay($id = null, $data = [], $segments = [])
    {
        try {
            $status = $_GET['status'] ?? '';
            $date   = $_GET['date']   ?? '';
            $where  = ['1=1'];
            $params = [];
            if ($status) { $where[] = 'sb.status=:status'; $params[':status'] = $status; }
            if ($date)   { $where[] = 'sb.visit_date=:date'; $params[':date'] = $date; }

            $stmt = $this->db->query(
                "SELECT sb.*,
                        s.first_name, s.last_name, s.admission_no,
                        c.name AS class_name
                 FROM sick_bay_visits sb
                 JOIN students s ON s.id = sb.student_id
                 LEFT JOIN class_streams cs ON cs.id = s.stream_id LEFT JOIN classes c ON c.id = cs.class_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY sb.visit_date DESC, sb.visit_time DESC
                 LIMIT 500",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postSickBay($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($data['student_id'] ?? 0);
            $complaint = trim($data['complaint'] ?? '');
            if (!$studentId || !$complaint) return $this->badRequest('student_id and complaint are required');

            $attendedBy = $data['attended_by'] ?? ($this->user['user_id'] ?? null);

            $this->db->query(
                "INSERT INTO sick_bay_visits
                    (student_id, visit_date, visit_time, complaint, symptoms, diagnosis,
                     treatment_given, temperature, weight_kg, medication_given,
                     referred_to_hospital, referral_hospital, parent_notified,
                     attended_by, status, notes)
                 VALUES
                    (:sid, :vd, :vt, :comp, :symp, :diag, :treat, :temp, :wt,
                     :meds, :ref, :refhosp, :pnotify, :atby, :status, :notes)",
                [
                    ':sid'     => $studentId,
                    ':vd'      => $data['visit_date']          ?? date('Y-m-d'),
                    ':vt'      => $data['visit_time']          ?? date('H:i:s'),
                    ':comp'    => $complaint,
                    ':symp'    => $data['symptoms']            ?? null,
                    ':diag'    => $data['diagnosis']           ?? null,
                    ':treat'   => $data['treatment_given']     ?? null,
                    ':temp'    => !empty($data['temperature']) ? (float)$data['temperature'] : null,
                    ':wt'      => !empty($data['weight_kg'])   ? (float)$data['weight_kg']   : null,
                    ':meds'    => $data['medication_given']    ?? null,
                    ':ref'     => !empty($data['referred_to_hospital']) ? 1 : 0,
                    ':refhosp' => $data['referral_hospital']   ?? null,
                    ':pnotify' => !empty($data['parent_notified']) ? 1 : 0,
                    ':atby'    => $attendedBy,
                    ':status'  => $data['status']              ?? 'active',
                    ':notes'   => $data['notes']               ?? null,
                ]
            );

            return $this->created(['id' => (int)$this->db->lastInsertId()], 'Visit recorded');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function putSickBay($id = null, $data = [], $segments = [])
    {
        try {
            if (!$id) return $this->badRequest('Visit ID required');
            $action = $segments[0] ?? '';

            if ($action === 'dismiss') {
                $this->db->query(
                    "UPDATE sick_bay_visits SET status='dismissed', dismissed_at=NOW(), updated_at=NOW() WHERE id=:id",
                    [':id' => (int)$id]
                );
                return $this->success(['message' => 'Student dismissed from sick bay']);
            }

            $this->db->query(
                "UPDATE sick_bay_visits SET
                    complaint=:comp, symptoms=:symp, diagnosis=:diag,
                    treatment_given=:treat, temperature=:temp, weight_kg=:wt,
                    medication_given=:meds, referred_to_hospital=:ref,
                    referral_hospital=:refhosp, parent_notified=:pnotify,
                    status=:status, notes=:notes, updated_at=NOW()
                 WHERE id=:id",
                [
                    ':comp'    => trim($data['complaint'] ?? ''),
                    ':symp'    => $data['symptoms']            ?? null,
                    ':diag'    => $data['diagnosis']           ?? null,
                    ':treat'   => $data['treatment_given']     ?? null,
                    ':temp'    => !empty($data['temperature']) ? (float)$data['temperature'] : null,
                    ':wt'      => !empty($data['weight_kg'])   ? (float)$data['weight_kg']   : null,
                    ':meds'    => $data['medication_given']    ?? null,
                    ':ref'     => !empty($data['referred_to_hospital']) ? 1 : 0,
                    ':refhosp' => $data['referral_hospital']   ?? null,
                    ':pnotify' => !empty($data['parent_notified']) ? 1 : 0,
                    ':status'  => $data['status']              ?? 'active',
                    ':notes'   => $data['notes']               ?? null,
                    ':id'      => (int)$id,
                ]
            );
            return $this->success(['message' => 'Visit updated']);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // VACCINATIONS
    // ----------------------------------------------------------------

    public function getVaccinations($id = null, $data = [], $segments = [])
    {
        try {
            if ($id) {
                $stmt = $this->db->query(
                    "SELECT v.*, s.first_name, s.last_name, s.admission_no
                     FROM student_vaccinations v
                     JOIN students s ON s.id = v.student_id
                     WHERE v.student_id=:sid ORDER BY v.date_given DESC",
                    [':sid' => (int)$id]
                );
            } else {
                $dueOnly = !empty($_GET['due_only']);
                $where   = $dueOnly
                    ? "WHERE v.next_due_date IS NOT NULL AND v.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
                    : "WHERE 1=1";
                $stmt = $this->db->query(
                    "SELECT v.*, s.first_name, s.last_name, s.admission_no, c.name AS class_name
                     FROM student_vaccinations v
                     JOIN students s ON s.id = v.student_id
                     LEFT JOIN class_streams cs ON cs.id = s.stream_id LEFT JOIN classes c ON c.id = cs.class_id
                     $where ORDER BY v.next_due_date, v.date_given DESC LIMIT 500"
                );
            }
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postVaccinations($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($data['student_id'] ?? 0);
            $vaccine   = trim($data['vaccine_name'] ?? '');
            $dateGiven = $data['date_given'] ?? date('Y-m-d');
            if (!$studentId || !$vaccine) return $this->badRequest('student_id and vaccine_name are required');

            $this->db->query(
                "INSERT INTO student_vaccinations
                    (student_id, vaccine_name, dose_number, date_given, next_due_date,
                     given_by, batch_number, notes, created_by)
                 VALUES (:sid, :vname, :dose, :dg, :ndd, :givenby, :batch, :notes, :crby)",
                [
                    ':sid'     => $studentId,
                    ':vname'   => $vaccine,
                    ':dose'    => (int)($data['dose_number']  ?? 1),
                    ':dg'      => $dateGiven,
                    ':ndd'     => $data['next_due_date']      ?? null,
                    ':givenby' => $data['given_by']           ?? null,
                    ':batch'   => $data['batch_number']       ?? null,
                    ':notes'   => $data['notes']              ?? null,
                    ':crby'    => $this->user['user_id']      ?? null,
                ]
            );
            return $this->created(['id' => (int)$this->db->lastInsertId()], 'Vaccination recorded');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
