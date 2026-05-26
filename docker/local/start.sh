#!/usr/bin/env bash

set -euo pipefail

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ]; then
  npm install
fi

if [ ! -d public/build ]; then
  npm run build
fi

php artisan storage:link --force || true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan filament:optimize

exec php-fpm -F
