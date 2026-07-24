<?php

declare(strict_types=1);

namespace App\API\Core;

use App\API\Services\DownloadService;
use App\API\Services\PrintService;
use App\API\Services\UploadService;

/**
 * Single inherited gateway for every controller/API file lifecycle operation.
 *
 * Child controllers never perform filesystem upload, write, delete, stream,
 * download-header or printable/export work directly. They inherit these
 * methods from BaseController or BaseAPI through this class.
 */
abstract class FileLifecycleBase
{
    private ?UploadService $canonicalUploadService = null;
    private ?DownloadService $canonicalDownloadService = null;
    private ?PrintService $canonicalPrintService = null;

    final protected function uploads(): UploadService
    {
        return $this->canonicalUploadService ??= new UploadService();
    }

    final protected function downloads(): DownloadService
    {
        return $this->canonicalDownloadService ??= new DownloadService();
    }

    final protected function prints(): PrintService
    {
        return $this->canonicalPrintService ??= new PrintService();
    }

    final protected function uploadManaged(
        array $file,
        string $category,
        array $options = []
    ): array {
        return $this->uploads()->store($file, $category, $options);
    }

    final protected function uploadLegacyCompatible(
        array $file,
        string $destination,
        array $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf']
    ): string {
        return $this->uploads()->storeLegacy(
            $file,
            $destination,
            $allowedTypes
        );
    }

    final protected function writeManagedFile(
        string $path,
        mixed $contents,
        int $flags = 0
    ): int|false {
        return $this->uploads()->writeFile($path, $contents, $flags);
    }

    final protected function deleteManagedFile(string $path): bool
    {
        return $this->uploads()->deleteFile($path);
    }

    final protected function managedPath(
        string $category,
        string ...$segments
    ): string {
        return $this->uploads()->path($category, ...$segments);
    }

    final protected function streamManagedFile(
        string $absolutePath,
        ?string $filename = null,
        ?string $mimeType = null,
        string $disposition = 'attachment'
    ): never {
        $this->downloads()->streamAbsolutePath(
            $absolutePath,
            $filename,
            $mimeType,
            $disposition
        );
    }

    final protected function generatedDownloadUrl(
        string $absolutePath,
        bool $inline = false,
        ?int $ttl = null
    ): string {
        return $inline
            ? $this->downloads()->printUrlForAbsolutePath($absolutePath, $ttl)
            : $this->downloads()->generatedDownloadUrlForAbsolutePath($absolutePath, $ttl);
    }

    final protected function writePrintable(
        string $filename,
        string $contents
    ): string {
        return $this->prints()->writeGeneratedFile($filename, $contents);
    }
    final protected function managedDirectory(
        string $category,
        string ...$segments
    ): string {
        return $this->uploads()->categoryDirectory($category, ...$segments);
    }

    final protected function managedPublicUrl(
        string $category,
        string ...$segments
    ): string {
        return $this->uploads()->categoryPublicUrl($category, ...$segments);
    }

    final protected function copyManagedFile(
        string $sourcePath,
        string $targetPath
    ): bool {
        return $this->uploads()->copyFile($sourcePath, $targetPath);
    }

    final protected function ensureManagedDirectory(string $directory): string
    {
        return $this->uploads()->ensureDirectoryPath($directory);
    }

    final protected function moveManagedFile(
        string $sourcePath,
        string $targetPath
    ): bool {
        return $this->uploads()->moveFile($sourcePath, $targetPath);
    }

    final protected function readManagedFile(string $path): string|false
    {
        return $this->uploads()->readFile($path);
    }

    final protected function atomicWriteManagedFile(
        string $path,
        string $contents
    ): bool {
        return $this->uploads()->atomicWrite($path, $contents);
    }

    final protected function managedMediaUrl(
        string $context,
        string|int|null $entityId,
        string $filename,
        string|int|null $albumId = null
    ): string {
        return $this->uploads()->mediaPublicUrl(
            $context,
            $entityId,
            $filename,
            $albumId
        );
    }

    final protected function publicUploadAssetUrl(string ...$segments): string
    {
        return $this->uploads()->publicUploadUrl(...$segments);
    }

}
