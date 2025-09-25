<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\StreamingCsvTransformer;
use App\Services\RateService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Log\LogManager;

// Bootstrap Laravel components
$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);

// Setup filesystem
$app->singleton('files', function () {
    return new Filesystem();
});

// Setup basic logging (disable Laravel logging for standalone mode)
$app->singleton('log', function () {
    return new class {
        public function info($message, $context = []) {
            echo "INFO: $message\n";
        }
        public function error($message, $context = []) {
            echo "ERROR: $message\n";
        }
        public function warning($message, $context = []) {
            echo "WARNING: $message\n";
        }
    };
});

// Mock RateService for standalone testing
class MockRateService extends RateService
{
    public function getExchangeRatesForTransformer(): array
    {
        // Throw exception to trigger fallback to hardcoded rates
        throw new Exception("Database not available in test mode");
    }

    public function getVatRatesForTransformer(): array
    {
        // Throw exception to trigger fallback to hardcoded rates
        throw new Exception("Database not available in test mode");
    }
}

function main()
{
    try {
        echo "StreamingCsvTransformer Test Script\n";
        echo "===================================\n\n";

        // Input and output paths
        $inputPath = '/var/www/mytaxeu/docs/1025809020354.csv';
        $outputPath = '/var/www/mytaxeu/docs/jorditest/transformed_output.xlsx';

        if (!file_exists($inputPath)) {
            throw new Exception("Input file not found: {$inputPath}");
        }

        echo "Input file: {$inputPath}\n";
        echo "File size: " . number_format(filesize($inputPath) / 1024 / 1024, 2) . " MB\n";
        echo "Output file: {$outputPath}\n\n";

        // Initialize services
        $rateService = new MockRateService();
        $transformer = new StreamingCsvTransformer($rateService);

        echo "Starting transformation...\n";
        $startTime = microtime(true);

        // Run the transformation
        $transformer->transform($inputPath, $outputPath);

        $endTime = microtime(true);
        $processingTime = round($endTime - $startTime, 2);

        echo "\nTransformation completed successfully!\n";
        echo "Processing time: {$processingTime} seconds\n";
        echo "Output saved to: {$outputPath}\n";

        // Check output file
        if (file_exists($outputPath)) {
            $outputSize = number_format(filesize($outputPath) / 1024, 2);
            echo "Output file size: {$outputSize} KB\n";
        }

        echo "\nAll files now available in docs/jorditest/ folder:\n";
        echo "- Spanish tax forms (4 files)\n";
        echo "- Excel transformation output (1 file)\n";
        echo "- Total: 5 files\n";

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

// Run the test
main();
