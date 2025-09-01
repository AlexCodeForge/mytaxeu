#!/bin/bash

# MyTaxEU Queue Worker Management Script
# Usage: ./manage-workers.sh [start|stop|restart|status|logs]

ACTION=${1:-status}

case $ACTION in
        "start")
        echo "🚀 Starting MyTaxEU queue workers..."
        supervisorctl start mytaxeu-worker:*
        supervisorctl start mytaxeu-worker-high-priority:*
        echo "✅ Workers started"
        ;;

    "stop")
        echo "🛑 Stopping MyTaxEU queue workers..."
        supervisorctl stop mytaxeu-worker:*
        supervisorctl stop mytaxeu-worker-high-priority:*
        echo "✅ Workers stopped"
        ;;

    "restart")
        echo "🔄 Restarting MyTaxEU queue workers..."
        supervisorctl restart mytaxeu-worker:*
        supervisorctl restart mytaxeu-worker-high-priority:*
        echo "✅ Workers restarted"
        ;;

    "status")
        echo "📊 MyTaxEU Queue Worker Status:"
        echo "================================"
        supervisorctl status mytaxeu-worker:*
        supervisorctl status mytaxeu-worker-high-priority:*
        echo ""
        echo "📈 Queue Statistics:"
        php artisan queue:monitor redis --once 2>/dev/null || echo "Queue monitoring unavailable"
        ;;

    "logs")
        echo "📋 Recent worker logs:"
        echo "======================"
        tail -n 50 /var/www/mytaxeu/storage/logs/worker.log
        ;;

    "health")
        echo "🏥 System Health Check:"
        echo "======================="

        # Check if Redis is running
        if redis-cli ping &>/dev/null; then
            echo "✅ Redis: Running"
        else
            echo "❌ Redis: Not responding"
        fi

        # Check worker count
        RUNNING_WORKERS=$(supervisorctl status mytaxeu-worker:* | grep RUNNING | wc -l)
        echo "👷 Workers: $RUNNING_WORKERS running"

        # Check memory usage
        MEMORY_USAGE=$(free -m | awk 'NR==2{printf "%.1f%%", $3*100/$2}')
        echo "🧠 Memory: $MEMORY_USAGE used"

        # Check disk space
        DISK_USAGE=$(df -h /var/www/mytaxeu | awk 'NR==2{print $5}')
        echo "💾 Disk: $DISK_USAGE used"

        # Check queue depth
        QUEUE_DEPTH=$(redis-cli llen queues:default 2>/dev/null || echo "0")
        echo "📦 Queue depth: $QUEUE_DEPTH jobs"
        ;;

    "scale")
        WORKERS=${2:-4}
        echo "📈 Scaling to $WORKERS workers..."

        # Update supervisor config
        sed -i "s/numprocs=.*/numprocs=$WORKERS/" /etc/supervisor/conf.d/supervisor-queue-workers.conf
        supervisorctl reread
        supervisorctl update

        echo "✅ Scaled to $WORKERS workers"
        ;;

    *)
        echo "MyTaxEU Queue Worker Management"
        echo "==============================="
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  start    - Start all queue workers"
        echo "  stop     - Stop all queue workers"
        echo "  restart  - Restart all queue workers"
        echo "  status   - Show worker status and queue stats"
        echo "  logs     - Show recent worker logs"
        echo "  health   - Comprehensive system health check"
        echo "  scale N  - Scale to N workers"
        echo ""
        echo "Examples:"
        echo "  $0 status"
        echo "  $0 restart"
        echo "  $0 scale 8"
        ;;
esac
