<?php
/**
 * FOS-Streaming Security Settings Page
 * Manage firewall, fail2ban, IP bans, and security configuration
 *
 * Date: 2025-11-21
 */

require_once 'config.php';
require_once 'lib/Security.php';
require_once 'lib/FirewallManager.php';
require_once 'lib/SecurityLogger.php';

use FOS\Security\Security;
use FOS\Security\FirewallManager;
use FOS\Security\SecurityLogger;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$success = '';
$error = '';
$action = $_GET['action'] ?? '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Validate CSRF
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'enable_ufw':
            $result = FirewallManager::enableUFW();
            echo json_encode($result);
            break;

        case 'disable_ufw':
            $result = FirewallManager::disableUFW();
            echo json_encode($result);
            break;

        case 'add_ufw_rule':
            $result = FirewallManager::addUFWRule(
                $_POST['port'],
                $_POST['protocol'] ?? 'tcp',
                $_POST['action'] ?? 'allow',
                $_POST['from'] ?? null
            );
            echo json_encode($result);
            break;

        case 'delete_ufw_rule':
            $result = FirewallManager::deleteUFWRule($_POST['port'], $_POST['protocol'] ?? 'tcp');
            echo json_encode($result);
            break;

        case 'ban_ip':
            $ip = $_POST['ip'] ?? '';
            $reason = $_POST['reason'] ?? 'Manual ban';
            $duration = $_POST['duration'] ?? null;

            // Ban in database
            BannedIP::ban($ip, $reason, $duration, $_SESSION['username']);

            // Ban in UFW
            $result = FirewallManager::banIP($ip, $reason);

            // Also ban in fail2ban if available
            if (FirewallManager::isFail2banInstalled()) {
                $f2bStatus = FirewallManager::getFail2banStatus();
                foreach ($f2bStatus['jails'] as $jail) {
                    FirewallManager::fail2banBanIP($jail, $ip);
                }
            }

            echo json_encode($result);
            break;

        case 'unban_ip':
            $ip = $_POST['ip'] ?? '';

            // Unban from database
            BannedIP::unban($ip);

            // Unban from UFW
            $result = FirewallManager::unbanIP($ip);

            // Unban from fail2ban
            if (FirewallManager::isFail2banInstalled()) {
                $f2bStatus = FirewallManager::getFail2banStatus();
                foreach ($f2bStatus['jails'] as $jail) {
                    FirewallManager::fail2banUnbanIP($jail, $ip);
                }
            }

            echo json_encode($result);
            break;

        case 'whitelist_ip':
            $ip = $_POST['ip'] ?? '';
            $reason = $_POST['reason'] ?? 'Whitelisted';

            BannedIP::whitelist($ip, $reason, $_SESSION['username']);

            echo json_encode(['success' => true]);
            break;

        case 'reload_fail2ban':
            $result = FirewallManager::reloadFail2ban();
            echo json_encode($result);
            break;

        case 'get_stats':
            $stats = FirewallManager::getSecurityStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        case 'get_jail_status':
            $jail = $_POST['jail'] ?? '';
            $result = FirewallManager::getJailStatus($jail);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Get current status
$ufwStatus = FirewallManager::getUFWStatus();
$fail2banStatus = FirewallManager::getFail2banStatus();
$securityStats = FirewallManager::getSecurityStats();
$bannedIPs = BannedIP::getActiveBans();
$whitelistedIPs = BannedIP::getWhitelisted();

// Clean up expired bans
BannedIP::cleanExpired();

$csrfToken = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - FOS-Streaming</title>

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="fonts/css/font-awesome.min.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">

    <style>
        .security-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-inactive {
            background: #dc3545;
            color: white;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin: 10px 0;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .ip-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .ip-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ip-item:hover {
            background: #f8f9fa;
        }

        .jail-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #17a2b8;
            color: white;
            border-radius: 3px;
            font-size: 11px;
            margin: 2px;
        }

        .log-entry {
            font-family: monospace;
            font-size: 12px;
            padding: 5px;
            border-left: 3px solid #007bff;
            margin: 5px 0;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="fa fa-shield"></i> Security Settings</h2>
                <p class="text-muted">Manage firewall, fail2ban, IP bans, and security configuration</p>
                <hr>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo Security::escape($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo Security::escape($error); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- UFW Firewall Status -->
            <div class="col-md-6">
                <div class="security-card">
                    <h3>
                        <i class="fa fa-fire"></i> UFW Firewall
                        <span class="status-badge <?php echo $ufwStatus['active'] ? 'status-active' : 'status-inactive'; ?> pull-right">
                            <?php echo $ufwStatus['active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                        </span>
                    </h3>

                    <?php if (!$ufwStatus['installed']): ?>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> UFW is not installed
                        </div>
                    <?php else: ?>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo count($ufwStatus['rules']); ?></div>
                            <div class="stat-label">Active Firewall Rules</div>
                        </div>

                        <div class="btn-group btn-group-justified" style="margin: 15px 0;">
                            <a href="#" class="btn btn-success" onclick="toggleUFW(true); return false;">
                                <i class="fa fa-check"></i> Enable Firewall
                            </a>
                            <a href="#" class="btn btn-danger" onclick="toggleUFW(false); return false;">
                                <i class="fa fa-times"></i> Disable Firewall
                            </a>
                        </div>

                        <h4>Firewall Rules</h4>
                        <div class="ip-list">
                            <?php foreach ($ufwStatus['rules'] as $rule): ?>
                                <div class="ip-item">
                                    <span>
                                        <strong><?php echo Security::escape($rule['port']); ?>/<?php echo Security::escape($rule['protocol']); ?></strong>
                                        - <?php echo Security::escape($rule['action']); ?>
                                        from <?php echo Security::escape($rule['from']); ?>
                                    </span>
                                    <button class="btn btn-xs btn-danger" onclick="deleteUFWRule('<?php echo Security::escape($rule['port']); ?>', '<?php echo Security::escape($rule['protocol']); ?>')">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button class="btn btn-primary btn-block" onclick="showAddRuleModal()" style="margin-top: 15px;">
                            <i class="fa fa-plus"></i> Add Firewall Rule
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- fail2ban Status -->
            <div class="col-md-6">
                <div class="security-card">
                    <h3>
                        <i class="fa fa-ban"></i> fail2ban
                        <span class="status-badge <?php echo $fail2banStatus['running'] ? 'status-active' : 'status-inactive'; ?> pull-right">
                            <?php echo $fail2banStatus['running'] ? 'RUNNING' : 'STOPPED'; ?>
                        </span>
                    </h3>

                    <?php if (!$fail2banStatus['installed']): ?>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> fail2ban is not installed
                        </div>
                    <?php else: ?>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $securityStats['fail2ban']['total_banned']; ?></div>
                            <div class="stat-label">Currently Banned IPs</div>
                        </div>

                        <h4>Active Jails <button class="btn btn-xs btn-info" onclick="reloadFail2ban()"><i class="fa fa-refresh"></i> Reload</button></h4>
                        <div class="ip-list">
                            <?php foreach ($fail2banStatus['jails'] as $jail): ?>
                                <?php $jailStatus = FirewallManager::getJailStatus($jail); ?>
                                <div class="ip-item">
                                    <span>
                                        <strong><?php echo Security::escape($jail); ?></strong>
                                        <br>
                                        <small>
                                            Banned: <?php echo $jailStatus['currently_banned']; ?> |
                                            Failed: <?php echo $jailStatus['total_failed']; ?>
                                        </small>
                                    </span>
                                    <button class="btn btn-xs btn-info" onclick="showJailDetails('<?php echo Security::escape($jail); ?>')">
                                        <i class="fa fa-eye"></i> Details
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Banned IPs -->
            <div class="col-md-6">
                <div class="security-card">
                    <h3>
                        <i class="fa fa-lock"></i> Banned IPs
                        <span class="badge"><?php echo count($bannedIPs); ?></span>
                    </h3>

                    <button class="btn btn-danger btn-sm" onclick="showBanIPModal()" style="margin-bottom: 15px;">
                        <i class="fa fa-plus"></i> Ban IP Address
                    </button>

                    <div class="ip-list">
                        <?php foreach ($bannedIPs as $ban): ?>
                            <div class="ip-item">
                                <div>
                                    <strong><?php echo Security::escape($ban['ip_address']); ?></strong>
                                    <br>
                                    <small><?php echo Security::escape($ban['reason']); ?></small>
                                    <br>
                                    <small class="text-muted">
                                        By: <?php echo Security::escape($ban['banned_by']); ?> |
                                        <?php echo $ban['permanent'] ? 'Permanent' : 'Expires: ' . $ban['expires_at']; ?>
                                    </small>
                                </div>
                                <button class="btn btn-xs btn-success" onclick="unbanIP('<?php echo Security::escape($ban['ip_address']); ?>')">
                                    <i class="fa fa-unlock"></i> Unban
                                </button>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($bannedIPs)): ?>
                            <p class="text-center text-muted" style="padding: 20px;">No banned IPs</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Whitelisted IPs -->
            <div class="col-md-6">
                <div class="security-card">
                    <h3>
                        <i class="fa fa-check-circle"></i> Whitelisted IPs
                        <span class="badge"><?php echo count($whitelistedIPs); ?></span>
                    </h3>

                    <button class="btn btn-success btn-sm" onclick="showWhitelistModal()" style="margin-bottom: 15px;">
                        <i class="fa fa-plus"></i> Whitelist IP Address
                    </button>

                    <div class="ip-list">
                        <?php foreach ($whitelistedIPs as $wl): ?>
                            <div class="ip-item">
                                <div>
                                    <strong><?php echo Security::escape($wl['ip_address']); ?></strong>
                                    <br>
                                    <small><?php echo Security::escape($wl['reason']); ?></small>
                                    <br>
                                    <small class="text-muted">Added by: <?php echo Security::escape($wl['banned_by']); ?></small>
                                </div>
                                <button class="btn btn-xs btn-danger" onclick="unbanIP('<?php echo Security::escape($wl['ip_address']); ?>')">
                                    <i class="fa fa-trash"></i> Remove
                                </button>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($whitelistedIPs)): ?>
                            <p class="text-center text-muted" style="padding: 20px;">No whitelisted IPs</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Security Events -->
        <div class="row">
            <div class="col-md-12">
                <div class="security-card">
                    <h3><i class="fa fa-list"></i> Recent Security Events</h3>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php
                        $recentEvents = SecurityLogger::getRecentSecurityEvents(50);
                        foreach ($recentEvents as $event):
                        ?>
                            <div class="log-entry"><?php echo Security::escape($event); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals would go here -->
    <!-- Ban IP Modal -->
    <!-- Whitelist IP Modal -->
    <!-- Add UFW Rule Modal -->
    <!-- Jail Details Modal -->

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';

        // AJAX helper
        function ajaxCall(action, data, callback) {
            data.ajax = 1;
            data.action = action;
            data.csrf_token = csrfToken;

            $.post('security_settings.php', data, callback, 'json')
                .fail(function(xhr) {
                    alert('Error: ' + (xhr.responseJSON?.error || 'Request failed'));
                });
        }

        function toggleUFW(enable) {
            const action = enable ? 'enable_ufw' : 'disable_ufw';
            const confirmed = confirm(enable ?
                'Are you sure you want to ENABLE the firewall?' :
                'WARNING: Disabling the firewall will leave your server unprotected. Continue?'
            );

            if (!confirmed) return;

            ajaxCall(action, {}, function(response) {
                if (response.success) {
                    alert('UFW ' + (enable ? 'enabled' : 'disabled') + ' successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        function deleteUFWRule(port, protocol) {
            if (!confirm('Delete firewall rule for port ' + port + '?')) return;

            ajaxCall('delete_ufw_rule', {port, protocol}, function(response) {
                if (response.success) {
                    alert('Rule deleted');
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        function unbanIP(ip) {
            if (!confirm('Unban IP: ' + ip + '?')) return;

            ajaxCall('unban_ip', {ip}, function(response) {
                if (response.success) {
                    alert('IP unbanned successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        function reloadFail2ban() {
            ajaxCall('reload_fail2ban', {}, function(response) {
                if (response.success) {
                    alert('fail2ban reloaded successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        // Modal functions (simplified - would need full implementation)
        function showBanIPModal() {
            const ip = prompt('Enter IP address to ban:');
            if (!ip) return;

            const reason = prompt('Reason for ban:', 'Manual ban from admin panel');
            const duration = prompt('Ban duration in seconds (leave empty for permanent):');

            ajaxCall('ban_ip', {
                ip,
                reason,
                duration: duration || null
            }, function(response) {
                if (response.success) {
                    alert('IP banned successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        function showWhitelistModal() {
            const ip = prompt('Enter IP address to whitelist:');
            if (!ip) return;

            const reason = prompt('Reason for whitelisting:', 'Trusted IP');

            ajaxCall('whitelist_ip', {ip, reason}, function(response) {
                if (response.success) {
                    alert('IP whitelisted successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        function showAddRuleModal() {
            const port = prompt('Enter port number:');
            if (!port) return;

            const protocol = prompt('Protocol (tcp/udp):', 'tcp');
            const action = prompt('Action (allow/deny):', 'allow');

            ajaxCall('add_ufw_rule', {port, protocol, action}, function(response) {
                if (response.success) {
                    alert('Firewall rule added');
                    location.reload();
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        function showJailDetails(jail) {
            ajaxCall('get_jail_status', {jail}, function(response) {
                if (response.success) {
                    let details = 'Jail: ' + jail + '\n\n';
                    details += 'Currently Banned: ' + response.currently_banned + '\n';
                    details += 'Total Banned: ' + response.total_banned + '\n';
                    details += 'Total Failed: ' + response.total_failed + '\n\n';
                    details += 'Banned IPs: ' + response.banned_ips.join(', ');
                    alert(details);
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            ajaxCall('get_stats', {}, function(response) {
                if (response.success) {
                    console.log('Stats updated:', response.stats);
                }
            });
        }, 30000);
    </script>
</body>
</html>
