<?php
// api/sidebar_render.php
header('Content-Type: text/html; charset=UTF-8');

// Accept JSON POST and decode to PHP array
$data = json_decode(file_get_contents('php://input'), true);
$sidebar_items = $data['sidebar_items'] ?? [];

// Defensive: if sidebar_items is a JSON string, decode it
if (is_string($sidebar_items)) {
    $sidebar_items = json_decode($sidebar_items, true);
}

// Now $sidebar_items is a PHP array as expected by sidebar.php
include __DIR__ . '/../components/global/sidebar.php';
