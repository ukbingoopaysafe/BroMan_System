<?php
/**
 * Rate Limiter - Brute Force Protection System
 * 
 * This module provides protection against brute force attacks by:
 * - Tracking failed login attempts per IP address
 * - Tracking failed login attempts per username
 * - Automatically blocking IPs after too many failed attempts
 * - Logging all attempts for security monitoring
 * 
 * @author BroMan Security Module
 * @version 1.0
 */

// Configuration constants
define('MAX_IP_ATTEMPTS', 5);           // Max failed attempts per IP before blocking
define('MAX_USERNAME_ATTEMPTS', 10);    // Max failed attempts per username before blocking
define('IP_BLOCK_DURATION', 15 * 60);   // Block duration for IP (15 minutes)
define('USERNAME_BLOCK_DURATION', 30 * 60); // Block duration for username (30 minutes)
define('ATTEMPT_WINDOW', 15 * 60);      // Time window to count attempts (15 minutes)

/**
 * Get the real client IP address (handles proxies like Cloudflare)
 */
function get_client_ip(): string {
    // Cloudflare passes the real IP in CF-Connecting-IP header
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }
    
    // Check for forwarded IP (other proxies)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }
    
    // Check X-Real-IP header
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }
    
    // Default to REMOTE_ADDR
    return filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

/**
 * Check if an IP is currently blocked
 */
function is_ip_blocked(mysqli $conn, string $ip): bool {
    $sql = "SELECT id FROM blocked_ips WHERE ip_address = ? AND blocked_until > NOW()";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $blocked = $result->num_rows > 0;
    $stmt->close();
    
    return $blocked;
}

/**
 * Get the number of failed attempts for an IP in the recent time window
 */
function get_ip_failed_attempts(mysqli $conn, string $ip): int {
    $window_start = date('Y-m-d H:i:s', time() - ATTEMPT_WINDOW);
    $sql = "SELECT COUNT(*) as count FROM login_attempts 
            WHERE ip_address = ? AND attempt_time > ? AND success = 0";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    
    $stmt->bind_param('ss', $ip, $window_start);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)($row['count'] ?? 0);
}

/**
 * Get the number of failed attempts for a username in the recent time window
 */
function get_username_failed_attempts(mysqli $conn, string $username): int {
    $window_start = date('Y-m-d H:i:s', time() - ATTEMPT_WINDOW);
    $sql = "SELECT COUNT(*) as count FROM login_attempts 
            WHERE username = ? AND attempt_time > ? AND success = 0";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    
    $stmt->bind_param('ss', $username, $window_start);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)($row['count'] ?? 0);
}

/**
 * Record a login attempt (successful or failed)
 */
function record_login_attempt(mysqli $conn, string $ip, ?string $username, bool $success): bool {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $sql = "INSERT INTO login_attempts (ip_address, username, success, user_agent) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Rate Limiter: Failed to prepare statement for recording attempt");
        return false;
    }
    
    $success_int = $success ? 1 : 0;
    $stmt->bind_param('ssis', $ip, $username, $success_int, $user_agent);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Block an IP address
 */
function block_ip(mysqli $conn, string $ip, int $duration = null): bool {
    $duration = $duration ?? IP_BLOCK_DURATION;
    $blocked_until = date('Y-m-d H:i:s', time() + $duration);
    
    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle existing entries
    $sql = "INSERT INTO blocked_ips (ip_address, blocked_until) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE blocked_until = ?, blocked_at = NOW()";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Rate Limiter: Failed to prepare statement for blocking IP");
        return false;
    }
    
    $stmt->bind_param('sss', $ip, $blocked_until, $blocked_until);
    $result = $stmt->execute();
    $stmt->close();
    
    // Log the block
    error_log("Rate Limiter: Blocked IP $ip until $blocked_until");

    // Activity log (security event)
    if (file_exists(__DIR__ . '/activity_log.php')) {
        require_once __DIR__ . '/activity_log.php';
        if (function_exists('activity_log_event')) {
            activity_log_event($conn, null, 'AUTH_USER_BLOCKED', [
                'reason' => 'ip_attempts_exceeded',
                'ip_address' => $ip,
                'blocked_until' => $blocked_until
            ]);
        } elseif (function_exists('log_activity')) {
            log_activity($conn, null, 'auth_user_blocked', [
                'reason' => 'ip_attempts_exceeded',
                'ip_address' => $ip,
                'blocked_until' => $blocked_until
            ]);
        }
    }
    
    return $result;
}

/**
 * Get remaining block time for an IP (in seconds)
 */
function get_ip_block_remaining(mysqli $conn, string $ip): int {
    $sql = "SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) as remaining 
            FROM blocked_ips WHERE ip_address = ? AND blocked_until > NOW()";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return max(0, (int)($row['remaining'] ?? 0));
}

/**
 * Clean up old login attempts (older than 24 hours)
 * Call this periodically (e.g., via cron or on random requests)
 */
function cleanup_old_attempts(mysqli $conn): void {
    // Only run cleanup occasionally (1% chance per request)
    if (rand(1, 100) > 1) return;
    
    $sql = "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $conn->query($sql);
    
    $sql = "DELETE FROM blocked_ips WHERE blocked_until < NOW()";
    $conn->query($sql);
}

/**
 * Main rate limit check - call this before processing login
 * Returns array with 'allowed' (bool), 'message' (string), and 'remaining_time' (int seconds)
 */
function check_rate_limit(mysqli $conn, ?string $username = null): array {
    $ip = get_client_ip();
    
    // Run cleanup occasionally
    cleanup_old_attempts($conn);
    
    // Check if IP is blocked
    if (is_ip_blocked($conn, $ip)) {
        $remaining = get_ip_block_remaining($conn, $ip);
        $minutes = ceil($remaining / 60);
        return [
            'allowed' => false,
            'message' => "تم حظر عنوان IP الخاص بك مؤقتاً. حاول مرة أخرى بعد {$minutes} دقيقة.",
            'remaining_time' => $remaining,
            'reason' => 'ip_blocked'
        ];
    }
    
    // Check IP attempt count
    $ip_attempts = get_ip_failed_attempts($conn, $ip);
    if ($ip_attempts >= MAX_IP_ATTEMPTS) {
        block_ip($conn, $ip);
        $minutes = ceil(IP_BLOCK_DURATION / 60);
        return [
            'allowed' => false,
            'message' => "تم حظر عنوان IP الخاص بك لمحاولات تسجيل دخول فاشلة متعددة. حاول بعد {$minutes} دقيقة.",
            'remaining_time' => IP_BLOCK_DURATION,
            'reason' => 'ip_attempts_exceeded'
        ];
    }
    
    // Check username attempt count (if username provided)
    if ($username) {
        $username_attempts = get_username_failed_attempts($conn, $username);
        if ($username_attempts >= MAX_USERNAME_ATTEMPTS) {
            $minutes = ceil(USERNAME_BLOCK_DURATION / 60);
            return [
                'allowed' => false,
                'message' => "تم تعليق هذا الحساب مؤقتاً لمحاولات تسجيل دخول فاشلة متعددة. حاول بعد {$minutes} دقيقة.",
                'remaining_time' => USERNAME_BLOCK_DURATION,
                'reason' => 'username_attempts_exceeded'
            ];
        }
    }
    
    // Calculate remaining attempts
    $remaining_ip_attempts = MAX_IP_ATTEMPTS - $ip_attempts;
    
    return [
        'allowed' => true,
        'message' => '',
        'remaining_attempts' => $remaining_ip_attempts,
        'reason' => null
    ];
}

/**
 * Handle failed login - record and check if blocking needed
 */
function handle_failed_login(mysqli $conn, ?string $username = null): array {
    $ip = get_client_ip();
    
    // Record the failed attempt
    record_login_attempt($conn, $ip, $username, false);

    // Check if we need to block now
    $ip_attempts = get_ip_failed_attempts($conn, $ip);
    if ($ip_attempts >= MAX_IP_ATTEMPTS) {
        block_ip($conn, $ip);
    }

    if ($username) {
        $username_attempts = get_username_failed_attempts($conn, $username);
        if ($username_attempts >= MAX_USERNAME_ATTEMPTS) {
            if (file_exists(__DIR__ . '/activity_log.php')) {
                require_once __DIR__ . '/activity_log.php';
                if (function_exists('activity_log_event')) {
                    activity_log_event($conn, null, 'AUTH_USER_BLOCKED', [
                        'reason' => 'username_attempts_exceeded',
                        'username' => $username
                    ]);
                } elseif (function_exists('log_activity')) {
                    log_activity($conn, null, 'auth_user_blocked', [
                        'reason' => 'username_attempts_exceeded',
                        'username' => $username
                    ]);
                }
            }
        }
    }
    
    $remaining = MAX_IP_ATTEMPTS - $ip_attempts;

    // Activity log (failed login)
    if (file_exists(__DIR__ . '/activity_log.php')) {
        require_once __DIR__ . '/activity_log.php';
        $resolved_id = null;
        if (function_exists('activity_resolve_employee_id')) {
            $resolved_id = activity_resolve_employee_id($conn, $username);
        }
        if (function_exists('activity_log_event')) {
            activity_log_event($conn, $resolved_id, 'AUTH_LOGIN_FAILED', [
                'username' => $username,
                'ip_address' => $ip,
                'attempts_remaining' => max(0, $remaining),
                'unauthenticated' => $resolved_id === null
            ]);
        } elseif (function_exists('log_activity')) {
            log_activity($conn, $resolved_id, 'auth_login_failed', [
                'username' => $username,
                'ip_address' => $ip,
                'attempts_remaining' => max(0, $remaining),
                'unauthenticated' => $resolved_id === null
            ]);
        }
    }
    
    return [
        'attempts_remaining' => max(0, $remaining),
        'blocked' => $remaining <= 0
    ];
}

/**
 * Handle successful login - record and clear blocks
 */
function handle_successful_login(mysqli $conn, string $username): void {
    $ip = get_client_ip();
    
    // Record successful login
    record_login_attempt($conn, $ip, $username, true);
    
    // Optionally: Clear IP block on successful login (uncomment if desired)
    // $sql = "DELETE FROM blocked_ips WHERE ip_address = ?";
    // $stmt = $conn->prepare($sql);
    // $stmt->bind_param('s', $ip);
    // $stmt->execute();
    // $stmt->close();
}

/**
 * Get recent login attempts for monitoring (admin function)
 */
function get_recent_attempts(mysqli $conn, int $limit = 100): array {
    $sql = "SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $attempts;
}

/**
 * Get currently blocked IPs for monitoring (admin function)
 */
function get_blocked_ips(mysqli $conn): array {
    $sql = "SELECT * FROM blocked_ips WHERE blocked_until > NOW() ORDER BY blocked_at DESC";
    $result = $conn->query($sql);
    if (!$result) return [];
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>
