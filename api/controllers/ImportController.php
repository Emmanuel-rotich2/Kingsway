<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\Import\DataImporter;
use Exception;

/**
 * ImportController — bulk data import for all school modules.
 *
 * Routes (URL segment: /api/import/...):
 *   POST  /api/import/preview   — parse file, return first 10 rows + validation errors
 *   POST  /api/import/execute   — run full import, return counts + per-row errors
 *   GET   /api/import/template  — download CSV template (?type=students)
 *   GET   /api/import/types     — list all supported import types
 *   GET   /api/import/logs      — import history
 *   GET   /api/import/log/{id}  — specific import detail
 */
class ImportController extends BaseController
{
    private DataImporter $importer;

    public function __construct()
    {
        parent::__construct();
        $this->importer = new DataImporter($this->db);
    }

    // ── GET /api/import/types ───────────────────────────────────────────────
    public function getTypes($id = null, $data = [], $segments = []): array
    {
        $grouped = [];
        foreach (DataImporter::TYPES as $key => $meta) {
            $grouped[$meta['category']][] = [
                'type'     => $key,
                'label'    => $meta['label'],
                'required' => $meta['required'],
                'category' => $meta['category'],
            ];
        }
        return $this->success($grouped, 'Import types loaded');
    }

    // ── POST /api/import/preview ────────────────────────────────────────────
    public function postPreview($id = null, $data = [], $segments = []): array
    {
        $this->requirePermission(['import_data', 'data_import', 'admin', 'system_admin']);
        $type = $_POST['type'] ?? $data['type'] ?? '';
        $file = $_FILES['file'] ?? null;

        if (!$type) {
            return $this->error('Import type is required', 400);
        }
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->error('No file uploaded or upload error', 400);
        }

        try {
            $result = $this->importer->preview($type, $file);
            return $this->success($result, 'Preview generated');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ── POST /api/import/execute ────────────────────────────────────────────
    public function postExecute($id = null, $data = [], $segments = []): array
    {
        $this->requirePermission(['import_data', 'data_import', 'admin', 'system_admin']);
        $type = $_POST['type'] ?? $data['type'] ?? '';
        $file = $_FILES['file'] ?? null;

        if (!$type) {
            return $this->error('Import type is required', 400);
        }
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->error('No file uploaded or upload error', 400);
        }

        $userId = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);

        try {
            $result = $this->importer->execute($type, $file, $userId);
            $code   = $result['status'] === 'failed' ? 422 : 200;
            return $this->formatResponse(
                $result['status'] === 'completed' ? 'success' : 'partial',
                $result,
                "Import {$result['status']}: {$result['success_rows']} rows imported",
                $code
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // ── GET /api/import/template ────────────────────────────────────────────
    public function getTemplate($id = null, $data = [], $segments = []): void
    {
        $type = $_GET['type'] ?? $segments[0] ?? '';
        $path = $this->importer->getTemplateFile($type);

        if (!$path) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => "No template for type '$type'"]);
            return;
        }

        $label = DataImporter::TYPES[$type]['label'] ?? $type;
        $filename = str_replace([' ', '/'], '_', strtolower($label)) . '_template.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Content-Length: ' . filesize($path));
        header('Pragma: no-cache');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        readfile($path);
        exit;
    }

    // ── GET /api/import/logs ────────────────────────────────────────────────
    public function getLogs($id = null, $data = [], $segments = []): array
    {
        $this->requirePermission(['import_data', 'data_import', 'admin', 'system_admin', 'reports_view']);
        try {
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            $logs  = $this->importer->getLogs($limit);
            return $this->success($logs, 'Import logs loaded');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // ── GET /api/import/log/{id} ────────────────────────────────────────────
    public function getLog($id = null, $data = [], $segments = []): array
    {
        $this->requirePermission(['import_data', 'data_import', 'admin', 'system_admin']);
        $logId = (int)($id ?? $segments[0] ?? 0);
        if (!$logId) return $this->error('Log ID required', 400);

        try {
            $log = $this->importer->getLog($logId);
            return $log ? $this->success($log) : $this->error('Not found', 404);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function requirePermission(array $perms): void
    {
        if (!$this->user) {
            http_response_code(401);
            throw new Exception('Authentication required');
        }
        $userPerms = $this->user['permissions'] ?? [];
        $roles     = $this->user['roles'] ?? [$this->user['role'] ?? ''];

        // Super admin always passes
        if (in_array('system_admin', $roles, true) || in_array('admin', $roles, true)) return;
        foreach ($perms as $p) {
            if (in_array($p, $userPerms, true)) return;
        }
        // Allow by role name too
        foreach ($roles as $r) {
            if (in_array(strtolower((string)$r), ['admin','director','principal','accountant'], true)) return;
        }
        http_response_code(403);
        throw new Exception('Insufficient permissions for data import');
    }

    private function error(string $msg, int $code = 400): array
    {
        http_response_code($code);
        return $this->formatResponse('error', null, $msg, $code);
    }
}
