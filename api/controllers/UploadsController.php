<?php

declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Services\DownloadService;
use App\API\Services\UploadService;
use Throwable;

/**
 * Canonical authenticated upload-management controller.
 *
 * The first managed category is public school documents. Other business
 * controllers use UploadService directly until their page-specific workflows
 * are migrated to dedicated /api/uploads/* actions.
 */
final class UploadsController extends BaseController
{

    /**
     * POST /api/uploads/school-document
     */
    public function postSchoolDocument(
        $id = null,
        $data = [],
        $segments = []
    ) {
        if (!$this->hasPermission('website_downloads_manage')) {
            return $this->forbidden(
                'Insufficient permission to upload school documents.'
            );
        }

        try {
            $stored = $this->uploadManaged(
                $_FILES['file'] ?? [],
                'school_document',
                [
                    'prefix' => 'school_document',
                    'preferred_name' => (string) (
                        $data['title'] ?? 'school_document'
                    ),
                ]
            );

            $token = $this->downloads()->createPublicToken();

            return $this->created([
                'storage_filename' => $stored['storage_filename'],
                'original_filename' => $stored['original_filename'],
                'mime_type' => $stored['mime_type'],
                'file_size_bytes' => $stored['file_size_bytes'],
                'file_size' => $stored['file_size'],
                'public_token' => $token,
                'download_url' =>
                    $this->downloads()->publicDownloadUrl($token),
            ], 'School document uploaded successfully.');
        } catch (Throwable $exception) {
            return $this->unprocessable(
                $exception->getMessage()
            );
        }
    }

    private function hasPermission(string $permission): bool
    {
        $permissions = (array) (
            $this->user['effective_permissions'] ?? []
        );

        return in_array($permission, $permissions, true)
            || in_array(
                str_replace('_', '.', $permission),
                $permissions,
                true
            );
    }
}
