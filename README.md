# City Hall Monitoring System

Laravel API backend + Next.js frontend. Default: SQLite, backend on port 8000, frontend on port 3000.

## Prerequisites

- **PHP 8.2+** (with extensions: mbstring, xml, pdo_sqlite, json, openssl, tokenizer)
- **Composer**
- **Node.js 18+** and npm
- **SQLite** (or MySQL/PostgreSQL if you change `.env`)

## How to run

### 1. Backend (Laravel)

```bash
cd CityHallMonitoringSystem
```

- Copy env and generate key (first time only):

```bash
copy .env.example .env
php artisan key:generate
```

- Install PHP dependencies and refresh autoload (fixes “HasApiTokens not found” if it appears):

```bash
composer install
composer dump-autoload
```

- Create DB and run migrations:

```bash
php artisan migrate
```

- Seed the database (creates users, departments, etc.):

```bash
php artisan db:seed
```

- Start the API server:

```bash
php artisan serve
```

Backend will be at **http://localhost:8000**. API base URL: **http://localhost:8000/api**.

### 2. Frontend (Next.js)

In a **second terminal**:

```bash
cd frontend
npm install
npm run dev
```

Frontend will be at **http://localhost:3000**. It talks to the API at `http://localhost:8000/api` by default.

### 3. Optional: API URL for frontend

If your backend runs on another host/port, create `frontend/.env.local`:

```env
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
```

## Quick recap

| Step | Where | Command |
|------|--------|---------|
| Env + key | `CityHallMonitoringSystem` | `copy .env.example .env` then `php artisan key:generate` |
| PHP deps | `CityHallMonitoringSystem` | `composer install` then `composer dump-autoload` |
| DB | `CityHallMonitoringSystem` | `php artisan migrate` then `php artisan db:seed` |
| Backend | `CityHallMonitoringSystem` | `php artisan serve` |
| Frontend | `frontend` | `npm install` then `npm run dev` |

If **db:seed** fails with “Trait Laravel\Sanctum\HasApiTokens not found”, run `composer dump-autoload` in `CityHallMonitoringSystem` and try again.