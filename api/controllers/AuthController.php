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

    // POST /api/auth/reset-password
    public function postResetPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->resetPassword($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/refresh-token
    public function postRefreshToken($id = null, $data = [], $segments = [])
    {
        $result = $this->api->refreshToken($data);
        return $this->handleResponse($result);
    }
    // Helper for consistent API response
    private function handleResponse($result)
    {
        $response = null;
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    $response = $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    $response = $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            } else {
                $response = $this->success($result);
            }
        } else {
            $response = $this->success($result);
        }
        return $response;
    }
}
