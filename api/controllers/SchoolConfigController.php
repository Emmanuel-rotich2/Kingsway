<?php
namespace App\API\Controllers;

use App\API\Modules\school_config\SchoolConfigAPI;
use Exception;

class SchoolConfigController extends BaseController
{
    private SchoolConfigAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new SchoolConfigAPI();
    }

    public function get($id = null, $data = [], $segments = []) {
        try {
            if ($id === null) {
                return $this->api->list($data);
            }
            return $this->api->get($id);
        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    public function post($id = null, $data = [], $segments = []) {
        try {
            if ($id === null) {
                return $this->api->create($data);
            }
            return $this->respondWith(400, 'Invalid action', null);
        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    public function put($id = null, $data = [], $segments = []) {
        try {
            return $this->api->update($id, $data);
        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    public function delete($id = null, $data = [], $segments = []) {
        try {
            return $this->api->delete($id);
        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }
}
