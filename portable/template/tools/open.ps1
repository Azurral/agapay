# AGAPAY for Windows: the "AGAPAY" shortcut (desktop and C:\AGAPAY). Starts AGAPAY if it is not running, then opens it in the browser.
. (Join-Path $PSScriptRoot 'common.ps1')

try {
    if (-not (Test-Installed)) {
        [void](Show-Message 'AGAPAY is not set up yet. Run AGAPAY-Setup.exe again.' 'AGAPAY' 'OK' 'Warning')
        exit 1
    }
    if (-not (Test-Web)) {
        $busy = Show-Busy 'Starting AGAPAY...'
        try { & (Join-Path $PSScriptRoot 'start.ps1') } finally { $busy.Close() }
    }
    Start-Process $Url
} catch {
    [void](Show-Message "AGAPAY could not start: $($_.Exception.Message)`n`nRestart the laptop and try again. If it still fails, send the files in $Logs to the developers." 'AGAPAY' 'OK' 'Error')
}
