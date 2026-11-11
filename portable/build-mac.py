"""Builds AGAPAY for Mac, the panel's copy: portable-build/AGAPAY-Mac.zip holding AGAPAY.app.

    python portable/build-mac.py

AGAPAY.app contains the committed program with its production packages, the built pages, a ready SQLite database with
the sample records and test accounts of the black-box test cases (made-up people only), and PHP itself: the static
macOS builds of static-php-cli ("common" set; AGAPAY runs without intl and sodium) for Apple Silicon and Intel, from
portable-build/downloads/mac/php-<PHP_VERSION>-cli-macos-{aarch64,x86_64}.tar.gz. Opening the app runs
portable/mac/launcher.sh, which serves AGAPAY on http://127.0.0.1:8742. Nothing has to be installed on the Mac.
"""
import base64
import os
import re
import secrets
import shutil
import subprocess
import tarfile
import zipfile
from pathlib import Path

from PIL import Image

REPO = Path(__file__).resolve().parent.parent
BUILD = REPO / 'portable-build'
VERSION = re.search(r"'version'\s*=>\s*'([0-9.]+)'", (REPO / 'config' / 'agapay.php').read_text(encoding='utf-8')).group(1)
PHP_VERSION = '8.5.8'
DOWNLOADS = BUILD / 'downloads' / 'mac'
STAGE = BUILD / 'mac-staging'
BUNDLE = STAGE / 'AGAPAY.app'
CONTENTS = BUNDLE / 'Contents'
RESOURCES = CONTENTS / 'Resources'
APP = RESOURCES / 'agapay'
OUT = BUILD / 'AGAPAY-Mac.zip'

# Same limits as the Windows copy's php.ini (photos, 25 MB masterlists, PDF reports).
PHP_FLAGS = ('-d memory_limit=512M -d upload_max_filesize=25M -d post_max_size=30M -d max_file_uploads=20 '
             '-d max_execution_time=300 -d max_input_time=300 -d date.timezone=Asia/Manila')

ENV = f"""APP_NAME=AGAPAY
APP_ENV=production
APP_KEY=base64:{base64.b64encode(secrets.token_bytes(32)).decode()}
APP_DEBUG=false
APP_URL=http://127.0.0.1:8742
APP_TIMEZONE=Asia/Manila
APP_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=warning

# database/database.sqlite: the sample records
DB_CONNECTION=sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=30
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

AGAPAY_UPDATES=false
# The panel's copy: sample records only.
AGAPAY_EVALUATION=true
"""

INFO_PLIST = f"""<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleName</key><string>AGAPAY</string>
    <key>CFBundleDisplayName</key><string>AGAPAY</string>
    <key>CFBundleIdentifier</key><string>app.agapay.panel</string>
    <key>CFBundleExecutable</key><string>AGAPAY</string>
    <key>CFBundleIconFile</key><string>AppIcon</string>
    <key>CFBundlePackageType</key><string>APPL</string>
    <key>CFBundleShortVersionString</key><string>{VERSION}</string>
    <key>CFBundleVersion</key><string>{VERSION}</string>
    <key>LSMinimumSystemVersion</key><string>11.0</string>
    <key>NSHighResolutionCapable</key><true/>
</dict>
</plist>
"""

README = """AGAPAY FOR MAC
==============
Sample records only (made-up farmers, programs, stock and crisis reports). Nothing to install, no internet needed.
Apple Silicon (M1 and newer) and Intel Macs, macOS 11 or newer.

1. Double-click AGAPAY (you may first drag it into Applications). AGAPAY opens in the browser.
   The first time only, macOS blocks it because it is not from the App Store:
     - Click "Done" on the message.
     - Open System Settings > Privacy & Security, scroll down, click "Open Anyway" next to AGAPAY, then "Open".
   (Older macOS: right-click AGAPAY > Open > Open.)

2. Sign in with a test account (also shown on the sign-in page). Password for all: Agapay@2026
      Administrator ................. Admin_01
      Agricultural Technologist ..... Agritech_02
      Data Encoder .................. Encoder_03
      Inactive account (test BB-04) . Encoder_04

3. Double-click AGAPAY again while it runs to open it, stop it, or start over with fresh sample records.
"""


def run(args, cwd):
    subprocess.run(args, cwd=cwd, check=True, shell=os.name == 'nt')


if STAGE.exists():
    shutil.rmtree(STAGE)
APP.mkdir(parents=True)
(CONTENTS / 'MacOS').mkdir()

print('  program...')
archive = STAGE / 'app.zip'
run(['git', 'archive', '--format=zip', '-o', str(archive), 'HEAD'], REPO)
with zipfile.ZipFile(archive) as z:
    z.extractall(APP)
archive.unlink()
for path in ['tests', 'portable', 'docs', '.github', 'phpunit.xml', '.env.example', 'README.md', 'DEVELOPMENT.md', 'package.json', 'package-lock.json']:
    target = APP / path
    if target.is_dir():
        shutil.rmtree(target)
    elif target.exists():
        target.unlink()
run(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress', '--quiet'], APP)
shutil.copytree(REPO / 'public' / 'build', APP / 'public' / 'build')
(APP / '.env').write_text(ENV, encoding='utf-8', newline='\n')

print('  sample records...')
(APP / 'database' / 'database.sqlite').touch()
run(['php', 'artisan', 'agapay:install', '--sample-data', '--no-interaction'], APP)
run(['php', 'artisan', 'optimize:clear'], APP)   # nothing cached with this computer's paths
for log in (APP / 'storage' / 'logs').glob('*.log'):
    log.unlink()

print('  PHP for Mac...')
for arch, name in [('aarch64', 'php-arm64'), ('x86_64', 'php-intel')]:
    with tarfile.open(DOWNLOADS / f'php-{PHP_VERSION}-cli-macos-{arch}.tar.gz') as tar:
        (RESOURCES / name).write_bytes(tar.extractfile(tar.getmember('php')).read())

print('  app bundle...')
launcher = (REPO / 'portable' / 'mac' / 'launcher.sh').read_text(encoding='utf-8')
launcher = launcher.replace('__VERSION__', VERSION).replace('__PHP_FLAGS__', PHP_FLAGS)
(CONTENTS / 'MacOS' / 'AGAPAY').write_text(launcher, encoding='utf-8', newline='\n')
(CONTENTS / 'Info.plist').write_text(INFO_PLIST, encoding='utf-8', newline='\n')
(CONTENTS / 'PkgInfo').write_text('APPL????', encoding='ascii')
icon = Image.open(REPO / 'portable' / 'mac' / 'AppIcon.png')
icon.save(RESOURCES / 'AppIcon.icns', sizes=[(16, 16), (32, 32), (64, 64), (128, 128), (256, 256), (512, 512), (1024, 1024)])
(STAGE / 'READ ME FIRST.txt').write_text(README, encoding='utf-8', newline='\n')

OUT.unlink(missing_ok=True)
EXECUTABLE = {'AGAPAY.app/Contents/MacOS/AGAPAY', 'AGAPAY.app/Contents/Resources/php-arm64', 'AGAPAY.app/Contents/Resources/php-intel'}
with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as z:
    for file in sorted(STAGE.rglob('*')):
        rel = file.relative_to(STAGE).as_posix()
        if file.is_dir():
            info = zipfile.ZipInfo(rel + '/')
            info.external_attr = (0o40755 << 16) | 0x10
            info.create_system = 3   # Unix: otherwise macOS ignores the permissions (and the app cannot start)
            z.writestr(info, '')
            continue
        info = zipfile.ZipInfo.from_file(file, rel)
        info.external_attr = (0o100755 if rel in EXECUTABLE else 0o100644) << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        info.create_system = 3
        z.writestr(info, file.read_bytes())
print(f'Done: {OUT.name} ({OUT.stat().st_size / 1e6:.0f} MB)')
