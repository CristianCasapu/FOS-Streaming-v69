# FOS-Streaming v70

A powerful streaming and restreaming platform with advanced transcoding capabilities, user management, and security features.

## Features

### Core Features
- **Streaming & Restreaming**: Full RTMP/HLS/HTTP-FLV support with authentication and M3U8 playlists
- **User Management**: Complete CRUD operations for users (add, edit, delete, enable, disable)
- **Category Management**: Organize streams with categories
- **Stream Management**: Full control over streams (overview, add, edit, delete, start, stop)
- **Configuration**: Flexible settings management
- **Transcoding**: Multiple predefined transcode profiles with h264_mp4toannexb support
- **Monitoring**: Auto-restart via cron, last IP tracking, stream playback
- **Import**: Playlist import functionality
- **Advanced Features**: Multiple streams per channel, user stream limits, IP blocking, User-Agent blocking

### Security Features
- **Modern Authentication**: Argon2id password hashing (PHP 8.4)
- **CSRF Protection**: Token-based request validation
- **Rate Limiting**: Login attempt limiting and DDoS protection
- **Security Logging**: Comprehensive audit trail
- **Input Validation**: Strict input sanitization and validation
- **Security Headers**: Modern HTTP security headers via nginx
- **Session Security**: Secure session management with strict cookies

## System Requirements

### Debian 12 (Bookworm)
- **OS**: Debian 12 (Bookworm)
- **PHP**: 8.4.x
- **MariaDB**: 11.4.x
- **Nginx**: 1.26.x with HTTP-FLV module (includes RTMP + HTTP-FLV streaming)
- **FFmpeg**: Latest static build

> **⚠️ Important**: Only Debian 12 is supported. Debian 11, PHP 7.x, and Nginx 1.19.x are **no longer supported**.

## Installation

### Quick Install - Debian 12

```bash
# 1. Download and run the installation script
curl -s https://raw.githubusercontent.com/theraw/FOS-Streaming-v70/master/install/debian12 | bash

# 2. Wait for installation to complete (10-20 minutes)
```

### Post-Installation Steps

1. **Access Web Panel**
   ```
   Visit: http://your-server-ip:7777/
   Default credentials: admin / admin
   ```

2. **Configure Web IP**
   - Navigate to Settings (http://your-server-ip:7777/settings.php)
   - Change "Web ip: *" to your public IPv4 address
   - Save settings

3. **Setup Cron Job** (if not automatically configured)
   ```bash
   crontab -e
   # Add this line:
   */2 * * * * /usr/bin/php /home/fos-streaming/fos/www/cron.php
   ```

4. **Retrieve MySQL Password**
   ```bash
   cat /root/MYSQL_ROOT_PASSWORD
   ```

5. **Change Default Password** (Security Critical!)
   - Login to web panel
   - Navigate to Admin Management
   - Change the default 'admin' password immediately


## Configuration

### Change Panel Port

1. Change port in web interface: Settings → Web Port
2. Edit nginx configuration:
   ```bash
   nano /home/fos-streaming/fos/nginx/conf/nginx.conf
   # Change: listen 7777; to your desired port
   ```
3. Restart nginx:
   ```bash
   killall nginx; killall nginx_fos
   /home/fos-streaming/fos/nginx/sbin/nginx
   ```

### Service Management

```bash
# Nginx
systemctl start fos-nginx
systemctl stop fos-nginx
systemctl restart fos-nginx
systemctl status fos-nginx

# PHP-FPM
systemctl start php8.4-fpm
systemctl stop php8.4-fpm
systemctl restart php8.4-fpm
systemctl status php8.4-fpm

# MariaDB
systemctl start mariadb
systemctl stop mariadb
systemctl restart mariadb
systemctl status mariadb
```

## Usage

### Adding Your First Stream

1. **Create User**
   - Navigate to Users → Add User
   - Set username, password, and stream limits

2. **Add Stream**
   - Navigate to Streams → Add Stream
   - Select transcode profile: **Default 1** (recommended)
   - Enter stream source URL
   - Save and start stream

3. **Access Stream**
   - Format: `http://your-ip:8000/live/{username}/{password}/{stream-id}`
   - Or use the web player: Streams → Play

### Transcoding Profiles

The most stable configuration is using **Default 1** transcode profile without proxy mode.

Proxy mode is available but depends on your use case and network configuration.

## Security Best Practices

1. **Change Default Credentials**
   ```bash
   # Change admin password immediately after installation
   ```

2. **Configure Firewall**
   ```bash
   # Using UFW
   ufw allow 7777/tcp   # Web panel
   ufw allow 8000/tcp   # Streaming port
   ufw allow 1935/tcp   # RTMP port
   ufw allow 22/tcp     # SSH
   ufw enable
   ```

3. **Setup SSL/TLS** (Recommended for production)
   ```bash
   # Install certbot
   apt-get install certbot

   # Get certificate
   certbot certonly --standalone -d your-domain.com

   # Update nginx configuration to use SSL
   ```

4. **Monitor Security Logs**
   ```bash
   tail -f /home/fos-streaming/fos/logs/security.log
   tail -f /home/fos-streaming/fos/logs/auth.log
   ```

5. **Regular Updates**
   ```bash
   apt-get update
   apt-get upgrade
   ```

## Troubleshooting

### Streams Not Starting

1. Check FFmpeg:
   ```bash
   /usr/local/bin/ffmpeg -version
   ```

2. Check logs:
   ```bash
   tail -f /home/fos-streaming/fos/logs/error.log
   tail -f /home/fos-streaming/fos/logs/php-fpm.log
   ```

3. Verify permissions:
   ```bash
   ls -la /home/fos-streaming/fos/www/hl/
   # Should be owned by nginx:nginx
   ```

### Web Panel Not Accessible

1. Check nginx status:
   ```bash
   systemctl status fos-nginx
   ```

2. Verify port is listening:
   ```bash
   netstat -tlnp | grep 7777
   ```

3. Check PHP-FPM:
   ```bash
   systemctl status php8.4-fpm
   ```

### Database Connection Errors

1. Check MariaDB status:
   ```bash
   systemctl status mariadb
   ```

2. Verify database credentials:
   ```bash
   cat /home/fos-streaming/fos/www/config.php
   ```

3. Test connection:
   ```bash
   mysql -u fos -p
   # Enter password from /root/MYSQL_ROOT_PASSWORD
   ```

### Permission Issues

```bash
# Fix ownership
chown -R nginx:nginx /home/fos-streaming/fos/www
chown -R nginx:nginx /home/fos-streaming/fos/www1
chown -R fosstreaming:fosstreaming /home/fos-streaming/fos/nginx

# Fix permissions
chmod 777 /home/fos-streaming/fos/www/hl
chmod 777 /home/fos-streaming/fos/www/cache
```

## File Locations

### Important Directories

- **Web Root**: `/home/fos-streaming/fos/www/`
- **Streaming Root**: `/home/fos-streaming/fos/www1/`
- **Nginx Config**: `/home/fos-streaming/fos/nginx/conf/nginx.conf`
- **PHP-FPM Config**: `/etc/php/8.4/fpm/pool.d/www.conf`
- **Logs**: `/home/fos-streaming/fos/logs/`
- **HLS Output**: `/home/fos-streaming/fos/www/hl/`

### Log Files

- **Nginx Error**: `/home/fos-streaming/fos/logs/error.log`
- **Nginx Access**: `/home/fos-streaming/fos/logs/access.log`
- **PHP-FPM**: `/home/fos-streaming/fos/logs/php-fpm.log`
- **Security**: `/home/fos-streaming/fos/logs/security.log`
- **Authentication**: `/home/fos-streaming/fos/logs/auth.log`

## Architecture

### Components

1. **Nginx with HTTP-FLV Module**
   - Handles HTTP/HTTPS requests
   - RTMP streaming ingress
   - HTTP-FLV streaming
   - HLS segment generation
   - FastCGI to PHP-FPM

2. **PHP 8.4 with FPM**
   - Web panel application
   - Stream management API
   - User authentication
   - Database operations

3. **MariaDB 11.4**
   - User data storage
   - Stream configuration
   - Settings and metadata

4. **FFmpeg**
   - Stream transcoding
   - Format conversion
   - Bitrate adaptation

### Data Flow

```
RTMP Source → Nginx RTMP → FFmpeg Transcode → HLS Output → Nginx HTTP → Client
                ↓
           PHP Management → MariaDB
```

## API Endpoints

### Streaming URLs

- **HLS Playlist**: `http://your-ip:8000/live/{user}/{pass}/{stream}/index.m3u8`
- **Direct Stream**: `http://your-ip:8000/live/{user}/{pass}/{stream}`

### Management Panel

- **Login**: `http://your-ip:7777/`
- **Dashboard**: `http://your-ip:7777/dashboard.php`
- **Streams**: `http://your-ip:7777/streams.php`
- **Users**: `http://your-ip:7777/users.php`
- **Settings**: `http://your-ip:7777/settings.php`


## Development

### Tech Stack

- **Backend**: PHP 8.4 (Laravel Eloquent, Blade)
- **Frontend**: Bootstrap 3, jQuery, DataTables
- **Streaming**: Nginx-RTMP, FFmpeg
- **Database**: MariaDB 11.4

### Project Structure

```
/home/fos-streaming/fos/
├── nginx/              # Nginx binaries and config
│   ├── conf/
│   ├── sbin/
│   └── logs/
├── www/                # Web panel application
│   ├── lib/            # Security libraries (new)
│   ├── models/         # Eloquent models
│   ├── views/          # Blade templates
│   ├── css/            # Stylesheets
│   ├── js/             # JavaScript
│   └── *.php           # Controllers
├── www1/               # Streaming application
│   └── stream.php
└── logs/               # Application logs
```

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Test thoroughly on Debian 12
4. Submit a pull request with detailed description

## Support

- **GitHub Issues**: https://github.com/theraw/FOS-Streaming-v70/issues
- **Documentation**: See [MIGRATION_PLAN.md](MIGRATION_PLAN.md) for detailed technical information

## License

All Rights Reserved - FOS-Streaming

## Sources & Credits

1. FOS-Streaming-v1
2. FFmpeg - https://ffmpeg.org
3. Nginx - https://nginx.org
4. nginx-http-flv-module - https://github.com/winshining/nginx-http-flv-module
5. nginx-geoip2-module - https://github.com/leev/ngx_http_geoip2_module
6. MariaDB - https://mariadb.org
7. PHP - https://www.php.net

## Changelog

### Version 70.2 - Debian 12 Support (2025-11-21)

**Added:**
- Debian 12 (Bookworm) support
- PHP 8.4 compatibility
- MariaDB 11.4 support
- Nginx 1.26+ with HTTP/2, HTTP/3
- Argon2id password hashing
- CSRF token protection
- Rate limiting
- Security logging and audit trails
- Input validation and sanitization
- Modern security headers
- Systemd service files
- Comprehensive migration tools

**Security Improvements:**
- Replaced MD5 with Argon2id for passwords
- Added CSRF protection to all forms
- Implemented login rate limiting (5 attempts/15 min)
- Added security event logging
- Enhanced session security
- Input validation for all user inputs
- Security headers via nginx
- DDoS protection via rate limiting

**Changed:**
- Updated installation script for Debian 12
- Modernized PHP-FPM configuration
- Enhanced nginx configuration
- Improved error handling
- Better log management with rotation

**Documentation:**
- Added MIGRATION_PLAN.md
- Updated README with Debian 12 instructions
- Added security best practices
- Added troubleshooting guide

### Version 70 - Original Release

**Features:**
- Multi-user streaming platform
- RTMP/HLS support
- Transcoding with multiple profiles
- Web-based management panel
- User and stream management
- IP and User-Agent blocking
- Playlist import
- Auto-restart via cron

---

**Copyright © 2025 FOS-Streaming. All Rights Reserved.**
