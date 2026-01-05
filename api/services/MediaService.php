<?php
namespace App\API\Services;

use PDO;
use Exception;

class MediaService
{
    private $db;
    private $uploadBase;

    public function __construct(PDO $db, $uploadBase = null)
    {
        $this->db = $db;
        $this->uploadBase = $uploadBase ? $uploadBase : (__DIR__ . '/../../uploads');
    }

    // 1. Upload Media
    public function uploadMedia($file, $context, $entityId = null, $albumId = null, $uploaderId = null, $description = '', $tags = '')
    {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'mp3', 'mp4', 'avi', 'mov'];
        $maxSize = 20 * 1024 * 1024; // 20MB
        if (!isset($file['error']) || is_array($file['error']))
            throw new Exception('Invalid file params');
        if ($file['error'] !== UPLOAD_ERR_OK)
            throw new Exception('File upload error');
        if ($file['size'] > $maxSize)
            throw new Exception('File too large');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes))
            throw new Exception('File type not allowed');
        $dir = $this->uploadBase . "/$context" . ($entityId ? "/$entityId" : '') . ($albumId ? "/album_$albumId" : '');
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        $filename = uniqid('media_') . '_' . time() . ".$ext";
        $path = "$dir/$filename";
        if (!move_uploaded_file($file['tmp_name'], $path))
            throw new Exception('Failed to move file');
        // Save metadata
        $stmt = $this->db->prepare("INSERT INTO media_files (filename, original_name, file_type, file_size, uploader_id, context, entity_id, album_id, description, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$filename, $file['name'], $ext, $file['size'], $uploaderId, $context, $entityId, $albumId, $description, $tags]);
        return $this->db->lastInsertId();
    }

    // Import an existing file on disk into managed uploads and register metadata
    public function importFile($sourcePath, $context, $entityId = null, $originalName = null, $uploaderId = null, $description = '', $tags = '')
    {
        if (!file_exists($sourcePath)) {
            throw new Exception('Source file does not exist: ' . $sourcePath);
        }
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'mp3', 'mp4', 'avi', 'mov'];
        if (!in_array($ext, $allowedTypes)) {
            throw new Exception('File type not allowed for import');
        }

        $dir = $this->uploadBase . "/$context" . ($entityId ? "/$entityId" : '');
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        $filename = uniqid('media_') . '_' . time() . ".{$ext}";
        $path = "$dir/$filename";
        if (!@copy($sourcePath, $path)) {
            throw new Exception('Failed to copy file to uploads');
        }

        $filesize = filesize($path);
        $origName = $originalName ?? basename($sourcePath);

        $stmt = $this->db->prepare("INSERT INTO media_files (filename, original_name, file_type, file_size, uploader_id, context, entity_id, album_id, description, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$filename, $origName, $ext, $filesize, $uploaderId, $context, $entityId, null, $description, $tags]);
        return $this->db->lastInsertId();
    }

    // 2. Create Album
    public function createAlbum($name, $description = '', $coverImage = null, $createdBy = null)
    {
        $stmt = $this->db->prepare("INSERT INTO albums (name, description, cover_image, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $coverImage, $createdBy]);
        return $this->db->lastInsertId();
    }

    // 3. List Albums
    public function listAlbums($filters = [])
    {
        $sql = "SELECT * FROM albums WHERE 1=1";
        $params = [];
        if (!empty($filters['created_by'])) {
            $sql .= " AND created_by = ?";
            $params[] = $filters['created_by'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. List Media
    public function listMedia($filters = [])
    {
        $sql = "SELECT * FROM media_files WHERE is_active = 1";
        $params = [];
        if (!empty($filters['context'])) {
            $sql .= " AND context = ?";
            $params[] = $filters['context'];
        }
        if (!empty($filters['entity_id'])) {
            $sql .= " AND entity_id = ?";
            $params[] = $filters['entity_id'];
        }
        if (!empty($filters['album_id'])) {
            $sql .= " AND album_id = ?";
            $params[] = $filters['album_id'];
        }
        if (!empty($filters['uploader_id'])) {
            $sql .= " AND uploader_id = ?";
            $params[] = $filters['uploader_id'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND file_type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (original_name LIKE ? OR description LIKE ? OR tags LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        $sql .= " ORDER BY upload_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Update Media Metadata
    public function updateMedia($mediaId, $fields)
    {
        $set = [];
        $params = [];
        foreach ($fields as $k => $v) {
            $set[] = "$k = ?";
            $params[] = $v;
        }
        $params[] = $mediaId;
        $sql = "UPDATE media_files SET " . implode(", ", $set) . ", upload_date = upload_date WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    // 6. Delete Media
    public function deleteMedia($mediaId)
    {
        $stmt = $this->db->prepare("SELECT filename, context, entity_id, album_id FROM media_files WHERE id = ?");
        $stmt->execute([$mediaId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($media) {
            $filePath = $this->uploadBase . "/{$media['context']}" . ($media['entity_id'] ? "/{$media['entity_id']}" : '') . ($media['album_id'] ? "/album_{$media['album_id']}" : '') . "/{$media['filename']}";
            if (file_exists($filePath))
                unlink($filePath);
        }
        $stmt = $this->db->prepare("UPDATE media_files SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$mediaId]);
    }

    // 7. Delete Album
    public function deleteAlbum($albumId)
    {
        $stmt = $this->db->prepare("UPDATE media_files SET album_id = NULL WHERE album_id = ?");
        $stmt->execute([$albumId]);
        $stmt = $this->db->prepare("DELETE FROM albums WHERE id = ?");
        return $stmt->execute([$albumId]);
    }

    // 8. Permissions (stub)
    public function canAccess($userId, $mediaId, $action = 'view')
    {
        // Example role-based access logic
        // You may want to replace this with your actual user/role system
        $stmt = $this->db->prepare("SELECT uploader_id, context FROM media_files WHERE id = ?");
        $stmt->execute([$mediaId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$media) return false;

        // Example: get user role (replace with your actual user system)
        $userRole = isset($_REQUEST['user']['role']) ? $_REQUEST['user']['role'] : 'guest';

        // Admins can do anything
        if ($userRole === 'admin') return true;

        // Uploader can update/delete their own media
        if (in_array($action, ['update', 'delete']) && $media['uploader_id'] == $userId) return true;

        // Public context: allow view
        if ($action === 'view' && $media['context'] === 'public') return true;

        // Owner can view
        if ($action === 'view' && $media['uploader_id'] == $userId) return true;

        // Otherwise deny
        return false;
    }

    // 9. Usage Tracking (stub)
    public function trackUsage($mediaId, $context)
    {
        $stmt = $this->db->prepare("UPDATE media_files SET usage_context = ? WHERE id = ?");
        return $stmt->execute([$context, $mediaId]);
    }

    // 10. Generate Preview (stub)
    public function getPreviewUrl($mediaId)
    {
        // Only generate previews for images
        $stmt = $this->db->prepare("SELECT filename, context, entity_id, album_id, file_type FROM media_files WHERE id = ?");
        $stmt->execute([$mediaId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$media)
            return null;
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
        if (!in_array(strtolower($media['file_type']), $imageTypes))
            return null;

        $baseDir = $this->uploadBase . "/{$media['context']}" . ($media['entity_id'] ? "/{$media['entity_id']}" : '') . ($media['album_id'] ? "/album_{$media['album_id']}" : '');
        $filePath = $baseDir . "/{$media['filename']}";
        $thumbDir = $baseDir . '/thumbnails';
        if (!is_dir($thumbDir))
            mkdir($thumbDir, 0755, true);
        $thumbPath = $thumbDir . "/thumb_{$media['filename']}";

        // Generate thumbnail if it doesn't exist
        if (!file_exists($thumbPath) && file_exists($filePath)) {
            $this->generateThumbnail($filePath, $thumbPath, 200, 200);
        }
        // Return relative path for web access (adjust as needed for your routing)
        $relativeThumb = str_replace($this->uploadBase, '/uploads', $thumbPath);
        return file_exists($thumbPath) ? $relativeThumb : null;
    }

    // Helper: Generate thumbnail for images
    private function generateThumbnail($src, $dest, $width, $height)
    {
        $info = getimagesize($src);
        if (!$info)
            return false;
        $type = $info[2];
        switch ($type) {
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($src);
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($src);
                break;
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($src);
                break;
            case IMAGETYPE_BMP:
                $img = imagecreatefrombmp($src);
                break;
            default:
                return false;
        }
        $origWidth = imagesx($img);
        $origHeight = imagesy($img);
        $ratio = min($width / $origWidth, $height / $origHeight);
        $newWidth = (int) ($origWidth * $ratio);
        $newHeight = (int) ($origHeight * $ratio);
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumb, $dest, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumb, $dest, 8);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumb, $dest);
                break;
            case IMAGETYPE_BMP:
                imagebmp($thumb, $dest);
                break;
        }
        imagedestroy($img);
        imagedestroy($thumb);
        return true;
    }
}


