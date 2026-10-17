# AGAPAY for Windows: puts back a backup. Lists the backups, asks which one, saves the current state first (so a wrong
# choice can be undone), then replaces the database and files and starts AGAPAY again.
param(
    [string] $File,        # the backup to restore (asked when not given)
    [switch] $Yes,         # do not ask for confirmation (used by the updater when it rolls back)
    [switch] $NoSafety     # skip the safety backup (the updater already made one)
)
. (Join-Path $PSScriptRoot 'common.ps1')

try {
    if (-not (Test-Installed)) { throw 'AGAPAY is not set up yet.' }

    if (-not $File) {
        $list = @(Get-ChildItem $Backups -Filter 'AGAPAY-backup-*.zip' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending)
        if ($list.Count -eq 0) { throw 'There are no backups in the AGAPAY\backups folder.' }
        Write-Host ''
        Write-Host '  AGAPAY - Restore a backup' -ForegroundColor Cyan
        Write-Host '  Everything entered after the chosen backup will be replaced by it.'
        Write-Host ''
        for ($i = 0; $i -lt $list.Count; $i++) {
            Write-Host ("  {0,2}.  {1:ddd, MMM d, yyyy  h:mm tt}   {2,8:N1} MB   {3}" -f ($i + 1), $list[$i].LastWriteTime, ($list[$i].Length / 1MB), $list[$i].Name)
        }
        Write-Host ''
        $choice = Read-Host '  Type the number of the backup to restore (or press Enter to cancel)'
        if (-not $choice) { Write-Host '  Cancelled. Nothing was changed.'; exit 0 }
        $index = 0
        if (-not [int]::TryParse($choice, [ref] $index) -or $index -lt 1 -or $index -gt $list.Count) { throw 'That number is not on the list.' }
        $File = $list[$index - 1].FullName
    }
    if (-not (Test-Path $File)) { throw "Backup not found: $File" }

    if (-not $Yes) {
        $answer = Read-Host "  Restore $(Split-Path $File -Leaf)? Type YES to continue"
        if ($answer -ne 'YES') { Write-Host '  Cancelled. Nothing was changed.'; exit 0 }
    }

    Write-Configs
    if (-not $NoSafety) {
        Write-Host '  Saving the current state first...'
        & (Join-Path $PSScriptRoot 'backup.ps1') -Quiet -Reason 'before-restore' | Out-Null
    }

    Write-Host '  Restoring...'
    $work = Join-Path $Backups '_restoring'
    if (Test-Path $work) { Remove-Item $work -Recurse -Force }
    Expand-Archive -Path $File -DestinationPath $work
    $sql = Join-Path $work 'agapay.sql'
    if (-not (Test-Path $sql)) { throw 'This file is not an AGAPAY backup.' }

    Stop-Web
    Start-Database
    Invoke-Root @('-e', 'DROP DATABASE IF EXISTS agapay; CREATE DATABASE agapay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    Invoke-Root @('agapay', '-e', "source $($sql -replace '\\', '/')")

    $files = Join-Path $Data 'storage\app\private'
    if (Test-Path $files) { Remove-Item $files -Recurse -Force }
    if (Test-Path (Join-Path $work 'files')) { Copy-Item (Join-Path $work 'files') $files -Recurse } else { New-Item -ItemType Directory -Force -Path $files | Out-Null }
    Remove-Item $work -Recurse -Force

    Start-Web
    Write-Log "Restored $(Split-Path $File -Leaf)."
    Write-Host "  Done. AGAPAY is back to $(Split-Path $File -Leaf)." -ForegroundColor Green
    if (-not $Yes) { Read-Host '  Press Enter to close' | Out-Null }
} catch {
    Write-Log "Restore failed: $($_.Exception.Message)"
    Write-Host "  The restore failed: $($_.Exception.Message)" -ForegroundColor Red
    try { Start-Web } catch { }
    if (-not $Yes) { Read-Host '  Press Enter to close' | Out-Null }
    throw
}
