<?php
require '../db.php';

$summary = [
    'total' => $db->query("SELECT COUNT(*) FROM counseling_sessions")->fetchColumn(),
    'scheduled' => $db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='scheduled'")->fetchColumn(),
    'completed' => $db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='completed'")->fetchColumn(),
    'active' => $db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='scheduled'")->fetchColumn()
];

echo json_encode($summary);
