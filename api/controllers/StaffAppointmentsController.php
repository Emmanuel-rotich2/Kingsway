<?php

namespace App\API\Controllers;

use App\API\Services\StaffAppointmentsService;
use InvalidArgumentException;
use Throwable;

class StaffAppointmentsController extends BaseController
{
    private StaffAppointmentsService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = $this->contract('App\API\Services\StaffAppointmentsService');
    }

    public function get($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireLeadershipAccess()) return $denied;
        if ($id !== null) {
            return $this->notFound('Use /api/staff-appointments/internal or /api/staff-appointments/new');
        }
        return $this->runSafely(fn() => $this->success($this->service->summary()));
    }

    public function getInternal($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireLeadershipAccess()) return $denied;
        return $this->runSafely(fn() => $this->success($this->service->listInternal($_GET ?? [])));
    }

    public function postInternal($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireLeadershipAccess()) return $denied;
        return $this->runSafely(function () use ($data) {
            $appointmentId = $this->service->submitInternal($data, $this->actorId());
            return $this->created(['id' => $appointmentId], 'Internal appointment submitted for Director approval');
        });
    }

    public function putInternalApprove($id = null, $data = [], $segments = [])
    {
        return $this->reviewInternal($id, $data, 'approve');
    }

    public function putInternalReject($id = null, $data = [], $segments = [])
    {
        return $this->reviewInternal($id, $data, 'reject');
    }

    public function putInternalRevert($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireDirectorAccess()) return $denied;
        return $this->runSafely(function () use ($id, $data) {
            $this->service->revertInternal((int)($id ?? $data['id'] ?? 0), $this->actorId(), $data);
            return $this->success(null, 'Acting appointment reverted');
        });
    }

    public function getNew($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireLeadershipAccess()) return $denied;
        return $this->runSafely(fn() => $this->success($this->service->listNew($_GET ?? [])));
    }

    public function postNew($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireLeadershipAccess()) return $denied;
        return $this->runSafely(function () use ($data) {
            $appointmentId = $this->service->submitNew($data, $this->actorId());
            return $this->created(['id' => $appointmentId], 'New staff appointment submitted for Director approval');
        });
    }

    public function putNewApprove($id = null, $data = [], $segments = [])
    {
        return $this->reviewNew($id, $data, 'approve');
    }

    public function putNewReject($id = null, $data = [], $segments = [])
    {
        return $this->reviewNew($id, $data, 'reject');
    }

    public function putNewOnboard($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireSchoolAdminAccess()) return $denied;
        return $this->runSafely(function () use ($id, $data) {
            $result = $this->service->onboardNew(
                (int)($id ?? $data['id'] ?? 0),
                $this->actorId(),
                (int)($data['role_id'] ?? 0),
                $data
            );
            return $this->success($result, 'New staff onboarded successfully');
        });
    }

    public function postCareersCandidate($id = null, $data = [], $segments = [])
    {
        return $this->runSafely(function () use ($data) {
            $appointmentId = $this->service->createCareerCandidate($data);
            return $this->created(['id' => $appointmentId], 'Candidate appointment received for recruitment review');
        });
    }

    public function getHistory($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireLeadershipAccess()) return $denied;
        return $this->runSafely(function () use ($data) {
            $appointmentType = $_GET['appointment_type'] ?? $data['appointment_type'] ?? null;
            $appointmentId = (int)($_GET['appointment_id'] ?? $data['appointment_id'] ?? 0);
            return $this->success($this->service->history((string)$appointmentType, $appointmentId));
        });
    }

    private function reviewInternal($id, array $data, string $action)
    {
        if ($denied = $this->requireDirectorAccess()) return $denied;
        return $this->runSafely(function () use ($id, $data, $action) {
            $this->service->reviewInternal((int)($id ?? $data['id'] ?? 0), $action, $this->actorId(), $data);
            return $this->success(null, 'Internal appointment ' . ($action === 'approve' ? 'approved' : 'rejected'));
        });
    }

    private function reviewNew($id, array $data, string $action)
    {
        if ($denied = $this->requireDirectorAccess()) return $denied;
        return $this->runSafely(function () use ($id, $data, $action) {
            $this->service->reviewNew((int)($id ?? $data['id'] ?? 0), $action, $this->actorId(), $data);
            return $this->success(null, 'New staff appointment ' . ($action === 'approve' ? 'approved' : 'rejected'));
        });
    }

    private function actorId(): int
    {
        $actorId = (int)($this->user['id'] ?? $this->user['user_id'] ?? 0);
        if (!$actorId) {
            throw new InvalidArgumentException('Authenticated user context is required');
        }
        return $actorId;
    }

    private function requireLeadershipAccess()
    {
        return $this->userHasAny([], [], [
            'system administrator', 'school administrator', 'director',
            'headteacher', 'deputy head - academic', 'deputy head – academic',
            'deputy head - discipline', 'deputy head – discipline',
        ]) ? null : $this->forbidden('School leadership access required.');
    }

    private function requireDirectorAccess()
    {
        return $this->userHasAny([], [], ['system administrator', 'director'])
            ? null
            : $this->forbidden('Director approval is required.');
    }

    private function requireSchoolAdminAccess()
    {
        return $this->userHasAny([], [], ['system administrator', 'school administrator'])
            ? null
            : $this->forbidden('School Administrator onboarding access required.');
    }

    private function runSafely(callable $callback)
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Staff user context is required') {
                \App\API\Services\Logger::legacyError('[StaffAppointmentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->unauthorized('An internal error occurred.');
            }
            \App\API\Services\Logger::legacyError('[StaffAppointmentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        } catch (Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffAppointmentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }
}
