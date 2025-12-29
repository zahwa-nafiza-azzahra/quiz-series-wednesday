<?php
/**
 * Simple database connector for the Wednesday quiz app.
 * Adjust the credentials below to match your local MySQL/MariaDB setup.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quiz_wednesday');

/**
 * Get a mysqli connection or exit with a readable error.
 *
 * @return mysqli
 */
function getDbConnection(): mysqli
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // Provide a clear error for debugging; in production replace with graceful handling.
        die('Database connection failed: ' . $conn->connect_error);
    }

    // Ensure we consistently use UTF-8.
    $conn->set_charset('utf8mb4');

    return $conn;
}

