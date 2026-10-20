# Bariton Music Academy - API (`bariton-api`)

Backend REST API for the Bariton Music Academy online learning platform, built with Laravel 13, MySQL, Laravel Sanctum, and Spatie Permissions.

---

## Requirements
- PHP 8.3+
- Composer 2+
- MySQL 8.x (running on port 3306 with InnoDB support)

---

## Quickstart Setup
```bash
# 1. Install dependencies
composer install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Configure your database in .env (DB_DATABASE=bariton, DB_USERNAME=root, DB_PASSWORD=)
# Ensure the database exists: CREATE DATABASE bariton;

# 4. Run migrations and seed roles, settings and demo users
php artisan migrate --force
php artisan db:seed --force

# 5. Start dev server
php artisan serve
```

API will be accessible at `http://localhost:8000`.

---

## Running Tests & Quality
```bash
# Run Pest test suite (includes Phase 1 comprehensive criteria tests)
vendor/bin/pest

# Run Laravel Pint code styling
vendor/bin/pint
```

---

## Default Accounts
All seeded accounts use password: `Secret123!`
- Super Admin: `admin@bmas.com`
- Coordinator: `coordinator@bmas.com`
- Instructor: `instructor@bmas.com`
- Finance Staff: `finance@bmas.com` (Strict 2FA setup required)
- Adult Student: `student.adult@bmas.com`
- Minor Student: `student.minor@bmas.com`
