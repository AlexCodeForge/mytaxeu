<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing ProcessUploadJob for Upload 312 ===" . PHP_EOL;

try {
    echo "1. Checking upload exists..." . PHP_EOL;
    $upload = App\Models\Upload::find(312);
    if (!$upload) {
        echo "ERROR: Upload 312 not found!" . PHP_EOL;
        exit(1);
    }
    echo "   ✅ Upload found: " . $upload->original_name . PHP_EOL;
    echo "   ✅ Status: " . $upload->status . PHP_EOL;
    echo "   ✅ Path: " . $upload->path . PHP_EOL;

    echo "\n2. Checking file exists..." . PHP_EOL;
    $exists = Storage::disk($upload->disk)->exists($upload->path);
    echo "   " . ($exists ? "✅" : "❌") . " File exists: " . ($exists ? "Yes" : "No") . PHP_EOL;

    echo "\n3. Creating ProcessUploadJob..." . PHP_EOL;
    $job = new App\Jobs\ProcessUploadJob(312);
    echo "   ✅ Job created successfully" . PHP_EOL;

    echo "\n4. Testing job dependencies..." . PHP_EOL;
    $streamingTransformer = app(App\Services\StreamingCsvTransformer::class);
    echo "   ✅ StreamingCsvTransformer resolved" . PHP_EOL;

    $jobStatusService = app(App\Services\JobStatusService::class);
    echo "   ✅ JobStatusService resolved" . PHP_EOL;

    echo "\n5. Dispatching job..." . PHP_EOL;
    dispatch($job);
    echo "   ✅ Job dispatched successfully!" . PHP_EOL;

    echo "\n6. Processing job immediately..." . PHP_EOL;
    $exitCode = 0;
    exec('php artisan queue:work redis --once --verbose 2>&1', $output, $exitCode);
    echo "   Queue work exit code: " . $exitCode . PHP_EOL;
    echo "   Queue work output: " . implode("\n   ", $output) . PHP_EOL;

    echo "\n7. Checking upload status after processing..." . PHP_EOL;
    $upload->refresh();
    echo "   Status: " . $upload->status . PHP_EOL;
    echo "   Updated: " . $upload->updated_at . PHP_EOL;

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
}

echo "\n=== Test Complete ===" . PHP_EOL;
