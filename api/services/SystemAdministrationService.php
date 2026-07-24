<?php
namespace App\API\Services;

use PDO;
use RuntimeException;
use Throwable;

final class SystemAdministrationService
{
    private PDO $db;

    private const REGISTRIES = [
        'roles' => ['table' => 'roles', 'fields' => ['name','description','scope','is_system','is_active']],
        'permissions' => ['table' => 'permissions', 'fields' => ['code','description','entity','action','module']],
        'settings' => ['table' => 'school_settings', 'fields' => ['setting_key','setting_value','label']],
        'routes' => ['table' => 'routes', 'fields' => ['name','url','domain','module','description','controller','action','is_active']],
        'sidebar-menus' => ['table' => 'sidebar_menu_items', 'fields' => ['name','label','icon','url','route_id','parent_id','menu_type','display_order','domain','is_active']],
        'domain-isolation' => ['table' => 'system_domain_isolation_rules', 'fields' => ['domain_key','resource_pattern','isolation_mode','description','enabled']],
        'time-bound-access' => ['table' => 'system_time_bound_access', 'fields' => ['user_id','role_id','permission_id','starts_at','expires_at','reason','enabled']],
        'policies' => ['table' => 'system_access_policies', 'fields' => ['policy_key','name','description','domain','effect','enabled','rules_json']],
        'route-rules' => ['table' => 'system_route_access_rules', 'fields' => ['route_id','http_method','permission_id','effect','enabled']],
        'feature-flags' => ['table' => 'system_feature_flags', 'fields' => ['key_name','name','description','enabled','environment','rollout_percentage']],
        'modules' => ['table' => 'system_modules', 'fields' => ['module_key','name','description','enabled','dependencies_json']],
        'maintenance' => ['table' => 'system_maintenance_windows', 'fields' => ['name','message','starts_at','ends_at','enabled','bypass_roles_json']],
        'rate-limits' => ['table' => 'system_rate_limit_rules', 'fields' => ['rule_key','route_pattern','http_method','requests_limit','window_seconds','enabled']],
        'retention' => ['table' => 'system_retention_policies', 'fields' => ['resource_key','retention_days','action','enabled','description']],
        'violations' => ['table' => 'system_policy_violations', 'fields' => ['policy_id','severity','status','user_id','resource_type','resource_id','description','resolved_by','resolved_at']],
        'incidents' => ['table' => 'system_security_incidents', 'fields' => ['title','severity','status','description','assigned_to','resolved_at']],
        'webhooks' => ['table' => 'system_webhooks', 'fields' => ['name','target_url','events_json','enabled','secret_hash','last_delivery_at']],
        'migrations' => ['table' => 'system_migration_history', 'fields' => ['migration_name','checksum','status','notes','executed_by','executed_at']],
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function dashboard(): array
    {
        $started = microtime(true);
        $this->db->query('SELECT 1')->fetchColumn();

        return [
            'generated_at' => date('c'),
            'database' => [
                'status' => 'healthy',
                'latency_ms' => round((microtime(true) - $started) * 1000, 2),
            ],
            'health' => $this->healthSummary(),
            'enabled_users' => $this->scalar("SELECT COUNT(*) FROM users WHERE status='active'"),
            'active_sessions' => $this->scalar("SELECT COUNT(*) FROM user_sessions WHERE session_status='active' AND logout_time IS NULL"),
            'failed_logins_24h' => $this->scalar("SELECT COUNT(*) FROM login_attempts WHERE status='failed' AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)"),
            'open_incidents' => $this->scalar("SELECT COUNT(*) FROM system_security_incidents WHERE status NOT IN ('resolved','closed')"),
            'pending_jobs' => $this->scalar("SELECT COUNT(*) FROM system_background_jobs WHERE status IN ('queued','retrying')"),
            'api_errors_24h' => $this->scalar("SELECT COUNT(*) FROM system_api_metrics WHERE status_code>=500 AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)"),
            'recent_activity' => $this->rows(
                "SELECT a.id,a.created_at,a.user_id,u.username,a.action,a.entity,a.entity_id,a.status,a.ip_address
                 FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id
                 ORDER BY a.created_at DESC LIMIT 15"
            ),
        ];
    }

    public function resource(string $key, array $filters = []): array
    {
        if ($key === '') throw new RuntimeException('Resource key is required');

        $rows = match ($key) {
            'accounts' => $this->accounts(),
            'role-permission-matrix' => $this->rolePermissionMatrix(),
            'role-navigation' => $this->roleNavigation(),
            'authentication-logs' => $this->authenticationLogs(false),
            'failed-logins' => $this->authenticationLogs(true),
            'sessions' => $this->sessions(),
            'health' => [$this->healthSummary()],
            'error-logs' => $this->rows("SELECT * FROM system_error_logs ORDER BY created_at DESC LIMIT 1000"),
            'jobs', 'job-inspector' => $this->rows("SELECT * FROM system_background_jobs ORDER BY created_at DESC LIMIT 1000"),
            'api-metrics' => $this->apiMetrics(),
            'backups' => $this->rows("SELECT * FROM system_backups ORDER BY created_at DESC LIMIT 500"),
            'audit-logs' => $this->auditLogs(),
            'permission-changes' => $this->rows("SELECT * FROM system_permission_changes ORDER BY created_at DESC LIMIT 1000"),
            'api-explorer' => $this->rows("SELECT id,name,url,domain,module,description,controller,action,is_active FROM routes ORDER BY module,name LIMIT 1000"),
            'diagnostics' => [$this->diagnostics()],
            default => $this->registryRows($key),
        };

        return [
            'rows' => $rows,
            'schema' => $this->schema($key),
            'summary' => [
                'records' => count($rows),
                'active' => $this->countState($rows, ['enabled','is_active'], 1),
                'open' => $this->countState($rows, ['status'], 'open'),
                'generated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function saveResource(string $key, array $record, ?int $id, ?int $actorId): array
    {
        if ($key === 'role-permission-matrix') return $this->saveRolePermission($record, $actorId);
        if ($key === 'role-navigation') return $this->saveRoleNavigation($record);
        if (!isset(self::REGISTRIES[$key])) throw new RuntimeException('This resource is read-only or unsupported');

        $definition = self::REGISTRIES[$key];
        $table = $definition['table'];
        if (!$this->tableExists($table)) throw new RuntimeException("Required table {$table} does not exist. Run the verified migration first.");

        $allowed = array_values(array_filter($definition['fields'], fn($field) => $this->columnExists($table, $field)));
        $fields = [];
        $values = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $record)) continue;
            $fields[] = $field;
            $values[] = $this->normalizeValue($field, $record[$field]);
        }
        if (!$fields) throw new RuntimeException('No supported fields supplied');

        if ($id) {
            $sets = implode(',', array_map(fn($field) => "`{$field}`=?", $fields));
            $values[] = $id;
            $this->execute("UPDATE `{$table}` SET {$sets} WHERE id=?", $values);
        } else {
            $columns = implode(',', array_map(fn($field) => "`{$field}`", $fields));
            $marks = implode(',', array_fill(0, count($fields), '?'));
            $this->execute("INSERT INTO `{$table}` ({$columns}) VALUES ({$marks})", $values);
            $id = (int)$this->db->lastInsertId();
        }

        return ['id' => $id, 'resource' => $key];
    }

    public function deleteResource(string $key, int $id): void
    {
        if ($id <= 0) throw new RuntimeException('Valid record ID is required');
        if ($key === 'role-permission-matrix') {
            $this->execute('DELETE FROM role_permissions WHERE id=?', [$id]);
            return;
        }
        if ($key === 'role-navigation') {
            $this->execute('DELETE FROM role_sidebar_menus WHERE id=?', [$id]);
            return;
        }
        if (!isset(self::REGISTRIES[$key])) throw new RuntimeException('This resource is read-only or unsupported');
        $this->execute('DELETE FROM `' . self::REGISTRIES[$key]['table'] . '` WHERE id=?', [$id]);
    }

    public function runAction(string $resource, string $action, array $payload, ?int $actorId): array
    {
        $id = (int)($payload['id'] ?? 0);
        return match ($action) {
            'activate-account' => $this->accountAction($id, 'active'),
            'disable-account' => $this->accountAction($id, 'inactive'),
            'suspend-account', 'lock-account' => $this->lockAccount($id),
            'unlock-account' => $this->unlockAccount($id),
            'revoke-session' => $this->revokeSession($id),
            'retry-job' => $this->jobAction($id, 'queued'),
            'cancel-job' => $this->jobAction($id, 'cancelled'),
            'create-backup' => $this->createBackup($actorId),
            default => throw new RuntimeException('Unsupported system action'),
        };
    }

    public function writeAudit(?int $userId, string $action, string $entity, ?int $entityId, array $details, string $status, ?string $ipAddress, ?string $userAgent): void
    {
        $this->execute(
            'INSERT INTO audit_logs(action,entity,entity_id,user_id,ip_address,user_agent,details,status,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())',
            [$action, substr($entity,0,50), $entityId, $userId, $ipAddress, $userAgent, json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $status]
        );
    }

    private function accounts(): array
    {
        return $this->rows(
            "SELECT u.id,u.username,u.email,u.first_name,u.last_name,u.status,u.last_login,
                    u.failed_login_attempts,u.account_locked_until,u.force_password_change,
                    r.name AS main_role
             FROM users u LEFT JOIN roles r ON r.id=u.role_id
             ORDER BY u.id DESC LIMIT 1000"
        );
    }

    private function rolePermissionMatrix(): array
    {
        return $this->rows(
            "SELECT rp.id,r.id role_id,r.name role_name,p.id permission_id,p.code permission_code,
                    p.description,p.entity,p.action,p.module
             FROM role_permissions rp JOIN roles r ON r.id=rp.role_id
             JOIN permissions p ON p.id=rp.permission_id ORDER BY r.name,p.code"
        );
    }

    private function roleNavigation(): array
    {
        return $this->rows(
            "SELECT rsm.id,rsm.role_id,r.name role_name,rsm.menu_item_id,sm.name menu_name,
                    sm.label,sm.url,sm.domain,rsm.is_default,rsm.custom_order
             FROM role_sidebar_menus rsm JOIN roles r ON r.id=rsm.role_id
             JOIN sidebar_menu_items sm ON sm.id=rsm.menu_item_id
             ORDER BY r.name,COALESCE(rsm.custom_order,sm.display_order),sm.label"
        );
    }

    private function authenticationLogs(bool $failedOnly): array
    {
        $where = $failedOnly ? "WHERE l.status='failed'" : '';
        return $this->rows(
            "SELECT l.id,l.username,l.user_id,u.email,l.ip_address,l.user_agent,l.status,l.failure_reason,l.created_at
             FROM login_attempts l LEFT JOIN users u ON u.id=l.user_id {$where}
             ORDER BY l.created_at DESC LIMIT 1000"
        );
    }

    private function sessions(): array
    {
        return $this->rows(
            "SELECT s.id,s.user_id,u.username,u.email,s.ip_address,s.user_agent,s.login_time,
                    s.last_activity,s.logout_time,s.session_status,s.created_at
             FROM user_sessions s LEFT JOIN users u ON u.id=s.user_id
             ORDER BY s.last_activity DESC LIMIT 1000"
        );
    }

    private function apiMetrics(): array
    {
        return $this->rows(
            "SELECT endpoint,http_method,status_code,COUNT(*) requests,ROUND(AVG(duration_ms),2) avg_duration_ms,MAX(created_at) last_request_at
             FROM system_api_metrics GROUP BY endpoint,http_method,status_code ORDER BY last_request_at DESC LIMIT 1000"
        );
    }

    private function auditLogs(): array
    {
        return $this->rows(
            "SELECT a.id,a.action,a.entity,a.entity_id,a.user_id,u.username,a.ip_address,a.status,a.details,a.created_at
             FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 1000"
        );
    }

    private function saveRolePermission(array $record, ?int $actorId): array
    {
        $roleId=(int)($record['role_id']??0); $permissionId=(int)($record['permission_id']??0);
        if (!$roleId || !$permissionId) throw new RuntimeException('role_id and permission_id are required');
        $this->execute('INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at) VALUES(?,?,NOW())',[$roleId,$permissionId]);
        $id=(int)$this->db->lastInsertId();
        if ($this->tableExists('system_permission_changes')) {
            $this->execute("INSERT INTO system_permission_changes(actor_user_id,target_type,target_id,permission_id,change_type,created_at) VALUES(?,'role',?,?,'assigned',NOW())",[$actorId,$roleId,$permissionId]);
        }
        return ['id'=>$id,'role_id'=>$roleId,'permission_id'=>$permissionId];
    }

    private function saveRoleNavigation(array $record): array
    {
        $roleId=(int)($record['role_id']??0); $menuId=(int)($record['menu_item_id']??0);
        if (!$roleId || !$menuId) throw new RuntimeException('role_id and menu_item_id are required');
        $this->execute('INSERT INTO role_sidebar_menus(role_id,menu_item_id,is_default,custom_order,created_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),custom_order=VALUES(custom_order)',[$roleId,$menuId,(int)($record['is_default']??1),$record['custom_order']!==''?($record['custom_order']??null):null]);
        return ['id'=>(int)$this->db->lastInsertId(),'role_id'=>$roleId,'menu_item_id'=>$menuId];
    }

    private function accountAction(int $id, string $status): array
    {
        if ($id<=0) throw new RuntimeException('User ID is required');
        $this->execute('UPDATE users SET status=?,account_locked_until=NULL WHERE id=?',[$status,$id]);
        return ['id'=>$id,'status'=>$status];
    }

    private function lockAccount(int $id): array
    {
        if ($id<=0) throw new RuntimeException('User ID is required');
        $this->execute("UPDATE users SET status='suspended',account_locked_until=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id=?",[$id]);
        return ['id'=>$id,'status'=>'suspended'];
    }

    private function unlockAccount(int $id): array
    {
        if ($id<=0) throw new RuntimeException('User ID is required');
        $this->execute("UPDATE users SET status='active',failed_login_attempts=0,account_locked_until=NULL WHERE id=?",[$id]);
        return ['id'=>$id,'status'=>'active'];
    }

    private function revokeSession(int $id): array
    {
        if ($id<=0) throw new RuntimeException('Session ID is required');
        $this->execute("UPDATE user_sessions SET session_status='logged_out',logout_time=NOW() WHERE id=?",[$id]);
        return ['id'=>$id,'session_status'=>'logged_out'];
    }

    private function jobAction(int $id, string $status): array
    {
        if ($id<=0) throw new RuntimeException('Job ID is required');
        $this->execute("UPDATE system_background_jobs SET status=?,next_attempt_at=IF(?='queued',NOW(),next_attempt_at) WHERE id=?",[$status,$status,$id]);
        return ['id'=>$id,'status'=>$status];
    }

    private function createBackup(?int $actorId): array
    {
        $filename='kingsway-'.date('Ymd-His').'.sql';
        $this->execute("INSERT INTO system_backups(filename,status,created_by,created_at,updated_at) VALUES(?,'queued',?,NOW(),NOW())",[$filename,$actorId]);
        return ['id'=>(int)$this->db->lastInsertId(),'filename'=>$filename,'status'=>'queued'];
    }

    private function registryRows(string $key): array
    {
        if (!isset(self::REGISTRIES[$key])) throw new RuntimeException('Unsupported system resource: '.$key);
        $table=self::REGISTRIES[$key]['table'];
        if (!$this->tableExists($table)) throw new RuntimeException("Required table {$table} does not exist. Run the verified migration first.");
        return $this->rows("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 1000");
    }

    private function schema(string $key): array
    {
        $schemas = [
            'roles'=>[['name'=>'id','editable'=>false],['name'=>'name','required'=>true],['name'=>'description','type'=>'textarea'],['name'=>'scope','type'=>'select','options'=>['system','school']],['name'=>'is_system','type'=>'boolean'],['name'=>'is_active','type'=>'boolean']],
            'permissions'=>[['name'=>'id','editable'=>false],['name'=>'code','required'=>true],['name'=>'description','type'=>'textarea'],['name'=>'entity'],['name'=>'action'],['name'=>'module']],
            'settings'=>[['name'=>'id','editable'=>false],['name'=>'setting_key','required'=>true],['name'=>'setting_value','type'=>'textarea'],['name'=>'label']],
            'routes'=>[['name'=>'id','editable'=>false],['name'=>'name','required'=>true],['name'=>'url','required'=>true],['name'=>'domain','type'=>'select','options'=>['SYSTEM','SCHOOL']],['name'=>'module'],['name'=>'description','type'=>'textarea'],['name'=>'controller'],['name'=>'action'],['name'=>'is_active','type'=>'boolean']],
            'sidebar-menus'=>[['name'=>'id','editable'=>false],['name'=>'name','required'=>true],['name'=>'label','required'=>true],['name'=>'icon'],['name'=>'url'],['name'=>'route_id','type'=>'number'],['name'=>'parent_id','type'=>'number'],['name'=>'menu_type','type'=>'select','options'=>['sidebar','topbar','dropdown']],['name'=>'display_order','type'=>'number'],['name'=>'domain','type'=>'select','options'=>['SYSTEM','SCHOOL','SHARED']],['name'=>'is_active','type'=>'boolean']],
            'role-permission-matrix'=>[['name'=>'id','editable'=>false],['name'=>'role_id','type'=>'number','required'=>true],['name'=>'permission_id','type'=>'number','required'=>true]],
            'role-navigation'=>[['name'=>'id','editable'=>false],['name'=>'role_id','type'=>'number','required'=>true],['name'=>'menu_item_id','type'=>'number','required'=>true],['name'=>'is_default','type'=>'boolean'],['name'=>'custom_order','type'=>'number']],
        ];
        if (isset($schemas[$key])) return $schemas[$key];
        if (!isset(self::REGISTRIES[$key])) return [];
        return array_merge([['name'=>'id','editable'=>false]],array_map(fn($f)=>['name'=>$f],self::REGISTRIES[$key]['fields']));
    }

    private function healthSummary(): array
    {
        $path=dirname(__DIR__,2);
        return ['php_status'=>'healthy','php_version'=>PHP_VERSION,'storage_status'=>is_writable($path)?'healthy':'warning','storage_free'=>$this->formatBytes((int)@disk_free_space($path)),'environment'=>defined('APP_ENV')?APP_ENV:'unknown'];
    }

    private function diagnostics(): array
    {
        return ['php_version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'database_driver'=>$this->db->getAttribute(PDO::ATTR_DRIVER_NAME),'database_server_version'=>$this->db->getAttribute(PDO::ATTR_SERVER_VERSION),'memory_limit'=>ini_get('memory_limit'),'upload_max_filesize'=>ini_get('upload_max_filesize'),'post_max_size'=>ini_get('post_max_size'),'timezone'=>date_default_timezone_get(),'server_time'=>date('c')];
    }

    private function tableExists(string $table): bool { $s=$this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'); $s->execute([$table]); return (int)$s->fetchColumn()>0; }
    private function columnExists(string $table,string $column): bool { $s=$this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?'); $s->execute([$table,$column]); return (int)$s->fetchColumn()>0; }
    private function execute(string $sql,array $params=[]): void { $s=$this->db->prepare($sql); $s->execute($params); }
    private function rows(string $sql,array $params=[]): array { try{$s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){error_log('[SystemAdministrationService] '.$e->getMessage());return [];} }
    private function scalar(string $sql,array $params=[]): int|float|string { try{$s=$this->db->prepare($sql);$s->execute($params);$v=$s->fetchColumn();return $v===false?0:$v;}catch(Throwable $e){return 0;} }
    private function normalizeValue(string $field,mixed $value): mixed { if(str_ends_with($field,'_json')&&is_array($value)) return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); return $value===''?null:$value; }
    private function countState(array $rows,array $fields,mixed $expected): int { $n=0;foreach($rows as $row){foreach($fields as $f){if(array_key_exists($f,$row)&&(string)$row[$f]===(string)$expected){$n++;break;}}}return $n; }
    private function formatBytes(int $bytes): string { if($bytes<=0)return '0 B';$u=['B','KB','MB','GB','TB'];$p=min((int)floor(log($bytes,1024)),count($u)-1);return round($bytes/(1024**$p),2).' '.$u[$p]; }
}
