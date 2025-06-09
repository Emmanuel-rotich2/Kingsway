<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db_connection.php';

try {
    // Run maintenance procedures
    $stmt = $db->prepare("CALL sp_run_maintenance()");
    $stmt->execute();
    
    // Log successful execution
    $logFile = __DIR__ . '/logs/maintenance.log';
    $message = date('Y-m-d H:i:s') . " - Maintenance tasks completed successfully\n";
    file_put_contents($logFile, $message, FILE_APPEND);
    
    echo "Maintenance tasks completed successfully\n";
    exit(0);
    
} catch (Exception $e) {
    // Log error
    $logFile = __DIR__ . '/logs/maintenance.log';
    $message = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $message, FILE_APPEND);
    
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 