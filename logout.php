<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Attempt to log logout activity if possible
try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/activity_log.php';
    $emp = $_SESSION['employee_id'] ?? null;
    if (isset($conn) && function_exists('activity_log_event')) {
        activity_log_event($conn, $emp, 'AUTH_LOGOUT', [
            'username' => $_SESSION['username'] ?? null
        ]);
    } elseif (isset($conn) && function_exists('log_activity')) {
        log_activity($conn, $emp, 'logout', [
            'username' => $_SESSION['username'] ?? null
        ]);
    }
    // Remove session from active_sessions table
    try {
        require_once __DIR__ . '/session_activity.php';
        if (isset($conn)) remove_session($conn);
    } catch (Exception $e) {
        error_log('Failed to remove active session: ' . $e->getMessage());
    }
    if (isset($conn)) { $conn->close(); }
} catch (Exception $e) {
    error_log('Failed to record logout activity: ' . $e->getMessage());
}

// Unset all session variables
session_unset();

// Destroy the session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: login.php?logout=success");
exit;
?>

