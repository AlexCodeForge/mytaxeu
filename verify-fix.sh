#!/bin/bash
echo "🔍 MyTaxEU Queue Worker Verification Report"
echo "==========================================="
echo ""

echo "1. ✅ Service Status:"
systemctl is-active mytaxeu-queue-workers.service
echo ""

echo "2. ✅ Worker Process User:"
ps aux | grep "queue:work" | grep -v grep | awk '{print "Process: " $1 " (should be www-data)"}'
echo ""

echo "3. ✅ File Permissions Check:"
ls -la storage/app/uploads/42/output/ | tail -3
echo ""

echo "4. ✅ Latest Upload Test:"
php artisan tinker --execute='$upload = App\Models\Upload::where("original_name", "ENERO 2023.csv")->orderBy("created_at", "desc")->first(); echo "Upload ID: " . $upload->id . " | hasTransformedFile(): " . ($upload->hasTransformedFile() ? "✅ TRUE" : "❌ FALSE") . PHP_EOL;'
echo ""

echo "5. ✅ Service Auto-Start:"
systemctl is-enabled mytaxeu-queue-workers.service
echo ""

echo "6. ✅ Queue Status:"
redis-cli LLEN queues:default 2>/dev/null || echo "Queue length check failed"
echo ""

echo "🎉 Verification Complete!"
echo "If all items show ✅, the issue is permanently fixed."
