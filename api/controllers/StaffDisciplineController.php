<?php

declare(strict_types=1);

namespace App\API\Controllers;

/**
 * Staff disciplinary handling — HTTP boundary.
 *
 * Used by Deputy Heads, Headteacher, School Administrator and Director.
 * Cases escalate to Deputy Head - Discipline; the senior roles see and act
 * on every case.
 */
final class StaffDisciplineController extends BaseController
{
    private const VIEW_PERMS = ['staff_discipline_view', 'staff_discipline_view_all', 'staff_discipline_manage'];
    private const MANAGE_PERMS = ['staff_discipline_manage', 'staff_discipline_create', 'staff_discipline_edit'];

    private ?\App\API\Services\StaffDisciplineService $service = null;

    private function service(): \App\API\Services\StaffDisciplineService
    {
        if ($this->service === null) {
            $this->service = new \App\API\Services\StaffDisciplineService($this->db->getConnection());
        }
        return $this->service;
    }

    private function operatorId(): int
    {
        return (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
    }

    public function getCases($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(...self::VIEW_PERMS)) {
            return $this->forbidden('Staff discipline view permission is required.');
        }
        try {
            return $this->success($this->service()->listCases(array_merge($_GET, $data)));
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffDisciplineController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function getCase($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(...self::VIEW_PERMS)) {
            return $this->forbidden('Staff discipline view permission is required.');
        }
        $caseId = $id !== null ? (int) $id : 0;
        if ($caseId <= 0) {
            return $this->badRequest('Case ID is required');
        }
        try {
            $case = $this->service()->getCase($caseId);
            if (!$case) {
                return $this->notFound('Staff disciplinary case not found');
            }
            return $this->success($case);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffDisciplineController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function getContext($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(...self::VIEW_PERMS)) {
            return $this->forbidden('Staff discipline view permission is required.');
        }
        try {
            return $this->success($this->service()->context());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffDisciplineController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function postCase($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny('staff_discipline_create', 'staff_discipline_manage')) {
            return $this->forbidden('Staff discipline create permission is required.');
        }
        try {
            return $this->success($this->service()->createCase($data, $this->operatorId()));
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffDisciplineController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function putCase($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny('staff_discipline_edit', 'staff_discipline_manage', 'staff_discipline_resolve', 'staff_discipline_escalate')) {
            return $this->forbidden('Staff discipline edit permission is required.');
        }
        $caseId = $id !== null ? (int) $id : 0;
        if ($caseId <= 0) {
            return $this->badRequest('Case ID is required');
        }
        try {
            $case = $this->service()->updateCase($caseId, $data, $this->operatorId());
            if (!$case) {
                return $this->notFound('Staff disciplinary case not found');
            }
            return $this->success($case);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffDisciplineController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function postEscalate($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny('staff_discipline_escalate', 'staff_discipline_manage')) {
            return $this->forbidden('Staff discipline escalate permission is required.');
        }
        $caseId = $id !== null ? (int) $id : 0;
        if ($caseId <= 0) {
            return $this->badRequest('Case ID is required');
        }
        try {
            $case = $this->service()->transitionStatus($caseId, 'escalated', $this->operatorId());
            if (!$case) {
                return $this->notFound('Staff disciplinary case not found');
            }
            return $this->success($case);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffDisciplineController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function postResolve($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny('staff_discipline_resolve', 'staff_discipline_manage')) {
            return $this->forbidden('Staff discipline resolve permission is required.');
        }
        $caseId = $id !== null ? (int) $id : 0;
        if ($caseId <= 0) {
            return $this->badRequest('Case ID is required');
        }
        $status = in_array(strtolower((string) ($data['status'] ?? 'resolved')), ['resolved', 'dismissed'], true)
            ? strtolower((string) $data['status'])
            : 'resolved';
        try {
            $case = $this->service()->transitionStatus($caseId, $status, $this->operatorId());
            if (!$case) {
                return $this->notFound('Staff disciplinary case not found');
            }
            return $this->success($case);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffDisciplineController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }
}
