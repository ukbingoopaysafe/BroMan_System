-- Create active_sessions table
CREATE TABLE IF NOT EXISTS `active_sessions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `session_id` VARCHAR(255) NOT NULL,
    `employee_id` INT NOT NULL,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_session_id` (`session_id`),
    KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;