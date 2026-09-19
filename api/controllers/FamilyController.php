<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\parent\ParentPortalManager;
use App\Database\Database;

/** Staff-session family workspace for staff members who are also parents. */
final class FamilyController extends BaseController
{
    private ParentPortalManager $parent;

    public function __construct()
    {
        parent::__construct();
        $userId=(int)($this->user['user_id']??$this->user['id']??0);
        $s=Database::getInstance()->getConnection()->prepare('SELECT pr.id FROM users u JOIN parents pr ON pr.person_id=u.person_id WHERE u.id=? AND pr.status="active" LIMIT 1');
        $s->execute([$userId]); $parentId=(int)$s->fetchColumn();
        if(!$parentId) throw new \RuntimeException('This staff account is not linked to an active parent profile.',403);
        $_SERVER['auth_user']['parent_id'] = $parentId;
        $this->parent=$this->contract('App\API\Modules\parent\ParentPortalManager');
    }

    private function call(string $method, array $args=[]): array { return $this->handleApiResponse($this->parent->{$method}(...$args)); }
    public function getDashboard($id=null,$data=[],$segments=[]){return $this->call('getDashboard');}
    public function getCommunity($id=null,$data=[],$segments=[]){return $this->call('getCommunity');}
    public function getStudentFees($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('student_id required');return $this->call('getStudentFees',[(int)$id]);}
    public function getStudentPaymentHistory($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('student_id required');return $this->call('getStudentPaymentHistory',[(int)$id]);}
    public function getStudentStatement($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('student_id required');return $this->call('getStudentStatement',[(int)$id]);}
    public function getFeeBalance($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('student_id required');return $this->call('getFeeBalance',[(int)$id]);}
    public function getStudentAttendance($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('student_id required');return $this->call('getStudentAttendance',[(int)$id]);}
    public function getStudentPerformance($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('student_id required');return $this->call('getStudentPerformance',[(int)$id]);}
    public function getStudentReportCard($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('student_id required');return $this->call('getStudentReportCard',[(int)$id]);}
    public function getMessages($id=null,$data=[],$segments=[]){return $this->call('getMessages',[$id?(int)$id:null]);}
    public function postSendMessage($id=null,$data=[],$segments=[]){return $this->call('postSendMessage',[$data]);}
    public function getPortfolio($id=null,$data=[],$segments=[]){if(!$id)return $this->badRequest('student_id required');return $this->call('getPortfolio',[(int)$id]);}
    public function getGradingScale($id=null,$data=[],$segments=[]){return $this->call('getGradingScale');}
    public function postInitiateMpesaPayment($id=null,$data=[],$segments=[]){return $this->call('postInitiateMpesaPayment',[$data]);}
    public function getMpesaStatus($id=null,$data=[],$segments=[]){return $this->call('getMpesaStatus',[$id]);}
}
