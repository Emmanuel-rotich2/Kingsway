<?php
namespace App\API\Controllers;

use App\API\Services\StaffLifecycleService;
use Throwable;

final class StaffLifecycleController extends BaseController
{
    private StaffLifecycleService $service;
    public function __construct(){ parent::__construct(); $this->service=new StaffLifecycleService(); }

    public function get($id=null,$data=[],$segments=[]){ return $this->guard(fn()=> $id ? $this->service->timeline((int)$id) : $this->service->dashboard($_GET)); }
    public function getReferenceData($id=null,$data=[],$segments=[]){ return $this->guard(fn()=> $this->service->referenceData()); }
    public function postAction($id=null,$data=[],$segments=[]){ return $this->guard(fn()=> ['id'=>$this->service->createAction($data,$this->actor())],201); }
    public function putApprove($id=null,$data=[],$segments=[]){ return $this->guard(function()use($id,$data){$this->service->reviewAction((int)($id?:($data['id']??0)),'approve',$this->actor(),$data['comment']??null);return ['approved'=>true];}); }
    public function putReject($id=null,$data=[],$segments=[]){ return $this->guard(function()use($id,$data){$this->service->reviewAction((int)($id?:($data['id']??0)),'reject',$this->actor(),$data['comment']??null);return ['rejected'=>true];}); }
    public function putCancel($id=null,$data=[],$segments=[]){ return $this->guard(function()use($id,$data){$this->service->cancelAction((int)($id?:($data['id']??0)),$this->actor(),(string)($data['reason']??'Cancelled'));return ['cancelled'=>true];}); }

    private function actor(): int { $u=$this->getCurrentUser(); $id=(int)($u['id']??0); if(!$id) throw new \RuntimeException('Authentication required'); return $id; }
    private function guard(callable $fn,int $code=200){ try{$result=$fn();return $code===201?$this->created($result):$this->success($result);}catch(Throwable $e){return $this->badRequest($e->getMessage());} }
}
