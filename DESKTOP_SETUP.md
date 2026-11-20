# Windows desktop development setup

This guide prepares the existing application; it does not create a new Laravel project. Run commands in PowerShell. Finish the laptop Git steps below before cloning on the desktop.

## 1. Install and verify tools

Install Git and VS Code:

~~~powershell
winget install --id Git.Git --exact
winget install --id Microsoft.VisualStudioCode --exact
~~~

Open the official Herd download page, run the Windows installer as administrator, finish onboarding, and leave Herd running:

~~~powershell
Start-Process 'https://herd.laravel.com/windows'
~~~

Herd supplies PHP, Composer, Node.js and local web serving. In Herd, install/select PHP **8.4**. Verify Node is **22.12 or newer** (the installed Vite 8 and Laravel Vite plugin require ^20.19.0 or >=22.12.0). If needed, install a compatible Node release from the official download:

~~~powershell
Start-Process 'https://nodejs.org/en/download'
~~~

Install MySQL Community Server using its Windows installer; record the root password and port. MySQL is separate from free Herd; an existing Herd Pro MySQL service also works:

~~~powershell
Start-Process 'https://dev.mysql.com/downloads/mysql/'
~~~

Enable the MySQL Windows service during installation. Add its bin directory to PATH if mysql is not recognized. Reopen PowerShell after installations:

~~~powershell
git --version
code --version
herd --version
herd php:list
php -v
composer --version
node --version
npm --version
mysql --version
~~~

See [Herd Windows installation](https://herd.laravel.com/docs/windows/getting-started/installation), [Herd CLI commands](https://herd.laravel.com/docs/windows/advanced-usage/command-line) and [Vite requirements](https://vite.dev/guide/). The inspected dependencies include Laravel 13.35, Livewire 3.8 and Pest 5.1; install the committed lockfiles rather than updating packages.

## 2. Publish these changes from the laptop

The implementation is local until you commit and push it. Review the diff first. Do not include .env, database dumps, real patient data or generated files.

~~~powershell
git status --short
git diff
git add .env.example database/seeders/DatabaseSeeder.php database/seeders/UserSeeder.php database/seeders/PatientInformationSeeder.php database/seeders/VitalSignsSeeder.php database/seeders/MedicalRecordSeeder.php tests/Feature/DesktopDevelopmentSeederTest.php DESKTOP_SETUP.md
git commit -m "Prepare repeatable desktop development data and setup guide"
git push
~~~

## 3. Clone and open on the desktop

~~~powershell
New-Item -ItemType Directory -Force -Path "$env:USERPROFILE\Herd" | Out-Null
Set-Location "$env:USERPROFILE\Herd"
git clone https://github.com/Clarr1/alabang-health-center.git
Set-Location alabang-health-center
code .
herd link alabang-health-center
herd isolate 8.4
php -v
composer install
composer check-platform-reqs
npm ci
Copy-Item .env.example .env
code .env
~~~

Use composer install with development dependencies: the historical fixture uses the existing User factory and Faker. Do not use composer update or npm update for routine setup.

## 4. Create and configure the database

Connect to your local MySQL server:

~~~powershell
mysql -h 127.0.0.1 -P 3306 -u root -p
~~~

At the MySQL prompt run (choose your own development password):

~~~sql
CREATE DATABASE IF NOT EXISTS alabang_health_center CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'alabang_dev'@'127.0.0.1' IDENTIFIED BY 'replace-with-your-local-password';
GRANT ALL PRIVILEGES ON alabang_health_center.* TO 'alabang_dev'@'127.0.0.1';
EXIT;
~~~

Set these values in your desktop .env. Use your actual port and local password:

~~~dotenv
APP_NAME="Alabang Health Center"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://alabang-health-center.test
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alabang_health_center
DB_USERNAME=alabang_dev
DB_PASSWORD="replace-with-your-local-password"
MAIL_MAILER=log
TEXTBEE_ENABLED=false
TEXTBEE_API_KEY=
TEXTBEE_DEVICE_ID=
SURVEILLANCE_DEMO=false
~~~

Keep the other .env.example values. No external API credentials are required for this setup. Do not copy a production .env.

~~~powershell
php artisan config:clear
php artisan key:generate --no-interaction
php artisan migrate --no-interaction
php artisan db:seed --no-interaction
npm run build
~~~

db:seed wraps the development dataset in a transaction, disables TextBee and uses an in-memory mailer for that process. No follow-up messages or notification jobs are created. Keep TextBee disabled and the log mailer configured for subsequent development too.

For a deliberate reset of a disposable development database only:

~~~powershell
php artisan migrate:fresh --seed --no-interaction
~~~

**WARNING: migrate:fresh drops existing database tables and deletes their records.** Back up anything you need first. Normally use migrate followed by db:seed. Rerunning db:seed preserves fixture dates and existing records; it does not reset account passwords. If a reserved account has the wrong role or is inactive, seeding stops rather than replacing it.

## 5. Open through Herd

Herd serves this project automatically once its directory is parked or linked. From the project directory:

~~~powershell
herd link alabang-health-center
herd open
~~~

Use the local address matching APP_URL. Do not run php artisan serve. To edit frontend assets with live updates, leave this command running in another project terminal:

~~~powershell
npm run dev
~~~

Alternatively, rerun npm run build after frontend changes. If a stale public/hot file points to a stopped Vite process, restart npm run dev or remove that generated file before building.

## 6. Log in and inspect the fixtures

| Login account type | Email | Initial password | Stored role |
| --- | --- | --- | --- |
| Admin | admin@gmail.com | admin | Admin |
| Staff | staff@example.test | staff-demo | Local Health Unit Staff |
| Healthcare Personnel | doctor@example.test | doctor-demo | Doctor |

These are local development credentials. On an existing database, matching accounts keep their current password.

In Patients, find Juan Dela Cruz, Maria Santos and the other eight fictional records. Their addresses begin with FICTIONAL DEMO HOME and all have Alabang residence, one registration profile, one vital sign record with BMI, and one structured doctor consultation. Contact numbers are intentionally absent and SMS consent is false.

These ten sample visits are anchored 180 days before first seeding, outside the default surveillance baseline. Use date filters that include their registration dates when inspecting medical records.

The historical fixture adds **2,122 additional fictional patients and 2,123 encounters** under default surveillance settings: **2,132 total patients and 2,133 vital signs/consultations** on a fresh database. Counts scale with baseline weeks and minimum attendance settings. Separate patients are necessary for the engine's deduplication and attendance requirements. Historical patient names start with SEW TEST. All belong to Alabang and have linked staff/doctor accounts, vitals, completed queues, symptom pivots and diagnoses.

The existing fixture supplies at least 60 days (currently 94 by default) of baseline attendance, varied syndromes, respiratory controls, individual Dengue-like / Chickenpox-like / Malaria-like spikes and a combined 48-hour spike. Its SEW TEST syndrome definitions and simulated approvals are fictional software fixtures, not clinical case definitions. The detection algorithm is unchanged.

Seeding creates no alerts directly. It prints six exact historical replay commands. Run them **in the printed order**:

1. Normal baseline: all Normal.
2. No outbreak: all Normal.
3. Dengue-like: only that syndrome Alert.
4. Chickenpox-like: only that syndrome Alert.
5. Malaria-like: only that syndrome Alert.
6. Combined current spike: three outbreak syndromes Alert; controls Normal.

Each printed command has this format, with its actual saved timestamp:

~~~text
php artisan surveillance:detect --at=YYYY-MM-DDTHH:MM:SSZ
~~~

Retrieve the same commands later without duplicating data:

~~~powershell
php artisan db:seed --class=SyndromicEarlyWarningSeeder --no-interaction
~~~

Open Syndromic Surveillance, Alerts and History as Admin or Healthcare Personnel. Under default settings on a fresh dataset, the six ordered replays produce six internal alerts. Afterward, evaluate the current window if desired:

~~~powershell
php artisan surveillance:detect
~~~

The fixture's dates remain anchored to its first successful run. Once its spike is older than 48 hours, use the printed replay dates to reproduce results. A current detection is not expected to keep detecting an expired spike. Changed settings, other data or changed syndrome definitions may change outcomes.

## 7. Verify and switch computers

Run focused regression tests (the test configuration uses a separate in-memory SQLite database):

~~~powershell
php artisan test --compact tests/Feature/DesktopDevelopmentSeederTest.php tests/Feature/SyndromicEarlyWarningSeederTest.php
php artisan test --compact
~~~

Before leaving either computer:

~~~powershell
git status --short
git diff
# Stage only the source files you intend to share.
git add <reviewed-file-paths>
git commit -m "Describe your changes"
git push
~~~

On the other computer, first commit any local work. Then:

~~~powershell
git pull --ff-only
composer install
npm ci
php artisan optimize:clear
php artisan migrate --no-interaction
php artisan db:seed --no-interaction
npm run build
~~~

If pull cannot fast-forward, resolve the branch divergence before continuing. Git synchronizes source code, not databases or .env. Each machine has its own application key, credentials and first-run fixture anchor. Never regenerate an established APP_KEY as a routine pull step.

## Configuration and files reviewed

.env is ignored and not tracked. .env.example contains placeholders only, MySQL defaults, the Herd URL and disabled external notifications. composer.json/package.json and both lockfiles declare the existing dependencies; none were changed. Seeders are ordinary source files and must be committed with the commands above.

Existing ignore rules cover vendor, node_modules, public/build, public/hot, public/storage, local IDE settings, logs, cache/session files, private storage and database/*.sqlite*. Keep MySQL dumps, other .env variants, exported patient records, API credentials, auth.json and machine-specific Herd configuration out of commits. The ignore list does not automatically cover every possible dump filename or custom environment filename; review git status before staging.

Changed: .env.example; DatabaseSeeder, UserSeeder, PatientInformationSeeder and VitalSignsSeeder.
Created: MedicalRecordSeeder, DesktopDevelopmentSeederTest and this guide.
Reused unchanged: SyndromicEarlyWarningSeeder and its existing engine regression tests.
