# Developing AGAPAY

How to run AGAPAY from the source code, build the Windows installer, publish updates and run the tests. To use AGAPAY at the office, see the [README](README.md) instead.

## Requirements

Install these once on the PC used to develop AGAPAY (the office laptop needs none of them; it uses the installer):

| Program | Version | What it is for |
|---|---|---|
| [PHP](https://windows.php.net/download/) | **8.5** (Thread Safe, x64 zip) | Runs AGAPAY |
| [XAMPP](https://www.apachefriends.org/) | any recent | Only for its **MySQL/MariaDB** database and **phpMyAdmin**. XAMPP's own PHP is older and is not used. |
| [Composer](https://getcomposer.org/download/) | 2.x | Downloads AGAPAY's PHP libraries |
| [Node.js](https://nodejs.org/) | 20 or newer (LTS) | Builds the CSS and JavaScript |

## Setup (first time)

Follow the steps in order. Each command goes in a terminal **opened in the AGAPAY folder** (step 4). Type one command, press Enter, and wait for it to finish before typing the next.

### 1. Install PHP 8.5

1. Download the PHP 8.5 **x64 Thread Safe** zip and unzip it to a folder, e.g. `C:\php`.
2. Add that folder to the Windows **Path**: Start → type *environment variables* → **Edit the system environment variables** → **Environment Variables…** → under *System variables* select **Path** → **Edit** → **New** → `C:\php` → OK on every window.
3. In `C:\php`, copy `php.ini-development` and rename the copy to `php.ini`.
4. Open `php.ini` in Notepad. Remove the `;` at the start of these lines so they are switched on:

```ini
extension_dir = "ext"
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
```

5. In the same file, find and change these three lines (they allow Excel uploads, damage photos and large PDFs):

```ini
upload_max_filesize = 25M
post_max_size = 60M
memory_limit = 512M
```

6. Save. Open a **new** terminal and check that it worked. It should print `PHP 8.5...`:

```bash
php -v
```

### 2. Install Composer and Node.js

Run both installers with the default options. When Composer asks for PHP, choose `C:\php\php.exe`. Open a **new** terminal and check both:

```bash
composer -V
```

```bash
node -v
```

### 3. Start the database and create `agapay`

1. Open the **XAMPP Control Panel** and press **Start** beside **MySQL**. It turns green. (Apache is not needed.)
2. Press **Admin** beside MySQL. phpMyAdmin opens in the browser.
3. Click **New** in the left panel, type `agapay` as the database name, and press **Create**. Leave it empty.

### 4. Open a terminal in the AGAPAY folder

Open the AGAPAY folder in File Explorer, click the address bar, type `cmd` and press Enter. A Command Prompt opens already inside the folder. Use this window for every command below.

### 5. Download the libraries

```bash
composer install
```

```bash
npm install
```

These take a few minutes the first time. Warnings are normal; only a red **error** means something went wrong.

### 6. Create the settings file

```bash
copy .env.example .env
```

```bash
php artisan key:generate
```

Open the new `.env` file in Notepad and check these lines. They already match a standard XAMPP install (user `root`, no password). Change `APP_URL` to the address used in step 8:

```ini
APP_URL=http://127.0.0.1:8765
DB_DATABASE=agapay
DB_USERNAME=root
DB_PASSWORD=
```

### 7. Create the tables and the starting data

```bash
php artisan migrate --seed
```

This creates every table and fills in the 16 barangays, the roles and accounts, and sample beneficiaries, interventions, inventory and damage records.

### 8. Build the screens and start AGAPAY

```bash
npm run build
```

```bash
php artisan serve --port=8765
```

Open **http://127.0.0.1:8765** in the browser and sign in with an account from the table below. **Keep this terminal open** while AGAPAY is in use; closing it stops the system.

## Starting AGAPAY again (every day)

1. XAMPP Control Panel → **Start** MySQL.
2. Open a terminal in the AGAPAY folder (step 4) and run:

```bash
php artisan serve --port=8765
```

3. Open http://127.0.0.1:8765.

`npm run build` is needed again only after the CSS or JavaScript files change.

## If something goes wrong

| What you see | What to do |
|---|---|
| `'php' is not recognized` | PHP is not on the Path. Redo step 1.2, then open a **new** terminal. |
| `could not find driver` or `SQLSTATE[HY000] [2002]` | `extension=pdo_mysql` is still switched off (step 1.4), or MySQL is not started in XAMPP. |
| `Unknown database 'agapay'` | Create the database (step 3). |
| `Failed to listen on 127.0.0.1:8765` | AGAPAY is already running in another terminal, or another program uses the port. Close the other terminal, or use `--port=8766` and open that address. |
| `Vite manifest not found` | Run `npm run build`. |
| "Page Expired" or you are signed out | Sessions end after 30 minutes without activity. Sign in again. |
| A download manager (e.g. IDM) grabs report downloads | Turn off its browser integration for `127.0.0.1`, or save the file it offers. |

### Backing up a development copy

Export the `agapay` database in phpMyAdmin and copy `storage\app\private` (damage photos and generated reports).

## Building the office installer

1. Put the official downloads in `portable-build\downloads\`: `php.zip` (PHP 8.5 Win32 vs17 x64, thread safe, windows.php.net), `httpd.zip` (Apache 2.4 Win64, apachelounge.com), `mariadb.zip` (MariaDB 11.8 winx64, mariadb.org) and `cacert.pem` (curl.se). Check their published checksums. Put Inno Setup 6 in `portable-build\inno\` (`ISCC.exe`).
2. Commit, then run `powershell -ExecutionPolicy Bypass -File portable\build.ps1`. It builds the committed version into `portable-build\`:
   - `AGAPAY-Setup-v<version>.exe`: the installer for the office laptop;
   - `AGAPAY-app-v<version>.zip`: the program only, for in-app updates.

The installer includes `handover\AGAPAY-User-Guide.pdf` when that file exists.

## Publishing an update

Bump the version, commit, build, push, then create a GitHub release tagged `v<version>` with these attached:

- `AGAPAY-app-v<version>.zip`: the in-app update. The office copy's Administrator then sees **Update available** above Dark Mode. The update backs up first, keeps `system\data`, applies database changes, and rolls back by itself if anything fails.
- `AGAPAY-Setup.exe` (a copy of `AGAPAY-Setup-v<version>.exe`) and `AGAPAY-User-Guide.pdf`: what the download page links to, under these exact names.

## The download page

`https://azurral.github.io/agapay/` is a one-page site in the `docs/` folder, published by GitHub Pages from `master` (repo **Settings → Pages → Deploy from a branch → master, /docs**). Its **Download for Windows** button links to `releases/latest/download/AGAPAY-Setup.exe`, so it always serves the newest release's installer and needs no change per release. To change the page, edit `docs/index.html` and push.

## Tests

| What | Command |
|---|---|
| Feature tests (Pest, in-memory SQLite) | `php artisan test --compact` |
| Visual comparison with all 46 Figma frames | `npm run visual` |
| End-to-end flows for each role, and the black-box test cases | `npm run e2e` |
| Rehearsal of the defense demo on a freshly installed copy | `npm run e2e:demo` |

`npm run visual` and `npm run e2e` use Microsoft Edge and their own database, `agapay_visual`. Create it once in phpMyAdmin. They never touch the `agapay` database. Run them one at a time.
