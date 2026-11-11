# AGAPAY for Windows: run by "Uninstall AGAPAY" before the program files are removed (no window). Stops AGAPAY and
# removes the daily backup and updater tasks. The installer itself removes the shortcuts and asks whether the records
# and backups are kept (they are, unless the person chooses to delete them).
. (Join-Path $PSScriptRoot 'common.ps1')

if (Test-Installed) {
    try { Write-Configs } catch { }
    try { Stop-Web } catch { }
    try { Stop-Database } catch { }
}
# An evaluation copy registered no tasks: the ones there belong to a real copy on the same computer.
$evaluation = (Test-Installed) -and ((Get-AgapayEnv)['AGAPAY_EVALUATION'] -eq 'true')
if (-not $evaluation) {
    foreach ($task in 'AGAPAY Backup', 'AGAPAY Update') {
        Unregister-ScheduledTask -TaskName $task -Confirm:$false -ErrorAction SilentlyContinue
    }
}
Write-Log 'Removed from Windows (uninstall).'
exit 0
