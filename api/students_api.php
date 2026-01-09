<?php
// students_api.php
require_once __DIR__ . '/db.php'; // Your DB connection

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch($action) {
    case 'list':
        listStudents();
        break;
    case 'classes':
        getClasses();
        break;
    case 'streams':
        getStreams($_GET['class_id'] ?? null);
        break;
    case 'student':
        getStudent($_GET['id'] ?? 0);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

// --- Fetch students with optional filters ---
function listStudents() {
    global $conn;

    $search = $_GET['search'] ?? '';
    $class = $_GET['class'] ?? '';
    $stream = $_GET['stream'] ?? '';
    $gender = $_GET['gender'] ?? '';
    $status = $_GET['status'] ?? '';

    $sql = "SELECT s.*, c.name AS class_name, st.name AS stream_name 
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN streams st ON s.stream_id = st.id
            WHERE 1";

    $params = [];

    if($search) {
        $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if($class) {
        $sql .= " AND s.class_id = ?";
        $params[] = $class;
    }
    if($stream) {
        $sql .= " AND s.stream_id = ?";
        $params[] = $stream;
    }
    if($gender) {
        $sql .= " AND s.gender = ?";
        $params[] = $gender;
    }
    if($status) {
        $sql .= " AND s.status = ?";
        $params[] = $status;
    }

    $stmt = $conn->prepare($sql);

    if ($params) {
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($students);
}

// --- Fetch classes ---
function getClasses() {
    global $conn;
    $res = $conn->query("SELECT id, name FROM classes ORDER BY name");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}

// --- Fetch streams for a class ---
function getStreams($classId) {
    global $conn;
    if (!$classId) {
        echo json_encode([]);
        return;
    }
    $stmt = $conn->prepare("SELECT id, name FROM streams WHERE class_id = ? ORDER BY name");
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}

// --- Fetch single student details ---
function getStudent($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $student = $res->fetch_assoc();
    echo json_encode($student);
}
?>
