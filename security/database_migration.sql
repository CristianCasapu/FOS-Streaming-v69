-- FOS-Streaming Security Features Database Migration
-- Creates tables for IP management
-- Run this after installation: mysql -u root -p fos < database_migration.sql

-- Create banned_ips table
CREATE TABLE IF NOT EXISTS `banned_ips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `type` enum('banned','whitelisted') NOT NULL DEFAULT 'banned',
  `reason` text NOT NULL,
  `banned_by` varchar(50) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `permanent` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `type` (`type`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create security_settings table
CREATE TABLE IF NOT EXISTS `security_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default security settings
INSERT INTO `security_settings` (`setting_key`, `setting_value`, `description`) VALUES
('fail2ban_enabled', '1', 'Enable fail2ban integration'),
('fail2ban_bantime', '3600', 'Default ban time in seconds'),
('fail2ban_maxretry', '5', 'Maximum failed attempts before ban'),
('fail2ban_findtime', '600', 'Time window to count failures (seconds)'),
('ufw_enabled', '1', 'Enable UFW firewall'),
('rate_limit_login', '5', 'Login attempts per time window'),
('rate_limit_login_window', '900', 'Login rate limit time window (seconds)'),
('rate_limit_api', '20', 'API requests per second'),
('security_alerts_enabled', '1', 'Enable security alert emails'),
('security_alerts_email', '', 'Email address for security alerts'),
('auto_ban_enabled', '1', 'Automatically ban IPs after threshold'),
('auto_ban_threshold', '10', 'Number of security violations before auto-ban'),
('port_scan_detection', '1', 'Enable port scan detection'),
('bruteforce_protection', '1', 'Enable brute force protection'),
('geo_blocking_enabled', '0', 'Enable geographic IP blocking'),
('geo_blocking_countries', '', 'Comma-separated country codes to block'),
('suspicious_ua_blocking', '1', 'Block suspicious user agents'),
('tor_blocking', '0', 'Block Tor exit nodes'),
('vpn_blocking', '0', 'Block known VPN IPs')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Create security_events table for detailed tracking
CREATE TABLE IF NOT EXISTS `security_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `description` text NOT NULL,
  `user_agent` text,
  `request_uri` varchar(255) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_type` (`event_type`),
  KEY `ip_address` (`ip_address`),
  KEY `severity` (`severity`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create firewall_rules table
CREATE TABLE IF NOT EXISTS `firewall_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_type` enum('ufw','iptables','custom') DEFAULT 'ufw',
  `port` int(11) DEFAULT NULL,
  `protocol` enum('tcp','udp','both') DEFAULT 'tcp',
  `action` enum('allow','deny','reject') DEFAULT 'allow',
  `source_ip` varchar(45) DEFAULT NULL,
  `description` text,
  `enabled` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default firewall rules
INSERT INTO `firewall_rules` (`rule_type`, `port`, `protocol`, `action`, `description`) VALUES
('ufw', 22, 'tcp', 'allow', 'SSH access'),
('ufw', 7777, 'tcp', 'allow', 'FOS web panel'),
('ufw', 8000, 'tcp', 'allow', 'FOS streaming port'),
('ufw', 1935, 'tcp', 'allow', 'RTMP streaming')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Create failed_login_attempts table
CREATE TABLE IF NOT EXISTS `failed_login_attempts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `user_agent` text,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for performance
CREATE INDEX idx_ip_time ON failed_login_attempts(ip_address, attempt_time);

-- Create view for security dashboard
CREATE OR REPLACE VIEW security_dashboard AS
SELECT
    (SELECT COUNT(*) FROM banned_ips WHERE type='banned' AND (permanent=1 OR expires_at > NOW())) as total_banned,
    (SELECT COUNT(*) FROM banned_ips WHERE type='whitelisted') as total_whitelisted,
    (SELECT COUNT(*) FROM security_events WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as events_24h,
    (SELECT COUNT(*) FROM security_events WHERE severity='critical' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as critical_events_24h,
    (SELECT COUNT(DISTINCT ip_address) FROM failed_login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as failed_logins_1h,
    (SELECT COUNT(*) FROM firewall_rules WHERE enabled=1) as active_firewall_rules;

-- Stored procedure to clean up old data
DELIMITER $$

CREATE PROCEDURE cleanup_old_security_data()
BEGIN
    -- Delete expired bans
    DELETE FROM banned_ips
    WHERE type='banned'
      AND permanent=0
      AND expires_at < NOW();

    -- Delete old security events (older than 90 days)
    DELETE FROM security_events
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

    -- Delete old failed login attempts (older than 30 days)
    DELETE FROM failed_login_attempts
    WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);

    -- Log cleanup
    INSERT INTO security_events (event_type, ip_address, severity, description)
    VALUES ('system', '127.0.0.1', 'low', 'Automatic security data cleanup completed');
END$$

DELIMITER ;

-- Create event to run cleanup daily
-- Note: Requires EVENT scheduler to be enabled
-- SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS daily_security_cleanup
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO CALL cleanup_old_security_data();

-- Grant necessary permissions to nginx user
-- This allows the web application to interact with security tables
GRANT SELECT, INSERT, UPDATE, DELETE ON fos.banned_ips TO 'fos'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON fos.security_settings TO 'fos'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON fos.security_events TO 'fos'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON fos.firewall_rules TO 'fos'@'localhost';
GRANT SELECT, INSERT, DELETE ON fos.failed_login_attempts TO 'fos'@'localhost';
GRANT SELECT ON fos.security_dashboard TO 'fos'@'localhost';
FLUSH PRIVILEGES;

-- Success message
SELECT 'Security tables created successfully!' as Status;
SELECT 'Run this to enable the cleanup scheduler:' as Note;
SELECT 'SET GLOBAL event_scheduler = ON;' as Command;
