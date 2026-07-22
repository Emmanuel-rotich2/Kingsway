<?php
namespace App\API\Controllers;

use App\API\Services\SystemAdministrationService;
use Throwable;

final class SystemAdministrationController extends BaseController
{
    private SystemAdministrationService $service;
    public function __construct(){parent::__construct();$this->service=new SystemAdministrationService();}

    private function guard(string $permission='system.manage')
    {
        if(!$this->user) return $this->unauthorized('Authentication required');
        if(!$this->userHasRole('System Administrator') && !$this->userHasPermission('*') && !$this->userHasPermission($permission)) return $this->forbidden('System Domain permission required');
        return null;
    }
    private function actorId(): int {return (int)($this->user['id']??$this->user['user_id']??0);}
    private function audit(string $action,string $resource,?int $resourceId=null,array $details=[]): void
    {
        try{$this->db->query("INSERT INTO audit_logs(user_id,action,resource_type,resource_id,details,status,ip_address,created_at) VALUES(?,?,?,?,?,'success',?,NOW())",[$this->actorId(),$action,$resource,$resourceId,json_encode($details),$_SERVER['REMOTE_ADDR']??null]);}catch(Throwable $e){}
    }

    public function getDashboard($id=null,$data=[],$segments=[]){if($g=$this->guard('system.dashboard.view'))return $g;return $this->success($this->service->dashboard());}
    public function getAccounts($id=null,$data=[],$segments=[]){if($g=$this->guard('system.users.view'))return $g;return $this->success($this->service->accounts());}
    public function postAccountAction($id=null,$data=[],$segments=[]){if($g=$this->guard('system.users.manage'))return $g;try{$r=$this->service->changeAccountState((int)($data['user_id']??0),(string)($data['action']??''),$data['until']??null);$this->audit('account_state_changed','user',(int)$data['user_id'],$r);return $this->success($r,'Account updated');}catch(Throwable $e){return $this->unprocessable($e->getMessage());}}
    public function getSessions($id=null,$data=[],$segments=[]){if($g=$this->guard('system.sessions.view'))return $g;return $this->success($this->service->sessions());}
    public function postRevokeSession($id=null,$data=[],$segments=[]){if($g=$this->guard('system.sessions.revoke'))return $g;$sid=(int)($data['session_id']??0);$this->service->revokeSession($sid);$this->audit('session_revoked','user_session',$sid);return $this->success(null,'Session revoked');}
    public function getRegistry($id=null,$data=[],$segments=[]){if($g=$this->guard('system.configuration.view'))return $g;try{return $this->success($this->service->listRegistry((string)($_GET['name']??'')));}catch(Throwable $e){return $this->badRequest($e->getMessage());}}
    public function postRegistry($id=null,$data=[],$segments=[]){if($g=$this->guard('system.configuration.manage'))return $g;try{$name=(string)($data['registry']??'');$r=$this->service->saveRegistry($name,$data['record']??[],isset($data['id'])?(int)$data['id']:null);$this->audit('registry_saved',$name,$r['id'],$data['record']??[]);return $this->success($r,'Configuration saved');}catch(Throwable $e){return $this->unprocessable($e->getMessage());}}
    public function deleteRegistry($id=null,$data=[],$segments=[]){if($g=$this->guard('system.configuration.manage'))return $g;try{$name=(string)($data['registry']??$_GET['registry']??'');$rid=(int)($data['id']??$id??0);$this->service->deleteRegistry($name,$rid);$this->audit('registry_deleted',$name,$rid);return $this->success(null,'Configuration deleted');}catch(Throwable $e){return $this->unprocessable($e->getMessage());}}
    public function postProvisioningStart($id=null,$data=[],$segments=[]){if($g=$this->guard('system.schools.provision'))return $g;try{$r=$this->service->startProvisioning($data,$this->actorId());$this->audit('school_provisioning_started','school',$r['school_id'],$r);return $this->created($r,'Provisioning started');}catch(Throwable $e){return $this->unprocessable($e->getMessage());}}
    public function postProvisioningStep($id=null,$data=[],$segments=[]){if($g=$this->guard('system.schools.provision'))return $g;try{$r=$this->service->saveProvisioningStep((int)$data['run_id'],(int)$data['step'],$data['payload']??[],$this->actorId());$this->audit('school_provisioning_step','school_provisioning_run',(int)$data['run_id'],$r);return $this->success($r,'Step saved');}catch(Throwable $e){return $this->unprocessable($e->getMessage());}}
    public function postProvisioningFinalize($id=null,$data=[],$segments=[]){if($g=$this->guard('system.schools.provision'))return $g;try{$r=$this->service->finalizeProvisioning((int)$data['run_id'],$this->actorId());$this->audit('school_provisioning_completed','school',$r['school_id'],$r);return $this->success($r,'School initialized');}catch(Throwable $e){return $this->unprocessable($e->getMessage());}}
}
