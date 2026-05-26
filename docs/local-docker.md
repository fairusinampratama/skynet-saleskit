# Local Docker

Use Docker for local checks because the host PHP install may be missing required
extensions such as `intl`, `zip`, or database drivers. The local app is served
through nginx and PHP-FPM so it behaves closer to production than
`php artisan serve`.

## Start the App

```bash
docker compose up --build
```

nginx exposes the app at:

```text
http://localhost:8000
```

Technician login:

```text
http://localhost:8000/technician/login
```

Admin Filament login:

```text
http://localhost:8000
```

## Run Checks

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec app php artisan route:list --except-vendor
```

PHP-FPM runs in the `app` service. Background jobs run in the `queue` service.

## Database

MySQL is exposed on host port `3307`.

```text
database: saleskit
username: saleskit
password: saleskit
root password: saleskit-root
```

The local stack uses a fixed development `APP_KEY` from `docker-compose.yml`.
Production still needs a real generated key.
