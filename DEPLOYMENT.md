# Shooting Game Deployment Guide

## Overview
This deployment script automates the deployment of your shooting game to a production server.

## Prerequisites

### Server Requirements
- Linux/Unix server with SSH access
- PHP 7.4+ installed
- MySQL/MariaDB database
- Apache or Nginx web server
- `rsync`, `tar`, `mysql` command-line tools

### Local Requirements
- **For Linux/Mac**: Bash shell, SSH client, rsync
- **For Windows**: PuTTY tools (pscp, plink), robocopy

### Configuration Required
1. **Update script variables**:
   - `REMOTE_HOST` - Your server hostname/IP
   - `REMOTE_USER` - SSH username
   - `REMOTE_PATH` - Deployment directory on server

2. **Configure `config.env`**:
   ```bash
   DB_HOST=your_database_host
   DB_NAME=your_database_name
   DB_USER=your_database_user
   DB_PASS=your_secure_password
   ENVIRONMENT=production
   ```

## Usage

### Linux/Mac
```bash
# Make script executable
chmod +x deploy.sh

# Basic deployment
./deploy.sh

# Deployment with database setup
./deploy.sh --setup-db

# Show help
./deploy.sh --help
```

### Windows
```cmd
# Basic deployment
deploy.bat

# Deployment with database setup
deploy.bat --setup-db

# Show help
deploy.bat --help
```

## What the Script Does

### Pre-deployment Checks
- Verifies `config.env` exists
- Checks required PHP files are present
- Validates assets directory exists

### Deployment Process
1. **Backup**: Creates timestamped backup of existing deployment
2. **File Transfer**: Syncs files using rsync (Linux) or robocopy (Windows)
3. **Exclusions**: Skips `.git`, SQL files, logs, temp files
4. **Permissions**: Sets appropriate file permissions
5. **Database**: Optionally imports SQL schema files
6. **Verification**: Confirms deployment succeeded
7. **Cleanup**: Removes old backups (keeps last 5)

### File Permissions Set
- PHP/JS/CSS/HTML files: `644`
- `config.env`: `600` (restricted access)
- Directories: `755`
- `.htaccess`: `644`

## Database Setup

When using `--setup-db` flag, the script will:
- Import `database_schema.sql` if exists
- Import `rate_limit_table.sql` if exists  
- Import `password_resets_table.sql` if exists

## Security Notes

- `config.env` contains sensitive data and is set to `600` permissions
- Database credentials should be different from development
- Consider using environment variables for production secrets
- Regularly rotate database passwords

## Troubleshooting

### Common Issues
1. **SSH Connection Failed**
   - Verify SSH keys are set up
   - Check firewall settings
   - Test manual SSH connection first

2. **Permission Denied**
   - Ensure remote user has write access to deployment directory
   - Check sudo requirements for permission changes

3. **Database Connection Failed**
   - Verify database credentials in `config.env`
   - Check database server is running
   - Ensure user has required privileges

### Manual Verification
After deployment, test:
- Main page loads: `http://your-server.com/`
- Game page works: `http://your-server.com/game.php`
- Login/registration functions
- Database connectivity

## Rollback

If deployment fails, restore from backup:
```bash
# On remote server
cd /path/to/backups
tar -xzf backup_YYYYMMDD_HHMMSS.tar.gz -C /path/to/deployment/
```

## Production Considerations

1. **Environment**: Set `ENVIRONMENT=production` in `config.env`
2. **Error Reporting**: Disable PHP error display in production
3. **HTTPS**: Configure SSL certificate
4. **Backup**: Regular database backups
5. **Monitoring**: Set up uptime monitoring
6. **Updates**: Test updates in staging first

## Customization

You can modify the script to:
- Add custom file exclusions
- Implement additional verification steps
- Send deployment notifications
- Integrate with CI/CD pipelines
