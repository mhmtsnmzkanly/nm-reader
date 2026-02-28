#!/bin/bash

# NovelMangaReader - Production Setup Script
# Usage: chmod +x setup.sh && ./setup.sh

echo "🚀 Starting NMR Setup..."

# 1. Install Composer Dependencies
if [ -f "composer.json" ]; then
    echo "📦 Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader
else
    echo "❌ Error: composer.json not found!"
    exit 1
fi

# 2. Create Required Directory Structure
echo "📁 Creating storage and public directories..."
mkdir -p storage/cache 
         storage/logs 
         storage/sessions 
         storage/backups 
         storage/views 
         public/uploads

# 3. Handle Environment File
if [ ! -f ".env" ]; then
    echo "📄 Creating .env from .env.example..."
    cp .env.example .env
    echo "⚠️  Action Required: Please edit .env with your production credentials!"
fi

# 4. Set Permissions (Production Standard)
echo "🔒 Setting permissions..."

# Get the web server user (usually www-data, apache, or nginx)
WEB_USER=$(ps aux | grep -E '[a]pache|[n]ginx|[p]hp-fpm' | grep -v root | head -1 | cut -d\  -f1)

if [ -z "$WEB_USER" ]; then
    WEB_USER="www-data"
fi

echo "👤 Detected web user: $WEB_USER"

# Make everything readable by web server, but writable only where needed
chmod -R 755 .
chmod -R 775 storage
chmod -R 775 public/uploads

# Change ownership to web user
if [ "$EUID" -ne 0 ]; then
    echo "⚠️  Not running as root. Ownership (chown) skipped. Please run manually if needed:"
    echo "   sudo chown -R $USER:$WEB_USER storage public/uploads"
else
    chown -R $USER:$WEB_USER storage public/uploads
fi

# 5. Clear Cache
echo "🧹 Cleaning up old cache files..."
rm -rf storage/cache/*

echo "✅ Setup Complete!"
echo "👉 Next step: Visit your-site.com/install-63e4qq3 to finish installation."
