<?php
namespace App\API\Controllers;

use App\API\Modules\auth\AuthAPI;
use Exception;

class AuthController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new AuthAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Auth API is running']);
    }

    // POST /api/auth/login
    // POST /api/auth/login
    public function postLogin($id = null, $data = [], $segments = [])
    {
        $result = $this->api->login($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/logout
    public function postLogout($id = null, $data = [], $segments = [])
    {
        $result = $this->api->logout($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/forgot-password
    public function postForgotPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->forgotPassword($data);
        return $this->handleResponse($result);
    }

    // GET /api/auth/reset-password
    public function getResetPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->verifyResetToken($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/reset-password
    public function postResetPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->resetPassword($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/reset-default-password
    public function postResetDefaultPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->resetDefaultPassword($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/refresh-token
    public function postRefreshToken($id = null, $data = [], $segments = [])
    {
        $result = $this->api->exchangeRefreshToken($data);

        if (
            is_array($result) &&
            array_key_exists('success', $result) &&
            $result['success'] === false
        ) {
            $code = (int) ($result['code'] ?? 401);
            $message = (string) (
                $result['error']
                    ?? $result['message']
                    ?? 'Session refresh failed'
            );

            if ($code === 401) {
                return $this->unauthorized($message);
            }
            if ($code >= 500) {
                return $this->serverError($message);
            }

            return $this->badRequest($message);
        }

        return $this->handleResponse($result);
    }

    // POST /api/auth/logout
    // Revoke refresh token on logout
    public function postLogoutRefresh($id = null, $data = [], $segments = [])
    {
        $result = $this->api->revokeRefreshToken($data);
        return $this->handleResponse($result);
    }
    // Helper for consistent API response
    private function handleResponse($result)
    {
        if (is_array($result)) {
            // If result already has proper API response structure with status and data, return as-is
            if (isset($result['status']) && isset($result['data'])) {
                return $result;
            }
            // Handle legacy format with 'success' key
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            // Default: wrap in success response
            return $this->success($result);
        }
        // Non-array results
        return $this->success($result);
    }
}
