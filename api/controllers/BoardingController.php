<?php
namespace App\API\Controllers;

use App\API\Modules\boarding\BoardingManager;
use Exception;

/**
 * BoardingController
 * Handles boarding/hostel management endpoints.
 *
 * GET    /api/boarding                    → getStats()
 * GET    /api/boarding/stats              → getStats()
 * GET    /api/boarding/occupancy          → getOccupancy()
 * GET    /api/boarding/dormitories        → getDormitories()
 * POST   /api/boarding/dormitories        → postDormitories()
 * PUT    /api/boarding/dormitories/{id}   → putDormitories()
 * DELETE /api/boarding/dormitories/{id}   → deleteDormitories()
 * GET    /api/boarding/students           → getStudents()
 * GET    /api/boarding/roll-call          → getRollCall()
 * POST   /api/boarding/roll-call          → postRollCall()
 * GET    /api/boarding/exeats             → getExeats()
 * POST   /api/boarding/exeats             → postExeats()
 * PUT    /api/boarding/exeats/{id}        → putExeats()  (approve/reject)
 * GET    /api/boarding/activity           → getActivity()
 */
class BoardingController extends BaseController
{
    private BoardingManager $manager;

    public function __construct()
    {
        parent::__construct();
        $this->manager = new BoardingManager();
    }

    public function get($id = null, $data = [], $segments = [])
    {
        return $this->getStats();
    }
    public function getStats($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getStats());
    }
    public function getOccupancy($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getOccupancy());
    }

    public function getDormitories($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->listDormitories());
    }

    public function postDormitories($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->createDormitory($data));
    }

    public function putDormitories($id = null, $data = [], $segments = [])
    {
        $dormId = (int) ($id ?? $data['id'] ?? 0);
        return $this->handleApiResponse($this->manager->updateDormitory($dormId, $data));
    }

    public function deleteDormitories($id = null, $data = [], $segments = [])
    {
        $dormId = (int) ($id ?? $data['id'] ?? 0);
        return $this->handleApiResponse($this->manager->deleteDormitory($dormId));
    }

    public function getStudents($id = null, $data = [], $segments = [])
    {
        $dormId = $_GET['dormitory_id'] ?? $data['dormitory_id'] ?? null;
        $search = $_GET['search'] ?? $data['search'] ?? '';
        return $this->handleApiResponse($this->manager->getStudents($dormId, $search));
    }
    public function getRollCall($id = null, $data = [], $segments = [])
    {
        $date = $_GET['date'] ?? $data['date'] ?? date('Y-m-d');
        $dateFrom = $_GET['date_from'] ?? $data['date_from'] ?? $date;
        $dateTo = $_GET['date_to'] ?? $data['date_to'] ?? $date;
        return $this->handleApiResponse($this->manager->getRollCall($date, $dateFrom, $dateTo));
    }

    public function postRollCall($id = null, $data = [], $segments = [])
    {
        $markedBy = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $this->handleApiResponse($this->manager->markRollCall($data, $markedBy));
    }

    public function getExeats($id = null, $data = [], $segments = [])
    {
        $status = $_GET['status'] ?? $data['status'] ?? '';
        return $this->handleApiResponse($this->manager->getExeats(
            $status,
            $_GET['date_from'] ?? $data['date_from'] ?? null,
            $_GET['date_to'] ?? $data['date_to'] ?? null
        ));
    }

    public function postExeats($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->createExeat($data));
    }

    public function putExeats($id = null, $data = [], $segments = [])
    {
        $exeatId = (int) ($id ?? $data['id'] ?? 0);
        $action = $data['action'] ?? $segments[0] ?? 'approve';
        return $this->handleApiResponse($this->manager->updateExeat($exeatId, $action));
    }

    public function getActivity($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getActivity());
    }
}
