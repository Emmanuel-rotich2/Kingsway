<?php
namespace App\API\Modules\admission;

use PDO;

class AdmissionStageAuthorization
{
    private PDO $db;
    private int $workflowId;
    private array $matrixCache = [];

    public function __construct(PDO $db, int $workflowId)
    {
        $this->db = $db;
        $this->workflowId = $workflowId;
    }

    public function getStageMatrix(array $roleIds, array $permissionCodes): array
    {
        $cacheKey = implode(',', $roleIds) . '|' . implode(',', $permissionCodes);
        if (isset($this->matrixCache[$cacheKey])) {
            return $this->matrixCache[$cacheKey];
        }

        $stages = $this->loadStages();
        $permissions = $this->loadStagePermissions($roleIds);
        $permissionLookup = array_flip($permissionCodes);

        foreach ($stages as $code => &$stage) {
            $stage['can_view'] = false;
            $stage['can_act'] = false;
            $stage['actions'] = [];
            $stage['responsible_roles'] = [];

            foreach ($permissions[$code] ?? [] as $row) {
                $permissionCode = (string) ($row['permission_code'] ?? '');
                $hasPermission = $permissionCode === '' || isset($permissionLookup[$permissionCode]) || isset($permissionLookup['*']);
                if (!$hasPermission) {
                    continue;
                }

                $canView = (int) ($row['can_view'] ?? 0) === 1;
                $canProcess = (int) ($row['can_process'] ?? 0) === 1;
                $canApprove = (int) ($row['can_approve'] ?? 0) === 1;

                $stage['can_view'] = $stage['can_view'] || $canView || $canProcess || $canApprove;
                $stage['can_act'] = $stage['can_act'] || $canProcess || $canApprove;

                if ($permissionCode !== '') {
                    $stage['actions'][] = $permissionCode;
                }
                if (!empty($row['role_name'])) {
                    $stage['responsible_roles'][] = $row['role_name'];
                }
            }

            $stage['actions'] = array_values(array_unique($stage['actions']));
            $stage['responsible_roles'] = array_values(array_unique($stage['responsible_roles']));
        }
        unset($stage);

        return $this->matrixCache[$cacheKey] = $stages;
    }

    public function canAct(string $stageCode, array $permissionCandidates, array $roleIds, array $permissionCodes): bool
    {
        $matrix = $this->getStageMatrix($roleIds, $permissionCodes);
        $stage = $matrix[$stageCode] ?? null;
        if (!$stage || !$stage['can_act']) {
            return false;
        }

        if (isset(array_flip($permissionCodes)['*'])) {
            return true;
        }

        return count(array_intersect($permissionCandidates, $stage['actions'], $permissionCodes)) > 0;
    }

    public function canView(string $stageCode, array $roleIds, array $permissionCodes): bool
    {
        $matrix = $this->getStageMatrix($roleIds, $permissionCodes);
        return !empty($matrix[$stageCode]['can_view']);
    }

    private function loadStages(): array
    {
        $stmt = $this->db->prepare("SELECT id, code, name, sequence, allowed_transitions FROM workflow_stages WHERE workflow_id = :workflow_id AND is_active = 1 ORDER BY sequence, id");
        $stmt->execute(['workflow_id' => $this->workflowId]);

        $stages = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $code = (string) $row['code'];
            $stages[$code] = [
                'id' => (int) $row['id'],
                'code' => $code,
                'label' => $row['name'] ?: ucwords(str_replace('_', ' ', $code)),
                'sequence' => (int) ($row['sequence'] ?? 0),
                'allowed_transitions' => $row['allowed_transitions'] ? json_decode($row['allowed_transitions'], true) : [],
            ];
        }

        return $stages;
    }

    private function loadStagePermissions(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $sql = "SELECT ws.code AS stage_code,
                       p.code AS permission_code,
                       r.name AS role_name,
                       wsp.can_view,
                       wsp.can_process,
                       wsp.can_approve
                FROM workflow_stage_permissions wsp
                JOIN workflow_stages ws ON ws.id = wsp.workflow_stage_id
                LEFT JOIN permissions p ON p.id = wsp.permission_id
                LEFT JOIN roles r ON r.id = wsp.role_id
                WHERE ws.workflow_id = ?
                  AND (wsp.role_id IS NULL OR wsp.role_id IN ($placeholders))";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$this->workflowId], array_map('intval', $roleIds)));

        $permissions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $permissions[$row['stage_code']][] = $row;
        }

        return $permissions;
    }
}
