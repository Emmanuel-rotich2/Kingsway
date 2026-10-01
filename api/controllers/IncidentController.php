<?php

declare(strict_types=1);

namespace App\API\Controllers;

/**
 * School incidents management — HTTP boundary.
 *
 * Any staff member may REPORT an incident (drivers, teachers, kitchen,
 * security, boarding, transport…). The authority roles — Deputy Heads,
 * Headteacher, School Administrator, Director — escalate, review, resolve and
 * see every incident. Reporters without the view-all permission see their own
 * reports plus what has been assigned to them.
 */
final class IncidentController extends BaseController
{
    private const VIEW_ALL_PERMS = ['incident_view_all', 'incident_manage'];

    private ?\App\API\Services\IncidentService $service = null;

    private function service(): \App\API\Services\IncidentService
    {
        if ($this->service === null) {
            $this->service = new \App\API\Services\IncidentService($this->db->getConnection());
        }
        return $this->service;
    }

    private function operatorId(): int
    {
        return (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
    }

    /**
     * Row-level scope: a reporter without incident_view_all sees only their
     * own reports plus what has been assigned to them.
     */
    private function scope(): array
    {
        if ($this->userHasAny(...self::VIEW_ALL_PERMS)) {
            return [];
        }
        $assignedStaffId = 0;
        try {
            $stmt = $this->db->getConnection()->prepare(
                'SELECT id FROM staff WHERE person_id = (SELECT person_id FROM users WHERE id = ?) LIMIT 1'
            );
            $stmt->execute([$this->operatorId()]);
            $assignedStaffId = (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            $assignedStaffId = 0;
        }
        return ['reported_by' => $this->operatorId(), 'assigned_staff_id' => $assignedStaffId];
    }

    private function canAct(string ...$perms): bool
    {
        return $this->userHasAny(...$perms);
    }

    public function getIncidents($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny('incident_view', 'incident_view_all', 'incident_report', 'incident_manage')) {
            return $this->forbidden('Incident view permission is required.');
        }
        try {
            return $this->success($this->service()->listIncidents(array_merge($_GET, $data), $this->scope()));
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[IncidentController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function getIncident($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny('incident_view', 'incident_view_all', 'incident_report', 'incident_manage')) {
            return $this->forbidden('Incident view permission is required.');
        }
        $incidentId = $id !== null ? (int) $id : 0;
        if ($incidentId <= 0) {
            return $this->badRequest('Incident ID is required');
        }
        try {
            $incident = $this->service()->getIncident($incidentId);
            if (!$incident) {
                return $this->notFound('Incident not found');
            }
            $scope = $this->scope();
            if (!empty($scope['reported_by'])
                && (int) $incident['reported_by'] !== (int) $scope['reported_by']
                && (int) ($incident['assigned_to'] ?? 0) !== (int) $scope['assigned_staff_id']) {
                return $this->forbidden('You may only view incidents you reported or that are assigned to you.');
            }
            return $this->success($incident);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[IncidentController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function postIncident($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny('incident_report', 'incident_manage')) {
            return $this->forbidden('Incident report permission is required.');
        }
        try {
            return $this->success($this->service()->reportIncident($data, $this->operatorId()));
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[IncidentController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function putIncident($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny('incident_manage', 'incident_review', 'incident_resolve', 'incident_escalate')) {
            return $this->forbidden('Incident management permission is required.');
        }
        $incidentId = $id !== null ? (int) $id : 0;
        if ($incidentId <= 0) {
            return $this->badRequest('Incident ID is required');
        }
        try {
            $incident = $this->service()->updateIncident($incidentId, $data, $this->operatorId());
            if (!$incident) {
                return $this->notFound('Incident not found');
            }
            return $this->success($incident);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[IncidentController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function postEscalate($id = null, $data = [], $segments = [])
    {
        if (!$this->canAct('incident_escalate', 'incident_manage')) {
            return $this->forbidden('Incident escalate permission is required.');
        }
        $incidentId = $id !== null ? (int) $id : 0;
        if ($incidentId <= 0) {
            return $this->badRequest('Incident ID is required');
        }
        $toLevel = (int) ($data['escalation_level'] ?? 0);
        try {
            $incident = $this->service()->transitionStatus($incidentId, 'escalated', $this->operatorId(), $toLevel);
            if (!$incident) {
                return $this->notFound('Incident not found');
            }
            return $this->success($incident);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[IncidentController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function postResolve($id = null, $data = [], $segments = [])
    {
        if (!$this->canAct('incident_resolve', 'incident_manage')) {
            return $this->forbidden('Incident resolve permission is required.');
        }
        $incidentId = $id !== null ? (int) $id : 0;
        if ($incidentId <= 0) {
            return $this->badRequest('Incident ID is required');
        }
        $status = in_array(strtolower((string) ($data['status'] ?? 'resolved')), ['resolved', 'closed'], true)
            ? strtolower((string) $data['status'])
            : 'resolved';
        try {
            $incident = $this->service()->transitionStatus($incidentId, $status, $this->operatorId());
            if (!$incident) {
                return $this->notFound('Incident not found');
            }
            return $this->success($incident);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[IncidentController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    public function postReview($id = null, $data = [], $segments = [])
    {
        if (!$this->canAct('incident_review', 'incident_manage')) {
            return $this->forbidden('Incident review permission is required.');
        }
        $incidentId = $id !== null ? (int) $id : 0;
        if ($incidentId <= 0) {
            return $this->badRequest('Incident ID is required');
        }
        try {
            $incident = $this->service()->transitionStatus($incidentId, 'in_review', $this->operatorId());
            if (!$incident) {
                return $this->notFound('Incident not found');
            }
            return $this->success($incident);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[IncidentController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }
}
