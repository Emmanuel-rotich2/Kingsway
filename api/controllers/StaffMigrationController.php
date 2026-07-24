<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Services\StaffMigrationService;
use RuntimeException;
use Throwable;

final class StaffMigrationController extends BaseController
{
    private StaffMigrationService $service;
    public function __construct(){parent::__construct();$this->service=new StaffMigrationService($this->db);}

    public function getReferenceData($id=null,$data=[],$segments=[]){$this->guard('staff_import');return $this->success($this->service->referenceData());}
    public function getBatches($id=null,$data=[],$segments=[]){$this->guard('staff_import');return $this->success($this->service->batches((int)($_GET['limit']??50)));}
    public function getBatch($id=null,$data=[],$segments=[]){$this->guard('staff_import');$batchId=(int)($id??$_GET['id']??0);if(!$batchId)return $this->badRequest('Batch ID is required.');return $this->success($this->service->batchDetail($batchId));}

    public function getTemplate($id=null,$data=[],$segments=[]): never
    {
        $this->guard('staff_import');
        $headers=$this->service->templateHeaders();
        $sample=['KWPS-001','Jane','Wanjiku','jane.wanjiku@example.com','0712345678','ACA','Class Teacher','2024-01-08','permanent','Class Teacher','female','1993-02-10','single','Teaching','Teacher','A123456789B','NSSF001','NHIF001','TSC001','Nairobi','KCB','1234567890','45000','08:00:00','17:00:00','15','yes','45000','jane.wanjiku@example.com','0712345678'];
        $csv="\xEF\xBB\xBF".implode(',',array_map([$this,'csvCell'],$headers))."\r\n".implode(',',array_map([$this,'csvCell'],$sample))."\r\n";
        $path=$this->managedPath('import_file','templates','existing_staff_migration_template.csv');
        $this->atomicWriteManagedFile($path,$csv);
        $this->streamManagedFile($path,'existing_staff_migration_template.csv','text/csv; charset=utf-8');
    }

    public function postStage($id=null,$data=[],$segments=[])
    {
        $this->guard('staff_import');
        try{
            if(empty($_FILES['file']))throw new RuntimeException('CSV file is required.');
            $stored=$this->uploadManaged($_FILES['file'],'import_file',['subdirectory'=>'staff_migration','allowed_extensions'=>['csv'],'allowed_mime_types'=>['text/csv','text/plain','application/vnd.ms-excel','application/octet-stream']]);
            $csv=$this->readManagedFile($stored['absolute_path']);if($csv===false)throw new RuntimeException('Uploaded CSV could not be read.');
            return $this->created($this->service->stage($_FILES['file']['name'],$stored['absolute_path'],$csv,$this->actorId()),'CSV staged and validated.');
        }catch(Throwable $e){return $this->unprocessable($e->getMessage());}
    }

    public function postCommit($id=null,$data=[],$segments=[])
    {
        $this->guard('staff_import');
        try{$batchId=(int)($data['batch_id']??$id??0);if(!$batchId)throw new RuntimeException('batch_id is required.');return $this->created($this->service->commit($batchId,$this->actorId()),'Existing staff imported atomically and invitations queued.');}
        catch(Throwable $e){return $this->unprocessable($e->getMessage());}
    }

    public function postRollback($id=null,$data=[],$segments=[])
    {
        $this->guard('staff_import_rollback');
        try{$batchId=(int)($data['batch_id']??$id??0);return $this->success($this->service->rollback($batchId,$this->actorId()),'Import batch rolled back.');}
        catch(Throwable $e){return $this->unprocessable($e->getMessage());}
    }

    public function postResendInvitation($id=null,$data=[],$segments=[])
    {
        $this->guard('staff_invitation_resend');
        try{$uid=(int)($data['user_id']??$id??0);if(!$uid)throw new RuntimeException('user_id is required.');$base=$data['base_url']??(defined('APP_URL')?APP_URL:'');return $this->success($this->service->resendInvitation($uid,$this->actorId(),$base),'Invitation queued again.');}
        catch(Throwable $e){return $this->unprocessable($e->getMessage());}
    }

    public function getOnboarding($id=null,$data=[],$segments=[])
    {
        $uid=$this->actorId();return $this->success($this->service->onboardingForUser($uid));
    }

    public function putProfile($id=null,$data=[],$segments=[])
    {
        try{return $this->success($this->service->completeProfile($this->actorId(),$data),'Profile completed.');}
        catch(Throwable $e){return $this->unprocessable($e->getMessage());}
    }

    private function actorId(): int{$id=(int)($this->user['id']??$this->user['user_id']??0);if(!$id)throw new RuntimeException('Authenticated user context is required.');return$id;}
    private function guard(string $permission): void
    {
        if(!$this->user)throw new RuntimeException('Authentication required.');
        $roles=array_map(fn($v)=>strtolower(str_replace(' ','_',is_array($v)?($v['name']??''):(string)$v)),(array)($this->user['roles']??[$this->user['role']??'']));
        $permissions=(array)($this->user['permissions']??[]);
        if(!array_intersect($roles,['school_administrator','school_admin','admin'])&&!in_array($permission,$permissions,true))throw new RuntimeException('School Administrator permission is required.');
    }
    private function csvCell(string $value):string{return '"'.str_replace('"','""',$value).'"';}
}
