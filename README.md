# Mega Stationary Catalog API

Laravel API serving as the authoritative data source for the Mega Stationary Catalog mobile app.

## Features

- Laravel Sanctum token authentication with admin and user roles
- Server-backed category ordering and video management
- Private authenticated video and cover downloads
- Cursor-based changes for offline mobile synchronization
- Video size metadata for device storage reporting

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

The Flutter Android emulator defaults to `http://10.0.2.2:8000/api`. For a physical device or production server, provide another URL:

```bash
flutter run --dart-define=API_BASE_URL=https://example.com/api
```

## Demo accounts

- Admin: `admin@mega.test` / `password`
- User: `user@mega.test` / `password`

Change or remove these seed credentials before deployment.

## Verify

```bash
php artisan test
vendor/bin/pint --test
```
