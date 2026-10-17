# AGAPAY for Windows: run by AGAPAY-Setup.exe after it copies the files (no window).
#   First install:  creates the database and settings, then the Administrator's account from -AccountFile (a JSON
#                   file the installer writes; agapay:install deletes it as soon as it is read).
#   Reinstall:      the records are kept; only database changes for the new version are applied.
# Both then register the daily noon backup and the updater, and start AGAPAY.
param(
    [string] $AccountFile,
    [switch] $NoWindowsChanges   # tests: leave the scheduled tasks alone
)
. (Join-Path $PSScriptRoot 'common.ps1')

function New-Secret([int] $Bytes) {
    $buffer = New-Object byte[] $Bytes
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return ([Convert]::ToBase64String($buffer) -replace '[^A-Za-z0-9]', '')
}

New-Item -ItemType Directory -Force -Path $Logs | Out-Null
Start-Transcript -Path (Join-Path $Logs 'setup.log') -Append | Out-Null
try {
    foreach ($dir in 'mysql', 'conf', 'logs', 'updates', 'storage\app\private', 'storage\app\public', 'storage\framework\cache\data',
                     'storage\framework\sessions', 'storage\framework\views', 'storage\framework\tmp', 'storage\logs') {
        New-Item -ItemType Directory -Force -Path (Join-Path $Data $dir) | Out-Null
    }
    New-Item -ItemType Directory -Force -Path $Backups | Out-Null
    Write-Configs

    if (Test-Installed) {
        Write-Log "Reinstalling v$(Get-Version) over the existing records."
        Start-Database
        Invoke-Artisan @('migrate', '--force')
        Invoke-Artisan @('optimize:clear')
    } else {
        if (-not $AccountFile -or -not (Test-Path $AccountFile)) { throw 'The Administrator account details are missing.' }
        Write-Log "First setup of v$(Get-Version)."

        $rootPassword = New-Secret 24
        $appPassword = New-Secret 24
        & (Join-Path $MariaBin 'mariadb-install-db.exe') "--datadir=$Data\mysql" "--password=$rootPassword" "--port=$DbPort" | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'The database could not be created.' }

        $keyBytes = New-Object byte[] 32
        [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($keyBytes)
        $envText = (Get-Content (Join-Path $PSScriptRoot 'conf\agapay.env') -Raw) `
            -replace '\{\{APP_KEY\}\}', ('base64:' + [Convert]::ToBase64String($keyBytes)) `
            -replace '\{\{DB_PASSWORD\}\}', $appPassword `
            -replace '\{\{DB_ROOT_PASSWORD\}\}', $rootPassword
        [IO.File]::WriteAllText($EnvFile, $envText)

        try {
            Start-Database
            Invoke-Root @('-e', "CREATE DATABASE IF NOT EXISTS agapay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'agapay'@'127.0.0.1' IDENTIFIED BY '$appPassword'; CREATE USER IF NOT EXISTS 'agapay'@'localhost' IDENTIFIED BY '$appPassword'; GRANT ALL ON agapay.* TO 'agapay'@'127.0.0.1'; GRANT ALL ON agapay.* TO 'agapay'@'localhost'; FLUSH PRIVILEGES;")
            Invoke-Artisan @('agapay:install', "--from-file=$AccountFile", '--no-interaction')
        } catch {
            # Leave no half-made copy: running the installer again starts over.
            try { Stop-Database } catch { }
            Remove-Item $EnvFile -Force -ErrorAction SilentlyContinue
            Remove-Item (Join-Path $Data 'mysql') -Recurse -Force -ErrorAction SilentlyContinue
            throw
        }
    }

    Start-Database
    Start-Web

    if (-not $NoWindowsChanges) {
        # Daily backup at noon (or as soon as the laptop is on, if it was off at noon), and the on-demand updater.
        $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 1)
        $runner = Join-Path $PSScriptRoot 'run.vbs'
        $backupAction = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument "`"$runner`" backup.ps1 -Quiet"
        Register-ScheduledTask -TaskName 'AGAPAY Backup' -Action $backupAction -Trigger (New-ScheduledTaskTrigger -Daily -At 12:00) -Settings $settings -Force | Out-Null
        $updateAction = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument "`"$runner`" update.ps1"
        Register-ScheduledTask -TaskName 'AGAPAY Update' -Action $updateAction -Settings $settings -Force | Out-Null
    }

    $last = Get-ChildItem $Backups -Filter 'AGAPAY-backup-*.zip' -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $last) { & (Join-Path $PSScriptRoot 'backup.ps1') -Quiet -Reason 'first-setup' | Out-Null }
    Write-Log "AGAPAY v$(Get-Version) is ready at $Url."
    Stop-Transcript | Out-Null
    exit 0
} catch {
    Write-Log "Setup failed: $($_.Exception.Message)"
    if ($AccountFile) { Remove-Item $AccountFile -Force -ErrorAction SilentlyContinue }
    Stop-Transcript | Out-Null
    exit 1
}
