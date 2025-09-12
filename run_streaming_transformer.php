<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use App\Services\StreamingCsvTransformer;

try {
    echo "Starting CSV transformation...\n";
    echo "=================================\n";

    // Define input and output paths
    $inputPath = __DIR__ . '/docs/transformer/input-output/input_real_enero.csv';
    $outputPath = __DIR__ . '/docs/transformer/input-output/output_real_test.xlsx';

    // Check if input file exists
    if (!file_exists($inputPath)) {
        throw new Exception("Input file not found: {$inputPath}");
    }

    // Ensure output directory exists
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    echo "Input file: {$inputPath}\n";
    echo "Output file: {$outputPath}\n";
    echo "Input file size: " . number_format(filesize($inputPath) / 1024 / 1024, 2) . " MB\n";
    echo "\n";

    // Create transformer instance and run transformation
    $transformer = new StreamingCsvTransformer();

    $startTime = microtime(true);
    $transformer->transform($inputPath, $outputPath);
    $endTime = microtime(true);

    echo "\n=================================\n";
    echo "Transformation completed successfully!\n";
    echo "Processing time: " . round($endTime - $startTime, 2) . " seconds\n";
    echo "Output saved to: {$outputPath}\n";

    if (file_exists($outputPath)) {
        echo "Output file size: " . number_format(filesize($outputPath) / 1024 / 1024, 2) . " MB\n";
    }

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
