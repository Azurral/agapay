# AGAPAY for Windows: the desktop "AGAPAY" icon. Starts AGAPAY if it is not running, then opens it in the browser.
. (Join-Path $PSScriptRoot 'common.ps1')

try {
    if (-not (Test-Installed)) {
        Show-Message 'AGAPAY is not set up yet. Run "Setup AGAPAY" in the AGAPAY folder first.'
        exit 1
    }
    if (-not (Test-Web)) { & (Join-Path $PSScriptRoot 'start.ps1') }
    Start-Process $Url
} catch {
    Show-Message "AGAPAY could not start: $($_.Exception.Message)`n`nRestart the laptop and try again. If it still fails, send the files in AGAPAY\data\logs to the developers."
}
