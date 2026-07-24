<?php
namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

final class StaffLifecycleService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function dashboard(array $filters = []): array
    {
        $where = ['1=1']; $params = [];
        if (!empty($filters['status'])) { $where[] = 's.status = :status'; $params[':status'] = $filters['status']; }
        if (!empty($filters['department_id'])) { $where[] = 's.department_id = :department_id'; $params[':department_id'] = (int)$filters['department_id']; }
        $clause = implode(' AND ', $where);
        $staff = $this->db->query(
            "SELECT s.id,s.staff_no,s.first_name,s.last_name,s.position,s.status,s.employment_date,s.contract_type,
                    s.department_id,d.name AS department_name,u.email,u.status AS user_status,
                    COALESCE(op.onboarding_status,'complete') AS onboarding_status
             FROM staff s
             LEFT JOIN departments d ON d.id=s.department_id
             LEFT JOIN users u ON u.id=s.user_id
             LEFT JOIN staff_onboarding_progress op ON op.staff_id=s.id
             WHERE {$clause}
             ORDER BY s.last_name,s.first_name LIMIT 500", $params
        )->fetchAll(PDO::FETCH_ASSOC);

        $summary = $this->db->query(
            "SELECT COUNT(*) total,
                    SUM(status='active') active,
                    SUM(status='suspended') suspended,
                    SUM(status IN ('terminated','retired','resigned','inactive')) exited
             FROM staff"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $pending = $this->db->query("SELECT COUNT(*) FROM staff_lifecycle_actions WHERE status='pending'")->fetchColumn();
        return ['summary' => $summary + ['pending_actions' => (int)$pending], 'staff' => $staff];
    }

    public function timeline(int $staffId): array
    {
        $staff = $this->db->query(
            "SELECT s.*,d.name department_name,u.email,u.status user_status
             FROM staff s LEFT JOIN departments d ON d.id=s.department_id LEFT JOIN users u ON u.id=s.user_id WHERE s.id=?", [$staffId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$staff) throw new RuntimeException('Staff member not found');
        $actions = $this->db->query(
            "SELECT a.*, CONCAT(c.first_name,' ',c.last_name) created_by_name,
                    CONCAT(ap.first_name,' ',ap.last_name) approved_by_name,
                    fd.name from_department_name, td.name to_department_name
             FROM staff_lifecycle_actions a
             LEFT JOIN users c ON c.id=a.created_by
             LEFT JOIN users ap ON ap.id=a.approved_by
             LEFT JOIN departments fd ON fd.id=a.from_department_id
             LEFT JOIN departments td ON td.id=a.to_department_id
             WHERE a.staff_id=? ORDER BY a.effective_date DESC,a.created_at DESC", [$staffId]
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['staff'=>$staff,'timeline'=>$actions];
    }

    public function referenceData(): array
    {
        return [
            'departments'=>$this->db->query("SELECT id,name,code FROM departments WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
            'roles'=>$this->db->query("SELECT id,name,display_name FROM roles WHERE is_active=1 AND scope IN ('school','both') ORDER BY display_name,name")->fetchAll(PDO::FETCH_ASSOC),
            'staff'=>$this->db->query("SELECT id,staff_no,CONCAT(first_name,' ',last_name) name FROM staff WHERE status='active' ORDER BY first_name,last_name")->fetchAll(PDO::FETCH_ASSOC),
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
        $staff = $this->db->query('SELECT * FROM staff WHERE id=?', [(int)$data['staff_id']])->fetch(PDO::FETCH_ASSOC);
        if (!$staff) throw new RuntimeException('Staff member not found');
        if (in_array($data['action_type'], ['promotion','demotion','transfer','acting_appointment'], true) && empty($data['to_position']) && empty($data['to_department_id'])) {
            throw new RuntimeException('A new position or department is required');
        }
        $this->db->query(
            "INSERT INTO staff_lifecycle_actions
            (staff_id,action_type,status,effective_date,reason,from_position,to_position,from_department_id,to_department_id,
             from_salary,to_salary,from_contract_type,to_contract_type,from_supervisor_id,to_supervisor_id,notes,created_by,created_at)
             VALUES (?,?, 'pending',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [(int)$data['staff_id'],$data['action_type'],$data['effective_date'],$data['reason'],$staff['position']??null,$data['to_position']??$staff['position'],
             $staff['department_id']??null,$data['to_department_id']??$staff['department_id'],$staff['salary']??null,$data['to_salary']??$staff['salary'],
             $staff['contract_type']??null,$data['to_contract_type']??$staff['contract_type'],$staff['supervisor_id']??null,$data['to_supervisor_id']??$staff['supervisor_id'],
             $data['notes']??null,$actorUserId]
        );
        $id=(int)$this->db->lastInsertId();
        $this->audit($actorUserId,'staff_lifecycle_action_created',$id,$data);
        return $id;
    }

    public function reviewAction(int $actionId, string $decision, int $actorUserId, ?string $comment=null): void
    {
        if (!in_array($decision,['approve','reject'],true)) throw new RuntimeException('Invalid decision');
        $action=$this->db->query('SELECT * FROM staff_lifecycle_actions WHERE id=? FOR UPDATE',[$actionId])->fetch(PDO::FETCH_ASSOC);
        if (!$action) throw new RuntimeException('Lifecycle action not found');
        if ($action['status']!=='pending') throw new RuntimeException('Only pending actions can be reviewed');
        $this->db->beginTransaction();
        try {
            $status=$decision==='approve'?'approved':'rejected';
            $this->db->query("UPDATE staff_lifecycle_actions SET status=?,approved_by=?,approved_at=NOW(),review_comment=?,updated_at=NOW() WHERE id=?",[$status,$actorUserId,$comment,$actionId]);
            if ($decision==='approve') $this->applyAction($action);
            $this->audit($actorUserId,'staff_lifecycle_action_'.$status,$actionId,['comment'=>$comment]);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollback(); throw $e; }
    }

    private function applyAction(array $a): void
    {
        $sets=[]; $params=[];
        foreach ([
            'position'=>'to_position','department_id'=>'to_department_id','salary'=>'to_salary','contract_type'=>'to_contract_type','supervisor_id'=>'to_supervisor_id'
        ] as $column=>$source) {
            if ($a[$source] !== null && $a[$source] !== '') { $sets[]="{$column}=?"; $params[]=$a[$source]; }
        }
        $statusMap=['suspension'=>'suspended','reinstatement'=>'active','resignation'=>'resigned','retirement'=>'retired','termination'=>'terminated','confirmation'=>'active'];
        if (isset($statusMap[$a['action_type']])) { $sets[]='status=?'; $params[]=$statusMap[$a['action_type']]; }
        $sets[]='updated_at=NOW()'; $params[]=(int)$a['staff_id'];
        $this->db->query('UPDATE staff SET '.implode(',',$sets).' WHERE id=?',$params);
        if (in_array($a['action_type'],['resignation','retirement','termination'],true)) {
            $this->db->query("UPDATE users u JOIN staff s ON s.user_id=u.id SET u.status='inactive',u.updated_at=NOW() WHERE s.id=?",[(int)$a['staff_id']]);
            $this->db->query("UPDATE user_sessions us JOIN staff s ON s.user_id=us.user_id SET us.revoked_at=NOW() WHERE s.id=? AND us.revoked_at IS NULL",[(int)$a['staff_id']]);
        }
        $this->db->query("UPDATE staff_lifecycle_actions SET status='effective',applied_at=NOW(),updated_at=NOW() WHERE id=?",[(int)$a['id']]);
    }

    public function cancelAction(int $actionId, int $actorUserId, string $reason): void
    {
        $row=$this->db->query('SELECT status FROM staff_lifecycle_actions WHERE id=?',[$actionId])->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status']!=='pending') throw new RuntimeException('Only pending actions can be cancelled');
        $this->db->query("UPDATE staff_lifecycle_actions SET status='cancelled',review_comment=?,updated_at=NOW() WHERE id=?",[$reason,$actionId]);
        $this->audit($actorUserId,'staff_lifecycle_action_cancelled',$actionId,['reason'=>$reason]);
    }

    private function audit(int $userId,string $action,int $entityId,array $details=[]): void
    {
        $this->db->query("INSERT INTO audit_logs(user_id,action,entity,entity_id,details,created_at) VALUES (?,?,?,?,?,NOW())",
            [$userId,$action,'staff_lifecycle_action',$entityId,json_encode($details,JSON_UNESCAPED_SLASHES)]);
    }
}
