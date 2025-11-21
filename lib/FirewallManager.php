<?php
/**
 * FOS-Streaming Firewall Manager
 * Manages UFW firewall and fail2ban from admin panel
 * PHP 8.4+ Compatible
 *
 * Date: 2025-11-21
 */

namespace FOS\Security;

class FirewallManager
{
    private const UFW_PATH = '/usr/sbin/ufw';
    private const FAIL2BAN_CLIENT = '/usr/bin/fail2ban-client';
    private const SUDO_PREFIX = '/usr/bin/sudo';

    /**
     * Check if UFW is installed and accessible
     */
    public static function isUFWInstalled(): bool
    {
        return file_exists(self::UFW_PATH) && is_executable(self::UFW_PATH);
    }

    /**
     * Check if fail2ban is installed and accessible
     */
    public static function isFail2banInstalled(): bool
    {
        return file_exists(self::FAIL2BAN_CLIENT) && is_executable(self::FAIL2BAN_CLIENT);
    }

    /**
     * Get UFW status
     */
    public static function getUFWStatus(): array
    {
        if (!self::isUFWInstalled()) {
            return ['installed' => false, 'active' => false, 'error' => 'UFW not installed'];
        }

        $output = [];
        $returnCode = 0;
        exec(self::SUDO_PREFIX . ' ' . self::UFW_PATH . ' status 2>&1', $output, $returnCode);

        $status = [
            'installed' => true,
            'active' => false,
            'rules' => [],
            'raw_output' => implode("\n", $output)
        ];

        foreach ($output as $line) {
            if (stripos($line, 'Status: active') !== false) {
                $status['active'] = true;
            }

            // Parse rules: "7777/tcp    ALLOW    Anywhere"
            if (preg_match('/^(\d+)\/(\w+)\s+(ALLOW|DENY|REJECT)\s+(.+)$/', trim($line), $matches)) {
                $status['rules'][] = [
                    'port' => $matches[1],
                    'protocol' => $matches[2],
                    'action' => $matches[3],
                    'from' => $matches[4]
                ];
            }
        }

        return $status;
    }

    /**
     * Enable UFW firewall
     */
    public static function enableUFW(): array
    {
        if (!self::isUFWInstalled()) {
            return ['success' => false, 'error' => 'UFW not installed'];
        }

        $output = [];
        $returnCode = 0;

        // Enable UFW with --force to avoid interactive prompt
        exec(self::SUDO_PREFIX . ' ' . self::UFW_PATH . ' --force enable 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('UFW firewall enabled', ['method' => 'admin_panel']);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Disable UFW firewall
     */
    public static function disableUFW(): array
    {
        if (!self::isUFWInstalled()) {
            return ['success' => false, 'error' => 'UFW not installed'];
        }

        $output = [];
        $returnCode = 0;
        exec(self::SUDO_PREFIX . ' ' . self::UFW_PATH . ' --force disable 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('UFW firewall disabled', ['method' => 'admin_panel', 'warning' => 'Server now unprotected']);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Add UFW rule
     */
    public static function addUFWRule(string $port, string $protocol = 'tcp', string $action = 'allow', ?string $from = null): array
    {
        if (!self::isUFWInstalled()) {
            return ['success' => false, 'error' => 'UFW not installed'];
        }

        // Validate inputs
        if (!is_numeric($port) || $port < 1 || $port > 65535) {
            return ['success' => false, 'error' => 'Invalid port number'];
        }

        if (!in_array(strtolower($protocol), ['tcp', 'udp'], true)) {
            return ['success' => false, 'error' => 'Invalid protocol'];
        }

        if (!in_array(strtolower($action), ['allow', 'deny', 'reject'], true)) {
            return ['success' => false, 'error' => 'Invalid action'];
        }

        // Build command
        $cmd = self::SUDO_PREFIX . ' ' . self::UFW_PATH . ' ' . escapeshellarg($action) . ' ' . escapeshellarg($port . '/' . $protocol);

        if ($from) {
            // Validate IP
            if (!filter_var($from, FILTER_VALIDATE_IP)) {
                return ['success' => false, 'error' => 'Invalid source IP'];
            }
            $cmd .= ' from ' . escapeshellarg($from);
        }

        $output = [];
        $returnCode = 0;
        exec($cmd . ' 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('UFW rule added', [
            'port' => $port,
            'protocol' => $protocol,
            'action' => $action,
            'from' => $from ?? 'any'
        ]);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Delete UFW rule
     */
    public static function deleteUFWRule(string $port, string $protocol = 'tcp'): array
    {
        if (!self::isUFWInstalled()) {
            return ['success' => false, 'error' => 'UFW not installed'];
        }

        $output = [];
        $returnCode = 0;
        $cmd = self::SUDO_PREFIX . ' ' . self::UFW_PATH . ' delete allow ' . escapeshellarg($port . '/' . $protocol);
        exec($cmd . ' 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('UFW rule deleted', [
            'port' => $port,
            'protocol' => $protocol
        ]);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Ban IP address immediately using UFW
     */
    public static function banIP(string $ip, string $reason = 'Manual ban'): array
    {
        if (!self::isUFWInstalled()) {
            return ['success' => false, 'error' => 'UFW not installed'];
        }

        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'error' => 'Invalid IP address'];
        }

        $output = [];
        $returnCode = 0;
        $cmd = self::SUDO_PREFIX . ' ' . self::UFW_PATH . ' deny from ' . escapeshellarg($ip);
        exec($cmd . ' 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('IP banned via UFW', [
            'ip' => $ip,
            'reason' => $reason,
            'method' => 'manual'
        ]);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Unban IP address
     */
    public static function unbanIP(string $ip): array
    {
        if (!self::isUFWInstalled()) {
            return ['success' => false, 'error' => 'UFW not installed'];
        }

        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'error' => 'Invalid IP address'];
        }

        $output = [];
        $returnCode = 0;
        $cmd = self::SUDO_PREFIX . ' ' . self::UFW_PATH . ' delete deny from ' . escapeshellarg($ip);
        exec($cmd . ' 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('IP unbanned', ['ip' => $ip]);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Get fail2ban status
     */
    public static function getFail2banStatus(): array
    {
        if (!self::isFail2banInstalled()) {
            return ['installed' => false, 'running' => false, 'error' => 'fail2ban not installed'];
        }

        $output = [];
        $returnCode = 0;
        exec(self::SUDO_PREFIX . ' ' . self::FAIL2BAN_CLIENT . ' status 2>&1', $output, $returnCode);

        $status = [
            'installed' => true,
            'running' => $returnCode === 0,
            'jails' => [],
            'raw_output' => implode("\n", $output)
        ];

        // Parse jail list
        foreach ($output as $line) {
            if (preg_match('/Jail list:\s+(.+)$/', $line, $matches)) {
                $jails = array_map('trim', explode(',', $matches[1]));
                foreach ($jails as $jail) {
                    if (!empty($jail)) {
                        $status['jails'][] = $jail;
                    }
                }
            }
        }

        return $status;
    }

    /**
     * Get detailed status of a specific jail
     */
    public static function getJailStatus(string $jailName): array
    {
        if (!self::isFail2banInstalled()) {
            return ['success' => false, 'error' => 'fail2ban not installed'];
        }

        $output = [];
        $returnCode = 0;
        exec(self::SUDO_PREFIX . ' ' . self::FAIL2BAN_CLIENT . ' status ' . escapeshellarg($jailName) . ' 2>&1', $output, $returnCode);

        $status = [
            'success' => $returnCode === 0,
            'jail' => $jailName,
            'total_banned' => 0,
            'currently_banned' => 0,
            'banned_ips' => [],
            'total_failed' => 0,
            'raw_output' => implode("\n", $output)
        ];

        foreach ($output as $line) {
            if (preg_match('/Total banned:\s+(\d+)/', $line, $matches)) {
                $status['total_banned'] = (int)$matches[1];
            }
            if (preg_match('/Currently banned:\s+(\d+)/', $line, $matches)) {
                $status['currently_banned'] = (int)$matches[1];
            }
            if (preg_match('/Banned IP list:\s+(.+)$/', $line, $matches)) {
                $ips = array_map('trim', explode(' ', $matches[1]));
                $status['banned_ips'] = array_filter($ips);
            }
            if (preg_match('/Total failed:\s+(\d+)/', $line, $matches)) {
                $status['total_failed'] = (int)$matches[1];
            }
        }

        return $status;
    }

    /**
     * Unban IP from fail2ban jail
     */
    public static function fail2banUnbanIP(string $jailName, string $ip): array
    {
        if (!self::isFail2banInstalled()) {
            return ['success' => false, 'error' => 'fail2ban not installed'];
        }

        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'error' => 'Invalid IP address'];
        }

        $output = [];
        $returnCode = 0;
        $cmd = self::SUDO_PREFIX . ' ' . self::FAIL2BAN_CLIENT . ' set ' . escapeshellarg($jailName) . ' unbanip ' . escapeshellarg($ip);
        exec($cmd . ' 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('IP unbanned from fail2ban', [
            'ip' => $ip,
            'jail' => $jailName
        ]);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Ban IP immediately in fail2ban jail
     */
    public static function fail2banBanIP(string $jailName, string $ip): array
    {
        if (!self::isFail2banInstalled()) {
            return ['success' => false, 'error' => 'fail2ban not installed'];
        }

        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'error' => 'Invalid IP address'];
        }

        $output = [];
        $returnCode = 0;
        $cmd = self::SUDO_PREFIX . ' ' . self::FAIL2BAN_CLIENT . ' set ' . escapeshellarg($jailName) . ' banip ' . escapeshellarg($ip);
        exec($cmd . ' 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('IP banned via fail2ban', [
            'ip' => $ip,
            'jail' => $jailName
        ]);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Reload fail2ban configuration
     */
    public static function reloadFail2ban(): array
    {
        if (!self::isFail2banInstalled()) {
            return ['success' => false, 'error' => 'fail2ban not installed'];
        }

        $output = [];
        $returnCode = 0;
        exec(self::SUDO_PREFIX . ' ' . self::FAIL2BAN_CLIENT . ' reload 2>&1', $output, $returnCode);

        SecurityLogger::logSecurityEvent('fail2ban configuration reloaded');

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Get list of all banned IPs across all jails
     */
    public static function getAllBannedIPs(): array
    {
        if (!self::isFail2banInstalled()) {
            return ['success' => false, 'error' => 'fail2ban not installed', 'banned_ips' => []];
        }

        $status = self::getFail2banStatus();
        $allBanned = [];

        foreach ($status['jails'] as $jail) {
            $jailStatus = self::getJailStatus($jail);
            if ($jailStatus['success'] && !empty($jailStatus['banned_ips'])) {
                foreach ($jailStatus['banned_ips'] as $ip) {
                    if (!isset($allBanned[$ip])) {
                        $allBanned[$ip] = [];
                    }
                    $allBanned[$ip][] = $jail;
                }
            }
        }

        return [
            'success' => true,
            'banned_ips' => $allBanned
        ];
    }

    /**
     * Get security statistics
     */
    public static function getSecurityStats(): array
    {
        $stats = [
            'ufw' => [
                'installed' => self::isUFWInstalled(),
                'active' => false,
                'rules_count' => 0
            ],
            'fail2ban' => [
                'installed' => self::isFail2banInstalled(),
                'running' => false,
                'jails_count' => 0,
                'total_banned' => 0
            ],
            'recent_attacks' => []
        ];

        // UFW stats
        if ($stats['ufw']['installed']) {
            $ufwStatus = self::getUFWStatus();
            $stats['ufw']['active'] = $ufwStatus['active'];
            $stats['ufw']['rules_count'] = count($ufwStatus['rules']);
        }

        // fail2ban stats
        if ($stats['fail2ban']['installed']) {
            $f2bStatus = self::getFail2banStatus();
            $stats['fail2ban']['running'] = $f2bStatus['running'];
            $stats['fail2ban']['jails_count'] = count($f2bStatus['jails']);

            // Count total banned IPs
            foreach ($f2bStatus['jails'] as $jail) {
                $jailStatus = self::getJailStatus($jail);
                if ($jailStatus['success']) {
                    $stats['fail2ban']['total_banned'] += $jailStatus['currently_banned'];
                }
            }
        }

        // Recent attacks from security log
        $stats['recent_attacks'] = SecurityLogger::getRecentSecurityEvents(50);

        return $stats;
    }
}
