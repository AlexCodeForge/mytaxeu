#!/bin/bash

# MyTaxEU Worker Health Monitor
# Continuously monitors worker health and logs issues

echo "🔍 MyTaxEU Worker Health Monitor"
echo "==============================="
echo "Started at: $(date)"
echo "Press Ctrl+C to stop monitoring"
echo ""

# Create monitoring log
MONITOR_LOG="/var/www/mytaxeu/storage/logs/worker-monitor.log"
mkdir -p "$(dirname "$MONITOR_LOG")"

# Function to log with timestamp
log_monitor() {
    local message="$1"
    local timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
    echo "$timestamp - $message" | tee -a "$MONITOR_LOG"
}

# Function to check worker health
check_worker_health() {
    local active_workers=$(ps aux | grep "queue:work" | grep -v grep | wc -l)

    if [ "$active_workers" -eq 0 ]; then
        log_monitor "❌ CRITICAL: No workers running!"
        return 1
    elif [ "$active_workers" -lt 1 ]; then
        log_monitor "⚠️ WARNING: Only $active_workers workers running (expected: 1+)"
        return 2
    else
        log_monitor "✅ OK: $active_workers workers running"
        return 0
    fi
}

# Function to check queue lengths
check_queue_status() {
    local default_queue=$(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:default 2>/dev/null || echo "ERROR")
    local emails_queue=$(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:emails 2>/dev/null || echo "ERROR")

    if [ "$default_queue" = "ERROR" ] || [ "$emails_queue" = "ERROR" ]; then
        log_monitor "❌ Redis connection error"
        return 1
    fi

    local total_jobs=$((default_queue + emails_queue))

    if [ "$total_jobs" -gt 100 ]; then
        log_monitor "⚠️ WARNING: High queue load - $total_jobs total jobs (default: $default_queue, emails: $emails_queue)"
    elif [ "$total_jobs" -gt 0 ]; then
        log_monitor "📊 Queue status: $total_jobs total jobs (default: $default_queue, emails: $emails_queue)"
    else
        log_monitor "✅ Queues empty"
    fi

    return 0
}

# Function to check for recent errors
check_recent_errors() {
    local error_count=$(tail -50 /var/www/mytaxeu/storage/logs/laravel.log 2>/dev/null | grep -c "ERROR\|CRITICAL\|Exception" || echo "0")

    if [ "$error_count" -gt 5 ]; then
        log_monitor "⚠️ WARNING: $error_count recent errors in Laravel log"
        tail -10 /var/www/mytaxeu/storage/logs/laravel.log 2>/dev/null | grep "ERROR\|Exception" | tail -3 | while read line; do
            log_monitor "  Recent error: $line"
        done
    elif [ "$error_count" -gt 0 ]; then
        log_monitor "ℹ️ $error_count recent errors in Laravel log"
    fi
}

# Function to check worker log health
check_worker_logs() {
    if [ -d "/var/www/mytaxeu/storage/logs/workers" ]; then
        local error_files=$(find /var/www/mytaxeu/storage/logs/workers -name "*errors.log" -type f -size +0c 2>/dev/null | wc -l)

        if [ "$error_files" -gt 0 ]; then
            log_monitor "⚠️ $error_files worker error logs have content"
            find /var/www/mytaxeu/storage/logs/workers -name "*errors.log" -type f -size +0c 2>/dev/null | while read error_file; do
                local last_error=$(tail -1 "$error_file" 2>/dev/null)
                if [ -n "$last_error" ]; then
                    log_monitor "  Error in $(basename "$error_file"): $last_error"
                fi
            done
        fi
    fi
}

# Function to check system resources
check_system_resources() {
    local memory_used=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100}')
    local disk_used=$(df /var/www/mytaxeu | tail -1 | awk '{print $5}' | sed 's/%//')

    if [ "$memory_used" -gt 90 ]; then
        log_monitor "⚠️ WARNING: High memory usage: ${memory_used}%"
    fi

    if [ "$disk_used" -gt 90 ]; then
        log_monitor "⚠️ WARNING: High disk usage: ${disk_used}%"
    fi
}

# Main monitoring loop
log_monitor "🚀 Starting worker health monitoring..."

while true; do
    echo "----------------------------------------"
    check_worker_health
    worker_status=$?

    check_queue_status
    check_recent_errors
    check_worker_logs
    check_system_resources

    # If no workers are running, try to restart them
    if [ "$worker_status" -eq 1 ]; then
        log_monitor "🔄 Attempting to restart workers..."
        /var/www/mytaxeu/start-workers-with-permissions.sh >> "$MONITOR_LOG" 2>&1
        sleep 10
    fi

    echo ""
    echo "Next check in 30 seconds... (Ctrl+C to stop)"
    sleep 30
done
