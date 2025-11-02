<?php
// Function to update or create session record
function update_session_activity($conn) {
    if (!isset($_SESSION['employee_id']) || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        return;
    }

    // Check if connection is still valid. Guard ping() in a try/catch because
    // calling ping() on a closed mysqli object will throw an Error.
    $alive = false;
    try {
        if ($conn && is_object($conn) && method_exists($conn, 'ping')) {
            $alive = $conn->ping();
        }
    } catch (Throwable $e) {
        // Treat any error as the connection not being alive
        $alive = false;
    }

    if (!$alive) {
        // Use require (not require_once) so we attempt to (re)create a connection
        // even if db.php was included earlier in the request.
        require __DIR__ . '/db.php';
        // db.php sets $conn; when required inside this function the variable
        // will be available in this scope. If needed, prefer the freshly
        // created $conn from the included file.
    }

    $session_id = session_id();
    $employee_id = (int)$_SESSION['employee_id'];
    
    // Use REPLACE INTO to handle both insert and update
    $sql = "REPLACE INTO active_sessions (session_id, employee_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing session activity statement: " . $conn->error);
        return;
    }
    $stmt->bind_param("si", $session_id, $employee_id);
    $result = $stmt->execute();
    if (!$result) {
        error_log("Error executing session activity update: " . $stmt->error);
    }
    $stmt->close();
}

// Function to clean old sessions
function cleanup_old_sessions($conn) {
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800; // 30 minutes default
    $sql = "DELETE FROM active_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $timeout);
    $stmt->execute();
}

// Function to remove session on logout
function remove_session($conn) {
    if (!isset($_SESSION['employee_id'])) {
        return;
    }
    
    $session_id = session_id();
    $sql = "DELETE FROM active_sessions WHERE session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
}

// Function to check if user is online
function is_user_online($conn, $employee_id) {
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;
    $sql = "SELECT COUNT(*) as active_count FROM active_sessions 
            WHERE employee_id = ? 
            AND last_activity >= DATE_SUB(NOW(), INTERVAL ? SECOND)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $employee_id, $timeout);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['active_count'] > 0);
}