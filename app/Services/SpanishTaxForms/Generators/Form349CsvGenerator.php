<?php

declare(strict_types=1);

namespace App\Services\SpanishTaxForms\Generators;

use App\Services\SpanishTaxForms\Config\Form349Config;
use App\Exceptions\SpanishTaxForms\FormGenerationException;

/**
 * Generator for Spanish Tax Form 349 CSV format - Alternative semicolon-delimited format
 * Generates CSV format compatible with some Spanish tax software systems
 */
class Form349CsvGenerator
{
    private const ENCODING = 'ISO-8859-1';

    public function generate(array $intracomunitariasData, Form349Config $config): string
    {
        echo "Starting Form 349 CSV generation with " . count($intracomunitariasData) . " records\n";

        if (empty($intracomunitariasData)) {
            echo "Warning: No intracomunitarias data found for Form 349 CSV\n";
            return '';
        }

        $csvLines = [];

        // Generate CSV records for each transaction
        foreach ($intracomunitariasData as $key => $transaction) {
            $csvLines[] = $this->generateCsvRecord($transaction, $config, $key);
        }

        $content = implode("\r\n", $csvLines);

        // Convert to required encoding
        $content = mb_convert_encoding($content, self::ENCODING, 'UTF-8');

        echo "Form 349 CSV generation completed: " . count($csvLines) . " records, " . strlen($content) . " bytes\n";

        return $content;
    }

    private function generateCsvRecord(array $transaction, Form349Config $config, string $key): string
    {
        // Parse the key: 'país|nombreComprador|nifComprador'
        [$country, $buyerName, $buyerVat] = explode('|', $key);

        // Extract country code from buyer VAT (first 2 characters)
        $buyerCountryCode = substr($buyerVat, 0, 2);

        // Extract base amount
        $baseAmount = $transaction['Base (€)'] ?? 0;
        $vatAmount = $transaction['IVA (€)'] ?? 0;

        // Format amounts with comma as decimal separator (European style)
        $baseAmountFormatted = number_format($baseAmount, 2, ',', '');
        $vatAmountFormatted = number_format($vatAmount, 2, ',', '');

        // Build CSV record fields based on the pattern from the image
        $fields = [
            $buyerCountryCode,                          // Country code (FR, DE, IT)
            '31',                                       // Fixed code (seems to be standard)
            'FOB',                                      // Transport terms
            '11',                                       // Fixed code
            '3',                                        // Fixed code
            '',                                         // Empty field
            '85182190',                                 // Commodity code (example)
            $country,                                   // Origin country
            '1',                                        // Quantity indicator
            (string)intval($baseAmount),                // Base amount as integer
            (string)intval($vatAmount),                 // VAT amount as integer
            $baseAmountFormatted,                       // Base amount formatted
            $baseAmountFormatted,                       // Total amount (base + VAT)
            $buyerVat,                                  // Buyer VAT number
        ];

        return implode(';', $fields);
    }

    /**
     * Validate transaction data before generating Form 349 CSV
     */
    public function validateData(array $intracomunitariasData): array
    {
        $errors = [];

        foreach ($intracomunitariasData as $key => $transaction) {
            if (!str_contains($key, '|')) {
                $errors[] = "Invalid key format: {$key}";
                continue;
            }

            [$country, $buyerName, $buyerVat] = explode('|', $key);

            if (empty($buyerName)) {
                $errors[] = "Empty buyer name for key: {$key}";
            }

            if (empty($buyerVat)) {
                $errors[] = "Empty buyer VAT for key: {$key}";
            }

            if (!is_numeric($transaction['Base (€)'] ?? 0)) {
                $errors[] = "Invalid base amount for key: {$key}";
            }

            if (($transaction['Base (€)'] ?? 0) < 0) {
                $errors[] = "Negative base amount for key: {$key}";
            }
        }

        return $errors;
    }
}




