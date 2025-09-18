# MyTaxEU Worker Diagnostics & Resolution Summary

## 🔍 Issue Analysis

**Problem**: Workers were getting stuck and not processing jobs properly.

**Root Causes Identified**:
1. **Multiple Worker Management Systems**: Both systemd service AND cron jobs were managing workers
2. **Conflicting Worker Counts**: Original config expected 8 workers, causing SQLite contention
3. **Limited Logging**: Minimal debugging information available
4. **No Health Monitoring**: No automated detection of worker issues

## ✅ Solutions Implemented

### 1. Enhanced Worker Management Script
- **File**: `/var/www/mytaxeu/start-workers-with-permissions.sh`
- **Features**:
  - Comprehensive system diagnostics (memory, disk, database, Redis)
  - Single worker configuration to prevent SQLite lock conflicts
  - Enhanced logging with timestamps and detailed output
  - Automatic error detection and reporting
  - PID tracking for each worker

### 2. Worker Health Monitoring
- **File**: `/var/www/mytaxeu/monitor-workers.sh`
- **Features**:
  - Continuous health monitoring (30-second intervals)
  - Automatic worker restart if failures detected
  - Queue length monitoring
  - Error log analysis
  - System resource monitoring

### 3. Quick Status Checker
- **File**: `/var/www/mytaxeu/check-worker-status.sh`
- **Features**:
  - Instant worker status overview
  - Queue statistics
  - Recent error detection
  - Process details

### 4. Updated System Integration
- **Systemd Service**: Updated to use enhanced worker script
- **Cron Jobs**: Adjusted to expect 2 workers instead of 8
- **Log Management**: Centralized logging in `/var/www/mytaxeu/storage/logs/workers/`

## 📊 Current Configuration

### Active Workers
- **Count**: 2 workers (optimized for SQLite)
- **Queues**: `emails,default`
- **Memory Limit**: 2048MB per worker
- **Max Time**: 3600 seconds (1 hour)
- **Sleep**: 3 seconds between jobs
- **Tries**: 3 attempts per job
- **Timeout**: 300 seconds per job

### Monitoring & Logs
- **Main Worker Log**: `/var/www/mytaxeu/storage/logs/workers/worker-main-1.log`
- **Error Log**: `/var/www/mytaxeu/storage/logs/workers/worker-main-1-errors.log`
- **PID File**: `/var/www/mytaxeu/storage/logs/workers/worker-main-1.pid`
- **Monitor Log**: `/var/www/mytaxeu/storage/logs/worker-monitor.log`

## 🔧 Management Commands

### Start Workers
```bash
/var/www/mytaxeu/start-workers-with-permissions.sh
```

### Check Status
```bash
/var/www/mytaxeu/check-worker-status.sh
```

### Monitor Health (continuous)
```bash
/var/www/mytaxeu/monitor-workers.sh
```

### Systemd Management
```bash
# Check service status
systemctl status mytaxeu-queue-workers.service

# Restart service
systemctl restart mytaxeu-queue-workers.service

# View service logs
journalctl -u mytaxeu-queue-workers.service -f
```

### Manual Worker Management
```bash
# Check active workers
ps aux | grep "queue:work" | grep -v grep

# Kill all workers
pkill -f "queue:work"

# Check queue lengths
redis-cli -h 127.0.0.1 -p 6379 LLEN queues:default
redis-cli -h 127.0.0.1 -p 6379 LLEN queues:emails

# Check failed jobs
php artisan queue:failed
```

## 📈 Monitoring Dashboard

### Real-time Monitoring
```bash
# Watch worker processes
watch 'ps aux | grep queue:work | grep -v grep'

# Monitor main worker log
tail -f /var/www/mytaxeu/storage/logs/workers/worker-main-1.log

# Monitor errors
tail -f /var/www/mytaxeu/storage/logs/workers/worker-main-1-errors.log

# Watch Laravel logs
tail -f /var/www/mytaxeu/storage/logs/laravel.log | grep -E "ERROR|Exception|ProcessUploadJob"
```

### Queue Statistics
```bash
# Check all queue lengths
for queue in default emails high-priority slow; do
  echo "$queue: $(redis-cli -h 127.0.0.1 -p 6379 LLEN queues:$queue)"
done
```

## 🚨 Troubleshooting

### If Workers Stop
1. Check systemd service: `systemctl status mytaxeu-queue-workers.service`
2. Check for errors: `cat /var/www/mytaxeu/storage/logs/workers/worker-main-1-errors.log`
3. Restart service: `systemctl restart mytaxeu-queue-workers.service`
4. Run diagnostics: `/var/www/mytaxeu/start-workers-with-permissions.sh`

### If Jobs Fail
1. Check failed jobs: `php artisan queue:failed`
2. Check Laravel logs: `tail -50 /var/www/mytaxeu/storage/logs/laravel.log | grep ERROR`
3. Check database locks: Look for "database is locked" errors
4. Check system resources: `free -h` and `df -h`

### If High Memory Usage
1. Check worker memory: `ps aux | grep queue:work`
2. Restart workers to clear memory: `systemctl restart mytaxeu-queue-workers.service`
3. Consider reducing memory limit in worker config

## 📝 Automated Maintenance

### Cron Jobs
- **Daily restart**: 3:00 AM - Full worker restart with permission fixes
- **Health check**: Every 15 minutes - Restart if less than 2 workers running
- **Laravel scheduler**: Every minute - Run scheduled tasks

### Log Rotation
- Worker logs are automatically rotated by system logrotate
- Laravel logs should be monitored for size
- Consider implementing log cleanup for worker logs

## ✅ Current Status
- **Workers**: 2 active workers running smoothly
- **Queues**: All queues empty and processing correctly
- **Errors**: No recent errors detected
- **System**: All diagnostics passing
- **Monitoring**: Enhanced logging and monitoring in place

## 🎯 Next Steps
1. Monitor worker performance over the next 24 hours
2. Set up alerts for worker failures (optional)
3. Consider implementing Laravel Horizon for advanced queue management (optional)
4. Regular review of worker logs to identify patterns

---
*Generated on: $(date)*
*Worker Diagnostics Complete ✅*
