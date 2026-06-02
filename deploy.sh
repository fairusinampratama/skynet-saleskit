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

# 2b. Seed Indonesian administrative data (support old/new Laravolt command names)
if php artisan list --raw | grep -q "^laravolt:indonesia:seed"; then
  echo "Seeding Indonesian administrative data with laravolt:indonesia:seed..."
  php artisan laravolt:indonesia:seed
elif php artisan list --raw | grep -q "^indonesia:seed"; then
  echo "Seeding Indonesian administrative data with indonesia:seed..."
  php artisan indonesia:seed
fi

# 3. Verify OCR runtime
echo "🔎 Verifying KTP OCR runtime..."
OCR_PYTHON="${EASYOCR_PYTHON:-python3}"

if ! command -v "$OCR_PYTHON" > /dev/null 2>&1 && ! [ -x "$OCR_PYTHON" ]; then
  echo "❌ OCR Python executable not found: $OCR_PYTHON" >&2
  exit 1
fi

if [ -n "${EASYOCR_MODEL_DIR:-}" ]; then
  mkdir -p "$EASYOCR_MODEL_DIR"
fi

if ! "$OCR_PYTHON" -c "import easyocr" > /dev/null 2>&1; then
  echo "❌ EasyOCR is not installed or cannot be imported by $OCR_PYTHON" >&2
  exit 1
fi

echo "✅ KTP OCR runtime is ready."

# 4. Cache optimization
echo "⚡ Optimizing application cache..."
php artisan optimize

echo "✅ Pre-deployment tasks complete. Passing control to Nixpacks..."

echo "✅ Deployment scripting complete. Passing control to Nixpacks..."
