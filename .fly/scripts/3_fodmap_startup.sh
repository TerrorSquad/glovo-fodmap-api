#!/bin/bash
set -e

echo "🚀 FODMAP API: Initializing startup sequence..."

# Wait for database to be ready (important for auto-shutdown scenarios)
echo "📊 Waiting for database connection..."
until php artisan migrate:status >/dev/null 2>&1; do
    echo "⏳ Database not ready, waiting 2 seconds..."
    sleep 2
done

# Run database migrations
echo "📊 Running database migrations..."
php artisan migrate --force

# Clear and optimize Laravel caches
echo "⚡ Optimizing Laravel for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ FODMAP API startup sequence completed!"
