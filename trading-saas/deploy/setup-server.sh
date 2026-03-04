#!/bin/bash
# ═══════════════════════════════════════════════════════
# Trading SaaS - Server Setup Script
# Run this on the target server (139.162.41.38) as root
# ═══════════════════════════════════════════════════════
set -e

echo "═══════════════════════════════════════════"
echo "  Trading SaaS - Server Setup"
echo "═══════════════════════════════════════════"

# ── 1. System Update & Dependencies ──
echo ""
echo "[1/9] Installing system dependencies..."
apt-get update -qq
apt-get install -y -qq python3 python3-pip python3-venv \
    mysql-server redis-server nginx \
    build-essential libmysqlclient-dev \
    curl wget ufw > /dev/null 2>&1
echo "  ✓ System dependencies installed"

# ── 2. Node.js (for frontend build) ──
echo ""
echo "[2/9] Installing Node.js..."
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
    apt-get install -y -qq nodejs > /dev/null 2>&1
fi
echo "  ✓ Node.js $(node --version)"

# ── 3. MySQL Setup ──
echo ""
echo "[3/9] Configuring MySQL..."
systemctl start mysql
systemctl enable mysql

# Check if we have a saved password from a previous run
if [ -f /root/.trading-saas-setup ]; then
    source /root/.trading-saas-setup
    MYSQL_PASS=${MYSQL_PASSWORD}
    echo "  Using saved MySQL password"
else
    MYSQL_PASS=$(python3 -c "import secrets; print(secrets.token_urlsafe(24))")
fi

mysql -e "CREATE DATABASE IF NOT EXISTS trading_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'trading_app'@'localhost' IDENTIFIED BY '${MYSQL_PASS}';"
mysql -e "ALTER USER 'trading_app'@'localhost' IDENTIFIED BY '${MYSQL_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON trading_saas.* TO 'trading_app'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
echo "  ✓ MySQL: database=trading_saas, user=trading_app"

# ── 4. Redis Setup ──
echo ""
echo "[4/9] Configuring Redis..."
systemctl start redis-server
systemctl enable redis-server
echo "  ✓ Redis running"

# ── 5. Project Directory ──
echo ""
echo "[5/9] Checking project files..."
mkdir -p /opt/trading-saas/logs

if [ ! -f /opt/trading-saas/wsgi.py ]; then
    echo "  ⚠ Code not found in /opt/trading-saas/"
    echo "  → Upload code first, then re-run this script"
    echo "  → From your Mac:"
    echo ""
    echo "    rsync -avz --exclude='__pycache__' --exclude='*.pyc' --exclude='.git' \\"
    echo "      --exclude='trading_saas.db' --exclude='venv' --exclude='.env' \\"
    echo "      --exclude='frontend/node_modules' \\"
    echo "      /Users/hongtou/newproject/trading-saas/ root@139.162.41.38:/opt/trading-saas/"
    echo ""
    echo "MYSQL_PASSWORD=${MYSQL_PASS}" > /root/.trading-saas-setup
    chmod 600 /root/.trading-saas-setup
    echo "  Password saved to /root/.trading-saas-setup"
    exit 1
fi
echo "  ✓ Code found"

# ── 6. Python Backend ──
echo ""
echo "[6/9] Setting up Python backend..."
cd /opt/trading-saas
if [ ! -d venv ]; then
    python3 -m venv venv
fi
source venv/bin/activate
pip install --upgrade pip -q
pip install -r requirements.txt -q
echo "  ✓ Python dependencies installed"

# ── 7. Frontend Build ──
echo ""
echo "[7/9] Building React frontend..."
cd /opt/trading-saas/frontend
if [ -f package.json ]; then
    npm install --production=false --silent 2>&1 | tail -1
    npm run build 2>&1 | tail -2
    echo "  ✓ Frontend built to frontend/dist/"
else
    echo "  ⚠ No frontend/package.json found, skipping frontend build"
fi

# ── 8. Generate .env + Init DB ──
echo ""
echo "[8/9] Configuring environment and database..."
cd /opt/trading-saas

if [ ! -f .env ]; then
    FLASK_SECRET=$(python3 -c "import secrets; print(secrets.token_hex(32))")
    JWT_SECRET=$(python3 -c "import secrets; print(secrets.token_hex(32))")
    ENCRYPTION_KEY=$(python3 -c "import os; print(os.urandom(32).hex())")

    cat > .env << ENVEOF
# Trading SaaS - Production (Auto-generated $(date +%Y-%m-%d))
FLASK_SECRET_KEY=${FLASK_SECRET}
FLASK_ENV=production
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_DATABASE=trading_saas
MYSQL_USER=trading_app
MYSQL_PASSWORD=${MYSQL_PASS}
JWT_SECRET_KEY=${JWT_SECRET}
REDIS_URL=redis://127.0.0.1:6379/0
ENCRYPTION_MASTER_KEY=${ENCRYPTION_KEY}
CORS_ORIGINS=http://139.162.41.38
BOT_SCAN_INTERVAL=60
ENVEOF
    chmod 600 .env
    echo "  ✓ .env generated"
else
    echo "  ✓ .env already exists, keeping it"
fi

# Create tables
source venv/bin/activate
python3 -c "
from app import create_app
app = create_app()
with app.app_context():
    from app.extensions import db
    db.create_all()
    print('  ✓ Database tables created')
"

# Create default admin
python3 -c "
from app import create_app
from app.extensions import db
from app.models.admin import Admin
import bcrypt

app = create_app()
with app.app_context():
    existing = Admin.query.filter_by(username='admin').first()
    if existing:
        print('  ✓ Admin user already exists')
    else:
        pw_hash = bcrypt.hashpw('admin123'.encode(), bcrypt.gensalt()).decode()
        admin = Admin(
            username='admin',
            email='admin@trading-saas.local',
            password_hash=pw_hash,
            is_active=True,
        )
        db.session.add(admin)
        db.session.commit()
        print('  ✓ Default admin created (admin / admin123)')
        print('  ⚠ CHANGE THE DEFAULT PASSWORD!')
"

# ── 9. Nginx + Systemd ──
echo ""
echo "[9/9] Starting services..."

# Remove default nginx site if exists
rm -f /etc/nginx/sites-enabled/default

# Nginx
cp /opt/trading-saas/deploy/nginx-trading-saas.conf /etc/nginx/sites-available/trading-saas
ln -sf /etc/nginx/sites-available/trading-saas /etc/nginx/sites-enabled/trading-saas
nginx -t 2>&1 | head -2
echo "  ✓ Nginx configured"

# Systemd
cp /opt/trading-saas/deploy/trading-saas.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable trading-saas
echo "  ✓ Systemd service registered"

# Start/restart
systemctl restart nginx
systemctl restart trading-saas
sleep 3

# Verify
echo ""
if systemctl is-active --quiet trading-saas; then
    echo "  ✓ Backend service is running!"
else
    echo "  ✗ Backend failed. Check: journalctl -u trading-saas -n 30"
fi

if systemctl is-active --quiet nginx; then
    echo "  ✓ Nginx is running!"
else
    echo "  ✗ Nginx failed. Check: nginx -t"
fi

# Quick health check
sleep 1
HEALTH=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:5200/health 2>/dev/null || echo "000")
if [ "$HEALTH" = "200" ]; then
    echo "  ✓ Health check passed!"
else
    echo "  ⚠ Health check returned: ${HEALTH}"
fi

echo ""
echo "═══════════════════════════════════════════"
echo "  Setup Complete!"
echo "═══════════════════════════════════════════"
echo ""
echo "  Frontend:  http://139.162.41.38/"
echo "  API:       http://139.162.41.38/api/auth/admin/login"
echo "  Health:    http://139.162.41.38/health"
echo ""
echo "  Admin: admin / admin123"
echo "  ⚠ Change the admin password immediately!"
echo ""
echo "  Commands:"
echo "    systemctl status trading-saas"
echo "    journalctl -u trading-saas -f"
echo "    systemctl restart trading-saas"
echo ""
