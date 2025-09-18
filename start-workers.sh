#!/bin/bash

# MyTaxEU Queue Workers Start Script
# This script ensures queue workers are running

echo "🚀 Starting MyTaxEU Queue Workers"
echo "================================="

# Kill any existing workers to prevent duplicates
echo "🛑 Stopping any existing workers..."
pkill -f "queue:work"
sleep 2

# Start workers for different queues
echo "▶️ Starting queue workers..."

# Start default workers (4 workers for 1-5MB files) - includes emails queue
for i in {1..4}; do
    nohup php /var/www/mytaxeu/artisan queue:work redis --queue=emails,default --sleep=3 --tries=3 --max-time=3600 --memory=2048 > /var/www/mytaxeu/storage/logs/worker-default-$i.log 2>&1 &
    echo "Started default worker $i (emails,default queues)"
done

# Start high-priority workers (2 workers for <1MB files)
for i in {1..2}; do
    nohup php /var/www/mytaxeu/artisan queue:work redis --queue=high-priority --sleep=1 --tries=3 --max-time=1800 --memory=2048 > /var/www/mytaxeu/storage/logs/worker-high-priority-$i.log 2>&1 &
    echo "Started high-priority worker $i"
done

# Start slow workers (2 workers for >5MB files)
for i in {1..2}; do
    nohup php /var/www/mytaxeu/artisan queue:work redis --queue=slow --sleep=3 --tries=3 --max-time=3600 --memory=2048 > /var/www/mytaxeu/storage/logs/worker-slow-$i.log 2>&1 &
    echo "Started slow worker $i"
done

sleep 2

# Check status
echo ""
echo "📊 Worker Status:"
ps aux | grep "queue:work" | grep -v grep | wc -l | awk '{print "Active workers: " $1}'

echo ""
echo "✅ Queue workers started successfully!"
echo ""
echo "📈 Worker Summary:"
echo "  • 4 default workers (files 1-5MB)"
echo "  • 2 high-priority workers (files <1MB)"
echo "  • 2 slow workers (files >5MB)"
echo ""
echo "🔍 Monitor workers with:"
echo "  ps aux | grep 'queue:work'"
echo "  tail -f storage/logs/worker*.log"


