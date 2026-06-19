<?php
// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "school_db";

// Create connection (initially without database selection to allow creation)
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$db_check = $conn->query("CREATE DATABASE IF NOT EXISTS `$database` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
if (!$db_check) {
    die("Database creation failed: " . $conn->error);
}

// Select the database
$conn->select_db($database);
$conn->set_charset("utf8mb4");

// Auto-import database.sql if 'users' table does not exist
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check && $table_check->num_rows == 0) {
    $sql_file = __DIR__ . '/database.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        
        // mysqli::multi_query is used to run the multiple queries in database.sql
        if ($conn->multi_query($sql)) {
            // We must clear the results of multi_query to avoid "Commands out of sync" error
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        } else {
            die("Error importing database schema: " . $conn->error);
        }
    }
}

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>