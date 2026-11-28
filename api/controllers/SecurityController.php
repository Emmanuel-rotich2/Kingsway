<?php

declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\security\SecurityGateService;
use RuntimeException;

/**
 * /api/security/* — the school security office (gate desk, visitor register,
 * gate occurrence book, exeats at the gate, security incidents).
 * Authorization is decided here per action; see SecurityGateService.
 */
class SecurityController extends BaseController
{
    private ?SecurityGateService $svc = null;

    private function svc(): SecurityGateService
    {
        return $this->svc ??= new SecurityGateService($this->db->getConnection());
    }

    private function roles(): array
    {
        return array_map('intval', (array) $this->getUserRoleIds());
    }

    /** Run an action; $gate = true limits it to the gate team and leadership. */
    private function run(callable $fn, bool $gate = true)
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if ($gate && !SecurityGateService::canUseGate($this->roles())) {
            return $this->forbidden('The gate desk is for the security team and school leadership.');
        }
        try {
            return $this->success($fn());
        } catch (RuntimeException $e) {
            $code = (int) $e->getCode();
            return $this->respond(null, $e->getMessage(), in_array($code, [400, 403, 404, 409], true) ? $code : 400, false);
        } catch (\Throwable $e) {
            \App\API\Includes\FileLogger::write('security', ['type' => 'security_office_error', 'reason' => mb_substr($e->getMessage(), 0, 200)], 'error');
            return $this->serverError('The security office request could not be completed.');
        }
    }

    private function q(array $data): array
    {
        return array_merge($_GET, is_array($data) ? $data : []);
    }

    /** GET /api/security/summary */
    public function getSummary($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->summary());
    }

    /** GET /api/security/visits?status=&from=&to=&purpose=&q= */
    public function getVisits($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->listVisits($this->q($data)));
    }

    /** POST /api/security/visits — sign a visitor in (or record them as turned away with deny=1). */
    public function postVisits($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->checkIn((array) $data, (int) $this->getUserId()));
    }

    /** POST /api/security/visits-checkout/{id} */
    public function postVisitsCheckout($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->checkOut((int) $id, (int) $this->getUserId(), $data['notes'] ?? null));
    }

    /** GET /api/security/movements?from=&to=&movement_type=&q= */
    public function getMovements($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->listMovements($this->q($data)));
    }

    /** POST /api/security/movements */
    public function postMovements($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->recordMovement((array) $data, (int) $this->getUserId()));
    }

    /** GET /api/security/exeats-today */
    public function getExeatsToday($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->exeatsToday());
    }

    /** POST /api/security/exeat-gate/{permissionId} {direction: out|in} */
    public function postExeatGate($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->exeatGate((int) $id, ($data['direction'] ?? '') === 'in' ? 'in' : 'out', (int) $this->getUserId()));
    }

    /** GET /api/security/learner-lookup?q= — minimal card to verify a pick-up. */
    public function getLearnerLookup($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->learnerLookup((string) ($this->q($data)['q'] ?? '')));
    }

    /** GET /api/security/host-lookup?q= — staff name and position, to record who a visitor is seeing. */
    public function getHostLookup($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->hostLookup((string) ($this->q($data)['q'] ?? '')));
    }

    /** GET /api/security/incidents — all for the gate team and leadership; own reports for other staff. */
    public function getIncidents($id = null, $data = [], $segments = [])
    {
        if ($id) {
            return $this->run(fn () => $this->svc()->incident((int) $id, (int) $this->getUserId(), $this->roles()), false);
        }
        return $this->run(fn () => $this->svc()->listIncidents($this->q($data), (int) $this->getUserId(), $this->roles()), false);
    }

    /** POST /api/security/incidents — any staff member reports a security incident. */
    public function postIncidents($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->reportIncident((array) $data, (int) $this->getUserId()), false);
    }

    /** POST /api/security/incident-actions/{id} {action_type, notes, police_ob_no} */
    public function postIncidentActions($id = null, $data = [], $segments = [])
    {
        return $this->run(fn () => $this->svc()->actOnIncident((int) $id, (array) $data, (int) $this->getUserId(), $this->roles()), false);
    }
}
