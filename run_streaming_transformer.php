<?php

declare(strict_types=1);

// Bootstrap Laravel application
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\StreamingCsvTransformer;

try {
    // Define input and output paths
    $inputPath = __DIR__ . '/docs/transformer/input-output/input_real_enero.csv';
    $outputPath = __DIR__ . '/output_real_test.xlsx';

    // Check if input file exists
    if (!file_exists($inputPath)) {
        throw new Exception("Input file not found: {$inputPath}");
    }

    // Create output directory if it doesn't exist
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    echo "Starting CSV transformation...\n";
    echo "Input file: {$inputPath}\n";
    echo "Output file: {$outputPath}\n";
    echo "Input file size: " . round(filesize($inputPath) / 1024 / 1024, 2) . " MB\n\n";

    // Create transformer instance and run transformation
    $transformer = new StreamingCsvTransformer();

    $startTime = microtime(true);
    $transformer->transform($inputPath, $outputPath);
    $endTime = microtime(true);

    $processingTime = round($endTime - $startTime, 2);
    $outputSize = file_exists($outputPath) ? round(filesize($outputPath) / 1024 / 1024, 2) : 0;

    echo "\n✅ Transformation completed successfully!\n";
    echo "Processing time: {$processingTime} seconds\n";
    echo "Output file size: {$outputSize} MB\n";
    echo "Output saved to: {$outputPath}\n";

} catch (Exception $e) {
    echo "\n❌ Error during transformation:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

