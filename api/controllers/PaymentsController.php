<?php
/**
 * PaymentsController - Exposes RESTful endpoints for all payment webhooks
 */
namespace App\API\Controllers;

use App\API\Modules\payments\PaymentsAPI;

class PaymentsController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new PaymentsAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Payments API is running']);
    }

    /**
     * Standard API response handler (copied from AcademicController)
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                return $result['success']
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            if (isset($result['status'])) {
                return $result['status'] === 'success'
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            return $this->success($result);
        }

        return $this->success(['result' => $result]);
    }

    /**
     * POST /api/payments/mpesa-b2c-callback
     */
    public function postMpesaB2cCallback($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaB2CCallback($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-b2c-timeout
     */
    public function postMpesaB2cTimeout($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaB2CTimeout($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-c2b-confirmation
     */
    public function postMpesaC2bConfirmation($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaC2BConfirmation($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-validation
     */
    public function postKcbValidation($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbValidation($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-transfer-callback
     */
    public function postKcbTransferCallback($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbTransferCallback($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-notification
     */
    public function postKcbNotification($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbNotification($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/bank-webhook
     */
    public function postBankWebhook($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processBankWebhook($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }
}

