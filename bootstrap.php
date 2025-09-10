<?php
// Central bootstrap: start secure session and load core config before any output.
// Include this at the very top of pages (before any HTML) to ensure session
// parameters are applied and the session is started early, avoiding race
// conditions where async requests arrive before session data is flushed.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

// Start secure session early
ensure_secure_session_started();

// Start output buffering to reduce the chance of "headers already sent" issues
// in files that may accidentally emit whitespace before including bootstraps.
// This is a safe, low-risk fallback. Individual pages may call ob_end_flush()
// later if they want explicit control.
if (!headers_sent()) {
    ob_start();
}

// Optional: expose a small helper to check readiness
function broman_session_id()
{
    return session_id();
}

?>
