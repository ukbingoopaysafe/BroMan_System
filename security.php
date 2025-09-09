<?php
// Centralized security helpers: session hardening and CSRF utilities

// Configure strict session cookie parameters. Call before session_start().
function configure_secure_session_cookies(): void {
    // Determine if connection is HTTPS (fallback to true for production deployment guidance)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    $params = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https, // Ensure HTTPS in production deployments
        'httponly' => true,
        'samesite' => 'Strict'
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        // Fallback for older PHP versions if needed
        session_set_cookie_params($params['lifetime'], $params['path'].'; samesite='.$params['samesite'], $params['domain'], $params['secure'], $params['httponly']);
    }
}

// Ensure session is started with hardened cookies
function ensure_secure_session_started(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Ensure local session save path is writable to avoid OS temp permission issues
        $localTmp = __DIR__ . '/tmp_sessions';
        if (!is_dir($localTmp)) {
            @mkdir($localTmp, 0755, true);
        }
        if (is_dir($localTmp) && is_writable($localTmp)) {
            session_save_path($localTmp);
        }

        configure_secure_session_cookies();
        session_start();
    }
}

// CSRF utilities
if (!function_exists('generateCSRFToken')) {
function generateCSRFToken(): string {
    ensure_secure_session_started();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
}

if (!function_exists('validateCSRFToken')) {
function validateCSRFToken(?string $token): bool {
    ensure_secure_session_started();
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}
}

// Convenience: render hidden input for CSRF
if (!function_exists('csrf_input')) {
function csrf_input(): string {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
}

?>

