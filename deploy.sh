#!/bin/sh

# Skynet Saleskit - Coolify Deployment Script
# This script runs as a Supervisor one-shot task after Octane starts.

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

# 2. Prepare database
# We override CACHE_STORE to 'file' for migrations to avoid "Table cache_locks not found"
# on the very first deployment when using --isolated.
if [ "${DEPLOY_FRESH_DATABASE:-false}" = "true" ]; then
  echo "📦 Rebuilding database from fresh migrations..."
  CACHE_STORE=file php artisan migrate:fresh --force --seed
else
  echo "📦 Running database migrations..."
  CACHE_STORE=file php artisan migrate --force --isolated

  echo "🌱 Seeding baseline users and areas..."
  php artisan db:seed --force
fi

# 3. Verify OCR service
echo "🔎 Verifying KTP OCR service..."
if [ -n "${PADDLEOCR_URL:-}" ] && php -r '$url=rtrim(getenv("PADDLEOCR_URL"), "/")."/health"; $context=stream_context_create(["http"=>["timeout"=>5]]); $json=@file_get_contents($url, false, $context); $data=$json ? json_decode($json, true) : null; exit((is_array($data) && !empty($data["ready"])) ? 0 : 1);'; then
  echo "✅ KTP OCR service is ready."
else
  echo "⚠️ KTP OCR service is unavailable or PADDLEOCR_URL is not configured. SalesKit will start, but OCR scans will require manual entry." >&2
fi

# 4. Cache optimization
echo "⚡ Optimizing application cache..."
php artisan optimize

echo "✅ Pre-deployment checks complete. Passing control to Nixpacks..."

echo "✅ Deployment scripting complete. Passing control to Nixpacks..."
