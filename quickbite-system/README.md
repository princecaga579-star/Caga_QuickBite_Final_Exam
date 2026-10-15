# QuickBite — Online Food Ordering & Management System

Vue 3 + Vite customer website and admin dashboard, backed by Laravel API + MySQL.

## Features
- Browse/search food, categories, product availability and prices
- Cart saved in browser, checkout with pickup/delivery details
- Cash on delivery or GCash (GCash is recorded as a payment method; this starter does not charge or verify GCash payments automatically)
- Order tracking by order number and phone number
- Admin sign-in, category/product CRUD, stock adjustment, order status management and sales summary
- Seeded demo categories, foods, and admin account

## Requirements
PHP 8.2+, Composer, MySQL 8+, Node.js 20+, npm.

## 1. Set up the Laravel backend (Windows / PowerShell)
This bundle contains the custom Laravel app files in `backend/`, not the Laravel framework vendor files. Create a clean Laravel install next to it, then copy the provided app files into that install.

From the extracted `quickbite-system` folder:
```powershell
composer create-project laravel/laravel laravel-app
cd laravel-app
php artisan install:api
composer require laravel/sanctum
Copy-Item ..\backend\app\* .\app\ -Recurse -Force
Copy-Item ..\backend\database\migrations\2026_01_01_000001_create_quickbite_tables.php .\database\migrations\ -Force
Copy-Item ..\backend\database\migrations\2026_01_01_000002_add_is_admin_to_users_table.php .\database\migrations\ -Force
Copy-Item ..\backend\database\seeders\* .\database\seeders\ -Recurse -Force
Copy-Item ..\backend\routes\api.php .\routes\api.php -Force
Copy-Item ..\backend\.env.example .\.env -Force
```
If PowerShell asks to overwrite files, accept it for the listed custom files. Keep the default Laravel `bootstrap/`, `config/`, `public/`, `artisan`, and `composer.json` files.

Edit `laravel-app/.env` and set your own MySQL credentials:
```env
APP_NAME=QuickBite
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quickbite
DB_USERNAME=root
DB_PASSWORD=
FRONTEND_URL=http://localhost:5173
```
Create the `quickbite` database in MySQL first. Then, still in `laravel-app`:
```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
API base URL: `http://127.0.0.1:8000/api`

## 2. Start Vue frontend
Open a second terminal in the extracted `quickbite-system` folder:
```bash
cd frontend
npm install
npm run dev
```
Open the URL Vite prints, usually `http://localhost:5173`.

## Demo admin
- Email: `admin@quickbite.test`
- Password: `ChangeMe123!`
Change the password and configure production authentication, rate limits, HTTPS, backups, and authorization before deployment.

## Important notes
- This is a development starter. GCash payments are not integrated with a payment gateway; selecting GCash records the method and payment status as pending.
- Product image URLs are seeded remote placeholders; replace them with your own images or add Laravel Storage uploads.
- The frontend calls the API directly. Set `VITE_API_URL` if your API URL differs.
