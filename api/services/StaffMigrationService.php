<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;
use Throwable;

/** Existing-staff migration only. Recruitment belongs to Phase 3. */
final class StaffMigrationService
{
    private const REQUIRED = [
        'staff_no','first_name','last_name','email','phone','department_code',
        'position','employment_date','contract_type','role_name'
    ];
    private const OPTIONAL = [
        'gender','date_of_birth','marital_status','staff_type','staff_category',
        'kra_pin','nssf_no','nhif_no','tsc_no','address','bank_name','bank_account',
        'salary','work_start_time','work_end_time','late_threshold_minutes',
        'create_payroll_profile','basic_salary','communication_email','communication_phone'
    ];

    public function __construct(private PDO $db) {}

    public function templateHeaders(): array { return array_merge(self::REQUIRED, self::OPTIONAL); }

    public function referenceData(): array
    {
        return [
            'departments' => $this->rows("SELECT id,code,name FROM departments WHERE status='active' ORDER BY name"),
            'roles' => $this->rows("SELECT id,name,scope FROM roles WHERE is_active=1 AND scope='school' ORDER BY name"),
            'staff_types' => $this->rows("SELECT id,name FROM staff_types WHERE is_active=1 ORDER BY name"),
            'staff_categories' => $this->rows("SELECT sc.id,sc.category_name AS name,st.name AS staff_type FROM staff_categories sc JOIN staff_types st ON st.id=sc.staff_type_id WHERE sc.is_active=1 ORDER BY st.name,sc.category_name"),
            'contracts' => ['permanent','contract','temporary'],
            'genders' => ['male','female','other'],
            'marital_statuses' => ['single','married','divorced','widowed'],
        ];
    }

    public function stage(string $originalName, string $storedPath, string $csv, int $actorId): array
    {
        [$headers, $rows] = $this->parseCsv($csv);
        $missing = array_values(array_diff(self::REQUIRED, $headers));
        $unknown = array_values(array_diff($headers, $this->templateHeaders()));
        $duplicateInFile = $this->duplicatesInFile($rows);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO staff_import_batches
                (source_filename,stored_path,total_rows,valid_rows,invalid_rows,status,imported_by,created_at,updated_at)
                VALUES (?,?,?,0,?,'validating',?,NOW(),NOW())");
            $stmt->execute([$originalName,$storedPath,count($rows),count($rows),$actorId]);
            $batchId = (int)$this->db->lastInsertId();

            $valid = 0;
            foreach ($rows as $i => $row) {
                $errors = $this->validateRow($row, $i + 2, $duplicateInFile);
                if (!$errors && !$missing && !$unknown) $valid++;
                $stmt = $this->db->prepare("INSERT INTO staff_import_rows
                    (batch_id,row_number,row_data,validation_errors,status,created_at,updated_at)
                    VALUES (?,?,?,?,?,NOW(),NOW())");
                $stmt->execute([
                    $batchId,$i+2,json_encode($row,JSON_UNESCAPED_UNICODE),
                    $errors ? json_encode($errors,JSON_UNESCAPED_UNICODE) : null,
                    (!$errors && !$missing && !$unknown) ? 'valid' : 'invalid'
                ]);
            }
            $invalid = count($rows)-$valid;
            $status = (!$missing && !$unknown && count($rows)>0 && $invalid===0) ? 'validated' : 'validation_failed';
            $this->db->prepare("UPDATE staff_import_batches SET valid_rows=?,invalid_rows=?,status=?,validation_summary=?,updated_at=NOW() WHERE id=?")
                ->execute([$valid,$invalid,$status,json_encode(['missing_columns'=>$missing,'unknown_columns'=>$unknown]),$batchId]);
            $this->audit($actorId,'staff_import_validated','staff_import_batch',$batchId,['valid'=>$valid,'invalid'=>$invalid,'missing'=>$missing,'unknown'=>$unknown]);
            $this->db->commit();
            return $this->batchDetail($batchId);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function commit(int $batchId, int $actorId): array
    {
        $batch = $this->lockBatch($batchId);
        if (!$batch) throw new RuntimeException('Import batch not found.');
        if ($batch['status'] !== 'validated') throw new RuntimeException('Only a fully validated batch can be imported.');
        $rows = $this->batchRows($batchId, 'valid');
        if (!$rows || count($rows)!==(int)$batch['total_rows']) throw new RuntimeException('Validated row count no longer matches the batch. Revalidate the file.');

        $created = [];
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE staff_import_batches SET status='processing',started_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$batchId]);
            foreach ($rows as $rowRecord) {
                $row = json_decode($rowRecord['row_data'], true, 512, JSON_THROW_ON_ERROR);
                $created[] = $this->createStaffGraph($row, $batchId, (int)$rowRecord['id'], $actorId);
            }
            $this->db->prepare("UPDATE staff_import_batches SET status='completed',imported_rows=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")
                ->execute([count($created),$batchId]);
            $this->audit($actorId,'staff_import_completed','staff_import_batch',$batchId,['created_count'=>count($created)]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->db->prepare("UPDATE staff_import_batches SET status='failed',failure_message=?,updated_at=NOW() WHERE id=?")
                ->execute([mb_substr($e->getMessage(),0,1000),$batchId]);
            $this->audit($actorId,'staff_import_failed','staff_import_batch',$batchId,['error'=>$e->getMessage()],'failure');
            throw $e;
        }
        return ['batch'=>$this->batchDetail($batchId),'created'=>$created];
    }

    public function rollback(int $batchId, int $actorId): array
    {
        $batch = $this->lockBatch($batchId);
        if (!$batch || $batch['status']!=='completed') throw new RuntimeException('Only a completed batch can be rolled back.');
        $rows = $this->batchRows($batchId, 'created');
        foreach ($rows as $r) {
            if ($this->hasOperationalDependencies((int)$r['staff_id'])) throw new RuntimeException('Rollback is blocked because one or more imported staff members already have operational records.');
        }
        $this->db->beginTransaction();
        try {
            foreach (array_reverse($rows) as $r) {
                $uid=(int)$r['user_id']; $sid=(int)$r['staff_id'];
                foreach (['staff_onboarding_progress','staff_communication_profiles','staff_attendance_profiles','staff_payroll_profiles','staff_employment_profiles'] as $table) {
                    $this->db->prepare("DELETE FROM `$table` WHERE staff_id=?")->execute([$sid]);
                }
                $this->db->prepare("DELETE FROM user_invitations WHERE user_id=?")->execute([$uid]);
                $this->db->prepare("DELETE FROM user_roles WHERE user_id=?")->execute([$uid]);
                $this->db->prepare("DELETE FROM staff WHERE id=?")->execute([$sid]);
                $this->db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
                $this->db->prepare("UPDATE staff_import_rows SET status='rolled_back',updated_at=NOW() WHERE id=?")->execute([(int)$r['id']]);
            }
            $this->db->prepare("UPDATE staff_import_batches SET status='rolled_back',rolled_back_at=NOW(),rolled_back_by=?,updated_at=NOW() WHERE id=?")
                ->execute([$actorId,$batchId]);
            $this->audit($actorId,'staff_import_rolled_back','staff_import_batch',$batchId,['records'=>count($rows)]);
            $this->db->commit();
            return $this->batchDetail($batchId);
        } catch(Throwable $e){ if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }

    public function resendInvitation(int $userId, int $actorId, string $baseUrl): array
    {
        $stmt=$this->db->prepare("SELECT u.id,u.email,u.username,u.first_name,u.last_name,s.id staff_id FROM users u JOIN staff s ON s.user_id=u.id WHERE u.id=? LIMIT 1");
        $stmt->execute([$userId]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user) throw new RuntimeException('Imported staff user not found.');
        $token=$this->createInvitation((int)$user['id'],(int)$user['staff_id'],$user['email'],$actorId);
        $url=rtrim($baseUrl,'/').'/home.php?route=reset_default_password&token='.rawurlencode($token);
        $this->queueEmail((int)$user['id'],$user['email'],'staff_account_invitation','Your Kingsway account is ready',[
            'name'=>trim($user['first_name'].' '.$user['last_name']),'username'=>$user['username'],'activation_url'=>$url
        ]);
        $this->audit($actorId,'staff_invitation_resent','user',(int)$user['id']);
        return ['user_id'=>(int)$user['id'],'queued'=>true];
    }

    public function processEmailQueue(int $limit=20): array
    {
        $stmt=$this->db->prepare("SELECT * FROM outbound_messages WHERE channel='email' AND status IN ('queued','retry') AND next_attempt_at<=NOW() ORDER BY id LIMIT ?");
        $stmt->bindValue(1,max(1,min(100,$limit)),PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // delivery performed by canonical worker/MessageService endpoint
    }

    public function batches(int $limit=50): array
    {
        $stmt=$this->db->prepare("SELECT b.*,CONCAT(u.first_name,' ',u.last_name) imported_by_name FROM staff_import_batches b LEFT JOIN users u ON u.id=b.imported_by ORDER BY b.id DESC LIMIT ?");
        $stmt->bindValue(1,max(1,min(200,$limit)),PDO::PARAM_INT);$stmt->execute();return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function batchDetail(int $batchId): array
    {
        $stmt=$this->db->prepare("SELECT b.*,CONCAT(u.first_name,' ',u.last_name) imported_by_name FROM staff_import_batches b LEFT JOIN users u ON u.id=b.imported_by WHERE b.id=?");
        $stmt->execute([$batchId]);$batch=$stmt->fetch(PDO::FETCH_ASSOC);if(!$batch)throw new RuntimeException('Import batch not found.');
        $stmt=$this->db->prepare("SELECT id,row_number,row_data,validation_errors,status,staff_id,user_id FROM staff_import_rows WHERE batch_id=? ORDER BY row_number");
        $stmt->execute([$batchId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$r){$r['data']=json_decode($r['row_data'],true);$r['errors']=$r['validation_errors']?json_decode($r['validation_errors'],true):[];unset($r['row_data'],$r['validation_errors']);}
        $summary=$batch['validation_summary']?json_decode($batch['validation_summary'],true):[];
        return ['batch'=>$batch,'rows'=>$rows,'summary'=>$summary,'can_commit'=>$batch['status']==='validated','can_rollback'=>$batch['status']==='completed'];
    }

    public function onboardingForUser(int $userId): array
    {
        $stmt=$this->db->prepare("SELECT s.id staff_id,s.staff_no,s.first_name,s.last_name,s.profile_pic_url,s.phone,s.address,
            sop.password_completed,sop.profile_completed,sop.communication_completed,sop.onboarding_status,
            sep.position,sep.department_id,sep.employment_date,sep.contract_type
            FROM staff s JOIN staff_onboarding_progress sop ON sop.staff_id=s.id LEFT JOIN staff_employment_profiles sep ON sep.staff_id=s.id WHERE s.user_id=?");
        $stmt->execute([$userId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Staff onboarding profile not found.');return $row;
    }

    public function completeProfile(int $userId,array $data): array
    {
        $stmt=$this->db->prepare("SELECT id FROM staff WHERE user_id=?");$stmt->execute([$userId]);$sid=(int)$stmt->fetchColumn();if(!$sid)throw new RuntimeException('Staff profile not found.');
        foreach(['phone','address','gender','marital_status','date_of_birth'] as $f){if(empty($data[$f]))throw new RuntimeException("$f is required.");}
        $this->db->beginTransaction();try{
            $this->db->prepare("UPDATE staff SET phone=?,address=?,gender=?,marital_status=?,date_of_birth=?,updated_at=NOW() WHERE id=?")
                ->execute([$data['phone'],$data['address'],$data['gender'],$data['marital_status'],$data['date_of_birth'],$sid]);
            $this->db->prepare("UPDATE staff_communication_profiles SET primary_email=?,primary_phone=?,emergency_contact_name=?,emergency_contact_phone=?,updated_at=NOW() WHERE staff_id=?")
                ->execute([$data['communication_email']??null,$data['phone'],$data['emergency_contact_name']??null,$data['emergency_contact_phone']??null,$sid]);
            $this->db->prepare("UPDATE staff_onboarding_progress SET profile_completed=1,communication_completed=1,onboarding_status='completed',completed_at=NOW(),updated_at=NOW() WHERE staff_id=?")
                ->execute([$sid]);
            $this->audit($userId,'staff_profile_completed','staff',$sid);$this->db->commit();return $this->onboardingForUser($userId);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }

    private function createStaffGraph(array $r,int $batchId,int $rowId,int $actorId): array
    {
        $dept=$this->lookupId('departments','code',$r['department_code'],"status='active'");
        $role=$this->lookupId('roles','name',$r['role_name'],"is_active=1 AND scope='school'");
        $type=$this->nullableLookup('staff_types','name',$r['staff_type']??null,"is_active=1");
        $cat=$this->nullableLookup('staff_categories','category_name',$r['staff_category']??null,"is_active=1");
        $username=$this->uniqueUsername($r['email'],$r['first_name'],$r['last_name']);
        $temporary=bin2hex(random_bytes(24)); // unknown to users; invitation establishes real password
        $this->db->prepare("INSERT INTO users(username,email,first_name,last_name,password,role_id,status,force_password_change,created_at,updated_at) VALUES(?,?,?,?,?,?,'pending',1,NOW(),NOW())")
            ->execute([$username,strtolower($r['email']),$r['first_name'],$r['last_name'],password_hash($temporary,PASSWORD_DEFAULT),$role]);
        $uid=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO user_roles(user_id,role_id,created_at) VALUES(?,?,NOW())")->execute([$uid,$role]);
        $this->db->prepare("INSERT INTO staff(staff_type_id,staff_category_id,staff_no,first_name,last_name,phone,department_id,user_id,position,employment_date,contract_type,nssf_no,kra_pin,nhif_no,bank_name,bank_account,salary,gender,marital_status,tsc_no,address,status,date_of_birth,work_start_time,work_end_time,late_threshold_minutes,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',?,?,?,?,?,NOW(),NOW())")
            ->execute([$type,$cat,$r['staff_no'],$r['first_name'],$r['last_name'],$r['phone'],$dept,$uid,$r['position'],$r['employment_date'],strtolower($r['contract_type']),$this->null($r,'nssf_no'),$this->null($r,'kra_pin'),$this->null($r,'nhif_no'),$this->null($r,'bank_name'),$this->null($r,'bank_account'),$this->decimal($r,'salary'),$this->null($r,'gender'),$this->null($r,'marital_status'),$this->null($r,'tsc_no'),$this->null($r,'address'),$this->null($r,'date_of_birth'),$r['work_start_time']?:'08:00:00',$r['work_end_time']?:'17:00:00',(int)($r['late_threshold_minutes']?:15)]);
        $sid=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO staff_employment_profiles(staff_id,department_id,position,employment_date,contract_type,status,created_at,updated_at) VALUES(?,?,?,?,?,'active',NOW(),NOW())")
            ->execute([$sid,$dept,$r['position'],$r['employment_date'],strtolower($r['contract_type'])]);
        $this->db->prepare("INSERT INTO staff_attendance_profiles(staff_id,work_start_time,work_end_time,late_threshold_minutes,is_active,created_at,updated_at) VALUES(?,?,?,?,1,NOW(),NOW())")
            ->execute([$sid,$r['work_start_time']?:'08:00:00',$r['work_end_time']?:'17:00:00',(int)($r['late_threshold_minutes']?:15)]);
        $this->db->prepare("INSERT INTO staff_communication_profiles(staff_id,primary_email,primary_phone,created_at,updated_at) VALUES(?,?,?,?,NOW())")
            ->execute([$sid,$r['communication_email']?:$r['email'],$r['communication_phone']?:$r['phone'],date('Y-m-d H:i:s')]);
        if($this->yes($r['create_payroll_profile']??'no')){
            $this->db->prepare("INSERT INTO staff_payroll_profiles(staff_id,basic_salary,bank_name,bank_account,kra_pin,nssf_no,nhif_no,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,'draft',NOW(),NOW())")
                ->execute([$sid,$this->decimal($r,'basic_salary')??$this->decimal($r,'salary')??0,$this->null($r,'bank_name'),$this->null($r,'bank_account'),$this->null($r,'kra_pin'),$this->null($r,'nssf_no'),$this->null($r,'nhif_no')]);
        }
        $this->db->prepare("INSERT INTO staff_onboarding_progress(staff_id,user_id,password_completed,profile_completed,communication_completed,onboarding_status,created_at,updated_at) VALUES(?,?,0,0,0,'invited',NOW(),NOW())")->execute([$sid,$uid]);
        $token=$this->createInvitation($uid,$sid,$r['email'],$actorId);
        $baseUrl=(defined('APP_URL')?APP_URL:'');$url=rtrim($baseUrl,'/').'/home.php?route=reset_default_password&token='.rawurlencode($token);
        $this->queueEmail($uid,$r['email'],'staff_account_invitation','Your Kingsway account is ready',['name'=>$r['first_name'].' '.$r['last_name'],'username'=>$username,'activation_url'=>$url]);
        $this->db->prepare("UPDATE staff_import_rows SET staff_id=?,user_id=?,status='created',updated_at=NOW() WHERE id=?")->execute([$sid,$uid,$rowId]);
        return ['staff_id'=>$sid,'user_id'=>$uid,'staff_no'=>$r['staff_no'],'username'=>$username,'email'=>$r['email'],'invitation_queued'=>true];
    }

    private function createInvitation(int $uid,int $sid,string $email,int $actor): string
    {
        $this->db->prepare("UPDATE user_invitations SET status='revoked',revoked_at=NOW() WHERE user_id=? AND status='pending'")->execute([$uid]);
        $token=bin2hex(random_bytes(32));
        $this->db->prepare("INSERT INTO user_invitations(user_id,staff_id,email,token_hash,status,expires_at,created_by,created_at,updated_at) VALUES(?,?,?,?,'pending',DATE_ADD(NOW(),INTERVAL 72 HOUR),?,NOW(),NOW())")
            ->execute([$uid,$sid,strtolower($email),hash('sha256',$token),$actor]);return $token;
    }
    private function queueEmail(int $uid,string $to,string $template,string $subject,array $payload): void
    {
        $this->db->prepare("INSERT INTO outbound_messages(user_id,channel,recipient,template_key,subject,payload_json,status,attempts,next_attempt_at,created_at,updated_at) VALUES(?,'email',?,?,?,?, 'queued',0,NOW(),NOW(),NOW())")
            ->execute([$uid,strtolower($to),$template,$subject,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    }
    private function validateRow(array $r,int $row,array $dupes): array
    {
        $e=[];foreach(self::REQUIRED as $f)if(trim((string)($r[$f]??''))==='')$e[]="$f is required";
        if(($r['email']??'')&&!filter_var($r['email'],FILTER_VALIDATE_EMAIL))$e[]='email is invalid';
        if(($r['employment_date']??'')&&!$this->validDate($r['employment_date']))$e[]='employment_date must be YYYY-MM-DD';
        if(($r['date_of_birth']??'')&&!$this->validDate($r['date_of_birth']))$e[]='date_of_birth must be YYYY-MM-DD';
        if(!in_array(strtolower((string)($r['contract_type']??'')),['permanent','contract','temporary'],true))$e[]='contract_type is invalid';
        if(($r['gender']??'')&&!in_array(strtolower($r['gender']),['male','female','other'],true))$e[]='gender is invalid';
        if(($r['staff_no']??'')&&$this->exists('staff','staff_no',$r['staff_no']))$e[]='staff_no already exists';
        if(($r['email']??'')&&$this->exists('users','email',$r['email']))$e[]='email already belongs to a user';
        if(in_array(strtolower($r['staff_no']??''),$dupes['staff_no'],true))$e[]='staff_no is duplicated in this file';
        if(in_array(strtolower($r['email']??''),$dupes['email'],true))$e[]='email is duplicated in this file';
        if(($r['department_code']??'')&&!$this->lookupExists('departments','code',$r['department_code'],"status='active'"))$e[]='department_code was not found or inactive';
        if(($r['role_name']??'')&&!$this->lookupExists('roles','name',$r['role_name'],"is_active=1 AND scope='school'"))$e[]='role_name was not found, inactive, or not a school role';
        if(($r['staff_type']??'')&&!$this->lookupExists('staff_types','name',$r['staff_type'],"is_active=1"))$e[]='staff_type was not found';
        if(($r['staff_category']??'')&&!$this->lookupExists('staff_categories','category_name',$r['staff_category'],"is_active=1"))$e[]='staff_category was not found';
        if(($r['salary']??'')!==''&&!is_numeric($r['salary']))$e[]='salary must be numeric';
        return $e;
    }
    private function parseCsv(string $csv): array
    {
        $csv=preg_replace('/^\xEF\xBB\xBF/','',$csv);$lines=preg_split('/\R/',$csv);$lines=array_values(array_filter($lines,fn($l)=>trim($l)!==''));if(!$lines)throw new RuntimeException('CSV is empty.');
        $headers=array_map(fn($v)=>strtolower(trim($v)),str_getcsv(array_shift($lines)));$rows=[];
        foreach($lines as $line){$v=str_getcsv($line);$v=array_pad($v,count($headers),'');$rows[]=array_combine($headers,array_slice($v,0,count($headers)));}
        return [$headers,$rows];
    }
    private function duplicatesInFile(array $rows): array{ $out=['staff_no'=>[],'email'=>[]];foreach(array_keys($out)as$f){$vals=array_map(fn($r)=>strtolower(trim($r[$f]??'')),$rows);$counts=array_count_values(array_filter($vals));$out[$f]=array_keys(array_filter($counts,fn($c)=>$c>1));}return$out;}
    private function batchRows(int $id,string $status): array{$s=$this->db->prepare("SELECT * FROM staff_import_rows WHERE batch_id=? AND status=? ORDER BY row_number");$s->execute([$id,$status]);return$s->fetchAll(PDO::FETCH_ASSOC);}
    private function lockBatch(int $id): array|false{$s=$this->db->prepare("SELECT * FROM staff_import_batches WHERE id=? FOR UPDATE");$s->execute([$id]);return$s->fetch(PDO::FETCH_ASSOC);}
    private function hasOperationalDependencies(int $sid): bool{foreach(['staff_attendance','staff_payroll','payslips','staff_leaves']as$t){try{$s=$this->db->prepare("SELECT 1 FROM `$t` WHERE staff_id=? LIMIT 1");$s->execute([$sid]);if($s->fetchColumn())return true;}catch(Throwable){}}return false;}
    private function audit(int $uid,string $action,string $entity,int $eid,array $details=[],string $status='success'):void{$this->db->prepare("INSERT INTO audit_logs(action,entity,entity_id,user_id,ip_address,user_agent,details,status,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())")->execute([$action,$entity,$eid,$uid,$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255),json_encode($details),$status]);}
    private function rows(string $sql):array{return$this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);}
    private function exists(string $t,string $c,string $v):bool{$s=$this->db->prepare("SELECT 1 FROM `$t` WHERE LOWER(`$c`)=LOWER(?) LIMIT 1");$s->execute([trim($v)]);return(bool)$s->fetchColumn();}
    private function lookupExists(string$t,string$c,string$v,string$w='1=1'):bool{$s=$this->db->prepare("SELECT 1 FROM `$t` WHERE LOWER(`$c`)=LOWER(?) AND $w LIMIT 1");$s->execute([trim($v)]);return(bool)$s->fetchColumn();}
    private function lookupId(string$t,string$c,string$v,string$w='1=1'):int{$s=$this->db->prepare("SELECT id FROM `$t` WHERE LOWER(`$c`)=LOWER(?) AND $w LIMIT 1");$s->execute([trim($v)]);$id=$s->fetchColumn();if(!$id)throw new RuntimeException("$t value '$v' was not found");return(int)$id;}
    private function nullableLookup(string$t,string$c,?string$v,string$w='1=1'):?int{return trim((string)$v)===''?null:$this->lookupId($t,$c,$v,$w);}
    private function validDate(string$v):bool{$d=\DateTime::createFromFormat('Y-m-d',$v);return$d&&$d->format('Y-m-d')===$v;}
    private function uniqueUsername(string$email,string$f,string$l):string{$base=preg_replace('/[^a-z0-9]/','',strtolower(strtok($email,'@')?:$f.'.'.$l))?:'staff';$u=$base;$i=1;while($this->exists('users','username',$u))$u=$base.$i++;return$u;}
    private function null(array$r,string$k):mixed{$v=trim((string)($r[$k]??''));return$v===''?null:$v;}
    private function decimal(array$r,string$k):?float{$v=trim((string)($r[$k]??''));return$v===''?null:(float)$v;}
    private function yes(string$v):bool{return in_array(strtolower(trim($v)),['1','yes','true','y'],true);}
}
