<?php
namespace App\API\Services;

use App\Database\Database;
use App\API\Includes\FileLogger;
use PDO;
use RuntimeException;

/**
 * Staff lifecycle service.
 *
 * Lifecycle actions are persisted in the dedicated staff_lifecycle_actions
 * table (previously carried in audit_logs details JSON). All public method
 * names and output keys are preserved.
 */
final class StaffLifecycleService
{
    private Database $db;
    private StaffRecordsService $recordsService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->recordsService = new StaffRecordsService($this->db);
    }

    public function dashboard(array $filters = []): array
    {
        // Identity (names/email) is on persons; the current department comes from
        // staff_department_assignments (effective_to IS NULL = current); the
        // staff↔user link is the shared persons row; onboarding status is the
        // workflow status surfaced by vw_staff_onboarding_progress.
        $where = ['1=1']; $params = [];
        if (!empty($filters['status'])) { $where[] = 's.status = :status'; $params[':status'] = $filters['status']; }
        if (!empty($filters['department_id'])) {
            $where[] = 'sda.department_id = :department_id AND sda.effective_to IS NULL';
            $params[':department_id'] = (int)$filters['department_id'];
        }
        $clause = implode(' AND ', $where);
        $staff = $this->db->query(
            "SELECT s.id,s.staff_no,p.first_name,p.last_name,s.position,s.status,s.employment_date,s.contract_type,
                    sda.department_id,d.name AS department_name,p.email,u.status AS user_status,
                    COALESCE(op.status,'completed') AS onboarding_status
             FROM staff s
             JOIN persons p ON p.id=s.person_id
             LEFT JOIN staff_department_assignments sda ON sda.staff_id=s.id AND sda.effective_to IS NULL
             LEFT JOIN departments d ON d.id=sda.department_id
             LEFT JOIN users u ON u.person_id=s.person_id
             LEFT JOIN vw_staff_onboarding_progress op ON op.staff_id=s.id
             WHERE {$clause}
             ORDER BY p.last_name,p.first_name LIMIT 500", $params
        )->fetchAll(PDO::FETCH_ASSOC);

        $summary = $this->db->query(
            "SELECT COUNT(*) total,
                    SUM(status='active') active,
                    SUM(status='on_leave') suspended,
                    SUM(status='inactive') exited
             FROM staff"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $pending = (int)$this->db->query(
            "SELECT COUNT(*) FROM staff_lifecycle_actions WHERE status = 'pending'"
        )->fetchColumn();
        return ['summary' => $summary + ['pending_actions' => $pending], 'staff' => $staff];
    }

    public function timeline(int $staffId): array
    {
        $staff = $this->db->query(
            "SELECT s.*,p.first_name,p.last_name,p.email,d.name department_name,u.status user_status
             FROM staff s
             LEFT JOIN persons p ON p.id=s.person_id
             LEFT JOIN staff_department_assignments sda ON sda.staff_id=s.id AND sda.effective_to IS NULL
             LEFT JOIN departments d ON d.id=sda.department_id
             LEFT JOIN users u ON u.person_id=s.person_id
             WHERE s.id=?", [$staffId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$staff) throw new RuntimeException('Staff member not found');

        $actions = $this->db->query(
            "SELECT la.id,
                    la.staff_id,
                    la.action_type,
                    la.status,
                    la.effective_date,
                    la.reason,
                    la.from_position,
                    la.to_position,
                    la.from_department_id,
                    la.to_department_id,
                    la.from_salary,
                    la.to_salary,
                    la.from_contract_type,
                    la.to_contract_type,
                    la.from_supervisor_id,
                    la.to_supervisor_id,
                    la.notes,
                    la.created_by,
                    la.approved_by,
                    la.approved_at,
                    la.review_comment,
                    la.applied_at,
                    la.user_id,
                    la.created_at,
                    CONCAT(cp.first_name,' ',cp.last_name) created_by_name,
                    CONCAT(ap.first_name,' ',ap.last_name) approved_by_name,
                    fd.name from_department_name,
                    td.name to_department_name
             FROM staff_lifecycle_actions la
             LEFT JOIN users cu ON cu.id = la.user_id
             LEFT JOIN persons cp ON cp.id = cu.person_id
             LEFT JOIN users apu ON apu.id = la.approved_by
             LEFT JOIN persons ap ON ap.id = apu.person_id
             LEFT JOIN departments fd ON fd.id = la.from_department_id
             LEFT JOIN departments td ON td.id = la.to_department_id
             WHERE la.staff_id = ?
             ORDER BY la.effective_date DESC, la.created_at DESC",
            [$staffId]
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['staff'=>$staff,'timeline'=>$actions];
    }

    public function referenceData(): array
    {
        return [
            'departments'=>$this->db->query("SELECT id,name,code FROM departments WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'roles'=>$this->db->query("SELECT id,name,description FROM roles WHERE is_active=1 AND scope='school' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'staff'=>$this->db->query(
                "SELECT s.id,s.staff_no,CONCAT(p.first_name,' ',p.last_name) name
                 FROM staff s JOIN persons p ON p.id=s.person_id
                 WHERE s.status='active' ORDER BY p.first_name,p.last_name"
            )->fetchAll(PDO::FETCH_ASSOC),
            'action_types'=>['promotion','demotion','transfer','acting_appointment','confirmation','contract_renewal','salary_change','suspension','reinstatement','resignation','retirement','termination'],
        ];
    }

    public function createAction(array $data, int $actorUserId): int
    {
        foreach (['staff_id','action_type','effective_date','reason'] as $field) {
            if (empty($data[$field])) throw new RuntimeException("{$field} is required");
        }
        $allowed = $this->referenceData()['action_types'];
        if (!in_array($data['action_type'], $allowed, true)) throw new RuntimeException('Unsupported lifecycle action');
        $staff = $this->db->query(
            "SELECT s.*, sda.department_id
             FROM staff s
             LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
             WHERE s.id = ?",
            [(int)$data['staff_id']]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$staff) throw new RuntimeException('Staff member not found');
        if (in_array($data['action_type'], ['promotion','demotion','transfer','acting_appointment'], true) && empty($data['to_position']) && empty($data['to_department_id'])) {
            throw new RuntimeException('A new position or department is required');
        }

        $this->db->query(
            "INSERT INTO staff_lifecycle_actions
             (staff_id, action_type, status, effective_date, reason,
              from_position, to_position, from_department_id, to_department_id,
              from_salary, to_salary, from_contract_type, to_contract_type,
              from_supervisor_id, to_supervisor_id, notes, created_by, user_id)
             VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                (int)$data['staff_id'],
                $data['action_type'],
                $data['effective_date'],
                $data['reason'],
                $staff['position'] ?? null,
                $data['to_position'] ?? $staff['position'],
                $staff['department_id'] ?? null,
                $data['to_department_id'] ?? $staff['department_id'],
                $staff['salary'] ?? null,
                $data['to_salary'] ?? $staff['salary'],
                $staff['contract_type'] ?? null,
                $data['to_contract_type'] ?? $staff['contract_type'],
                $staff['supervisor_id'] ?? null,
                $data['to_supervisor_id'] ?? $staff['supervisor_id'],
                $data['notes'] ?? null,
                $actorUserId,
                $actorUserId,
            ]
        );
        $id = (int)$this->db->lastInsertId();
        $this->audit($actorUserId, 'staff_lifecycle_action_created', $id, $data);
        return $id;
    }

    public function reviewAction(int $actionId, string $decision, int $actorUserId, ?string $comment = null): void
    {
        if (!in_array($decision, ['approve','reject'], true)) throw new RuntimeException('Invalid decision');
        $action = $this->db->query(
            "SELECT * FROM staff_lifecycle_actions WHERE id = ? FOR UPDATE",
            [$actionId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$action) throw new RuntimeException('Lifecycle action not found');
        if (($action['status'] ?? null) !== 'pending') throw new RuntimeException('Only pending actions can be reviewed');
        $this->db->beginTransaction();
        try {
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $this->db->query(
                "UPDATE staff_lifecycle_actions
                 SET status = ?, approved_by = ?, approved_at = ?, review_comment = ?
                 WHERE id = ?",
                [$status, $actorUserId, date('Y-m-d H:i:s'), $comment, $actionId]
            );
            if ($decision === 'approve') {
                $action['id'] = $actionId;
                $this->applyAction($action, $actorUserId);
            }
            $this->audit($actorUserId, 'staff_lifecycle_action_' . $status, $actionId, ['comment' => $comment]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollback(); throw $e; }
    }

    private function applyAction(array $a, int $actorUserId): void
    {
        $staffId = (int)$a['staff_id'];
        $sets = []; $params = [];
        foreach ([
            'position' => 'to_position',
            'salary' => 'to_salary',
            'contract_type' => 'to_contract_type',
            'supervisor_id' => 'to_supervisor_id'
        ] as $column => $source) {
            if (isset($a[$source]) && $a[$source] !== null && $a[$source] !== '') { $sets[] = "{$column}=?"; $params[] = $a[$source]; }
        }

        $statusMap = [
            'suspension' => 'on_leave',
            'reinstatement' => 'active',
            'resignation' => 'inactive',
            'retirement' => 'inactive',
            'termination' => 'inactive',
            'confirmation' => 'active',
        ];

        if (isset($statusMap[$a['action_type']])) {
            $sets[] = 'status=?';
            $params[] = $statusMap[$a['action_type']];
        }

        $sets[] = 'updated_at=NOW()';
        $params[] = $staffId;
        $this->db->query(
            'UPDATE staff SET ' . implode(',', $sets) . ' WHERE id=?',
            $params
        );

        // staff.department_id is dropped — a department move is a time-boxed row in
        // staff_department_assignments (close the current one, open the new one).
        $toDept = $a['to_department_id'] ?? null;
        $fromDept = $a['from_department_id'] ?? null;
        if ($toDept !== null && $toDept !== '' && (string)$toDept !== (string)$fromDept) {
            $this->assignDepartment($staffId, (int)$toDept, (string)($a['effective_date'] ?? date('Y-m-d')));
        }

        // Salary moves are reflected on staff.salary and the payroll profile.
        if (isset($a['to_salary']) && $a['to_salary'] !== '' && (string)$a['to_salary'] !== (string)$a['from_salary']) {
            $this->db->query(
                "INSERT INTO staff_payroll_profiles (staff_id, basic_salary, status)
                 VALUES (?, ?, 'active')
                 ON DUPLICATE KEY UPDATE basic_salary = ?",
                [$staffId, $a['to_salary'], $a['to_salary']]
            );
        }

        $revokingActions = [
            'suspension',
            'resignation',
            'retirement',
            'termination',
        ];

        if (in_array($a['action_type'], $revokingActions, true)) {
            $this->recordsService->revokeLatestSecurityPass(
                $staffId,
                $actorUserId,
                'Staff lifecycle action: ' . (string) $a['action_type']
            );
        }

        /*
         * A transfer in this service is an internal department/position move,
         * so it does not invalidate the staff security pass.
         */
        if (in_array($a['action_type'], ['resignation','retirement','termination'], true)) {
            $this->db->query(
                "UPDATE users u JOIN staff s ON s.person_id = u.person_id SET u.status='inactive',u.updated_at=NOW() WHERE s.id=?",
                [$staffId]
            );
            $this->db->query(
                "UPDATE user_sessions us JOIN users u ON u.id = us.user_id JOIN staff s ON s.person_id = u.person_id
                 SET us.logout_time = NOW(), us.session_status = 'logged_out'
                 WHERE s.id = ? AND us.logout_time IS NULL",
                [$staffId]
            );
        }

        $this->db->query(
            "UPDATE staff_lifecycle_actions SET status = 'effective', applied_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), (int)$a['id']]
        );
    }

    public function cancelAction(int $actionId, int $actorUserId, string $reason): void
    {
        $row = $this->db->query(
            "SELECT id, status FROM staff_lifecycle_actions WHERE id = ?",
            [$actionId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Lifecycle action not found');
        if (($row['status'] ?? null) !== 'pending') throw new RuntimeException('Only pending actions can be cancelled');
        $this->db->query(
            "UPDATE staff_lifecycle_actions SET status = 'cancelled', review_comment = ? WHERE id = ?",
            [$reason, $actionId]
        );
        $this->audit($actorUserId, 'staff_lifecycle_action_cancelled', $actionId, ['reason' => $reason]);
    }

    private function assignDepartment(int $staffId, int $departmentId, string $effectiveDate): void
    {
        $this->db->query(
            "UPDATE staff_department_assignments SET effective_to = ? WHERE staff_id = ? AND effective_to IS NULL",
            [$effectiveDate, $staffId]
        );
        $this->db->query(
            "INSERT INTO staff_department_assignments (staff_id, department_id, effective_from)
             VALUES (?, ?, ?)",
            [$staffId, $departmentId, $effectiveDate]
        );
    }

    private function audit(int $userId, string $action, int $entityId, array $details = []): void
    {
        FileLogger::write('staff', [
            'type' => 'lifecycle_audit',
            'action' => $action,
            'entity' => 'staff_lifecycle_action',
            'entity_id' => $entityId,
            'user_id' => $userId,
            'details' => $details,
        ]);
    }
}
