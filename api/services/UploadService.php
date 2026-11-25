<?php

declare(strict_types=1);

namespace App\API\Services;

use RuntimeException;
use Throwable;

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
     * Longest edge kept for uploaded photographs. Only photo categories are
     * normalised: scanned documents (birth certificates, ID, exam papers) must
     * retain their original pixels so small print stays legible.
     *
     * @var array<string,int>
     */
    private const PHOTO_MAX_EDGE = [
        'website_image' => 1920,
        'student_photo' => 1200,
        'staff_photo' => 1200,
        'uniform_catalog_image' => 1600,
        // MediaService uploads (student/staff photos, admissions, gallery).
        'media' => 1600,
    ];

    /** Images at or below this size are already light enough to leave alone. */
    private const PHOTO_MIN_OPTIMISE_BYTES = 262144; // 256 KB

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

    private ?DocumentExtractionService $extractionService = null;

    private ?PythonDocumentBridge $imageBridge = null;

    public function __construct()
    {
        $this->categories = [
            // Public website media (gallery, news and announcement covers).
            // Raster images and browser-playable video only: no SVG or
            // documents, which could carry script into a public page.
            // Exam papers and marking schemes: confidential, never served
            // publicly; downloaded only through the paper bank endpoint.
            'exam_paper' => [
                'root' => (string) UPLOAD_PATH . '/academic/exam_papers',
                'public_segment' => null,
                'extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png',
                ],
                'max_bytes' => 20971520,
            ],
            'website_image' => [
                'root' => (string) SCHOOL_ASSETS . '/website',
                'public_segment' => 'school_assets/website',
                'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                'max_bytes' => 10485760,
            ],
            'website_video' => [
                'root' => (string) SCHOOL_ASSETS . '/website',
                'public_segment' => 'school_assets/website',
                'extensions' => ['mp4', 'webm', 'mov'],
                'mime_types' => ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v'],
                'max_bytes' => 104857600,
            ],
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
            'uniform_catalog_image' => [
                'root' => (string) UPLOAD_PATH . '/uniform_catalog',
                // Keep the browser URL aligned with the physical upload root.
                // This prevents catalogue images from being stored under one
                // path while database links point at a different path.
                'public_segment' => 'uniform_catalog',
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
                ],
                'max_bytes' => 26214400,
            ],
            'career_cv' => [
                'root' => (string) UPLOAD_PATH . '/cvs',
                'public_segment' => null,
                'extensions' => ['pdf', 'doc', 'docx'],
                'mime_types' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'max_bytes' => 10485760,
            ],
            'communication_attachment' => [
                'root' => (string) UPLOAD_PATH . '/communications',
                'public_segment' => null,
                'extensions' => ['pdf','doc','docx','xls','xlsx','csv','txt','jpg','jpeg','png','zip'],
                'mime_types' => ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/csv','text/plain','image/jpeg','image/png','application/zip'],
                'max_bytes' => 26214400,
            ],
            'import_file' => [
                'root' => (string) UPLOAD_PATH . '/imports',
                'public_segment' => null,
                'extensions' => ['csv','xls','xlsx','ods','pdf'],
                // finfo sniffs a plain CSV as text/plain, so text/plain is required
                // here or CSV import can never pass. ODS is the school's own
                // spreadsheet format and StaffMigrationController accepts it.
                'mime_types' => ['text/csv','text/plain','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.oasis.opendocument.spreadsheet','application/pdf','application/octet-stream'],
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
     *   subdirectory?:string,
     *   prefix?:string,
     *   preferred_name?:string,
     *   replace_path?:string|null,
     *   extraction_context?:string,
     *   uploaded_by?:int|null,
     *   expected_document_type?:string|null
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
        $subdirectory = $this->safePathSegment(
            (string) ($options['subdirectory'] ?? '')
        );

        $destinationDirectory = rtrim($policy['root'], '/\\');
        if ($subdirectory !== '') {
            $destinationDirectory .= DIRECTORY_SEPARATOR . $subdirectory;
        }
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

        // Downscale and recompress photographs in place, so the stored path and
        // the URL returned to callers stay exactly the same. Failure is never
        // fatal: the original file remains available.
        $this->normalisePhoto($destinationPath, $category);

        $extraction = $this->queueExtraction(
            $destinationPath,
            $category,
            (string) ($options['extraction_context'] ?? $options['context'] ?? $category),
            $originalName,
            isset($options['uploaded_by']) ? (int) $options['uploaded_by'] : null,
            isset($options['expected_document_type'])
                ? (string) $options['expected_document_type']
                : null
        );

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
            'extraction' => $extraction,
            // The portable reference to store: relative to UPLOAD_URL, so a
            // moved uploads folder only needs UPLOAD_PATH/UPLOAD_URL changed.
            'path' => $policy['public_segment'] === null ? null : implode('/', array_filter([
                trim((string) $policy['public_segment'], '/'),
                $ownerId,
                $storageName,
            ], static fn ($part) => $part !== '')),
        ];
    }

    /**
     * Full browser URL for a stored upload reference.
     *
     * Stored values are relative to the uploads root ("school_assets/x.png").
     * Older rows hold "/uploads/...", "<base>/uploads/..." or a full URL under
     * UPLOAD_URL; those resolve through the same root. External URLs (another
     * site, a CDN link the user pasted) are returned unchanged.
     */
    public static function resolvePublicUrl(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') return '';
        $relative = self::toStoredPath($stored);
        if ($relative === $stored && preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|/)#i', $stored)) {
            return $stored; // external URL or an app path outside uploads
        }
        $segments = array_map('rawurlencode', array_map('rawurldecode', explode('/', $relative)));
        return rtrim((string) UPLOAD_URL, '/') . '/' . implode('/', $segments);
    }

    /**
     * The portable form of an upload reference: strips UPLOAD_URL and any
     * "/uploads/" prefix so only the path inside the uploads root is stored.
     * Values that do not point into the uploads root are returned unchanged.
     */
    public static function toStoredPath(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        $root = rtrim((string) UPLOAD_URL, '/') . '/';
        if (stripos($value, $root) === 0) {
            return ltrim(rawurldecode(substr($value, strlen($root))), '/');
        }
        $path = (string) (parse_url($value, PHP_URL_PATH) ?? '');
        $isLocal = !preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $value)
            || strcasecmp((string) parse_url($value, PHP_URL_HOST), (string) parse_url((string) BASE_URL, PHP_URL_HOST)) === 0;
        if ($isLocal && preg_match('#(?:^|/)uploads/(.+)$#', $path, $m)) {
            return ltrim(rawurldecode($m[1]), '/');
        }
        if (!preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|/)#i', $value)) {
            return ltrim($value, '/'); // already relative
        }
        return $value;
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
     * Convert any stored upload reference into the plain path relative to
     * the configured uploads root.
     *
     * Accepted stored forms: a bare relative path, an `uploads/...`
     * relative path, a root-relative path such as `/uploads/...`, an
     * absolute URL pointing into the uploads tree, and legacy values that
     * embed an old base segment such as `/Kingsway/uploads/...`. Stored
     * values must never contain BASE_URL or a filesystem root, so the same
     * database keeps working whenever BASE_URL or UPLOAD_PATH change.
     */
    public function normaliseStoredReference(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value));
        if ($value === '') {
            throw new RuntimeException('Invalid stored upload path.');
        }
        if (preg_match('#^(?:https?:)?//#i', $value)) {
            $path = (string) (parse_url($value, PHP_URL_PATH) ?? '');
            if (!preg_match('#(?:^|/)uploads/(.+)$#i', $path, $match)) {
                throw new RuntimeException(
                    'External URLs are not local upload paths.'
                );
            }
            $value = $match[1];
        } else {
            // Old deployments could have any application-folder name before
            // `/uploads/`. Extract by the stable uploads boundary instead of
            // hard-coding a project name such as `Kingsway`.
            $value = (string) preg_replace('#^(?:.*/)?uploads/#i', '', $value);
        }
        $value = ltrim($value, '/');
        $value = (string) preg_replace('/[?#].*$/', '', $value);
        $decoded = rawurldecode($value);
        if (
            $value === ''
            || str_contains($decoded, "\0")
            || in_array('.', explode('/', $decoded), true)
            || in_array('..', explode('/', $decoded), true)
        ) {
            throw new RuntimeException('Invalid stored upload path.');
        }
        return $value;
    }

    /**
     * Produce the exact value suitable for persistence in a media column.
     * Local uploads become an uploads-root-relative path; genuine remote
     * HTTP(S) resources remain absolute because they are not environment
     * configuration and must continue to resolve at their owning host.
     */
    public function canonicalStoredReference(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('Invalid stored media reference.');
        }
        if (preg_match('#^(?:https?:)?//#i', $value)) {
            $path = (string) (parse_url($value, PHP_URL_PATH) ?? '');
            if (!preg_match('#(?:^|/)uploads/(.+)$#i', $path)) {
                $validNetworkPath = str_starts_with($value, '//')
                    && (string) (parse_url($value, PHP_URL_HOST) ?? '') !== '';
                if (!$validNetworkPath && filter_var($value, FILTER_VALIDATE_URL) === false) {
                    throw new RuntimeException('Invalid external media URL.');
                }
                return $value;
            }
        }
        return $this->normaliseStoredReference($value);
    }

    /**
     * Resolve a stored upload reference into the public URL it must have in
     * THIS environment, derived from the configured BASE_URL/UPLOAD_URL.
     *
     * External absolute URLs that do not point into the uploads tree (for
     * example remote images or CDNs) are returned untouched, while local
     * values are always re-anchored to the current UPLOAD_URL so the same
     * database row resolves correctly after BASE_URL or UPLOAD_PATH change.
     */
    public function publicUrl(?string $stored): ?string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }
        if (preg_match('#^(?:https?:)?//#i', $stored)) {
            $path = (string) (parse_url($stored, PHP_URL_PATH) ?? '');
            if (!preg_match('#(?:^|/)uploads/.+#i', $path)) {
                return $stored;
            }
        }
        try {
            $relative = $this->normaliseStoredReference($stored);
        } catch (RuntimeException $e) {
            return null;
        }
        return rtrim((string) UPLOAD_URL, '/')
            . '/'
            . $relative;
    }

    /**
     * Convert a canonical uploads-relative path back to an absolute path.
     */
    public function absolutePath(string $storedPath): string
    {
        $storedPath = $this->normaliseStoredReference($storedPath);

        $resolved = rtrim((string) UPLOAD_PATH, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);

        $realDestination = realpath(rtrim((string) UPLOAD_PATH, '/\\'));
        $realTarget = realpath(dirname($resolved));
        if (
            $realDestination === false
            || $realTarget === false
            || strpos($realTarget, $realDestination) !== 0
        ) {
            throw new RuntimeException('Invalid file path');
        }

        return $resolved;
    }

    /** @return array<string,mixed> */
    /** Largest accepted upload for a category, in bytes. */
    public function maxBytes(string $category): int
    {
        return (int) $this->policy($category)['max_bytes'];
    }

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

        if (
            $extension !== ''
            && isset(self::EXTENSION_MIME_MAP[$extension])
            && self::EXTENSION_MIME_MAP[$extension] !== $mime
            && !($extension === 'csv' && $mime === 'text/plain')
        ) {
            throw new RuntimeException(
                sprintf(
                    'File extension .%s does not match declared type %s.',
                    $extension,
                    $mime
                )
            );
        }

        $this->assertContentAllowed($tmpName, $extension);
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

    /**
     * @var array<string,string> Map of file extension to expected MIME type.
     */
    private const EXTENSION_MIME_MAP = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'm4v' => 'video/x-m4v',
        'webm' => 'video/webm',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
    ];

    /**
     * Content types that can never be a legitimate upload, whatever
     * extension the file wears. A renamed executable, script or HTML
     * document is rejected on its bytes, not its name.
     */
    private const DANGEROUS_MIME_TYPES = [
        'application/x-dosexec',
        'application/x-msdownload',
        'application/x-msdos-program',
        'application/x-executable',
        'application/x-sharedlib',
        'application/x-sh',
        'application/x-bsh',
        'application/x-csh',
        'application/x-php',
        'text/html',
        'application/xhtml+xml',
        'text/javascript',
        'application/javascript',
        'application/x-javascript',
        'application/ecmascript',
        'application/x-msdownload',
        'application/vnd.microsoft.portable-executable',
    ];

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
            throw new RuntimeException('Invalid upload path segment.');
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

    /** Injectable for tests; production uses the canonical governed service. */
    public function setExtractionService(?DocumentExtractionService $service): void
    {
        $this->extractionService = $service;
    }

    /** Injectable for tests; production uses the pinned Pillow-backed bridge. */
    public function setImageBridge(?PythonDocumentBridge $bridge): void
    {
        $this->imageBridge = $bridge;
    }

    /**
     * Every successful upload is offered to the extraction boundary. Photos,
     * video and unsupported files are declined inside the service; an
     * extraction failure never rolls back a valid upload.
     *
     * @return array<string,mixed>|null
     */
    private function queueExtraction(
        string $absolutePath,
        string $category,
        string $context,
        string $originalName,
        ?int $uploaderId,
        ?string $expectedDocumentType = null
    ): ?array {
        try {
            $service = $this->extractionService ??= new DocumentExtractionService();
            return $service->enqueue(
                $absolutePath,
                $category,
                $context,
                $originalName,
                $uploaderId,
                $this->currentPermissions(),
                (string) ($_SERVER['REQUEST_ID'] ?? ''),
                $expectedDocumentType
            );
        } catch (Throwable $error) {
            \App\API\Includes\FileLogger::write('document_extraction', [
                'type' => 'document_extract_upload_hook_failed',
                'category' => $category,
                'filename' => basename($originalName),
                'error_class' => $error::class,
            ]);
            return [
                'extraction_status' => 'needs_manual_entry',
                'extraction_state' => 'failed',
                'requires_staff_confirmation' => true,
                'preview_only' => true,
            ];
        }
    }

    /** @return array<int,string> */
    private function currentPermissions(): array
    {
        $permissions = $_SERVER['auth_user']['effective_permissions'] ?? [];
        if (!is_array($permissions)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $permissions)));
    }

    /**
     * Keep uploaded photographs lightweight without changing their filename,
     * extension or URL. Animated GIFs are left untouched, and a rewrite is
     * discarded whenever it does not actually reduce the byte size.
     */
    private function normalisePhoto(string $path, string $category): void
    {
        $maxEdge = self::PHOTO_MAX_EDGE[$category] ?? null;
        if ($maxEdge === null) {
            return;
        }
        $originalSize = filesize($path);
        if ($originalSize === false || $originalSize <= self::PHOTO_MIN_OPTIMISE_BYTES) {
            return;
        }

        // Prefer the pinned Pillow stack. GD remains the dependency-free
        // fallback for shared hosts where the Python service is unavailable.
        $this->normalisePhotoWithPillow($path, $maxEdge);
        $this->normalisePhotoWithGd($path, $maxEdge, (int) $originalSize);
    }

    private function normalisePhotoWithPillow(string $path, int $maxEdge): void
    {
        try {
            $bridge = $this->imageBridge ??= new PythonDocumentBridge();
            if (!$bridge->available()) {
                return;
            }
            $optimized = $bridge->optimizeImage($path, $maxEdge);
            if (!is_array($optimized)) {
                return;
            }
            $content = (string) ($optimized['content'] ?? '');
            $currentSize = (int) (filesize($path) ?: 0);
            if ($content === '' || $currentSize === 0 || strlen($content) >= $currentSize) {
                return;
            }

            $temporary = $path . '.pillow-' . bin2hex(random_bytes(4));
            if (@file_put_contents($temporary, $content, LOCK_EX) === false) {
                @unlink($temporary);
                return;
            }
            @chmod($temporary, (int) (fileperms($path) & 0777));
            if (!@rename($temporary, $path)) {
                @unlink($temporary);
            }
        } catch (Throwable $error) {
            \App\API\Includes\FileLogger::write('media', [
                'type' => 'image_optimize_fallback',
                'filename' => basename($path),
                'error_class' => $error::class,
            ]);
        }
    }

    private function normalisePhotoWithGd(string $path, int $maxEdge, int $originalSize): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        $info = @getimagesize($path);
        if (!is_array($info)) {
            return;
        }

        $type = (int) ($info[2] ?? 0);
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return;
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if ($source === false) {
            return;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, $maxEdge / max(1, max($width, $height)));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);
        if ($type !== IMAGETYPE_JPEG) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $newWidth, $newHeight, $transparent);
        }
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $tempPath = $path . '.opt-' . bin2hex(random_bytes(4));
        $written = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($target, $tempPath, 82),
            'png' => imagepng($target, $tempPath, 6),
            'webp' => function_exists('imagewebp') && imagewebp($target, $tempPath, 82),
            default => false,
        };
        // PHP >= 8.0 releases GD images when they leave scope; imagedestroy()
        // is a deprecated no-op as of PHP 8.5.

        $optimisedSize = is_file($tempPath) ? (int) filesize($tempPath) : 0;
        if (
            !$written
            || $optimisedSize === 0
            || $optimisedSize >= $originalSize
            || !rename($tempPath, $path)
        ) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
            return;
        }
        @chmod($path, 0664);
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
        $this->assertContentAllowed($tmp, $extension);
        $this->ensureDirectory($destination);
        $filename = bin2hex(random_bytes(12)) . '_' . date('Ymd_His') . '.' . $extension;
        $target = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Failed to store uploaded file.');
        }
        @chmod($target, 0664);
        $this->queueExtraction(
            $target,
            'system_storage',
            'legacy_upload',
            (string) ($file['name'] ?? $filename),
            isset($_SERVER['auth_user']['user_id']) ? (int) $_SERVER['auth_user']['user_id'] : null
        );
        return $filename;
    }

    /** Centralized write primitive for application-managed files. */
    public function writeFile(string $path, mixed $contents, int $flags = 0): int|false
    {
        $normalized = str_replace('\\', '/', $path);
        $logRoot = str_replace('\\', '/', dirname(__DIR__, 2) . '/logs');
        if ($normalized === $logRoot || str_starts_with($normalized, $logRoot . '/')) {
            // Compatibility interception for old modules that still try to
            // append flat files under logs/. Keep them in the governed,
            // rotated JSON journal and never persist an unredacted raw body.
            $category = preg_replace('/\.log$/i', '', basename($path)) ?: 'legacy';
            Logger::info($category, 'Legacy application log event', [
                'legacy_source' => basename($path),
                'legacy_message' => mb_substr(is_scalar($contents) ? (string) $contents : json_encode($contents), 0, 12000),
            ]);
            return is_string($contents) ? strlen($contents) : 1;
        }
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
        ?string $preferredBaseName = null,
        ?int $uploaderId = null,
        ?string $expectedDocumentType = null
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
        $this->normalisePhoto($target, 'media');
        $extraction = $this->queueExtraction(
            $target,
            'media',
            $context,
            (string) ($file['name'] ?? $filename),
            $uploaderId,
            $expectedDocumentType
        );

        return [
            'absolute_path' => $target,
            'extraction' => $extraction,
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
        ?string $originalName = null,
        ?int $uploaderId = null,
        ?string $expectedDocumentType = null
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
        $this->assertContentAllowed($resolved, $extension);
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
        $this->normalisePhoto($target, 'media');
        $extraction = $this->queueExtraction(
            $target,
            'media',
            $context,
            basename($originalName ?: $resolved),
            $uploaderId,
            $expectedDocumentType
        );
        return [
            'absolute_path' => $target,
            'extraction' => $extraction,
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
        $segments = [];
        foreach (explode('/', trim(str_replace('\\', '/', $context), '/')) as $part) {
            if ($part === '') {
                continue;
            }
            $segments[] = $this->safePublicSegment($part);
        }
        if ($segments === []) {
            throw new RuntimeException('Invalid upload path segment.');
        }
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
        $this->assertContentAllowed($tmp, $extension);
    }

    /**
     * Content-level upload gate: the bytes decide, not the filename.
     *
     * A file renamed to an accepted extension still carries its real type:
     * finfo reads the magic bytes, executables/scripts/markup are refused
     * outright, and a known extension must agree with the detected type.
     */
    private function assertContentAllowed(string $path, string $extension): void
    {
        $mime = $this->detectMimeType($path);
        if (in_array($mime, self::DANGEROUS_MIME_TYPES, true)) {
            throw new RuntimeException(
                'File content is not an allowed document or media type.'
            );
        }
        $expected = self::EXTENSION_MIME_MAP[$extension] ?? null;
        if (
            $expected !== null
            && $mime !== $expected
            // finfo reports CSVs as text/plain on some builds.
            && !($extension === 'csv' && $mime === 'text/plain')
            // Some browsers/OSes tag modern phone videos as generic MPEG.
            && !($extension === 'mp4' && $mime === 'application/mp4')
        ) {
            throw new RuntimeException(
                sprintf('File content does not match the .%s type.', $extension)
            );
        }

        if ($extension === 'pdf') {
            $this->assertPdfHasNoActiveContent($path);
        } elseif (in_array($extension, self::RASTER_IMAGE_EXTENSIONS, true)) {
            $this->assertDecodableImage($path);
        } elseif ($extension === 'csv') {
            $this->sanitiseCsvInPlace($path);
        }
    }

    /** Raster formats getimagesize() can actually decode. */
    private const RASTER_IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
    ];

    /**
     * PDF active-content markers. A PDF is a container: /JavaScript,
     * /Launch, /XFA, embedded files and rich media let a document run code
     * or fetch remote content inside the viewer. School uploads (letters,
     * report cards, registers) never legitimately need them, so their
     * presence rejects the file outright.
     */
    private const PDF_ACTIVE_CONTENT_MARKERS = [
        '/JavaScript',
        '/Launch',
        '/XFA',
        '/EmbeddedFiles',
        '/RichMedia',
        '/SubmitForm',
    ];

    private function assertPdfHasNoActiveContent(string $path): void
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to inspect the uploaded file.');
        }
        foreach (self::PDF_ACTIVE_CONTENT_MARKERS as $marker) {
            if (strpos($contents, $marker) !== false) {
                throw new RuntimeException(
                    'PDF contains active content and was rejected.'
                );
            }
        }
    }

    /**
     * Polyglot guard for raster images: the bytes must decode as the image
     * they claim to be. An appended payload still decodes, but anything
     * that is not a real image at all is refused here.
     */
    private function assertDecodableImage(string $path): void
    {
        if (@getimagesize($path) === false) {
            throw new RuntimeException(
                'Image file is corrupted or is not a valid image.'
            );
        }
    }

    /**
     * Uploaded CSVs are downloaded again by staff who may open them in a
     * spreadsheet app. Neutralise formula-leading cells (=, +, -, @, tab,
     * CR) in place so the stored file is already safe.
     */
    private function sanitiseCsvInPlace(string $path): void
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return;
        }
        $sanitised = \App\API\Includes\CsvSafety::content($contents);
        if ($sanitised !== $contents) {
            file_put_contents($path, $sanitised);
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
