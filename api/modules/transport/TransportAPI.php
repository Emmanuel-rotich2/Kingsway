<?php
namespace App\API\Modules\transport;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

class TransportAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('transport');
    }

    // List records with pagination and filtering
    public function list($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE r.name LIKE ? OR v.registration_no LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM transport_routes r
                LEFT JOIN vehicles v ON r.vehicle_id = v.id
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    r.*,
                    v.registration_no,
                    v.model,
                    v.capacity,
                    CONCAT(d.first_name, ' ', d.last_name) as driver_name,
                    COUNT(DISTINCT ta.student_id) as student_count
                FROM transport_routes r
                LEFT JOIN vehicles v ON r.vehicle_id = v.id
                LEFT JOIN drivers d ON r.driver_id = d.id
                LEFT JOIN transport_assignments ta ON r.id = ta.route_id
                $where
                GROUP BY r.id
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'routes' => $routes,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single record
    public function get($id)
    {
        try {
            $sql = "
                SELECT 
                    r.*,
                    v.registration_no,
                    v.model,
                    v.capacity,
                    CONCAT(d.first_name, ' ', d.last_name) as driver_name,
                    d.phone as driver_phone,
                    d.license_no
                FROM transport_routes r
                LEFT JOIN vehicles v ON r.vehicle_id = v.id
                LEFT JOIN drivers d ON r.driver_id = d.id
                WHERE r.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$route) {
                return $this->response(['status' => 'error', 'message' => 'Route not found'], 404);
            }

            // Get students assigned to this route
            $sql = "
                SELECT 
                    s.id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    c.name as class_name,
                    cs.stream_name,
                    ta.pickup_point,
                    ta.dropoff_point
                FROM transport_assignments ta
                JOIN students s ON ta.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                WHERE ta.route_id = ?
                ORDER BY s.admission_no
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $route['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $route]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new record
    public function create($data)
    {
        try {
            $required = ['name', 'description', 'start_point', 'end_point'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO transport_routes (
                    name,
                    description,
                    start_point,
                    end_point,
                    vehicle_id,
                    driver_id,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['start_point'],
                $data['end_point'],
                $data['vehicle_id'] ?? null,
                $data['driver_id'] ?? null,
                $data['status'] ?? 'active'
            ]);

            $id = $this->db->lastInsertId();

            return $this->response([
                'status' => 'success',
                'message' => 'Route created successfully',
                'data' => ['id' => $id]
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update record
    public function update($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM transport_routes WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'Route not found'], 404);
            }

            $updates = [];
            $params = [];
            $allowedFields = [
                'name', 'description', 'start_point', 'end_point',
                'vehicle_id', 'driver_id', 'status'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE transport_routes SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Route updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete record
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE transport_routes SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Route not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Route deleted successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints
    public function handleCustomGet($id, $action, $params)
    {
        try {
            switch ($action) {
                case 'schedule':
                    return $this->getRouteSchedule($id);
                case 'maintenance':
                    return $this->getVehicleMaintenance($id);
                case 'attendance':
                    return $this->getDriverAttendance($id);
                case 'students':
                    return $this->getRouteStudents($id);
                default:
                    throw new Exception('Invalid action specified');
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom POST endpoints
    public function handleCustomPost($id, $action, $data)
    {
        try {
            $this->beginTransaction();
            
            switch ($action) {
                case 'schedule':
                    $result = $this->updateRouteSchedule($id, $data);
                    break;
                case 'maintenance':
                    $result = $this->logVehicleMaintenance($id, $data);
                    break;
                case 'attendance':
                    $result = $this->markDriverAttendance($id, $data);
                    break;
                default:
                    throw new Exception('Invalid action specified');
            }

            $this->commit();
            return $result;

        } catch (Exception $e) {
            $this->rollBack();
            return $this->handleException($e);
        }
    }

    public function getRoutes($params = [])
    {
        try {
            $sql = "
                SELECT 
                    r.*,
                    v.registration_no,
                    v.model,
                    v.capacity,
                    CONCAT(d.first_name, ' ', d.last_name) as driver_name,
                    COUNT(DISTINCT ta.student_id) as student_count
                FROM transport_routes r
                LEFT JOIN vehicles v ON r.vehicle_id = v.id
                LEFT JOIN drivers d ON r.driver_id = d.id
                LEFT JOIN transport_assignments ta ON r.id = ta.route_id
                WHERE r.status = 'active'
                GROUP BY r.id
                ORDER BY r.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $routes]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getVehicles($params = [])
    {
        try {
            $sql = "
                SELECT 
                    v.*,
                    CONCAT(d.first_name, ' ', d.last_name) as assigned_driver,
                    r.name as current_route
                FROM vehicles v
                LEFT JOIN drivers d ON v.current_driver_id = d.id
                LEFT JOIN transport_routes r ON v.id = r.vehicle_id
                WHERE v.status = 'active'
                ORDER BY v.registration_no
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $vehicles]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getDrivers($params = [])
    {
        try {
            $sql = "
                SELECT 
                    d.*,
                    v.registration_no as assigned_vehicle,
                    r.name as current_route
                FROM drivers d
                LEFT JOIN vehicles v ON d.id = v.current_driver_id
                LEFT JOIN transport_routes r ON d.id = r.driver_id
                WHERE d.status = 'active'
                ORDER BY d.first_name, d.last_name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $drivers]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignRoute($data)
    {
        try {
            $required = ['student_id', 'route_id', 'pickup_point', 'dropoff_point'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO transport_assignments (
                    student_id,
                    route_id,
                    pickup_point,
                    dropoff_point,
                    status
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    pickup_point = VALUES(pickup_point),
                    dropoff_point = VALUES(dropoff_point),
                    status = VALUES(status)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['student_id'],
                $data['route_id'],
                $data['pickup_point'],
                $data['dropoff_point'],
                $data['status'] ?? 'active'
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Route assigned successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignVehicle($data)
    {
        try {
            $required = ['route_id', 'vehicle_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "UPDATE transport_routes SET vehicle_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['vehicle_id'], $data['route_id']]);

            return $this->response([
                'status' => 'success',
                'message' => 'Vehicle assigned successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignDriver($data)
    {
        try {
            $required = ['route_id', 'driver_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "UPDATE transport_routes SET driver_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['driver_id'], $data['route_id']]);

            return $this->response([
                'status' => 'success',
                'message' => 'Driver assigned successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getRouteSchedule($id)
    {
        // Get route details
        $stmt = $this->db->prepare("SELECT id, name FROM transport_routes WHERE id = ?");
        $stmt->execute([$id]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$route) {
            return $this->response(['status' => 'error', 'message' => 'Route not found'], 404);
        }

        // Get schedule
        $sql = "
            SELECT 
                rs.*,
                COUNT(DISTINCT ta.student_id) as student_count
            FROM route_schedules rs
            LEFT JOIN transport_assignments ta ON rs.route_id = ta.route_id AND ta.status = 'active'
            WHERE rs.route_id = ?
            GROUP BY rs.id
            ORDER BY rs.day_of_week, rs.pickup_time
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->response([
            'status' => 'success',
            'data' => [
                'route' => $route,
                'schedule' => $schedule
            ]
        ]);
    }

    private function getVehicleMaintenance($id)
    {
        // Get vehicle details
        $stmt = $this->db->prepare("SELECT id, registration_no FROM vehicles WHERE id = ?");
        $stmt->execute([$id]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            return $this->response(['status' => 'error', 'message' => 'Vehicle not found'], 404);
        }

        // Get maintenance records
        $sql = "
            SELECT 
                m.*,
                u.username as recorded_by
            FROM vehicle_maintenance m
            LEFT JOIN users u ON m.recorded_by = u.id
            WHERE m.vehicle_id = ?
            ORDER BY m.maintenance_date DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->response([
            'status' => 'success',
            'data' => [
                'vehicle' => $vehicle,
                'maintenance' => $maintenance
            ]
        ]);
    }

    private function getDriverAttendance($id)
    {
        // Get driver details
        $stmt = $this->db->prepare("SELECT id, staff_no, first_name, last_name FROM drivers WHERE id = ?");
        $stmt->execute([$id]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$driver) {
            return $this->response(['status' => 'error', 'message' => 'Driver not found'], 404);
        }

        // Get attendance records
        $sql = "
            SELECT 
                da.*,
                u.username as recorded_by
            FROM driver_attendance da
            LEFT JOIN users u ON da.recorded_by = u.id
            WHERE da.driver_id = ?
            ORDER BY da.date DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->response([
            'status' => 'success',
            'data' => [
                'driver' => $driver,
                'attendance' => $attendance
            ]
        ]);
    }

    private function getRouteStudents($id)
    {
        // Get route details
        $stmt = $this->db->prepare("SELECT id, name FROM transport_routes WHERE id = ?");
        $stmt->execute([$id]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$route) {
            return $this->response(['status' => 'error', 'message' => 'Route not found'], 404);
        }

        // Get assigned students
        $sql = "
            SELECT 
                s.id,
                s.admission_no,
                s.first_name,
                s.last_name,
                c.name as class_name,
                cs.stream_name,
                ta.pickup_point,
                ta.dropoff_point,
                ta.status
            FROM transport_assignments ta
            JOIN students s ON ta.student_id = s.id
            JOIN class_streams cs ON s.stream_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            WHERE ta.route_id = ? AND ta.status = 'active'
            ORDER BY c.name, cs.stream_name, s.admission_no
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->response([
            'status' => 'success',
            'data' => [
                'route' => $route,
                'students' => $students
            ]
        ]);
    }

    private function updateRouteSchedule($id, $data)
    {
        // Check if route exists
        $stmt = $this->db->prepare("SELECT id, name FROM transport_routes WHERE id = ?");
        $stmt->execute([$id]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$route) {
            return $this->response(['status' => 'error', 'message' => 'Route not found'], 404);
        }

        if (!isset($data['schedule']) || !is_array($data['schedule'])) {
            return $this->response([
                'status' => 'error',
                'message' => 'Invalid schedule data'
            ], 400);
        }

        try {
            $this->db->beginTransaction();

            // Delete existing schedule
            $stmt = $this->db->prepare("DELETE FROM route_schedules WHERE route_id = ?");
            $stmt->execute([$id]);

            // Insert new schedule
            $sql = "
                INSERT INTO route_schedules (
                    route_id,
                    day_of_week,
                    pickup_time,
                    dropoff_time
                ) VALUES (?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            foreach ($data['schedule'] as $schedule) {
                $stmt->execute([
                    $id,
                    $schedule['day_of_week'],
                    $schedule['pickup_time'],
                    $schedule['dropoff_time']
                ]);
            }

            $this->db->commit();

            $this->logAction('update', $id, "Updated schedule for route: {$route['name']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Route schedule updated successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function logVehicleMaintenance($id, $data)
    {
        // Check if vehicle exists
        $stmt = $this->db->prepare("SELECT id, registration_no FROM vehicles WHERE id = ?");
        $stmt->execute([$id]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            return $this->response(['status' => 'error', 'message' => 'Vehicle not found'], 404);
        }

        // Validate required fields
        $required = ['maintenance_type', 'maintenance_date', 'cost'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            return $this->response([
                'status' => 'error',
                'message' => 'Missing required fields',
                'fields' => $missing
            ], 400);
        }

        // Insert maintenance record
        $sql = "
            INSERT INTO vehicle_maintenance (
                vehicle_id,
                maintenance_type,
                maintenance_date,
                cost,
                description,
                next_due_date,
                recorded_by,
                recorded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $id,
            $data['maintenance_type'],
            $data['maintenance_date'],
            $data['cost'],
            $data['description'] ?? null,
            $data['next_due_date'] ?? null,
            $_SESSION['user_id'] ?? null
        ]);

        $maintenanceId = $this->db->lastInsertId();

        // Handle document uploads if provided
        if (isset($_FILES['documents'])) {
            $uploadResult = $this->handleFileUpload(
                'documents',
                'maintenance_documents',
                $vehicle['registration_no'] . '_' . date('Ymd')
            );
            if ($uploadResult['status'] === 'success') {
                $stmt = $this->db->prepare("UPDATE vehicle_maintenance SET documents_path = ? WHERE id = ?");
                $stmt->execute([$uploadResult['path'], $maintenanceId]);
            }
        }

        $this->logAction('create', $maintenanceId, "Logged maintenance for vehicle: {$vehicle['registration_no']}");

        return $this->response([
            'status' => 'success',
            'message' => 'Maintenance record logged successfully',
            'data' => ['id' => $maintenanceId]
        ], 201);
    }

    private function markDriverAttendance($id, $data)
    {
        // Check if driver exists
        $stmt = $this->db->prepare("SELECT id, staff_no FROM drivers WHERE id = ?");
        $stmt->execute([$id]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$driver) {
            return $this->response(['status' => 'error', 'message' => 'Driver not found'], 404);
        }

        // Validate required fields
        $required = ['date', 'status'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            return $this->response([
                'status' => 'error',
                'message' => 'Missing required fields',
                'fields' => $missing
            ], 400);
        }

        // Check if attendance already marked for this date
        $stmt = $this->db->prepare("
            SELECT id FROM driver_attendance 
            WHERE driver_id = ? AND date = ?
        ");
        $stmt->execute([$id, $data['date']]);
        if ($stmt->fetch()) {
            return $this->response([
                'status' => 'error',
                'message' => 'Attendance already marked for this date'
            ], 400);
        }

        // Insert attendance record
        $sql = "
            INSERT INTO driver_attendance (
                driver_id,
                date,
                status,
                check_in_time,
                check_out_time,
                notes,
                recorded_by,
                recorded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $id,
            $data['date'],
            $data['status'],
            $data['check_in_time'] ?? null,
            $data['check_out_time'] ?? null,
            $data['notes'] ?? null,
            $_SESSION['user_id'] ?? null
        ]);

        $attendanceId = $this->db->lastInsertId();

        $this->logAction('create', $attendanceId, "Marked attendance for driver: {$driver['staff_no']}");

        return $this->response([
            'status' => 'success',
            'message' => 'Attendance marked successfully',
            'data' => ['id' => $attendanceId]
        ], 201);
    }

    private function handleFileUpload($fileKey, $directory, $prefix = '')
    {
        try {
            if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed');
            }

            $file = $_FILES[$fileKey];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $prefix ? $prefix . '.' . $ext : uniqid() . '.' . $ext;
            $uploadDir = $this->getUploadPath($directory);
            $targetPath = $uploadDir . '/' . $filename;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return [
                    'status' => 'success',
                    'path' => "$directory/$filename",
                    'filename' => $filename
                ];
            }

            throw new Exception('Failed to move uploaded file');
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function getUploadPath($directory)
    {
        return dirname(__DIR__, 3) . '/uploads/' . $directory;
    }
}
