<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing ProcessUploadJob handle method directly ===" . PHP_EOL;

try {
    echo "1. Creating job..." . PHP_EOL;
    $job = new App\Jobs\ProcessUploadJob(312);
    echo "   ✅ Job created" . PHP_EOL;

    echo "\n2. Resolving dependencies..." . PHP_EOL;
    $jobStatusService = app(App\Services\JobStatusService::class);
    $notificationService = app(App\Services\NotificationService::class);
    $usageMeteringService = app(App\Services\UsageMeteringService::class);
    echo "   ✅ Dependencies resolved" . PHP_EOL;

    echo "\n3. Setting up fake job context..." . PHP_EOL;
    // Create a mock job object for getJobId()
    $mockJob = new class {
        public function getJobId() { return 'test-job-id'; }
        public function getJobRecord() {
            return (object)['created_at' => time()];
        }
    };

    // Use reflection to set the job property
    $reflection = new ReflectionClass($job);
    $jobProperty = $reflection->getProperty('job');
    $jobProperty->setAccessible(true);
    $jobProperty->setValue($job, $mockJob);
    echo "   ✅ Mock job context set" . PHP_EOL;

    echo "\n4. Calling handle method directly..." . PHP_EOL;
    $job->handle($jobStatusService, $notificationService, $usageMeteringService);
    echo "   ✅ Handle method completed!" . PHP_EOL;

    echo "\n5. Checking upload status..." . PHP_EOL;
    $upload = App\Models\Upload::find(312);
    echo "   Status: " . $upload->status . PHP_EOL;
    echo "   Updated: " . $upload->updated_at . PHP_EOL;

    echo "\n6. Checking debug log..." . PHP_EOL;
    if (file_exists('/tmp/debug-job.log')) {
        echo "   Debug log contents:" . PHP_EOL;
        echo file_get_contents('/tmp/debug-job.log');
    } else {
        echo "   ❌ No debug log found" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Trace: " . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
}

echo "\n=== Direct Handle Test Complete ===" . PHP_EOL;
