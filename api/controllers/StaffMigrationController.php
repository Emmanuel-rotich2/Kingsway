<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Services\StaffMigrationService;
use RuntimeException;
use Throwable;

final class StaffMigrationController extends BaseController
{
    private StaffMigrationService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new StaffMigrationService($this->db->getConnection());
    }

    public function getReferenceData($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_import', fn() => $this->success($this->service->referenceData()));
    }

    public function getBatches($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_import', function () {
            return $this->success($this->service->batches((int)($_GET['limit'] ?? 50)));
        });
    }

    public function getBatch($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_import', function () use ($id) {
            $batchId = (int)($id ?? $_GET['id'] ?? 0);
            if (!$batchId) {
                return $this->badRequest('Batch ID is required.');
            }
            return $this->success($this->service->batchDetail($batchId));
        });
    }

    public function getTemplate($id = null, $data = [], $segments = []): never
    {
        $this->guard('staff_import');

        $path = $this->managedPath('import_file', 'templates', 'existing_staff_migration_template.csv');
        if (!$this->atomicWriteManagedFile($path, $this->service->templateCsv())) {
            throw new RuntimeException('Unable to prepare staff import template.');
        }
        $this->downloads()->streamAbsolutePath($path, 'existing_staff_migration_template.csv', 'text/csv; charset=utf-8');
    }

    public function getTemplateXlsx($id = null, $data = [], $segments = []): never
    {
        $this->guard('staff_import');

        $path = $this->managedPath('import_file', 'templates', 'existing_staff_migration_template.xlsx');
        $this->ensureManagedDirectory(dirname($path));
        $this->service->writeTemplateXlsx($path);
        $this->downloads()->streamAbsolutePath(
            $path,
            'existing_staff_migration_template.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function postStage($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_import', function () {
            if (empty($_FILES['file'])) {
                throw new RuntimeException('CSV or Excel file is required.');
            }

            $stored = $this->uploadManaged($_FILES['file'], 'import_file', [
                'subdirectory' => 'staff_migration',
                'allowed_extensions' => ['csv', 'xlsx', 'xls'],
                'allowed_mime_types' => [
                    'text/csv',
                    'text/plain',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/octet-stream',
                ],
            ]);

            $extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $csv = in_array($extension, ['xlsx', 'xls'], true)
                ? $this->service->spreadsheetToCsv($stored['absolute_path'])
                : $this->readManagedFile($stored['absolute_path']);
            if ($csv === false) {
                throw new RuntimeException('Uploaded file could not be read.');
            }

            return $this->created(
                $this->service->stage($_FILES['file']['name'], $stored['absolute_path'], $csv, $this->actorId()),
                'Staff import file staged and validated.'
            );
        });
    }

    public function postCommit($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_import', function () use ($id, $data) {
            $batchId = (int)($data['batch_id'] ?? $id ?? 0);
            if (!$batchId) {
                throw new RuntimeException('batch_id is required.');
            }
            return $this->created(
                $this->service->commit($batchId, $this->actorId()),
                'Existing staff imported atomically and invitations queued.'
            );
        });
    }

    public function postRollback($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_import_rollback', function () use ($id, $data) {
            $batchId = (int)($data['batch_id'] ?? $id ?? 0);
            if (!$batchId) {
                throw new RuntimeException('batch_id is required.');
            }
            return $this->success(
                $this->service->rollback($batchId, $this->actorId()),
                'Import batch rolled back.'
            );
        });
    }

    public function postResendInvitation($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_invitation_resend', function () use ($id, $data) {
            $userId = (int)($data['user_id'] ?? $id ?? 0);
            if (!$userId) {
                throw new RuntimeException('user_id is required.');
            }
            $baseUrl = $data['base_url'] ?? (defined('APP_URL') ? APP_URL : '');
            return $this->success(
                $this->service->resendInvitation($userId, $this->actorId(), $baseUrl),
                'Invitation queued again.'
            );
        });
    }

    public function getOnboarding($id = null, $data = [], $segments = [])
    {
        return $this->respond(fn() => $this->success($this->service->onboardingForUser($this->actorId())));
    }

    public function putProfile($id = null, $data = [], $segments = [])
    {
        return $this->respond(function () use ($data) {
            return $this->success($this->service->completeProfile($this->actorId(), $data), 'Profile completed.');
        });
    }

    private function respondWithGuard(string $permission, callable $callback)
    {
        return $this->respond(function () use ($permission, $callback) {
            $this->guard($permission);
            return $callback();
        });
    }

    private function respond(callable $callback)
    {
        try {
            return $callback();
        } catch (RuntimeException $e) {
            return $this->unprocessable($e->getMessage());
        } catch (Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }

    private function actorId(): int
    {
        $id = (int)($this->user['id'] ?? $this->user['user_id'] ?? 0);
        if (!$id) {
            throw new RuntimeException('Authenticated user context is required.');
        }
        return $id;
    }

    private function guard(string $permission): void
    {
        if (!$this->user) {
            throw new RuntimeException('Authentication required.');
        }

        $roles = array_map(
            fn($role) => strtolower(str_replace(' ', '_', $this->roleName($role))),
            (array)($this->user['roles'] ?? [$this->user['role'] ?? ''])
        );
        $permissions = (array)($this->user['permissions'] ?? []);

        if (
            !array_intersect($roles, ['school_administrator', 'school_admin', 'admin'])
            && !in_array($permission, $permissions, true)
        ) {
            throw new RuntimeException('School Administrator permission is required.');
        }
    }

    private function roleName(mixed $role): string
    {
        if (is_array($role)) {
            return (string)($role['name'] ?? $role['role_name'] ?? $role['code'] ?? '');
        }

        if (is_object($role)) {
            return (string)($role->name ?? $role->role_name ?? $role->code ?? '');
        }

        return (string)$role;
    }
}
