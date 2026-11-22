@echo off
setlocal

rem PostgreSQL connection settings: update these for the database to back up.
set "dbHost=localhost"
set "dbPort=5432"
set "dbName=grompil"
set "dbUser=postgres"

rem Prefer %APPDATA%\postgresql\pgpass.conf. Optionally set the Windows
rem environment variable GROMPIL_DB_PASS to use it here instead.
set "dbPass=apo110"

rem Backups are written outside the project folder by default.
set "backupDir=C:\usr\Dropbox\grompil_backups"
set "pgDumpExe=C:\usr\progs\PostgreSQL\18\bin\pg_dump.exe"
set "PGPASSFILE=C:\usr\progs\PostgreSQL\pgpass.conf"

if not exist "%backupDir%" mkdir "%backupDir%"
if errorlevel 1 (
    echo ERROR: Could not create backup directory "%backupDir%".
    exit /b 1
)

for /f %%I in ('powershell.exe -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "timeStamp=%%I"
if not defined timeStamp (
    echo ERROR: Could not generate a timestamp.
    exit /b 1
)

set "fileNameOut=%backupDir%\%dbName%_%timeStamp%.dump"

if not "%dbPass%"=="" set "PGPASSWORD=%dbPass%"

echo Creating PostgreSQL backup:
echo   %fileNameOut%
"%pgDumpExe%" -h "%dbHost%" -p "%dbPort%" -U "%dbUser%" -d "%dbName%" --format=custom --file="%fileNameOut%"
if errorlevel 1 (
    echo ERROR: pg_dump failed.
    if exist "%fileNameOut%" del /q "%fileNameOut%"
    exit /b 1
)

echo Backup completed successfully.
echo File: %fileNameOut%
exit /b 0
