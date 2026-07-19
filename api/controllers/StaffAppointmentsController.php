<?php

namespace App\API\Controllers;

use App\Database\Database;
use App\API\Services\MessageService;
use Exception;
use PDO;

class StaffAppointmentsController extends BaseController
{
    private const INTERNAL_TYPES = ['acting', 'substantive', 'transfer', 'reclassification'];
    private const NEW_STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'onboarded'];

    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->notFound('Use /api/staff-appointments/internal or /api/staff-appointments/new');
        }

        try {
            $db = Database::getInstance();
            $internal = $db->query(
                "SELECT status, COUNT(*) AS total
                 FROM staff_promotions
                 WHERE promotion_type IN ('acting', 'substantive', 'transfer', 'reclassification')
                 GROUP BY status"
            )->fetchAll(PDO::FETCH_ASSOC);

            $newStaff = $db->query(
                "SELECT status, COUNT(*) AS total
                 FROM staff_appointments
                 GROUP BY status"
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->success([
                'internal' => $internal,
                'new_staff' => $newStaff,
            ]);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function getInternal($id = null, $data = [], $segments = [])
    {
        try {
            $db = Database::getInstance();
            $where = ["sp.promotion_type IN ('acting', 'substantive', 'transfer', 'reclassification')"];
            $params = [];

            if (!empty($_GET['status'])) {
                $where[] = 'sp.status = :status';
                $params[':status'] = $_GET['status'];
            }
            if (!empty($_GET['staff_id'])) {
                $where[] = 'sp.staff_id = :staff_id';
                $params[':staff_id'] = (int)$_GET['staff_id'];
            }

            $appointments = $db->query(
                "SELECT sp.*,
                        CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
                        s.staff_no,
                        fd.name AS from_department,
                        td.name AS to_department,
                        CONCAT(cb.first_name, ' ', cb.last_name) AS created_by_name,
                        CONCAT(sb.first_name, ' ', sb.last_name) AS submitted_by_name,
                        CONCAT(ab.first_name, ' ', ab.last_name) AS approved_by_name
                 FROM staff_promotions sp
                 JOIN staff s ON s.id = sp.staff_id
                 LEFT JOIN departments fd ON fd.id = sp.from_department_id
                 LEFT JOIN departments td ON td.id = sp.to_department_id
                 LEFT JOIN staff cb ON cb.id = sp.created_by
                 LEFT JOIN staff sb ON sb.id = sp.submitted_by
                 LEFT JOIN staff ab ON ab.id = sp.approved_by
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY sp.created_at DESC
                 LIMIT 200",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->success($appointments);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postInternal($id = null, $data = [], $segments = [])
    {
        try {
            $staffId = (int)($data['staff_id'] ?? 0);
            if (!$staffId) {
                return $this->badRequest('staff_id is required');
            }

            $type = $data['promotion_type'] ?? 'transfer';
            if (!in_array($type, self::INTERNAL_TYPES, true)) {
                return $this->badRequest('promotion_type must be acting, substantive, transfer, or reclassification');
            }

            $effectiveDate = $data['effective_date'] ?? null;
            if (!$effectiveDate) {
                return $this->badRequest('effective_date is required');
            }

            $db = Database::getInstance();
            $staff = $db->query('SELECT * FROM staff WHERE id = ?', [$staffId])->fetch(PDO::FETCH_ASSOC);
            if (!$staff) {
                return $this->badRequest('Staff member not found');
            }

            $actorId = $this->getCurrentStaffId();
            if (!$actorId) {
                return $this->unauthorized('Staff user context is required');
            }

            $db->beginTransaction();
            $db->query(
                "INSERT INTO staff_promotions
                  (staff_id, promotion_type, is_temporary, from_position, to_position,
                   from_department_id, to_department_id, from_salary, to_salary,
                   from_contract_type, to_contract_type, from_supervisor_id, to_supervisor_id,
                   effective_date, status, reason, letter_url, created_by, submitted_by, submitted_at)
                 VALUES
                  (:staff_id, :promotion_type, :is_temporary, :from_position, :to_position,
                   :from_department_id, :to_department_id, :from_salary, :to_salary,
                   :from_contract_type, :to_contract_type, :from_supervisor_id, :to_supervisor_id,
                   :effective_date, 'pending', :reason, :letter_url, :created_by, :submitted_by, NOW())",
                [
                    ':staff_id' => $staffId,
                    ':promotion_type' => $type,
                    ':is_temporary' => $type === 'acting' ? 1 : 0,
                    ':from_position' => $staff['position'],
                    ':to_position' => $data['to_position'] ?? $staff['position'],
                    ':from_department_id' => $staff['department_id'],
                    ':to_department_id' => $data['to_department_id'] ?? $staff['department_id'],
                    ':from_salary' => $staff['salary'],
                    ':to_salary' => array_key_exists('to_salary', $data) ? $data['to_salary'] : $staff['salary'],
                    ':from_contract_type' => $staff['contract_type'],
                    ':to_contract_type' => $data['to_contract_type'] ?? $staff['contract_type'],
                    ':from_supervisor_id' => $staff['supervisor_id'],
                    ':to_supervisor_id' => array_key_exists('to_supervisor_id', $data) ? $data['to_supervisor_id'] : $staff['supervisor_id'],
                    ':effective_date' => $effectiveDate,
                    ':reason' => $data['reason'] ?? null,
                    ':letter_url' => $data['letter_url'] ?? null,
                    ':created_by' => $actorId,
                    ':submitted_by' => $actorId,
                ]
            );
            $appointmentId = (int)$db->lastInsertId();
            $this->recordHistory($db, 'internal', $appointmentId, 'submitted', $actorId, $data['reason'] ?? null, null, 'pending', $data);
            $db->commit();

            return $this->created(['id' => $appointmentId], 'Internal appointment submitted for Director approval');
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->serverError($e->getMessage());
        }
    }

    public function putInternalApprove($id = null, $data = [], $segments = [])
    {
        return $this->reviewInternalAppointment($id, $data, 'approve');
    }

    public function putInternalReject($id = null, $data = [], $segments = [])
    {
        return $this->reviewInternalAppointment($id, $data, 'reject');
    }

    public function putInternalRevert($id = null, $data = [], $segments = [])
    {
        try {
            $appointmentId = (int)($id ?? $data['id'] ?? 0);
            if (!$appointmentId) {
                return $this->badRequest('Appointment ID is required');
            }

            $actorId = $this->getCurrentStaffId();
            if (!$actorId) {
                return $this->unauthorized('Staff user context is required');
            }

            $db = Database::getInstance();
            $appointment = $db->query('SELECT * FROM staff_promotions WHERE id = ?', [$appointmentId])->fetch(PDO::FETCH_ASSOC);
            if (!$appointment) {
                return $this->badRequest('Internal appointment not found');
            }
            if ($appointment['promotion_type'] !== 'acting' || (int)$appointment['is_temporary'] !== 1) {
                return $this->badRequest('Only temporary acting appointments can be reverted');
            }
            if (!in_array($appointment['status'], ['approved', 'effective'], true)) {
                return $this->badRequest('Only approved or effective acting appointments can be reverted');
            }

            $db->beginTransaction();
            $db->query(
                'UPDATE staff
                 SET position = :position,
                     department_id = :department_id,
                     salary = :salary,
                     contract_type = :contract_type,
                     supervisor_id = :supervisor_id,
                     updated_at = NOW()
                 WHERE id = :staff_id',
                [
                    ':position' => $appointment['from_position'],
                    ':department_id' => $appointment['from_department_id'],
                    ':salary' => $appointment['from_salary'],
                    ':contract_type' => $appointment['from_contract_type'],
                    ':supervisor_id' => $appointment['from_supervisor_id'],
                    ':staff_id' => $appointment['staff_id'],
                ]
            );
            $db->query(
                "UPDATE staff_promotions SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
                [$appointmentId]
            );
            $this->recordHistory($db, 'internal', $appointmentId, 'reverted', $actorId, $data['remarks'] ?? null, $appointment['status'], 'cancelled', [
                'position' => [$appointment['to_position'], $appointment['from_position']],
                'department_id' => [$appointment['to_department_id'], $appointment['from_department_id']],
                'salary' => [$appointment['to_salary'], $appointment['from_salary']],
            ]);
            $db->commit();

            return $this->success(null, 'Acting appointment reverted');
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->serverError($e->getMessage());
        }
    }

    public function getNew($id = null, $data = [], $segments = [])
    {
        try {
            $db = Database::getInstance();
            $where = ['1=1'];
            $params = [];

            if (!empty($_GET['status'])) {
                $where[] = 'sa.status = :status';
                $params[':status'] = $_GET['status'];
            }

            $appointments = $db->query(
                "SELECT sa.*,
                        d.name AS department_name,
                        CONCAT(sb.first_name, ' ', sb.last_name) AS submitted_by_name,
                        CONCAT(ab.first_name, ' ', ab.last_name) AS approved_by_name,
                        CONCAT(ob.first_name, ' ', ob.last_name) AS onboarded_by_name
                 FROM staff_appointments sa
                 JOIN departments d ON d.id = sa.department_id
                 LEFT JOIN staff sb ON sb.id = sa.submitted_by
                 LEFT JOIN staff ab ON ab.id = sa.approved_by
                 LEFT JOIN staff ob ON ob.id = sa.onboarded_by
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY sa.created_at DESC
                 LIMIT 200",
                $params
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->success($appointments);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postNew($id = null, $data = [], $segments = [])
    {
        try {
            foreach (['candidate_first_name', 'candidate_last_name', 'candidate_email', 'department_id', 'position', 'employment_date'] as $field) {
                if (empty($data[$field])) {
                    return $this->badRequest("{$field} is required");
                }
            }
            if (!filter_var($data['candidate_email'], FILTER_VALIDATE_EMAIL)) {
                return $this->badRequest('candidate_email must be a valid email address');
            }

            $actorId = $this->getCurrentStaffId();
            if (!$actorId) {
                return $this->unauthorized('Staff user context is required');
            }

            $db = Database::getInstance();
            $db->beginTransaction();
            $db->query(
                "INSERT INTO staff_appointments
                  (recruitment_id, candidate_first_name, candidate_last_name, candidate_email,
                   candidate_phone, candidate_id_number, candidate_qualifications, candidate_experience,
                   candidate_notes, department_id, position, employment_date, contract_type, salary,
                   supervisor_id, staff_type_id, staff_category_id, status, submitted_by, submitted_at)
                 VALUES
                  (:recruitment_id, :first_name, :last_name, :email,
                   :phone, :id_number, :qualifications, :experience,
                   :notes, :department_id, :position, :employment_date, :contract_type, :salary,
                   :supervisor_id, :staff_type_id, :staff_category_id, 'submitted', :submitted_by, NOW())",
                [
                    ':recruitment_id' => $data['recruitment_id'] ?? null,
                    ':first_name' => $data['candidate_first_name'],
                    ':last_name' => $data['candidate_last_name'],
                    ':email' => $data['candidate_email'],
                    ':phone' => $data['candidate_phone'] ?? null,
                    ':id_number' => $data['candidate_id_number'] ?? null,
                    ':qualifications' => $data['candidate_qualifications'] ?? null,
                    ':experience' => $data['candidate_experience'] ?? null,
                    ':notes' => $data['candidate_notes'] ?? null,
                    ':department_id' => (int)$data['department_id'],
                    ':position' => $data['position'],
                    ':employment_date' => $data['employment_date'],
                    ':contract_type' => $data['contract_type'] ?? 'permanent',
                    ':salary' => $data['salary'] ?? null,
                    ':supervisor_id' => $data['supervisor_id'] ?? null,
                    ':staff_type_id' => $data['staff_type_id'] ?? null,
                    ':staff_category_id' => $data['staff_category_id'] ?? null,
                    ':submitted_by' => $actorId,
                ]
            );
            $appointmentId = (int)$db->lastInsertId();
            $this->recordHistory($db, 'new', $appointmentId, 'submitted', $actorId, $data['candidate_notes'] ?? null, null, 'submitted', $data);
            $db->commit();

            return $this->created(['id' => $appointmentId], 'New staff appointment submitted for Director approval');
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->serverError($e->getMessage());
        }
    }

    public function putNewApprove($id = null, $data = [], $segments = [])
    {
        return $this->reviewNewAppointment($id, $data, 'approve');
    }

    public function putNewReject($id = null, $data = [], $segments = [])
    {
        return $this->reviewNewAppointment($id, $data, 'reject');
    }

    public function putNewOnboard($id = null, $data = [], $segments = [])
    {
        try {
            $appointmentId = (int)($id ?? $data['id'] ?? 0);
            if (!$appointmentId) {
                return $this->badRequest('Appointment ID is required');
            }

            $actorId = $this->getCurrentStaffId();
            if (!$actorId) {
                return $this->unauthorized('Staff user context is required');
            }

            $roleId = (int)($data['role_id'] ?? 0);
            if (!$roleId) {
                return $this->badRequest('role_id is required for account creation');
            }

            $db = Database::getInstance();
            $appointment = $db->query('SELECT * FROM staff_appointments WHERE id = ?', [$appointmentId])->fetch(PDO::FETCH_ASSOC);
            if (!$appointment) {
                return $this->badRequest('New staff appointment not found');
            }
            if ($appointment['status'] !== 'approved') {
                return $this->badRequest('Only approved new staff appointments can be onboarded');
            }

            $tempPassword = $this->generateTemporaryPassword();
            $username = $this->uniqueUsername($db, $appointment['candidate_first_name'], $appointment['candidate_last_name']);
            $staffNo = $this->nextStaffNumber($db);

            $db->beginTransaction();
            $db->query(
                "INSERT INTO users
                  (username, email, first_name, last_name, password, role_id, status, force_password_change)
                 VALUES
                  (:username, :email, :first_name, :last_name, :password, :role_id, 'active', 1)",
                [
                    ':username' => $username,
                    ':email' => $appointment['candidate_email'],
                    ':first_name' => $appointment['candidate_first_name'],
                    ':last_name' => $appointment['candidate_last_name'],
                    ':password' => password_hash($tempPassword, PASSWORD_DEFAULT),
                    ':role_id' => $roleId,
                ]
            );
            $userId = (int)$db->lastInsertId();

            $db->query(
                "INSERT INTO staff
                  (staff_type_id, staff_category_id, staff_no, first_name, last_name, department_id,
                   supervisor_id, user_id, position, employment_date, contract_type, salary, status)
                 VALUES
                  (:staff_type_id, :staff_category_id, :staff_no, :first_name, :last_name, :department_id,
                   :supervisor_id, :user_id, :position, :employment_date, :contract_type, :salary, 'active')",
                [
                    ':staff_type_id' => $appointment['staff_type_id'],
                    ':staff_category_id' => $appointment['staff_category_id'],
                    ':staff_no' => $staffNo,
                    ':first_name' => $appointment['candidate_first_name'],
                    ':last_name' => $appointment['candidate_last_name'],
                    ':department_id' => $appointment['department_id'],
                    ':supervisor_id' => $appointment['supervisor_id'],
                    ':user_id' => $userId,
                    ':position' => $appointment['position'],
                    ':employment_date' => $appointment['employment_date'],
                    ':contract_type' => $appointment['contract_type'],
                    ':salary' => $appointment['salary'],
                ]
            );
            $staffId = (int)$db->lastInsertId();

            $db->query(
                "UPDATE staff_appointments
                 SET status = 'onboarded', onboarded_by = :actor_id, onboarded_at = NOW(),
                     created_user_id = :user_id, created_staff_id = :staff_id, updated_at = NOW()
                 WHERE id = :id",
                [
                    ':actor_id' => $actorId,
                    ':user_id' => $userId,
                    ':staff_id' => $staffId,
                    ':id' => $appointmentId,
                ]
            );
            $this->recordHistory($db, 'new', $appointmentId, 'onboarded', $actorId, $data['remarks'] ?? null, 'approved', 'onboarded', [
                'created_user_id' => $userId,
                'created_staff_id' => $staffId,
                'staff_no' => $staffNo,
            ]);
            $db->commit();

            $emailSent = $this->sendWelcomeEmail($appointment, $username, $tempPassword);

            return $this->success([
                'user_id' => $userId,
                'staff_id' => $staffId,
                'staff_no' => $staffNo,
                'username' => $username,
                'email_sent' => $emailSent,
            ], 'New staff onboarded successfully');
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->serverError($e->getMessage());
        }
    }

    public function postCareersCandidate($id = null, $data = [], $segments = [])
    {
        try {
            foreach (['candidate_first_name', 'candidate_last_name', 'candidate_email', 'department_id', 'position', 'employment_date'] as $field) {
                if (empty($data[$field])) {
                    return $this->badRequest("{$field} is required");
                }
            }
            if (!filter_var($data['candidate_email'], FILTER_VALIDATE_EMAIL)) {
                return $this->badRequest('candidate_email must be a valid email address');
            }

            $db = Database::getInstance();
            $db->query(
                "INSERT INTO staff_appointments
                  (recruitment_id, candidate_first_name, candidate_last_name, candidate_email,
                   candidate_phone, candidate_id_number, candidate_qualifications, candidate_experience,
                   candidate_notes, department_id, position, employment_date, contract_type, salary,
                   supervisor_id, staff_type_id, staff_category_id, status)
                 VALUES
                  (:recruitment_id, :first_name, :last_name, :email,
                   :phone, :id_number, :qualifications, :experience,
                   :notes, :department_id, :position, :employment_date, :contract_type, :salary,
                   :supervisor_id, :staff_type_id, :staff_category_id, 'draft')",
                [
                    ':recruitment_id' => $data['recruitment_id'] ?? null,
                    ':first_name' => trim($data['candidate_first_name']),
                    ':last_name' => trim($data['candidate_last_name']),
                    ':email' => trim($data['candidate_email']),
                    ':phone' => $data['candidate_phone'] ?? null,
                    ':id_number' => $data['candidate_id_number'] ?? null,
                    ':qualifications' => $data['candidate_qualifications'] ?? null,
                    ':experience' => $data['candidate_experience'] ?? null,
                    ':notes' => $data['candidate_notes'] ?? null,
                    ':department_id' => (int)$data['department_id'],
                    ':position' => trim($data['position']),
                    ':employment_date' => $data['employment_date'],
                    ':contract_type' => $data['contract_type'] ?? 'permanent',
                    ':salary' => $data['salary'] ?? null,
                    ':supervisor_id' => $data['supervisor_id'] ?? null,
                    ':staff_type_id' => $data['staff_type_id'] ?? null,
                    ':staff_category_id' => $data['staff_category_id'] ?? null,
                ]
            );

            return $this->created(['id' => (int)$db->lastInsertId()], 'Candidate appointment received for recruitment review');
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }
    public function getHistory($id = null, $data = [], $segments = [])
    {
        try {
            $appointmentType = $_GET['appointment_type'] ?? $data['appointment_type'] ?? null;
            $appointmentId = (int)($_GET['appointment_id'] ?? $data['appointment_id'] ?? 0);
            if (!$appointmentType || !$appointmentId) {
                return $this->badRequest('appointment_type and appointment_id are required');
            }

            $history = Database::getInstance()->query(
                "SELECT saa.*, CONCAT(s.first_name, ' ', s.last_name) AS actor_name
                 FROM staff_appointment_approvals saa
                 JOIN staff s ON s.id = saa.actor_id
                 WHERE saa.appointment_type = :appointment_type AND saa.appointment_id = :appointment_id
                 ORDER BY saa.created_at ASC",
                [':appointment_type' => $appointmentType, ':appointment_id' => $appointmentId]
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->success($history);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    private function reviewInternalAppointment($id, array $data, string $action)
    {
        try {
            $appointmentId = (int)($id ?? $data['id'] ?? 0);
            if (!$appointmentId) {
                return $this->badRequest('Appointment ID is required');
            }

            $actorId = $this->getCurrentStaffId();
            if (!$actorId) {
                return $this->unauthorized('Staff user context is required');
            }

            $db = Database::getInstance();
            $appointment = $db->query('SELECT * FROM staff_promotions WHERE id = ?', [$appointmentId])->fetch(PDO::FETCH_ASSOC);
            if (!$appointment) {
                return $this->badRequest('Internal appointment not found');
            }
            if ($appointment['status'] !== 'pending') {
                return $this->badRequest('Only pending internal appointments can be reviewed');
            }

            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $db->beginTransaction();
            $db->query(
                'UPDATE staff_promotions
                 SET status = :status, approved_by = :actor_id, approved_at = NOW(),
                     rejected_reason = :rejected_reason, updated_at = NOW()
                 WHERE id = :id',
                [
                    ':status' => $newStatus,
                    ':actor_id' => $actorId,
                    ':rejected_reason' => $action === 'reject' ? ($data['reason'] ?? null) : null,
                    ':id' => $appointmentId,
                ]
            );

            if ($action === 'approve') {
                $this->applyInternalAppointment($db, $appointment, $actorId);
            }

            $this->recordHistory($db, 'internal', $appointmentId, $newStatus, $actorId, $data['reason'] ?? null, 'pending', $newStatus, $appointment);
            $db->commit();

            return $this->success(null, "Internal appointment {$action}d");
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->serverError($e->getMessage());
        }
    }

    private function reviewNewAppointment($id, array $data, string $action)
    {
        try {
            $appointmentId = (int)($id ?? $data['id'] ?? 0);
            if (!$appointmentId) {
                return $this->badRequest('Appointment ID is required');
            }

            $actorId = $this->getCurrentStaffId();
            if (!$actorId) {
                return $this->unauthorized('Staff user context is required');
            }

            $db = Database::getInstance();
            $appointment = $db->query('SELECT * FROM staff_appointments WHERE id = ?', [$appointmentId])->fetch(PDO::FETCH_ASSOC);
            if (!$appointment) {
                return $this->badRequest('New staff appointment not found');
            }
            if ($appointment['status'] !== 'submitted') {
                return $this->badRequest('Only submitted new staff appointments can be reviewed');
            }

            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $db->beginTransaction();
            $db->query(
                "UPDATE staff_appointments
                 SET status = :status, approved_by = :actor_id, approved_at = NOW(),
                     rejection_reason = :rejection_reason, updated_at = NOW()
                 WHERE id = :id",
                [
                    ':status' => $newStatus,
                    ':actor_id' => $actorId,
                    ':rejection_reason' => $action === 'reject' ? ($data['reason'] ?? null) : null,
                    ':id' => $appointmentId,
                ]
            );
            $this->recordHistory($db, 'new', $appointmentId, $newStatus, $actorId, $data['reason'] ?? null, 'submitted', $newStatus, $appointment);
            $db->commit();

            return $this->success(null, "New staff appointment {$action}d");
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->serverError($e->getMessage());
        }
    }

    private function applyInternalAppointment(Database $db, array $appointment, int $actorId): void
    {
        $db->query(
            'UPDATE staff
             SET position = :position,
                 department_id = :department_id,
                 salary = :salary,
                 contract_type = :contract_type,
                 supervisor_id = :supervisor_id,
                 updated_at = NOW()
             WHERE id = :staff_id',
            [
                ':position' => $appointment['to_position'],
                ':department_id' => $appointment['to_department_id'],
                ':salary' => $appointment['to_salary'],
                ':contract_type' => $appointment['to_contract_type'],
                ':supervisor_id' => $appointment['to_supervisor_id'],
                ':staff_id' => $appointment['staff_id'],
            ]
        );

        if ((string)$appointment['from_salary'] !== (string)$appointment['to_salary']) {
            $db->query(
                "INSERT INTO staff_payroll_adjustments
                  (staff_id, source_type, source_id, previous_salary, new_salary, effective_date, reason, created_by)
                 VALUES
                  (:staff_id, 'internal_appointment', :source_id, :previous_salary, :new_salary, :effective_date, :reason, :created_by)",
                [
                    ':staff_id' => $appointment['staff_id'],
                    ':source_id' => $appointment['id'],
                    ':previous_salary' => $appointment['from_salary'],
                    ':new_salary' => $appointment['to_salary'],
                    ':effective_date' => $appointment['effective_date'],
                    ':reason' => $appointment['reason'],
                    ':created_by' => $actorId,
                ]
            );
            $adjustmentId = (int)$db->lastInsertId();
            $db->query('UPDATE staff_promotions SET payroll_adjustment_id = ? WHERE id = ?', [$adjustmentId, $appointment['id']]);
        }

        if ($appointment['effective_date'] <= date('Y-m-d')) {
            $db->query("UPDATE staff_promotions SET status = 'effective' WHERE id = ?", [$appointment['id']]);
        }
    }

    private function recordHistory(Database $db, string $type, int $appointmentId, string $action, int $actorId, ?string $remarks, ?string $previousStatus, ?string $newStatus, array $changes): void
    {
        $db->query(
            'INSERT INTO staff_appointment_approvals
              (appointment_type, appointment_id, action, actor_id, remarks, previous_status, new_status, changes_json)
             VALUES
              (:appointment_type, :appointment_id, :action, :actor_id, :remarks, :previous_status, :new_status, :changes_json)',
            [
                ':appointment_type' => $type,
                ':appointment_id' => $appointmentId,
                ':action' => $action,
                ':actor_id' => $actorId,
                ':remarks' => $remarks,
                ':previous_status' => $previousStatus,
                ':new_status' => $newStatus,
                ':changes_json' => json_encode($changes),
            ]
        );
    }

    private function getCurrentStaffId(): ?int
    {
        if (!empty($this->user['staff_id'])) {
            return (int)$this->user['staff_id'];
        }
        if (!empty($this->user['user_id'])) {
            $staff = Database::getInstance()->query('SELECT id FROM staff WHERE user_id = ?', [(int)$this->user['user_id']])->fetch(PDO::FETCH_ASSOC);
            return $staff ? (int)$staff['id'] : null;
        }
        return null;
    }

    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(4)) . 'K!';
    }

    private function uniqueUsername(Database $db, string $firstName, string $lastName): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($firstName . '.' . $lastName)));
        $base = trim($base, '.') ?: 'staff';
        $username = $base;
        $suffix = 1;
        while ($db->query('SELECT id FROM users WHERE username = ?', [$username])->fetch()) {
            $username = $base . $suffix;
            $suffix++;
        }
        return $username;
    }

    private function nextStaffNumber(Database $db): string
    {
        $latest = $db->query(
            "SELECT staff_no FROM staff WHERE staff_no LIKE 'KWPS%' ORDER BY CAST(SUBSTRING(staff_no, 5) AS UNSIGNED) DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $next = $latest ? ((int)substr($latest['staff_no'], 4) + 1) : 1;
        return 'KWPS' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }

    private function sendWelcomeEmail(array $appointment, string $username, string $password): bool
    {
        try {
            $service = new MessageService(Database::getInstance()->getConnection());
            $body = $service->renderFormalEmail(
                'Welcome to Kingsway Preparatory School',
                '<p>Your staff account has been created.</p>' .
                '<p><strong>Username:</strong> ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</p>' .
                '<p><strong>Temporary password:</strong> ' . htmlspecialchars($password, ENT_QUOTES, 'UTF-8') . '</p>' .
                '<p>Please change your password after your first login.</p>',
                '',
                ''
            );
            return (bool)$service->sendEmail([
                $appointment['candidate_email'] => trim($appointment['candidate_first_name'] . ' ' . $appointment['candidate_last_name'])
            ], 'Your Kingsway staff account', $body);
        } catch (Exception $e) {
            error_log('Staff appointment welcome email failed: ' . $e->getMessage());
            return false;
        }
    }
}
