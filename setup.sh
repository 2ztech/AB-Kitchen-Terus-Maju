#!/bin/bash

# ----------------------------------------------------------------------
# Kuih Raya - Production Setup Script (Local Clone Version)
# ----------------------------------------------------------------------

if [ "$(id -u)" -ne 0 ]; then
   echo "ERROR: This script must be run as root (use sudo)." 
   exit 1
fi

echo "=========================================================="
echo "   KUIH RAYA SYSTEM SETUP (LOCAL DEPLOYMENT)   "
echo "=========================================================="

read -p "Enter the Domain Name or IP Address for this site (e.g., kuihraya.com): " USER_DOMAIN
if [ -z "$USER_DOMAIN" ]; then
    USER_DOMAIN="localhost"
    echo "Defaulting to: localhost"
fi

read -p "Enable automatic daily updates from GitHub? (y/n): " AUTO_UPDATE

echo ""
echo "--- [1/6] Installing Dependencies (Apache, PHP, Git, Composer) ---"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q && apt-get upgrade -y -q
apt-get install -y apache2 git cron composer unzip \
    php php-cli php-common php-sqlite3 php-gd php-curl php-mbstring php-xml php-zip

echo "--- [2/6] Configuring Apache for $USER_DOMAIN ---"
a2enmod rewrite

cat > /etc/apache2/sites-available/kuih_raya.conf <<EOF
<VirtualHost *:80>
    ServerAdmin admin@$USER_DOMAIN
    ServerName $USER_DOMAIN
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

a2dissite 000-default.conf
a2ensite kuih_raya.conf

echo "--- [3/6] Deploying Repository & Installing Composer Packages ---"
CURRENT_DIR=$(pwd)

if [ ! -d "$CURRENT_DIR/.git" ]; then
    echo "⚠️ WARNING: It looks like you are not running this script from inside the Git repository."
    echo "Auto-updates will fail if the .git folder is missing."
fi

echo "Copying files from $CURRENT_DIR to /var/www/html..."
# Remove default apache index and recreate empty folder
rm -rf /var/www/html
mkdir -p /var/www/html

# Copy all contents (including hidden files like .git and .htaccess)
cp -a "$CURRENT_DIR"/. /var/www/html/

# Install PHPMailer via Composer
echo "Installing PHP dependencies..."
cd /var/www/html
composer install --no-dev --optimize-autoloader

echo "--- [4/6] Setting up Database & Upload Directories ---"
# Ensure db folder exists and create empty sqlite file
mkdir -p /var/www/html/db
touch /var/www/html/db/kuih_raya.db

# Explicitly create image upload directories
mkdir -p /var/www/html/images/receipts
mkdir -p /var/www/html/images/product
mkdir -p /var/www/html/images/settings

echo "--- [5/6] Finalizing Permissions ---"
# Set ownership to Apache user
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Special write permissions for SQLite DB and Image Uploads
chmod -R 775 /var/www/html/db
chmod 664 /var/www/html/db/kuih_raya.db 2>/dev/null || true
chmod -R 775 /var/www/html/images

echo "--- [6/6] Configuring Auto-Updates ---"
if [[ "$AUTO_UPDATE" =~ ^[Yy]$ ]]; then
    cat > /usr/local/bin/update_kuih_raya.sh <<EOF
#!/bin/bash
cd /var/www/html

# Git security fix: tell Git this directory is safe for the root cron job
git config --global --add safe.directory /var/www/html

git reset --hard
git pull origin main

# Update composer if composer.json changed
composer install --no-dev --optimize-autoloader

# Re-apply permissions
chown -R www-data:www-data /var/www/html
chmod -R 775 /var/www/html/db
chmod -R 775 /var/www/html/images
echo "Updated at \$(date)" >> /var/log/kuih_raya_update.log
EOF
    
    chmod +x /usr/local/bin/update_kuih_raya.sh
    (crontab -l 2>/dev/null; echo "0 3 * * * /usr/local/bin/update_kuih_raya.sh") | crontab -
    echo "✅ Auto-update enabled (Runs daily at 3 AM)"
else
    echo "Skipping auto-update setup."
fi

# Restart Apache
service apache2 restart

echo "=========================================================="
echo "✅ Setup Complete!"
echo "   - Web Root:   /var/www/html"
echo "   - Domain:     http://$USER_DOMAIN"
echo "   - Admin:      http://$USER_DOMAIN/admin"
echo "=========================================================="
