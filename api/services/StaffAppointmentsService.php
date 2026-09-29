<?php

namespace App\API\Services;

use App\Database\Database;
use Exception;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Staff appointments service.
 *
 * Internal promotions live in the dedicated staff_promotions table (previously
 * carried as audit_logs rows with the legacy column set in details JSON).
 * Review/approve/revert transitions update the staff_promotions row; the
 * approval/history trail is stored in staff_appointment_approvals. New-staff
 * appointments still use staff_appointments. All public signatures are
 * unchanged.
 */
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
             GROUP BY status"
        )->fetchAll(PDO::FETCH_ASSOC);

        $newStaff = $this->db->query(
            "SELECT status, COUNT(*) AS total
             FROM staff_appointments
             GROUP BY status"
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['internal' => $internal, 'new_staff' => $newStaff];
    }

    public function jobApplicationAssignmentOptions(): array
    {
        return [
            'departments' => $this->db->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'staff_types' => $this->db->query("SELECT id,name FROM staff_types WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'staff_categories' => $this->db->query("SELECT id,staff_type_id,category_name AS name FROM staff_categories WHERE is_active=1 ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function listInternal(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['staff_id'])) {
            $where[] = 'p.staff_id = :staff_id';
            $params[':staff_id'] = (int)$filters['staff_id'];
        }

        $rows = $this->db->query(
            "SELECT p.id,
                    p.staff_id,
                    p.promotion_type,
                    p.is_temporary,
                    p.from_position,
                    p.to_position,
                    p.from_department_id,
                    p.to_department_id,
                    p.from_salary,
                    p.to_salary,
                    p.from_contract_type,
                    p.to_contract_type,
                    p.from_supervisor_id,
                    p.to_supervisor_id,
                    p.effective_date,
                    p.status,
                    p.reason,
                    p.letter_url,
                    p.created_by,
                    p.submitted_by,
                    p.submitted_at,
                    p.approved_by,
                    p.approved_at,
                    p.rejected_reason,
                    p.payroll_adjustment_id,
                    p.created_at,
                    CONCAT(pe.first_name, ' ', pe.last_name) AS staff_name,
                    s.staff_no,
                    fd.name AS from_department,
                    td.name AS to_department,
                    CONCAT(cbp.first_name, ' ', cbp.last_name) AS created_by_name,
                    CONCAT(sbp.first_name, ' ', sbp.last_name) AS submitted_by_name,
                    CONCAT(abp.first_name, ' ', abp.last_name) AS approved_by_name
             FROM staff_promotions p
             JOIN staff s ON s.id = p.staff_id
             JOIN persons pe ON pe.id = s.person_id
             LEFT JOIN departments fd ON fd.id = p.from_department_id
             LEFT JOIN departments td ON td.id = p.to_department_id
             LEFT JOIN users cu ON cu.id = p.created_by
             LEFT JOIN persons cbp ON cbp.id = cu.person_id
             LEFT JOIN users su ON su.id = p.submitted_by
             LEFT JOIN persons sbp ON sbp.id = su.person_id
             LEFT JOIN users au ON au.id = p.approved_by
             LEFT JOIN persons abp ON abp.id = au.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.created_at DESC
             LIMIT 200",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
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

        $staff = $this->db->query(
            "SELECT s.*, sda.department_id
             FROM staff s
             LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
             WHERE s.id = ?",
            [$staffId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$staff) {
            throw new InvalidArgumentException('Staff member not found');
        }

        $this->db->beginTransaction();
        try {
            $this->db->query(
                "INSERT INTO staff_promotions
                  (staff_id, promotion_type, is_temporary,
                   from_position, to_position, from_department_id, to_department_id,
                   from_salary, to_salary, from_contract_type, to_contract_type,
                   from_supervisor_id, to_supervisor_id, effective_date, status,
                   reason, letter_url, created_by, submitted_by, submitted_at)
                 VALUES
                  (?, ?, ?,
                   ?, ?, ?, ?,
                   ?, ?, ?, ?,
                   ?, ?, ?, 'pending',
                   ?, ?, ?, ?, ?)",
                [
                    $staffId,
                    $type,
                    $type === 'acting' ? 1 : 0,
                    $staff['position'],
                    $data['to_position'] ?? $staff['position'],
                    $staff['department_id'],
                    $data['to_department_id'] ?? $staff['department_id'],
                    $staff['salary'],
                    array_key_exists('to_salary', $data) ? $data['to_salary'] : $staff['salary'],
                    $staff['contract_type'],
                    $data['to_contract_type'] ?? $staff['contract_type'],
                    $staff['supervisor_id'],
                    array_key_exists('to_supervisor_id', $data) ? $data['to_supervisor_id'] : $staff['supervisor_id'],
                    $data['effective_date'],
                    $data['reason'] ?? null,
                    $data['letter_url'] ?? null,
                    $actorId,
                    $actorId,
                    date('Y-m-d H:i:s'),
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
                     salary = :salary,
                     contract_type = :contract_type,
                     supervisor_id = :supervisor_id,
                     updated_at = NOW()
                 WHERE id = :staff_id',
                [
                    ':position' => $appointment['from_position'],
                    ':salary' => $appointment['from_salary'],
                    ':contract_type' => $appointment['from_contract_type'],
                    ':supervisor_id' => $appointment['from_supervisor_id'],
                    ':staff_id' => $appointment['staff_id'],
                ]
            );

            // staff.department_id is gone — a department move is closed/reopened on
            // staff_department_assignments (current row has effective_to IS NULL).
            $this->closeDepartmentAssignment($appointment['staff_id'], $appointment['effective_date'] ?? date('Y-m-d'));
            if (!empty($appointment['from_department_id'])) {
                $this->openDepartmentAssignment($appointment['staff_id'], (int)$appointment['from_department_id'], $appointment['effective_date'] ?? date('Y-m-d'));
            }

            $this->db->query(
                "UPDATE staff_promotions
                 SET status = 'cancelled', cancelled_at = ?, updated_at = NOW()
                 WHERE id = ?",
                [date('Y-m-d H:i:s'), $appointmentId]
            );
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

        $rows = $this->db->query(
            "SELECT sa.*,
                    CASE WHEN sa.candidate_notes LIKE '%[job_application_id=%' THEN 'online_application'
                         WHEN sa.candidate_notes LIKE '%[candidate_source=walk_in]%' THEN 'walk_in'
                         ELSE 'staff_entered' END AS candidate_source,
                    d.name AS department_name,
                    CONCAT(sbp.first_name, ' ', sbp.last_name) AS submitted_by_name,
                    CONCAT(abp.first_name, ' ', abp.last_name) AS approved_by_name,
                    CONCAT(obp.first_name, ' ', obp.last_name) AS onboarded_by_name,
                    CASE WHEN ui.id IS NULL THEN 'not_sent'
                         WHEN ui.status='pending' AND ui.expires_at<=NOW() THEN 'expired'
                         ELSE ui.status END AS invitation_status,
                    COALESCE(om.status,'not_queued') AS invitation_delivery_status,
                    om.sent_at AS invitation_sent_at,
                    u.force_password_change AS setup_required,
                    u.profile_completed_at
             FROM staff_appointments sa
             LEFT JOIN departments d ON d.id = sa.department_id
             LEFT JOIN users sb ON sb.id = sa.submitted_by
             LEFT JOIN persons sbp ON sbp.id = sb.person_id
             LEFT JOIN users ab ON ab.id = sa.approved_by
             LEFT JOIN persons abp ON abp.id = ab.person_id
             LEFT JOIN users ob ON ob.id = sa.onboarded_by
             LEFT JOIN persons obp ON obp.id = ob.person_id
             LEFT JOIN users u ON u.id=sa.created_user_id
             LEFT JOIN user_invitations ui ON ui.id=(
                SELECT ui2.id FROM user_invitations ui2 WHERE ui2.user_id=u.id ORDER BY ui2.id DESC LIMIT 1
             )
             LEFT JOIN outbound_messages om ON om.id=(
                SELECT om2.id FROM outbound_messages om2 WHERE om2.user_id=u.id AND om2.template_key='staff_account_invitation' ORDER BY om2.id DESC LIMIT 1
             )
             WHERE " . implode(' AND ', $where) . "
             ORDER BY sa.created_at DESC
             LIMIT 200",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
        $profileGate = new StaffProfileCompletionService($this->db->getConnection());
        foreach ($rows as &$row) {
            $row['profile_completed'] = !empty($row['created_user_id'])
                && !$profileGate->isRequired((int)$row['created_user_id']) ? 1 : 0;
        }
        unset($row);
        return $rows;
    }

    public function submitNew(array $data, int $actorId): int
    {
        // This endpoint is the school-entered, in-person candidate path. Keep
        // source markers server-owned: notes supplied by a client must never
        // make a walk-in look like a reviewed online application at onboarding.
        $notes = trim((string)($data['candidate_notes'] ?? ''));
        $notes = trim((string)preg_replace('/\[(?:job_application_id=\d+|candidate_source=walk_in)\]/i', '', $notes));
        $data['candidate_notes'] = '[candidate_source=walk_in]' . ($notes !== '' ? ' ' . $notes : '');
        $this->validateNewAppointment($data);
        $this->validateEmploymentAssignment($data);
        $this->assertCandidateCanBecomeNewStaff((string)$data['candidate_email']);

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

    /** Create the school-owned employment proposal from a reviewed public application. */
    public function submitFromJobApplication(int $applicationId, array $assignment, int $actorId): int
    {
        if ($applicationId < 1) throw new InvalidArgumentException('Application ID is required');
        foreach (['department_id', 'position', 'employment_date', 'contract_type', 'staff_type_id', 'staff_category_id'] as $field) {
            if (trim((string)($assignment[$field] ?? '')) === '') throw new InvalidArgumentException("{$field} is required");
        }

        $this->db->beginTransaction();
        try {
            $application = $this->db->query('SELECT * FROM job_applications WHERE id=? FOR UPDATE', [$applicationId])->fetch(PDO::FETCH_ASSOC);
            if (!$application) throw new InvalidArgumentException('Job application not found');
            if (($application['status'] ?? '') !== 'interviewed' || ($application['applicant_type'] ?? 'external') !== 'external') {
                throw new InvalidArgumentException('Only an interviewed external applicant can be proposed for hire');
            }
            $this->assertCandidateCanBecomeNewStaff((string)$application['email']);
            $duplicate = $this->db->query(
                "SELECT id FROM staff_appointments WHERE candidate_notes LIKE ? AND status NOT IN ('rejected','cancelled') LIMIT 1 FOR UPDATE",
                ['%[job_application_id=' . $applicationId . ']%']
            )->fetchColumn();
            if ($duplicate) throw new InvalidArgumentException('A staff appointment already exists for this application');
            $payload = [
                'candidate_first_name' => $application['first_name'],
                'candidate_last_name' => $application['last_name'],
                'candidate_email' => $application['email'],
                'candidate_phone' => $application['phone'] ?? null,
                'candidate_qualifications' => $application['tsc_number'] ?? null,
                'candidate_notes' => '[job_application_id=' . $applicationId . '] School employment proposal from online application.',
                'department_id' => (int)$assignment['department_id'],
                'position' => trim((string)$assignment['position']),
                'employment_date' => (string)$assignment['employment_date'],
                'contract_type' => (string)$assignment['contract_type'],
                'salary' => null,
                'supervisor_id' => !empty($assignment['supervisor_id']) ? (int)$assignment['supervisor_id'] : null,
                'staff_type_id' => (int)$assignment['staff_type_id'],
                'staff_category_id' => (int)$assignment['staff_category_id'],
            ];
            $this->validateNewAppointment($payload);
            $this->validateEmploymentAssignment($payload);
            $appointmentId = $this->insertNewAppointment($payload, 'submitted', $actorId);
            $this->recordHistory('new', $appointmentId, 'submitted', $actorId, $payload['candidate_notes'], null, 'submitted', $payload);
            $this->db->commit();
            return $appointmentId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
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
            if ($action === 'approve') {
                $appointment = $this->applyInternalAppointment($appointment, $actorId);
            }
            $this->db->query(
                "UPDATE staff_promotions
                 SET status = :status, approved_by = :actor_id, approved_at = NOW(),
                     rejected_reason = :rejection_reason,
                     payroll_adjustment_id = :payroll_adjustment_id,
                     updated_at = NOW()
                 WHERE id = :id",
                [
                    ':status' => $appointment['status'],
                    ':actor_id' => $actorId,
                    ':rejection_reason' => $action === 'reject' ? ($data['reason'] ?? null) : null,
                    ':payroll_adjustment_id' => $appointment['payroll_adjustment_id'] ?? null,
                    ':id' => $appointmentId,
                ]
            );
            $this->recordHistory('internal', $appointmentId, $newStatus, $actorId, $data['reason'] ?? null, 'pending', $appointment['status'], $appointment);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function reviewNew(int $appointmentId, string $action, int $actorId, array $data = []): void
    {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $this->db->beginTransaction();
        try {
            $appointment = $this->db->query('SELECT * FROM staff_appointments WHERE id=? FOR UPDATE', [$appointmentId])->fetch(PDO::FETCH_ASSOC);
            if (!$appointment) throw new InvalidArgumentException('New staff appointment not found');
            if ($appointment['status'] !== 'submitted') throw new InvalidArgumentException('Only submitted new staff appointments can be reviewed');
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
        $roleCheck = $this->db->prepare("SELECT name FROM roles WHERE id=? AND is_active=1 AND scope='school' AND is_system=0 LIMIT 1");
        $roleCheck->execute([$roleId]);
        $roleName = strtolower(trim((string)$roleCheck->fetchColumn()));
        if ($roleName === '' || in_array($roleName, ['system administrator', 'parent'], true)) {
            throw new InvalidArgumentException('Choose an active staff role. System Administrator and Parent roles cannot be assigned through staff onboarding.');
        }
        // 4NF identity model: persons holds first/last name + email; users links via
        // person_id (password_hash, not password); staff links to the same person.
        // ids come from AUTO_INCREMENT — a MAX(id)+1 read here would race concurrent inserts.
        $invitationMessageId = 0;
        $this->db->beginTransaction();
        try {
            $appointment = $this->db->query('SELECT * FROM staff_appointments WHERE id=? FOR UPDATE', [$appointmentId])->fetch(PDO::FETCH_ASSOC);
            if (!$appointment) throw new InvalidArgumentException('New staff appointment not found');
            if ($appointment['status'] !== 'approved') throw new InvalidArgumentException('Only approved new staff appointments can be onboarded');
            $this->validateEmploymentAssignment($appointment);
            $identity = $this->db->query('SELECT id FROM persons WHERE LOWER(email)=LOWER(?) LIMIT 1 FOR UPDATE', [$appointment['candidate_email']])->fetchColumn();
            if ($identity) throw new RuntimeException('This email was linked to another account after the appointment was approved. Resolve the existing identity through staff management before onboarding.');
            $tempPassword = $this->generateTemporaryPassword();
            $username = UsernameService::generate(
                $this->db->getConnection(),
                (string) $appointment['candidate_email'],
                (string) $appointment['candidate_first_name'],
                (string) $appointment['candidate_last_name']
            );
            $staffNo = $this->nextStaffNumber();
            $this->db->query(
                "INSERT INTO persons (first_name, middle_name, last_name, email, phone)
                 VALUES (?, NULL, ?, ?, ?)",
                [$appointment['candidate_first_name'], $appointment['candidate_last_name'], $appointment['candidate_email'], $appointment['candidate_phone'] ?? null]
            );
            $personId = (int)$this->db->lastInsertId();

            $this->db->query(
                "INSERT INTO users (username, password_hash, person_id, status, force_password_change,
                    is_test_user, account_type, data_scope, two_factor_enabled, two_factor_method,
                    created_at, updated_at)
                 VALUES (?, ?, ?, 'active', 1, 0, 'real', 'live', 1, 'email', NOW(), NOW())",
                [$username, password_hash($tempPassword, PASSWORD_DEFAULT), $personId]
            );
            $userId = (int)$this->db->lastInsertId();
            $roleManager = new \App\API\Modules\users\UserRoleManager($this->db->getConnection());
            $roleAssignment = $roleManager->assignRole($userId, $roleId);
            if (empty($roleAssignment['success'])) throw new RuntimeException('The approved staff role could not be assigned.');
            $this->db->query("INSERT INTO user_two_factor_methods(user_id,method,label,is_primary,is_enabled,verified_at) VALUES(?,'email','Account email',1,1,NULL) ON DUPLICATE KEY UPDATE is_enabled=1,is_primary=1", [$userId]);

            $this->db->query(
                "INSERT INTO staff
                  (person_id, staff_type_id, staff_category_id, staff_no,
                   supervisor_id, position, employment_date, contract_type, salary, status, created_at, updated_at)
                 VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())",
                [
                    $personId,
                    $appointment['staff_type_id'],
                    $appointment['staff_category_id'],
                    $staffNo,
                    $appointment['supervisor_id'],
                    $appointment['position'],
                    $appointment['employment_date'],
                    $appointment['contract_type'],
                    $appointment['salary'],
                ]
            );
            $staffId = (int)$this->db->lastInsertId();
            $positionName = (string)$appointment['position'];
            $positionId = StaffPositionCatalog::resolveId($this->db->getConnection(), $positionName);
            $this->db->query(
                "INSERT INTO staff_employment_profiles
                    (staff_id,department_id,position_id,position,employment_date,contract_type,status,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,'active',NOW(),NOW())",
                [
                    $staffId,
                    (int)$appointment['department_id'],
                    $positionId,
                    $positionName,
                    (string)$appointment['employment_date'],
                    (string)$appointment['contract_type'],
                ]
            );
            if (!empty($appointment['department_id'])) {
                $this->openDepartmentAssignment($staffId, (int)$appointment['department_id'], $appointment['employment_date'] ?? date('Y-m-d'));
            }
            if ($appointment['salary'] !== null && $appointment['salary'] !== '') {
                (new StaffCompensationService($this->db))->saveIndividualSalary([
                    'staff_id' => $staffId,
                    'gross_salary' => $appointment['salary'],
                    'effective_from' => $appointment['employment_date'] ?: date('Y-m-01'),
                ], isset($appointment['approved_by']) ? (int)$appointment['approved_by'] : null);
            }

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
            if (preg_match('/\[job_application_id=(\d+)\]/', (string)($appointment['candidate_notes'] ?? ''), $sourceMatch)) {
                $applicationId = (int)$sourceMatch[1];
                $application = $this->db->query('SELECT status, staff_id, tsc_number FROM job_applications WHERE id=? FOR UPDATE', [$applicationId])->fetch(PDO::FETCH_ASSOC);
                if (!$application || $application['status'] !== 'interviewed' || !empty($application['staff_id'])) {
                    throw new RuntimeException('The source job application is no longer eligible for onboarding');
                }
                if (strcasecmp((string)$appointment['candidate_email'], (string)($application['email'] ?? '')) !== 0) {
                    throw new RuntimeException('The appointment identity does not match its source application');
                }
                $this->db->query("UPDATE job_applications SET status='hired', staff_id=?, updated_at=NOW() WHERE id=?", [$staffId, $applicationId]);
                $this->db->query("INSERT INTO job_application_status_history(application_id,from_status,to_status,changed_by,notes) VALUES(?,'interviewed','hired',?,'Staff account created from approved appointment')", [$applicationId, $actorId]);
                if (trim((string)($application['tsc_number'] ?? '')) !== '') {
                    $this->db->query(
                        "INSERT INTO person_professional_identifiers(person_id,identifier_type,identifier_value,issuing_body,is_primary,created_at,updated_at) VALUES(?,'tsc',?,'Teachers Service Commission',1,NOW(),NOW())",
                        [$personId, strtoupper(trim((string)$application['tsc_number']))]
                    );
                }
            }
            $invitationMessageId = $this->queueWelcomeInvitation($userId, $staffId, $appointment, $username, $actorId);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }

        try {
            $delivery = (new StaffMigrationService($this->db->getConnection()))->processEmailQueue(1, $invitationMessageId);
            $emailSent = (int)($delivery['sent'] ?? 0) === 1;
        } catch (\Throwable $mailError) {
            \App\API\Services\Logger::legacyError('New staff invitation remains queued for retry: ' . $mailError->getMessage());
            $emailSent = false;
        }

        return [
            'user_id' => $userId,
            'staff_id' => $staffId,
            'staff_no' => $staffNo,
            'username' => $username,
            'email_sent' => $emailSent,
        ];
    }

    public function history(string $appointmentType, int $appointmentId): array
    {
        if (!$appointmentType || !$appointmentId) {
            throw new InvalidArgumentException('appointment_type and appointment_id are required');
        }

        // Approval history is stored in staff_appointment_approvals with the
        // legacy staff_appointment_approvals column set as real columns.
        return $this->db->query(
            "SELECT a.id,
                    a.appointment_type,
                    a.appointment_id,
                    a.action,
                    a.actor_id,
                    a.remarks,
                    a.previous_status,
                    a.new_status,
                    a.changes AS changes_json,
                    a.created_at,
                    CONCAT(p.first_name, ' ', p.last_name) AS actor_name
             FROM staff_appointment_approvals a
             JOIN users u ON u.id = a.actor_id
             JOIN persons p ON p.id = u.person_id
             WHERE a.appointment_type = :appointment_type
               AND a.appointment_id = :appointment_id
             ORDER BY a.created_at ASC",
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
            $staff = $this->db->query(
                'SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ?',
                [$userId]
            )->fetch(PDO::FETCH_ASSOC);
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

    private function validateEmploymentAssignment(array $data): void
    {
        foreach (['department_id', 'position', 'employment_date', 'contract_type', 'staff_type_id', 'staff_category_id'] as $field) {
            if (trim((string)($data[$field] ?? '')) === '') throw new InvalidArgumentException("{$field} is required");
        }
        if (!is_numeric($data['department_id']) || !is_numeric($data['staff_type_id']) || !is_numeric($data['staff_category_id'])) throw new InvalidArgumentException('Invalid school assignment');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string)$data['employment_date']);
        if (!$date || $date->format('Y-m-d') !== $data['employment_date']) throw new InvalidArgumentException('employment_date must be a valid date');
        if (!in_array($data['contract_type'], ['permanent', 'contract', 'temporary'], true)) throw new InvalidArgumentException('Invalid contract_type');
        $department = $this->db->query("SELECT id FROM departments WHERE id=? AND status='active'", [(int)$data['department_id']])->fetchColumn();
        $classification = $this->db->query(
            'SELECT EXISTS(SELECT 1 FROM staff_types WHERE id=? AND is_active=1) AS type_ok, EXISTS(SELECT 1 FROM staff_categories WHERE id=? AND staff_type_id=? AND is_active=1) AS category_ok',
            [(int)$data['staff_type_id'], (int)$data['staff_category_id'], (int)$data['staff_type_id']]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$department || !(int)($classification['type_ok'] ?? 0) || !(int)($classification['category_ok'] ?? 0)) throw new InvalidArgumentException('Choose an active department and matching staff classification');
        if (!empty($data['supervisor_id'])) {
            $supervisor = $this->db->query("SELECT id FROM staff WHERE id=? AND status='active' AND data_scope='live' LIMIT 1", [(int)$data['supervisor_id']])->fetchColumn();
            if (!$supervisor) throw new InvalidArgumentException('Choose an active supervisor or leave it blank');
        }
    }

    private function assertCandidateCanBecomeNewStaff(string $email): void
    {
        $existing = $this->db->query(
            'SELECT p.id FROM persons p WHERE LOWER(p.email)=LOWER(?) LIMIT 1',
            [trim($email)]
        )->fetchColumn();
        if ($existing) {
            throw new InvalidArgumentException('This email is already linked to a person or staff account. Resolve the existing account through staff management before creating a new-staff appointment.');
        }
        $openAppointment = $this->db->query(
            "SELECT id FROM staff_appointments WHERE LOWER(candidate_email)=LOWER(?) AND status NOT IN ('rejected','cancelled') LIMIT 1",
            [trim($email)]
        )->fetchColumn();
        if ($openAppointment) throw new InvalidArgumentException('An active staff appointment already exists for this email address');
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
        $appointment = $this->db->query(
            'SELECT * FROM staff_promotions WHERE id = ?',
            [$appointmentId]
        )->fetch(PDO::FETCH_ASSOC);
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

    /**
     * Apply an approved internal promotion. staff has no department_id column, so a
     * department move is a new row in staff_department_assignments, and a salary move
     * is written to staff.salary + staff_payroll_profiles.basic_salary. Returns the
     * appointment with any updated status so the caller persists the row once.
     */
    private function applyInternalAppointment(array $appointment, int $actorId): array
    {
        $this->db->query(
            'UPDATE staff
             SET position = :position,
                 salary = :salary,
                 contract_type = :contract_type,
                 supervisor_id = :supervisor_id,
                 updated_at = NOW()
             WHERE id = :staff_id',
            [
                ':position' => $appointment['to_position'],
                ':salary' => $appointment['to_salary'],
                ':contract_type' => $appointment['to_contract_type'],
                ':supervisor_id' => $appointment['to_supervisor_id'],
                ':staff_id' => $appointment['staff_id'],
            ]
        );

        $toDept = $appointment['to_department_id'] ?? null;
        $fromDept = $appointment['from_department_id'] ?? null;
        if ($toDept !== null && $toDept !== '' && (string)$toDept !== (string)$fromDept) {
            $this->closeDepartmentAssignment($appointment['staff_id'], $appointment['effective_date'] ?? date('Y-m-d'));
            $this->openDepartmentAssignment($appointment['staff_id'], (int)$toDept, $appointment['effective_date'] ?? date('Y-m-d'));
        }

        if ((string)($appointment['from_salary'] ?? '') !== (string)($appointment['to_salary'] ?? '')
            && $appointment['to_salary'] !== null && $appointment['to_salary'] !== '') {
            (new StaffCompensationService($this->db))->saveIndividualSalary([
                'staff_id' => (int)$appointment['staff_id'],
                'gross_salary' => $appointment['to_salary'],
                'effective_from' => $appointment['effective_date'] ?? date('Y-m-01'),
            ], isset($appointment['approved_by']) ? (int)$appointment['approved_by'] : null);
            $appointment['payroll_adjustment_id'] = (int)$appointment['id'];
        }

        if (!empty($appointment['effective_date']) && $appointment['effective_date'] <= date('Y-m-d')) {
            $appointment['status'] = 'effective';
        }

        return $appointment;
    }

    private function closeDepartmentAssignment(int $staffId, string $effectiveDate): void
    {
        $this->db->query(
            "UPDATE staff_department_assignments SET effective_to = ? WHERE staff_id = ? AND effective_to IS NULL",
            [$effectiveDate, $staffId]
        );
    }

    private function openDepartmentAssignment(int $staffId, int $departmentId, string $effectiveDate): void
    {
        $this->db->query(
            "INSERT INTO staff_department_assignments (staff_id, department_id, effective_from)
             VALUES (?, ?, ?)",
            [$staffId, $departmentId, $effectiveDate]
        );
    }

    private function recordHistory(string $type, int $appointmentId, string $action, int $actorId, ?string $remarks, ?string $previousStatus, ?string $newStatus, array $changes): void
    {
        $this->db->query(
            "INSERT INTO staff_appointment_approvals
              (appointment_type, appointment_id, action, actor_id, remarks, previous_status, new_status, changes)
             VALUES
              (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $type,
                $appointmentId,
                $action,
                $actorId,
                $remarks,
                $previousStatus,
                $newStatus,
                json_encode($changes, JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(4)) . 'K!';
    }

    private function nextStaffNumber(): string
    {
        $service = new StaffNumberService($this->db->getConnection());
        return $service->generate();
    }

    private function queueWelcomeInvitation(
        int $userId,
        int $staffId,
        array $appointment,
        string $username,
        int $actorId
    ): int
    {
        $this->db->query("UPDATE outbound_messages SET status='cancelled',last_error='Replaced by a newer staff invitation',updated_at=NOW() WHERE user_id=? AND template_key='staff_account_invitation' AND status IN ('queued','retry')", [$userId]);
        $this->db->query(
            "UPDATE user_invitations SET status='revoked', revoked_at=NOW(), updated_at=NOW()
             WHERE user_id=? AND status='pending'",
            [$userId]
        );
        $token = bin2hex(random_bytes(32));
        $this->db->query(
            "INSERT INTO user_invitations
                (user_id, staff_id, email, token_hash, status, expires_at, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 72 HOUR), ?, NOW(), NOW())",
            [$userId, $staffId, strtolower($appointment['candidate_email']), hash('sha256', $token), $actorId]
        );
        $base = StaffMigrationService::applicationBaseUrl();
        $payload = [
            'name' => trim($appointment['candidate_first_name'] . ' ' . $appointment['candidate_last_name']),
            'username' => $username,
            'setup_url' => StaffMigrationService::invitationSetupUrl($token),
            'login_url' => $base . '/index.php?route=r6d394ab20b0b',
            'profile_url' => $base . '/home.php?route=complete_staff_profile',
            'expires_hours' => 72,
        ];
        $this->db->query(
            "INSERT INTO outbound_messages
                (user_id, channel, recipient, template_key, subject, payload_json, status, attempts, next_attempt_at, created_at, updated_at)
             VALUES (?, 'email', ?, 'staff_account_invitation', 'Welcome to Kingsway — set up your staff account', ?, 'queued', 0, NOW(), NOW(), NOW())",
            [$userId, strtolower($appointment['candidate_email']), json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );
        return (int)$this->db->lastInsertId();
    }
}
