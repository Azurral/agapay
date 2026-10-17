# AGAPAY for Windows: shared paths and helpers for the start, stop, backup, restore and update tools.
# Windows PowerShell 5.1 (built into Windows 10/11).
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

# C:\AGAPAY holds the shortcuts and the "AGAPAY Backups" folder; the program itself is in the hidden C:\AGAPAY\system.
$AgapayHome = Split-Path -Parent $PSScriptRoot
$AgapayFolder = Split-Path -Parent $AgapayHome
$Data = Join-Path $AgapayHome 'data'
$Backups = Join-Path $AgapayFolder 'AGAPAY Backups'
$Logs = Join-Path $Data 'logs'
$Conf = Join-Path $Data 'conf'
$EnvFile = Join-Path $Data 'agapay.env'
$Php = Join-Path $AgapayHome 'php\php.exe'
$Httpd = Join-Path $AgapayHome 'apache\bin\httpd.exe'
$MariaBin = Join-Path $AgapayHome 'mariadb\bin'
$WebPort = 8742
$DbPort = 3307
$Url = "http://localhost:$WebPort"

function Write-Log([string] $Message) {
    New-Item -ItemType Directory -Force -Path $Logs | Out-Null
    Add-Content -Path (Join-Path $Logs 'agapay-tools.log') -Value ("[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message)
}

function Test-Installed { Test-Path $EnvFile }

function Get-AgapayEnv {
    $values = @{}
    foreach ($line in Get-Content $EnvFile) {
        if ($line -match '^\s*([A-Z0-9_]+)=(.*)$') { $values[$Matches[1]] = $Matches[2] }
    }
    return $values
}

function Get-Version {
    $config = Get-Content (Join-Path $AgapayHome 'app\config\agapay.php') -Raw
    if ($config -match "'version'\s*=>\s*'([0-9.]+)'") { return $Matches[1] }
    return '?'
}

# The settings files hold the folder's own path, so they are written again on every start (the folder may move).
function Write-Configs {
    New-Item -ItemType Directory -Force -Path $Conf, $Logs | Out-Null
    $homeSlash = $AgapayHome -replace '\\', '/'
    foreach ($name in 'httpd.conf', 'php.ini', 'my.ini') {
        $text = (Get-Content (Join-Path $PSScriptRoot "conf\$name") -Raw) -replace '\{\{HOME\}\}', $homeSlash
        [IO.File]::WriteAllText((Join-Path $Conf $name), $text)
    }
}

function Test-Port([int] $Port) {
    $client = New-Object Net.Sockets.TcpClient
    try { $client.Connect('127.0.0.1', $Port); return $true } catch { return $false } finally { $client.Dispose() }
}

function Wait-Until([scriptblock] $Condition, [int] $Seconds, [string] $What) {
    $deadline = (Get-Date).AddSeconds($Seconds)
    while ((Get-Date) -lt $deadline) {
        if (& $Condition) { return }
        Start-Sleep -Milliseconds 500
    }
    throw "$What did not start in time. See $Logs."
}

function Start-Database {
    if (Test-Port $DbPort) { return }
    Start-Process -FilePath (Join-Path $MariaBin 'mariadbd.exe') -ArgumentList "--defaults-file=`"$Conf\my.ini`"" -WindowStyle Hidden
    Wait-Until { Test-Port $DbPort } 60 'The database'
    Write-Log 'Database started.'
}

function Invoke-Root([string[]] $Arguments) {
    $root = (Get-AgapayEnv)['AGAPAY_DB_ROOT_PASSWORD']
    & (Join-Path $MariaBin 'mariadb.exe') "--defaults-file=$Conf\my.ini" -u root "--password=$root" @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Database command failed: $($Arguments -join ' ')" }
}

function Stop-Database {
    if (-not (Test-Port $DbPort)) { return }
    $root = (Get-AgapayEnv)['AGAPAY_DB_ROOT_PASSWORD']
    & (Join-Path $MariaBin 'mariadb-admin.exe') "--defaults-file=$Conf\my.ini" -u root "--password=$root" shutdown
    Wait-Until { -not (Test-Port $DbPort) } 60 'The database shutdown'
    Write-Log 'Database stopped.'
}

function Test-Web {
    try { return (Invoke-WebRequest -Uri "$Url/up" -UseBasicParsing -TimeoutSec 5).StatusCode -eq 200 } catch { return $false }
}

function Start-Web {
    if (Test-Port $WebPort) { return }
    Start-Process -FilePath $Httpd -ArgumentList '-f', "`"$Conf\httpd.conf`"" -WindowStyle Hidden
    Wait-Until { Test-Web } 60 'AGAPAY'
    Write-Log 'Web server started.'
}

function Stop-Web {
    Get-Process httpd -ErrorAction SilentlyContinue | Where-Object { $_.Path -like "$AgapayHome*" } | Stop-Process -Force
    Wait-Until { -not (Test-Port $WebPort) } 30 'The web server shutdown'
    Write-Log 'Web server stopped.'
}

# Runs an artisan command with the office copy's settings (data\agapay.env) and storage (data\storage).
function Invoke-Artisan([string[]] $Arguments) {
    $env:AGAPAY_HOME = $AgapayHome
    $env:LARAVEL_STORAGE_PATH = Join-Path $Data 'storage'
    & $Php -c "$Conf\php.ini" (Join-Path $AgapayHome 'app\artisan') @Arguments
    if ($LASTEXITCODE -ne 0) { throw "AGAPAY command failed: artisan $($Arguments -join ' ')" }
}

function Show-Message([string] $Text, [string] $Title = 'AGAPAY', [string] $Buttons = 'OK', [string] $Icon = 'Information') {
    Add-Type -AssemblyName System.Windows.Forms
    return [Windows.Forms.MessageBox]::Show($Text, $Title, $Buttons, $Icon)
}

# A small "please wait" window for the Backup and Restore shortcuts (they run without a console).
function Show-Busy([string] $Text) {
    Add-Type -AssemblyName System.Windows.Forms, System.Drawing
    [Windows.Forms.Application]::EnableVisualStyles()
    $form = New-Object Windows.Forms.Form -Property @{
        Text = 'AGAPAY'; Width = 380; Height = 140; FormBorderStyle = 'FixedDialog'; ControlBox = $false
        StartPosition = 'CenterScreen'; TopMost = $true; Font = New-Object Drawing.Font('Segoe UI', 10)
    }
    $icon = Join-Path $PSScriptRoot 'agapay.ico'
    if (Test-Path $icon) { $form.Icon = New-Object Drawing.Icon($icon) }
    $label = New-Object Windows.Forms.Label -Property @{ Text = $Text; AutoSize = $false; Left = 20; Top = 18; Width = 330; Height = 26 }
    $bar = New-Object Windows.Forms.ProgressBar -Property @{ Style = 'Marquee'; Left = 20; Top = 52; Width = 325; Height = 18; MarqueeAnimationSpeed = 30 }
    $form.Controls.AddRange(@($label, $bar))
    $form.Show()
    [Windows.Forms.Application]::DoEvents()
    return $form
}
