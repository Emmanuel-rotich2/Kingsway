<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'init_session') {
    // Initialize session with user data
    $_SESSION['user_id'] = $_POST['user_id'];
    $_SESSION['username'] = $_POST['username'];
    $_SESSION['roles'] = $_POST['roles'];
    $_SESSION['display_name'] = $_POST['display_name'];
    $_SESSION['permissions'] = $_POST['permissions'];
    
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
} 