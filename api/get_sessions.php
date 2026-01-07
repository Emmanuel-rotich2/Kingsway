<?php
require '../db.php';

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$date = $_GET['date'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = intval($_GET['limit'] ?? 10);
$offset = ($page - 1) * $limit;

// Base query
$query = "SELECT cs.*, s.first_name, s.last_name, c.name as class_name
          FROM counseling_sessions cs
          JOIN students s ON cs.student_id = s.id
          JOIN classes c ON s.class_id = c.id
          WHERE 1=1";
$params = [];

if ($search) { $query .= " AND CONCAT(s.first_name,' ',s.last_name) LIKE ?"; $params[] = "%$search%"; }
if ($status) { $query .= " AND cs.status = ?"; $params[] = $status; }
if ($category) { $query .= " AND cs.category = ?"; $params[] = $category; }
if ($date) { $query .= " AND DATE(cs.session_datetime) = ?"; $params[] = $date; }

// Count total
$stmtCount = $db->prepare(str_replace('SELECT cs.*, s.first_name, s.last_name, c.name as class_name', 'SELECT COUNT(*) as total', $query));
$stmtCount->execute($params);
$total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

// Limit & order
$query .= " ORDER BY cs.session_datetime DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'sessions' => $sessions,
    'total' => $total,
    'page' => $page,
    'pages' => ceil($total / $limit)
]);
