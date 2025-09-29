# Laravel Chatbot Platform with RAG

A powerful Laravel-based chatbot platform that uses Retrieval-Augmented Generation (RAG) with PostgreSQL pgvector for semantic search and Ollama for AI inference.

## Features

- 🤖 **Multiple Chatbots**: Create and manage multiple chatbots with individual knowledge bases
- 📄 **Document Processing**: Upload PDF and text files with automatic chunking and embedding generation
- 🔍 **Semantic Search**: Use pgvector for fast similarity search across document embeddings
- 🧠 **RAG Integration**: Combine retrieved context with AI generation using Ollama
- 🚀 **RESTful API**: Complete REST API for all operations
- 📊 **Analytics**: Document statistics and chatbot performance metrics

## Requirements

- Ubuntu 22.04 LTS
- PHP 8.3+
- Composer
- PostgreSQL 15+ with pgvector extension
- Node.js 18+
- Nginx
- Supervisor
- Ollama with mistral:7b and mistral:7b-embed models

## Quick Setup

1. **Run the setup script** (Ubuntu 22.04):
   ```bash
   chmod +x setup.sh
   sudo ./setup.sh
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run migrations**:
   ```bash
   php artisan migrate
   ```

4. **Configure Nginx**:
   ```bash
   sudo cp nginx.conf /etc/nginx/sites-available/chatbot-platform
   sudo ln -s /etc/nginx/sites-available/chatbot-platform /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl reload nginx
   ```

5. **Configure Supervisor**:
   ```bash
   sudo cp supervisor.conf /etc/supervisor/conf.d/chatbot-platform.conf
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start chatbot-platform:*
   ```

## Manual Installation

### 1. System Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.3
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-mysql \
    php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath \
    php8.3-pgsql php8.3-intl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install PostgreSQL and pgvector
sudo apt install -y postgresql postgresql-contrib postgresql-server-dev-15
sudo -u postgres psql -c "CREATE EXTENSION IF NOT EXISTS vector;"

# Install Nginx and Supervisor
sudo apt install -y nginx supervisor

# Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh
```

### 2. Database Setup

```bash
sudo -u postgres createuser --interactive chatbot_user
sudo -u postgres createdb chatbot_platform -O chatbot_user
sudo -u postgres psql -c "ALTER USER chatbot_user PASSWORD 'secure_password';"
```

### 3. Ollama Models

```bash
ollama pull mistral:7b
ollama pull mistral:7b-embed
```

### 4. Laravel Setup

```bash
# Install dependencies
composer install

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate
```

## API Documentation

### Base URL
```
http://your-domain.com/api
```

### Authentication
Currently, the API doesn't require authentication, but you can add Laravel Sanctum for token-based auth.

### Endpoints

#### Health Check
```http
GET /api/health
```

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-01-01T12:00:00.000000Z",
  "version": "1.0.0"
}
```

#### Chatbots

##### List Chatbots
```http
GET /api/chatbots
```

##### Create Chatbot
```http
POST /api/chatbots
Content-Type: application/json

{
  "name": "Customer Support Bot",
  "description": "Handles customer inquiries",
  "is_active": true,
  "settings": {
    "temperature": 0.7,
    "max_tokens": 500
  }
}
```

##### Get Chatbot
```http
GET /api/chatbots/{id}
```

##### Update Chatbot
```http
PUT /api/chatbots/{id}
Content-Type: application/json

{
  "name": "Updated Bot Name",
  "is_active": false
}
```

##### Delete Chatbot
```http
DELETE /api/chatbots/{id}
```

#### Documents

##### Upload Document
```http
POST /api/chatbots/{chatbot_id}/documents/upload
Content-Type: multipart/form-data

file: [PDF or TXT file]
```

**Response:**
```json
{
  "success": true,
  "message": "Document processed successfully",
  "data": {
    "filename": "document.pdf",
    "chunks_created": 15,
    "total_tokens": 3420,
    "processing_time": "2.34s"
  }
}
```

##### List Documents
```http
GET /api/chatbots/{chatbot_id}/documents
```

##### Get Document Statistics
```http
GET /api/chatbots/{chatbot_id}/documents/stats
```

##### Delete All Documents
```http
DELETE /api/chatbots/{chatbot_id}/documents
```

#### Query

##### Query Chatbot
```http
POST /api/chatbots/{chatbot_id}/query
Content-Type: application/json

{
  "message": "What is the refund policy?",
  "limit": 3,
  "threshold": 0.7
}
```

**Response:**
```json
{
  "success": true,
  "message": "Query processed successfully",
  "data": {
    "response": "Based on the provided information, our refund policy allows...",
    "context_used": [
      "Refund policy text chunk 1...",
      "Refund policy text chunk 2..."
    ],
    "similarity_scores": [0.89, 0.76],
    "query": "What is the refund policy?",
    "chatbot_id": 1
  }
}
```

##### Get Chatbot Status
```http
GET /api/chatbots/{chatbot_id}/query/status
```

## Configuration

### Environment Variables

Key environment variables in `.env`:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=chatbot_platform
DB_USERNAME=chatbot_user
DB_PASSWORD=secure_password

# Ollama
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_CHAT_MODEL=mistral:7b
OLLAMA_EMBED_MODEL=mistral:7b-embed

# File Upload
MAX_FILE_SIZE=10240
ALLOWED_FILE_TYPES=pdf,txt
CHUNK_SIZE=1000
```

### Ollama Configuration

Ensure Ollama is running and models are available:

```bash
# Check Ollama status
curl http://localhost:11434/api/tags

# Test embedding generation
curl http://localhost:11434/api/embeddings \
  -d '{"model": "mistral:7b-embed", "prompt": "test"}'
```

## Performance Tuning

### PostgreSQL

Add to `/etc/postgresql/15/main/postgresql.conf`:

```conf
# Memory settings
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 4MB

# pgvector settings
shared_preload_libraries = 'vector'
```

### PHP-FPM

Adjust `/etc/php/8.3/fpm/pool.d/www.conf`:

```conf
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

## Monitoring

### Logs

- **Nginx**: `/var/log/nginx/chatbot_access.log`, `/var/log/nginx/chatbot_error.log`
- **Laravel**: `storage/logs/laravel.log`
- **Supervisor**: `/var/log/supervisor/chatbot-worker.log`

### Health Checks

```bash
# Check services
sudo systemctl status nginx
sudo systemctl status postgresql
sudo systemctl status php8.3-fpm
sudo supervisorctl status

# Check Ollama
curl http://localhost:11434/api/tags

# Check API
curl http://localhost/api/health
```

## Troubleshooting

### Common Issues

1. **Ollama not responding**:
   ```bash
   sudo systemctl restart ollama
   ollama serve
   ```

2. **Permission errors**:
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

3. **Database connection issues**:
   ```bash
   sudo -u postgres psql -c "SELECT version();"
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

4. **Large file uploads failing**:
   - Check `upload_max_filesize` and `post_max_size` in PHP config
   - Verify `client_max_body_size` in Nginx config

### Performance Issues

1. **Slow queries**: Add database indexes
2. **High memory usage**: Reduce chunk size or batch processing
3. **Slow embeddings**: Consider using a faster embedding model

## Development

### Running Tests

```bash
php artisan test
```

### Queue Workers (Development)

```bash
php artisan queue:work
```

### Debugging

Enable debug mode in `.env`:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## Security Considerations

- Enable HTTPS in production
- Add rate limiting for API endpoints
- Implement proper authentication (Laravel Sanctum)
- Regularly update dependencies
- Monitor for suspicious file uploads
- Use environment variables for sensitive data

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Support

For issues and questions:
- Check the troubleshooting section
- Review logs for error details
- Create an issue in the repository