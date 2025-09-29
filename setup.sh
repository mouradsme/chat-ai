#!/bin/bash

# Laravel Chatbot Platform Setup Script for Ubuntu 22.04
# This script installs all dependencies and configures the server

set -e

echo "🚀 Starting Laravel Chatbot Platform Setup..."

# Update system packages
echo "📦 Updating system packages..."
sudo apt update && sudo apt upgrade -y

# Install system dependencies
echo "🔧 Installing system dependencies..."
sudo apt install -y software-properties-common curl wget unzip git supervisor nginx build-essential make gcc

# Install PHP 8.3 and extensions
echo "🐘 Installing PHP 8.3..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath php8.3-pgsql php8.3-intl php8.3-redis

# Install Composer
echo "🎼 Installing Composer..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Install Node.js and npm
echo "📦 Installing Node.js and npm..."
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install PostgreSQL
echo "🐘 Installing PostgreSQL..."
sudo apt install -y postgresql postgresql-contrib postgresql-server-dev-all

# Install pgvector extension
echo "🔍 Installing pgvector extension..."
cd /tmp
git clone --branch v0.5.1 https://github.com/pgvector/pgvector.git
cd pgvector
make
sudo make install

# Configure PostgreSQL
echo "🔧 Configuring PostgreSQL..."
sudo -u postgres psql -c "CREATE DATABASE chatbot_platform;"
sudo -u postgres psql -c "CREATE USER chatbot_user WITH PASSWORD 'chatbot_password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE chatbot_platform TO chatbot_user;"
sudo -u postgres psql -d chatbot_platform -c "CREATE EXTENSION vector;"

# Install Ollama
echo "🤖 Installing Ollama..."
curl -fsSL https://ollama.ai/install.sh | sh

# Start Ollama service
echo "🚀 Starting Ollama service..."
sudo systemctl enable ollama
sudo systemctl start ollama

# Wait for Ollama to be ready
echo "⏳ Waiting for Ollama to be ready..."
sleep 10

# Pull required models
echo "📥 Pulling Ollama models..."
ollama pull mistral:7b
ollama pull mistral:7b-embed

# Create Laravel project directory
echo "📁 Creating Laravel project directory..."
sudo mkdir -p /var/www/chatbot-platform
sudo chown -R $USER:$USER /var/www/chatbot-platform

# Install Laravel
echo "🎨 Installing Laravel..."
cd /var/www/chatbot-platform
composer create-project laravel/laravel . --prefer-dist

# Install additional Laravel packages
echo "📦 Installing additional packages..."
composer require spatie/pdf-to-text
composer require pgvector/pgvector-php

# Set proper permissions
echo "🔐 Setting proper permissions..."
sudo chown -R www-data:www-data /var/www/chatbot-platform
sudo chmod -R 755 /var/www/chatbot-platform
sudo chmod -R 775 /var/www/chatbot-platform/storage
sudo chmod -R 775 /var/www/chatbot-platform/bootstrap/cache

# Configure environment
echo "⚙️ Configuring environment..."
cp .env.example .env
php artisan key:generate

# Update .env file with database configuration
sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=pgsql/' .env
sed -i 's/DB_HOST=127.0.0.1/DB_HOST=localhost/' .env
sed -i 's/DB_PORT=3306/DB_PORT=5432/' .env
sed -i 's/DB_DATABASE=laravel/DB_DATABASE=chatbot_platform/' .env
sed -i 's/DB_USERNAME=root/DB_USERNAME=chatbot_user/' .env
sed -i 's/DB_PASSWORD=/DB_PASSWORD=chatbot_password/' .env

# Add Ollama configuration to .env
echo "" >> .env
echo "# Ollama Configuration" >> .env
echo "OLLAMA_BASE_URL=http://localhost:11434" >> .env
echo "OLLAMA_MODEL=mistral:7b" >> .env
echo "OLLAMA_EMBED_MODEL=mistral:7b-embed" >> .env

echo "✅ Setup completed successfully!"
echo ""
echo "📋 Next steps:"
echo "1. Navigate to /var/www/chatbot-platform"
echo "2. Run 'php artisan migrate' to create database tables"
echo "3. Configure Nginx (see nginx.conf)"
echo "4. Configure Supervisor (see supervisor.conf)"
echo "5. Start the application with 'php artisan serve'"
echo ""
echo "🌐 Ollama API will be available at: http://localhost:11434"
echo "🚀 Laravel app will be available at: http://your-server-ip"