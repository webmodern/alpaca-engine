#!/bin/bash

set -e

# Config
DEPLOY_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$DEPLOY_DIR/.."
BACKUP_DIR="$DEPLOY_DIR"
LOG_PREFIX="env"
DATE_FMT="$(date +%Y%m%d_%H%M%S)"

# Usage function
usage() {
  echo "Usage: $0 [profile]"
  echo "Profile can be: local, server, staging, etc. Or 'restore' to recover last backup."
  exit 1
}

# Check argument
PROFILE="$1"
if [ -z "$PROFILE" ]; then
  usage
fi

# Helper: Get caller IP address
get_caller_ip() {
  if [ -n "$SSH_CONNECTION" ]; then
    echo $SSH_CONNECTION | awk '{print $1}'
  else
    IPADDR=$(hostname -I 2>/dev/null | awk '{print $1}')
    [ -z "$IPADDR" ] && IPADDR="127.0.0.1"
    echo $IPADDR
  fi
}

# ==== RESTORE MODE ====
if [ "$PROFILE" = "restore" ]; then
  LAST_BACKUP=$(ls -1t "$BACKUP_DIR/.env.backup."* 2>/dev/null | head -n 1)
  LOG_FILE="$BACKUP_DIR/${LOG_PREFIX}_restore.log"
  CALLER_IP=$(get_caller_ip)

  if [ -z "$LAST_BACKUP" ]; then
    echo "No backup files found to restore!"
    echo "$(date '+%F %T'): IP connected $CALLER_IP | FAILED RESTORE: No backups found" >> "$LOG_FILE"
    exit 1
  fi

  echo "Restoring .env from $LAST_BACKUP ..."
  cp "$LAST_BACKUP" "$PROJECT_ROOT/.env"
  echo "$(date '+%F %T'): IP connected $CALLER_IP" >> "$LOG_FILE"
  echo "$(date '+%F %T'): .env restored from $LAST_BACKUP" >> "$LOG_FILE"
  echo "Config cache clearing..."
  cd "$PROJECT_ROOT"
  php artisan config:clear
  echo "Done."
  # Cleanup old logs (older than 90 days)
  find "$BACKUP_DIR" -name "${LOG_PREFIX}_*.log" -type f -mtime +90 -delete
  exit 0
fi

# ==== SWITCH MODE ====

# Prepare env file name and log file
ENV_FILE="$PROJECT_ROOT/.env.$PROFILE"
LOG_FILE="$BACKUP_DIR/${LOG_PREFIX}_${PROFILE}.log"
CALLER_IP=$(get_caller_ip)

# Log IP at start of session
echo "$(date '+%F %T'): IP connected $CALLER_IP" >> "$LOG_FILE"

# Check if env file exists
if [ ! -f "$ENV_FILE" ]; then
  echo "File $ENV_FILE does not exist! Aborting."
  echo "$(date '+%F %T'): FAILED - env file $ENV_FILE not found" >> "$LOG_FILE"
  exit 1
fi

# Backup current .env (if exists)
if [ -f "$PROJECT_ROOT/.env" ]; then
  BACKUP_FILE="$BACKUP_DIR/.env.backup.$DATE_FMT"
  cp "$PROJECT_ROOT/.env" "$BACKUP_FILE"
  echo "$(date '+%F %T'): .env backed up to $BACKUP_FILE" >> "$LOG_FILE"
fi

# Switch to selected env file
cp "$ENV_FILE" "$PROJECT_ROOT/.env"
echo "$(date '+%F %T'): .env replaced by $ENV_FILE" >> "$LOG_FILE"

# Laravel config cache clear
cd "$PROJECT_ROOT"
php artisan config:clear

# Laravel migrate
php artisan migrate --force

# Composer update
composer update --no-interaction --prefer-dist --optimize-autoloader

echo "Operation completed. See log: $LOG_FILE"

# Cleanup old logs (older than 90 days)
find "$BACKUP_DIR" -name "${LOG_PREFIX}_*.log" -type f -mtime +90 -delete

exit 0
