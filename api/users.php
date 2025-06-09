<?php

namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
$db = \App\Config\Database::getInstance()->getConnection();
require_once __DIR__ . '/includes/BulkOperationsHelper.php';
require_once __DIR__ . '/modules/users/UsersAPI.php';

use App\Config\Database;
use App\API\Modules\users\UsersAPI;
use Exception;


$bulkHelper = new \App\API\Includes\BulkOperationsHelper($db);

header('Content-Type: application/json');

try {
    if (!class_exists('App\\API\\Modules\\Users\\UsersAPI')) {
        throw new Exception('UsersAPI class not found. Check autoloading and file paths.');
    }
    $usersApi = new UsersAPI($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Parse input data
$input = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $input = $_POST;
    // For JSON requests
    if (empty($input)) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                echo json_encode($usersApi->list($_GET));
            } elseif ($action === 'view' && $id) {
                echo json_encode($usersApi->get($id));
            } elseif ($action === 'profile' && $id) {
                echo json_encode($usersApi->getProfile($id));
            } elseif ($action === 'roles') {
                echo json_encode($usersApi->getRoles());
            } elseif ($action === 'permissions') {
                echo json_encode($usersApi->getPermissions());
            } elseif ($action === 'sidebar') {
                $user_id = $_GET['user_id'] ?? null;
                
                if (!$user_id) {
                    echo json_encode(['status' => 'error', 'message' => 'User ID not provided']);
                    exit;
                }

                // Get user roles from API
                $mainRole = $usersApi->getMainRole($user_id);
                $extraRoles = $usersApi->getExtraRoles($user_id);
                $allRoles = array_merge([$mainRole], $extraRoles);

                // Load menu configuration
                $menuConfig = require __DIR__ . '/../config/menu_items.php';
                $sidebar = [];
                $itemMap = []; // Track items by label to merge subitems

                // First add main role items
                if (isset($menuConfig[$mainRole])) {
                    foreach ($menuConfig[$mainRole] as $item) {
                        $label = $item['label'];
                        $itemMap[$label] = count($sidebar);
                        $sidebar[] = $item;
                    }
                }

                // Then add extra role items, merging where needed
                foreach ($extraRoles as $role) {
                    if (isset($menuConfig[$role])) {
                        foreach ($menuConfig[$role] as $item) {
                            $label = $item['label'];
                            if (!isset($itemMap[$label])) {
                                // If item doesn't exist, add it
                                $itemMap[$label] = count($sidebar);
                                $sidebar[] = $item;
                            } else {
                                // If item exists, merge subitems
                                $existingIndex = $itemMap[$label];
                                if (isset($item['subitems'])) {
                                    if (!isset($sidebar[$existingIndex]['subitems'])) {
                                        $sidebar[$existingIndex]['subitems'] = [];
                                    }
                                    foreach ($item['subitems'] as $subitem) {
                                        $exists = false;
                                        foreach ($sidebar[$existingIndex]['subitems'] as $existing) {
                                            if ($existing['url'] === $subitem['url']) {
                                                $exists = true;
                                                break;
                                            }
                                        }
                                        if (!$exists) {
                                            $sidebar[$existingIndex]['subitems'][] = $subitem;
                                        }
                                    }
                                }
                                // If current item has a direct URL and existing doesn't, add it
                                if (isset($item['url']) && !isset($sidebar[$existingIndex]['url'])) {
                                    $sidebar[$existingIndex]['url'] = $item['url'];
                                }
                            }
                        }
                    }
                }

                // Finally add universal items
                if (isset($menuConfig['universal'])) {
                    foreach ($menuConfig['universal'] as $item) {
                        $label = $item['label'];
                        if (!isset($itemMap[$label])) {
                            $sidebar[] = $item;
                        } else {
                            $existingIndex = $itemMap[$label];
                            if (isset($item['subitems'])) {
                                if (!isset($sidebar[$existingIndex]['subitems'])) {
                                    $sidebar[$existingIndex]['subitems'] = [];
                                }
                                foreach ($item['subitems'] as $subitem) {
                                    $exists = false;
                                    foreach ($sidebar[$existingIndex]['subitems'] as $existing) {
                                        if ($existing['url'] === $subitem['url']) {
                                            $exists = true;
                                            break;
                                        }
                                    }
                                    if (!$exists) {
                                        $sidebar[$existingIndex]['subitems'][] = $subitem;
                                    }
                                }
                            }
                            // If universal item has a direct URL and existing doesn't, add it
                            if (isset($item['url']) && !isset($sidebar[$existingIndex]['url'])) {
                                $sidebar[$existingIndex]['url'] = $item['url'];
                            }
                        }
                    }
                }

                // Define default dashboards
                $roleDefaultDash = [
                    'admin' => 'admin_dashboard',
                    'teacher' => 'teacher_dashboard',
                    'accountant' => 'accounts_dashboard',
                    'registrar' => 'admissions_dashboard',
                    'headteacher' => 'head_teacher_dashboard',
                    'head_teacher' => 'head_teacher_dashboard',
                    'non_teaching' => 'non_teaching_dashboard',
                    'student' => 'student_dashboard',
                ];

                $default_dashboard = $roleDefaultDash[$mainRole] ?? 'admin_dashboard';

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'sidebar' => array_values($sidebar),
                        'default_dashboard' => $default_dashboard,
                        'mainRole' => $mainRole,
                        'extraRoles' => $extraRoles
                    ]
                ]);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid GET request']);
            }
            break;

        case 'POST':
            if ($action === 'add') {
                echo json_encode($usersApi->create($input));
            } elseif ($action === 'update' && $id) {
                echo json_encode($usersApi->update($id, $input));
            } elseif ($action === 'delete' && $id) {
                echo json_encode($usersApi->delete($id));
            } elseif ($action === 'assign-role' && $id) {
                echo json_encode($usersApi->assignRole($id, $input));
            } elseif ($action === 'assign-permission' && $id) {
                echo json_encode($usersApi->assignPermission($id, $input));
            } elseif ($action === 'bulk_insert') {
                if (!empty($_FILES['file'])) {
                    $result = $bulkHelper->processUploadedFile($_FILES['file']);
                    if ($result['status'] === 'success') {
                        $data = $result['data'];
                        $unique = ['email']; // adjust as needed
                        $insertResult = $bulkHelper->bulkInsert('users', $data, $unique);
                        echo json_encode($insertResult);
                    } else {
                        echo json_encode($result);
                    }
                } else {
                    $data = $input;
                    $unique = ['email'];
                    $insertResult = $bulkHelper->bulkInsert('users', $data, $unique);
                    echo json_encode($insertResult);
                }
                exit;
            } elseif ($action === 'bulk_update') {
                $identifier = 'id';
                $result = $bulkHelper->bulkUpdate('users', $input, $identifier);
                echo json_encode($result);
                exit;
            } elseif ($action === 'bulk_delete') {
                $ids = $input['ids'] ?? [];
                if (empty($ids)) {
                    echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
                    exit;
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "DELETE FROM users WHERE id IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $stmt->execute($ids);
                echo json_encode(['status' => 'success', 'deleted' => $stmt->rowCount()]);
                exit;
            } elseif ($action === 'export') {
                $format = $_GET['format'] ?? 'csv';
                $query = "SELECT * FROM users";
                $stmt = $db->query($query);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                require_once __DIR__ . '/includes/ExportHelper.php';
                $exportHelper = new \App\API\Includes\ExportHelper();
                $exportHelper->export($rows, $format, 'users_export');
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid POST request']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unsupported HTTP method']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
