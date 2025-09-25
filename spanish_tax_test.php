<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\SpanishTaxForms\SpanishTaxFormsService;
use App\Services\SpanishTaxForms\Config\SpanishTaxConfig;
use App\Services\SpanishTaxForms\Generators\Form349Generator;
use App\Services\SpanishTaxForms\Generators\Form369Generator;
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

// Setup logging
$app->singleton('log', function () {
    return new LogManager(Container::getInstance());
});

// Mock database for RateService (simplified for testing)
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
], 'default');
$capsule->setEventDispatcher(new Dispatcher($app));
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Create mock tables and data for RateService
try {
    Capsule::schema()->create('exchange_rates', function ($table) {
        $table->id();
        $table->string('from_currency');
        $table->string('to_currency');
        $table->decimal('rate', 10, 6);
        $table->date('effective_date');
        $table->timestamps();
    });

    Capsule::schema()->create('oss_vat_rates', function ($table) {
        $table->id();
        $table->string('country_code');
        $table->decimal('standard_rate', 5, 4);
        $table->date('effective_date');
        $table->timestamps();
    });

    // Insert sample exchange rates
    Capsule::table('exchange_rates')->insert([
        ['from_currency' => 'SEK', 'to_currency' => 'EUR', 'rate' => 0.095, 'effective_date' => '2023-01-01'],
        ['from_currency' => 'USD', 'to_currency' => 'EUR', 'rate' => 0.85, 'effective_date' => '2023-01-01'],
    ]);

    // Insert sample VAT rates
    Capsule::table('oss_vat_rates')->insert([
        ['country_code' => 'DE', 'standard_rate' => 0.19, 'effective_date' => '2023-01-01'],
        ['country_code' => 'FR', 'standard_rate' => 0.20, 'effective_date' => '2023-01-01'],
        ['country_code' => 'IT', 'standard_rate' => 0.22, 'effective_date' => '2023-01-01'],
        ['country_code' => 'SE', 'standard_rate' => 0.25, 'effective_date' => '2023-01-01'],
        ['country_code' => 'NL', 'standard_rate' => 0.21, 'effective_date' => '2023-01-01'],
    ]);

} catch (Exception $e) {
    echo "Database setup error: " . $e->getMessage() . "\n";
}

function main(): void
{
    echo "Spanish Tax Forms Test Script\n";
    echo "============================\n\n";

    try {
        // Configuration for Spanish tax forms (example company)
        $baseConfig = new SpanishTaxConfig(
            declarantNif: 'B54166277',
            companyName: 'INNOVTEC E-COMMERCE SL',
            addressLine1: 'CALLE EJEMPLO 123',
            addressLine2: 'PISO 2 PUERTA A',
            postalCode: '03801',
            city: 'ALCOI',
            province: 'ALICANTE',
            iossNumber: 'IM1234567890'
        );

        echo "Configuration created:\n";
        echo "- Company: {$baseConfig->companyName}\n";
        echo "- NIF: {$baseConfig->declarantNif}\n";
        echo "- Address: {$baseConfig->addressLine1}, {$baseConfig->city}\n\n";

        // Initialize services
        $rateService = new RateService();
        $form349Generator = new Form349Generator();
        $form369Generator = new Form369Generator();

        $spanishTaxService = new SpanishTaxFormsService(
            $rateService,
            $form349Generator,
            $form369Generator
        );

        echo "Services initialized successfully\n\n";

    // Input file path
    $inputPath = '/var/www/mytaxeu/docs/1025809020354.csv';

        if (!file_exists($inputPath)) {
            throw new Exception("Input file not found: {$inputPath}");
        }

        echo "Processing input file: {$inputPath}\n";
        echo "File size: " . number_format(filesize($inputPath) / 1024 / 1024, 2) . " MB\n\n";

        // Get data summary first
        echo "Analyzing data...\n";
        $summary = $spanishTaxService->getDataSummary($inputPath);

        echo "Data Summary:\n";
        echo "- Activity periods: " . implode(', ', array_keys($summary['activity_periods'])) . "\n";
        echo "- Period info: Year {$summary['period_info']['year']}, Period {$summary['period_info']['period']}\n";
        echo "- Intracomunitarias records: {$summary['intracomunitarias_count']}\n";
        echo "- OSS records: {$summary['oss_count']}\n";
        echo "- IOSS records: {$summary['ioss_count']}\n";
        echo "- Form 349 applicable: " . ($summary['forms_applicable']['form349'] ? 'Yes' : 'No') . "\n";
        echo "- Form 369 applicable: " . ($summary['forms_applicable']['form369'] ? 'Yes' : 'No') . "\n\n";

        // Generate forms
        echo "Generating Spanish tax forms...\n";
        $results = $spanishTaxService->generateForms($inputPath, $baseConfig);

        echo "\nGeneration Results:\n";
        echo "==================\n";

        foreach ($results as $formType => $result) {
            echo "\n{$formType}:\n";

            // Handle Form 349 with multiple formats
            if ($formType === 'form349') {
                echo "- Form 349 generated in both formats:\n";

                // Fixed width format
                if (isset($result['fixed_width'])) {
                    $fw = $result['fixed_width'];
                    echo "  Fixed-width format:\n";
                    echo "    - Filename: {$fw['filename']}\n";
                    echo "    - File path: {$fw['filepath']}\n";
                    echo "    - Content length: " . number_format($fw['content_length']) . " bytes\n";
                    echo "    - Records count: {$fw['records_count']}\n";
                    echo "    - Description: {$fw['description']}\n";
                }

                // CSV format
                if (isset($result['csv'])) {
                    $csv = $result['csv'];
                    echo "  CSV format:\n";
                    echo "    - Filename: {$csv['filename']}\n";
                    echo "    - File path: {$csv['filepath']}\n";
                    echo "    - Content length: " . number_format($csv['content_length']) . " bytes\n";
                    echo "    - Records count: {$csv['records_count']}\n";
                    echo "    - Description: {$csv['description']}\n";
                }

                // Summary
                if (isset($result['summary'])) {
                    $summary = $result['summary'];
                    echo "  Summary:\n";
                    echo "    - Original records: {$summary['original_records_count']}\n";
                    echo "    - Valid records: {$summary['valid_records_count']}\n";
                    echo "    - Skipped records: {$summary['skipped_records_count']}\n";
                    echo "    - Period: {$summary['period']}\n";
                }

                // Show preview of both formats
                if (isset($result['fixed_width']) && file_exists($result['fixed_width']['filepath'])) {
                    $content = file_get_contents($result['fixed_width']['filepath']);
                    echo "  Fixed-width preview (first 200 chars):\n";
                    echo "    " . substr($content, 0, 200) . (strlen($content) > 200 ? '...' : '') . "\n";
                }

                if (isset($result['csv']) && file_exists($result['csv']['filepath'])) {
                    $content = file_get_contents($result['csv']['filepath']);
                    echo "  CSV preview (first 300 chars):\n";
                    echo "    " . substr($content, 0, 300) . (strlen($content) > 300 ? '...' : '') . "\n";
                }

            } else {
                // Handle other forms (like Form 369) with old structure
                echo "- Filename: {$result['filename']}\n";
                echo "- File path: {$result['filepath']}\n";
                echo "- Content length: " . number_format($result['content_length']) . " bytes\n";

                if (isset($result['records_count'])) {
                    echo "- Records count: {$result['records_count']}\n";
                }
                if (isset($result['oss_records_count'])) {
                    echo "- OSS records: {$result['oss_records_count']}\n";
                }
                if (isset($result['ioss_records_count'])) {
                    echo "- IOSS records: {$result['ioss_records_count']}\n";
                }
                if (isset($result['regime'])) {
                    echo "- Regime: {$result['regime']}\n";
                }
                echo "- Period: {$result['period']}\n";

                // Show preview of file content (first 500 characters)
                if (file_exists($result['filepath'])) {
                    $content = file_get_contents($result['filepath']);
                    echo "- Content preview:\n";
                    echo substr($content, 0, 500) . (strlen($content) > 500 ? '...' : '') . "\n";
                }
            }
        }

        echo "\nTest completed successfully!\n";
        echo "Generated files are saved in storage/app/temp/spanish-tax/\n";

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

// Run the test
main();

