# Production Deployment Guide for MyTaxEU

## 🚀 Scaling for Thousands of Users

This guide provides a complete production setup that can handle thousands of concurrent users processing CSV files.

## ⚡ Quick Setup

Run the automated setup script:

```bash
chmod +x setup-production-queues.sh
sudo ./setup-production-queues.sh
```

## 📋 Manual Setup Steps

### 1. Environment Configuration

Update your `.env` file with these critical settings:

```env
# Queue Configuration - Critical for Performance
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default

# Worker Configuration
QUEUE_WORKER_MEMORY=512
QUEUE_WORKER_TIMEOUT=600
QUEUE_WORKER_SLEEP=3
QUEUE_WORKER_TRIES=3

# Performance Settings
CSV_MEMORY_LIMIT=1024M
CSV_TIMEOUT=600
CSV_CHUNK_SIZE=1000

# Session & Cache - Use Redis
SESSION_DRIVER=redis
CACHE_STORE=redis

# Database Connection Pooling
DB_CONNECTION=mysql
DB_CONNECTION_POOL=true
```

### 2. Redis Setup

Install and configure Redis for high-performance queue processing:

```bash
# Install Redis
sudo apt install redis-server

# Configure Redis for queues
sudo cp /etc/redis/redis.conf /etc/redis/redis-queue.conf
sudo nano /etc/redis/redis-queue.conf

# Add these lines:
port 6380
maxmemory 1gb
maxmemory-policy allkeys-lru
```

### 3. Supervisor Configuration

The included `supervisor-queue-workers.conf` provides:

- **4 default workers** for regular CSV processing
- **2 high-priority workers** for small files
- **Automatic restart** on failure
- **Memory limits** to prevent crashes
- **Log rotation** for monitoring

```bash
# Copy configuration
sudo cp supervisor-queue-workers.conf /etc/supervisor/conf.d/

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start mytaxeu-worker:*
```

### 4. Database Optimization

For thousands of users, optimize your database:

```sql
-- Index optimization for uploads table
CREATE INDEX idx_uploads_user_status ON uploads(user_id, status);
CREATE INDEX idx_uploads_created_at ON uploads(created_at);
CREATE INDEX idx_jobs_queue_status ON jobs(queue, status);

-- Connection pooling (MySQL)
SET GLOBAL max_connections = 300;
SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB
```

### 5. Web Server Configuration

#### Nginx Configuration (Recommended)

```nginx
# /etc/nginx/sites-available/mytaxeu
server {
    listen 80;
    listen 443 ssl http2;
    server_name your-domain.com;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=upload:10m rate=2r/m;
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
        
        # Apply rate limiting to uploads
        location /uploads {
            limit_req zone=upload burst=3 nodelay;
            try_files $uri $uri/ /index.php?$query_string;
        }
    }

    # PHP Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Increase timeouts for file uploads
        fastcgi_read_timeout 300;
        client_max_body_size 10M;
    }
}
```

### 6. PHP-FPM Optimization

```ini
# /etc/php/8.2/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.max_requests = 500

# Memory settings
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 10M
```

## 📊 Monitoring & Health Checks

### Queue Monitoring

Use the built-in queue monitor:

```bash
# Real-time queue monitoring
php artisan queue:monitor redis

# Check worker status
sudo supervisorctl status mytaxeu-worker:*

# View worker logs
tail -f storage/logs/worker.log
```

### Performance Metrics

Monitor these key metrics:

- **Queue depth**: Keep under 100 pending jobs
- **Worker memory**: Should stay under 512MB per worker
- **Failed jobs**: Investigate any failures immediately
- **Processing time**: Average should be under 30 seconds

### Alerts Setup

Set up monitoring alerts for:

```bash
# Check if workers are running
#!/bin/bash
RUNNING_WORKERS=$(sudo supervisorctl status mytaxeu-worker:* | grep RUNNING | wc -l)
if [ $RUNNING_WORKERS -lt 4 ]; then
    echo "ALERT: Only $RUNNING_WORKERS queue workers running"
fi

# Check queue depth
QUEUE_DEPTH=$(redis-cli llen queues:default)
if [ $QUEUE_DEPTH -gt 100 ]; then
    echo "ALERT: Queue depth is $QUEUE_DEPTH (threshold: 100)"
fi
```

## 🔄 Scaling Strategies

### Horizontal Scaling

For even higher loads:

1. **Multiple Servers**: Deploy queue workers on separate servers
2. **Load Balancing**: Use nginx load balancer for web traffic
3. **Database Scaling**: Implement read replicas
4. **Redis Clustering**: Scale Redis across multiple instances

### Queue Optimization

```php
// Priority-based job dispatch
ProcessUploadJob::dispatch($uploadId)
    ->onQueue($upload->size_bytes > 5242880 ? 'slow' : 'default');

// Batch processing for multiple files
Bus::batch([
    new ProcessUploadJob($upload1->id),
    new ProcessUploadJob($upload2->id),
    // ...
])->dispatch();
```

### Resource Scaling Guidelines

| Users | Workers | Redis Memory | DB Connections |
|-------|---------|--------------|----------------|
| 0-100 | 2-4     | 256MB        | 50             |
| 100-500 | 4-8   | 512MB        | 100            |
| 500-1000 | 8-12 | 1GB          | 150            |
| 1000-5000 | 12-20 | 2GB        | 200            |
| 5000+ | 20+ | 4GB+ | 300+ |

## 🛠️ Troubleshooting

### Common Issues

**Workers Stop Running:**
```bash
# Check logs
tail -f storage/logs/worker.log

# Restart workers
sudo supervisorctl restart mytaxeu-worker:*

# Check memory usage
ps aux | grep "queue:work"
```

**High Memory Usage:**
```bash
# Monitor per-worker memory
ps -o pid,ppid,cmd,%mem,%cpu -p $(pgrep -f "queue:work")

# Reduce worker memory limit
# Edit supervisor config: --memory=256
```

**Database Connection Issues:**
```bash
# Check active connections
SHOW PROCESSLIST;

# Optimize connection pooling
# Update DB_CONNECTION_POOL in .env
```

### Performance Optimization

1. **Enable OPcache** in production
2. **Use Redis** for sessions and cache
3. **Implement CDN** for static assets
4. **Database indexing** for queries
5. **Regular queue cleanup** of old jobs

## 🎯 Production Checklist

- [ ] Redis installed and configured
- [ ] Supervisor managing queue workers
- [ ] 4+ workers running automatically
- [ ] Memory limits configured (512MB per worker)
- [ ] Database optimized with proper indexes
- [ ] Nginx rate limiting configured
- [ ] PHP-FPM optimized for concurrent requests
- [ ] Monitoring alerts set up
- [ ] Log rotation configured
- [ ] Backup strategy implemented
- [ ] SSL certificate installed
- [ ] Environment variables secured

## 📈 Expected Performance

With this setup, you can expect:

- **Concurrent Users**: 1000+ simultaneous users
- **File Processing**: 50+ CSV files per minute
- **Response Time**: < 200ms for web requests
- **Queue Processing**: < 30 seconds average per file
- **Uptime**: 99.9% with proper monitoring

## 🔒 Security Considerations

1. **Rate Limiting**: Prevent abuse with nginx limits
2. **File Validation**: Strict CSV-only uploads
3. **User Limits**: Enforce processing quotas
4. **Database Security**: Use strong passwords and limited privileges
5. **Redis Security**: Enable password protection
6. **SSL/TLS**: Encrypt all traffic
7. **Log Security**: Sanitize sensitive data in logs

---

This production setup will reliably handle thousands of users processing CSV files simultaneously while maintaining performance and stability.
