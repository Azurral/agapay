# AGAPAY

**A Centralized Agricultural Beneficiary Information and Intervention Management System** for the Office of the Municipal Agriculturist (OMAG), Bontoc, Mountain Province.

AGAPAY replaces OMAG's separate Excel workbooks with one web system for:

- beneficiary profiles (address in parts, farm area, RSBSA No. or N/A);
- DA and LGU interventions, with the household rule (DA programs need an RSBSA No.);
- inventory, with automatic stock deduction;
- Excel import and export;
- crisis (agricultural damage) reports;
- assistance requests;
- distribution reports;
- a full audit trail;
- the farmer's consent under the Data Privacy Act (RA 10173), with a privacy notice.

It has three roles: **Administrator** (Municipal Agriculturist), **Agricultural Technologist** and **Data Encoder**, plus **AgriTech & Encoder** for one person who does both jobs.

Built with Laravel 13 (PHP 8.5), MariaDB, Blade, Tailwind CSS 4 and Alpine.js. Its screens follow the "Agapay - Wireframes" Figma file.

---

## Using AGAPAY at the office

The developers provide one installer, `AGAPAY-Setup-v<version>.exe`. It brings its own web server (Apache and PHP 8.5) and database (MariaDB), and needs no administrator rights and no internet for daily use.

1. Run it on the office laptop. If Windows says "Windows protected your PC" (the installer is not signed), choose **More info** → **Run anyway**.
2. Create the Administrator's account: last name, first name, M.I., suffix, username and password, with a live password checklist.
3. AGAPAY sets up an empty database (no sample farmers) and opens at `http://localhost:8742`. From then on it starts with Windows and makes a backup every day at noon.

Everything goes into `C:\AGAPAY`:

| In `C:\AGAPAY` | What it is |
|---|---|
| **AGAPAY** | Opens AGAPAY in the browser (also on the desktop) |
| **Backup now** | Makes a backup right away |
| **Restore a backup** | Lists the backups and puts the chosen one back, after saving the current records first |
| **AGAPAY Backups** | The newest 30 backups |
| **Uninstall AGAPAY** | Removes AGAPAY. It keeps the records and backups unless you choose to delete them. |
| **AGAPAY User Guide.pdf** | The staff guide |
| `system` (hidden) | PHP, Apache, MariaDB, the program, and the records in `system\data` |

Running the installer again (a newer version, or after an uninstall that kept the records) backs up first and keeps the records.

**Updates:** when a new version is released, the Administrator sees **Update available** above Dark Mode in the side panel (the laptop needs internet for this). The update backs up first, keeps the records, and goes back to the previous version by itself if anything fails.

## Online demo

`https://agapay.up.railway.app` runs the same system with **sample data only**, for testing and the capstone defense. Real farmer records are kept only on the office laptop.

## Accounts

These sample accounts exist in a development copy and in the online demo; the office installation has none (its installer creates the Administrator, who adds the staff).

The seeder creates these accounts. Their password is the value of `AGAPAY_SEED_PASSWORD` in `.env`; the default is in `.env.example`. **Change it, and each user's password, before real use.**

| Username | Role | Can do |
|---|---|---|
| `Admin_01` | Administrator | Everything: users and role permissions, audit trail, claims, archive/restore, inventory, import/export, distribution cycles, crises and crop values, reports |
| `Agritech_02` | Agricultural Technologist | Mark special cases (Deceased, Duplicate, ...), release programs, file and validate crisis reports, download reports |
| `Encoder_03` | Data Encoder | Add beneficiaries, edit profiles, encode intervention records, inventory, Excel import, file crisis reports, download reports |
| `Encoder_04` | (no role, inactive) | Shows how blocked accounts behave |

The seeder has no **AgriTech & Encoder** account; add one under **User Management → Add User** if you need it. Staff names are entered in parts (last name, first name, M.I., suffix), and new passwords are checked as they are typed (at least 8 characters, both boxes match).

The Administrator can change what each role may do under **User Management → Configure Roles**.

## Everyday use

- **Distribution cycles:** go to Interventions → Distribution Cycles (Administrator) to add the next cycle, e.g. `2026-Q4`. Only one cycle is "ongoing" at a time. Cycles are kept in date order.
- **Programs:** the Administrator adds and edits the DA and LGU programs under Interventions → Programs (unit, one per household per cycle or not, active or not).
- **Adding beneficiaries:** use Add Beneficiary. Enter the address in parts (House/Lot No., Street, Sitio/Purok, Barangay); the town is always Bontoc, Mountain Province. The RSBSA No. is optional and shows as **N/A** when blank. Such farmers can get LGU programs but not DA programs. The farmer's **consent** checkbox is required; who confirmed it and when shows on the profile.
- **Program records:** new program records are ready to release at once. Change a record's status only for special cases: Deceased (released to a proxy), or Duplicate, Relocated or Inactive (not claimable).
- **Assistance requests:** use **New Request** (Encoder, Agricultural Technologist) to record a farmer asking for a program, optionally after a crisis. The Administrator approves (this adds the program record for the current cycle) or denies with a reason on the LGU page's **Requests** tab, which also counts requests by barangay, sitio/purok, crisis and crop and downloads them as Excel.
- **LGU page:** lists every farmer with address, farm area and number of crisis reports; the LGU program records are on its **Program Records** tab.
- **Excel import:** use Upload Excel. The sheet needs Name (or First Name + Last Name), Birthdate and Barangay columns; RSBSA No., Address, Farm Area, Contact and an Intervention column are optional. Check the Processing Feedback and preview, tick the consent box for the file, then press **Confirm & Import**.
- **Export List:** one row per program given, with claim status, amount and the farmer's crises. Birthdates and contact numbers are left out.
- **Privacy notice:** the landing page links to `/privacy`, the full notice (what is kept, why, who sees it, the farmer's rights).
- **Download Reports:** choose a cycle, a program, the dates and PDF or Excel. Every generated file stays under Generated Reports so it can be downloaded again.
- **Crisis reports:** go to Crisis Reports → + New Damage Report. Loss and cost are computed from the crop values, which the Administrator edits under **Crop Values**; new crises (e.g. a typhoon) are added with **+ Add Crisis**.

## Backups

- **Office laptop:** automatic. A backup is made every day at noon (or as soon as the laptop is on), when AGAPAY starts if the last one is over a day old, and before every update, restore and reinstall. The newest 30 are kept in `C:\AGAPAY\AGAPAY Backups`. **Backup now** and **Restore a backup** are in `C:\AGAPAY`.
- **Online demo:** use the **Backups** tab on the Railway MySQL service and volume (paid plans). It holds sample data only.

## Known limits

- The distribution report PDF lists up to 1,000 beneficiary rows, and the damage report PDF up to 1,000 reports. The summaries count everything; choose Excel for the full lists.
- Damage photos are JPG/PNG, at least 1 and up to 10 per report (photographic documentation is mandatory, per OMAG), each up to 5 MB (or less if `upload_max_filesize` is lower).
- Excel imports are limited to 25 MB and 20,000 rows per file.
- The office copy (AGAPAY for Windows) is used on the laptop it is installed on; other computers on the network cannot open it.
- Damage photos are stored as uploaded, so many large phone photos make each backup bigger.
- The **"Use my location"** button on the damage form only works over `https://` or on the computer itself (`localhost`). Otherwise type the coordinates in.

## For developers

Setting up AGAPAY from the source code, building the installer, publishing updates, putting it online and running the tests: see [DEVELOPMENT.md](DEVELOPMENT.md).
