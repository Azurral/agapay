# AGAPAY for Windows: puts back a backup. The "Restore a backup" shortcut shows a window listing the backups; the
# current state is saved first (so a wrong choice can be undone), then the database and files are replaced and
# AGAPAY starts again. The updater calls it with -File -Yes -NoSafety to roll back.
param(
    [string] $File,        # the backup to restore (chosen in the window when not given)
    [switch] $Yes,         # no questions or windows (the updater rolling back)
    [switch] $NoSafety     # skip the safety backup (the updater already made one)
)
. (Join-Path $PSScriptRoot 'common.ps1')

$kinds = @{
    '' = 'Daily backup'; 'manual' = 'Backup now'; 'first-setup' = 'First setup'; 'before-update' = 'Before an update'
    'before-restore' = 'Before a restore'; 'before-upgrade' = 'Before reinstalling'
}

function Get-BackupKind([string] $Name) {
    $reason = if ($Name -match '^AGAPAY-backup-[0-9-]+_[0-9]+-?(.*)\.zip$') { $Matches[1] } else { '' }
    if ($kinds.ContainsKey($reason)) { return $kinds[$reason] }
    return $reason
}

# The window: newest backup first, already selected.
function Select-Backup {
    $list = @(Get-ChildItem $Backups -Filter 'AGAPAY-backup-*.zip' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending)
    if ($list.Count -eq 0) { throw "There are no backups yet in $Backups." }

    Add-Type -AssemblyName System.Windows.Forms, System.Drawing
    [Windows.Forms.Application]::EnableVisualStyles()
    $font = New-Object Drawing.Font('Segoe UI', 10)
    $form = New-Object Windows.Forms.Form -Property @{
        Text = 'AGAPAY - Restore a backup'; Width = 640; Height = 480; StartPosition = 'CenterScreen'
        FormBorderStyle = 'FixedDialog'; MaximizeBox = $false; MinimizeBox = $false; Font = $font
    }
    $icon = Join-Path $PSScriptRoot 'agapay.ico'
    if (Test-Path $icon) { $form.Icon = New-Object Drawing.Icon($icon) }

    $intro = New-Object Windows.Forms.Label -Property @{
        Left = 18; Top = 14; Width = 590; Height = 44
        Text = "Choose the backup to put back. Everything entered after it will be replaced.`nAGAPAY saves the current records first, so this can be undone."
    }
    $view = New-Object Windows.Forms.ListView -Property @{
        Left = 18; Top = 64; Width = 588; Height = 300; View = 'Details'; FullRowSelect = $true; MultiSelect = $false
        HideSelection = $false; GridLines = $true
    }
    [void]$view.Columns.Add('Date and time', 250)
    [void]$view.Columns.Add('Kind', 200)
    [void]$view.Columns.Add('Size', 110, 'Right')
    foreach ($backup in $list) {
        $item = New-Object Windows.Forms.ListViewItem($backup.LastWriteTime.ToString('ddd, MMM d, yyyy  h:mm tt'))
        [void]$item.SubItems.Add((Get-BackupKind $backup.Name))
        $size = if ($backup.Length -ge 1MB) { '{0:N1} MB' -f ($backup.Length / 1MB) } else { '{0:N0} KB' -f [Math]::Max(1, $backup.Length / 1KB) }
        [void]$item.SubItems.Add($size)
        $item.Tag = $backup.FullName
        [void]$view.Items.Add($item)
    }
    $view.Items[0].Selected = $true

    $restore = New-Object Windows.Forms.Button -Property @{ Text = 'Restore'; Left = 396; Top = 380; Width = 100; Height = 34; DialogResult = 'OK' }
    $cancel = New-Object Windows.Forms.Button -Property @{ Text = 'Cancel'; Left = 506; Top = 380; Width = 100; Height = 34; DialogResult = 'Cancel' }
    $open = New-Object Windows.Forms.LinkLabel -Property @{ Text = 'Open the AGAPAY Backups folder'; Left = 18; Top = 388; Width = 300; Height = 24 }
    $open.add_LinkClicked({ Start-Process explorer.exe $Backups })
    $view.add_SelectedIndexChanged({ $restore.Enabled = $view.SelectedItems.Count -eq 1 })
    $view.add_DoubleClick({ $form.DialogResult = 'OK'; $form.Close() })
    $form.AcceptButton = $restore
    $form.CancelButton = $cancel
    $form.Controls.AddRange(@($intro, $view, $restore, $cancel, $open))

    if ($form.ShowDialog() -ne 'OK' -or $view.SelectedItems.Count -ne 1) { return $null }
    $chosen = $view.SelectedItems[0]
    $question = "Put back the backup from $($chosen.Text)?`n`nRecords entered after it will be replaced. The current records are saved first."
    if ((Show-Message $question 'AGAPAY - Restore a backup' 'YesNo' 'Warning') -ne 'Yes') { return $null }
    return $chosen.Tag
}

$busy = $null
$work = Join-Path $Data 'restoring'
try {
    if (-not (Test-Installed)) { throw 'AGAPAY is not set up yet.' }
    if (-not $File) {
        $File = Select-Backup
        if (-not $File) { exit 0 }
    }
    if (-not (Test-Path $File)) { throw "Backup not found: $File" }
    if (-not $Yes) { $busy = Show-Busy 'Restoring the backup. Please wait...' }

    Write-Configs
    if (-not $NoSafety) {
        & (Join-Path $PSScriptRoot 'backup.ps1') -Quiet -Reason 'before-restore' | Out-Null
    }

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
    if ($busy) { $busy.Close(); [void](Show-Message "Done. AGAPAY is back to the backup from $((Get-Item $File).LastWriteTime.ToString('ddd, MMM d, yyyy h:mm tt'))." 'AGAPAY - Restore a backup') }
} catch {
    Write-Log "Restore failed: $($_.Exception.Message)"
    try { Start-Web } catch { }
    if ($busy) { $busy.Close() }
    if (-not $Yes) { [void](Show-Message "The restore failed: $($_.Exception.Message)`n`nThe records from just before are saved in the list as `"Before a restore`"; restore that one if AGAPAY looks wrong." 'AGAPAY - Restore a backup' 'OK' 'Error') }
    throw
}
