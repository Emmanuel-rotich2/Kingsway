<?php
namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

final class SystemAdministrationService
{
    private PDO $db;
    private const REGISTRIES = [
        'feature-flags'=>['system_feature_flags',['key_name','name','description','enabled','environment','rollout_percentage']],
        'modules'=>['system_modules',['module_key','name','description','enabled','dependencies_json']],
        'ip-rules'=>['system_ip_rules',['rule_type','cidr','description','enabled','starts_at','expires_at']],
        'policies'=>['system_access_policies',['policy_key','name','description','domain','effect','enabled','rules_json']],
        'route-rules'=>['system_route_access_rules',['route_key','http_method','domain','permission_key','effect','enabled']],
        'maintenance'=>['system_maintenance_windows',['name','message','starts_at','ends_at','enabled','bypass_roles_json']],
        'retention'=>['system_retention_policies',['resource_key','retention_days','action','enabled','description']],
        'webhooks'=>['system_webhooks',['name','target_url','events_json','enabled','secret_hint']],
        'incidents'=>['system_security_incidents',['title','severity','status','description','assigned_to','resolved_at']],
    ];

    public function __construct(?PDO $db=null){$this->db=$db?:Database::getInstance()->getConnection();}

    public function dashboard(): array
    {
        $started=microtime(true); $this->db->query('SELECT 1')->fetchColumn();
        return [
            'generated_at'=>date('c'),
            'database'=>['status'=>'healthy','latency_ms'=>round((microtime(true)-$started)*1000,2)],
            'active_sessions'=>$this->scalar("SELECT COUNT(*) FROM user_sessions WHERE revoked_at IS NULL AND expires_at>NOW()"),
            'enabled_users'=>$this->scalar("SELECT COUNT(*) FROM users WHERE COALESCE(is_active,1)=1"),
            'failed_logins_24h'=>$this->scalar("SELECT COUNT(*) FROM login_attempts WHERE success=0 AND attempted_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)"),
            'open_incidents'=>$this->scalar("SELECT COUNT(*) FROM system_security_incidents WHERE status NOT IN ('resolved','closed')"),
            'pending_jobs'=>$this->scalar("SELECT COUNT(*) FROM system_background_jobs WHERE status IN ('queued','retrying')"),
            'recent_activity'=>$this->rows("SELECT id,user_id,action,resource_type,resource_id,status,ip_address,created_at FROM audit_logs ORDER BY created_at DESC LIMIT 10")
        ];
    }

    public function accounts(): array
    {
        return $this->rows("SELECT u.id,u.username,u.email,u.is_active,u.locked_until,u.last_login,u.must_change_password,
          GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') roles
          FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id
          GROUP BY u.id ORDER BY u.id DESC LIMIT 500");
    }

    public function changeAccountState(int $id,string $action,?string $until=null): array
    {
        if(!in_array($action,['activate','disable','lock','unlock','force-password-reset'],true)) throw new RuntimeException('Unsupported account action');
        $sql=[
          'activate'=>"UPDATE users SET is_active=1,locked_until=NULL WHERE id=?",
          'disable'=>"UPDATE users SET is_active=0 WHERE id=?",
          'lock'=>"UPDATE users SET locked_until=COALESCE(?,DATE_ADD(NOW(),INTERVAL 1 DAY)) WHERE id=?",
          'unlock'=>"UPDATE users SET locked_until=NULL WHERE id=?",
          'force-password-reset'=>"UPDATE users SET must_change_password=1 WHERE id=?"
        ][$action];
        $params=$action==='lock'?[$until,$id]:[$id]; $this->exec($sql,$params);
        return ['user_id'=>$id,'action'=>$action];
    }

    public function sessions(): array
    {
        return $this->rows("SELECT s.id,s.user_id,u.username,u.email,s.ip_address,s.user_agent,s.created_at,s.last_activity,s.expires_at
          FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.revoked_at IS NULL AND s.expires_at>NOW()
          ORDER BY s.last_activity DESC LIMIT 500");
    }
    public function revokeSession(int $id): void {$this->exec("UPDATE user_sessions SET revoked_at=NOW() WHERE id=?",[$id]);}

    public function listRegistry(string $name): array
    {[$table]=$this->registry($name); return $this->rows("SELECT * FROM `$table` ORDER BY id DESC LIMIT 1000");}

    public function saveRegistry(string $name,array $data,?int $id=null): array
    {
        [$table,$allowed]=$this->registry($name); $fields=[];$values=[];
        foreach($allowed as $field){if(array_key_exists($field,$data)){$fields[]=$field;$values[]=$this->normalize($field,$data[$field]);}}
        if(!$fields) throw new RuntimeException('No supported fields supplied');
        if($id){$sets=implode(',',array_map(fn($f)=>"`$f`=?",$fields));$values[]=$id;$this->exec("UPDATE `$table` SET $sets,updated_at=NOW() WHERE id=?",$values);}
        else{$cols=implode(',',array_map(fn($f)=>"`$f`",$fields));$marks=implode(',',array_fill(0,count($fields),'?'));$this->exec("INSERT INTO `$table` ($cols) VALUES ($marks)",$values);$id=(int)$this->db->lastInsertId();}
        return ['id'=>$id,'registry'=>$name];
    }
    public function deleteRegistry(string $name,int $id): void {[$table]=$this->registry($name);$this->exec("DELETE FROM `$table` WHERE id=?",[$id]);}

    public function startProvisioning(array $data,int $actorId): array
    {
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare("INSERT INTO schools(name,code,status,created_by) VALUES(?,?, 'draft',?)");
            $stmt->execute([trim($data['name']??''),trim($data['code']??''),$actorId]); $schoolId=(int)$this->db->lastInsertId();
            $stmt=$this->db->prepare("INSERT INTO school_provisioning_runs(school_id,status,current_step,created_by) VALUES(?, 'draft',1,?)");
            $stmt->execute([$schoolId,$actorId]); $runId=(int)$this->db->lastInsertId();
            $this->db->commit(); return ['school_id'=>$schoolId,'run_id'=>$runId,'current_step'=>1];
        }catch(\Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function saveProvisioningStep(int $runId,int $step,array $payload,int $actorId): array
    {
        $stmt=$this->db->prepare("INSERT INTO school_provisioning_steps(run_id,step_number,payload_json,completed_by,completed_at)
          VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),completed_by=VALUES(completed_by),completed_at=NOW()");
        $stmt->execute([$runId,$step,json_encode($payload,JSON_THROW_ON_ERROR),$actorId]);
        $this->exec("UPDATE school_provisioning_runs SET current_step=GREATEST(current_step,?),updated_at=NOW() WHERE id=?",[$step+1,$runId]);
        return ['run_id'=>$runId,'completed_step'=>$step,'next_step'=>$step+1];
    }

    public function finalizeProvisioning(int $runId,int $actorId): array
    {
        $this->db->beginTransaction();
        try{
            $run=$this->row("SELECT * FROM school_provisioning_runs WHERE id=? FOR UPDATE",[$runId]); if(!$run) throw new RuntimeException('Provisioning run not found');
            $count=(int)$this->scalar("SELECT COUNT(*) FROM school_provisioning_steps WHERE run_id=?",[$runId]); if($count<8) throw new RuntimeException('All configuration steps must be completed');
            $this->exec("UPDATE school_provisioning_runs SET status='completed',completed_at=NOW(),updated_at=NOW() WHERE id=?",[$runId]);
            $this->exec("UPDATE schools SET status='active',provisioned_at=NOW(),updated_at=NOW() WHERE id=?",[$run['school_id']]);
            $this->db->commit(); return ['run_id'=>$runId,'school_id'=>(int)$run['school_id'],'status'=>'completed'];
        }catch(\Throwable $e){$this->db->rollBack();throw $e;}
    }

    private function registry(string $name): array {if(!isset(self::REGISTRIES[$name])) throw new RuntimeException('Unsupported registry');return self::REGISTRIES[$name];}
    private function normalize(string $field,$value){if(str_ends_with($field,'_json')&&is_array($value))return json_encode($value,JSON_THROW_ON_ERROR);return $value;}
    private function exec(string $sql,array $params=[]): void {$s=$this->db->prepare($sql);$s->execute($params);}
    private function rows(string $sql,array $params=[]): array {try{$s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);}catch(\Throwable $e){return [];}}
    private function row(string $sql,array $params=[]): ?array {$s=$this->db->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;}
    private function scalar(string $sql,array $params=[]){try{$s=$this->db->prepare($sql);$s->execute($params);return $s->fetchColumn()?:0;}catch(\Throwable $e){return 0;}}
}
