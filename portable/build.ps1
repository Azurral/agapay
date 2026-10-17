# Builds "AGAPAY for Windows": the installer for the office laptop and the update package for a GitHub release.
#   powershell -ExecutionPolicy Bypass -File portable\build.ps1
# Needs, in portable-build\downloads: php.zip (PHP 8.5 Win32 vs17 x64, thread safe), httpd.zip (Apache Lounge
# Win64), mariadb.zip (MariaDB winx64), cacert.pem (curl.se); and Inno Setup 6 in portable-build\inno (ISCC.exe).
# Produces in portable-build:
#   AGAPAY\system\                       what the installer puts in C:\AGAPAY\system
#   AGAPAY-Setup-v<version>.exe          the installer (send this to the office)
#   AGAPAY-app-v<version>.zip           the program folder only: attach it to the GitHub release for in-app updates
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$repo = Split-Path -Parent $PSScriptRoot
$build = Join-Path $repo 'portable-build'
$downloads = Join-Path $build 'downloads'
$folder = Join-Path $build 'AGAPAY'
$out = Join-Path $folder 'system'
$staging = Join-Path $build 'staging'

$config = Get-Content (Join-Path $repo 'config\agapay.php') -Raw
if ($config -notmatch "'version'\s*=>\s*'([0-9.]+)'") { throw 'Version not found in config\agapay.php.' }
$version = $Matches[1]
Write-Host "Building AGAPAY for Windows v$version"

foreach ($dir in $folder, $staging) { if (Test-Path $dir) { Remove-Item $dir -Recurse -Force } }
New-Item -ItemType Directory -Force -Path $out, $staging | Out-Null

function Expand-Into([string] $ZipName, [string] $Inner, [string] $Target) {
    $tmp = Join-Path $staging ([IO.Path]::GetFileNameWithoutExtension($ZipName))
    Expand-Archive -Path (Join-Path $downloads $ZipName) -DestinationPath $tmp
    $source = if ($Inner) { (Get-ChildItem $tmp -Directory -Recurse -Filter $Inner | Select-Object -First 1).FullName } else { $tmp }
    Move-Item $source $Target
}

Write-Host '  PHP, Apache and MariaDB...'
Expand-Into 'php.zip' '' (Join-Path $out 'php')
Expand-Into 'httpd.zip' 'Apache24' (Join-Path $out 'apache')
Expand-Into 'mariadb.zip' 'mariadb-*-winx64' (Join-Path $out 'mariadb')

# Trim what an office copy never uses (manuals, headers, debug symbols, test suites).
foreach ($path in 'apache\manual', 'apache\htdocs', 'apache\cgi-bin', 'apache\include', 'apache\lib', 'mariadb\include', 'mariadb\lib\*.lib', 'mariadb\mysql-test', 'mariadb\sql-bench', 'php\dev') {
    Remove-Item (Join-Path $out $path) -Recurse -Force -ErrorAction SilentlyContinue
}
Get-ChildItem $out -Recurse -Filter '*.pdb' | Remove-Item -Force

# The Microsoft Visual C++ runtime next to PHP and Apache (app-local, as Microsoft allows), so nothing has to be
# installed on the office laptop. MariaDB ships its own copy.
$runtime = 'vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll', 'msvcp140_1.dll', 'msvcp140_2.dll', 'msvcp140_atomic_wait.dll', 'msvcp140_codecvt_ids.dll', 'concrt140.dll'
foreach ($dll in $runtime) {
    $src = Join-Path $env:SystemRoot "System32\$dll"
    foreach ($dest in (Join-Path $out 'php'), (Join-Path $out 'apache\bin')) { Copy-Item $src $dest -Force }
}
New-Item -ItemType Directory -Force -Path (Join-Path $out 'php\extras\ssl') | Out-Null
Copy-Item (Join-Path $downloads 'cacert.pem') (Join-Path $out 'php\extras\ssl\cacert.pem')

Write-Host '  AGAPAY program (committed version, production packages only)...'
$app = Join-Path $out 'app'
$archive = Join-Path $staging 'app.zip'
git -C $repo archive --format=zip -o $archive HEAD
if ($LASTEXITCODE -ne 0) { throw 'git archive failed' }
Expand-Archive $archive $app
foreach ($path in 'tests', 'portable', '.github', 'phpunit.xml', '.env.example', 'backup.bat', 'README.md') {
    Remove-Item (Join-Path $app $path) -Recurse -Force -ErrorAction SilentlyContinue
}
Push-Location $app
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'composer install failed' }
Pop-Location
Copy-Item (Join-Path $repo 'public\build') (Join-Path $app 'public\build') -Recurse -Force
Remove-Item (Join-Path $app 'package.json'), (Join-Path $app 'package-lock.json') -ErrorAction SilentlyContinue

Write-Host '  Tools...'
Copy-Item (Join-Path $PSScriptRoot 'template\*') $out -Recurse -Force
Set-Content (Join-Path $out 'VERSION.txt') "AGAPAY for Windows v$version"

Write-Host '  Update package...'
$appZip = Join-Path $build "AGAPAY-app-v$version.zip"
Remove-Item $appZip -ErrorAction SilentlyContinue
Compress-Archive -Path $app -DestinationPath $appZip
Remove-Item $staging -Recurse -Force

Write-Host '  Installer...'
if (-not (Test-Path (Join-Path $repo 'handover\AGAPAY-User-Guide.pdf'))) { Write-Warning 'handover\AGAPAY-User-Guide.pdf not found: the installer will have no user guide.' }
& (Join-Path $build 'inno\ISCC.exe') /Q "/DAppVersion=$version" (Join-Path $PSScriptRoot 'installer\agapay.iss')
if ($LASTEXITCODE -ne 0) { throw 'The installer could not be compiled.' }
$setup = Join-Path $build "AGAPAY-Setup-v$version.exe"

Write-Host ("Done: {0} ({1:N0} MB), {2} ({3:N0} MB)" -f (Split-Path $setup -Leaf), ((Get-Item $setup).Length / 1MB), (Split-Path $appZip -Leaf), ((Get-Item $appZip).Length / 1MB))
