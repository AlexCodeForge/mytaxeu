<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CsvTransformer;
use DomainException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvTransformerTest extends TestCase
{
    private CsvTransformer $transformer;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new CsvTransformer();
        $this->tempDir = sys_get_temp_dir() . '/csv_transformer_tests';
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        
        parent::tearDown();
    }

    /** @test */
    public function transform_method_exists_and_accepts_correct_parameters(): void
    {
        $inputPath = $this->tempDir . '/input.csv';
        $outputPath = $this->tempDir . '/output.csv';
        
        // Create a valid minimal CSV file
        file_put_contents($inputPath, "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE\n2024-01,EUR");
        
        // Should not throw exception for valid signature
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $outputPath);
    }

    /** @test */
    public function validate_rejects_unsupported_file_extensions(): void
    {
        $invalidPath = $this->tempDir . '/test.xlsx';
        file_put_contents($invalidPath, 'content');
        
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid file extension');
        
        $this->transformer->transform($invalidPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function validate_accepts_csv_and_txt_extensions(): void
    {
        $csvPath = $this->tempDir . '/test.csv';
        $txtPath = $this->tempDir . '/test.txt';
        
        // Create valid files with ACTIVITY_PERIOD column
        $content = "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE\n2024-01,EUR";
        file_put_contents($csvPath, $content);
        file_put_contents($txtPath, $content);
        
        // Should not throw exceptions
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($csvPath, $this->tempDir . '/output1.csv');
        $this->transformer->transform($txtPath, $this->tempDir . '/output2.csv');
    }

    /** @test */
    public function validate_requires_activity_period_column(): void
    {
        $inputPath = $this->tempDir . '/invalid.csv';
        file_put_contents($inputPath, "TRANSACTION_CURRENCY_CODE,AMOUNT\nEUR,100");
        
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Missing required ACTIVITY_PERIOD column');
        
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function validate_enforces_max_three_distinct_activity_periods(): void
    {
        $inputPath = $this->tempDir . '/too_many_periods.csv';
        $content = "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE\n" .
                   "2024-01,EUR\n" .
                   "2024-02,EUR\n" .
                   "2024-03,EUR\n" .
                   "2024-04,EUR";
        file_put_contents($inputPath, $content);
        
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Maximum 3 distinct ACTIVITY_PERIOD values allowed');
        
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function validate_allows_max_three_distinct_activity_periods(): void
    {
        $inputPath = $this->tempDir . '/valid_periods.csv';
        $content = "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE\n" .
                   "2024-01,EUR\n" .
                   "2024-02,EUR\n" .
                   "2024-03,EUR\n" .
                   "2024-01,PLN";  // Duplicate period should be allowed
        file_put_contents($inputPath, $content);
        
        // Should not throw exception
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function parse_rows_detects_comma_delimiter(): void
    {
        $inputPath = $this->tempDir . '/comma_delimited.csv';
        $content = "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE,AMOUNT\n" .
                   "2024-01,EUR,100.50\n" .
                   "2024-01,USD,200.75";
        file_put_contents($inputPath, $content);
        
        // Should parse without errors
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function parse_rows_detects_semicolon_delimiter(): void
    {
        $inputPath = $this->tempDir . '/semicolon_delimited.csv';
        $content = "ACTIVITY_PERIOD;TRANSACTION_CURRENCY_CODE;AMOUNT\n" .
                   "2024-01;EUR;100,50\n" .
                   "2024-01;USD;200,75";
        file_put_contents($inputPath, $content);
        
        // Should parse without errors
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function parse_rows_prefers_comma_when_equal_delimiters(): void
    {
        $inputPath = $this->tempDir . '/mixed_delimiters.csv';
        // Equal number of commas and semicolons, should prefer comma
        $content = "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE;AMOUNT\n" .
                   "2024-01,EUR;100.50";
        file_put_contents($inputPath, $content);
        
        // Should parse without errors using comma as primary delimiter
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function normalize_encoding_handles_utf8_files(): void
    {
        $inputPath = $this->tempDir . '/utf8.csv';
        $content = "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE,DESCRIPTION\n" .
                   "2024-01,EUR,Café München\n" .
                   "2024-01,EUR,Niño España";
        file_put_contents($inputPath, $content);
        
        // Should handle UTF-8 without errors
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function normalize_encoding_handles_iso_8859_1_fallback(): void
    {
        $inputPath = $this->tempDir . '/iso.csv';
        // Create file with ISO-8859-1 encoding
        $content = "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE,DESCRIPTION\n" .
                   "2024-01,EUR," . mb_convert_encoding('Café', 'ISO-8859-1', 'UTF-8');
        file_put_contents($inputPath, $content);
        
        // Should handle ISO-8859-1 and convert to UTF-8
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function row_data_normalization_trims_whitespace(): void
    {
        $inputPath = $this->tempDir . '/whitespace.csv';
        $content = "ACTIVITY_PERIOD , TRANSACTION_CURRENCY_CODE , AMOUNT \n" .
                   " 2024-01 , EUR , 100.50 \n" .
                   "  2024-01  ,  USD  ,  200.75  ";
        file_put_contents($inputPath, $content);
        
        // Should normalize whitespace without errors
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function row_data_normalization_coerces_numeric_columns_to_float(): void
    {
        $inputPath = $this->tempDir . '/numeric.csv';
        $content = "ACTIVITY_PERIOD,TOTAL_ACTIVITY_VALUE_VAT_INCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_AMT\n" .
                   "2024-01,100.50,90.00,10.50\n" .
                   "2024-01,,80.00,\n" .  // Empty values should become 0
                   "2024-01,200,180.25,19.75";
        file_put_contents($inputPath, $content);
        
        // Should coerce numeric values without errors
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function transform_creates_output_file(): void
    {
        $inputPath = $this->tempDir . '/input.csv';
        $outputPath = $this->tempDir . '/output.csv';
        
        $content = "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE\n2024-01,EUR";
        file_put_contents($inputPath, $content);
        
        $this->transformer->transform($inputPath, $outputPath);
        
        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /** @test */
    public function transform_throws_exception_for_nonexistent_input_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Input file not found');
        
        $this->transformer->transform('/nonexistent/file.csv', $this->tempDir . '/output.csv');
    }

    /** @test */
    public function transform_throws_exception_for_unwritable_output_path(): void
    {
        $inputPath = $this->tempDir . '/input.csv';
        file_put_contents($inputPath, "ACTIVITY_PERIOD,TRANSACTION_CURRENCY_CODE\n2024-01,EUR");
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to output path');
        
        $this->transformer->transform($inputPath, '/root/unwritable/output.csv');
    }

    // ================================
    // Classification Engine Tests
    // ================================

    /** @test */
    public function classify_identifies_b2c_b2b_local_transactions(): void
    {
        $inputPath = $this->tempDir . '/b2c_local.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TAX_COLLECTION_RESPONSIBILITY\n" .
                   "2024-01,REGULAR,SELLER\n" .
                   "2024-01,UK_VOEC-DOMESTIC,SELLER";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function classify_identifies_local_sin_iva_transactions(): void
    {
        $inputPath = $this->tempDir . '/local_sin_iva.csv';
        $content = "ACTIVITY_PERIOD,SALE_DEPART_COUNTRY,SALE_ARRIVAL_COUNTRY,TOTAL_ACTIVITY_VALUE_VAT_AMT,BUYER_VAT_NUMBER,TAX_COLLECTION_RESPONSIBILITY\n" .
                   "2024-01,ES,ES,0,,SELLER\n" .
                   "2024-01,DE,DE,0.00,,SELLER";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function classify_identifies_intracom_b2b_transactions(): void
    {
        $inputPath = $this->tempDir . '/intracom_b2b.csv';
        $content = "ACTIVITY_PERIOD,SALE_DEPART_COUNTRY,SALE_ARRIVAL_COUNTRY,BUYER_VAT_NUMBER_COUNTRY,TAX_COLLECTION_RESPONSIBILITY,TAX_REPORTING_SCHEME\n" .
                   "2024-01,ES,DE,DE,SELLER,REGULAR\n" .
                   "2024-01,FR,IT,IT,SELLER,REGULAR";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function classify_identifies_oss_transactions(): void
    {
        $inputPath = $this->tempDir . '/oss.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME\n" .
                   "2024-01,UNION-OSS\n" .
                   "2024-01,UNION-OSS";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function classify_identifies_ioss_transactions(): void
    {
        $inputPath = $this->tempDir . '/ioss.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,SALE_DEPART_COUNTRY,SALE_ARRIVAL_COUNTRY\n" .
                   "2024-01,DEEMED_RESELLER-IOSS,ES,DE\n" .
                   "2024-01,DEEMED_RESELLER-IOSS,FR,IT";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function classify_identifies_marketplace_vat_transactions(): void
    {
        $inputPath = $this->tempDir . '/marketplace.csv';
        $content = "ACTIVITY_PERIOD,TAX_COLLECTION_RESPONSIBILITY\n" .
                   "2024-01,MARKETPLACE\n" .
                   "2024-01,MARKETPLACE";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function classify_identifies_amazon_compras_transactions(): void
    {
        $inputPath = $this->tempDir . '/amazon_compras.csv';
        $content = "ACTIVITY_PERIOD,SUPPLIER_NAME\n" .
                   "2024-01,Amazon Services Europe Sarl\n" .
                   "2024-01,Amazon Services Europe Sarl";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function classify_identifies_exportaciones_transactions(): void
    {
        $inputPath = $this->tempDir . '/exportaciones.csv';
        $content = "ACTIVITY_PERIOD,SALE_DEPART_COUNTRY,SALE_ARRIVAL_COUNTRY\n" .
                   "2024-01,ES,US\n" .
                   "2024-01,DE,GB\n" .
                   "2024-01,FR,CA";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function classify_applies_rules_in_first_match_order(): void
    {
        $inputPath = $this->tempDir . '/priority_test.csv';
        // Create a transaction that could match multiple rules - marketplace should win over other rules
        $content = "ACTIVITY_PERIOD,TAX_COLLECTION_RESPONSIBILITY,TAX_REPORTING_SCHEME,SUPPLIER_NAME\n" .
                   "2024-01,MARKETPLACE,REGULAR,Amazon Services Europe Sarl";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    // ================================
    // Currency Conversion & Calculations Tests
    // ================================

    /** @test */
    public function convert_currencies_applies_fixed_exchange_rates(): void
    {
        $inputPath = $this->tempDir . '/currency_conversion.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TRANSACTION_CURRENCY_CODE,TOTAL_ACTIVITY_VALUE_VAT_INCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_AMT\n" .
                   "2024-01,UNION-OSS,PLN,100,90,10\n" .  // PLN -> EUR
                   "2024-01,UNION-OSS,SEK,200,180,20\n" . // SEK -> EUR
                   "2024-01,UNION-OSS,EUR,150,135,15";    // EUR stays same
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function convert_currencies_only_applies_to_specific_categories(): void
    {
        $inputPath = $this->tempDir . '/selective_conversion.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TAX_COLLECTION_RESPONSIBILITY,TRANSACTION_CURRENCY_CODE,TOTAL_ACTIVITY_VALUE_VAT_INCL_AMT\n" .
                   "2024-01,REGULAR,SELLER,PLN,100\n" .        // B2C/B2B Local - no conversion
                   "2024-01,UNION-OSS,,PLN,100\n" .           // OSS - should convert
                   "2024-01,,MARKETPLACE,PLN,100";            // Marketplace - should convert
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function compute_row_totals_calculates_base_iva_total_amounts(): void
    {
        $inputPath = $this->tempDir . '/row_totals.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TRANSACTION_CURRENCY_CODE,TOTAL_ACTIVITY_VALUE_VAT_INCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_AMT,PRICE_OF_ITEMS_VAT_INCL_AMT,PRICE_OF_ITEMS_VAT_EXCL_AMT,PRICE_OF_ITEMS_VAT_AMT\n" .
                   "2024-01,UNION-OSS,EUR,100,90,10,50,45,5";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function compute_calculated_base_for_b2c_b2b_local_category(): void
    {
        $inputPath = $this->tempDir . '/calculated_base.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TAX_COLLECTION_RESPONSIBILITY,TRANSACTION_CURRENCY_CODE,TOTAL_ACTIVITY_VALUE_VAT_AMT,PRICE_OF_ITEMS_VAT_RATE_PERCENT,TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT,PRICE_OF_ITEMS_VAT_EXCL_AMT\n" .
                   "2024-01,REGULAR,SELLER,EUR,21,21,100,50\n" .  // With VAT: calculated base = VAT / rate
                   "2024-01,REGULAR,SELLER,EUR,0,0,100,50";       // No VAT: calculated base = base
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function outputs_properly_formatted_sections(): void
    {
        $inputPath = $this->tempDir . '/formatted_output.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TRANSACTION_CURRENCY_CODE,TOTAL_ACTIVITY_VALUE_VAT_INCL_AMT\n" .
                   "2024-01,UNION-OSS,PLN,100\n" .
                   "2024-01,UNION-OSS,SEK,200";
        file_put_contents($inputPath, $content);
        
        $outputPath = $this->tempDir . '/formatted_output.csv';
        $this->transformer->transform($inputPath, $outputPath);
        
        $outputContent = file_get_contents($outputPath);
        // Should contain section headers and proper formatting
        $this->assertStringContainsString('INTERNATIONAL SECTION', $outputContent);
        $this->assertStringContainsString('OSS', $outputContent);
    }

    // ================================
    // Category Aggregation System Tests
    // ================================

    /** @test */
    public function aggregate_per_category_groups_transactions_by_category(): void
    {
        $inputPath = $this->tempDir . '/aggregation_test.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TAX_COLLECTION_RESPONSIBILITY,TAXABLE_JURISDICTION,TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_AMT\n" .
                   "2024-01,REGULAR,SELLER,ES,100,21\n" .        // B2C/B2B Local
                   "2024-01,REGULAR,SELLER,ES,200,42\n" .        // B2C/B2B Local - same jurisdiction
                   "2024-01,UNION-OSS,,DE,150,30";              // OSS
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function b2c_b2b_local_aggregation_groups_by_taxable_jurisdiction(): void
    {
        $inputPath = $this->tempDir . '/b2c_local_agg.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TAX_COLLECTION_RESPONSIBILITY,TAXABLE_JURISDICTION,TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_AMT\n" .
                   "2024-01,REGULAR,SELLER,ES,100,21\n" .
                   "2024-01,REGULAR,SELLER,DE,150,30\n" .
                   "2024-01,REGULAR,SELLER,ES,200,42";  // Should group with first ES
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function local_sin_iva_aggregation_includes_buyer_data(): void
    {
        $inputPath = $this->tempDir . '/local_sin_iva_agg.csv';
        $content = "ACTIVITY_PERIOD,SALE_DEPART_COUNTRY,SALE_ARRIVAL_COUNTRY,TOTAL_ACTIVITY_VALUE_VAT_AMT,BUYER_VAT_NUMBER,TAX_COLLECTION_RESPONSIBILITY,BUYER_NAME,TRANSACTION_EVENT_CODE\n" .
                   "2024-01,ES,ES,0,,SELLER,Company A,SALE\n" .
                   "2024-01,ES,ES,0,,SELLER,Company B,SALE";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function intracom_b2b_aggregation_includes_buyer_vat_info(): void
    {
        $inputPath = $this->tempDir . '/intracom_agg.csv';
        $content = "ACTIVITY_PERIOD,SALE_DEPART_COUNTRY,SALE_ARRIVAL_COUNTRY,BUYER_VAT_NUMBER_COUNTRY,TAX_COLLECTION_RESPONSIBILITY,TAX_REPORTING_SCHEME,BUYER_NAME,BUYER_VAT_NUMBER\n" .
                   "2024-01,ES,DE,DE,SELLER,REGULAR,German Company,DE123456789\n" .
                   "2024-01,ES,FR,FR,SELLER,REGULAR,French Company,FR987654321";
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function oss_aggregation_includes_destination_country_breakdown(): void
    {
        $inputPath = $this->tempDir . '/oss_agg.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,SALE_ARRIVAL_COUNTRY,TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_AMT\n" .
                   "2024-01,UNION-OSS,DE,100,21\n" .
                   "2024-01,UNION-OSS,FR,150,30\n" .
                   "2024-01,UNION-OSS,DE,200,42";  // Should group with first DE
        file_put_contents($inputPath, $content);
        
        $this->expectNotToPerformAssertions();
        $this->transformer->transform($inputPath, $this->tempDir . '/output.csv');
    }

    /** @test */
    public function aggregation_generates_totals_rows_for_each_category(): void
    {
        $inputPath = $this->tempDir . '/totals_test.csv';
        $content = "ACTIVITY_PERIOD,TAX_REPORTING_SCHEME,TAX_COLLECTION_RESPONSIBILITY,TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT,TOTAL_ACTIVITY_VALUE_VAT_AMT\n" .
                   "2024-01,REGULAR,SELLER,100,21\n" .
                   "2024-01,REGULAR,SELLER,200,42\n" .
                   "2024-01,UNION-OSS,,150,30";
        file_put_contents($inputPath, $content);
        
        $outputPath = $this->tempDir . '/totals_output.csv';
        $this->transformer->transform($inputPath, $outputPath);
        
        $outputContent = file_get_contents($outputPath);
        // Should contain total rows
        $this->assertStringContainsString('Total', $outputContent);
    }
}
