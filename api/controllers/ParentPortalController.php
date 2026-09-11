<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\parent\ParentPortalManager;
use App\Database\Database;
use App\API\Services\payments\UniformCatalogService;

/**
 * ParentPortalController — thin endpoint exposer for the parent portal.
 *
 * All data access, session/OTP decisions, fee/report-card/attendance reads and
 * messaging orchestration live in ParentPortalManager (normalised schema only).
 * This controller only: reads input, validates required fields, delegates and
 * formats the response via handleApiResponse().
 *
 * Uses ParentAuthMiddleware instead of staff JWT auth; the middleware sets
 * $_SERVER['parent_auth'] (parent_id/user_id/session_id/session_token).
 *
 * ROUTES (all under /api/parent-portal/):
 * POST /api/parent-portal/login                    → postLogin()
 * POST /api/parent-portal/login-otp-request        → postLoginOtpRequest()
 * POST /api/parent-portal/login-otp-verify         → postLoginOtpVerify()
 * POST /api/parent-portal/logout                   → postLogout()
 * GET  /api/parent-portal/dashboard                → getDashboard()
 * GET  /api/parent-portal/student-fees/{id}        → getStudentFees($id)
 * GET  /api/parent-portal/student-payment-history/{id} → getStudentPaymentHistory($id)
 * GET  /api/parent-portal/student-statement/{id}   → getStudentStatement($id)
 * GET  /api/parent-portal/fee-balance/{id}         → getFeeBalance($id)
 */
class ParentPortalController extends BaseController
{
    private ParentPortalManager $parent;

    public function __construct()
    {
        parent::__construct();
        $this->parent = $this->contract('App\API\Modules\parent\ParentPortalManager');
    }

    // ============================================================
    // AUTH ENDPOINTS (no ParentAuthMiddleware required)
    // ============================================================

    /**
     * POST /api/parent-portal/login
     * Body: {email, password}
     */
    public function postLogin($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postLogin($data));
    }

    /**
     * POST /api/parent-portal/login-otp-request
     * Body: {phone}
     */
    public function postLoginOtpRequest($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postLoginOtpRequest($data));
    }

    /**
     * POST /api/parent-portal/login-otp-verify
     * Body: {otp_session_id, otp_code}
     */
    public function postLoginOtpVerify($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postLoginOtpVerify($data));
    }

    /**
     * POST /api/parent-portal/logout
     */
    public function postLogout($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postLogout());
    }

    // ============================================================
    // AUTHENTICATED ENDPOINTS (require ParentAuthMiddleware)
    // ============================================================

    /**
     * GET /api/parent-portal/dashboard
     */
    public function getDashboard($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->getDashboard());
    }

    /** GET /api/parent-portal/community */
    public function getCommunity($id = null, $data = [], $segments = [])
    { return $this->handleApiResponse($this->parent->getCommunity()); }

    /** GET /api/parent-portal/uniform-catalog */
    public function getUniformCatalog($id = null, $data = [], $segments = [])
    { return $this->handleApiResponse(['success'=>true,'data'=>['products'=>($this->contract('App\API\Services\payments\UniformCatalogService', Database::getInstance()->getConnection()))->list($data)]]); }

    /** GET /api/parent-portal/uniform-cart */
    public function getUniformCart($id = null, $data = [], $segments = [])
    { $parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->cart('parent',$parentId)]); }

    public function getUniformPaymentOptions($id = null, $data = [], $segments = [])
    { return $this->handleApiResponse(['success'=>true,'data'=>['options'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->paymentOptions(false)]]); }

    /** POST /api/parent-portal/uniform-cart */
    public function postUniformCart($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->addCart('parent',$parentId,$data)]); }catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    public function putUniformCart($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->updateCart('parent',$parentId,(int)$id,(int)($data['quantity']??0))]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    public function deleteUniformCart($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->removeCart('parent',$parentId,(int)$id)]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    /** POST /api/parent-portal/uniform-wishlist */
    public function postUniformWishlist($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>['items'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->addWishlist('parent',$parentId,(int)($data['product_id']??0))]]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    public function getUniformWishlist($id = null, $data = [], $segments = [])
    { $parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>['items'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->wishlist('parent',$parentId)]]); }

    public function deleteUniformWishlist($id = null, $data = [], $segments = [])
    { $parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>['items'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->removeWishlist('parent',$parentId,(int)$id)]]); }

    /** POST /api/parent-portal/uniform-checkout-payment */
    public function postUniformCheckoutPayment($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->checkout('parent',$parentId,$data,0)]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    public function getUniformOrders($id = null, $data = [], $segments = [])
    { $parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>['orders'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->orders('parent',$parentId)]]); }

    public function deleteUniformOrders($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->cancel((int)$id,'parent',$parentId,0)]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    public function postUniformOrderPaymentRetry($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->retryPayment((int)$id,'parent',$parentId,$data,0)]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    public function postUniformReviews($id = null, $data = [], $segments = [])
    { try{$parentId=(int)(($_SERVER['parent_auth']['parent_id']??0));return $this->handleApiResponse(['success'=>true,'data'=>($this->contract('App\API\Services\catalog\CatalogCommerceService', Database::getInstance()->getConnection()))->saveReview('parent',$parentId,$data)]);}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    /**
     * GET /api/parent-portal/student-fees/{id}
     */
    public function getStudentFees($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentFees((int)$id));
    }

    /**
     * GET /api/parent-portal/student-payment-history/{id}
     */
    public function getStudentPaymentHistory($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentPaymentHistory((int)$id));
    }

    /**
     * GET /api/parent-portal/student-statement/{id}
     */
    public function getStudentStatement($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentStatement((int)$id));
    }

    /**
     * GET /api/parent-portal/fee-balance/{id}
     */
    public function getFeeBalance($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getFeeBalance((int)$id));
    }

    /**
     * GET /api/parent-portal/student-attendance/{id}
     */
    public function getStudentAttendance($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentAttendance((int)$id));
    }

    /** GET /api/parent-portal/student-transport/{id} */
    public function getStudentTransport($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('student_id required');
        return $this->handleApiResponse($this->parent->getStudentTransport((int) $id));
    }

    /**
     * GET /api/parent-portal/student-performance/{id}
     */
    public function getStudentPerformance($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentPerformance((int)$id));
    }

    /**
     * GET /api/parent-portal/student-report-card/{id}
     */
    public function getStudentReportCard($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getStudentReportCard((int)$id));
    }

    /** GET /api/parent-portal/student-learning-plan/{id} */
    public function getStudentLearningPlan($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('student_id required');
        return $this->handleApiResponse($this->parent->getStudentLearningPlan((int)$id));
    }

    /**
     * GET /api/parent-portal/messages/{studentId?}
     */
    public function getMessages($id = null, $data = [], $segments = [])
    {
        $studentId = $id ? (int)$id : null;
        return $this->handleApiResponse($this->parent->getMessages($studentId));
    }

    /**
     * POST /api/parent-portal/send-message
     * Body: {student_id, subject, message}
     */
    public function postSendMessage($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postSendMessage($data));
    }

    /**
     * GET /api/parent-portal/portfolio/{studentId}
     */
    public function getPortfolio($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('student_id required');
        }
        return $this->handleApiResponse($this->parent->getPortfolio((int)$id));
    }

    /**
     * GET /api/parent-portal/grading-scale
     */
    public function getGradingScale($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->getGradingScale());
    }

    /**
     * POST /api/parent-portal/initiate-mpesa-payment
     * Body: {student_id, phone?, amount?}
     */
    public function postInitiateMpesaPayment($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->parent->postInitiateMpesaPayment($data));
    }

    /**
     * GET /api/parent-portal/mpesa-status/{checkoutRequestId}
     */
    public function getMpesaStatus($id = null, $data = [], $segments = [])
    {
        $checkoutId = $id ?? ($data['checkout_request_id'] ?? '');
        if (!$checkoutId) {
            return $this->badRequest('checkout_request_id required');
        }
        return $this->handleApiResponse($this->parent->getMpesaStatus((string)$checkoutId));
    }
}
