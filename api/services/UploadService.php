<?php

declare(strict_types=1);

namespace App\API\Services;

use RuntimeException;

/**
 * Canonical filesystem upload service.
 *
 * All backend upload workflows should delegate validation, destination
 * selection, safe naming, replacement and deletion to this service.
 */
final class UploadService
{
    private const MAX_DEFAULT_BYTES = 15728640; // 15 MB

    /**
     * @var array<string,array{
     *   root:string,
     *   public_segment:?string,
     *   extensions:list<string>,
     *   mime_types:list<string>,
     *   max_bytes:int
     * }>
     */
    private array $categories;

    public function __construct()
    {
        $this->categories = [
            'school_document' => [
                'root' => (string) SCHOOL_ASSETS_DOCUMENTS,
                'public_segment' => null,
                'extensions' => [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                    'csv', 'txt',
                ],
                'mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/csv',
                    'text/plain',
                    'application/octet-stream',
                ],
                'max_bytes' => 26214400,
            ],
            'student_document' => [
                'root' => (string) STUDENT_DOCUMENTS,
                'public_segment' => 'students/documents',
                'extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png',
                    'application/octet-stream',
                ],
                'max_bytes' => self::MAX_DEFAULT_BYTES,
            ],
            'admission_document' => [
                'root' => (string) ADMISSION_DOCUMENTS,
                'public_segment' => 'students/admissions/documents',
                'extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png',
                    'application/octet-stream',
                ],
                'max_bytes' => self::MAX_DEFAULT_BYTES,
            ],
            'staff_document' => [
                'root' => (string) STAFF_DOCUMENTS,
                'public_segment' => 'staff/documents',
                'extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png',
                    'application/octet-stream',
                ],
                'max_bytes' => self::MAX_DEFAULT_BYTES,
            ],
            'student_photo' => [
                'root' => (string) STUDENT_IMAGES,
                'public_segment' => 'students/images',
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_bytes' => 5242880,
            ],
            'staff_photo' => [
                'root' => (string) STAFF_PHOTOS,
                'public_segment' => 'staff/profile_pictures',
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_bytes' => 5242880,
            ],
            'teaching_material' => [
                'root' => (string) UPLOAD_PATH . '/teaching_materials',
                'public_segment' => 'teaching_materials',
                'extensions' => [
                    'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
                    'csv', 'jpg', 'jpeg', 'png', 'zip',
                ],
                'mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv',
                    'image/jpeg',
                    'image/png',
                    'application/zip',
                    'application/octet-stream',
                ],
                'max_bytes' => 52428800,
            ],
            'academic_assessment' => [
                'root' => (string) ACADEMIC_ASSESSMENTS,
                'public_segment' => 'academic/assessments',
                'extensions' => [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv',
                ],
                'mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv',
                    'application/octet-stream',
                ],
                'max_bytes' => 26214400,
            ],
            'career_cv' => [
                'root' => (string) UPLOAD_PATH . '/cvs',
                'public_segment' => null,
                'extensions' => ['pdf', 'doc', 'docx'],
                'mime_types' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'],
                'max_bytes' => 10485760,
            ],
            'communication_attachment' => [
                'root' => (string) UPLOAD_PATH . '/communications',
                'public_segment' => null,
                'extensions' => ['pdf','doc','docx','xls','xlsx','csv','txt','jpg','jpeg','png','zip'],
                'mime_types' => ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/csv','text/plain','image/jpeg','image/png','application/zip','application/octet-stream'],
                'max_bytes' => 26214400,
            ],
            'import_file' => [
                'root' => (string) UPLOAD_PATH . '/imports',
                'public_segment' => null,
                'extensions' => ['csv','xls','xlsx'],
                'mime_types' => ['text/csv','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/octet-stream'],
                'max_bytes' => 26214400,
            ],
            'system_storage' => [
                'root' => dirname((string) UPLOAD_PATH) . '/storage',
                'public_segment' => null,
                'extensions' => [],
                'mime_types' => [],
                'max_bytes' => 0,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $file
     * @param array{
     *   owner_id?:int|string|null,
     *   prefix?:string,
     *   preferred_name?:string,
     *   replace_path?:string|null
     * } $options
     *
     * @return array<string,mixed>
     */
    public function store(
        array $file,
        string $category,
        array $options = []
    ): array {
        $policy = $this->policy($category);
        $this->assertValidUpload($file, $policy);

        $ownerId = $this->safePathSegment(
            (string) ($options['owner_id'] ?? '')
        );

        $destinationDirectory = rtrim($policy['root'], '/\\');
        if ($ownerId !== '') {
            $destinationDirectory .= DIRECTORY_SEPARATOR . $ownerId;
        }

        $this->ensureDirectory($destinationDirectory);

        $originalName = basename((string) ($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $preferredName = trim((string) ($options['preferred_name'] ?? ''));
        $base = $preferredName !== ''
            ? pathinfo($preferredName, PATHINFO_FILENAME)
            : pathinfo($originalName, PATHINFO_FILENAME);

        $prefix = trim((string) ($options['prefix'] ?? ''));
        $safeBase = $this->safeFilenamePart(
            ($prefix !== '' ? $prefix . '_' : '') . $base
        );

        $storageName = $safeBase
            . '_'
            . date('Ymd_His')
            . '_'
            . bin2hex(random_bytes(4))
            . ($extension !== '' ? '.' . $extension : '');

        $destinationPath = $destinationDirectory
            . DIRECTORY_SEPARATOR
            . $storageName;

        if (!move_uploaded_file(
            (string) $file['tmp_name'],
            $destinationPath
        )) {
            throw new RuntimeException('Unable to store the uploaded file.');
        }

        @chmod($destinationPath, 0664);

        $replacePath = trim((string) ($options['replace_path'] ?? ''));
        if ($replacePath !== '') {
            $this->deleteAbsolutePath($replacePath, $policy['root']);
        }

        $mimeType = $this->detectMimeType($destinationPath);
        $size = filesize($destinationPath);

        return [
            'category' => $category,
            'absolute_path' => $destinationPath,
            'storage_filename' => $storageName,
            'original_filename' => $originalName,
            'mime_type' => $mimeType,
            'file_size_bytes' => $size === false ? 0 : (int) $size,
            'file_size' => $this->humanFileSize(
                $size === false ? 0 : (int) $size
            ),
            'relative_path' => $this->relativePath($destinationPath),
            'application_path' => 'uploads/' . $this->relativePath($destinationPath),
            'url' => $this->browserUrl(
                $policy['public_segment'],
                $ownerId,
                $storageName
            ),
        ];
    }

    public function ensureCategoryDirectory(
        string $category,
        string|int|null $ownerId = null
    ): string {
        $policy = $this->policy($category);
        $path = rtrim($policy['root'], '/\\');
        $segment = $this->safePathSegment((string) ($ownerId ?? ''));

        if ($segment !== '') {
            $path .= DIRECTORY_SEPARATOR . $segment;
        }

        $this->ensureDirectory($path);
        return $path;
    }

    public function deleteAbsolutePath(
        string $path,
        ?string $expectedRoot = null
    ): void {
        $path = trim($path);
        if ($path === '' || !is_file($path)) {
            return;
        }

        $root = $expectedRoot ?? (string) UPLOAD_PATH;
        $resolvedRoot = realpath($root);
        $resolvedFile = realpath($path);

        if (
            $resolvedRoot === false
            || $resolvedFile === false
            || !$this->isWithin($resolvedFile, $resolvedRoot)
        ) {
            throw new RuntimeException(
                'Refusing to delete a file outside the configured upload root.'
            );
        }

        if (!unlink($resolvedFile) && is_file($resolvedFile)) {
            throw new RuntimeException('Unable to remove the replaced file.');
        }
    }

    /**
     * Convert a canonical uploads-relative path back to an absolute path.
     */
    public function absolutePath(string $storedPath): string
    {
        $storedPath = trim(str_replace('\\', '/', $storedPath));

        foreach ([
            rtrim((string) UPLOAD_URL, '/') . '/',
            rtrim((string) BASE_URL, '/') . '/uploads/',
            '/uploads/',
            'uploads/',
        ] as $prefix) {
            if (str_starts_with($storedPath, $prefix)) {
                $storedPath = substr($storedPath, strlen($prefix));
                break;
            }
        }

        $storedPath = ltrim($storedPath, '/');
        if (
            $storedPath === ''
            || str_contains($storedPath, '..')
            || str_contains($storedPath, "\0")
        ) {
            throw new RuntimeException('Invalid stored upload path.');
        }

        return rtrim((string) UPLOAD_PATH, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
    }

    /** @return array<string,mixed> */
    private function policy(string $category): array
    {
        if (!isset($this->categories[$category])) {
            throw new RuntimeException(
                sprintf('Unsupported upload category: %s', $category)
            );
        }

        return $this->categories[$category];
    }

    /** @param array<string,mixed> $policy */
    private function assertValidUpload(array $file, array $policy): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                $this->uploadErrorMessage($error)
            );
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('The uploaded file is invalid.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > (int) $policy['max_bytes']) {
            throw new RuntimeException(
                'The uploaded file exceeds the allowed size.'
            );
        }

        $extension = strtolower(
            pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)
        );
        if (!in_array($extension, $policy['extensions'], true)) {
            throw new RuntimeException(
                sprintf('File extension .%s is not allowed.', $extension)
            );
        }

        $mime = $this->detectMimeType($tmpName);
        if (!in_array($mime, $policy['mime_types'], true)) {
            throw new RuntimeException(
                sprintf('File type %s is not allowed.', $mime)
            );
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            if (!is_writable($directory)) {
                throw new RuntimeException(
                    sprintf('Upload directory is not writable: %s', $directory)
                );
            }
            return;
        }

        $parent = dirname($directory);
        if (!is_dir($parent)) {
            $this->ensureDirectory($parent);
        }

        if (
            !mkdir($directory, 0775)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                sprintf('Unable to create upload directory: %s', $directory)
            );
        }
    }

    private function relativePath(string $absolutePath): string
    {
        $root = rtrim((string) UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR;
        if (!str_starts_with($absolutePath, $root)) {
            throw new RuntimeException(
                'Stored file is outside the configured upload root.'
            );
        }

        return str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            substr($absolutePath, strlen($root))
        );
    }

    private function browserUrl(
        ?string $publicSegment,
        string $ownerId,
        string $filename
    ): ?string {
        if ($publicSegment === null) {
            return null;
        }

        $parts = [rtrim((string) UPLOAD_URL, '/'), $publicSegment];
        if ($ownerId !== '') {
            $parts[] = rawurlencode($ownerId);
        }
        $parts[] = rawurlencode($filename);

        return implode('/', $parts);
    }

    private function detectMimeType(string $path): string
    {
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    private function safeFilenamePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($value));
        $value = trim((string) $value, '_-');

        return $value !== '' ? substr($value, 0, 120) : 'file';
    }

    private function safePathSegment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new RuntimeException('Invalid upload owner identifier.');
        }

        return $value;
    }

    private function isWithin(string $path, string $root): bool
    {
        $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        return $path === $root || str_starts_with($path, $normalizedRoot);
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return number_format($value, 1) . ' ' . $unit;
            }
            $value /= 1024;
        }

        return number_format($value, 1) . ' GB';
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file is too large.',
            UPLOAD_ERR_PARTIAL => 'The file upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the file.',
            UPLOAD_ERR_EXTENSION => 'A server extension blocked the upload.',
            default => 'The upload failed.',
        };
    }
    /**
     * Compatibility adapter used only by the inherited parent gateway.
     * Child APIs must call BaseController/BaseAPI::uploadFile instead.
     */
    public function storeLegacy(
        array $file,
        string $destination,
        array $allowedTypes
    ): string {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid upload parameters.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The file upload failed.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('The uploaded file is invalid.');
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes, true)) {
            throw new RuntimeException('File type not allowed.');
        }
        $this->ensureDirectory($destination);
        $filename = bin2hex(random_bytes(12)) . '_' . date('Ymd_His') . '.' . $extension;
        $target = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Failed to store uploaded file.');
        }
        @chmod($target, 0664);
        return $filename;
    }

    /** Centralized write primitive for application-managed files. */
    public function writeFile(string $path, mixed $contents, int $flags = 0): int|false
    {
        $directory = dirname($path);
        $this->ensureDirectory($directory);
        return file_put_contents($path, $contents, $flags);
    }

    /** Centralized delete primitive for application-managed files. */
    public function deleteFile(string $path): bool
    {
        if (!is_file($path)) {
            return true;
        }
        return unlink($path);
    }

    /**
     * Resolve a path under a canonical category without path construction in
     * controllers. Segments are sanitized and traversal is rejected.
     */
    public function path(string $category, string ...$segments): string
    {
        $policy = $this->policy($category);
        $path = rtrim((string) $policy['root'], '/\\');
        foreach ($segments as $segment) {
            $segment = trim(str_replace('\\', '/', $segment), '/');
            if ($segment === '' || str_contains($segment, '..') || str_contains($segment, "\\0")) {
                throw new RuntimeException('Invalid managed path segment.');
            }
            foreach (explode('/', $segment) as $part) {
                if ($part === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $part)) {
                    throw new RuntimeException('Invalid managed path segment.');
                }
                $path .= DIRECTORY_SEPARATOR . $part;
            }
        }
        return $path;
    }

    /** Internal adapter for legacy service destinations during migration. */
    public function moveUploadedTo(array $file, string $targetPath): bool
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded file.');
        }
        $this->ensureDirectory(dirname($targetPath));
        $moved = move_uploaded_file($tmp, $targetPath);
        if ($moved) {
            @chmod($targetPath, 0664);
        }
        return $moved;
    }

    /** Centralized copy primitive for managed files. */
    public function copyFile(string $sourcePath, string $targetPath): bool
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            return false;
        }
        $this->ensureDirectory(dirname($targetPath));
        $copied = copy($sourcePath, $targetPath);
        if ($copied) {
            @chmod($targetPath, 0664);
        }
        return $copied;
    }

    /**
     * Store a dynamically-scoped media file under the canonical uploads root.
     * Context and identifiers are sanitized here so no caller constructs paths.
     *
     * @return array<string,mixed>
     */
    public function storeMedia(
        array $file,
        string $context,
        int|string|null $entityId = null,
        int|string|null $albumId = null,
        ?string $preferredBaseName = null
    ): array {
        $allowed = [
            'jpg','jpeg','png','gif','bmp','svg','pdf','doc','docx','xls','xlsx',
            'txt','csv','zip','mp3','mp4','avi','mov'
        ];
        $this->assertBasicUpload($file, $allowed, 20 * 1024 * 1024);

        $segments = $this->mediaSegments($context, $entityId, $albumId);
        $directory = $this->uploadsRootPath(...$segments);
        $this->ensureDirectory($directory);

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $base = $preferredBaseName !== null && trim($preferredBaseName) !== ''
            ? $this->safeFilenamePart($preferredBaseName)
            : 'media_' . bin2hex(random_bytes(8));
        $filename = $this->uniqueFilename($directory, $base, $extension);
        $target = $directory . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new RuntimeException('Failed to store uploaded media.');
        }
        @chmod($target, 0664);

        return [
            'absolute_path' => $target,
            'storage_filename' => $filename,
            'original_filename' => basename((string) $file['name']),
            'file_type' => $extension,
            'mime_type' => $this->detectMimeType($target),
            'file_size_bytes' => (int) filesize($target),
            'relative_path' => $this->uploadsRelativePath(...array_merge($segments, [$filename])),
            'application_path' => $this->applicationUploadPath(...array_merge($segments, [$filename])),
            'url' => $this->publicUploadUrl(...array_merge($segments, [$filename])),
        ];
    }

    /** @return array<string,mixed> */
    public function importMedia(
        string $sourcePath,
        string $context,
        int|string|null $entityId = null,
        ?string $originalName = null
    ): array {
        $resolved = realpath($sourcePath);
        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            throw new RuntimeException('Source file does not exist or is not readable.');
        }
        $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        $allowed = [
            'jpg','jpeg','png','gif','bmp','svg','pdf','doc','docx','xls','xlsx',
            'txt','csv','zip','mp3','mp4','avi','mov'
        ];
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('File type not allowed for import.');
        }
        $segments = $this->mediaSegments($context, $entityId, null);
        $directory = $this->uploadsRootPath(...$segments);
        $this->ensureDirectory($directory);
        $base = $this->safeFilenamePart(
            pathinfo($originalName ?: basename($resolved), PATHINFO_FILENAME)
        );
        $filename = $this->uniqueFilename($directory, $base, $extension);
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!copy($resolved, $target)) {
            throw new RuntimeException('Failed to import file into managed uploads.');
        }
        @chmod($target, 0664);
        return [
            'absolute_path' => $target,
            'storage_filename' => $filename,
            'original_filename' => basename($originalName ?: $resolved),
            'file_type' => $extension,
            'mime_type' => $this->detectMimeType($target),
            'file_size_bytes' => (int) filesize($target),
            'relative_path' => $this->uploadsRelativePath(...array_merge($segments, [$filename])),
            'application_path' => $this->applicationUploadPath(...array_merge($segments, [$filename])),
            'url' => $this->publicUploadUrl(...array_merge($segments, [$filename])),
        ];
    }

    public function mediaAbsolutePath(
        string $context,
        int|string|null $entityId,
        int|string|null $albumId,
        string $filename
    ): string {
        return $this->uploadsRootPath(
            ...array_merge($this->mediaSegments($context, $entityId, $albumId), [basename($filename)])
        );
    }

    public function mediaPublicUrl(
        string $context,
        int|string|null $entityId,
        int|string|null $albumId,
        string $filename
    ): string {
        return $this->publicUploadUrl(
            ...array_merge($this->mediaSegments($context, $entityId, $albumId), [basename($filename)])
        );
    }

    public function mediaThumbnailUrl(
        string $context,
        int|string|null $entityId,
        int|string|null $albumId,
        string $filename,
        int $width = 200,
        int $height = 200
    ): ?string {
        $source = $this->mediaAbsolutePath($context, $entityId, $albumId, $filename);
        if (!is_file($source)) {
            return null;
        }
        $segments = array_merge(
            $this->mediaSegments($context, $entityId, $albumId),
            ['thumbnails']
        );
        $thumbDirectory = $this->uploadsRootPath(...$segments);
        $this->ensureDirectory($thumbDirectory);
        $thumbFilename = 'thumb_' . basename($filename);
        $thumbPath = $thumbDirectory . DIRECTORY_SEPARATOR . $thumbFilename;
        if (!is_file($thumbPath) && !$this->generateThumbnail($source, $thumbPath, $width, $height)) {
            return null;
        }
        return $this->publicUploadUrl(...array_merge($segments, [$thumbFilename]));
    }

    public function publicUploadUrl(string ...$segments): string
    {
        $clean = array_map([$this, 'safePublicSegment'], $segments);
        return rtrim((string) BASE_URL, '/') . '/uploads/' . implode('/', array_map('rawurlencode', $clean));
    }

    public function applicationUploadPath(string ...$segments): string
    {
        $clean = array_map([$this, 'safePublicSegment'], $segments);
        return 'uploads/' . implode('/', $clean);
    }

    private function uploadsRelativePath(string ...$segments): string
    {
        $clean = array_map([$this, 'safePublicSegment'], $segments);
        return implode('/', $clean);
    }

    private function uploadsRootPath(string ...$segments): string
    {
        $path = rtrim((string) UPLOAD_PATH, '/\\');
        foreach ($segments as $segment) {
            $path .= DIRECTORY_SEPARATOR . $this->safePublicSegment($segment);
        }
        return $path;
    }

    /** @return list<string> */
    private function mediaSegments(
        string $context,
        int|string|null $entityId,
        int|string|null $albumId
    ): array {
        $segments = [$this->safePublicSegment($context)];
        if ($entityId !== null && $entityId !== '') {
            $segments[] = $this->safePublicSegment((string) $entityId);
        }
        if ($albumId !== null && $albumId !== '') {
            $segments[] = 'album_' . $this->safePublicSegment((string) $albumId);
        }
        return $segments;
    }

    private function safePublicSegment(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value), '/');
        if ($value === '' || str_contains($value, '..') || str_contains($value, "\0")) {
            throw new RuntimeException('Invalid upload path segment.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new RuntimeException('Invalid upload path segment.');
        }
        return $value;
    }

    /** @param list<string> $allowedExtensions */
    private function assertBasicUpload(array $file, array $allowedExtensions, int $maxBytes): void
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid file parameters.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed.');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxBytes) {
            throw new RuntimeException('Uploaded file size is invalid.');
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('File type not allowed.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded file.');
        }
    }

    private function uniqueFilename(string $directory, string $base, string $extension): string
    {
        $candidate = $base . '.' . $extension;
        $counter = 1;
        while (is_file($directory . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = $base . '_' . date('YmdHis') . '_' . $counter . '.' . $extension;
            $counter++;
        }
        return $candidate;
    }

    private function generateThumbnail(string $source, string $destination, int $width, int $height): bool
    {
        $info = @getimagesize($source);
        if (!is_array($info)) {
            return false;
        }
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_GIF => @imagecreatefromgif($source),
            IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($source) : false,
            default => false,
        };
        if ($image === false) {
            return false;
        }
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        $ratio = min($width / max(1, $originalWidth), $height / max(1, $originalHeight));
        $newWidth = max(1, (int) round($originalWidth * $ratio));
        $newHeight = max(1, (int) round($originalHeight * $ratio));
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled(
            $thumbnail,
            $image,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );
        $written = match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($thumbnail, $destination, 85),
            IMAGETYPE_PNG => imagepng($thumbnail, $destination, 8),
            IMAGETYPE_GIF => imagegif($thumbnail, $destination),
            IMAGETYPE_BMP => function_exists('imagebmp') ? imagebmp($thumbnail, $destination) : false,
            default => false,
        };
        imagedestroy($image);
        imagedestroy($thumbnail);
        return (bool) $written;
    }

    /** Ensure and return a directory under a canonical upload category. */
    public function categoryDirectory(string $category, string ...$segments): string
    {
        $path = $this->path($category, ...$segments);
        $this->ensureDirectory($path);
        return $path;
    }

    /** Build a public URL for a canonical category without exposing roots. */
    public function categoryPublicUrl(string $category, string ...$segments): string
    {
        $policy = $this->policy($category);
        $publicSegment = $policy['public_segment'];
        if ($publicSegment === null) {
            throw new RuntimeException('This upload category is not publicly addressable.');
        }
        $parts = [rtrim((string) BASE_URL, '/'), 'uploads', trim((string) $publicSegment, '/')];
        foreach ($segments as $segment) {
            $parts[] = rawurlencode($this->safePublicSegment($segment));
        }
        return implode('/', $parts);
    }

    /** Ensure a filesystem directory through the canonical storage service. */
    public function ensureDirectoryPath(string $directory): string
    {
        $this->ensureDirectory($directory);
        return $directory;
    }

    /** Move/rename a managed file while ensuring the target directory exists. */
    public function moveFile(string $sourcePath, string $targetPath): bool
    {
        if (!is_file($sourcePath)) {
            return false;
        }
        $this->ensureDirectory(dirname($targetPath));
        return rename($sourcePath, $targetPath);
    }

    /** Read a managed or infrastructure file through the canonical service. */
    public function readFile(string $path): string|false
    {
        return is_file($path) ? file_get_contents($path) : false;
    }

    /** Atomically replace a file through the canonical storage service. */
    public function atomicWrite(string $path, string $contents): bool
    {
        $this->ensureDirectory(dirname($path));
        $tmp = $path . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            return false;
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

}
