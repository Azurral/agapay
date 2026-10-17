# AGAPAY for Windows: stops the web server and closes the database properly (Windows shutdown does this too;
# the database also recovers by itself after a crash or power loss).
. (Join-Path $PSScriptRoot 'common.ps1')

if (-not (Test-Installed)) { exit 0 }
Write-Configs
Stop-Web
Stop-Database
Write-Host 'AGAPAY stopped.'
