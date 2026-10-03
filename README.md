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

| | Version |
|---|---|
| XAMPP (or any PHP + MariaDB/MySQL) | PHP **8.5**, MariaDB 10.4+ |
| Composer | 2.x |
| Node.js | 20+ (for building the CSS/JS) |

**PHP extensions** (enable them in `php.ini`): `pdo_mysql`, `mbstring`, `fileinfo`, `intl`, `zip`, `gd`, `xml`/`dom`, `iconv`.

**php.ini limits** (needed for Excel uploads, damage photos and large PDFs):

```ini
upload_max_filesize = 25M
post_max_size = 60M
memory_limit = 512M
```

Restart the web server or `php artisan serve` after changing `php.ini`.

## Setup

1. Create an empty database named `agapay` (phpMyAdmin → New).
2. In the project folder:

```bash
composer install
```

```bash
npm install
```

```bash
copy .env.example .env
```

```bash
php artisan key:generate
```

3. Check the database settings in `.env` (`DB_DATABASE=agapay`, `DB_USERNAME=root`, `DB_PASSWORD=`).
4. Create the tables and the starting data (the 16 barangays, roles, sample beneficiaries, interventions, inventory and damage records):

```bash
php artisan migrate --seed
```

5. Build the front end and start the server:

```bash
npm run build
```

```bash
php artisan serve --port=8765
```

Open http://127.0.0.1:8765. Keep the terminal open while using AGAPAY.

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

## Backups

Back up two things regularly:

1. **The database:** phpMyAdmin → `agapay` → Export, or:

```bash
mysqldump -u root agapay > agapay-backup.sql
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
