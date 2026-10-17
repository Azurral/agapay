# AGAPAY for Windows: makes a backup in AGAPAY\backups (database + crisis photos, reports and uploads) and keeps
# the newest 30. Runs every day at noon, at start-up when the last backup is over a day old, and before updates.
param([string] $Reason = '', [switch] $Quiet)
. (Join-Path $PSScriptRoot 'common.ps1')

try {
    if (-not (Test-Installed)) { throw 'AGAPAY is not set up yet.' }
    Write-Configs
    Start-Database
    New-Item -ItemType Directory -Force -Path $Backups | Out-Null

    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'
    $suffix = if ($Reason) { "-$Reason" } else { '' }
    $work = Join-Path $Backups "_making-$stamp"
    New-Item -ItemType Directory -Force -Path $work | Out-Null

    # A consistent copy of the database while people keep working (--single-transaction).
    $root = (Get-AgapayEnv)['AGAPAY_DB_ROOT_PASSWORD']
    & (Join-Path $MariaBin 'mariadb-dump.exe') "--defaults-file=$Conf\my.ini" -u root "--password=$root" `
        --single-transaction --routines --triggers --default-character-set=utf8mb4 "--result-file=$work\agapay.sql" agapay
    if ($LASTEXITCODE -ne 0) { throw 'The database could not be copied.' }

    $files = Join-Path $Data 'storage\app\private'
    if (Test-Path $files) { Copy-Item $files (Join-Path $work 'files') -Recurse }
    Set-Content -Path (Join-Path $work 'backup-info.txt') -Value "AGAPAY v$(Get-Version)`r`nMade $(Get-Date -Format 'yyyy-MM-dd HH:mm')`r`nReason: $(if ($Reason) { $Reason } else { 'scheduled' })"

    $zip = Join-Path $Backups "AGAPAY-backup-$stamp$suffix.zip"
    Compress-Archive -Path (Join-Path $work '*') -DestinationPath $zip -Force
    Remove-Item $work -Recurse -Force

    # Keep the newest 30.
    Get-ChildItem $Backups -Filter 'AGAPAY-backup-*.zip' | Sort-Object LastWriteTime -Descending | Select-Object -Skip 30 | Remove-Item -Force
    Write-Log "Backup made: $(Split-Path $zip -Leaf)"
    if (-not $Quiet) { Show-Message "Backup made:`n$zip" }
    return $zip
} catch {
    Write-Log "Backup failed: $($_.Exception.Message)"
    if (-not $Quiet) { Show-Message "The backup failed: $($_.Exception.Message)" }
    throw
}
