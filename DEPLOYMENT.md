# S3 Digital - Deployment Guide

## 🚀 Production Deployment Guide

This guide will help you deploy the S3 Digital e-commerce platform to production with all AI-powered features and security enhancements.

### 📋 Prerequisites

#### Server Requirements
- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher / MariaDB 10.2 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **SSL Certificate**: Required for production
- **Memory**: Minimum 2GB RAM (4GB recommended)
- **Storage**: Minimum 20GB disk space

#### PHP Extensions
```bash
php-mysql
php-curl
php-json
php-mbstring
php-xml
php-zip
php-gd
php-intl
php-bcmath
php-opcache (recommended)
```

#### Required Services
- **MySQL/MariaDB**: Database server
- **SMTP Server**: For email functionality
- **HTTPS**: SSL certificate for secure connections

---

## 🗂️ File Structure

```
S3 Digital/
├── .env.example              # Environment template
├── .env                      # Environment configuration (create this)
├── config.production.php     # Production configuration
├── database/
│   ├── migrations/           # Database migration files
│   └── database_clean.sql   # Initial database schema
├── includes/                 # Core classes and libraries
├── assets/                   # Static assets
├── uploads/                  # User uploads (create this)
├── logs/                     # Application logs (create this)
├── backups/                  # Database backups (create this)
└── errors/                   # Custom error pages (create this)
```

---

## 🔧 Step-by-Step Deployment

### 1. Server Setup

#### Install Dependencies
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php8.1 php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml php8.1-zip php8.1-gd php8.1-intl php8.1-bcmath php8.1-opcache

# CentOS/RHEL
sudo yum install php81 php81-mysql php81-curl php81-json php81-mbstring php81-xml php81-zip php81-gd php81-intl php81-bcmath php81-opcache

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo systemctl restart apache2
```

#### Create Required Directories
```bash
mkdir -p /var/www/html/S3_Digital/uploads
mkdir -p /var/www/html/S3_Digital/logs
mkdir -p /var/www/html/S3_Digital/backups
mkdir -p /var/www/html/S3_Digital/errors

# Set permissions
chown -R www-data:www-data /var/www/html/S3_Digital
chmod -R 755 /var/www/html/S3_Digital
chmod -R 777 /var/www/html/S3_Digital/uploads
chmod -R 777 /var/www/html/S3_Digital/logs
chmod -R 777 /var/www/html/S3_Digital/backups
```

### 2. Database Setup

#### Create Database
```sql
CREATE DATABASE s3_digital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 's3digital'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON s3_digital.* TO 's3digital'@'localhost';
FLUSH PRIVILEGES;
```

#### Run Migrations
```bash
# Import initial schema
mysql -u s3digital -p s3_digital < database/database_clean.sql

# Run AI enhancements
mysql -u s3digital -p s3_digital < database_ai_enhancements.sql

# Or use migration system (recommended)
php -r "
require_once 'migrations/Migration.php';
require_once 'config.production.php';
\$migration = new Migration(\$conn);
\$migration->migrate();
"
```

### 3. Environment Configuration

#### Create .env File
```bash
cp .env.example .env
nano .env
```

#### Configure Environment Variables
```bash
# Essential settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=base64:GENERATE_32_CHAR_KEY_HERE

# Database
DB_HOST=localhost
DB_NAME=s3_digital
DB_USER=s3digital
DB_PASSWORD=your_strong_password

# Email
MAIL_DRIVER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_email_password

# Security
CSRF_TOKEN_EXPIRY=3600
SECURE_HEADERS=true

# Performance
OPCACHE_ENABLE=true
MEMORY_LIMIT=256M
```

#### Generate Application Key
```bash
php -r "echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

### 4. Web Server Configuration

#### Apache Configuration
```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/S3_Digital
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    
    # PHP Configuration
    <Directory "/var/www/html/S3_Digital">
        AllowOverride All
        Require all granted
        
        # Performance
        php_value memory_limit 256M
        php_value max_execution_time 30
        php_value upload_max_filesize 10M
        php_value post_max_size 20M
        
        # Security
        php_flag display_errors off
        php_flag log_errors on
        php_value error_log /var/www/html/S3_Digital/logs/php_errors.log
    </Directory>
    
    # URL Rewriting
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
    
    # Error Pages
    ErrorDocument 404 /errors/404.php
    ErrorDocument 500 /errors/500.php
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/html/S3_Digital;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # PHP Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        fastcgi_param MEMORY_LIMIT 256M;
        fastcgi_param MAX_EXECUTION_TIME 30;
    }
    
    # URL Rewriting
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # Security
    location ~ /\. {
        deny all;
    }
    
    # File Uploads
    location /uploads/ {
        location ~ \.php$ {
            deny all;
        }
    }
    
    # Error Pages
    error_page 404 /errors/404.php;
    error_page 500 /errors/500.php;
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

### 5. Application Configuration

#### Update Configuration Files
```bash
# Update config.php to use production config
sed -i 's/require_once.*config.php/require_once "config.production.php";/' index.php
```

#### Create Error Pages
```php
// errors/404.php
<!DOCTYPE html>
<html>
<head>
    <title>Page Not Found - S3 Digital</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h1>404 - Page Not Found</h1>
    <p>The page you're looking for doesn't exist.</p>
    <a href="/">Go Home</a>
</body>
</html>

// errors/500.php
<!DOCTYPE html>
<html>
<head>
    <title>Server Error - S3 Digital</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h1>500 - Server Error</h1>
    <p>We're experiencing technical difficulties. Please try again later.</p>
    <a href="/">Go Home</a>
</body>
</html>
```

### 6. Security Hardening

#### File Permissions
```bash
# Secure sensitive files
chmod 600 .env
chmod 600 config.production.php
chmod 755 includes/
chmod 644 includes/*.php

# Protect uploads directory
chmod 755 uploads/
chmod 644 uploads/*.*
```

#### Create .htaccess for Security
```apache
# Protect sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "config.production.php">
    Order allow,deny
    Deny from all
</Files>

# Prevent PHP execution in uploads
<Directory "uploads">
    php_flag engine off
</Directory>

# Hide .htaccess
<Files .htaccess>
    Order allow,deny
    Deny from all
</Files>
```

### 7. Performance Optimization

#### OPcache Configuration
```ini
; /etc/php/8.1/mods-available/opcache.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.load_comments=1
opcache.fast_shutdown=1
```

#### Database Optimization
```sql
-- MySQL Configuration
SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB
SET GLOBAL innodb_log_file_size = 268435456; -- 256MB
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
SET GLOBAL innodb_flush_method = O_DIRECT;
```

### 8. Backup Setup

#### Create Backup Script
```bash
#!/bin/bash
# backups/backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/html/S3_Digital/backups"
DB_NAME="s3_digital"
DB_USER="s3digital"
DB_PASS="your_password"

# Database Backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Files Backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz /var/www/html/S3_Digital/uploads

# Clean old backups (keep 30 days)
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

#### Setup Cron Job
```bash
# Add to crontab
crontab -e

# Daily backup at 2 AM
0 2 * * * /var/www/html/S3_Digital/backups/backup.sh

# Log cleanup (weekly)
0 3 * * 0 find /var/www/html/S3_Digital/logs -name "*.log" -mtime +7 -delete
```

### 9. Monitoring Setup

#### Health Check Endpoint
```php
// health.php
<?php
require_once 'config.production.php';

$status = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => false,
    'uploads' => false,
    'logs' => false
];

// Check database
if ($conn && mysqli_query($conn, "SELECT 1")) {
    $status['database'] = true;
}

// Check directories
$status['uploads'] = is_dir('uploads') && is_writable('uploads');
$status['logs'] = is_dir('logs') && is_writable('logs');

header('Content-Type: application/json');
echo json_encode($status);
?>
```

#### Monitoring Script
```bash
#!/bin/bash
# monitoring/check_health.sh

HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" https://yourdomain.com/health.php)

if [ $HEALTH_CHECK -ne 200 ]; then
    echo "Health check failed with status: $HEALTH_CHECK"
    # Send alert (email, Slack, etc.)
    # mail -s "S3 Digital Health Alert" admin@yourdomain.com <<< "Health check failed"
fi
```

---

## 🔍 Pre-Deployment Checklist

### Security
- [ ] SSL certificate installed and configured
- [ ] Environment variables set correctly
- [ ] File permissions secured
- [ ] Security headers enabled
- [ ] Database credentials secured
- [ ] Error display disabled in production

### Performance
- [ ] OPcache enabled and configured
- [ ] Database optimized
- [ ] Caching configured
- [ ] Gzip compression enabled
- [ ] CDN configured (if applicable)

### Functionality
- [ ] Database migrations run
- [ ] All directories created with proper permissions
- [ ] Email configuration tested
- [ ] Payment gateways configured
- [ ] AI features tested
- [ ] Error pages created

### Monitoring
- [ ] Health check endpoint working
- [ ] Backup scripts scheduled
- [ ] Log rotation configured
- [ ] Monitoring alerts set up
- [ ] Uptime monitoring configured

---

## 🚀 Deployment Commands

### Quick Deploy
```bash
# 1. Setup environment
cp .env.example .env
nano .env

# 2. Run migrations
php -r "
require_once 'migrations/Migration.php';
require_once 'config.production.php';
\$migration = new Migration(\$conn);
\$migration->migrate();
"

# 3. Set permissions
chmod 600 .env
chmod 755 uploads logs backups

# 4. Test deployment
curl -f https://yourdomain.com/health.php
```

### Update Deployment
```bash
# 1. Backup current version
./backups/backup.sh

# 2. Update files
git pull origin main

# 3. Run new migrations
php -r "
require_once 'migrations/Migration.php';
require_once 'config.production.php';
\$migration = new Migration(\$conn);
\$migration->migrate();
"

# 4. Clear cache
rm -rf cache/*
```

---

## 🆘 Troubleshooting

### Common Issues

#### Database Connection Failed
```bash
# Check MySQL service
sudo systemctl status mysql

# Check credentials
mysql -u s3digital -p s3_digital

# Check configuration
grep DB_ .env
```

#### 500 Internal Server Error
```bash
# Check error logs
tail -f logs/php_errors.log

# Check permissions
ls -la uploads/ logs/

# Test PHP syntax
php -l config.production.php
```

#### SSL Certificate Issues
```bash
# Test SSL configuration
openssl s_client -connect yourdomain.com:443

# Check certificate expiry
openssl x509 -in /path/to/certificate.crt -noout -dates
```

#### Performance Issues
```bash
# Check OPcache status
php -i | grep opcache

# Monitor database
mysql -u s3digital -p -e "SHOW PROCESSLIST;"

# Check server load
top
htop
```

---

## 📞 Support

For deployment assistance:

1. **Check logs**: `tail -f logs/php_errors.log`
2. **Health check**: Visit `https://yourdomain.com/health.php`
3. **Database test**: `mysql -u s3digital -p s3_digital`
4. **File permissions**: `ls -la uploads/ logs/`

---

## 🔄 Maintenance

### Regular Tasks
- **Daily**: Check backups, monitor logs
- **Weekly**: Update security patches, clean old logs
- **Monthly**: Review performance metrics, update dependencies
- **Quarterly**: Security audit, database optimization

### Update Process
1. Backup current version
2. Test updates in staging
3. Deploy to production
4. Monitor for issues
5. Rollback if necessary

---

**🎉 Your S3 Digital platform is now production-ready with AI-powered features!**
