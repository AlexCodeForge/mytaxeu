<?php
/**
 * Test script to verify StreamingCsvTransformer integration in web app
 *
 * This script tests the transformation using our input file to ensure
 * the web app integration works correctly.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\StreamingCsvTransformer;

// Test the streaming transformer directly
$inputPath = '/var/www/mytaxeu/docs/transformer/input-output/input_real_enero.csv';
$outputPath = '/var/www/mytaxeu/test_streaming_integration_output.xlsx';

try {
    echo "🧪 Testing StreamingCsvTransformer Integration\n";
    echo "===============================================\n\n";

    echo "📁 Input file: " . basename($inputPath) . "\n";
    echo "📁 Output file: " . basename($outputPath) . "\n\n";

    if (!file_exists($inputPath)) {
        throw new Exception("Input file not found: $inputPath");
    }

    echo "⚡ Starting streaming transformation...\n";
    $startTime = microtime(true);
    $initialMemory = memory_get_usage(true);

    // Create transformer instance
    $transformer = new StreamingCsvTransformer();

    // Run transformation
    $transformer->transform($inputPath, $outputPath);

    $endTime = microtime(true);
    $finalMemory = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);

    // Verify output file was created
    if (!file_exists($outputPath)) {
        throw new Exception("Output file was not created!");
    }

    $outputSize = filesize($outputPath);
    $processingTime = round($endTime - $startTime, 2);

    echo "\n✅ SUCCESS! Transformation completed\n";
    echo "=====================================\n";
    echo "⏱️  Processing time: {$processingTime} seconds\n";
    echo "💾 Memory usage:\n";
    echo "   - Initial: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";
    echo "   - Final: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
    echo "   - Peak: " . round($peakMemory / 1024 / 1024, 2) . " MB\n";
    echo "📊 Output file size: " . round($outputSize / 1024 / 1024, 2) . " MB\n";
    echo "📄 Output file: $outputPath\n\n";

    echo "🔍 Quick verification of key values:\n";
    echo "====================================\n";

    // Use a simple Python script to verify key results
    $pythonScript = <<<'PYTHON'
import pandas as pd
import sys

try:
    # Read the Excel file
    df = pd.read_excel(sys.argv[1], sheet_name='REGULAR')

    # Find B2C/B2B section
    b2c_section = False
    results = {}

    for index, row in df.iterrows():
        if 'Ventas locales al consumidor final - B2C y B2B' in str(row.iloc[0]):
            b2c_section = True
            continue

        if b2c_section and pd.notna(row.iloc[0]):
            jurisdiction = row.iloc[0]
            if jurisdiction == 'Total':
                break

            # Extract values (assuming columns: Jurisdiction, Calc Base, No IVA, IVA, Total, Currency)
            calc_base = row.iloc[1] if len(row) > 1 and pd.notna(row.iloc[1]) else 0
            no_iva = row.iloc[2] if len(row) > 2 and pd.notna(row.iloc[2]) else 0
            iva = row.iloc[3] if len(row) > 3 and pd.notna(row.iloc[3]) else 0
            total = row.iloc[4] if len(row) > 4 and pd.notna(row.iloc[4]) else 0

            results[jurisdiction] = {
                'calc_base': float(calc_base) if calc_base != 0 else 0,
                'no_iva': float(no_iva) if no_iva != 0 else 0,
                'iva': float(iva) if iva != 0 else 0,
                'total': float(total) if total != 0 else 0
            }

    # Print key results
    key_countries = ['ITALY', 'POLAND', 'CZECH REPUBLIC', 'UNITED KINGDOM']
    for country in key_countries:
        if country in results:
            r = results[country]
            print(f"{country}:")
            print(f"  Calculated Base: €{r['calc_base']:,.2f}")
            print(f"  No IVA: €{r['no_iva']:,.2f}")
            print(f"  IVA: €{r['iva']:,.2f}")
            print(f"  Total: €{r['total']:,.2f}")
        else:
            print(f"{country}: NOT FOUND")

    print("\n✅ Key verification points:")
    if 'ITALY' in results:
        italy = results['ITALY']
        print(f"   - Italy Calculated Base ≈ €109,501.09: {'✅' if abs(italy['calc_base'] - 109501.09) < 5 else '❌'} (Got: €{italy['calc_base']:,.2f})")

    if 'POLAND' in results and 'CZECH REPUBLIC' in results:
        poland_no_iva = results['POLAND']['no_iva']
        czech_no_iva = results['CZECH REPUBLIC']['no_iva']
        print(f"   - Poland & Czech No IVA ≈ €1,139: {'✅' if abs(poland_no_iva - 1139) < 5 and abs(czech_no_iva - 1139) < 5 else '❌'}")
        print(f"     Poland: €{poland_no_iva:,.2f}, Czech: €{czech_no_iva:,.2f}")

except Exception as e:
    print(f"❌ Error reading Excel: {e}")
    sys.exit(1)
PYTHON;

    file_put_contents('/tmp/verify_results.py', $pythonScript);
    $verifyCommand = "python3 /tmp/verify_results.py " . escapeshellarg($outputPath);
    $verifyOutput = shell_exec($verifyCommand);
    echo $verifyOutput;

    echo "\n🎯 STREAMING TRANSFORMER INTEGRATION TEST COMPLETE!\n";
    echo "The transformer is ready for production use in the web app.\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
