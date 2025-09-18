#!/bin/bash

# MyTaxEU Permissions Fix Script
# This script ensures proper permissions for queue workers and database access

echo "🔧 Fixing MyTaxEU permissions..."

# Fix database permissions (critical for queue workers)
echo "📄 Setting database permissions..."
chmod 664 /var/www/mytaxeu/database/database.sqlite
chown root:www-data /var/www/mytaxeu/database/database.sqlite

# Fix storage permissions (for file uploads and logs)
echo "📁 Setting storage permissions..."
chmod -R 775 /var/www/mytaxeu/storage
chown -R www-data:www-data /var/www/mytaxeu/storage

# Fix bootstrap/cache permissions (for Laravel optimization)
echo "⚡ Setting cache permissions..."
chmod -R 775 /var/www/mytaxeu/bootstrap/cache
chown -R www-data:www-data /var/www/mytaxeu/bootstrap/cache

# Fix log permissions specifically
echo "📝 Setting log permissions..."
chmod -R 775 /var/www/mytaxeu/storage/logs
chown -R www-data:www-data /var/www/mytaxeu/storage/logs

# Ensure database directory is writable
echo "🗃️ Setting database directory permissions..."
chmod 775 /var/www/mytaxeu/database
chown root:www-data /var/www/mytaxeu/database

echo "✅ Permissions fixed successfully!"
echo "📊 Current database permissions:"
ls -la /var/www/mytaxeu/database/database.sqlite
