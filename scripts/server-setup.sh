#!/bin/bash

# ===========================================
# Production Server Setup Script
# ===========================================
# This script sets up a production server for
# the Swinx application deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_USER="swinx"
APP_DIR="/opt/swinx"
DOMAIN="swinburne.me"
EMAIL="hieupv2412@gmail.com"

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "Please run this script as root"
fi

log "🚀 Starting production server setup..."

# Update system
log "📦 Updating system packages..."
apt update && apt upgrade -y

# Install required packages
log "📦 Installing required packages..."
apt install -y \
    curl \
    wget \
    git \
    unzip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    ufw \
    fail2ban \
    htop \
    nano \
    certbot

# Install Docker
log "🐳 Installing Docker..."
if ! command -v docker &> /dev/null; then
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    apt update
    apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable docker
    systemctl start docker
    success "Docker installed successfully"
else
    log "Docker is already installed"
fi

# Install Docker Compose (standalone)
log "🐳 Installing Docker Compose..."
if ! command -v docker-compose &> /dev/null; then
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    success "Docker Compose installed successfully"
else
    log "Docker Compose is already installed"
fi

# Create application user
log "👤 Creating application user..."
if ! id "$APP_USER" &>/dev/null; then
    useradd -m -s /bin/bash "$APP_USER"
    usermod -aG docker "$APP_USER"
    success "User $APP_USER created successfully"
else
    log "User $APP_USER already exists"
fi

# Create application directory
log "📁 Creating application directory..."
mkdir -p "$APP_DIR"
chown "$APP_USER:$APP_USER" "$APP_DIR"

# Setup SSH key for deployment
log "🔑 Setting up SSH key for deployment..."
sudo -u "$APP_USER" mkdir -p "/home/$APP_USER/.ssh"
sudo -u "$APP_USER" touch "/home/$APP_USER/.ssh/authorized_keys"
chmod 700 "/home/$APP_USER/.ssh"
chmod 600 "/home/$APP_USER/.ssh/authorized_keys"
chown -R "$APP_USER:$APP_USER" "/home/$APP_USER/.ssh"

echo "Add your deployment public key to: /home/$APP_USER/.ssh/authorized_keys"

# Configure firewall
log "🔥 Configuring firewall..."
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
success "Firewall configured successfully"

# Configure fail2ban
log "🛡️ Configuring fail2ban..."
cat > /etc/fail2ban/jail.local << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3

[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 10
EOF

systemctl enable fail2ban
systemctl restart fail2ban
success "Fail2ban configured successfully"

# Setup SSL certificate with Let's Encrypt
log "🔒 Setting up SSL certificate..."
if [ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
    warning "SSL certificate not found. Please run the following command after DNS is configured:"
    echo "certbot certonly --standalone -d $DOMAIN -d www.$DOMAIN --email $EMAIL --agree-tos --non-interactive"
    
    # Create self-signed certificate for testing
    mkdir -p "$APP_DIR/ssl"
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$APP_DIR/ssl/privkey.pem" \
        -out "$APP_DIR/ssl/fullchain.pem" \
        -subj "/C=US/ST=State/L=City/O=Organization/CN=$DOMAIN"
    chown -R "$APP_USER:$APP_USER" "$APP_DIR/ssl"
    warning "Created self-signed certificate for testing"
else
    # Link Let's Encrypt certificates
    ln -sf "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "$APP_DIR/ssl/fullchain.pem"
    ln -sf "/etc/letsencrypt/live/$DOMAIN/privkey.pem" "$APP_DIR/ssl/privkey.pem"
    success "SSL certificate linked successfully"
fi

# Setup automatic certificate renewal
log "🔄 Setting up automatic certificate renewal..."
cat > /etc/cron.d/certbot << EOF
0 12 * * * root test -x /usr/bin/certbot -a \! -d /run/systemd/system && perl -e 'sleep int(rand(43200))' && certbot -q renew --deploy-hook "cd $APP_DIR && docker-compose -f docker-compose.production.yml restart nginx"
EOF

# Create necessary directories
log "📁 Creating necessary directories..."
sudo -u "$APP_USER" mkdir -p "$APP_DIR"/{logs,backups,ssl,storage/logs/nginx}

# Setup log rotation
log "📝 Setting up log rotation..."
cat > /etc/logrotate.d/swinx << EOF
$APP_DIR/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 $APP_USER $APP_USER
}

$APP_DIR/storage/logs/nginx/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 $APP_USER $APP_USER
    postrotate
        cd $APP_DIR && docker-compose -f docker-compose.production.yml exec nginx nginx -s reload
    endscript
}
EOF

# Setup system monitoring
log "📊 Setting up system monitoring..."
cat > /etc/cron.d/system-monitor << EOF
*/5 * * * * root df -h | grep -E '^/dev/' | awk '{if($5+0 > 80) print "Disk usage warning: " $0}' | logger -t disk-monitor
*/5 * * * * root free -m | awk 'NR==2{printf "Memory usage: %.2f%%\n", $3*100/$2}' | awk '{if($3+0 > 80) print "Memory usage warning: " $0}' | logger -t memory-monitor
EOF

success "🎉 Production server setup completed!"

log "📋 Next steps:"
echo "1. Add your deployment public key to: /home/$APP_USER/.ssh/authorized_keys"
echo "2. Configure DNS to point $DOMAIN to this server"
echo "3. Run: certbot certonly --standalone -d $DOMAIN -d www.$DOMAIN --email $EMAIL --agree-tos --non-interactive"
echo "4. Clone your repository to: $APP_DIR"
echo "5. Configure your GitHub secrets with server details"
echo "6. Test the deployment pipeline"

log "🔧 Server information:"
echo "- Application user: $APP_USER"
echo "- Application directory: $APP_DIR"
echo "- Domain: $DOMAIN"
echo "- SSL certificate: $APP_DIR/ssl/"
echo "- Logs: $APP_DIR/logs/"
echo "- Backups: $APP_DIR/backups/"
