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
        $this->service = $this->contract('App\API\Services\StaffMigrationService', $this->db->getConnection());
    }

    public function getReferenceData($id = null, $data = [], $segments = [])
    {
        // Read-only master/reference data (departments, staff types, staff
        // categories, learning areas, leadership positions, supervisors) that
        // the staff form needs to render populated dropdowns. Gating this on
        // `staff_import` alone left every other staff-facing role with empty
        // selects, so the lookup follows `staff_view`. This grants no import
        // capability: every mutating endpoint below still requires
        // `staff_import`.
        return $this->respondWithGuard(['staff_view', 'staff_import'], fn() => $this->success($this->service->referenceData()));
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

        $path = $this->managedPath('import_file', 'templates', 'existing_staff_import_template.csv');
        if (!$this->atomicWriteManagedFile($path, $this->service->templateCsv())) {
            throw new RuntimeException('Unable to prepare staff import template.');
        }
        $this->downloads()->streamAbsolutePath($path, 'existing_staff_import_template.csv', 'text/csv; charset=utf-8');
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

    public function getTemplateOds($id = null, $data = [], $segments = []): never
    {
        $this->guard('staff_import');
        $path = $this->managedPath('import_file', 'templates', 'existing_staff_import_template.ods');
        $this->ensureManagedDirectory(dirname($path));
        $this->service->writeTemplateOds($path);
        $this->downloads()->streamAbsolutePath(
            $path,
            'existing_staff_import_template.ods',
            'application/vnd.oasis.opendocument.spreadsheet'
        );
    }

    public function postStage($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_import', function () {
            if (empty($_FILES['file'])) {
                throw new RuntimeException('CSV, Excel, or ODS file is required.');
            }

            $stored = $this->uploadManaged($_FILES['file'], 'import_file', [
                'subdirectory' => 'files',
                'allowed_extensions' => ['csv', 'xlsx', 'xls', 'ods'],
                'allowed_mime_types' => [
                    'text/csv',
                    'text/plain',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.oasis.opendocument.spreadsheet',
                    'application/octet-stream',
                ],
            ]);

            $extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $csv = in_array($extension, ['xlsx', 'xls', 'ods'], true)
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
            $invitation = $this->service->resendInvitation($userId, $this->actorId());
            $delivery = $this->service->processEmailQueue(1, (int)($invitation['message_id'] ?? 0));
            $invitation['email_sent'] = (int)($delivery['sent'] ?? 0) === 1;
            return $this->success(
                $invitation,
                $invitation['email_sent'] ? 'Invitation email sent.' : 'Invitation is queued for email delivery.'
            );
        });
    }

    public function postResendSetupOtp($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_invitation_resend', function () use ($id, $data) {
            $userId = (int)($data['user_id'] ?? $id ?? 0);
            if (!$userId) throw new RuntimeException('user_id is required.');
            return $this->success(
                $this->service->resendSetupOtp($userId, $this->actorId()),
                'A new staff setup verification code was sent.'
            );
        });
    }

    /** Internal retry worker for the staff invitation outbox. */
    public function postProcessInvitationEmail($id = null, $data = [], $segments = [])
    {
        $expected = defined('COMMUNICATION_WORKER_SECRET') ? (string)COMMUNICATION_WORKER_SECRET : '';
        $provided = (string)($_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return $this->unauthorized('Invalid worker credentials.');
        }
        $limit = max(1, min(100, (int)($data['limit'] ?? 20)));
        return $this->runSafely(fn() => $this->success($this->service->processEmailQueue($limit)));
    }

    public function postCancelInvitation($id = null, $data = [], $segments = [])
    {
        return $this->respondWithGuard('staff_invitation_resend', function () use ($id, $data) {
            $userId = (int)($data['user_id'] ?? $id ?? 0);
            if (!$userId) throw new RuntimeException('user_id is required.');
            return $this->success(
                $this->service->cancelInvitation($userId, $this->actorId()),
                'Staff invitation cancelled.'
            );
        });
    }

    public function getOnboarding($id = null, $data = [], $segments = [])
    {
        return $this->runSafely(fn() => $this->success($this->service->onboardingForUser($this->actorId())));
    }

    public function putProfile($id = null, $data = [], $segments = [])
    {
        return $this->runSafely(function () use ($data) {
            return $this->success($this->service->completeProfile($this->actorId(), $data), 'Profile completed.');
        });
    }

    private function respondWithGuard(string|array $permission, callable $callback)
    {
        return $this->runSafely(function () use ($permission, $callback) {
            $this->guard($permission);
            return $callback();
        });
    }

    private function runSafely(callable $callback)
    {
        try {
            return $callback();
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[StaffMigrationController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); if ($e->getCode() === 401) { return $this->unauthorized($e->getMessage()); } if ($e->getCode() === 403) { return $this->forbidden($e->getMessage()); } return $this->badRequest($e->getMessage()); } catch (Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffMigrationController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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

    private function guard(string|array $permission): void
    {
        if (!$this->user) {
            throw new RuntimeException('Authentication required.', 401);
        }

        $roles = array_map(
            fn($role) => strtolower(str_replace(' ', '_', $this->roleName($role))),
            (array)($this->user['roles'] ?? [$this->user['role'] ?? ''])
        );
        $permissions = (array)($this->user['permissions'] ?? []);
        $required = (array)$permission;

        if (
            !array_intersect($roles, ['school_administrator', 'school_admin', 'admin'])
            && !array_intersect($required, $permissions)
        ) {
            throw new RuntimeException(
                count($required) === 1
                    ? sprintf('%s permission is required.', $required[0])
                    : sprintf('One of these permissions is required: %s.', implode(', ', $required)),
                403
            );
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
