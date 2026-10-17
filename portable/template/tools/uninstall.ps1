# AGAPAY for Windows: removes AGAPAY from Windows (desktop shortcuts, start with Windows, scheduled backup and
# updater) and stops it. The AGAPAY folder itself, with the records and backups, is left alone: delete it yourself
# only after copying the backups somewhere safe.
. (Join-Path $PSScriptRoot 'common.ps1')

Write-Host ''
Write-Host '  AGAPAY - Uninstall from Windows' -ForegroundColor Cyan
Write-Host '  This removes the desktop shortcuts, the start with Windows and the scheduled tasks.'
Write-Host '  Your records and backups in this folder are NOT deleted.'
$answer = Read-Host '  Type YES to continue'
if ($answer -ne 'YES') { Write-Host '  Cancelled.'; exit 0 }

if (Test-Installed) {
    Write-Configs
    try { Stop-Web } catch { }
    try { Stop-Database } catch { }
}
$desktop = [Environment]::GetFolderPath('Desktop')
foreach ($name in 'AGAPAY.lnk', 'AGAPAY - Backup now.lnk', 'AGAPAY - Restore a backup.lnk', 'AGAPAY - Copy backups to USB.lnk') {
    Remove-Item (Join-Path $desktop $name) -ErrorAction SilentlyContinue
}
Remove-Item (Join-Path ([Environment]::GetFolderPath('Startup')) 'AGAPAY.lnk') -ErrorAction SilentlyContinue
foreach ($task in 'AGAPAY Backup', 'AGAPAY Update') {
    Unregister-ScheduledTask -TaskName $task -Confirm:$false -ErrorAction SilentlyContinue
}
Write-Log 'Removed from Windows (uninstall).'
Write-Host ''
Write-Host "  Done. AGAPAY was removed from Windows. The folder $AgapayHome is still here with your records and backups." -ForegroundColor Green
Read-Host '  Press Enter to close' | Out-Null
