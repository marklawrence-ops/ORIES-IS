Here is a complete, ready-to-use `README.md` tailored specifically for your project's architecture. It includes all the critical steps for the Next.js frontend, the Laravel backend, the PostgreSQL database, and the specific Google Drive API credentials we just set up.

You can copy and paste this directly into a `README.md` file in the root of your repository so Aldrin, Axyll, and Christian can get their local environments running smoothly for the defense.

---

```markdown
# ORIES-IS Development Setup Guide

Welcome to the repository for the ORIES Information System. This guide provides the step-by-step process to get the local development environment running on your machine.

## 🛠 Prerequisites

Before you begin, ensure your machine has the following installed:
*   **PHP** (v8.2 or higher)
*   **Composer** (PHP dependency manager)
*   **Node.js** (v18 or higher) & **npm**
*   **PostgreSQL** (V16; Ensure the service is actively running on port 5432)

---

## 🐘 Backend Setup (Laravel)

The backend handles our API, PostgreSQL database routing, and Google Drive ingestion queue.

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

### 3. Google Drive Service Account Setup

For the Ingestion Queue to work, you need the Google Service Account credentials.

* **Do not commit this file to version control.**
* Ask the repository owner for the `google-credentials.json` file.
* Place this file directly inside `ories-backend/storage/app/`.
* Ensure your `.env` file includes the shared drop-off folder ID:

```env
GOOGLE_DRIVE_FOLDER_ID="your_shared_folder_id_here"

```

### 4. Database Setup & Migrations

Open pgAdmin (or your psql CLI) and create an empty database named `ories_is_db`. Once created, run the migrations to build the tables (including the deliverables and tags schemas):

```bash
php artisan migrate

```

### 5. Start the Server

Launch the local Laravel API:

```bash
php artisan serve

```

*The backend is now running at `http://localhost:8000`.*

---

## ⚛️ Frontend Setup (Next.js v14)

The frontend contains the role-based dashboards (Head, Director, VP) and the tagging module, styled with Tailwind CSS.

### 1. Install Dependencies

Open a *new* terminal window, navigate to the frontend directory, and install the Node packages:

```bash
cd ories-frontend 
npm install

```

### 2. Environment Configuration

Create a `.env.local` file in the root of the frontend folder and connect it to your local Laravel API:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api

```

### 3. Start the Development Server

Launch the Next.js application:

```bash
npm run dev

```

*The frontend is now running at `http://localhost:3000`.*

---

## 🚀 Testing the Pipeline

To verify your setup is fully operational:

1. Open `http://localhost:3000` in your browser.
2. Log in using a Head account (e.g., `head@evsu.edu.ph`).
3. Navigate to the **Ingestion Queue** and click "Sync with Google Drive". If files appear without a 500 error, your Service Account and API are perfectly configured.
4. Encode a deliverable and push it through the pipeline to ensure PostgreSQL is saving the `mov_link` and tags correctly.

```

```