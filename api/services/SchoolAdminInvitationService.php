<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Modules\users\UsersAPI;
use PDO;
use Throwable;

/**
 * SchoolAdminInvitationService — owns the School Administrator bootstrap and
 * invitation lifecycle (directory listing, invitation create/cancel). Extracted
 * from StaffController under the strict SQL placement rule: the controller only
 * authenticates, validates shape and formats responses.
 */
final class SchoolAdminInvitationService
{
    public function __construct(private PDO $pdo)
    {
    }


    /** Full bootstrap directory for the invitation screen. */
    public function bootstrapData(): array
    {
        $roleStmt = $this->pdo->prepare(
            "SELECT id,name FROM roles
             WHERE LOWER(TRIM(name))='school administrator'
               AND is_active=1 AND scope='school' AND is_system=0
             LIMIT 1"
        );
        $roleStmt->execute();
        $schoolAdminRole = $roleStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $count = 0;
        if ($schoolAdminRole) {
            $countStmt = $this->pdo->prepare(
                'SELECT COUNT(DISTINCT u.id)
                 FROM ' . ReadReplicaService::qualifiedRef('person_directory') . '  
                 WHERE ur.role_id=? AND COALESCE(u.is_test_user,0)=0'
            );
            $countStmt->execute([(int) $schoolAdminRole['id']]);
            $count = (int) $countStmt->fetchColumn();
        }

        $positions = \App\API\Services\StaffPositionCatalog::list($this->pdo, true);
        $defaultPosition = $schoolAdminRole
            ? \App\API\Services\StaffPositionCatalog::defaultForRole($this->pdo, (int) $schoolAdminRole['id'])
            : null;
        $positionRoleIds = [];
        foreach ($positions as $positionRow) {
            $positionRoleIds[(int) $positionRow['id']] = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) ($positionRow['role_ids'] ?? ''))
            )));
        }
        unset($positionRow);

        // Per-account invitation listing — every staff account is visible only
        // through this governed directory read, so the System Administrator sees
        // who holds the administrator role and whether their invitation is live.
        $invitations = [];
        if ($schoolAdminRole) {
            $invitationsQuery = $this->pdo->prepare("
                SELECT u.id AS user_id, u.username, u.status AS user_status,
                       u.force_password_change AS setup_required,
                       u.profile_completed_at,
                       u.created_at AS account_created_at,
                       CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                       p.email, s.id AS staff_id, s.staff_no,
                       COALESCE(d.name, employment_department.name) AS department_name,
                       ui.id AS invitation_id, ui.status AS raw_invitation_status,
                       CASE WHEN ui.id IS NULL THEN 'not_sent'
                            WHEN ui.status='pending' AND ui.expires_at<=NOW() THEN 'expired'
                            ELSE ui.status END AS invitation_status,
                       om.status AS email_delivery_status,
                       om.sent_at AS invitation_sent_at, ui.expires_at,
                       ui.accepted_at
                FROM users u
                JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " pd ON pd.person_id = u.person_id
                JOIN user_roles ur ON ur.user_id=u.id AND ur.role_id=?
                LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.person_id=p.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("staff_department_assignments") . " sda ON sda.id=(
                    SELECT a.id FROM " . ReadReplicaService::qualifiedRef("staff_department_assignments") . " a
                    WHERE a.staff_id=s.id AND a.effective_to IS NULL
                    ORDER BY a.effective_from DESC,a.id DESC LIMIT 1
                )
                LEFT JOIN " . ReadReplicaService::qualifiedRef("departments") . " d ON d.id=sda.department_id
                LEFT JOIN staff_employment_profiles sep ON sep.id=(
                    SELECT current_sep.id FROM staff_employment_profiles current_sep
                    WHERE current_sep.staff_id=s.id AND current_sep.status='active'
                    ORDER BY COALESCE(current_sep.employment_date,'1000-01-01') DESC,
                             current_sep.updated_at DESC,current_sep.id DESC LIMIT 1
                )
                LEFT JOIN " . ReadReplicaService::qualifiedRef("departments") . " employment_department ON employment_department.id=sep.department_id
                LEFT JOIN user_invitations ui ON ui.id=(
                    SELECT ui2.id FROM user_invitations ui2
                    WHERE ui2.user_id=u.id ORDER BY ui2.id DESC LIMIT 1
                )
                LEFT JOIN outbound_messages om ON om.id=(
                    SELECT MAX(m.id) FROM outbound_messages m
                    WHERE m.user_id=u.id AND m.template_key='staff_account_invitation'
                )
                WHERE COALESCE(u.is_test_user,0)=0
                ORDER BY COALESCE(ui.created_at,u.created_at) DESC,ui.id DESC,u.id DESC
            ");
            $invitationsQuery->execute([(int) $schoolAdminRole['id']]);
            $invitations = $invitationsQuery->fetchAll(PDO::FETCH_ASSOC);
        }

        $profileGate = new StaffProfileCompletionService($this->pdo);
        foreach ($invitations as &$invitation) {
            $invitation['profile_completed'] = !empty($invitation['staff_id'])
                && !$profileGate->isRequired((int) $invitation['user_id']) ? 1 : 0;
        }
        unset($invitation);

        return [
            'available' => true,
            'administrator_count' => $count,
            'departments' => $this->pdo->query("SELECT id,name,code FROM departments WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'staff_types' => $this->pdo->query("SELECT id,name FROM staff_types WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'staff_categories' => $this->pdo->query("SELECT id,staff_type_id,category_name AS name FROM staff_categories WHERE is_active=1 ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC),
            'supervisors' => $this->pdo->query("SELECT s.staff_id AS id,s.staff_no,CONCAT_WS(' ',s.first_name,s.last_name) AS name FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " s WHERE s.staff_status='active' AND s.data_scope='live' AND s.person_id IS NOT NULL ORDER BY s.last_name,s.first_name")->fetchAll(PDO::FETCH_ASSOC),
            'positions' => $positions,
            'position_role_ids' => $positionRoleIds,
            'default_position' => $defaultPosition ? (string) $defaultPosition['name'] : null,
            'invitations' => $invitations,
            'role' => $schoolAdminRole,
        ];
    }

    /**
     * Create the account + staff assignment and queue the invitation email.
     * The UsersAPI create instance and the migration service are injected so
     * this service never constructs HTTP-bound objects itself.
     *
     * @return array{user_id:int, staff_id:int, staff_no:string, email:string, invitation_queued:bool, email_sent:bool}
     */
    public function invite(array $raw, int $actorId, UsersAPI $usersApi, StaffMigrationService $migration): array
    {
        $data = $raw;
        $data['first_name'] = trim((string) ($data['first_name'] ?? ''));
        $data['last_name'] = trim((string) ($data['last_name'] ?? ''));
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
        $data['position'] = StaffPositionCatalog::normalize((string) ($data['position'] ?? ''));
        $data['employment_date'] = trim((string) ($data['employment_date'] ?? ''));
        $data['contract_type'] = strtolower(trim((string) ($data['contract_type'] ?? '')));
        $data['staff_type_id'] = (int) ($data['staff_type_id'] ?? 0);
        $data['staff_category_id'] = (int) ($data['staff_category_id'] ?? 0);
        $position = $data['position'];
        $employmentDate = $data['employment_date'];
        $contractType = $data['contract_type'];
        $staffTypeId = $data['staff_type_id'];
        $staffCategoryId = $data['staff_category_id'];
        $departmentId = (int) ($data['department_id'] ?? 0);
        $supervisorId = (int) ($data['supervisor_id'] ?? 0);

        // Identity must be unique before anything is written.
        $identityCheck = $this->pdo->prepare('SELECT 1 FROM persons WHERE LOWER(email)=LOWER(?) LIMIT 1');
        $identityCheck->execute([$data['email']]);
        if ($identityCheck->fetchColumn()) {
            throw new \RuntimeException('This email already belongs to an account. Use staff management to assign the School Administrator role to an existing staff account.', 409);
        }

        $departmentCheck = $this->pdo->prepare("SELECT id FROM departments WHERE id=? AND status='active' LIMIT 1");
        $departmentCheck->execute([$departmentId]);
        if ($departmentId < 1 || !$departmentCheck->fetchColumn()) {
            throw new \RuntimeException('Choose an active department for this account.', 400);
        }
        $classification = $this->pdo->prepare("SELECT EXISTS(SELECT 1 FROM staff_types WHERE id=? AND is_active=1 AND LOWER(name)='administration'), EXISTS(SELECT 1 FROM staff_categories WHERE id=? AND staff_type_id=? AND is_active=1)");
        $classification->execute([$staffTypeId, $staffCategoryId, $staffTypeId]);
        if (array_map('intval', $classification->fetch(PDO::FETCH_NUM) ?: []) !== [1, 1]) {
            throw new \RuntimeException('Choose a valid staff type and matching category.', 400);
        }
        if ($supervisorId > 0) {
            $supervisorCheck = $this->pdo->prepare("SELECT 1 FROM staff WHERE id=? AND status='active' AND data_scope='live'");
            $supervisorCheck->execute([$supervisorId]);
            if (!$supervisorCheck->fetchColumn()) {
                throw new \RuntimeException('Choose an active supervisor or leave the field blank.', 400);
            }
        }

        // The employment position is an active catalogue entry compatible with
        // this role before the transaction; a blank title falls back to the role
        // default so a retired legacy label never blocks an invitation.
        $catalog = StaffPositionCatalog::class;
        $schoolAdminRoleId = $catalog::resolveId($this->pdo, '');
        // resolveId('') is intentionally never valid; the real id comes next.
        $roleStmt = $this->pdo->prepare(
            "SELECT id FROM roles
             WHERE LOWER(TRIM(name))='school administrator'
               AND is_active=1 AND scope='school' AND is_system=0
             LIMIT 1"
        );
        $roleStmt->execute();
        $schoolAdminRoleId = (int) $roleStmt->fetchColumn();
        if ($schoolAdminRoleId < 1) {
            throw new \RuntimeException('The active School Administrator role is not configured.', 400);
        }
        try {
            if ($position === '') {
                $fallback = $catalog::defaultForRole($this->pdo, $schoolAdminRoleId, $staffTypeId, $staffCategoryId);
                if (!$fallback) {
                    throw new \RuntimeException('No active employment position is configured for the School Administrator role. Ask the System Administrator to add one to the school position catalogue.', 400);
                }
                $position = (string) $fallback['name'];
            } else {
                $position = $catalog::assertActive($this->pdo, $position, $staffTypeId, $staffCategoryId, $schoolAdminRoleId);
            }
            $positionId = $catalog::resolveId($this->pdo, $position);
        } catch (\RuntimeException $positionError) {
            throw $positionError;
        }

        // Keep assignment fields out of UsersAPI's flattened payload.
        unset(
            $data['department_id'], $data['position'], $data['employment_date'],
            $data['contract_type'], $data['staff_type_id'], $data['staff_category_id'],
            $data['salary'], $data['supervisor_id']
        );

        $lock = (int) $this->pdo->query("SELECT GET_LOCK('kingsway:school_administrator_invitation', 10)")->fetchColumn();
        if ($lock !== 1) {
            throw new \RuntimeException('Another School Administrator invitation is being processed.', 409);
        }

        try {
            $this->pdo->beginTransaction();
            // Re-verify inside the transaction: the role or position could have
            // been retired between validation and the write.
            $lockedRole = $this->pdo->prepare(
                "SELECT id FROM roles
                 WHERE LOWER(TRIM(name))='school administrator'
                   AND is_active=1 AND scope='school' AND is_system=0
                 LIMIT 1"
            );
            $lockedRole->execute();
            if ((int) $lockedRole->fetchColumn() !== $schoolAdminRoleId) {
                throw new \RuntimeException('The active School Administrator role is not configured.', 409);
            }
            $activePosition = $this->pdo->prepare('SELECT id FROM staff_positions WHERE id=? AND is_active=1');
            $activePosition->execute([$positionId]);
            if ((int) $activePosition->fetchColumn() !== $positionId) {
                throw new \RuntimeException('The selected employment position is no longer active. Choose an active school position.', 400);
            }

            $data['role_id'] = $schoolAdminRoleId;
            $data['role_ids'] = [$schoolAdminRoleId];
            $data['status'] = 'active';
            $data['force_password_change'] = 1;
            $data['account_type'] = 'real';
            $data['data_scope'] = 'live';
            $data['password'] = 'Kwps-' . bin2hex(random_bytes(12)) . '!aA';
            $result = $usersApi->create($data, true, true);
            if (empty($result['success'])) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw new \RuntimeException('Account creation failed.', 422);
            }

            $lookup = $this->pdo->prepare('SELECT u.id AS user_id, u.person_id FROM users u JOIN ' . ReadReplicaService::qualifiedRef('person_directory') . ' pd ON pd.person_id = u.person_id WHERE LOWER(pd.email)=? LIMIT 1');
            $lookup->execute([$data['email']]);
            $created = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$created) {
                throw new \RuntimeException('The created School Administrator could not be verified.', 500);
            }

            $staffNo = (new StaffNumberService($this->pdo))->generate();
            $this->pdo->prepare("INSERT INTO staff (person_id,staff_type_id,staff_category_id,staff_no,position,contract_type,employment_date,status,data_scope,supervisor_id,salary,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'active','live',NULLIF(?,0),?,NOW(),NOW())")
                ->execute([(int) $created['person_id'], $staffTypeId, $staffCategoryId, $staffNo, $position, $contractType, $employmentDate, $supervisorId, null]);
            $staffId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare("INSERT INTO staff_employment_profiles
                (staff_id,department_id,position_id,position,employment_date,contract_type,status,created_at,updated_at)
                VALUES (?,?,?,?,?,?,'active',NOW(),NOW())")
                ->execute([$staffId, $departmentId, $positionId, $position, $employmentDate, $contractType]);
            $this->pdo->prepare("INSERT INTO staff_department_assignments(staff_id,department_id,role,effective_from,created_at) VALUES(?,?,NULL,?,NOW())")
                ->execute([$staffId, $departmentId, $employmentDate]);

            $invitation = $migration->resendInvitation((int) $created['user_id'], $actorId);
            if (empty($invitation['queued'])) {
                throw new \RuntimeException('The account was created but the invitation could not be queued.', 500);
            }
            $this->pdo->commit();

            return [
                'user_id' => (int) $created['user_id'],
                'staff_id' => $staffId,
                'staff_no' => $staffNo,
                'email' => $data['email'],
                'invitation_queued' => true,
                'email_sent' => false,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            Logger::legacyError('[SchoolAdministratorInvitation] ' . $e->getMessage());
            throw $e;
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('kingsway:school_administrator_invitation')");
        }
    }

    /** Whether the user id resolves to an active School Administrator account. */
    public function isSchoolAdmin(int $userId): bool
    {
        $target = $this->pdo->prepare("SELECT user_id AS id FROM " . ReadReplicaService::masterRef('user_role_grant') . " WHERE user_id=? AND role_name='School Administrator' AND COALESCE(is_test_user,0)=0 LIMIT 1");
        $target->execute([$userId]);
        return (bool) $target->fetchColumn();
    }

    /** Cancel a pending invitation (revoke the invitation + cancel queued mails). */
    public function cancelInvitation(int $userId, StaffDomainAccessService $audit): void
    {
        $this->pdo->beginTransaction();
        try {
            $revoke = $this->pdo->prepare("UPDATE user_invitations SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE user_id=? AND status='pending'");
            $revoke->execute([$userId]);
            if ($revoke->rowCount() < 1) {
                $this->pdo->rollBack();
                throw new \RuntimeException('This invitation is no longer pending. Refresh the list and try again.', 400);
            }
            $this->pdo->prepare("UPDATE outbound_messages SET status='cancelled',last_error='Invitation cancelled by administrator',updated_at=NOW() WHERE user_id=? AND template_key='staff_account_invitation' AND status IN ('queued','retry')")
                ->execute([$userId]);
            $audit->audit('cancel_school_administrator_invitation', 'user', $userId, ['invitation_status' => 'pending'], ['invitation_status' => 'revoked']);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
