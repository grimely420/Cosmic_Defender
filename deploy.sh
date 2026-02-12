#!/bin/bash

# Shooting Game Deployment Script
# This script deploys the shooting game to a production server

set -e  # Exit on any error

# Configuration
REMOTE_HOST=""  # Set your remote host
REMOTE_USER=""  # Set your remote user
REMOTE_PATH=""  # Set your remote deployment path
LOCAL_PATH="$(pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
    exit 1
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

# Check if required configuration is set
check_config() {
    log "Checking deployment configuration..."
    
    if [ -z "$REMOTE_HOST" ] || [ -z "$REMOTE_USER" ] || [ -z "$REMOTE_PATH" ]; then
        error "Please configure REMOTE_HOST, REMOTE_USER, and REMOTE_PATH variables in the script"
    fi
}

# Pre-deployment checks
pre_deploy_checks() {
    log "Running pre-deployment checks..."
    
    # Check if config.env exists
    if [ ! -f "$LOCAL_PATH/config.env" ]; then
        error "config.env file not found. Please create it with your database configuration."
    fi
    
    # Check if required PHP files exist
    required_files=("index.php" "game.php" "login.php" "register.php" "db.php" "config.php")
    for file in "${required_files[@]}"; do
        if [ ! -f "$LOCAL_PATH/$file" ]; then
            error "Required file $file not found"
        fi
    done
    
    # Check if assets directory exists
    if [ ! -d "$LOCAL_PATH/assets" ]; then
        error "Assets directory not found"
    fi
    
    log "Pre-deployment checks passed"
}

# Backup remote deployment
backup_remote() {
    log "Creating backup of remote deployment..."
    
    BACKUP_NAME="backup_$(date +%Y%m%d_%H%M%S)"
    ssh "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_PATH && tar -czf ../$BACKUP_NAME.tar.gz ."
    
    log "Backup created: $BACKUP_NAME.tar.gz"
}

# Deploy files
deploy_files() {
    log "Deploying files to remote server..."
    
    # Create temporary directory for deployment
    TEMP_DIR="/tmp/shooting_game_deploy_$(date +%s)"
    
    # Copy files to temporary directory, excluding unwanted files
    mkdir -p "$TEMP_DIR"
    rsync -av --exclude-from='.gitignore' \
          --exclude='deploy.sh' \
          --exclude='*.sql' \
          --exclude='.git/' \
          --exclude='node_modules/' \
          "$LOCAL_PATH/" "$TEMP_DIR/"
    
    # Copy to remote server
    rsync -avz "$TEMP_DIR/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/"
    
    # Clean up temporary directory
    rm -rf "$TEMP_DIR"
    
    log "Files deployed successfully"
}

# Set permissions
set_permissions() {
    log "Setting file permissions..."
    
    ssh "$REMOTE_USER@$REMOTE_HOST" "
        cd $REMOTE_PATH &&
        chmod 644 *.php *.css *.js *.html &&
        chmod 600 config.env &&
        chmod 755 . &&
        chmod -R 755 assets/ &&
        chmod 644 .htaccess
    "
    
    log "Permissions set successfully"
}

# Database setup (optional)
setup_database() {
    if [ "$1" = "--setup-db" ]; then
        log "Setting up database..."
        
        # Check if SQL files exist
        if [ -f "$LOCAL_PATH/database_schema.sql" ]; then
            log "Found database schema, importing..."
            ssh "$REMOTE_USER@$REMOTE_HOST" "mysql -h $(grep DB_HOST $LOCAL_PATH/config.env | cut -d'=' -f2) -u $(grep DB_USER $LOCAL_PATH/config.env | cut -d'=' -f2) -p$(grep DB_PASS $LOCAL_PATH/config.env | cut -d'=' -f2) $(grep DB_NAME $LOCAL_PATH/config.env | cut -d'=' -f2) < $REMOTE_PATH/database_schema.sql"
        fi
        
        if [ -f "$LOCAL_PATH/rate_limit_table.sql" ]; then
            log "Setting up rate limit table..."
            ssh "$REMOTE_USER@$REMOTE_HOST" "mysql -h $(grep DB_HOST $LOCAL_PATH/config.env | cut -d'=' -f2) -u $(grep DB_USER $LOCAL_PATH/config.env | cut -d'=' -f2) -p$(grep DB_PASS $LOCAL_PATH/config.env | cut -d'=' -f2) $(grep DB_NAME $LOCAL_PATH/config.env | cut -d'=' -f2) < $REMOTE_PATH/rate_limit_table.sql"
        fi
        
        if [ -f "$LOCAL_PATH/password_resets_table.sql" ]; then
            log "Setting up password resets table..."
            ssh "$REMOTE_USER@$REMOTE_HOST" "mysql -h $(grep DB_HOST $LOCAL_PATH/config.env | cut -d'=' -f2) -u $(grep DB_USER $LOCAL_PATH/config.env | cut -d'=' -f2) -p$(grep DB_PASS $LOCAL_PATH/config.env | cut -d'=' -f2) $(grep DB_NAME $LOCAL_PATH/config.env | cut -d'=' -f2) < $REMOTE_PATH/password_resets_table.sql"
        fi
        
        log "Database setup completed"
    fi
}

# Post-deployment verification
post_deploy_verify() {
    log "Running post-deployment verification..."
    
    # Check if main files exist on remote server
    ssh "$REMOTE_USER@$REMOTE_HOST" "
        cd $REMOTE_PATH &&
        test -f index.php || exit 1 &&
        test -f game.php || exit 1 &&
        test -f config.php || exit 1 &&
        test -d assets || exit 1
    "
    
    log "Post-deployment verification passed"
}

# Cleanup old backups (keep last 5)
cleanup_backups() {
    log "Cleaning up old backups..."
    
    ssh "$REMOTE_USER@$REMOTE_HOST" "
        cd $(dirname $REMOTE_PATH) &&
        ls -t backup_*.tar.gz | tail -n +6 | xargs -r rm
    "
    
    log "Backup cleanup completed"
}

# Main deployment function
deploy() {
    log "Starting deployment of Shooting Game..."
    
    check_config
    pre_deploy_checks
    backup_remote
    deploy_files
    set_permissions
    setup_database "$1"
    post_deploy_verify
    cleanup_backups
    
    log "Deployment completed successfully!"
    log "Your shooting game is now live at: http://$REMOTE_HOST"
}

# Help function
show_help() {
    echo "Shooting Game Deployment Script"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --setup-db    Also set up database tables during deployment"
    echo "  --help        Show this help message"
    echo ""
    echo "Before running:"
    echo "1. Configure REMOTE_HOST, REMOTE_USER, and REMOTE_PATH variables"
    echo "2. Ensure SSH access to the remote server"
    echo "3. Update config.env with production database credentials"
    echo "4. Test deployment in staging environment first"
}

# Parse command line arguments
case "$1" in
    --help)
        show_help
        exit 0
        ;;
    --setup-db)
        deploy --setup-db
        ;;
    "")
        deploy
        ;;
    *)
        error "Unknown option: $1. Use --help for usage information."
        ;;
esac
