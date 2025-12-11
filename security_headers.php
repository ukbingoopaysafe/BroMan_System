<?php
/**
 * Security Headers Module
 * 
 * Sends comprehensive security headers to protect against common web vulnerabilities.
 * Include this file at the very beginning of all public-facing PHP pages.
 * 
 * @author BroMan Security Module
 * @version 1.0
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'security_headers.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Send all security headers
 * Call this function before any output
 */
function send_security_headers(): void {
    // Only send headers if not already sent
    if (headers_sent()) {
        return;
    }
    
    // Determine if HTTPS
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    // ============================================
    // CONTENT SECURITY POLICY
    // ============================================
    // Restricts sources for scripts, styles, images, etc.
    $csp_directives = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://maps.googleapis.com https://maps.gstatic.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "img-src 'self' data: blob: https://*.googleapis.com https://*.gstatic.com https://*.google.com",
        "font-src 'self' https://fonts.gstatic.com",
        "connect-src 'self' https://*.googleapis.com",
        "frame-src 'self' https://www.google.com https://maps.google.com",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'"
    ];
    header("Content-Security-Policy: " . implode('; ', $csp_directives));
    
    // ============================================
    // XSS PROTECTION
    // ============================================
    // Legacy header for older browsers
    header("X-XSS-Protection: 1; mode=block");
    
    // ============================================
    // CONTENT TYPE OPTIONS
    // ============================================
    // Prevents MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // ============================================
    // FRAME OPTIONS
    // ============================================
    // Prevents clickjacking by disallowing framing
    header("X-Frame-Options: SAMEORIGIN");
    
    // ============================================
    // REFERRER POLICY
    // ============================================
    // Controls referrer information sent with requests
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // ============================================
    // PERMISSIONS POLICY
    // ============================================
    // Controls browser features the site can use
    $permissions = [
        "accelerometer=()",
        "camera=()",
        "geolocation=(self)",
        "gyroscope=()",
        "magnetometer=()",
        "microphone=()",
        "payment=()",
        "usb=()"
    ];
    header("Permissions-Policy: " . implode(', ', $permissions));
    
    // ============================================
    // STRICT TRANSPORT SECURITY (HSTS)
    // ============================================
    // Only send HSTS header over HTTPS
    if ($is_https) {
        // max-age=31536000 = 1 year
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
    
    // ============================================
    // CACHE CONTROL FOR SENSITIVE PAGES
    // ============================================
    // Prevent caching of authenticated pages
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
    }
    
    // ============================================
    // CROSS-ORIGIN POLICIES
    // ============================================
    header("Cross-Origin-Opener-Policy: same-origin");
    header("Cross-Origin-Resource-Policy: same-origin");
}

/**
 * Check if request is coming through Cloudflare
 */
function is_cloudflare_request(): bool {
    // Cloudflare always sends CF-Connecting-IP header
    return !empty($_SERVER['HTTP_CF_CONNECTING_IP']);
}

/**
 * Get Cloudflare country code (if available)
 */
function get_cloudflare_country(): ?string {
    return $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
}

/**
 * Validate that request is from Cloudflare (for extra security)
 * Cloudflare IP ranges: https://www.cloudflare.com/ips/
 */
function validate_cloudflare_origin(): bool {
    // Skip validation if not using Cloudflare
    if (!is_cloudflare_request()) {
        return true;
    }
    
    // Cloudflare IPv4 ranges (update periodically from https://www.cloudflare.com/ips-v4)
    $cloudflare_ips = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22'
    ];
    
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    foreach ($cloudflare_ips as $range) {
        if (ip_in_range($remote_ip, $range)) {
            return true;
        }
    }
    
    // Log suspicious request
    error_log("Security Warning: Request claims Cloudflare origin but IP $remote_ip not in CF ranges");
    return false;
}

/**
 * Check if IP is in CIDR range
 */
function ip_in_range(string $ip, string $range): bool {
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    
    list($subnet, $bits) = explode('/', $range);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    
    return ($ip_long & $mask) === ($subnet_long & $mask);
}

// Automatically send headers when this file is included
send_security_headers();
?>
