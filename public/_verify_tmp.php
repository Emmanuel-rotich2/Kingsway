<?php
require_once __DIR__.'/../vendor/autoload.php'; require_once __DIR__.'/../config/DashboardRouter.php'; require_once __DIR__.'/../database/Database.php';
use App\Database\Database; $db=Database::getInstance()->getConnection();
echo "staff ids: ".json_encode($db->query('SELECT id FROM staff ORDER BY id LIMIT 5')->fetchAll(PDO::FETCH_COLUMN))."\n";
