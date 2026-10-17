# AGAPAY for Windows: copies the backups to a USB drive or external disk (recommended once a week), so the records
# survive even if the laptop is lost, stolen or broken.
. (Join-Path $PSScriptRoot 'common.ps1')

try {
    $drives = @(Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=2' | Where-Object { $_.FreeSpace -gt 0 })
    if ($drives.Count -eq 0) {
        Show-Message 'No USB drive found. Plug in a USB drive or external disk, then click this shortcut again.'
        exit 0
    }
    $drive = $drives[0].DeviceID
    $target = Join-Path "$drive\" 'AGAPAY Backups'
    New-Item -ItemType Directory -Force -Path $target | Out-Null

    $copied = 0
    foreach ($zip in Get-ChildItem $Backups -Filter 'AGAPAY-backup-*.zip') {
        $dest = Join-Path $target $zip.Name
        if (-not (Test-Path $dest)) { Copy-Item $zip.FullName $dest; $copied++ }
    }
    Write-Log "Copied $copied backups to $target."
    Show-Message "Copied $copied new backup(s) to $target.`n`nYou can now remove the USB drive safely."
} catch {
    Write-Log "Copy to USB failed: $($_.Exception.Message)"
    Show-Message "The copy failed: $($_.Exception.Message)"
}
