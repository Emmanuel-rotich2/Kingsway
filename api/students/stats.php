<?php
require '../../config/db.php';

try {
    $stats = [
        'total' => (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'active' => (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn(),
        'inactive' => (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status!='active'")->fetchColumn(),
        'gender' => $pdo->query("SELECT gender, COUNT(*) AS total FROM students GROUP BY gender")->fetchAll(PDO::FETCH_ASSOC)
    ];

    echo json_encode($stats);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
