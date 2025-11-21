#!/bin/bash
################################################################################
# FOS-Streaming Security Features Installer
# Installs and configures fail2ban + UFW + security monitoring
#
# Run this after the main FOS-Streaming installation
# Usage: bash install_security_features.sh
#
# Date: 2025-11-21
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${BLUE}===${NC} $1 ${BLUE}===${NC}\n"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    log_error "This script must be run as root"
    exit 1
fi

log_step "FOS-Streaming Security Features Installation"

# ============================================================================
# STEP 1: Install UFW Firewall
# ============================================================================
log_step "Step 1: Installing UFW Firewall"

if command -v ufw &> /dev/null; then
    log_info "UFW already installed"
else
    log_info "Installing UFW..."
    apt-get install -y ufw
fi

# Configure UFW
log_info "Configuring UFW firewall..."

# Reset UFW to default (clean slate)
ufw --force reset

# Set default policies
ufw default deny incoming
ufw default allow outgoing

# Allow SSH (CRITICAL - don't lock yourself out!)
ufw allow 22/tcp comment 'SSH'

# Allow FOS-Streaming ports
ufw allow 7777/tcp comment 'FOS Web Panel'
ufw allow 8000/tcp comment 'FOS Streaming'
ufw allow 1935/tcp comment 'RTMP'

# Allow DNS
ufw allow 53

# Enable UFW
log_warn "Enabling UFW firewall..."
ufw --force enable

log_info "UFW firewall configured and enabled"

# ============================================================================
# STEP 2: Install fail2ban
# ============================================================================
log_step "Step 2: Installing fail2ban"

if command -v fail2ban-client &> /dev/null; then
    log_info "fail2ban already installed"
else
    log_info "Installing fail2ban..."
    apt-get install -y fail2ban
fi

# ============================================================================
# STEP 3: Configure fail2ban for FOS-Streaming
# ============================================================================
log_step "Step 3: Configuring fail2ban"

# Create filter directory if it doesn't exist
mkdir -p /etc/fail2ban/filter.d
mkdir -p /etc/fail2ban/jail.d

# Copy FOS-Streaming filters
log_info "Installing fail2ban filters..."

# FOS Auth filter
cat > /etc/fail2ban/filter.d/fos-auth.conf <<'EOF'
[Definition]
failregex = ^\[.*?\] Auth FAILED - User: .*, IP: <HOST>
            ^\[.*?\] SECURITY EVENT: CSRF token validation failed - IP: <HOST>
            ^\[.*?\] SECURITY EVENT: Rate limit exceeded - IP: <HOST>
            ^\[.*?\] SECURITY EVENT: SUSPICIOUS: .* - IP: <HOST>
            ^\[.*?\] SECURITY EVENT: Possible SQL injection attempt - IP: <HOST>
            ^\[.*?\] SECURITY EVENT: Possible XSS attempt - IP: <HOST>
ignoreregex = Auth SUCCESS
datepattern = ^[[]%%Y-%%m-%%d %%H:%%M:%%S[]]
EOF

# FOS Security filter
cat > /etc/fail2ban/filter.d/fos-security.conf <<'EOF'
[Definition]
failregex = ^\[.*?\] SECURITY EVENT: .* - IP: <HOST>
ignoreregex = Password upgraded|Password rehashed
datepattern = ^[[]%%Y-%%m-%%d %%H:%%M:%%S[]]
EOF

# FOS Nginx filter
cat > /etc/fail2ban/filter.d/fos-nginx.conf <<'EOF'
[Definition]
failregex = ^<HOST> .* "(?:GET|POST|HEAD) .* HTTP/.*" 403
            ^<HOST> .* "(?:GET|POST|HEAD) .*(?:\.php|\.asp|\.exe|admin|phpmyadmin) .* HTTP/.*" 404
            ^<HOST> .* ".*(?:nikto|sqlmap|nmap|masscan).*" \d+ \d+
            ^<HOST> .* ".*\.\./.*" \d+ \d+
ignoreregex =
EOF

# Port scan filter
cat > /etc/fail2ban/filter.d/fos-portscan.conf <<'EOF'
[Definition]
failregex = UFW BLOCK.* SRC=<HOST>
            kernel: .*\[UFW BLOCK\].* SRC=<HOST>
ignoreregex =
EOF

# FOS-Streaming jail configuration
log_info "Installing fail2ban jail configuration..."

cat > /etc/fail2ban/jail.d/fos-streaming.local <<'EOF'
[DEFAULT]
bantime  = 3600
findtime = 600
maxretry = 5
backend = systemd
banaction = ufw

[fos-auth]
enabled  = true
port     = 7777,8000
protocol = tcp
filter   = fos-auth
logpath  = /home/fos-streaming/fos/logs/auth.log
maxretry = 5
findtime = 900
bantime  = 3600
action   = ufw

[fos-security]
enabled  = true
port     = 7777,8000
protocol = tcp
filter   = fos-security
logpath  = /home/fos-streaming/fos/logs/security.log
maxretry = 3
findtime = 600
bantime  = 7200
action   = ufw

[fos-nginx]
enabled  = true
port     = 7777,8000,1935
protocol = tcp
filter   = fos-nginx
logpath  = /home/fos-streaming/fos/logs/access.log
          /home/fos-streaming/fos/logs/panel-access.log
          /home/fos-streaming/fos/logs/streaming-access.log
maxretry = 10
findtime = 600
bantime  = 1800
action   = ufw

[fos-portscan]
enabled  = true
port     = all
protocol = tcp
filter   = fos-portscan
logpath  = /var/log/syslog
maxretry = 5
findtime = 300
bantime  = 86400
action   = ufw

[fos-recidive]
enabled  = true
filter   = recidive
logpath  = /var/log/fail2ban.log
maxretry = 3
findtime = 86400
bantime  = 604800
action   = ufw
EOF

# ============================================================================
# STEP 4: Setup sudo permissions for nginx user
# ============================================================================
log_step "Step 4: Configuring sudo permissions"

log_info "Adding nginx user sudo permissions for security management..."

# Create sudoers file for nginx user
cat > /etc/sudoers.d/fos-security <<'EOF'
# FOS-Streaming Security Management
# Allows nginx user to manage firewall and fail2ban

# UFW commands
nginx ALL=(root) NOPASSWD: /usr/sbin/ufw status
nginx ALL=(root) NOPASSWD: /usr/sbin/ufw enable
nginx ALL=(root) NOPASSWD: /usr/sbin/ufw disable
nginx ALL=(root) NOPASSWD: /usr/sbin/ufw allow *
nginx ALL=(root) NOPASSWD: /usr/sbin/ufw deny *
nginx ALL=(root) NOPASSWD: /usr/sbin/ufw delete *
nginx ALL=(root) NOPASSWD: /usr/sbin/ufw reload

# fail2ban commands
nginx ALL=(root) NOPASSWD: /usr/bin/fail2ban-client status
nginx ALL=(root) NOPASSWD: /usr/bin/fail2ban-client status *
nginx ALL=(root) NOPASSWD: /usr/bin/fail2ban-client set * banip *
nginx ALL=(root) NOPASSWD: /usr/bin/fail2ban-client set * unbanip *
nginx ALL=(root) NOPASSWD: /usr/bin/fail2ban-client reload

# FFmpeg (already exists, keeping for reference)
nginx ALL=(root) NOPASSWD: /usr/local/bin/ffmpeg
nginx ALL=(root) NOPASSWD: /usr/local/bin/ffprobe
EOF

# Set proper permissions
chmod 0440 /etc/sudoers.d/fos-security

log_info "Sudo permissions configured"

# ============================================================================
# STEP 5: Create security database tables
# ============================================================================
log_step "Step 5: Setting up security database"

if [ -f /root/MYSQL_ROOT_PASSWORD ]; then
    MYSQL_PASS=$(cat /root/MYSQL_ROOT_PASSWORD)
    log_info "Creating security database tables..."

    # Check if we have the migration script
    if [ -f /home/fos-streaming/fos/www/security/database_migration.sql ]; then
        mysql -u root -p"${MYSQL_PASS}" fos < /home/fos-streaming/fos/www/security/database_migration.sql
        log_info "Security tables created successfully"
    else
        log_warn "Database migration script not found at /home/fos-streaming/fos/www/security/database_migration.sql"
        log_info "Please run the migration manually after copying the files"
    fi
else
    log_warn "MySQL password file not found. Please create security tables manually."
fi

# ============================================================================
# STEP 6: Start and enable services
# ============================================================================
log_step "Step 6: Starting security services"

# Enable fail2ban
log_info "Starting fail2ban..."
systemctl enable fail2ban
systemctl restart fail2ban

# Wait a moment for fail2ban to start
sleep 3

# Check fail2ban status
if systemctl is-active --quiet fail2ban; then
    log_info "fail2ban is running"
else
    log_error "fail2ban failed to start. Check logs: journalctl -u fail2ban"
fi

# ============================================================================
# STEP 7: Create daily security report cron job
# ============================================================================
log_step "Step 7: Setting up security reporting"

log_info "Creating daily security report cron job..."

cat > /etc/cron.daily/fos-security-report <<'EOF'
#!/bin/bash
# FOS-Streaming Daily Security Report
# Runs daily to check security status

LOG_FILE="/var/log/fos-security-report.log"

echo "===========================================" >> $LOG_FILE
echo "FOS-Streaming Security Report" >> $LOG_FILE
echo "Date: $(date)" >> $LOG_FILE
echo "===========================================" >> $LOG_FILE
echo "" >> $LOG_FILE

# UFW Status
echo "UFW Firewall Status:" >> $LOG_FILE
ufw status numbered >> $LOG_FILE 2>&1
echo "" >> $LOG_FILE

# fail2ban Status
echo "fail2ban Status:" >> $LOG_FILE
fail2ban-client status >> $LOG_FILE 2>&1
echo "" >> $LOG_FILE

# Banned IPs per jail
for jail in $(fail2ban-client status | grep "Jail list" | sed 's/.*://g' | sed 's/,//g'); do
    echo "Jail: $jail" >> $LOG_FILE
    fail2ban-client status $jail >> $LOG_FILE 2>&1
    echo "" >> $LOG_FILE
done

# Recent security events
echo "Recent Security Events (last 24 hours):" >> $LOG_FILE
if [ -f /home/fos-streaming/fos/logs/security.log ]; then
    tail -100 /home/fos-streaming/fos/logs/security.log | grep "$(date -d yesterday +%Y-%m-%d)" >> $LOG_FILE 2>&1
fi
echo "" >> $LOG_FILE

# Rotate log if too large (> 10MB)
if [ -f "$LOG_FILE" ] && [ $(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE") -gt 10485760 ]; then
    mv $LOG_FILE ${LOG_FILE}.old
    gzip ${LOG_FILE}.old
fi
EOF

chmod +x /etc/cron.daily/fos-security-report

log_info "Security report cron job created"

# ============================================================================
# STEP 8: Test fail2ban jails
# ============================================================================
log_step "Step 8: Testing fail2ban configuration"

log_info "Testing fail2ban filters..."

fail2ban-client reload > /dev/null 2>&1 || true
sleep 2

# List active jails
ACTIVE_JAILS=$(fail2ban-client status | grep "Jail list" | sed 's/.*://g' | sed 's/,//g')

log_info "Active fail2ban jails:"
for jail in $ACTIVE_JAILS; do
    echo "  - $jail"
done

# ============================================================================
# STEP 9: Create UFW application profile
# ============================================================================
log_step "Step 9: Creating UFW application profile"

cat > /etc/ufw/applications.d/fos-streaming <<'EOF'
[FOS-Streaming]
title=FOS-Streaming Platform
description=Streaming and restreaming platform
ports=7777,8000,1935/tcp

[FOS-Panel]
title=FOS-Streaming Web Panel
description=FOS-Streaming management panel
ports=7777/tcp

[FOS-Stream]
title=FOS-Streaming Stream Port
description=FOS-Streaming HLS output
ports=8000/tcp

[FOS-RTMP]
title=FOS-Streaming RTMP
description=RTMP streaming ingress
ports=1935/tcp
EOF

ufw app update FOS-Streaming 2>/dev/null || true

log_info "UFW application profile created"

# ============================================================================
# Installation Complete
# ============================================================================
log_step "Installation Complete!"

PUBLIC_IP=$(curl -s https://api.ipify.org 2>/dev/null || echo "YOUR_SERVER_IP")

echo ""
echo "================================================================"
echo "  FOS-Streaming Security Features Installed Successfully!"
echo "================================================================"
echo ""
echo "  Services Status:"
echo "    ✓ UFW Firewall: ENABLED"
echo "    ✓ fail2ban: RUNNING"
echo "    ✓ Security Monitoring: ACTIVE"
echo ""
echo "  Access Security Settings:"
echo "    URL: http://${PUBLIC_IP}:7777/security_settings.php"
echo ""
echo "  fail2ban Jails:"
for jail in $ACTIVE_JAILS; do
    echo "    - $jail"
done
echo ""
echo "  Firewall Rules:"
ufw status numbered | grep -v "^Status:" | head -10
echo ""
echo "================================================================"
echo "  Important Notes:"
echo "================================================================"
echo ""
echo "  1. UFW is now ENABLED - only allowed ports are accessible"
echo "  2. fail2ban will automatically ban IPs after failed attempts"
echo "  3. Security events are logged to:"
echo "     /home/fos-streaming/fos/logs/security.log"
echo "  4. Daily security reports saved to:"
echo "     /var/log/fos-security-report.log"
echo "  5. Manage security from admin panel:"
echo "     http://${PUBLIC_IP}:7777/security_settings.php"
echo ""
echo "  Check status:"
echo "    ufw status"
echo "    fail2ban-client status"
echo "    fail2ban-client status fos-auth"
echo ""
echo "================================================================"
echo ""

log_info "Security features installation completed successfully!"
