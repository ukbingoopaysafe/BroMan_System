<?php
// Set session timeout to 30 minutes (in seconds)
define('SESSION_TIMEOUT', 1800); // 30 minutes * 60 seconds

// Set session configuration
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', SESSION_TIMEOUT);

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => '/',
    'secure' => true,    // Only transmit over HTTPS
    'httponly' => true,  // Prevent JavaScript access
    'samesite' => 'Lax' // Prevent CSRF attacks
]);