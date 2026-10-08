# AGAPAY

**A Centralized Agricultural Beneficiary Information and Intervention Management System** for the Office of the Municipal Agriculturist (OMAG), Bontoc, Mountain Province.

AGAPAY replaces OMAG's separate Excel workbooks with one web system for:

- beneficiary profiles and RSBSA registration;
- DA and LGU interventions, with household and eligibility rules;
- inventory, with automatic stock deduction;
- Excel import and export;
- agricultural damage recording;
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
| `Admin_01` | Administrator | Everything: users and role permissions, audit trail, RSBSA processing, claims, archive/restore, inventory, import/export, distribution cycles, disasters and crop values, reports |
| `Agritech_02` | Agricultural Technologist | Validate eligibility, verify claims, file and validate damage reports, generate reports |
| `Encoder_03` | Data Encoder | Register beneficiaries, edit profiles, encode intervention records, inventory, Excel import, file damage reports, generate reports |
| `Encoder_04` | (no role, inactive) | Shows how blocked accounts behave |

The Administrator can change what each role may do under **User Management → Configure Roles**.

## Everyday use

- **Distribution cycles:** go to Interventions → Distribution Cycles (Administrator) to add the next cycle, e.g. `2026-Q4`. Only one cycle is "ongoing" at a time. Cycles are kept in date order.
- **Excel import:** use Upload Excel. The sheet needs Name (or First Name + Last Name), Birthdate and Barangay columns; RSBSA No., Address, Contact and an Intervention column are optional. Check the Processing Feedback and preview, then press **Confirm & Import**.
- **Reports:** use Reports, then choose a cycle, a program, the dates and PDF or Excel. Every generated file stays under Generated Reports so it can be downloaded again.
- **Damage reports:** go to Disaster Reports → + New Damage Report. Loss and cost are computed from the crop values, which the Administrator edits under "Disasters & Crop Values".

## Using it on the office network

Run AGAPAY on one office PC and open it from the others:

```bash
php artisan serve --host=0.0.0.0 --port=8765
```

The other PCs then open `http://<that PC's IP address>:8765`. Allow port 8765 in Windows Firewall. For a permanent setup, point an Apache virtual host at the `public/` folder instead of using `php artisan serve`. Also set `APP_URL` in `.env` to the address people use.

The **"Use my location"** button on the damage form only works over `https://` or on the PC itself (`localhost`). Over plain `http://` on the network, type the coordinates in.

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

Back up two things regularly:

1. **The database:** phpMyAdmin → `agapay` → **Export** → **Go**. Or, in a terminal:

```bash
C:\xampp\mysql\bin\mysqldump -u root agapay > agapay-backup.sql
```

2. **Uploaded and generated files:** the folder `storage/app/private`. It holds damage photos and generated reports.

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
