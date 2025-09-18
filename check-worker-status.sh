#!/bin/bash

# Quick Worker Status Check for MyTaxEU

echo "🔍 Worker Status Check - $(date)"
echo "================================="

# Check active workers
echo "📊 Active Workers:"
active_count=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
echo "  Count: $active_count"

if [ "$active_count" -gt 0 ]; then
    ps aux | grep "queue:work" | grep -v grep | while read line; do
        echo "  $line"
    done
else
    echo "  ❌ No workers running!"
fi

echo ""

# Check queue lengths
echo "📋 Queue Status:"
if redis-cli -h 127.0.0.1 -p 6379 ping >/dev/null 2>&1; then
    default_len=$(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:default 2>/dev/null || echo "ERROR")
    emails_len=$(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:emails 2>/dev/null || echo "ERROR")
    high_len=$(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:high-priority 2>/dev/null || echo "ERROR")
    slow_len=$(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:slow 2>/dev/null || echo "ERROR")

    echo "  Default queue: $default_len jobs"
    echo "  Emails queue: $emails_len jobs"
    echo "  High-priority queue: $high_len jobs"
    echo "  Slow queue: $slow_len jobs"
else
    echo "  ❌ Redis not responding"
fi

echo ""

# Check recent worker logs
echo "📄 Recent Worker Activity:"
if [ -f "/var/www/mytaxeu/storage/logs/workers/worker-main-1.log" ]; then
    echo "  Main worker log (last 5 lines):"
    tail -5 /var/www/mytaxeu/storage/logs/workers/worker-main-1.log | sed 's/^/    /'
else
    echo "  ❌ No main worker log found"
fi

echo ""

# Check for errors
echo "⚠️ Recent Errors:"
if [ -f "/var/www/mytaxeu/storage/logs/workers/worker-main-1-errors.log" ]; then
    error_size=$(stat -c%s /var/www/mytaxeu/storage/logs/workers/worker-main-1-errors.log 2>/dev/null || echo "0")
    if [ "$error_size" -gt 0 ]; then
        echo "  Worker error log (last 3 lines):"
        tail -3 /var/www/mytaxeu/storage/logs/workers/worker-main-1-errors.log | sed 's/^/    /'
    else
        echo "  ✅ No worker errors"
    fi
else
    echo "  ❌ No worker error log found"
fi

# Check Laravel errors
laravel_errors=$(tail -20 /var/www/mytaxeu/storage/logs/laravel.log 2>/dev/null | grep -c "ERROR\|Exception" || echo "0")
if [ "$laravel_errors" -gt 0 ] 2>/dev/null; then
    echo "  Laravel log: $laravel_errors recent errors"
    echo "  Recent Laravel errors:"
    tail -20 /var/www/mytaxeu/storage/logs/laravel.log 2>/dev/null | grep "ERROR\|Exception" | tail -2 | sed 's/^/    /'
else
    echo "  ✅ No recent Laravel errors"
fi

echo ""
echo "✅ Status check complete"
