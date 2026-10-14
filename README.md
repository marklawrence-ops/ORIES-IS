# ORIES-IS Development Setup Guide

Welcome to the repository for the ORIES Information System. This guide provides the step-by-step process to get the local development environment running on your machine.

## 🛠 Prerequisites

Before you begin, ensure your machine has the following installed:
*   **PHP** (v8.4 or higher)
*   **Composer** (PHP dependency manager)
*   **Node.js** (v20 or higher) & **npm**
*   **PostgreSQL** (V16; Ensure the service is actively running on port 5432)

---

## 🐘 Backend Setup (Laravel 12)

The backend is the REST API: accounts and roles, records and their lifecycle, verification, local MOV storage with Google Drive backup, duplicate-title crosschecking, certificates, reports, and notifications.

### 1. Install Dependencies
Open your terminal, navigate into the backend directory, and install the PHP packages:
```bash
cd ories-backend
composer install

```

### 2. Environment Configuration

Duplicate the example environment file:

```bash
cp .env.example .env

```

Open the new `.env` file and update the database configuration to match your local PostgreSQL setup:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ories_is_db
DB_USERNAME=postgres
DB_PASSWORD=your_local_pg_password

```

Generate your local application key:

```bash
php artisan key:generate

```

The ORIES-IS settings (duplicate threshold, upload size, reminder interval, mail notifications) are listed at the bottom of `.env.example` and default to sensible values.

### 3. Google Drive (optional)

Uploaded Means of Verification are always stored locally in `storage/app/private/movs`. Google Drive is a secondary backup and the source for importing existing files, so the system works without it.

* Ask the repository owner for the `google-credentials.json` service-account file and place it in `ories-backend/storage/app/`.
* **Do not commit this file to version control.**
* Use a **Shared Drive** folder for backups (service accounts cannot store files in a personal "My Drive") and share it with the service account as a Content Manager.

```env
GOOGLE_DRIVE_BACKUP_ENABLED=true
GOOGLE_DRIVE_FOLDER_ID="shared_drive_backup_folder_id"
GOOGLE_DRIVE_IMPORT_FOLDER_ID="existing_files_folder_id"

```

### 4. Database Setup & Seed Data

Create an empty database named `ories_is_db`, then build the tables and load the campuses, colleges, one account per role, and fictional demonstration records:

```bash
php artisan migrate --seed

```

See [Resetting the Demo Data](#-resetting-the-demo-data) to start over later.

### 5. Start the Server (plus optional Queue Worker and Scheduler)

```bash
php artisan serve --no-reload

```

> On Windows, plain `php artisan serve` fails with `Failed to listen on 127.0.0.1:8000 (reason: ?)`. Laravel removes the `SystemRoot` environment variable from the server process (it only keeps the uppercase `SYSTEMROOT`), and PHP cannot open sockets without it. `--no-reload` keeps the environment intact; restart the server yourself after editing `.env`.

Two background processes are **optional** during development. Run each in its own terminal only when you need it:

* **Queue worker:** uploads backup copies to Google Drive. Needed only when `GOOGLE_DRIVE_BACKUP_ENABLED=true`; uploads are always saved locally either way.

  ```bash
  php artisan queue:work

  ```

* **Scheduler:** sends weekly reminders for ongoing records with no verified update in 90 days and retries failed Drive backups hourly.

  ```bash
  php artisan schedule:work

  ```

*The backend is now running at `http://localhost:8000`.*

---

## ⚛️ Frontend Setup (Next.js 16)

One role-aware application for faculty and non-teaching personnel, heads, ORIES staff, directors, the VP, and the OUP/Planning Office.

### 1. Install Dependencies

```bash
cd ories-frontend
npm install

```

### 2. Environment Configuration

Create a `.env.local` file in the root of the frontend folder (optional; this is the default):

```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api

```

### 3. Start the Development Server

```bash
npm run dev

```

*The frontend is now running at `http://localhost:3000`.*

---

## ▶️ Quick Start (after setup)

| Terminal | Folder | Command | Required? |
|---|---|---|---|
| 1 | `ories-backend` | `php artisan serve --no-reload` | Yes |
| 2 | `ories-frontend` | `npm run dev` | Yes |
| 3 | `ories-backend` | `php artisan queue:work` | Only for Google Drive backup |
| 4 | `ories-backend` | `php artisan schedule:work` | Only for reminders and backup retries |

Then open `http://localhost:3000`.

---

## 🚀 Trying the Workflow

All seeded accounts use the password `password123`. Sign out and sign in as another role to see each side of the workflow.

| Role | Email |
|---|---|
| Faculty | `faculty@evsu.edu.ph` |
| Head / Dean | `head@evsu.edu.ph` |
| Research office staff | `staff.research@evsu.edu.ph` |
| Technical staff (all offices, user admin) | `admin@evsu.edu.ph` |
| Director for Research | `director.research@evsu.edu.ph` |
| VP for ORIES | `vp@evsu.edu.ph` |
| OUP / Planning (view-only) | `oup@evsu.edu.ph` |

1. Sign in as faculty and **Submit Proposal** with a file attached. Type a title close to an existing one to see the duplicate warning.
2. Sign in as `staff.research@evsu.edu.ph`, open **Verification Queue**, open the MOV, and verify or return it.
3. As faculty, open the verified record, **Submit update** (stage or outcome), and **Request certificate**.
4. As `director.research@evsu.edu.ph`, decide the request in **Certificates**; check the code on `/verify`.
5. As the VP, explore the dashboard drill-downs, **Reports** (with CSV export), and the **Org Chart**.
6. As `admin@evsu.edu.ph`, approve the pending self-registered account in **User Accounts** and check **Storage & Drive Import**.

## 🔄 Resetting the Demo Data

Wipes the database and reloads the campuses, one account per role, and the fictional demo records. **Development only. All data is deleted.**

```bash
cd ories-backend
php artisan migrate:fresh --seed

```

Files uploaded while testing stay in `ories-backend/storage/app/private/movs/`; delete that folder too for a completely clean start.

## ✅ Tests and Evaluation

The automated tests use their own in-memory database, so the servers do not need to be running and your demo data is not touched. Google Drive backup is always off during tests, even if it is enabled in `.env`:

```bash
cd ories-backend
php artisan test

```

Before a release, also run the suite on PostgreSQL, because SQLite ignores row locks. Create an empty `ories_is_test` database once, then in PowerShell (**never point this at `ories_is_db`; the tests wipe the database they run on**):

```powershell
$env:DB_CONNECTION='pgsql'; $env:DB_DATABASE='ories_is_test'; php artisan test; Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE

```

Evaluate the Crosschecking Module against the labeled title pairs in `database/data/crosscheck_validation.csv`:

```bash
php artisan ories:evaluate-crosscheck

```

## 🧰 Troubleshooting

* **`Failed to listen on 127.0.0.1:8000 (reason: ?)`**: start the backend with `php artisan serve --no-reload` (see Backend step 5).
* **`Another next dev server is already running`** or **`Port 3000 is in use`**: an earlier `npm run dev` is still running. Stop it with `Ctrl+C` in its terminal, or end the process with the PID shown in the message (`taskkill /PID <pid> /F`), then run `npm run dev` again.
* **Login shows "Authentication failed" for every account**: the backend is not running, or the database has not been seeded yet (`php artisan migrate --seed`).
