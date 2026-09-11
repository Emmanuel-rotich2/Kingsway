<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\health\HealthAPI;

/**
 * HealthController
 * Student health records, sick bay visits, and vaccinations.
 *
 * ROUTES:
 * GET  /api/health/summary                  → getSummary()
 * GET  /api/health/records                  → getRecords()
 * GET  /api/health/records/{id}             → getRecords($id)  — by student_id
 * POST /api/health/records                  → postRecords()
 * PUT  /api/health/records/{id}             → putRecords($id)
 * GET  /api/health/sick-bay                 → getSickBay()
 * POST /api/health/sick-bay                 → postSickBay()
 * PUT  /api/health/sick-bay/{id}            → putSickBay($id)  — update / dismiss
 * GET  /api/health/vaccinations             → getVaccinations()
 * GET  /api/health/vaccinations/{id}        → getVaccinations($id)  — by student_id
 * POST /api/health/vaccinations             → postVaccinations()
 */
class HealthController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\health\HealthAPI');
    }

    private function userId()
    {
        return $this->user['user_id'] ?? $this->user['id'] ?? null;
    }

    // ----------------------------------------------------------------
    // SUMMARY
    // ----------------------------------------------------------------

    public function getSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSummary();
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? []);
    }

    // ----------------------------------------------------------------
    // HEALTH RECORDS
    // ----------------------------------------------------------------

    public function getRecords($id = null, $data = [], $segments = [])
    {
        $studentId = $id ? (int)$id : null;
        $result = $this->api->listRecords(
            $studentId,
            $_GET['search'] ?? '',
            $_GET['class_id'] ?? null
        );
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? []);
    }

    public function postRecords($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id'])) {
            return $this->badRequest('student_id is required');
        }

        $result = $this->api->upsertRecord($data, $this->userId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? ['message' => 'Health record saved']);
    }

    public function putRecords($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Student ID required');
        $data['student_id'] = $id;
        return $this->postRecords(null, $data, $segments);
    }

    // ----------------------------------------------------------------
    // SICK BAY
    // ----------------------------------------------------------------

    public function getSickBay($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listVisits(
            $_GET['status'] ?? '',
            $_GET['date'] ?? ''
        );
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? []);
    }

    public function postSickBay($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id']) || trim($data['complaint'] ?? '') === '') {
            return $this->badRequest('student_id and complaint are required');
        }

        $result = $this->api->createVisit($data, $this->userId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->created(['id' => $result['data']['id'] ?? null], 'Visit recorded');
    }

    public function putSickBay($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Visit ID required');
        $dismiss = ($segments[0] ?? '') === 'dismiss';

        $result = $this->api->updateVisit($id, $data, $dismiss);
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? ['message' => 'Visit updated']);
    }

    // ----------------------------------------------------------------
    // VACCINATIONS
    // ----------------------------------------------------------------

    public function getVaccinations($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listVaccinations(
            $id ? (int)$id : null,
            !empty($_GET['due_only'])
        );
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? []);
    }

    public function postVaccinations($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id']) || trim($data['vaccine_name'] ?? '') === '') {
            return $this->badRequest('student_id and vaccine_name are required');
        }

        $result = $this->api->createVaccination($data, $this->userId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->created(['id' => $result['data']['id'] ?? null], 'Vaccination recorded');
    }
}
