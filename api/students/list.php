<?php
require '../../config/db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$gender = $_GET['gender'] ?? '';
$class  = $_GET['class'] ?? ''; // optional

$where = "WHERE 1";
$params = [];

// Search filter
if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR admission_no LIKE ? OR upi_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Gender filter
if ($gender) {
    $where .= " AND gender = ?";
    $params[] = $gender;
}

// Class/Stream filter (if you have it in DB)
if ($class) {
    $where .= " AND stream_id = ?";
    $params[] = $class;
}

// Fetch students
$sql = "SELECT * FROM students $where ORDER BY admission_no DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$countSql = "SELECT COUNT(*) FROM students $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Return JSON
echo json_encode([
    'data' => $students,
    'total' => $total
]);
