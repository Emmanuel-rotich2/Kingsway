<?php

namespace App\API\Services;

use App\Database\Database;
use Exception;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class StaffAppointmentsService
{
    private const INTERNAL_TYPES = ['acting', 'substantive', 'transfer', 'reclassification'];

    public function __construct(private ?Database $db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function summary(): array
    {
        $internal = $this->db->query(
            "SELECT status, COUNT(*) AS total
             FROM staff_promotions
             WHERE promotion_type IN ('acting', 'substantive', 'transfer', 'reclassification')
             GROUP BY status"
        )->fetchAll(PDO::FETCH_ASSOC);

        $newStaff = $this->db->query(
            "SELECT status, COUNT(*) AS total
             FROM staff_appointments
             GROUP BY status"
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['internal' => $internal, 'new_staff' => $newStaff];
    }

    public function listInternal(array $filters = []): array
    {
        $where = ["sp.promotion_type IN ('acting', 'substantive', 'transfer', 'reclassification')"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'sp.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['staff_id'])) {
            $where[] = 'sp.staff_id = :staff_id';
            $params[':staff_id'] = (int)$filters['staff_id'];
        }

        return $this->db->query(
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
    }

    public function submitInternal(array $data, int $actorId): int
    {
        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId) {
            throw new InvalidArgumentException('staff_id is required');
        }
        $type = $data['promotion_type'] ?? 'transfer';
        if (!in_array($type, self::INTERNAL_TYPES, true)) {
            throw new InvalidArgumentException('promotion_type must be acting, substantive, transfer, or reclassification');
        }
        if (empty($data['effective_date'])) {
            throw new InvalidArgumentException('effective_date is required');
        }

        $staff = $this->db->query('SELECT * FROM staff WHERE id = ?', [$staffId])->fetch(PDO::FETCH_ASSOC);
        if (!$staff) {
            throw new InvalidArgumentException('Staff member not found');
        }

        $this->db->beginTransaction();
        try {
            $this->db->query(
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
                    ':effective_date' => $data['effective_date'],
                    ':reason' => $data['reason'] ?? null,
                    ':letter_url' => $data['letter_url'] ?? null,
                    ':created_by' => $actorId,
                    ':submitted_by' => $actorId,
                ]
            );
            $appointmentId = (int)$this->db->lastInsertId();
            $this->recordHistory('internal', $appointmentId, 'submitted', $actorId, $data['reason'] ?? null, null, 'pending', $data);
            $this->db->commit();
            return $appointmentId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function revertInternal(int $appointmentId, int $actorId, array $data = []): void
    {
        $appointment = $this->internalAppointment($appointmentId);
        if ($appointment['promotion_type'] !== 'acting' || (int)$appointment['is_temporary'] !== 1) {
            throw new InvalidArgumentException('Only temporary acting appointments can be reverted');
        }
        if (!in_array($appointment['status'], ['approved', 'effective'], true)) {
            throw new InvalidArgumentException('Only approved or effective acting appointments can be reverted');
        }

        $this->db->beginTransaction();
        try {
            $this->db->query(
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
            $this->db->query("UPDATE staff_promotions SET status = 'cancelled', updated_at = NOW() WHERE id = ?", [$appointmentId]);
            $this->recordHistory('internal', $appointmentId, 'reverted', $actorId, $data['remarks'] ?? null, $appointment['status'], 'cancelled', [
                'position' => [$appointment['to_position'], $appointment['from_position']],
                'department_id' => [$appointment['to_department_id'], $appointment['from_department_id']],
                'salary' => [$appointment['to_salary'], $appointment['from_salary']],
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function listNew(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'sa.status = :status';
            $params[':status'] = $filters['status'];
        }

        return $this->db->query(
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
    }

    public function submitNew(array $data, int $actorId): int
    {
        $this->validateNewAppointment($data);

        $this->db->beginTransaction();
        try {
            $appointmentId = $this->insertNewAppointment($data, 'submitted', $actorId);
            $this->recordHistory('new', $appointmentId, 'submitted', $actorId, $data['candidate_notes'] ?? null, null, 'submitted', $data);
            $this->db->commit();
            return $appointmentId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function createCareerCandidate(array $data): int
    {
        $this->validateNewAppointment($data);
        return $this->insertNewAppointment($data, 'draft');
    }

    public function reviewInternal(int $appointmentId, string $action, int $actorId, array $data = []): void
    {
        $appointment = $this->internalAppointment($appointmentId);
        if ($appointment['status'] !== 'pending') {
            throw new InvalidArgumentException('Only pending internal appointments can be reviewed');
        }

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $this->db->beginTransaction();
        try {
            $this->db->query(
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
                $this->applyInternalAppointment($appointment, $actorId);
            }
            $this->recordHistory('internal', $appointmentId, $newStatus, $actorId, $data['reason'] ?? null, 'pending', $newStatus, $appointment);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function reviewNew(int $appointmentId, string $action, int $actorId, array $data = []): void
    {
        $appointment = $this->newAppointment($appointmentId);
        if ($appointment['status'] !== 'submitted') {
            throw new InvalidArgumentException('Only submitted new staff appointments can be reviewed');
        }

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $this->db->beginTransaction();
        try {
            $this->db->query(
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
            $this->recordHistory('new', $appointmentId, $newStatus, $actorId, $data['reason'] ?? null, 'submitted', $newStatus, $appointment);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function onboardNew(int $appointmentId, int $actorId, int $roleId, array $data = []): array
    {
        if (!$roleId) {
            throw new InvalidArgumentException('role_id is required for account creation');
        }
        $appointment = $this->newAppointment($appointmentId);
        if ($appointment['status'] !== 'approved') {
            throw new InvalidArgumentException('Only approved new staff appointments can be onboarded');
        }

        $tempPassword = $this->generateTemporaryPassword();
        $username = $this->uniqueUsername($appointment['candidate_first_name'], $appointment['candidate_last_name']);
        $staffNo = $this->nextStaffNumber();

        $this->db->beginTransaction();
        try {
            $this->db->query(
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
            $userId = (int)$this->db->lastInsertId();

            $this->db->query(
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
            $staffId = (int)$this->db->lastInsertId();

            $this->db->query(
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
            $this->recordHistory('new', $appointmentId, 'onboarded', $actorId, $data['remarks'] ?? null, 'approved', 'onboarded', [
                'created_user_id' => $userId,
                'created_staff_id' => $staffId,
                'staff_no' => $staffNo,
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return [
            'user_id' => $userId,
            'staff_id' => $staffId,
            'staff_no' => $staffNo,
            'username' => $username,
            'email_sent' => $this->sendWelcomeEmail($appointment, $username, $tempPassword),
        ];
    }

    public function history(string $appointmentType, int $appointmentId): array
    {
        if (!$appointmentType || !$appointmentId) {
            throw new InvalidArgumentException('appointment_type and appointment_id are required');
        }

        return $this->db->query(
            "SELECT saa.*, CONCAT(s.first_name, ' ', s.last_name) AS actor_name
             FROM staff_appointment_approvals saa
             JOIN staff s ON s.id = saa.actor_id
             WHERE saa.appointment_type = :appointment_type AND saa.appointment_id = :appointment_id
             ORDER BY saa.created_at ASC",
            [':appointment_type' => $appointmentType, ':appointment_id' => $appointmentId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function staffIdForUser(array $user): ?int
    {
        if (!empty($user['staff_id'])) {
            return (int)$user['staff_id'];
        }
        $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
        if ($userId > 0) {
            $staff = $this->db->query('SELECT id FROM staff WHERE user_id = ?', [$userId])->fetch(PDO::FETCH_ASSOC);
            return $staff ? (int)$staff['id'] : null;
        }
        return null;
    }

    private function validateNewAppointment(array $data): void
    {
        foreach (['candidate_first_name', 'candidate_last_name', 'candidate_email', 'department_id', 'position', 'employment_date'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("{$field} is required");
            }
        }
        if (!filter_var($data['candidate_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('candidate_email must be a valid email address');
        }
    }

    private function insertNewAppointment(array $data, string $status, ?int $actorId = null): int
    {
        $this->db->query(
            "INSERT INTO staff_appointments
              (recruitment_id, candidate_first_name, candidate_last_name, candidate_email,
               candidate_phone, candidate_id_number, candidate_qualifications, candidate_experience,
               candidate_notes, department_id, position, employment_date, contract_type, salary,
               supervisor_id, staff_type_id, staff_category_id, status, submitted_by, submitted_at)
             VALUES
              (:recruitment_id, :first_name, :last_name, :email,
               :phone, :id_number, :qualifications, :experience,
               :notes, :department_id, :position, :employment_date, :contract_type, :salary,
               :supervisor_id, :staff_type_id, :staff_category_id, :status, :submitted_by, :submitted_at)",
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
                ':status' => $status,
                ':submitted_by' => $actorId,
                ':submitted_at' => $status === 'submitted' ? date('Y-m-d H:i:s') : null,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    private function internalAppointment(int $appointmentId): array
    {
        if (!$appointmentId) {
            throw new InvalidArgumentException('Appointment ID is required');
        }
        $appointment = $this->db->query('SELECT * FROM staff_promotions WHERE id = ?', [$appointmentId])->fetch(PDO::FETCH_ASSOC);
        if (!$appointment) {
            throw new InvalidArgumentException('Internal appointment not found');
        }
        return $appointment;
    }

    private function newAppointment(int $appointmentId): array
    {
        if (!$appointmentId) {
            throw new InvalidArgumentException('Appointment ID is required');
        }
        $appointment = $this->db->query('SELECT * FROM staff_appointments WHERE id = ?', [$appointmentId])->fetch(PDO::FETCH_ASSOC);
        if (!$appointment) {
            throw new InvalidArgumentException('New staff appointment not found');
        }
        return $appointment;
    }

    private function applyInternalAppointment(array $appointment, int $actorId): void
    {
        $this->db->query(
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
            $this->db->query(
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
            $adjustmentId = (int)$this->db->lastInsertId();
            $this->db->query('UPDATE staff_promotions SET payroll_adjustment_id = ? WHERE id = ?', [$adjustmentId, $appointment['id']]);
        }

        if ($appointment['effective_date'] <= date('Y-m-d')) {
            $this->db->query("UPDATE staff_promotions SET status = 'effective' WHERE id = ?", [$appointment['id']]);
        }
    }

    private function recordHistory(string $type, int $appointmentId, string $action, int $actorId, ?string $remarks, ?string $previousStatus, ?string $newStatus, array $changes): void
    {
        $this->db->query(
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

    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(4)) . 'K!';
    }

    private function uniqueUsername(string $firstName, string $lastName): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($firstName . '.' . $lastName)));
        $base = trim($base, '.') ?: 'staff';
        $username = $base;
        $suffix = 1;
        while ($this->db->query('SELECT id FROM users WHERE username = ?', [$username])->fetch()) {
            $username = $base . $suffix;
            $suffix++;
        }
        return $username;
    }

    private function nextStaffNumber(): string
    {
        $latest = $this->db->query(
            "SELECT staff_no FROM staff WHERE staff_no LIKE 'KWPS%' ORDER BY CAST(SUBSTRING(staff_no, 5) AS UNSIGNED) DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $next = $latest ? ((int)substr($latest['staff_no'], 4) + 1) : 1;
        return 'KWPS' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }

    private function sendWelcomeEmail(array $appointment, string $username, string $password): bool
    {
        try {
            $service = new MessageService($this->db->getConnection());
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
