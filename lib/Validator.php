<?php
/**
 * FOS-Streaming Input Validation Library
 * Comprehensive input validation and sanitization
 * PHP 8.4+ Compatible
 *
 * Date: 2025-11-21
 */

namespace FOS\Security;

class Validator
{
    /**
     * Validate username
     *
     * @param string $username Username to validate
     * @param int $minLength Minimum length
     * @param int $maxLength Maximum length
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateUsername(string $username, int $minLength = 3, int $maxLength = 50): array
    {
        $username = trim($username);

        if (strlen($username) < $minLength) {
            return ['valid' => false, 'error' => "Username must be at least {$minLength} characters"];
        }

        if (strlen($username) > $maxLength) {
            return ['valid' => false, 'error' => "Username must not exceed {$maxLength} characters"];
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return ['valid' => false, 'error' => 'Username can only contain letters, numbers, hyphens and underscores'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate password strength
     *
     * @param string $password Password to validate
     * @param int $minLength Minimum length
     * @return array ['valid' => bool, 'error' => string|null, 'strength' => string]
     */
    public static function validatePassword(string $password, int $minLength = 8): array
    {
        if (strlen($password) < $minLength) {
            return [
                'valid' => false,
                'error' => "Password must be at least {$minLength} characters",
                'strength' => 'weak'
            ];
        }

        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);

        $strength = 0;
        if ($hasUpper) $strength++;
        if ($hasLower) $strength++;
        if ($hasNumber) $strength++;
        if ($hasSpecial) $strength++;

        $strengthLabel = match ($strength) {
            0, 1 => 'weak',
            2 => 'medium',
            3, 4 => 'strong',
        };

        // Require at least 2 character types
        if ($strength < 2) {
            return [
                'valid' => false,
                'error' => 'Password must contain at least 2 of: uppercase, lowercase, numbers, special characters',
                'strength' => $strengthLabel
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'strength' => $strengthLabel
        ];
    }

    /**
     * Validate stream name
     *
     * @param string $streamName Stream name to validate
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateStreamName(string $streamName): array
    {
        $streamName = trim($streamName);

        if (empty($streamName)) {
            return ['valid' => false, 'error' => 'Stream name cannot be empty'];
        }

        if (strlen($streamName) > 100) {
            return ['valid' => false, 'error' => 'Stream name must not exceed 100 characters'];
        }

        // Allow alphanumeric, spaces, hyphens, underscores
        if (!preg_match('/^[a-zA-Z0-9 _-]+$/', $streamName)) {
            return ['valid' => false, 'error' => 'Stream name contains invalid characters'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate port number
     *
     * @param int|string $port Port number
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validatePort(int|string $port): array
    {
        $port = (int)$port;

        if ($port < 1 || $port > 65535) {
            return ['valid' => false, 'error' => 'Port must be between 1 and 65535'];
        }

        // Check for privileged ports
        if ($port < 1024) {
            return ['valid' => false, 'error' => 'Cannot use privileged ports (1-1023)'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate integer within range
     *
     * @param mixed $value Value to validate
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return array ['valid' => bool, 'error' => string|null, 'value' => int]
     */
    public static function validateIntRange(mixed $value, int $min, int $max): array
    {
        if (!is_numeric($value)) {
            return ['valid' => false, 'error' => 'Value must be a number', 'value' => 0];
        }

        $intValue = (int)$value;

        if ($intValue < $min || $intValue > $max) {
            return ['valid' => false, 'error' => "Value must be between {$min} and {$max}", 'value' => $intValue];
        }

        return ['valid' => true, 'error' => null, 'value' => $intValue];
    }

    /**
     * Sanitize and validate transcode profile settings
     *
     * @param array $profile Profile settings
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => array]
     */
    public static function validateTranscodeProfile(array $profile): array
    {
        $errors = [];
        $sanitized = [];

        // Validate bitrate
        if (isset($profile['bitrate'])) {
            $bitrate = self::validateIntRange($profile['bitrate'], 64, 50000);
            if (!$bitrate['valid']) {
                $errors['bitrate'] = $bitrate['error'];
            } else {
                $sanitized['bitrate'] = $bitrate['value'];
            }
        }

        // Validate resolution
        if (isset($profile['resolution'])) {
            if (!preg_match('/^\d{2,4}x\d{2,4}$/', $profile['resolution'])) {
                $errors['resolution'] = 'Invalid resolution format (use WIDTHxHEIGHT)';
            } else {
                $sanitized['resolution'] = $profile['resolution'];
            }
        }

        // Validate codec
        if (isset($profile['codec'])) {
            $allowedCodecs = ['h264', 'h265', 'vp8', 'vp9', 'av1'];
            $codec = strtolower(trim($profile['codec']));
            if (!in_array($codec, $allowedCodecs, true)) {
                $errors['codec'] = 'Invalid codec (allowed: ' . implode(', ', $allowedCodecs) . ')';
            } else {
                $sanitized['codec'] = $codec;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => $sanitized
        ];
    }

    /**
     * Validate M3U8 playlist content
     *
     * @param string $content M3U8 content
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateM3U8(string $content): array
    {
        $lines = explode("\n", $content);

        // Must start with #EXTM3U
        if (empty($lines[0]) || trim($lines[0]) !== '#EXTM3U') {
            return ['valid' => false, 'error' => 'Invalid M3U8 format: must start with #EXTM3U'];
        }

        // Check for suspicious content
        $suspicious = ['<script', 'javascript:', 'data:', 'file://'];
        foreach ($lines as $line) {
            foreach ($suspicious as $pattern) {
                if (stripos($line, $pattern) !== false) {
                    return ['valid' => false, 'error' => 'M3U8 contains suspicious content'];
                }
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Sanitize filename
     *
     * @param string $filename Filename to sanitize
     * @return string Sanitized filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts
        $filename = basename($filename);

        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

        // Remove multiple dots (except for extension)
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            $ext = array_pop($parts);
            $base = implode('_', $parts);
            $filename = $base . '.' . $ext;
        }

        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }

        return $filename;
    }

    /**
     * Validate allowed IP addresses list
     *
     * @param string $ipList Comma-separated IP addresses or CIDR ranges
     * @return array ['valid' => bool, 'errors' => array, 'ips' => array]
     */
    public static function validateIPList(string $ipList): array
    {
        $errors = [];
        $validIPs = [];

        $ips = array_map('trim', explode(',', $ipList));

        foreach ($ips as $ip) {
            if (empty($ip)) {
                continue;
            }

            // Check for CIDR notation
            if (strpos($ip, '/') !== false) {
                [$ipAddr, $mask] = explode('/', $ip);
                if (!filter_var($ipAddr, FILTER_VALIDATE_IP) || !is_numeric($mask) || $mask < 0 || $mask > 32) {
                    $errors[] = "Invalid CIDR notation: {$ip}";
                } else {
                    $validIPs[] = $ip;
                }
            } else {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $errors[] = "Invalid IP address: {$ip}";
                } else {
                    $validIPs[] = $ip;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'ips' => $validIPs
        ];
    }

    /**
     * Validate user agent string
     *
     * @param string $userAgent User agent string
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateUserAgent(string $userAgent): array
    {
        $userAgent = trim($userAgent);

        if (empty($userAgent)) {
            return ['valid' => false, 'error' => 'User agent cannot be empty'];
        }

        if (strlen($userAgent) > 500) {
            return ['valid' => false, 'error' => 'User agent too long'];
        }

        // Check for suspicious patterns
        $suspicious = ['<script', 'javascript:', 'data:', 'file://', '<?php'];
        foreach ($suspicious as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return ['valid' => false, 'error' => 'User agent contains suspicious content'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Sanitize HTML for output (allow limited HTML)
     *
     * @param string $html HTML content
     * @param array $allowedTags Allowed HTML tags
     * @return string Sanitized HTML
     */
    public static function sanitizeHTML(string $html, array $allowedTags = ['b', 'i', 'u', 'strong', 'em', 'br', 'p']): string
    {
        $allowed = '<' . implode('><', $allowedTags) . '>';
        return strip_tags($html, $allowed);
    }

    /**
     * Validate cron expression
     *
     * @param string $expression Cron expression
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateCronExpression(string $expression): array
    {
        $parts = explode(' ', trim($expression));

        if (count($parts) !== 5) {
            return ['valid' => false, 'error' => 'Cron expression must have 5 parts'];
        }

        // Basic validation (not comprehensive)
        foreach ($parts as $part) {
            if (!preg_match('/^(\*|[0-9\-,\/]+)$/', $part)) {
                return ['valid' => false, 'error' => 'Invalid cron expression format'];
            }
        }

        return ['valid' => true, 'error' => null];
    }
}
