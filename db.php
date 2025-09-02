<?php
// Database connection settings
// IMPORTANT: For a production environment, store credentials securely outside the webroot
// (e.g., using environment variables or a dedicated, restricted configuration file).

// Attempt to get credentials from environment variables (best practice)
$host = getenv('DB_HOST') ?: "127.0.0.1";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASS') ?: "1671";
$dbname = getenv('DB_NAME') ?: "broman_db";

// Use mysqli_report to turn errors into exceptions for better handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);

    // Set character set to UTF-8
    if (!$conn->set_charset("utf8mb4")) {
        // Log the error, but don't necessarily die immediately unless critical
        error_log("Critical Error: Could not set character set utf8mb4: " . $conn->error);
        // Depending on application needs, you might throw an exception or handle this differently
        // For now, we log it and continue, but this is risky for data integrity.
    }

} catch (mysqli_sql_exception $e) {
    // Log the detailed error for administrators
    error_log("Database connection failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");

    // Display a generic, user-friendly error message
    // In a real application, you might redirect to an error page or display a message within the layout.
    die("Sorry, we are experiencing technical difficulties. Please try again later or contact support.");
}

// Optional: Remove error reporting back to default if needed elsewhere,
// but generally, letting exceptions bubble up is cleaner.
// mysqli_report(MYSQLI_REPORT_OFF);


