# AGAPAY for Windows: starts the database and the web server (runs at Windows sign-in, hidden).
# Also makes the daily backup if the last one is more than a day old (the laptop may have been off at noon).
. (Join-Path $PSScriptRoot 'common.ps1')

try {
    if (-not (Test-Installed)) { exit 0 }
    Write-Configs
    Start-Database
    Start-Web
    $last = Get-ChildItem $Backups -Filter 'AGAPAY-backup-*.zip' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $last -or $last.LastWriteTime -lt (Get-Date).AddDays(-1)) {
        & (Join-Path $PSScriptRoot 'backup.ps1') -Quiet
    }
} catch {
    Write-Log "Start failed: $($_.Exception.Message)"
    throw
}
