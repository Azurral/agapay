# AGAPAY for Windows: first-time setup (run once with "Setup AGAPAY.bat").
# Creates the database and settings, asks for the Administrator's account, then makes AGAPAY start with Windows and
# puts its shortcuts on the desktop. Running it again on an installed copy changes nothing.
param(
    [string] $AdminName, [string] $AdminUsername, [string] $AdminPassword,   # for unattended tests; normally asked
    [switch] $NoShortcuts                                                      # tests: leave Windows untouched
)
. (Join-Path $PSScriptRoot 'common.ps1')

if (Test-Installed) {
    Write-Host 'AGAPAY is already set up on this laptop. Use the AGAPAY shortcut on the desktop.'
    exit 0
}

function New-Secret([int] $Bytes) {
    $buffer = New-Object byte[] $Bytes
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return ([Convert]::ToBase64String($buffer) -replace '[^A-Za-z0-9]', '')
}

Write-Host ''
Write-Host '  AGAPAY for Windows - first-time setup' -ForegroundColor Cyan
Write-Host '  This takes about a minute.'
Write-Host ''

foreach ($dir in 'mysql', 'conf', 'logs', 'updates', 'storage\app\private', 'storage\app\public', 'storage\framework\cache\data',
                 'storage\framework\sessions', 'storage\framework\views', 'storage\framework\tmp', 'storage\logs') {
    New-Item -ItemType Directory -Force -Path (Join-Path $Data $dir) | Out-Null
}
New-Item -ItemType Directory -Force -Path $Backups | Out-Null
Write-Configs

Write-Host '  1/4  Creating the database...'
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

Start-Database
Invoke-Root @('-e', "CREATE DATABASE IF NOT EXISTS agapay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'agapay'@'127.0.0.1' IDENTIFIED BY '$appPassword'; CREATE USER IF NOT EXISTS 'agapay'@'localhost' IDENTIFIED BY '$appPassword'; GRANT ALL ON agapay.* TO 'agapay'@'127.0.0.1'; GRANT ALL ON agapay.* TO 'agapay'@'localhost'; FLUSH PRIVILEGES;")

Write-Host '  2/4  Setting up AGAPAY (tables, barangays, programs, crop values)...'
Write-Host ''
Write-Host '  Now create the Administrator account (the Municipal Agriculturist).' -ForegroundColor Cyan
try {
    if ($AdminUsername) {
        Invoke-Artisan @('agapay:install', "--name=$AdminName", "--username=$AdminUsername", "--password=$AdminPassword", '--no-interaction')
    } else {
        Invoke-Artisan @('agapay:install')
    }
} catch {
    # Leave no half-made copy: the next "Setup AGAPAY" starts over.
    Stop-Database
    Remove-Item $EnvFile -Force
    Remove-Item (Join-Path $Data 'mysql') -Recurse -Force
    throw
}

Write-Host '  3/4  Starting AGAPAY...'
Start-Web

if (-not $NoShortcuts) {
    Write-Host '  4/4  Adding the desktop shortcuts and starting AGAPAY with Windows...'
    $shell = New-Object -ComObject WScript.Shell
    $icon = Join-Path $AgapayHome 'app\public\favicon.ico'
    $powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    function New-Shortcut([string] $Path, [string] $Script, [string] $Description, [switch] $Hidden) {
        $link = $shell.CreateShortcut($Path)
        $link.TargetPath = $powershell
        $style = if ($Hidden) { '-WindowStyle Hidden ' } else { '' }
        $link.Arguments = "-NoProfile -ExecutionPolicy Bypass $style-File `"$PSScriptRoot\$Script`""
        $link.WorkingDirectory = $AgapayHome
        $link.IconLocation = $icon
        $link.Description = $Description
        $link.Save()
    }
    $desktop = [Environment]::GetFolderPath('Desktop')
    New-Shortcut (Join-Path $desktop 'AGAPAY.lnk') 'open.ps1' 'Open AGAPAY' -Hidden
    New-Shortcut (Join-Path $desktop 'AGAPAY - Backup now.lnk') 'backup.ps1' 'Make an AGAPAY backup now'
    New-Shortcut (Join-Path $desktop 'AGAPAY - Restore a backup.lnk') 'restore.ps1' 'Put back an AGAPAY backup'
    New-Shortcut (Join-Path $desktop 'AGAPAY - Copy backups to USB.lnk') 'copy-to-usb.ps1' 'Copy the AGAPAY backups to a USB drive'
    New-Shortcut (Join-Path ([Environment]::GetFolderPath('Startup')) 'AGAPAY.lnk') 'start.ps1' 'Start AGAPAY with Windows' -Hidden

    # Daily backup at noon (or as soon as the laptop is on, if it was off at noon), and the on-demand updater.
    $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 1)
    $backupAction = New-ScheduledTaskAction -Execute $powershell -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$PSScriptRoot\backup.ps1`" -Quiet"
    Register-ScheduledTask -TaskName 'AGAPAY Backup' -Action $backupAction -Trigger (New-ScheduledTaskTrigger -Daily -At 12:00) -Settings $settings -Force | Out-Null
    $updateAction = New-ScheduledTaskAction -Execute $powershell -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$PSScriptRoot\update.ps1`""
    Register-ScheduledTask -TaskName 'AGAPAY Update' -Action $updateAction -Settings $settings -Force | Out-Null
}

& (Join-Path $PSScriptRoot 'backup.ps1') -Quiet -Reason 'first-setup' | Out-Null
Write-Log "Set up AGAPAY v$(Get-Version)."
Write-Host ''
Write-Host "  Done. AGAPAY v$(Get-Version) is ready at $Url" -ForegroundColor Green
Write-Host '  From now on it starts by itself with Windows; open it with the AGAPAY icon on the desktop.'
if (-not $NoShortcuts) { Start-Process $Url }
