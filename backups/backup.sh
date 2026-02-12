#!/bin/bash

# S3 Digital - Backup Script
# Automated backup system for database and files

# Configuration
BACKUP_DIR="/var/www/html/S3_Digital/backups"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="s3_digital"
DB_USER="s3digital"
DB_PASS=""
WEB_ROOT="/var/www/html/S3_Digital"
RETENTION_DAYS=30
LOG_FILE="$BACKUP_DIR/backup.log"

# Load environment variables
if [ -f "$WEB_ROOT/.env" ]; then
    source "$WEB_ROOT/.env"
    DB_NAME="${DB_NAME:-$DB_NAME}"
    DB_USER="${DB_USER:-$DB_USER}"
    DB_PASS="${DB_PASSWORD:-$DB_PASS}"
fi

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"
mkdir -p "$BACKUP_DIR/database"
mkdir -p "$BACKUP_DIR/files"
mkdir -p "$BACKUP_DIR/config"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Check if database is accessible
check_database() {
    if mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
        log "Database connection successful"
        return 0
    else
        log "ERROR: Database connection failed"
        return 1
    fi
}

# Database backup
backup_database() {
    log "Starting database backup..."
    
    local backup_file="$BACKUP_DIR/database/db_backup_$DATE.sql"
    local compressed_file="$backup_file.gz"
    
    # Create database backup
    if mysqldump -u "$DB_USER" -p"$DB_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --hex-blob \
        --default-character-set=utf8mb4 \
        "$DB_NAME" > "$backup_file" 2>> "$LOG_FILE"; then
        
        # Compress backup
        gzip "$backup_file"
        
        # Verify backup
        if [ -f "$compressed_file" ]; then
            local file_size=$(du -h "$compressed_file" | cut -f1)
            log "Database backup completed: $compressed_file ($file_size)"
            return 0
        else
            log "ERROR: Database backup file not found"
            return 1
        fi
    else
        log "ERROR: Database backup failed"
        return 1
    fi
}

# Files backup
backup_files() {
    log "Starting files backup..."
    
    local backup_file="$BACKUP_DIR/files/files_backup_$DATE.tar.gz"
    
    # Create files backup (excluding unnecessary directories)
    if tar -czf "$backup_file" \
        --exclude="$BACKUP_DIR" \
        --exclude="logs/*.log" \
        --exclude="cache/*" \
        --exclude="temp/*" \
        -C "$WEB_ROOT" \
        uploads/ 2>> "$LOG_FILE"; then
        
        local file_size=$(du -h "$backup_file" | cut -f1)
        log "Files backup completed: $backup_file ($file_size)"
        return 0
    else
        log "ERROR: Files backup failed"
        return 1
    fi
}

# Configuration backup
backup_config() {
    log "Starting configuration backup..."
    
    local config_file="$BACKUP_DIR/config/config_backup_$DATE.tar.gz"
    
    # Backup configuration files
    if tar -czf "$config_file" \
        -C "$WEB_ROOT" \
        .env \
        config.production.php \
        includes/ 2>> "$LOG_FILE"; then
        
        local file_size=$(du -h "$config_file" | cut -f1)
        log "Configuration backup completed: $config_file ($file_size)"
        return 0
    else
        log "ERROR: Configuration backup failed"
        return 1
    fi
}

# Clean old backups
cleanup_old_backups() {
    log "Cleaning up old backups (older than $RETENTION_DAYS days)..."
    
    local deleted_count=0
    
    # Clean database backups
    while IFS= read -r file; do
        rm "$file"
        ((deleted_count++))
    done < <(find "$BACKUP_DIR/database" -name "*.gz" -mtime +$RETENTION_DAYS)
    
    # Clean file backups
    while IFS= read -r file; do
        rm "$file"
        ((deleted_count++))
    done < <(find "$BACKUP_DIR/files" -name "*.gz" -mtime +$RETENTION_DAYS)
    
    # Clean config backups
    while IFS= read -r file; do
        rm "$file"
        ((deleted_count++))
    done < <(find "$BACKUP_DIR/config" -name "*.gz" -mtime +$RETENTION_DAYS)
    
    log "Deleted $deleted_count old backup files"
}

# Verify backup integrity
verify_backups() {
    log "Verifying backup integrity..."
    
    local verification_failed=false
    
    # Check database backup
    local latest_db_backup=$(ls -t "$BACKUP_DIR/database"/*.gz 2>/dev/null | head -1)
    if [ -n "$latest_db_backup" ]; then
        if gzip -t "$latest_db_backup" 2>/dev/null; then
            log "Database backup integrity verified"
        else
            log "ERROR: Database backup integrity check failed"
            verification_failed=true
        fi
    else
        log "WARNING: No database backup found"
        verification_failed=true
    fi
    
    # Check files backup
    local latest_files_backup=$(ls -t "$BACKUP_DIR/files"/*.gz 2>/dev/null | head -1)
    if [ -n "$latest_files_backup" ]; then
        if tar -tzf "$latest_files_backup" >/dev/null 2>&1; then
            log "Files backup integrity verified"
        else
            log "ERROR: Files backup integrity check failed"
            verification_failed=true
        fi
    else
        log "WARNING: No files backup found"
        verification_failed=true
    fi
    
    if [ "$verification_failed" = true ]; then
        return 1
    fi
    
    return 0
}

# Generate backup report
generate_report() {
    local report_file="$BACKUP_DIR/backup_report_$DATE.txt"
    
    cat > "$report_file" << EOF
S3 Digital Backup Report
========================
Date: $(date)
Backup ID: $DATE

Database Backup:
- File: $(ls -t "$BACKUP_DIR/database"/*.gz 2>/dev/null | head -1 | xargs basename)
- Size: $(du -h "$BACKUP_DIR/database"/*.gz 2>/dev/null | head -1 | cut -f1)
- Status: $([ -f "$BACKUP_DIR/database/db_backup_$DATE.sql.gz" ] && echo "Success" || echo "Failed")

Files Backup:
- File: $(ls -t "$BACKUP_DIR/files"/*.gz 2>/dev/null | head -1 | xargs basename)
- Size: $(du -h "$BACKUP_DIR/files"/*.gz 2>/dev/null | head -1 | cut -f1)
- Status: $([ -f "$BACKUP_DIR/files/files_backup_$DATE.tar.gz" ] && echo "Success" || echo "Failed")

Configuration Backup:
- File: $(ls -t "$BACKUP_DIR/config"/*.gz 2>/dev/null | head -1 | xargs basename)
- Size: $(du -h "$BACKUP_DIR/config"/*.gz 2>/dev/null | head -1 | cut -f1)
- Status: $([ -f "$BACKUP_DIR/config/config_backup_$DATE.tar.gz" ] && echo "Success" || echo "Failed")

Storage Usage:
- Total: $(du -sh "$BACKUP_DIR" | cut -f1)
- Database: $(du -sh "$BACKUP_DIR/database" | cut -f1)
- Files: $(du -sh "$BACKUP_DIR/files" | cut -f1)
- Config: $(du -sh "$BACKUP_DIR/config" | cut -f1)

Backup Retention: $RETENTION_DAYS days
Next Cleanup: $(date -d "+$RETENTION_DAYS days" '+%Y-%m-%d')

EOF
    
    log "Backup report generated: $report_file"
}

# Send notification (optional)
send_notification() {
    local status=$1
    local message="S3 Digital Backup $status on $(date)"
    
    # Email notification (configure as needed)
    if command -v mail >/dev/null 2>&1; then
        echo "$message" | mail -s "S3 Digital Backup $status" admin@yourdomain.com
    fi
    
    # Slack notification (configure webhook URL)
    # curl -X POST -H 'Content-type: application/json' \
    #     --data "{\"text\":\"$message\"}" \
    #     https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
}

# Main backup function
main() {
    log "Starting S3 Digital backup process..."
    log "Backup ID: $DATE"
    
    local backup_success=true
    
    # Check prerequisites
    if ! check_database; then
        send_notification "FAILED" "Database connection failed"
        exit 1
    fi
    
    # Perform backups
    if ! backup_database; then
        backup_success=false
    fi
    
    if ! backup_files; then
        backup_success=false
    fi
    
    if ! backup_config; then
        backup_success=false
    fi
    
    # Verify backups
    if ! verify_backups; then
        backup_success=false
    fi
    
    # Clean old backups
    cleanup_old_backups
    
    # Generate report
    generate_report
    
    # Send notification
    if [ "$backup_success" = true ]; then
        log "Backup process completed successfully"
        send_notification "SUCCESS" "All backups completed successfully"
    else
        log "Backup process completed with errors"
        send_notification "PARTIAL" "Some backups failed - check logs"
        exit 1
    fi
}

# Script usage
usage() {
    echo "Usage: $0 [OPTION]"
    echo "Options:"
    echo "  -h, --help     Show this help message"
    echo "  -d, --db       Backup database only"
    echo "  -f, --files    Backup files only"
    echo "  -c, --config   Backup configuration only"
    echo "  -v, --verify   Verify existing backups"
    echo "  --cleanup      Clean old backups only"
    echo ""
    echo "Examples:"
    echo "  $0              # Full backup"
    echo "  $0 --db         # Database backup only"
    echo "  $0 --verify     # Verify backups"
    echo "  $0 --cleanup    # Clean old backups"
}

# Handle command line arguments
case "${1:-}" in
    -h|--help)
        usage
        exit 0
        ;;
    -d|--db)
        check_database && backup_database
        exit $?
        ;;
    -f|--files)
        backup_files
        exit $?
        ;;
    -c|--config)
        backup_config
        exit $?
        ;;
    -v|--verify)
        verify_backups
        exit $?
        ;;
    --cleanup)
        cleanup_old_backups
        exit $?
        ;;
    "")
        main
        ;;
    *)
        echo "Unknown option: $1"
        usage
        exit 1
        ;;
esac
