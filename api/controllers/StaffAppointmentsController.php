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
        $this->service = new StaffAppointmentsService();
    }

    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->notFound('Use /api/staff-appointments/internal or /api/staff-appointments/new');
        }
        return $this->respond(fn() => $this->success($this->service->summary()));
    }

    public function getInternal($id = null, $data = [], $segments = [])
    {
        return $this->respond(fn() => $this->success($this->service->listInternal($_GET ?? [])));
    }

    public function postInternal($id = null, $data = [], $segments = [])
    {
        return $this->respond(function () use ($data) {
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
        return $this->respond(function () use ($id, $data) {
            $this->service->revertInternal((int)($id ?? $data['id'] ?? 0), $this->actorId(), $data);
            return $this->success(null, 'Acting appointment reverted');
        });
    }

    public function getNew($id = null, $data = [], $segments = [])
    {
        return $this->respond(fn() => $this->success($this->service->listNew($_GET ?? [])));
    }

    public function postNew($id = null, $data = [], $segments = [])
    {
        return $this->respond(function () use ($data) {
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
        return $this->respond(function () use ($id, $data) {
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
        return $this->respond(function () use ($data) {
            $appointmentId = $this->service->createCareerCandidate($data);
            return $this->created(['id' => $appointmentId], 'Candidate appointment received for recruitment review');
        });
    }

    public function getHistory($id = null, $data = [], $segments = [])
    {
        return $this->respond(function () use ($data) {
            $appointmentType = $_GET['appointment_type'] ?? $data['appointment_type'] ?? null;
            $appointmentId = (int)($_GET['appointment_id'] ?? $data['appointment_id'] ?? 0);
            return $this->success($this->service->history((string)$appointmentType, $appointmentId));
        });
    }

    private function reviewInternal($id, array $data, string $action)
    {
        return $this->respond(function () use ($id, $data, $action) {
            $this->service->reviewInternal((int)($id ?? $data['id'] ?? 0), $action, $this->actorId(), $data);
            return $this->success(null, 'Internal appointment ' . ($action === 'approve' ? 'approved' : 'rejected'));
        });
    }

    private function reviewNew($id, array $data, string $action)
    {
        return $this->respond(function () use ($id, $data, $action) {
            $this->service->reviewNew((int)($id ?? $data['id'] ?? 0), $action, $this->actorId(), $data);
            return $this->success(null, 'New staff appointment ' . ($action === 'approve' ? 'approved' : 'rejected'));
        });
    }

    private function actorId(): int
    {
        $actorId = $this->service->staffIdForUser($this->user);
        if (!$actorId) {
            throw new InvalidArgumentException('Staff user context is required');
        }
        return $actorId;
    }

    private function respond(callable $callback)
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Staff user context is required') {
                return $this->unauthorized($e->getMessage());
            }
            return $this->badRequest($e->getMessage());
        } catch (Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
