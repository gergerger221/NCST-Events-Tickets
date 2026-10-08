# 🏭 Production Deployment & Operations Checklist

This document serves as the institutional IT operations guide for deploying and maintaining the **NCST Events & Tickets System** in a production campus environment.

---

## 1. System Requirements

### Server Environment
* **Operating System**: Ubuntu 22.04 LTS / Debian 12 / Windows Server 2022
* **Web Server**: Apache 2.4+ (with `mod_rewrite` and `mod_headers` enabled) or Nginx 1.22+
* **PHP Version**: PHP 8.1, 8.2, or 8.3
  * Required PHP Extensions: `pdo_mysql`, `openssl`, `mbstring`, `json`, `filter`
* **Database**: MySQL 8.0+ or MariaDB 10.4+
* **Node.js**: v18.0.0+ (LTS) for compiling frontend production bundles
* **SSL Certificate**: Valid TLS/SSL Certificate (e.g. Let's Encrypt or institutional wildcard)

---

## 2. Pre-Deployment Setup Checklist

### Step 1: Clone Repository & Install Dependencies
```bash
# Clone repository
git clone <repository-url> /var/www/ncst-events
cd /var/www/ncst-events

# Install frontend dependencies
npm ci

# Compile production frontend bundle
npm run build
```

### Step 2: Database Initialization
1. Create a dedicated MySQL database and an unprivileged user:
   ```sql
   CREATE DATABASE event_attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

   CREATE USER 'ncst_event_user'@'localhost' IDENTIFIED BY 'StrongRandomPasswordHere!@#';
   GRANT SELECT, INSERT, UPDATE, DELETE ON event_attendance_db.* TO 'ncst_event_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. Import the initial database schema:
   ```bash
   mysql -u ncst_event_user -p event_attendance_db < database.sql
   ```
3. Run the automated setup and table verification script:
   ```bash
   php api/setup.php
   ```

### Step 3: Production Environment File (`.env`)
1. Create the production `.env` from template:
   ```bash
   cp .env.example .env
   ```
2. Generate a cryptographically secure 64-character hex key for `APP_KEY`:
   ```bash
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```
3. Populate `.env`:
   ```env
   # Application Environment
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=<64_CHARACTER_GENERATED_HEX_KEY>

   # Database Configuration
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=event_attendance_db
   DB_USER=ncst_event_user
   DB_PASS=StrongRandomPasswordHere!@#

   # Security & Session Settings
   SESSION_SECRET=<ANOTHER_SECURE_RANDOM_KEY>
   TOKEN_EXPIRY_HOURS=24
   RATE_LIMIT_LOGIN_MAX=5
   RATE_LIMIT_LOGIN_WINDOW=60

   # CORS Allowed Origins (Comma-separated)
   CORS_ALLOWED_ORIGINS=https://events.ncst.edu.ph
   ```

---

## 3. Web Server Configuration

### Apache VirtualHost Configuration
Ensure `mod_rewrite` and `mod_headers` are enabled:
```bash
sudo a2enmod rewrite headers ssl
```

Configure `/etc/apache2/sites-available/ncst-events.conf`:
```apache
<VirtualHost *:443>
    ServerName events.ncst.edu.ph
    DocumentRoot /var/www/ncst-events/dist

    # SSL Certificate Paths
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/events.ncst.edu.ph.crt
    SSLCertificateKeyFile /etc/ssl/private/events.ncst.edu.ph.key

    # Proxy or Alias API Requests to PHP Backend
    Alias /api /var/www/ncst-events/api
    <Directory /var/www/ncst-events/api>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Single Page Application (SPA) Routing for Frontend
    <Directory /var/www/ncst-events/dist>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>

    # Security Headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Static Assets Long-Term Cache
    <LocationMatch "\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$">
        Header set Cache-Control "max-age=31536000, public, immutable"
    </LocationMatch>

    ErrorLog ${APACHE_LOG_DIR}/ncst_events_error.log
    CustomLog ${APACHE_LOG_DIR}/ncst_events_access.log combined
</VirtualHost>
```

---

## 4. Pre-Launch Verification & Testing

Before declaring the deployment live, run the automated regression test suite:
```bash
npm test
```
Verify that:
* All 19 frontend integration tests pass.
* All backend PHP security and delta sync tests pass.
* Run a type check: `npx vue-tsc --noEmit` returns exit code 0.

---

## 5. Security Maintenance & Best Practices

1. **Protect Sensitive Files**:
   Ensure `.env`, `.git`, and `.server_db.json` are not web-accessible. Verify with:
   ```bash
   curl -I https://events.ncst.edu.ph/.env
   # Must return 403 Forbidden or 404 Not Found
   ```
2. **Rotate `APP_KEY`**:
   The `APP_KEY` signs all HMAC authentication tokens. If compromised, rotate the key in `.env`. Existing tokens will cleanly invalidate, prompting staff to log in again.
3. **Database Backups**:
   Schedule daily compressed database backups via cron:
   ```bash
   # Add to crontab: /etc/cron.daily/backup-ncst-db
   #!/bin/bash
   DATE=$(date +%Y%m%d_%H%M%S)
   mysqldump -u ncst_event_user -p'StrongRandomPasswordHere!@#' event_attendance_db | gzip > /var/backups/ncst_db_$DATE.sql.gz
   find /var/backups/ -name "ncst_db_*.sql.gz" -mtime +30 -delete
   ```
4. **Rate Limiting**:
   The built-in login rate limiter in [`api/auth_helper.php`](file:///c:/xampp/htdocs/Integ%202/api/auth_helper.php) restricts brute-force attempts to 5 per minute per IP. For high-volume deployment, additional rate limiting can be configured via fail2ban or Cloudflare WAF.
