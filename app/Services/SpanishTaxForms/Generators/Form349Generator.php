<?php

declare(strict_types=1);

namespace App\Services\SpanishTaxForms\Generators;

use App\Services\SpanishTaxForms\Config\Form349Config;
use App\Exceptions\SpanishTaxForms\FormGenerationException;

/**
 * Generator for Spanish Tax Form 349 - Modelo 349 (Intracomunitarias)
 * Generates fixed-width text format compatible with Spanish tax authority
 * CRITICAL: Every line must be EXACTLY 500 characters
 */
class Form349Generator
{
    private const RECORD_LENGTH = 500;
    private const ENCODING = 'ISO-8859-1';

    public function generate(array $intracomunitariasData, Form349Config $config): string
    {
        echo "Starting Form 349 generation with " . count($intracomunitariasData) . " records\n";

        if (empty($intracomunitariasData)) {
            echo "Warning: No intracomunitarias data found for Form 349\n";
            return '';
        }

        $lines = [];

        // 1. Generate TIPO DE REGISTRO 1 (Header record)
        $lines[] = $this->generateHeaderRecord($intracomunitariasData, $config);

        // 2. Generate TIPO DE REGISTRO 2 (Operator records)
        $operatorCount = 0;
        foreach ($intracomunitariasData as $key => $transaction) {
            $lines[] = $this->generateOperatorRecord($transaction, $config, $key);
            $operatorCount++;

            // Limit records per page as per official requirements
            if ($operatorCount >= $config->maxRecordsPerPage) {
                echo "Warning: Reached maximum records per page ({$config->maxRecordsPerPage}) for Form 349\n";
                break;
            }
        }

        $content = implode("\r\n", $lines);

        // Convert to required encoding
        $content = mb_convert_encoding($content, self::ENCODING, 'UTF-8');

        echo "Form 349 generation completed: " . count($lines) . " records, " . strlen($content) . " bytes\n";

        return $content;
    }

    /**
     * Generate TIPO DE REGISTRO 1 - Header record (exactly 500 characters)
     */
    private function generateHeaderRecord(array $intracomunitariasData, Form349Config $config): string
    {
        // Calculate totals for header
        $totalOperators = min(count($intracomunitariasData), $config->maxRecordsPerPage);
        $totalAmount = 0;
        $count = 0;
        foreach ($intracomunitariasData as $transaction) {
            if ($count >= $config->maxRecordsPerPage) break;
            $totalAmount += (float)($transaction['Base (€)'] ?? 0);
            $count++;
        }

        // Split amount into euros and cents
        $totalAmountCents = round($totalAmount * 100);
        $euros = intval($totalAmountCents / 100);
        $cents = $totalAmountCents % 100;

        $record = '';

        // Position 1: Type of record
        $record .= '1';

        // Position 2-4: Model declaration
        $record .= '349';

        // Position 5-8: Year (4 digits)
        $record .= $config->exerciseYear;

        // Position 9-17: Declarant NIF (9 positions, right-aligned, zero-padded)
        $record .= str_pad($config->declarantNif, 9, '0', STR_PAD_LEFT);

        // Position 18-57: Company name (40 positions, left-aligned, space-padded)
        $record .= str_pad('INNOVTEC E-COMMERCE SL', 40, ' ', STR_PAD_RIGHT);

        // Position 58: Blank
        $record .= ' ';

        // Position 59-67: Phone (9 positions) - using zeros as placeholder
        $record .= '000000000';

        // Position 68-107: Contact person name (40 positions)
        $record .= str_pad('ADMINISTRACION', 40, ' ', STR_PAD_RIGHT);

        // Position 108-120: Declaration number (13 positions) - sequential number starting with 349
        $declarationNumber = '3490000000001'; // Sequential number
        $record .= $declarationNumber;

        // Position 121-122: Complementary/Substitutive declaration
        $record .= '  '; // Two spaces - not complementary/substitutive

        // Position 123-135: Previous declaration number (13 positions, zeros)
        $record .= '0000000000000';

        // Position 136-137: Period
        $record .= $this->formatPeriod($config->period);

        // Position 138-146: Total number of operators (9 positions, zero-padded)
        $record .= str_pad((string)$totalOperators, 9, '0', STR_PAD_LEFT);

        // Position 147-161: Total amount (15 positions: 13 euros + 2 cents)
        $record .= str_pad((string)$euros, 13, '0', STR_PAD_LEFT);
        $record .= str_pad((string)$cents, 2, '0', STR_PAD_LEFT);

        // Position 162-170: Total operators with corrections (9 positions, zeros)
        $record .= '000000000';

        // Position 171-185: Correction amounts (15 positions, zeros)
        $record .= '000000000000000';

        // Position 186: Periodicity change indicator
        $record .= ' '; // Space - no change

        // Position 187-390: Blanks (204 positions)
        $record .= str_repeat(' ', 204);

        // Position 391-399: Legal representative NIF (9 positions, spaces)
        $record .= '         ';

        // Position 400-500: Blanks (101 positions)
        $record .= str_repeat(' ', 101);

        // Ensure exactly 500 characters
        $record = substr($record . str_repeat(' ', self::RECORD_LENGTH), 0, self::RECORD_LENGTH);

        return $record;
    }

    /**
     * Generate TIPO DE REGISTRO 2 - Operator record (exactly 500 characters)
     */
    private function generateOperatorRecord(array $transaction, Form349Config $config, string $key): string
    {
        // Parse the key: 'país|nombreComprador|nifComprador'
        [$country, $buyerName, $buyerVat] = explode('|', $key);

        // Get transaction amount in cents
        $baseAmount = (float)($transaction['Base (€)'] ?? 0);
        $amountCents = round($baseAmount * 100);
        $euros = intval($amountCents / 100);
        $cents = $amountCents % 100;

        $record = '';

        // Position 1: Type of record
        $record .= '2';

        // Position 2-4: Model declaration
        $record .= '349';

        // Position 5-8: Year
        $record .= $config->exerciseYear;

        // Position 9-17: Declarant NIF (same as header)
        $record .= str_pad($config->declarantNif, 9, '0', STR_PAD_LEFT);

        // Position 18-75: Blanks (58 positions)
        $record .= str_repeat(' ', 58);

        // Position 76-92: Operator community VAT (17 positions)
        // Format: Country code (2) + VAT number (15), left-aligned, space-padded
        $operatorVat = $buyerVat;
        $record .= str_pad($operatorVat, 17, ' ', STR_PAD_RIGHT);

        // Position 93-132: Operator name (40 positions, left-aligned, space-padded)
        $operatorName = $this->cleanOperatorName($buyerName);
        $record .= str_pad($operatorName, 40, ' ', STR_PAD_RIGHT);

        // Position 133: Operation key
        $record .= 'E'; // E = Entregas intracomunitarias exentas (intracom deliveries)

        // Position 134-146: Base amount (13 positions, right-aligned, zero-padded)
        $record .= str_pad((string)$amountCents, 13, '0', STR_PAD_LEFT);

        // Position 147-178: Blanks (32 positions)
        $record .= str_repeat(' ', 32);

        // Position 179-195: Final destination VAT (17 positions, only for operation key "C")
        $record .= str_repeat(' ', 17);

        // Position 196-500: Blanks (305 positions)
        $record .= str_repeat(' ', 305);

        // Ensure exactly 500 characters
        $record = substr($record . str_repeat(' ', self::RECORD_LENGTH), 0, self::RECORD_LENGTH);

        return $record;
    }

    /**
     * Format period according to Spanish tax authority requirements
     */
    private function formatPeriod(string $period): string
    {
        // Convert from "T 1" format to "1T" format
        if (preg_match('/T\s*(\d)/', $period, $matches)) {
            return $matches[1] . 'T';
        }

        // If already in correct format, use as is
        if (preg_match('/^\d{1,2}T?$/', $period)) {
            return str_pad($period, 2, '0', STR_PAD_LEFT);
        }

        // Default to first quarter
        return '1T';
    }

    /**
     * Clean operator name for Spanish tax authority format
     */
    private function cleanOperatorName(string $name): string
    {
        // Remove special characters and normalize
        $cleaned = strtoupper($name);
        $cleaned = preg_replace('/[^A-Z0-9\s]/', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        // Remove accents (convert to ISO-8859-1 compatible)
        $accents = [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ñ' => 'N', 'Ç' => 'C'
        ];

        return strtr($cleaned, $accents);
    }

    /**
     * Validate transaction data before generating Form 349
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

            // Allow zero amounts but warn about negative amounts
            if (($transaction['Base (€)'] ?? 0) < 0) {
                $errors[] = "Negative base amount for key: {$key}";
            }
        }

        return $errors;
    }
}
