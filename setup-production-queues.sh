#!/bin/bash

# Production Queue Setup Script for MyTaxEU
# This script sets up a robust queue system for handling thousands of users

echo "🚀 Setting up production queue system for MyTaxEU..."

# 1. Install Redis if not already installed
if ! command -v redis-server &> /dev/null; then
    echo "📦 Installing Redis..."
    apt update
    apt install -y redis-server
    systemctl enable redis-server
    systemctl start redis-server
else
    echo "✅ Redis already installed"
fi

# 2. Install Supervisor if not already installed
if ! command -v supervisord &> /dev/null; then
    echo "📦 Installing Supervisor..."
    apt install -y supervisor
    systemctl enable supervisor
    systemctl start supervisor
else
    echo "✅ Supervisor already installed"
fi

# 3. Configure Redis for production
echo "⚙️ Configuring Redis for production..."
tee /etc/redis/redis-queue.conf > /dev/null <<EOF
# Redis configuration for queue processing
port 6380
bind 127.0.0.1
protected-mode yes
tcp-keepalive 300
timeout 0
tcp-backlog 511
databases 16
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename dump-queue.rdb
dir /var/lib/redis/
maxmemory 1gb
maxmemory-policy allkeys-lru
EOF

# 4. Start Redis queue instance
systemctl start redis-server@redis-queue

# 5. Copy Supervisor configuration
echo "📋 Setting up Supervisor configuration..."
cp /var/www/mytaxeu/supervisor-queue-workers.conf /etc/supervisor/conf.d/

# 6. Create log directories
mkdir -p /var/www/mytaxeu/storage/logs
chown -R www-data:www-data /var/www/mytaxeu/storage/logs

# 7. Update Supervisor
supervisorctl reread
supervisorctl update

# 8. Start workers
echo "🏃 Starting queue workers..."
supervisorctl start mytaxeu-worker:*
supervisorctl start mytaxeu-worker-high-priority:*

# 9. Show status
echo "📊 Queue worker status:"
supervisorctl status mytaxeu-worker:*
supervisorctl status mytaxeu-worker-high-priority:*

echo ""
echo "✅ Production queue system setup complete!"
echo ""
echo "📋 Next steps:"
echo "1. Update your .env file with Redis configuration:"
echo "   QUEUE_CONNECTION=redis"
echo "   REDIS_QUEUE_CONNECTION=default"
echo ""
echo "2. Monitor workers with:"
echo "   sudo supervisorctl status"
echo ""
echo "3. View worker logs:"
echo "   tail -f /var/www/mytaxeu/storage/logs/worker.log"
echo ""
echo "4. Test queue processing:"
echo "   php artisan queue:monitor redis"
echo ""

# 10. Set up log rotation
echo "📜 Setting up log rotation..."
tee /etc/logrotate.d/mytaxeu-workers > /dev/null <<EOF
/var/www/mytaxeu/storage/logs/worker*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        supervisorctl restart mytaxeu-worker:*
        supervisorctl restart mytaxeu-worker-high-priority:*
    endscript
}
EOF

echo "🔄 Log rotation configured"
echo ""
echo "🎯 Your system is now ready to handle thousands of concurrent users!"
