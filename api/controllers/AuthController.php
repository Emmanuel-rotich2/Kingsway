<?php
namespace App\API\Controllers;

use App\API\Modules\Auth\AuthAPI;
use Exception;

class AuthController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new AuthAPI();
    }

    // POST /api/auth/login
    public function login($id = null, $data = [], $segments = [])
    {
        $result = $this->api->login($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/logout
    public function logout($id = null, $data = [], $segments = [])
    {
        $result = $this->api->logout($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/forgot-password
    public function forgotPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->forgotPassword($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/reset-password
    public function resetPassword($id = null, $data = [], $segments = [])
    {
        $result = $this->api->resetPassword($data);
        return $this->handleResponse($result);
    }

    // POST /api/auth/refresh-token
    public function refreshToken($id = null, $data = [], $segments = [])
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
