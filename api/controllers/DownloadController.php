<?php

declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Services\DownloadService;
use Throwable;

/**
 * Canonical file-delivery controller.
 *
 * Public token routes:
 * - GET /api/download/public?token=...
 * - GET /api/download/print?token=...
 * - GET /api/download/generated?token=...
 */
final class DownloadController extends BaseController
{

    public function getPublic(
        $id = null,
        $data = [],
        $segments = []
    ): never {
        $this->deliverPublic();
    }

    public function getPrint(
        $id = null,
        $data = [],
        $segments = []
    ): never {
        $this->deliverTemporary('print', 'inline');
    }

    public function getGenerated(
        $id = null,
        $data = [],
        $segments = []
    ): never {
        $this->deliverTemporary(
            'generated_download',
            'attachment'
        );
    }


    /** POST /api/download/export */
    public function postExport($id = null, $data = [], $segments = [])
    {
        $filename = trim((string) ($data['filename'] ?? 'export.csv'));
        $content = (string) ($data['content'] ?? '');
        if ($filename === '' || $content === '') {
            return $this->badRequest('Export filename and content are required.');
        }
        if (strlen($content) > 20 * 1024 * 1024) {
            return $this->unprocessable('Export content exceeds 20 MB.');
        }
        try {
            $path = $this->writePrintable($filename, $content);
            return $this->success([
                'download_url' => $this->generatedDownloadUrl($path),
            ], 'Export generated.');
        } catch (\Throwable $exception) {
            return $this->serverError('Export generation failed.', $exception->getMessage());
        }
    }

    private function deliverPublic(): never
    {
        try {
            $file = $this->downloads()->resolvePublicDocument(
                trim((string) ($_GET['token'] ?? ''))
            );
            $this->stream($file, 'attachment');
        } catch (Throwable $exception) {
            $this->abortDownload($exception);
        }
    }

    private function deliverTemporary(
        string $purpose,
        string $disposition
    ): never {
        try {
            $file = $this->downloads()->resolveTemporary(
                trim((string) ($_GET['token'] ?? '')),
                $purpose
            );
            $this->stream($file, $disposition);
        } catch (Throwable $exception) {
            $this->abortDownload($exception);
        }
    }

    /** @param array<string,mixed> $file */
    private function stream(array $file, string $disposition): never
    {
        $this->downloads()->streamResolved($file, $disposition);
    }


    private function abortDownload(Throwable $exception): never
    {
        error_log('[DownloadController] ' . $exception->getMessage());

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $expired = str_contains(
            strtolower($exception->getMessage()),
            'expired'
        );

        http_response_code($expired ? 410 : 404);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => $exception->getMessage(),
            'data' => null,
            'code' => $expired ? 410 : 404,
        ], JSON_UNESCAPED_SLASHES);

        exit;
    }
}
