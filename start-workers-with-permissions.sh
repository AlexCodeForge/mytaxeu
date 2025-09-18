#!/bin/bash

# MyTaxEU Enhanced Worker Management Script with Full Diagnostics
# This script fixes permissions, starts workers, and provides comprehensive logging

echo "🚀 MyTaxEU Enhanced Worker Diagnostics & Setup"
echo "=============================================="
echo "Started at: $(date)"
echo ""

# Function to log with timestamp
log_with_timestamp() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1"
}

# Function to check system resources
check_system_status() {
    log_with_timestamp "🔍 System Status Check:"
    echo "Memory usage: $(free -h | grep 'Mem:' | awk '{print $3 "/" $2}')"
    echo "Disk usage: $(df -h /var/www/mytaxeu | tail -1 | awk '{print $5 " used"}')"
    echo "Load average: $(uptime | awk -F'load average:' '{print $2}')"
    echo ""
}

# Function to check database connectivity
check_database() {
    log_with_timestamp "🗄️ Database Connectivity Check:"
    if [ -f "/var/www/mytaxeu/database/database.sqlite" ]; then
        echo "✅ SQLite database exists"
        echo "Database size: $(du -h /var/www/mytaxeu/database/database.sqlite | awk '{print $1}')"
        echo "Database permissions: $(ls -la /var/www/mytaxeu/database/database.sqlite)"

        # Test database connection
        cd /var/www/mytaxeu
        if php artisan tinker --execute="echo 'DB connection test: '; DB::connection()->getPdo(); echo 'SUCCESS';" 2>/dev/null | grep -q "SUCCESS"; then
            echo "✅ Database connection successful"
        else
            echo "❌ Database connection failed"
        fi
    else
        echo "❌ SQLite database not found"
    fi
    echo ""
}

# Function to check Redis connectivity
check_redis() {
    log_with_timestamp "🔴 Redis Connectivity Check:"
    if redis-cli -h 127.0.0.1 -p 6379 ping >/dev/null 2>&1; then
        echo "✅ Redis is responding"
        echo "Default queue length: $(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:default 2>/dev/null || echo 'N/A')"
        echo "Emails queue length: $(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:emails 2>/dev/null || echo 'N/A')"
        echo "High-priority queue length: $(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:high-priority 2>/dev/null || echo 'N/A')"
        echo "Slow queue length: $(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:slow 2>/dev/null || echo 'N/A')"
    else
        echo "❌ Redis is not responding"
    fi
    echo ""
}

# Function to check failed jobs
check_failed_jobs() {
    log_with_timestamp "❌ Failed Jobs Check:"
    cd /var/www/mytaxeu
    local failed_count=$(php artisan queue:failed | grep -c "^|" || echo "0")
    if [ "$failed_count" -gt 2 ] 2>/dev/null; then  # More than just header rows
        echo "⚠️ Found $((failed_count - 2)) failed jobs"
        php artisan queue:failed | head -10
    else
        echo "✅ No failed jobs found"
    fi
    echo ""
}

# Run comprehensive diagnostics
check_system_status
check_database
check_redis
check_failed_jobs

# First, fix all permissions
log_with_timestamp "🔧 Step 1: Fixing permissions..."
/var/www/mytaxeu/fix-permissions.sh

echo ""
log_with_timestamp "🔄 Step 2: Stopping existing workers..."

# Kill any existing workers to prevent duplicates
echo "🛑 Stopping any existing workers..."
pkill -f "queue:work"
sleep 3

# Check if any workers are still running
remaining_workers=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
if [ "$remaining_workers" -gt 0 ]; then
    log_with_timestamp "⚠️ Warning: $remaining_workers workers still running after graceful shutdown"
    echo "Force killing remaining workers..."
    pkill -9 -f "queue:work"
    sleep 2
fi

log_with_timestamp "🚀 Step 3: Starting enhanced workers with full logging..."

# Create log directory if it doesn't exist
mkdir -p /var/www/mytaxeu/storage/logs/workers

# Start workers with enhanced logging and error handling
echo "▶️ Starting queue workers with verbose logging..."

# Enhanced worker startup function
start_worker() {
    local queue_name="$1"
    local worker_id="$2"
    local queue_list="$3"
    local sleep_time="$4"
    local memory_limit="$5"
    local max_time="$6"

    local log_file="/var/www/mytaxeu/storage/logs/workers/worker-${queue_name}-${worker_id}.log"
    local error_log="/var/www/mytaxeu/storage/logs/workers/worker-${queue_name}-${worker_id}-errors.log"

    # Create enhanced startup command with full logging
    local cmd="php /var/www/mytaxeu/artisan queue:work redis --queue=${queue_list} --sleep=${sleep_time} --tries=3 --max-time=${max_time} --memory=${memory_limit} --verbose --timeout=300"

    log_with_timestamp "Starting ${queue_name} worker ${worker_id}: ${cmd}"

    # Start worker with comprehensive logging
    nohup bash -c "
        exec > >(tee -a '$log_file')
        exec 2> >(tee -a '$error_log' >&2)
        echo '$(date): Starting worker process'
        echo 'Command: $cmd'
        echo 'PID: \$\$'
        echo '===================='
        $cmd
    " &

    local worker_pid=$!
    echo "Started ${queue_name} worker ${worker_id} with PID: ${worker_pid}"
    echo "${worker_pid}" > "/var/www/mytaxeu/storage/logs/workers/worker-${queue_name}-${worker_id}.pid"
}

# Start 1 main worker for emails and default queue (prevent SQLite lock conflicts)
start_worker "main" "1" "emails,default" "3" "2048" "3600"

sleep 3

# Check if workers started successfully
active_workers=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
log_with_timestamp "✅ Worker startup complete. Active workers: $active_workers"

if [ "$active_workers" -eq 0 ]; then
    log_with_timestamp "❌ ERROR: No workers started successfully!"
    echo "Checking for startup errors..."
    find /var/www/mytaxeu/storage/logs/workers/ -name "*errors.log" -type f -exec echo "=== {} ===" \; -exec cat {} \; 2>/dev/null
    exit 1
fi

# Final status report
echo ""
log_with_timestamp "📊 Final Worker Status Report:"
echo "Active workers: $active_workers"
ps aux | grep "queue:work" | grep -v grep | while read line; do
    echo "  $line"
done

echo ""
log_with_timestamp "📁 Log Files Created:"
find /var/www/mytaxeu/storage/logs/workers/ -type f -exec echo "  {}" \;

echo ""
log_with_timestamp "🔍 Monitoring Commands:"
echo "  • Watch all workers: watch 'ps aux | grep queue:work | grep -v grep'"
echo "  • Monitor main worker: tail -f /var/www/mytaxeu/storage/logs/workers/worker-main-1.log"
echo "  • Check errors: tail -f /var/www/mytaxeu/storage/logs/workers/worker-main-1-errors.log"
echo "  • Queue status: redis-cli -h 127.0.0.1 -p 6379 LLEN queues:default"
echo "  • Failed jobs: php artisan queue:failed"
echo ""
log_with_timestamp "✅ Enhanced worker setup complete!"

# Show recent Laravel logs for immediate troubleshooting
echo ""
log_with_timestamp "📋 Recent Laravel Log Entries (last 10):"
tail -10 /var/www/mytaxeu/storage/logs/laravel.log 2>/dev/null || echo "No Laravel log found"
