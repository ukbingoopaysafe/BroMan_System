-- Login Attempts Tracking Table for Brute Force Protection
-- This table tracks failed and successful login attempts for security monitoring

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(100) DEFAULT NULL,
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 0,
    user_agent TEXT,
    blocked_until DATETIME DEFAULT NULL,
    INDEX idx_ip_time (ip_address, attempt_time),
    INDEX idx_username_time (username, attempt_time),
    INDEX idx_blocked (ip_address, blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Add a table for tracking blocked IPs separately
CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    blocked_until DATETIME NOT NULL,
    reason VARCHAR(255) DEFAULT 'Too many failed login attempts',
    INDEX idx_ip (ip_address),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
