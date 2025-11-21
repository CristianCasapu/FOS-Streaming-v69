<?php
/**
 * FOS-Streaming Security Library
 * Provides CSRF protection, input validation, and security utilities
 * PHP 8.4+ Compatible
 *
 * Date: 2025-11-21
 */

namespace FOS\Security;

class Security
{
    /**
     * Generate a CSRF token and store it in the session
     *
     * @return string The generated CSRF token
     */
    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a CSRF token
     *
     * @param string|null $token The token to validate
     * @param int $maxAge Maximum age of token in seconds (default: 1 hour)
     * @return bool True if valid, false otherwise
     */
    public static function validateCSRFToken(?string $token, int $maxAge = 3600): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if token exists
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        // Check token age
        if (!empty($_SESSION['csrf_token_time'])) {
            $age = time() - $_SESSION['csrf_token_time'];
            if ($age > $maxAge) {
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                return false;
            }
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get CSRF token HTML input field
     *
     * @return string HTML input field
     */
    public static function getCSRFField(): string
    {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Clean and validate string input
     *
     * @param string $input The input string
     * @param int $maxLength Maximum allowed length
     * @return string Cleaned string
     */
    public static function cleanString(string $input, int $maxLength = 255): string
    {
        $clean = trim($input);
        $clean = strip_tags($clean);
        $clean = substr($clean, 0, $maxLength);
        return $clean;
    }

    /**
     * Validate email address
     *
     * @param string $email Email address to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate IP address
     *
     * @param string $ip IP address to validate
     * @param bool $allowPrivate Allow private IP addresses
     * @return bool True if valid, false otherwise
     */
    public static function validateIP(string $ip, bool $allowPrivate = true): bool
    {
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

        if (!$allowPrivate) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @param array $allowedSchemes Allowed URL schemes
     * @return bool True if valid, false otherwise
     */
    public static function validateURL(string $url, array $allowedSchemes = ['http', 'https']): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parsed = parse_url($url);

        if (!isset($parsed['scheme'])) {
            return false;
        }

        return in_array(strtolower($parsed['scheme']), $allowedSchemes, true);
    }

    /**
     * Sanitize stream URL (RTMP, HTTP, HTTPS only)
     *
     * @param string $url Stream URL to sanitize
     * @return string|false Sanitized URL or false if invalid
     */
    public static function sanitizeStreamURL(string $url): string|false
    {
        $allowedSchemes = ['http', 'https', 'rtmp', 'rtmps'];

        $parsed = parse_url($url);

        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), $allowedSchemes, true)) {
            return false;
        }

        // Prevent file:// and other dangerous protocols
        if (in_array(strtolower($parsed['scheme']), ['file', 'php', 'data', 'ftp'], true)) {
            return false;
        }

        return filter_var($url, FILTER_SANITIZE_URL);
    }

    /**
     * Generate secure random string
     *
     * @param int $length Length of the string
     * @return string Random string
     */
    public static function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash password using Argon2id (PHP 8.4 recommended)
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2
        ]);
    }

    /**
     * Verify password against hash
     *
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool True if password matches
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing
     *
     * @param string $hash Current password hash
     * @return bool True if rehash needed
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2
        ]);
    }

    /**
     * Get client IP address (handles proxies)
     *
     * @return string Client IP address
     */
    public static function getClientIP(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Check for proxy headers (only if you trust your proxy)
        $proxyHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_REAL_IP',            // Nginx proxy
        ];

        foreach ($proxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                break;
            }
        }

        return $ip;
    }

    /**
     * Rate limit check (simple implementation)
     *
     * @param string $key Unique identifier (e.g., 'login_' . $ip)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if rate limit exceeded
     */
    public static function isRateLimited(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $now = time();
        $attempts = $_SESSION['rate_limit'][$key] ?? [];

        // Clean old attempts
        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });

        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            return true;
        }

        // Record this attempt
        $attempts[] = $now;
        $_SESSION['rate_limit'][$key] = $attempts;

        return false;
    }

    /**
     * Reset rate limit for a key
     *
     * @param string $key Rate limit key
     * @return void
     */
    public static function resetRateLimit(string $key): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['rate_limit'][$key]);
    }

    /**
     * Escape output for HTML context
     *
     * @param string $string String to escape
     * @return string Escaped string
     */
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Generate time-limited signed token for streams
     *
     * @param int $userId User ID
     * @param int $streamId Stream ID
     * @param int $ttl Time to live in seconds
     * @param string $secretKey Secret key for signing
     * @return string Base64 encoded token
     */
    public static function generateStreamToken(int $userId, int $streamId, int $ttl = 3600, string $secretKey = ''): string
    {
        if (empty($secretKey)) {
            // Try to get from environment or config
            $secretKey = getenv('FOS_SECRET_KEY') ?: 'CHANGE_THIS_SECRET_KEY';
        }

        $payload = [
            'user_id' => $userId,
            'stream_id' => $streamId,
            'expires' => time() + $ttl,
            'ip' => self::getClientIP()
        ];

        $payloadJson = json_encode($payload);
        $payloadB64 = base64_encode($payloadJson);
        $signature = hash_hmac('sha256', $payloadB64, $secretKey);

        return $payloadB64 . '.' . $signature;
    }

    /**
     * Verify stream token
     *
     * @param string $token Token to verify
     * @param string $secretKey Secret key for verification
     * @return array|false Payload if valid, false otherwise
     */
    public static function verifyStreamToken(string $token, string $secretKey = ''): array|false
    {
        if (empty($secretKey)) {
            $secretKey = getenv('FOS_SECRET_KEY') ?: 'CHANGE_THIS_SECRET_KEY';
        }

        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return false;
        }

        [$payloadB64, $signature] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $payloadB64, $secretKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // Decode payload
        $payload = json_decode(base64_decode($payloadB64), true);

        if (!is_array($payload)) {
            return false;
        }

        // Check expiry
        if (empty($payload['expires']) || time() > $payload['expires']) {
            return false;
        }

        // Optionally check IP (strict mode)
        // if ($payload['ip'] !== self::getClientIP()) {
        //     return false;
        // }

        return $payload;
    }
}
