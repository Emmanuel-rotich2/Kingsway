<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/BulkCrudController.php';

use App\API\Includes\BulkCrudController;

header('Content-Type: application/json');

$bulkCrud = new BulkCrudController($db);

// Example: handle all bulk/file/export/profile-pic/document actions for maintenance table
$bulkCrud->handle(
    'maintenance',
    ['asset_id'], // unique columns, adjust as needed
    'id',
    [
        'profile_pic_column' => 'asset_image', // if you store images for assets
        'document_table' => 'maintenance_documents',
        'document_ref_column' => 'maintenance_id'
    ]
);

// Add any maintenance-specific logic below if needed
