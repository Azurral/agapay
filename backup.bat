@echo off
rem AGAPAY nightly backup: the database (as agapay.sql) and the uploaded/generated files
rem (damage photos, reports) into a dated folder. Old backups are removed after KEEP_DAYS.
rem Schedule it with Windows Task Scheduler - see "Installing at the office" in README.md.
setlocal

rem ===== Settings: change these to match the office PC =====
if not defined XAMPP set "XAMPP=C:\xampp"
if not defined DB_NAME set "DB_NAME=agapay"
if not defined DB_USER set "DB_USER=root"
if not defined DB_PASS set "DB_PASS="
if not defined BACKUP_DIR set "BACKUP_DIR=D:\AGAPAY-Backups"
if not defined KEEP_DAYS set "KEEP_DAYS=30"
rem ===========================================================

set "APP=%~dp0"
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set "STAMP=%%i"
set "TARGET=%BACKUP_DIR%\%STAMP%"

if not exist "%XAMPP%\mysql\bin\mysqldump.exe" (
    echo BACKUP FAILED: mysqldump not found in %XAMPP%\mysql\bin - set XAMPP at the top of backup.bat.
    exit /b 1
)
mkdir "%TARGET%" 2>nul
if not exist "%TARGET%" (
    echo BACKUP FAILED: cannot create %TARGET% - check BACKUP_DIR at the top of backup.bat.
    exit /b 1
)

set "PASSARG="
if defined DB_PASS set "PASSARG=--password=%DB_PASS%"

"%XAMPP%\mysql\bin\mysqldump.exe" --user=%DB_USER% %PASSARG% --single-transaction --default-character-set=utf8mb4 %DB_NAME% > "%TARGET%\agapay.sql"
if errorlevel 1 (
    echo BACKUP FAILED: the database could not be copied. Is MySQL started in XAMPP?
    exit /b 1
)

robocopy "%APP%storage\app\private" "%TARGET%\files" /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP >nul
if errorlevel 8 (
    echo BACKUP FAILED: the photo and report files could not be copied.
    exit /b 1
)

rem Keep the last KEEP_DAYS days of backups.
forfiles /p "%BACKUP_DIR%" /d -%KEEP_DAYS% /c "cmd /c if @isdir==TRUE rmdir /s /q @path" >nul 2>nul

echo Backup saved to %TARGET%
exit /b 0
