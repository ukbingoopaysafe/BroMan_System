<?php
// Small flash message helper with short TTL to tolerate duplicate quick reads
// Requires session to be started already (or call ensure_secure_session_started() before use)

if (!function_exists('add_flash')) {
    function add_flash(string $message, int $ttl_seconds = 5): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // best-effort start
            @session_start();
        }
        if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        // id for reference if needed
        $id = bin2hex(random_bytes(8));
        $_SESSION['flash_messages'][$id] = [
            'message' => $message,
            'expires_at' => time() + $ttl_seconds,
        ];
        return $id;
    }
}

if (!function_exists('get_flash_messages')) {
    function get_flash_messages(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $now = time();
        $out = [];
        if (!empty($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages'])) {
            foreach ($_SESSION['flash_messages'] as $id => $item) {
                if (!is_array($item)) { unset($_SESSION['flash_messages'][$id]); continue; }
                if (isset($item['expires_at']) && $item['expires_at'] >= $now) {
                    $out[$id] = $item['message'];
                } else {
                    // expired — remove
                    unset($_SESSION['flash_messages'][$id]);
                }
            }
            // If none remain, remove the container
            if (empty($_SESSION['flash_messages'])) { unset($_SESSION['flash_messages']); }
        }
        return $out;
    }
}

if (!function_exists('clear_flash')) {
    function clear_flash(string $id): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['flash_messages'][$id])) {
            unset($_SESSION['flash_messages'][$id]);
        }
        if (empty($_SESSION['flash_messages'])) { unset($_SESSION['flash_messages']); }
    }
}

?>