<?php
/**
 * FOS-Streaming Security Logger
 * Logs security-related events for monitoring and auditing
 * PHP 8.4+ Compatible
 *
 * Date: 2025-11-21
 */

namespace FOS\Security;

class SecurityLogger
{
    private static string $logDir = '/home/fos-streaming/fos/logs';

    /**
     * Set log directory
     *
     * @param string $dir Directory path
     * @return void
     */
    public static function setLogDir(string $dir): void
    {
        self::$logDir = rtrim($dir, '/');
    }

    /**
     * Log authentication attempt
     *
     * @param string $username Username
     * @param bool $success Whether authentication succeeded
     * @param string|null $ip IP address
     * @param string|null $reason Failure reason
     * @return void
     */
    public static function logAuthAttempt(string $username, bool $success, ?string $ip = null, ?string $reason = null): void
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $reasonText = $reason ? " - Reason: {$reason}" : '';

        $log = sprintf(
            "[%s] Auth %s - User: %s, IP: %s%s\n",
            date('Y-m-d H:i:s'),
            $status,
            $username,
            $ip,
            $reasonText
        );

        self::writeLog('auth.log', $log);

        // If failed, also log to security log
        if (!$success) {
            self::logSecurityEvent('Failed login attempt', [
                'username' => $username,
                'ip' => $ip,
                'reason' => $reason
            ]);
        }
    }

    /**
     * Log stream access
     *
     * @param int $userId User ID
     * @param int $streamId Stream ID
     * @param string $streamName Stream name
     * @param string|null $ip IP address
     * @return void
     */
    public static function logStreamAccess(int $userId, int $streamId, string $streamName, ?string $ip = null): void
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        $log = sprintf(
            "[%s] Stream Access - User: %d, Stream: %d (%s), IP: %s\n",
            date('Y-m-d H:i:s'),
            $userId,
            $streamId,
            $streamName,
            $ip
        );

        self::writeLog('stream-access.log', $log);
    }

    /**
     * Log security event
     *
     * @param string $event Event description
     * @param array $context Additional context
     * @return void
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';

        $contextJson = !empty($context) ? json_encode($context) : '';

        $log = sprintf(
            "[%s] SECURITY EVENT: %s - IP: %s, URI: %s, UA: %s, Context: %s\n",
            date('Y-m-d H:i:s'),
            $event,
            $ip,
            $uri,
            substr($userAgent, 0, 100),
            $contextJson
        );

        self::writeLog('security.log', $log);
    }

    /**
     * Log CSRF token validation failure
     *
     * @param string $action Action being performed
     * @return void
     */
    public static function logCSRFFailure(string $action): void
    {
        self::logSecurityEvent('CSRF token validation failed', [
            'action' => $action,
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
        ]);
    }

    /**
     * Log rate limit exceeded
     *
     * @param string $key Rate limit key
     * @param string|null $ip IP address
     * @return void
     */
    public static function logRateLimitExceeded(string $key, ?string $ip = null): void
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        self::logSecurityEvent('Rate limit exceeded', [
            'key' => $key,
            'ip' => $ip
        ]);
    }

    /**
     * Log suspicious activity
     *
     * @param string $description Description of suspicious activity
     * @param array $context Additional context
     * @return void
     */
    public static function logSuspiciousActivity(string $description, array $context = []): void
    {
        self::logSecurityEvent("SUSPICIOUS: {$description}", $context);
    }

    /**
     * Log SQL injection attempt
     *
     * @param string $input Suspicious input
     * @return void
     */
    public static function logSQLInjectionAttempt(string $input): void
    {
        self::logSecurityEvent('Possible SQL injection attempt', [
            'input' => substr($input, 0, 200)
        ]);
    }

    /**
     * Log XSS attempt
     *
     * @param string $input Suspicious input
     * @return void
     */
    public static function logXSSAttempt(string $input): void
    {
        self::logSecurityEvent('Possible XSS attempt', [
            'input' => substr($input, 0, 200)
        ]);
    }

    /**
     * Log file upload
     *
     * @param string $filename Uploaded filename
     * @param int $size File size
     * @param bool $success Upload success
     * @return void
     */
    public static function logFileUpload(string $filename, int $size, bool $success): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $status = $success ? 'SUCCESS' : 'FAILED';

        $log = sprintf(
            "[%s] File Upload %s - File: %s, Size: %d, IP: %s\n",
            date('Y-m-d H:i:s'),
            $status,
            $filename,
            $size,
            $ip
        );

        self::writeLog('uploads.log', $log);

        if (!$success) {
            self::logSecurityEvent('File upload failed', [
                'filename' => $filename,
                'size' => $size
            ]);
        }
    }

    /**
     * Log password change
     *
     * @param int $userId User ID
     * @param bool $success Success status
     * @return void
     */
    public static function logPasswordChange(int $userId, bool $success): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $status = $success ? 'SUCCESS' : 'FAILED';

        $log = sprintf(
            "[%s] Password Change %s - UserID: %d, IP: %s\n",
            date('Y-m-d H:i:s'),
            $status,
            $userId,
            $ip
        );

        self::writeLog('password-changes.log', $log);
    }

    /**
     * Log admin action
     *
     * @param string $action Action performed
     * @param int $adminId Admin user ID
     * @param array $context Additional context
     * @return void
     */
    public static function logAdminAction(string $action, int $adminId, array $context = []): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $contextJson = !empty($context) ? json_encode($context) : '';

        $log = sprintf(
            "[%s] Admin Action: %s - AdminID: %d, IP: %s, Context: %s\n",
            date('Y-m-d H:i:s'),
            $action,
            $adminId,
            $ip,
            $contextJson
        );

        self::writeLog('admin-actions.log', $log);
    }

    /**
     * Write to log file
     *
     * @param string $filename Log filename
     * @param string $message Message to log
     * @return void
     */
    private static function writeLog(string $filename, string $message): void
    {
        $logFile = self::$logDir . '/' . $filename;

        // Create directory if it doesn't exist
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }

        // Write to file
        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);

        // Rotate log if it's too large (> 10MB)
        if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
            self::rotateLog($logFile);
        }
    }

    /**
     * Rotate log file
     *
     * @param string $logFile Log file path
     * @return void
     */
    private static function rotateLog(string $logFile): void
    {
        $rotatedFile = $logFile . '.' . date('Y-m-d-His');
        rename($logFile, $rotatedFile);

        // Compress rotated file
        if (function_exists('gzencode')) {
            $content = file_get_contents($rotatedFile);
            file_put_contents($rotatedFile . '.gz', gzencode($content));
            unlink($rotatedFile);
        }
    }

    /**
     * Get recent security events
     *
     * @param int $lines Number of lines to retrieve
     * @return array Recent events
     */
    public static function getRecentSecurityEvents(int $lines = 100): array
    {
        $logFile = self::$logDir . '/security.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $content = file($logFile);
        $recent = array_slice($content, -$lines);

        return array_reverse($recent);
    }

    /**
     * Get failed login attempts for an IP
     *
     * @param string $ip IP address
     * @param int $minutes Time window in minutes
     * @return int Number of failed attempts
     */
    public static function getFailedLoginAttempts(string $ip, int $minutes = 15): int
    {
        $logFile = self::$logDir . '/auth.log';

        if (!file_exists($logFile)) {
            return 0;
        }

        $since = time() - ($minutes * 60);
        $count = 0;

        $handle = fopen($logFile, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, 'FAILED') !== false && strpos($line, $ip) !== false) {
                    // Parse timestamp from line
                    if (preg_match('/\[([^\]]+)\]/', $line, $matches)) {
                        $timestamp = strtotime($matches[1]);
                        if ($timestamp >= $since) {
                            $count++;
                        }
                    }
                }
            }
            fclose($handle);
        }

        return $count;
    }
}
