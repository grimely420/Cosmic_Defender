@echo off
REM Shooting Game Deployment Script for Windows
REM This script deploys the shooting game to a production server

setlocal enabledelayedexpansion

REM Configuration
set REMOTE_HOST=
set REMOTE_USER=
set REMOTE_PATH=
set LOCAL_PATH=%cd%

REM Colors for output
set "RED=[91m"
set "GREEN=[92m"
set "YELLOW=[93m"
set "NC=[0m"

REM Logging function
:log
echo %GREEN%[%date% %time%] %~1%NC%
goto :eof

:error
echo %RED%[%date% %time%] ERROR: %~1%NC%
exit /b 1

:warning
echo %YELLOW%[%date% %time%] WARNING: %~1%NC%
goto :eof

REM Check if required configuration is set
:check_config
call :log "Checking deployment configuration..."

if "%REMOTE_HOST%"=="" call :error "Please configure REMOTE_HOST variable in the script"
if "%REMOTE_USER%"=="" call :error "Please configure REMOTE_USER variable in the script"
if "%REMOTE_PATH%"=="" call :error "Please configure REMOTE_PATH variable in the script"

call :log "Configuration check passed"
goto :eof

REM Pre-deployment checks
:pre_deploy_checks
call :log "Running pre-deployment checks..."

REM Check if config.env exists
if not exist "%LOCAL_PATH%\config.env" call :error "config.env file not found. Please create it with your database configuration."

REM Check if required PHP files exist
set "required_files=index.php game.php login.php register.php db.php config.php"
for %%f in (%required_files%) do (
    if not exist "%LOCAL_PATH%\%%f" call :error "Required file %%f not found"
)

REM Check if assets directory exists
if not exist "%LOCAL_PATH%\assets" call :error "Assets directory not found"

call :log "Pre-deployment checks passed"
goto :eof

REM Backup remote deployment
:backup_remote
call :log "Creating backup of remote deployment..."

for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set datetime=%%I
set BACKUP_NAME=backup_%datetime:~0,8%_%datetime:~8,6%

pscp -q "%REMOTE_USER%@%REMOTE_HOST%:%REMOTE_PATH%\*" "../%BACKUP_NAME%.tar.gz" >nul 2>&1

call :log "Backup created: %BACKUP_NAME%.tar.gz"
goto :eof

REM Deploy files
:deploy_files
call :log "Deploying files to remote server..."

REM Create temporary directory for deployment
set "TEMP_DIR=%TEMP%\shooting_game_deploy_%random%"

REM Copy files to temporary directory
robocopy "%LOCAL_PATH%" "%TEMP_DIR%" /E /XF deploy.sh *.sql .git* /XD .git node_modules /NFL /NDL /NJH /NJS

REM Copy to remote server
pscp -r -q "%TEMP_DIR%\*" "%REMOTE_USER%@%REMOTE_HOST%:%REMOTE_PATH%\" >nul 2>&1

REM Clean up temporary directory
rmdir /s /q "%TEMP_DIR%" >nul 2>&1

call :log "Files deployed successfully"
goto :eof

REM Set permissions
:set_permissions
call :log "Setting file permissions..."

plink -q "%REMOTE_USER%@%REMOTE_HOST%" "cd %REMOTE_PATH% && chmod 644 *.php *.css *.js *.html && chmod 600 config.env && chmod 755 . && chmod -R 755 assets/ && chmod 644 .htaccess"

call :log "Permissions set successfully"
goto :eof

REM Database setup (optional)
:setup_database
if "%1"=="--setup-db" (
    call :log "Setting up database..."
    
    if exist "%LOCAL_PATH%\database_schema.sql" (
        call :log "Found database schema, importing..."
        for /f "tokens=2 delims==" %%I in ('type "%LOCAL_PATH%\config.env" ^| findstr "DB_HOST"') do set DB_HOST=%%I
        for /f "tokens=2 delims==" %%I in ('type "%LOCAL_PATH%\config.env" ^| findstr "DB_USER"') do set DB_USER=%%I
        for /f "tokens=2 delims==" %%I in ('type "%LOCAL_PATH%\config.env" ^| findstr "DB_PASS"') do set DB_PASS=%%I
        for /f "tokens=2 delims==" %%I in ('type "%LOCAL_PATH%\config.env" ^| findstr "DB_NAME"') do set DB_NAME=%%I
        
        pscp -q "%LOCAL_PATH%\database_schema.sql" "%REMOTE_USER%@%REMOTE_HOST%:/tmp/" >nul 2>&1
        plink -q "%REMOTE_USER%@%REMOTE_HOST%" "mysql -h %DB_HOST% -u %DB_USER% -p%DB_PASS% %DB_NAME% < /tmp/database_schema.sql"
    )
    
    call :log "Database setup completed"
)
goto :eof

REM Post-deployment verification
:post_deploy_verify
call :log "Running post-deployment verification..."

plink -q "%REMOTE_USER%@%REMOTE_HOST%" "cd %REMOTE_PATH% && test -f index.php && test -f game.php && test -f config.php && test -d assets"

call :log "Post-deployment verification passed"
goto :eof

REM Cleanup old backups (keep last 5)
:cleanup_backups
call :log "Cleaning up old backups..."

plink -q "%REMOTE_USER%@%REMOTE_HOST%" "cd $(dirname %REMOTE_PATH%) && ls -t backup_*.tar.gz | tail -n +6 | xargs -r rm"

call :log "Backup cleanup completed"
goto :eof

REM Main deployment function
:deploy
call :log "Starting deployment of Shooting Game..."

call :check_config
call :pre_deploy_checks
call :backup_remote
call :deploy_files
call :set_permissions
call :setup_database %1
call :post_deploy_verify
call :cleanup_backups

call :log "Deployment completed successfully!"
call :log "Your shooting game is now live at: http://%REMOTE_HOST%"
goto :eof

REM Help function
:show_help
echo Shooting Game Deployment Script
echo.
echo Usage: %~nx0 [OPTIONS]
echo.
echo Options:
echo   --setup-db    Also set up database tables during deployment
echo   --help        Show this help message
echo.
echo Before running:
echo 1. Configure REMOTE_HOST, REMOTE_USER, and REMOTE_PATH variables
echo 2. Ensure SSH access to the remote server
echo 3. Update config.env with production database credentials
echo 4. Test deployment in staging environment first
goto :eof

REM Parse command line arguments
if "%1"=="--help" (
    call :show_help
    exit /b 0
) else if "%1"=="" (
    call :deploy
) else if "%1"=="--setup-db" (
    call :deploy --setup-db
) else (
    call :error "Unknown option: %1. Use --help for usage information."
)

pause
