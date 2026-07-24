<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * Media metadata adapter.
 *
 * This class deliberately contains no upload validation, path construction,
 * filesystem move/copy/delete logic, thumbnail generation or public URL
 * construction. All of that is owned by UploadService.
 */
final class MediaService
{
    private UploadService $uploads;

    public function __construct(
        private PDO $db,
        mixed $legacyUploadBase = null
    ) {
        $this->uploads = new UploadService();
    }

    public function uploadMedia(
        array $file,
        string $context,
        int|string|null $entityId = null,
        int|string|null $albumId = null,
        int|string|null $uploaderId = null,
        string $description = '',
        string $tags = '',
        ?string $preferredBaseName = null
    ): string|int {
        $stored = $this->uploads->storeMedia(
            $file,
            $context,
            $entityId,
            $albumId,
            $preferredBaseName
        );

        $statement = $this->db->prepare(
            'INSERT INTO media_files (
                filename,
                original_name,
                file_type,
                file_size,
                uploader_id,
                context,
                entity_id,
                album_id,
                description,
                tags
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $stored['storage_filename'],
            $stored['original_filename'],
            $stored['file_type'],
            $stored['file_size_bytes'],
            $uploaderId,
            $context,
            $entityId,
            $albumId,
            $description,
            $tags,
        ]);

        return $this->db->lastInsertId();
    }

    public function importFile(
        string $sourcePath,
        string $context,
        int|string|null $entityId = null,
        ?string $originalName = null,
        int|string|null $uploaderId = null,
        string $description = '',
        string $tags = ''
    ): string|int {
        $stored = $this->uploads->importMedia(
            $sourcePath,
            $context,
            $entityId,
            $originalName
        );

        $statement = $this->db->prepare(
            'INSERT INTO media_files (
                filename,
                original_name,
                file_type,
                file_size,
                uploader_id,
                context,
                entity_id,
                album_id,
                description,
                tags
             ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)'
        );
        $statement->execute([
            $stored['storage_filename'],
            $stored['original_filename'],
            $stored['file_type'],
            $stored['file_size_bytes'],
            $uploaderId,
            $context,
            $entityId,
            $description,
            $tags,
        ]);

        return $this->db->lastInsertId();
    }

    public function createAlbum(
        string $name,
        string $description = '',
        ?string $coverImage = null,
        int|string|null $createdBy = null
    ): string|int {
        $statement = $this->db->prepare(
            'INSERT INTO albums (name, description, cover_image, created_by)
             VALUES (?, ?, ?, ?)'
        );
        $statement->execute([
            $name,
            $description,
            $coverImage,
            $createdBy,
        ]);
        return $this->db->lastInsertId();
    }

    public function listAlbums(array $filters = []): array
    {
        $sql = 'SELECT * FROM albums WHERE 1=1';
        $params = [];
        if (!empty($filters['created_by'])) {
            $sql .= ' AND created_by = ?';
            $params[] = $filters['created_by'];
        }
        $sql .= ' ORDER BY created_at DESC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listMedia(array $filters = []): array
    {
        $sql = 'SELECT * FROM media_files WHERE is_active = 1';
        $params = [];
        foreach ([
            'context',
            'entity_id',
            'album_id',
            'uploader_id',
            'type' => 'file_type',
        ] as $filter => $column) {
            if (is_int($filter)) {
                $filter = $column;
            }
            if (!empty($filters[$filter])) {
                $sql .= " AND {$column} = ?";
                $params[] = $filters[$filter];
            }
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (original_name LIKE ? OR description LIKE ? OR tags LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term, $term);
        }
        $sql .= ' ORDER BY upload_date DESC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateMedia(int|string $mediaId, array $fields): bool
    {
        $allowed = ['description', 'tags', 'album_id', 'is_active'];
        $set = [];
        $params = [];
        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                continue;
            }
            $set[] = "{$field} = ?";
            $params[] = $value;
        }
        if ($set === []) {
            return true;
        }
        $params[] = $mediaId;
        $statement = $this->db->prepare(
            'UPDATE media_files SET ' . implode(', ', $set) . ' WHERE id = ?'
        );
        return $statement->execute($params);
    }

    public function deleteMedia(int|string $mediaId): bool
    {
        $media = $this->findMedia($mediaId);
        if ($media !== null) {
            $path = $this->uploads->mediaAbsolutePath(
                (string) $media['context'],
                $media['entity_id'] ?? null,
                $media['album_id'] ?? null,
                (string) $media['filename']
            );
            $this->uploads->deleteFile($path);
        }

        $statement = $this->db->prepare(
            'UPDATE media_files SET is_active = 0 WHERE id = ?'
        );
        return $statement->execute([$mediaId]);
    }

    public function deleteAlbum(int|string $albumId): bool
    {
        $statement = $this->db->prepare(
            'UPDATE media_files SET album_id = NULL WHERE album_id = ?'
        );
        $statement->execute([$albumId]);
        $statement = $this->db->prepare('DELETE FROM albums WHERE id = ?');
        return $statement->execute([$albumId]);
    }

    public function canAccess(
        int|string|null $userId,
        int|string $mediaId,
        string $action = 'view'
    ): bool {
        $media = $this->findMedia($mediaId);
        if ($media === null) {
            return false;
        }
        $role = $_REQUEST['user']['role'] ?? 'guest';
        if ($role === 'admin') {
            return true;
        }
        if (
            in_array($action, ['update', 'delete'], true)
            && (string) ($media['uploader_id'] ?? '') === (string) $userId
        ) {
            return true;
        }
        if ($action === 'view' && ($media['context'] ?? '') === 'public') {
            return true;
        }
        return $action === 'view'
            && (string) ($media['uploader_id'] ?? '') === (string) $userId;
    }

    public function trackUsage(int|string $mediaId, string $context): bool
    {
        $statement = $this->db->prepare(
            'UPDATE media_files SET usage_context = ? WHERE id = ?'
        );
        return $statement->execute([$context, $mediaId]);
    }

    public function getPreviewUrl(int|string $mediaId): ?string
    {
        $media = $this->findMedia($mediaId);
        if ($media === null) {
            return null;
        }
        if (!in_array(
            strtolower((string) $media['file_type']),
            ['jpg', 'jpeg', 'png', 'gif', 'bmp'],
            true
        )) {
            return null;
        }
        return $this->uploads->mediaThumbnailUrl(
            (string) $media['context'],
            $media['entity_id'] ?? null,
            $media['album_id'] ?? null,
            (string) $media['filename']
        );
    }

    public function getFileUrl(int|string $mediaId): ?string
    {
        $media = $this->findMedia($mediaId);
        if ($media === null) {
            return null;
        }
        $path = $this->uploads->mediaAbsolutePath(
            (string) $media['context'],
            $media['entity_id'] ?? null,
            $media['album_id'] ?? null,
            (string) $media['filename']
        );
        if (!is_file($path)) {
            return null;
        }
        return $this->uploads->mediaPublicUrl(
            (string) $media['context'],
            $media['entity_id'] ?? null,
            $media['album_id'] ?? null,
            (string) $media['filename']
        );
    }

    private function findMedia(int|string $mediaId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT filename, original_name, file_type, file_size, uploader_id,
                    context, entity_id, album_id, description, tags, is_active
             FROM media_files
             WHERE id = ?
             LIMIT 1'
        );
        $statement->execute([$mediaId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
