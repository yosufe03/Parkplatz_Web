<?php
/**
 * Database Connection
 * Uses configuration from config.php
 */

// Load config if not already loaded
if (!isset($config) || !is_array($config)) {
    $config = include __DIR__ . '/../config.php';
}

// Try to connect to database
try {
    $conn = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_password'],
        $config['db_name']
    );

    if ($conn->connect_error) {
        throw new Exception("Connection Error: " . $conn->connect_error);
    }

    // Set UTF-8 charset
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());

    // For development - show helpful message
    if ($config['app_debug']) {
        die("Database Connection Failed: " . htmlspecialchars($e->getMessage()) .
            "<br>Make sure MySQL/MariaDB is running and configured correctly.");
    } else {
        die("Database connection failed. Please try again later.");
    }
}


