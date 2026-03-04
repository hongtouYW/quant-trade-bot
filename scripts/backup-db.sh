#!/bin/bash
# MySQL/MariaDB backup script for Trading SaaS
# Usage: Add to crontab: 0 3 * * * /opt/trading-saas/scripts/backup-db.sh
#
# Keeps last 7 daily backups

set -euo pipefail

BACKUP_DIR="/opt/trading-saas/backups"
DB_NAME="${MYSQL_DATABASE:-trading_saas}"
DB_USER="${MYSQL_USER:-trading_app}"
DB_PASS="${MYSQL_PASSWORD:-}"
DB_HOST="${MYSQL_HOST:-localhost}"
KEEP_DAYS=7

# Create backup dir
mkdir -p "$BACKUP_DIR"

# Generate filename
DATE=$(date +%Y%m%d_%H%M%S)
FILENAME="${BACKUP_DIR}/${DB_NAME}_${DATE}.sql.gz"

# Dump and compress
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
  --single-transaction --quick --routines --triggers \
  "$DB_NAME" | gzip > "$FILENAME"

echo "[Backup] Created: $FILENAME ($(du -h "$FILENAME" | cut -f1))"

# Clean old backups
find "$BACKUP_DIR" -name "*.sql.gz" -type f -mtime +${KEEP_DAYS} -delete
echo "[Backup] Cleaned backups older than ${KEEP_DAYS} days"

# List remaining
echo "[Backup] Current backups:"
ls -lh "$BACKUP_DIR"/*.sql.gz 2>/dev/null || echo "  (none)"
