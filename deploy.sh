#!/bin/sh

# Skynet Saleskit - Coolify Deployment Script
# This script runs on container startup

set -e

echo "🚀 Starting Skynet Saleskit deployment..."

# 0. Wait for database to be ready
echo "⏳ Waiting for database connection..."
until php artisan db:show > /dev/null 2>&1; do
  echo "  (Still waiting for database...)"
  sleep 2
done
echo "📡 Database is ready!"

# 1. Create storage link (ignore if exists)
echo "🔗 Creating storage symlink..."
php artisan storage:link --force || true

# 2. Run migrations
echo "📦 Running database migrations..."
# We override CACHE_STORE to 'file' for migrations to avoid "Table cache_locks not found" 
# on the very first deployment when using --isolated.
CACHE_STORE=file php artisan migrate --force --isolated

# 2b. Seed Indonesian administrative data if Laravolt command exists
if php artisan list --raw | grep -q "^indonesia:seed"; then
  echo "Seeding Indonesian administrative data..."
  php artisan indonesia:seed
fi

# 3. Cache optimization
echo "⚡ Optimizing application cache..."
php artisan optimize

echo "✅ Pre-deployment tasks complete. Passing control to Nixpacks..."

echo "✅ Deployment scripting complete. Passing control to Nixpacks..."
