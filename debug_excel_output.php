<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Services\CsvTransformer;

echo "🔍 DEBUGGING EXCEL OUTPUT GENERATION\n";
echo "====================================\n\n";

$inputPath = '/var/www/mytaxeu/docs/transformer/input-output/input.csv';
$outputPath = '/var/www/mytaxeu/docs/tests/debug_output.xlsx';

echo "📋 Running full transformation and tracking classifications...\n";

$transformer = new CsvTransformer();

// Manually do what transform() does but with debugging
$data = $transformer->readCsvFile($inputPath);

$classifications = [];
foreach ($data as $row) {
    $category = $transformer->classifyTransaction($row);
    if ($category) {
        $classifications[$category][] = $row;
    }
}

// Access private processCategory method
$reflection = new ReflectionClass($transformer);
$processMethod = $reflection->getMethod('processCategory');
$processMethod->setAccessible(true);

foreach ($classifications as $category => $transactions) {
    if (!empty($transactions)) {
        $classifications[$category] = $processMethod->invoke($transformer, $category, $transactions);
    }
}

echo "📊 Final classifications before Excel generation:\n";
foreach ($classifications as $category => $data) {
    echo sprintf("%-50s: %d items\n", $category, count($data));
}

echo "\n🔍 B2B data sample before Excel:\n";
if (isset($classifications['Ventas Intracomunitarias de bienes - B2B (EUR)'])) {
    $b2bData = $classifications['Ventas Intracomunitarias de bienes - B2B (EUR)'];
    echo "B2B data count: " . count($b2bData) . "\n";

    if (!empty($b2bData)) {
        echo "First B2B record:\n";
        foreach ($b2bData[0] as $key => $value) {
            echo "  $key: $value\n";
        }
    }
} else {
    echo "❌ B2B data missing!\n";
}

echo "\n📝 Now testing Excel generation manually...\n";

// Access generateExcelOutput
$excelMethod = $reflection->getMethod('generateExcelOutput');
$excelMethod->setAccessible(true);

try {
    $excelMethod->invoke($transformer, $classifications, $outputPath);
    echo "✅ Excel generation completed\n";

    if (file_exists($outputPath)) {
        echo "✅ Output file created: $outputPath\n";
        echo "📊 File size: " . number_format(filesize($outputPath)) . " bytes\n";

        // Check what's actually in the file
        $pythonScript = "
import pandas as pd
import sys

try:
    sheets = pd.read_excel('/var/www/mytaxeu/docs/tests/debug_output.xlsx', sheet_name=None)

    print('\\n📋 SHEETS FOUND:', list(sheets.keys()))

    if 'INTERNATIONAL' in sheets:
        intl = sheets['INTERNATIONAL']
        print('\\n🌍 INTERNATIONAL SHEET:')
        print(f'Rows: {len(intl)}')

        sections = []
        for i, row in intl.iterrows():
            first_col = str(row.iloc[0])
            if ('EUR)' in first_col or 'OSS' in first_col) and 'Unnamed' not in first_col:
                sections.append(first_col)
                print(f'{len(sections)}. {first_col}')

        print(f'\\nTotal sections: {len(sections)}')

        # Check for B2B specifically
        intl_text = intl.iloc[:, 0].astype(str).str.cat(sep=' ')
        print('\\n🔍 B2B SECTION CHECK:')
        print('B2B present in sheet:', 'Ventas Intracomunitarias de bienes - B2B (EUR)' in intl_text)

    else:
        print('❌ INTERNATIONAL sheet not found!')

except Exception as e:
    print(f'❌ Error reading Excel: {e}')
    sys.exit(1)
";

        file_put_contents('/tmp/check_debug_excel.py', $pythonScript);
        $output = shell_exec('python3 /tmp/check_debug_excel.py 2>&1');
        echo $output;
        @unlink('/tmp/check_debug_excel.py');

    } else {
        echo "❌ Output file not created!\n";
    }
} catch (Exception $e) {
    echo "❌ Excel generation error: " . $e->getMessage() . "\n";
}

echo "\n🎯 This should show us exactly where B2B data gets lost.\n";
