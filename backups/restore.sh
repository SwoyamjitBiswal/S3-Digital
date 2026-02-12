#!/bin/bash

# S3 Digital - Restore Script
# Automated restore system for database and files

# Configuration
BACKUP_DIR="/var/www/html/S3_Digital/backups"
WEB_ROOT="/var/www/html/S3_Digital"
DB_NAME="s3_digital"
DB_USER="s3digital"
DB_PASS=""
LOG_FILE="$BACKUP_DIR/restore.log"

# Load environment variables
if [ -f "$WEB_ROOT/.env" ]; then
    source "$WEB_ROOT/.env"
    DB_NAME="${DB_NAME:-$DB_NAME}"
    DB_USER="${DB_USER:-$DB_USER}"
    DB_PASS="${DB_PASSWORD:-$DB_PASS}"
fi

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

# List available backups
list_backups() {
    local type=$1
    
    case $type in
        database)
            echo "Available Database Backups:"
            ls -la "$BACKUP_DIR/database"/*.gz 2>/dev/null | awk '{print $9 " " $5 " " $6 " " $7 " " $8}'
            ;;
        files)
            echo "Available Files Backups:"
            ls -la "$BACKUP_DIR/files"/*.gz 2>/dev/null | awk '{print $9 " " $5 " " $6 " " $7 " " $8}'
            ;;
        config)
            echo "Available Configuration Backups:"
            ls -la "$BACKUP_DIR/config"/*.gz 2>/dev/null | awk '{print $9 " " $5 " " $6 " " $7 " " $8}'
            ;;
        *)
            echo "Available Backups:"
            echo "=================="
            list_backups database
            echo
            list_backups files
            echo
            list_backups config
            ;;
    esac
}

# Restore database
restore_database() {
    local backup_file=$1
    
    if [ -z "$backup_file" ]; then
        echo "Available database backups:"
        list_backups database
        read -p "Enter backup file path: " backup_file
    fi
    
    # Convert relative to absolute path
    if [[ "$backup_file" != /* ]]; then
        backup_file="$BACKUP_DIR/database/$backup_file"
    fi
    
    if [ ! -f "$backup_file" ]; then
        log "ERROR: Database backup file not found: $backup_file"
        return 1
    fi
    
    log "Starting database restore from: $backup_file"
    
    # Create temporary SQL file
    local temp_sql="/tmp/restore_db_$$.sql"
    
    # Extract and restore database
    if gunzip -c "$backup_file" > "$temp_sql" 2>> "$LOG_FILE"; then
        log "Database backup extracted successfully"
        
        # Create backup of current database before restore
        local current_backup="$BACKUP_DIR/database/pre_restore_$(date +%Y%m%d_%H%M%S).sql.gz"
        if mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$current_backup" 2>> "$LOG_FILE"; then
            log "Current database backed up to: $current_backup"
        else
            log "WARNING: Failed to backup current database"
        fi
        
        # Restore database
        if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$temp_sql" 2>> "$LOG_FILE"; then
            log "Database restore completed successfully"
            rm -f "$temp_sql"
            return 0
        else
            log "ERROR: Database restore failed"
            rm -f "$temp_sql"
            return 1
        fi
    else
        log "ERROR: Failed to extract database backup"
        return 1
    fi
}

# Restore files
restore_files() {
    local backup_file=$1
    
    if [ -z "$backup_file" ]; then
        echo "Available files backups:"
        list_backups files
        read -p "Enter backup file path: " backup_file
    fi
    
    # Convert relative to absolute path
    if [[ "$backup_file" != /* ]]; then
        backup_file="$BACKUP_DIR/files/$backup_file"
    fi
    
    if [ ! -f "$backup_file" ]; then
        log "ERROR: Files backup file not found: $backup_file"
        return 1
    fi
    
    log "Starting files restore from: $backup_file"
    
    # Create backup of current files before restore
    local current_backup="$BACKUP_DIR/files/pre_restore_$(date +%Y%m%d_%H%M%S).tar.gz"
    if tar -czf "$current_backup" -C "$WEB_ROOT" uploads/ 2>> "$LOG_FILE"; then
        log "Current files backed up to: $current_backup"
    else
        log "WARNING: Failed to backup current files"
    fi
    
    # Extract files backup
    if tar -xzf "$backup_file" -C "$WEB_ROOT" 2>> "$LOG_FILE"; then
        log "Files restore completed successfully"
        
        # Set correct permissions
        chown -R www-data:www-data "$WEB_ROOT/uploads"
        chmod -R 755 "$WEB_ROOT/uploads"
        
        return 0
    else
        log "ERROR: Files restore failed"
        return 1
    fi
}

# Restore configuration
restore_config() {
    local backup_file=$1
    
    if [ -z "$backup_file" ]; then
        echo "Available configuration backups:"
        list_backups config
        read -p "Enter backup file path: " backup_file
    fi
    
    # Convert relative to absolute path
    if [[ "$backup_file" != /* ]]; then
        backup_file="$BACKUP_DIR/config/$backup_file"
    fi
    
    if [ ! -f "$backup_file" ]; then
        log "ERROR: Configuration backup file not found: $backup_file"
        return 1
    fi
    
    log "Starting configuration restore from: $backup_file"
    
    # Create backup of current configuration before restore
    local current_backup="$BACKUP_DIR/config/pre_restore_$(date +%Y%m%d_%H%M%S).tar.gz"
    if tar -czf "$current_backup" -C "$WEB_ROOT" .env config.production.php includes/ 2>> "$LOG_FILE"; then
        log "Current configuration backed up to: $current_backup"
    else
        log "WARNING: Failed to backup current configuration"
    fi
    
    # Extract configuration backup
    if tar -xzf "$backup_file" -C "$WEB_ROOT" 2>> "$LOG_FILE"; then
        log "Configuration restore completed successfully"
        
        # Set correct permissions
        chown www-data:www-data "$WEB_ROOT/.env"
        chmod 600 "$WEB_ROOT/.env"
        chown www-data:www-data "$WEB_ROOT/config.production.php"
        chmod 600 "$WEB_ROOT/config.production.php"
        
        return 0
    else
        log "ERROR: Configuration restore failed"
        return 1
    fi
}

# Full restore
full_restore() {
    local backup_id=$1
    
    if [ -z "$backup_id" ]; then
        echo "Available backup sets:"
        ls -1 "$BACKUP_DIR/database" | grep -o '[0-9]\{8\}_[0-9]\{6\}' | sort -r | head -10
        read -p "Enter backup ID (YYYYMMDD_HHMMSS): " backup_id
    fi
    
    log "Starting full restore for backup ID: $backup_id"
    
    local db_backup="$BACKUP_DIR/database/db_backup_$backup_id.sql.gz"
    local files_backup="$BACKUP_DIR/files/files_backup_$backup_id.tar.gz"
    local config_backup="$BACKUP_DIR/config/config_backup_$backup_id.tar.gz"
    
    local restore_success=true
    
    # Restore database
    if [ -f "$db_backup" ]; then
        if ! restore_database "$db_backup"; then
            restore_success=false
        fi
    else
        log "WARNING: Database backup not found for ID: $backup_id"
    fi
    
    # Restore files
    if [ -f "$files_backup" ]; then
        if ! restore_files "$files_backup"; then
            restore_success=false
        fi
    else
        log "WARNING: Files backup not found for ID: $backup_id"
    fi
    
    # Restore configuration
    if [ -f "$config_backup" ]; then
        if ! restore_config "$config_backup"; then
            restore_success=false
        fi
    else
        log "WARNING: Configuration backup not found for ID: $backup_id"
    fi
    
    if [ "$restore_success" = true ]; then
        log "Full restore completed successfully"
        return 0
    else
        log "Full restore completed with errors"
        return 1
    fi
}

# Verify restore integrity
verify_restore() {
    log "Verifying restore integrity..."
    
    local verification_failed=false
    
    # Check database
    if mysql -u "$DB_USER" -p"$DB_PASS" -e "SELECT COUNT(*) FROM users LIMIT 1;" "$DB_NAME" >/dev/null 2>&1; then
        log "Database verification passed"
    else
        log "ERROR: Database verification failed"
        verification_failed=true
    fi
    
    # Check files
    if [ -d "$WEB_ROOT/uploads" ] && [ -r "$WEB_ROOT/uploads" ]; then
        log "Files verification passed"
    else
        log "ERROR: Files verification failed"
        verification_failed=true
    fi
    
    # Check configuration
    if [ -f "$WEB_ROOT/.env" ] && [ -f "$WEB_ROOT/config.production.php" ]; then
        log "Configuration verification passed"
    else
        log "ERROR: Configuration verification failed"
        verification_failed=true
    fi
    
    if [ "$verification_failed" = true ]; then
        return 1
    fi
    
    return 0
}

# Generate restore report
generate_restore_report() {
    local restore_id=$1
    local status=$2
    local report_file="$BACKUP_DIR/restore_report_$restore_id.txt"
    
    cat > "$report_file" << EOF
S3 Digital Restore Report
=========================
Date: $(date)
Restore ID: $restore_id
Status: $status

Database Restore:
- Status: $([ -f "$BACKUP_DIR/database/db_backup_$restore_id.sql.gz" ] && echo "Completed" || echo "Not Found")
- Latest Backup: $(ls -t "$BACKUP_DIR/database"/*.gz 2>/dev/null | head -1 | xargs basename)

Files Restore:
- Status: $([ -f "$BACKUP_DIR/files/files_backup_$restore_id.tar.gz" ] && echo "Completed" || echo "Not Found")
- Latest Backup: $(ls -t "$BACKUP_DIR/files"/*.gz 2>/dev/null | head -1 | xargs basename)

Configuration Restore:
- Status: $([ -f "$BACKUP_DIR/config/config_backup_$restore_id.tar.gz" ] && echo "Completed" || echo "Not Found")
- Latest Backup: $(ls -t "$BACKUP_DIR/config"/*.gz 2>/dev/null | head -1 | xargs basename)

Pre-Restore Backups:
- Database: $(ls -t "$BACKUP_DIR/database"/pre_restore_*.gz 2>/dev/null | head -1 | xargs basename)
- Files: $(ls -t "$BACKUP_DIR/files"/pre_restore_*.gz 2>/dev/null | head -1 | xargs basename)
- Config: $(ls -t "$BACKUP_DIR/config"/pre_restore_*.gz 2>/dev/null | head -1 | xargs basename)

Next Steps:
1. Verify website functionality
2. Test database connections
3. Check file uploads
4. Test AI features
5. Monitor system performance

EOF
    
    log "Restore report generated: $report_file"
}

# Send notification
send_notification() {
    local status=$1
    local message="S3 Digital Restore $status on $(date)"
    
    # Email notification (configure as needed)
    if command -v mail >/dev/null 2>&1; then
        echo "$message" | mail -s "S3 Digital Restore $status" admin@yourdomain.com
    fi
}

# Script usage
usage() {
    echo "Usage: $0 [OPTION] [FILE]"
    echo "Options:"
    echo "  -h, --help         Show this help message"
    echo "  -l, --list         List available backups"
    echo "  -d, --db FILE     Restore database from FILE"
    echo "  -f, --files FILE   Restore files from FILE"
    echo "  -c, --config FILE  Restore configuration from FILE"
    echo "  --full ID         Full restore from backup ID"
    echo "  --verify          Verify restore integrity"
    echo ""
    echo "Examples:"
    echo "  $0 --list                    # List all backups"
    echo "  $0 --db db_backup_20240101.sql.gz  # Restore database"
    echo "  $0 --full 20240101_120000     # Full restore"
    echo "  $0 --verify                  # Verify current state"
}

# Main restore function
main() {
    local restore_id=$(date +%Y%m%d_%H%M%S)
    local restore_success=true
    
    case "${1:-}" in
        -h|--help)
            usage
            exit 0
            ;;
        -l|--list)
            list_backups
            exit 0
            ;;
        -d|--db)
            if restore_database "$2"; then
                verify_restore && send_notification "SUCCESS" "Database restore completed"
            else
                send_notification "FAILED" "Database restore failed"
                exit 1
            fi
            ;;
        -f|--files)
            if restore_files "$2"; then
                verify_restore && send_notification "SUCCESS" "Files restore completed"
            else
                send_notification "FAILED" "Files restore failed"
                exit 1
            fi
            ;;
        -c|--config)
            if restore_config "$2"; then
                verify_restore && send_notification "SUCCESS" "Configuration restore completed"
            else
                send_notification "FAILED" "Configuration restore failed"
                exit 1
            fi
            ;;
        --full)
            if full_restore "$2"; then
                verify_restore && send_notification "SUCCESS" "Full restore completed"
            else
                send_notification "FAILED" "Full restore failed"
                exit 1
            fi
            ;;
        --verify)
            if verify_restore; then
                log "System verification passed"
                exit 0
            else
                log "System verification failed"
                exit 1
            fi
            ;;
        "")
            echo "No action specified. Use --help for usage."
            exit 1
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
    
    generate_restore_report "$restore_id" "SUCCESS"
}

# Handle command line arguments
main "$@"
