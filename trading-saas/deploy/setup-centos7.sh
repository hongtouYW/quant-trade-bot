#!/bin/bash
# ═══════════════════════════════════════════════════════
# Trading SaaS - CentOS 7 Server Setup
# ═══════════════════════════════════════════════════════
set -e

echo "═══════════════════════════════════════════"
echo "  Trading SaaS - CentOS 7 Setup"
echo "═══════════════════════════════════════════"

# ── 1. Install Python 3.9 from IUS/source ──
echo ""
echo "[1/9] Installing Python 3.9..."
if python3.9 --version 2>/dev/null; then
    echo "  ✓ Python 3.9 already installed"
else
    yum install -y gcc openssl-devel bzip2-devel libffi-devel zlib-devel \
        wget make sqlite-devel > /dev/null 2>&1
    cd /tmp
    if [ ! -f Python-3.9.18.tgz ]; then
        wget -q https://www.python.org/ftp/python/3.9.18/Python-3.9.18.tgz
    fi
    tar xzf Python-3.9.18.tgz
    cd Python-3.9.18
    ./configure --enable-optimizations --prefix=/usr/local > /dev/null 2>&1
    make -j$(nproc) > /dev/null 2>&1
    make altinstall > /dev/null 2>&1
    ln -sf /usr/local/bin/python3.9 /usr/local/bin/python3
    ln -sf /usr/local/bin/pip3.9 /usr/local/bin/pip3
    echo "  ✓ Python 3.9 installed from source"
fi
/usr/local/bin/python3.9 --version

# ── 2. Install MariaDB ──
echo ""
echo "[2/9] Installing MariaDB..."
if systemctl is-active --quiet mariadb 2>/dev/null; then
    echo "  ✓ MariaDB already running"
else
    yum install -y mariadb-server mariadb-devel > /dev/null 2>&1
    systemctl start mariadb
    systemctl enable mariadb
    echo "  ✓ MariaDB installed and started"
fi

# Configure DB
if [ -f /root/.trading-saas-setup ]; then
    source /root/.trading-saas-setup
    MYSQL_PASS=${MYSQL_PASSWORD}
    echo "  Using saved MySQL password"
else
    MYSQL_PASS=$(/usr/local/bin/python3.9 -c "import secrets; print(secrets.token_urlsafe(24))")
fi

mysql -e "CREATE DATABASE IF NOT EXISTS trading_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
mysql -e "GRANT ALL PRIVILEGES ON trading_saas.* TO 'trading_app'@'localhost' IDENTIFIED BY '${MYSQL_PASS}';" 2>/dev/null
mysql -e "FLUSH PRIVILEGES;" 2>/dev/null
echo "  ✓ Database: trading_saas, User: trading_app"
echo "MYSQL_PASSWORD=${MYSQL_PASS}" > /root/.trading-saas-setup
chmod 600 /root/.trading-saas-setup

# ── 3. Install Redis ──
echo ""
echo "[3/9] Installing Redis..."
if systemctl is-active --quiet redis 2>/dev/null; then
    echo "  ✓ Redis already running"
else
    yum install -y epel-release > /dev/null 2>&1
    yum install -y redis > /dev/null 2>&1
    systemctl start redis
    systemctl enable redis
    echo "  ✓ Redis installed and started"
fi

# ── 4. Install Nginx ──
echo ""
echo "[4/9] Installing Nginx..."
if systemctl is-active --quiet nginx 2>/dev/null; then
    echo "  ✓ Nginx already running"
else
    yum install -y nginx > /dev/null 2>&1
    systemctl enable nginx
    echo "  ✓ Nginx installed"
fi

# ── 5. Install Node.js 20 ──
echo ""
echo "[5/9] Installing Node.js..."
if node --version 2>/dev/null | grep -q "v2"; then
    echo "  ✓ Node.js already installed: $(node --version)"
else
    curl -fsSL https://rpm.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
    yum install -y nodejs > /dev/null 2>&1
    echo "  ✓ Node.js $(node --version) installed"
fi

# ── 6. Python venv + deps ──
echo ""
echo "[6/9] Setting up Python backend..."
cd /opt/trading-saas
mkdir -p logs

if [ ! -d venv ]; then
    /usr/local/bin/python3.9 -m venv venv
fi
source venv/bin/activate
pip install --upgrade pip -q 2>&1 | tail -1
pip install -r requirements.txt -q 2>&1 | tail -1
echo "  ✓ Python dependencies installed"

# ── 7. Frontend build ──
echo ""
echo "[7/9] Building frontend..."
cd /opt/trading-saas/frontend
if [ -f package.json ]; then
    npm install --silent 2>&1 | tail -1
    npm run build 2>&1 | tail -3
    echo "  ✓ Frontend built"
else
    echo "  ⚠ No frontend found"
fi

# ── 8. Generate .env + init DB ──
echo ""
echo "[8/9] Environment + database..."
cd /opt/trading-saas

if [ ! -f .env ]; then
    source venv/bin/activate
    FLASK_SECRET=$(/usr/local/bin/python3.9 -c "import secrets; print(secrets.token_hex(32))")
    JWT_SECRET=$(/usr/local/bin/python3.9 -c "import secrets; print(secrets.token_hex(32))")
    ENC_KEY=$(/usr/local/bin/python3.9 -c "import os; print(os.urandom(32).hex())")

    cat > .env << ENVEOF
FLASK_SECRET_KEY=${FLASK_SECRET}
FLASK_ENV=production
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_DATABASE=trading_saas
MYSQL_USER=trading_app
MYSQL_PASSWORD=${MYSQL_PASS}
JWT_SECRET_KEY=${JWT_SECRET}
REDIS_URL=redis://127.0.0.1:6379/0
ENCRYPTION_MASTER_KEY=${ENC_KEY}
CORS_ORIGINS=http://139.162.41.38
BOT_SCAN_INTERVAL=60
ENVEOF
    chmod 600 .env
    echo "  ✓ .env generated"
else
    echo "  ✓ .env exists"
fi

# Init DB tables
source venv/bin/activate
/usr/local/bin/python3.9 -c "
from app import create_app
app = create_app()
with app.app_context():
    from app.extensions import db
    db.create_all()
    print('  ✓ Database tables created')
"

# Create default admin
/usr/local/bin/python3.9 -c "
from app import create_app
from app.extensions import db
from app.models.admin import Admin
import bcrypt

app = create_app()
with app.app_context():
    existing = Admin.query.filter_by(username='admin').first()
    if existing:
        print('  ✓ Admin already exists')
    else:
        pw_hash = bcrypt.hashpw('admin123'.encode(), bcrypt.gensalt()).decode()
        admin = Admin(username='admin', email='admin@trading-saas.local',
                      password_hash=pw_hash, is_active=True)
        db.session.add(admin)
        db.session.commit()
        print('  ✓ Default admin: admin / admin123')
        print('  ⚠ CHANGE PASSWORD IMMEDIATELY!')
"

# ── 9. Nginx + Systemd ──
echo ""
echo "[9/9] Starting services..."

# Fix systemd service for CentOS (Type=notify needs gunicorn support, use simple)
sed -i 's/Type=notify/Type=simple/' /opt/trading-saas/deploy/trading-saas.service
# Fix python path
sed -i "s|/opt/trading-saas/venv/bin:/usr/local/bin:/usr/bin|/opt/trading-saas/venv/bin:/usr/local/bin:/usr/bin:/usr/local/sbin|" /opt/trading-saas/deploy/trading-saas.service

# Nginx config
cp /opt/trading-saas/deploy/nginx-trading-saas.conf /etc/nginx/conf.d/trading-saas.conf
# CentOS uses conf.d, not sites-enabled
nginx -t 2>&1
echo "  ✓ Nginx configured"

# Systemd
cp /opt/trading-saas/deploy/trading-saas.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable trading-saas

# Open firewall if firewalld is running
if systemctl is-active --quiet firewalld; then
    firewall-cmd --permanent --add-port=80/tcp > /dev/null 2>&1
    firewall-cmd --reload > /dev/null 2>&1
    echo "  ✓ Firewall port 80 opened"
fi

# Start services
systemctl restart nginx
systemctl restart trading-saas
sleep 3

echo ""
if systemctl is-active --quiet trading-saas; then
    echo "  ✓ Backend running!"
else
    echo "  ✗ Backend failed:"
    journalctl -u trading-saas -n 10 --no-pager
fi

if systemctl is-active --quiet nginx; then
    echo "  ✓ Nginx running!"
else
    echo "  ✗ Nginx failed"
fi

HEALTH=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:5200/health 2>/dev/null || echo "000")
echo "  Health check: ${HEALTH}"

echo ""
echo "═══════════════════════════════════════════"
echo "  Setup Complete!"
echo "═══════════════════════════════════════════"
echo ""
echo "  Frontend: http://139.162.41.38/"
echo "  API:      http://139.162.41.38/api/auth/admin/login"
echo "  Health:   http://139.162.41.38/health"
echo "  Admin:    admin / admin123"
echo ""
