# AGAPAY

**A Centralized Agricultural Beneficiary Information and Intervention Management System** for the Office of the Municipal Agriculturist (OMAG), Bontoc, Mountain Province.

AGAPAY replaces OMAG's separate Excel workbooks with one web system for:

- beneficiary profiles (address in parts, farm area, RSBSA No. or N/A);
- DA and LGU interventions, with the household rule (DA programs need an RSBSA No.);
- inventory, with automatic stock deduction;
- Excel import and export;
- crisis (agricultural damage) reports;
- distribution reports;
- a full audit trail.

It has three roles: **Administrator** (Municipal Agriculturist), **Agricultural Technologist** and **Data Encoder**.

Built with Laravel 13 (PHP 8.5), MariaDB, Blade, Tailwind CSS 4 and Alpine.js. Its screens follow the "Agapay - Wireframes" Figma file.

---

## Requirements

Install these once on the PC that will run AGAPAY:

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

## Accounts

The seeder creates these accounts. Their password is the value of `AGAPAY_SEED_PASSWORD` in `.env`; the default is in `.env.example`. **Change it, and each user's password, before real use.**

| Username | Role | Can do |
|---|---|---|
| `Admin_01` | Administrator | Everything: users and role permissions, audit trail, claims, archive/restore, inventory, import/export, distribution cycles, crises and crop values, reports |
| `Agritech_02` | Agricultural Technologist | Mark special cases (Deceased, Duplicate, ...), release programs, file and validate crisis reports, download reports |
| `Encoder_03` | Data Encoder | Add beneficiaries, edit profiles, encode intervention records, inventory, Excel import, file crisis reports, download reports |
| `Encoder_04` | (no role, inactive) | Shows how blocked accounts behave |

The Administrator can change what each role may do under **User Management → Configure Roles**.

## Everyday use

- **Distribution cycles:** go to Interventions → Distribution Cycles (Administrator) to add the next cycle, e.g. `2026-Q4`. Only one cycle is "ongoing" at a time. Cycles are kept in date order.
- **Adding beneficiaries:** use Add Beneficiary. Enter the address in parts (House/Lot No., Street, Sitio/Purok, Barangay); the town is always Bontoc, Mountain Province. The RSBSA No. is optional and shows as **N/A** when blank. Such farmers can get LGU programs but not DA programs.
- **Programs:** new program records are ready to release at once. Change a record's status only for special cases: Deceased (released to a proxy), or Duplicate, Relocated or Inactive (not claimable).
- **Assistance requests:** use **New Request** (Encoder, Agricultural Technologist) to record a farmer asking for a program, optionally after a crisis. The Administrator approves (this adds the program record for the current cycle) or denies with a reason on the LGU page's **Requests** tab, which also counts requests by barangay, sitio/purok, crisis and crop and downloads them as Excel.
- **LGU page:** lists every farmer with address, farm area and number of crisis reports; the LGU program records are on its **Program Records** tab.
- **Excel import:** use Upload Excel. The sheet needs Name (or First Name + Last Name), Birthdate and Barangay columns; RSBSA No., Address, Farm Area, Contact and an Intervention column are optional. Check the Processing Feedback and preview, then press **Confirm & Import**.
- **Export List:** one row per program given, with claim status, amount and the farmer's crises. Birthdates and contact numbers are left out.
- **Download Reports:** choose a cycle, a program, the dates and PDF or Excel. Every generated file stays under Generated Reports so it can be downloaded again.
- **Crisis reports:** go to Crisis Reports → + New Damage Report. Loss and cost are computed from the crop values, which the Administrator edits under "Crises & Crop Values" (also where new crises, e.g. a typhoon, are added).

## Installing at the office

For everyday use at OMAG, one office PC runs AGAPAY for everyone. Do these three things once, after the Setup above. The steps assume XAMPP is in `C:\xampp` and PHP 8.5 in `C:\php`; change the paths if yours differ.

### 1. Give MySQL a password

A fresh XAMPP lets anyone on the PC open the database as `root` with no password.

1. XAMPP Control Panel → MySQL **Admin** (phpMyAdmin) → **User accounts**.
2. For every `root` row (`localhost`, `127.0.0.1`, `::1`): **Edit privileges** → **Change password** → type a strong password → **Go**.
3. Put the same password in AGAPAY's `.env`: `DB_PASSWORD=your-password`.
4. phpMyAdmin will now ask for it: in `C:\xampp\phpMyAdmin\config.inc.php`, change `'auth_type'` from `'config'` to `'cookie'`.

### 2. Run AGAPAY with Apache

`php artisan serve` stops when its window closes. Apache (part of XAMPP) runs in the background and serves many users at once. XAMPP's own PHP is too old, so Apache is pointed at PHP 8.5:

1. Open `C:\xampp\apache\conf\httpd.conf` in Notepad. Put `#` in front of the line that starts with `Include "conf/extra/httpd-xampp.conf"` (XAMPP's old PHP).
2. At the end of the same file, add (fix the AGAPAY path):

```apache
LoadModule php_module "C:/php/php8apache2_4.dll"
PHPIniDir "C:/php"
AddHandler application/x-httpd-php .php
DirectoryIndex index.php

<VirtualHost *:80>
    DocumentRoot "C:/AGAPAY/public"
    <Directory "C:/AGAPAY/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Check that `LoadModule rewrite_module modules/mod_rewrite.so` has no `#` in front.
4. XAMPP Control Panel → **Start** Apache. Tick **Svc** beside Apache and MySQL so both start with Windows.
5. In `.env`, set `APP_URL=http://<this PC's IP address>` and `APP_ENV=production`, `APP_DEBUG=false`. Then, in the AGAPAY folder:

```bash
php artisan optimize
```

6. Allow port 80 in Windows Firewall. Other PCs open `http://<this PC's IP address>`.

After any later change to `.env`, run `php artisan optimize` again.

### 3. Back up every night

`backup.bat` (in the AGAPAY folder) copies the database and the photos/reports folder into a dated folder, and deletes backups older than 30 days.

1. Open `backup.bat` in Notepad and set, at the top: `XAMPP`, `DB_PASS` (the MySQL password from step 1) and `BACKUP_DIR` (a USB drive or another disk, e.g. `D:\AGAPAY-Backups`).
2. Double-click it once. It should say **Backup saved to …**.
3. Start → **Task Scheduler** → **Create Basic Task** → name it "AGAPAY backup" → **Daily**, e.g. 6:00 PM → **Start a program** → choose `backup.bat` → **Finish**.

**To restore** a backup (e.g. on a new PC after Setup):

```bash
C:\xampp\mysql\bin\mysql -u root -p agapay < D:\AGAPAY-Backups\<date>\agapay.sql
```

Then copy the backup's `files` folder back into `storage\app\private`.

The **"Use my location"** button on the damage form only works over `https://` or on the PC itself (`localhost`). Over plain `http://` on the office network, type the coordinates in.

## Putting AGAPAY online (Railway)

This gives AGAPAY a public `https://` link while the office setup above keeps working as before. [Railway](https://railway.com) runs Laravel and MySQL directly. The free trial gives $5 of credit; after that the Hobby plan costs about $5 a month. **When the credit runs out, the site stops**, so check that it covers the dates you need.

1. Make sure the latest code is on GitHub.
2. Go to railway.com → **Login** → **Login with GitHub**.
3. **New Project** → **Deploy from GitHub repo** → choose `agapay`. The first build may fail because the settings are not in yet; that is fine.
4. In the same project, press **+ Create** → **Database** → **MySQL**.
5. Click the AGAPAY service → **Settings** → **Networking** → **Generate Domain**. If it asks for a port, enter `8765`. Copy the address it shows, e.g. `agapay-production.up.railway.app`.
6. Make an app key on your own PC (in the AGAPAY folder). Copy the line it prints, which starts with `base64:`:

```bash
php artisan key:generate --show
```

7. Click the AGAPAY service → **Variables** → **Raw Editor**, paste the following, fill in the three `<...>` parts, and press **Update Variables**:

```ini
APP_NAME=Agapay
APP_ENV=production
APP_DEBUG=false
APP_KEY=<the base64:... line from step 6>
APP_URL=https://<the domain from step 5>
APP_TIMEZONE=Asia/Manila
DB_CONNECTION=mysql
DB_URL=${{MySQL.MYSQL_URL}}
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
TRUSTED_PROXIES=*
LOG_CHANNEL=stderr
AGAPAY_SEED_PASSWORD=<a new strong password>
PORT=8765
```

   **Do not use the default password from `.env.example`.** This repository is public, so anyone could read it.

8. Keep photos and reports between updates: right-click the AGAPAY service → **Attach Volume** → mount path `/app/storage/app/private`.
9. Create the tables and fill in the starting data **once**: AGAPAY service → **Settings** → **Deploy** → **Pre-deploy Command** → type `php artisan migrate --force && php artisan db:seed --force` → press **Deploy**. When the deployment shows **Success**, change the command to just `php artisan migrate --force`, so later updates add new tables but never load the sample data again.
10. Open `https://<your domain>` and sign in as `Admin_01` with the password from step 7. Change it under User Management.

Every push to GitHub's `master` branch updates the online copy automatically. The database changes (migrations) run by themselves.

If uploading a damage photo fails with a "permission denied" message, add the variable `RAILWAY_RUN_UID=0` and redeploy.

## Backups

At the office, `backup.bat` copies the database and the `storage/app/private` folder (damage photos, generated reports) every night; see **Installing at the office**, step 3. On a laptop you can also double-click it any time. For the online (Railway) copy, use the **Backups** tab on the Railway MySQL service and volume (available on paid plans).

## Tests

| What | Command |
|---|---|
| Feature tests (Pest, in-memory SQLite) | `php artisan test --compact` |
| Visual comparison with all 46 Figma frames | `npm run visual` |
| End-to-end flows for each role | `npm run e2e` |

`npm run visual` and `npm run e2e` use Microsoft Edge and their own database, `agapay_visual`. Create it once in phpMyAdmin. They never touch the `agapay` database. Run them one at a time.

## Known limits

- The distribution report PDF lists up to 1,000 beneficiary rows, and the damage report PDF up to 1,000 reports. The summaries count everything; choose Excel for the full lists.
- Damage photos are JPG/PNG, at least 1 and up to 10 per report (photographic documentation is mandatory, per OMAG), each up to 5 MB (or less if `upload_max_filesize` is lower).
- Excel imports are limited to 25 MB and 20,000 rows per file.
