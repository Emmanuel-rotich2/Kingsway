<?php
require '../db.php';

$data = $_POST;
$id = $data['sessionId'] ?? null;

if ($id) {
    // Update
    $stmt = $db->prepare("UPDATE counseling_sessions SET student_id=?, session_datetime=?, category=?, priority=?, issue_summary=?, session_notes=?, action_plan=?, status=?, follow_up=?, follow_up_date=?, notify_parent=?, confidential=? WHERE id=?");
    $stmt->execute([
        $data['student'], $data['sessionDateTime'], $data['category'], $data['priority'], $data['issue'],
        $data['sessionNotes'], $data['actionPlan'], $data['status'], $data['followUp'], $data['followUpDate'] ?: null,
        isset($data['notifyParent']) ? 1 : 0, isset($data['confidential']) ? 1 : 0, $id
    ]);
} else {
    // Insert
    $stmt = $db->prepare("INSERT INTO counseling_sessions (student_id, session_datetime, category, priority, issue_summary, session_notes, action_plan, status, follow_up, follow_up_date, notify_parent, confidential) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $data['student'], $data['sessionDateTime'], $data['category'], $data['priority'], $data['issue'],
        $data['sessionNotes'], $data['actionPlan'], $data['status'], $data['followUp'], $data['followUpDate'] ?: null,
        isset($data['notifyParent']) ? 1 : 0, isset($data['confidential']) ? 1 : 0
    ]);
}

echo json_encode(['status' => 'success']);
