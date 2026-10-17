# AGAPAY for Windows: installs a new version (started by the Administrator's "Update" button, through the
# "AGAPAY Update" scheduled task). Backs up first, swaps in the new program folder, applies database changes and
# starts AGAPAY again. If anything fails, it puts the previous version and the backup back.
param([string] $Zip)   # tests: a local package instead of the download named in data\updates\pending.json
. (Join-Path $PSScriptRoot 'common.ps1')

$updates = Join-Path $Data 'updates'
$pending = Join-Path $updates 'pending.json'
$app = Join-Path $AgapayHome 'app'
$previous = Join-Path $AgapayHome 'app-previous'
$incoming = Join-Path $updates 'incoming'

function Save-Result([string] $Status, [string] $Message) {
    @{ status = $Status; message = $Message; version = (Get-Version); at = (Get-Date -Format 'yyyy-MM-dd HH:mm') } |
        ConvertTo-Json | Set-Content (Join-Path $updates 'result.json')
    Write-Log "Update $Status`: $Message"
}

$backup = $null
$swapped = $false
try {
    if (-not $Zip) {
        if (-not (Test-Path $pending)) { exit 0 }
        $request = Get-Content $pending -Raw | ConvertFrom-Json
        if ($request.url -notmatch '^https://github\.com/[^/]+/[^/]+/releases/download/') { throw 'Unexpected download address.' }
        $Zip = Join-Path $updates "AGAPAY-app-v$($request.version).zip"
        Write-Log "Downloading v$($request.version)..."
        Invoke-WebRequest -Uri $request.url -OutFile $Zip -UseBasicParsing
    }

    if (Test-Path $incoming) { Remove-Item $incoming -Recurse -Force }
    Expand-Archive -Path $Zip -DestinationPath $incoming
    $newApp = if (Test-Path (Join-Path $incoming 'app\artisan')) { Join-Path $incoming 'app' } else { $incoming }
    if (-not (Test-Path (Join-Path $newApp 'artisan')) -or -not (Test-Path (Join-Path $newApp 'public\index.php'))) {
        throw 'The downloaded package is not an AGAPAY program folder.'
    }

    Write-Configs
    $backup = @(& (Join-Path $PSScriptRoot 'backup.ps1') -Quiet -Reason "before-update")[-1]

    Stop-Web
    if (Test-Path $previous) { Remove-Item $previous -Recurse -Force }
    Rename-Item $app $previous
    Move-Item $newApp $app
    $swapped = $true

    Invoke-Artisan @('migrate', '--force')
    Start-Web
    Remove-Item $incoming -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $pending -Force -ErrorAction SilentlyContinue
    Save-Result 'done' "Updated to v$(Get-Version)."
} catch {
    $reason = $_.Exception.Message
    if ($swapped) {
        # Put the previous version back, with the data as it was before the update.
        try { Stop-Web } catch { }
        if (Test-Path $app) { Remove-Item $app -Recurse -Force }
        Rename-Item $previous $app
        if ($backup) { & (Join-Path $PSScriptRoot 'restore.ps1') -File $backup -Yes -NoSafety }
    }
    try { Start-Web } catch { }
    Remove-Item $pending -Force -ErrorAction SilentlyContinue
    Save-Result 'failed' "$reason (AGAPAY was kept at v$(Get-Version))"
    exit 1
}
