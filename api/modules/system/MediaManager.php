<?php
namespace App\API\Modules\system;

use App\API\Services\MediaService;
use PDO;

class MediaManager
{
    private $mediaService;

    public function __construct(PDO $db)
    {
        $this->mediaService = new MediaService($db);
    }

    // Upload media file
    public function upload($file, $context, $entityId = null, $albumId = null, $uploaderId = null, $description = '', $tags = '')
    {
        return $this->mediaService->uploadMedia($file, $context, $entityId, $albumId, $uploaderId, $description, $tags);
    }

    // Create album
    public function createAlbum($name, $description = '', $coverImage = null, $createdBy = null)
    {
        return $this->mediaService->createAlbum($name, $description, $coverImage, $createdBy);
    }

    // List albums
    public function listAlbums($filters = [])
    {
        return $this->mediaService->listAlbums($filters);
    }

    // List media
    public function listMedia($filters = [])
    {
        return $this->mediaService->listMedia($filters);
    }

    // Update media metadata
    public function updateMedia($mediaId, $fields)
    {
        return $this->mediaService->updateMedia($mediaId, $fields);
    }

    // Delete media
    public function deleteMedia($mediaId)
    {
        return $this->mediaService->deleteMedia($mediaId);
    }

    // Delete album
    public function deleteAlbum($albumId)
    {
        return $this->mediaService->deleteAlbum($albumId);
    }

    // Permissions
    public function canAccess($userId, $mediaId, $action = 'view')
    {
        return $this->mediaService->canAccess($userId, $mediaId, $action);
    }

    // Usage tracking
    public function trackUsage($mediaId, $context)
    {
        return $this->mediaService->trackUsage($mediaId, $context);
    }

    // Get preview URL
    public function getPreviewUrl($mediaId)
    {
        return $this->mediaService->getPreviewUrl($mediaId);
    }

    // Import existing file from disk into uploads and register metadata
    public function import($sourcePath, $context, $entityId = null, $originalName = null, $uploaderId = null, $description = '', $tags = '')
    {
        return $this->mediaService->importFile($sourcePath, $context, $entityId, $originalName, $uploaderId, $description, $tags);
    }
}
