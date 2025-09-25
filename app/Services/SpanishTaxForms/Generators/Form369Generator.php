<?php

declare(strict_types=1);

namespace App\Services\SpanishTaxForms\Generators;

use App\Services\SpanishTaxForms\Config\Form369Config;
use App\Exceptions\SpanishTaxForms\FormGenerationException;

/**
 * Generator for Spanish Tax Form 369 - Modelo 369 (OSS/IOSS)
 * Generates XML-like tagged structure compatible with Spanish tax authority
 * CRITICAL: Must follow exact format per official examples
 */
class Form369Generator
{
    private const ENCODING = 'ISO-8859-1';
    private const MAX_RECORDS_PER_SECTION = 28;

    public function generate(array $ossData, array $iossData, Form369Config $config): string
    {
        echo "Starting Form 369 generation - OSS: " . count($ossData) . ", IOSS: " . count($iossData) . ", Regime: {$config->regime}\n";

        if (empty($ossData) && empty($iossData)) {
            echo "Warning: No OSS/IOSS data found for Form 369\n";
            return '';
        }

        // Determine if we need complementary pages
        $totalRecords = count($ossData) + count($iossData);
        $needsComplementaryPage = $totalRecords > self::MAX_RECORDS_PER_SECTION;

        // Generate header
        $header = $this->generateHeader($config, $needsComplementaryPage ? 'C' : ' ');

        // Generate sections based on regime
        $sections = '';
        switch ($config->regime) {
            case 'MOSS':
                $sections = $this->generateMossSection($ossData, $config, $needsComplementaryPage);
                break;
            case 'VOES':
                $sections = $this->generateVoesSection($ossData, $config, $needsComplementaryPage);
                break;
            case 'IMPO':
                $sections = $this->generateImpoSection($iossData, $config, $needsComplementaryPage);
                break;
            default:
                throw new FormGenerationException("Unsupported regime: {$config->regime}");
        }

        // Generate footer
        $footer = $this->generateFooter($config);

        $content = $header . $sections . $footer;

        // Convert to required encoding
        $content = mb_convert_encoding($content, self::ENCODING, 'UTF-8');

        echo "Form 369 generation completed: " . strlen($content) . " bytes\n";

        return $content;
    }

    /**
     * Generate header with correct format: <T369YYYYMM0000> or <T369YYYYQT0000>
     */
    private function generateHeader(Form369Config $config, string $complementaryPage): string
    {
        // Format period correctly
        $periodType = $config->isQuarterly ? 'T' : 'M';
        $periodFormatted = $this->formatPeriodForHeader($config->period, $periodType);

        $year = $this->extractYearFromPeriod($config->period);
        $headerTag = "T369{$year}{$periodFormatted}0000";

        $header = "<{$headerTag}>";

        // Add padding to position 84 (where NIF starts)
        $header .= str_repeat(' ', 84 - strlen($header));

        // Add NIF (9 positions)
        $header .= str_pad($config->declarantNif, 9, ' ', STR_PAD_RIGHT);

        // Add padding to position 204 (where T36900 starts)
        $header .= str_repeat(' ', 204 - strlen($header));

        // Add mandatory T36900 section (empty)
        $header .= '<T36900></T36900>';

        return $header;
    }

    /**
     * Generate MOSS sections (T36904-T36909)
     */
    private function generateMossSection(array $ossData, Form369Config $config, bool $needsComplementaryPage): string
    {
        if (empty($ossData)) {
            return '<T36904></T36904><T36905></T36905><T36906></T36907><T36908></T36908><T36909></T36909>';
        }

        $sections = '';

        // T36904 - Main section
        $sections .= $this->generateT36904($ossData, $config, $needsComplementaryPage);

        // T36905-T36909 - Additional sections (for overflow or special cases)
        $sections .= $this->generateT36905($ossData, $config);
        $sections .= $this->generateT36906($config);
        $sections .= $this->generateT36907($config);
        $sections .= $this->generateT36908($config);
        $sections .= $this->generateT36909();

        return $sections;
    }

    /**
     * Generate VOES sections (T36901-T36903)
     */
    private function generateVoesSection(array $ossData, Form369Config $config, bool $needsComplementaryPage): string
    {
        $sections = '';

        // T36901 - Main section (similar to T36904 but for VOES)
        $sections .= $this->generateT36901($ossData, $config, $needsComplementaryPage);

        // T36902-T36903 - Additional sections
        $sections .= $this->generateT36902($config);
        $sections .= $this->generateT36903();

        return $sections;
    }

    /**
     * Generate IMPO sections (T36910-T36912)
     */
    private function generateImpoSection(array $iossData, Form369Config $config, bool $needsComplementaryPage): string
    {
        $sections = '';

        // T36910 - Main section
        $sections .= $this->generateT36910($iossData, $config, $needsComplementaryPage);

        // T36911-T36912 - Additional sections
        $sections .= $this->generateT36911($config);
        $sections .= $this->generateT36912();

        return $sections;
    }

    /**
     * Generate T36904 section (MOSS main data)
     */
    private function generateT36904(array $ossData, Form369Config $config, bool $needsComplementaryPage): string
    {
        $content = '<T36904>';

        // Regime and category
        $content .= 'MOSS DO';

        // Padding to position 41
        $content .= str_repeat(' ', 41 - strlen('<T36904>MOSS DO'));

        // Complementary page indicator
        $content .= $needsComplementaryPage ? 'C' : ' ';

        // Space
        $content .= ' ';

        // Country and NIF
        $content .= 'ES' . $config->declarantNif;

        // Padding (6 spaces)
        $content .= '      ';

        // Company name (truncated to fit)
        $companyName = substr($config->companyName, 0, 80);
        $content .= str_pad($companyName, 80, ' ', STR_PAD_RIGHT);

        // Period (format: YYYYT Q or YYYYM MM)
        $periodType = $config->isQuarterly ? 'T' : 'M';
        $year = $this->extractYearFromPeriod($config->period);
        $content .= $this->formatPeriodForData($config->period, $periodType, $year);

        // Padding to align declaration indicator
        $content .= str_repeat(' ', 15);

        // Declaration without activity indicator
        $content .= $this->hasActivity($ossData) ? '0' : '1';

        // Country VAT entries (limit to 28 for first page)
        $processedCount = 0;
        foreach ($ossData as $key => $data) {
            if ($processedCount >= self::MAX_RECORDS_PER_SECTION) {
                break;
            }

            // Parse key format: "ES|FR - 20.00%"
            $parsedData = $this->parseOssKey($key, $data);
            if ($parsedData) {
                $content .= $this->formatVatEntry($parsedData['destination_country'], $parsedData['vat_rate'], $data);
                $processedCount++;
            }
        }

        // Pad to ensure proper length
        $content .= str_repeat(' ', max(0, 2100 - strlen($content . '</T36904>')));

        $content .= '</T36904>';

        return $content;
    }

    /**
     * Generate T36901 section (VOES main data)
     */
    private function generateT36901(array $ossData, Form369Config $config, bool $needsComplementaryPage): string
    {
        $content = '<T36901>';

        // Similar to T36904 but for VOES
        $content .= 'VOES DO';

        // Padding to position 41
        $content .= str_repeat(' ', 41 - strlen('<T36901>VOES DO'));

        // Complementary page indicator
        $content .= $needsComplementaryPage ? 'C' : ' ';

        // Space and country/NIF
        $content .= ' ES' . $config->declarantNif;

        // Add IOSS number if available
        $content .= '      ' . str_pad($config->iossNumber ?? '', 12, ' ', STR_PAD_RIGHT);

        // Company name
        $companyName = substr($config->companyName, 0, 80);
        $content .= str_pad($companyName, 80, ' ', STR_PAD_RIGHT);

        // Period and activity indicator
        $content .= $this->formatPeriodForData($config->period, ($config->isQuarterly ? 'T' : 'M'), $config->exerciseYear);
        $content .= str_repeat(' ', 15);
        $content .= $this->hasActivity($ossData) ? '0' : '1';

        // Country VAT entries
        $processedCount = 0;
        foreach ($ossData as $key => $data) {
            if ($processedCount >= self::MAX_RECORDS_PER_SECTION) {
                break;
            }

            // Parse key format: "ES|FR - 20.00%"
            $parsedData = $this->parseOssKey($key, $data);
            if ($parsedData) {
                $content .= $this->formatVatEntry($parsedData['destination_country'], $parsedData['vat_rate'], $data);
                $processedCount++;
            }
        }

        // Pad to proper length
        $content .= str_repeat(' ', max(0, 2100 - strlen($content . '</T36901>')));

        $content .= '</T36901>';

        return $content;
    }

    /**
     * Generate T36910 section (IMPO main data)
     */
    private function generateT36910(array $iossData, Form369Config $config, bool $needsComplementaryPage): string
    {
        $content = '<T36910>';

        // IMPO regime
        $content .= 'IMPO DO';

        // Padding to position 41
        $content .= str_repeat(' ', 41 - strlen('<T36910>IMPO DO'));

        // Complementary page indicator
        $content .= $needsComplementaryPage ? 'C' : ' ';

        // Space and country/NIF
        $content .= ' ES' . $config->declarantNif;

        // IOSS number (required for IMPO)
        $iossNumber = $config->iossNumber ?? 'IM1234567890';
        $content .= '   ' . str_pad($iossNumber, 12, ' ', STR_PAD_RIGHT);

        // Company name
        $companyName = substr($config->companyName, 0, 80);
        $content .= str_pad($companyName, 80, ' ', STR_PAD_RIGHT);

        // Period and activity indicator
        $content .= $this->formatPeriodForData($config->period, ($config->isQuarterly ? 'T' : 'M'), $config->exerciseYear);
        $content .= str_repeat(' ', 15);
        $content .= $this->hasActivity($iossData) ? '0' : '1';

        // Country VAT entries for IOSS
        $processedCount = 0;
        foreach ($iossData as $key => $data) {
            if ($processedCount >= self::MAX_RECORDS_PER_SECTION) {
                break;
            }

            // Parse key format (same as OSS): "ES|FR - 20.00%"
            $parsedData = $this->parseOssKey($key, $data);
            if ($parsedData) {
                $content .= $this->formatVatEntry($parsedData['destination_country'], $parsedData['vat_rate'], $data);
                $processedCount++;
            }
        }

        // Pad to proper length
        $content .= str_repeat(' ', max(0, 2100 - strlen($content . '</T36910>')));

        $content .= '</T36910>';

        return $content;
    }

    /**
     * Format VAT entry: "CC RRRRX            BBBBBBBB             VVVVVVVV"
     */
    private function formatVatEntry(string $countryCode, string $vatRate, array $amounts): string
    {
        // VAT rate is already cleaned (e.g., "2000")
        $rateType = $this->determineVatType($vatRate);

        // Format: CC RRRRX (country + 4-digit rate + S/R)
        $entry = str_pad($countryCode, 2, ' ', STR_PAD_LEFT) . ' ';
        $entry .= $vatRate . $rateType;

        // Padding to align amounts (total width ~20 chars)
        $entry .= str_repeat(' ', 20 - strlen($entry));

        // Base amount (8-10 digits, right-aligned)
        $baseAmount = round(($amounts['Base (€)'] ?? 0) * 100);
        $entry .= str_pad((string)$baseAmount, 10, '0', STR_PAD_LEFT);

        // Padding between amounts
        $entry .= str_repeat(' ', 13);

        // VAT amount (8 digits, right-aligned) - use IVA (€) for OSS data
        $vatAmount = round(($amounts['IVA (€)'] ?? $amounts['VAT (€)'] ?? 0) * 100);
        $entry .= str_pad((string)$vatAmount, 8, '0', STR_PAD_LEFT);

        return $entry;
    }

    /**
     * Determine if VAT rate is standard (S) or reduced (R)
     */
    private function determineVatType(string $rate): string
    {
        $numericRate = (int)$rate;

        // Common standard rates in EU
        $standardRates = [1900, 2000, 2100, 2200, 2300, 2400, 2500];

        return in_array($numericRate, $standardRates) ? 'S' : 'R';
    }

    /**
     * Format period for header tag
     */
    private function formatPeriodForHeader(string $period, string $periodType): string
    {
        if ($periodType === 'T') {
            // Quarterly: extract quarter number and format as QT
            if (preg_match('/T\s*(\d)/', $period, $matches)) {
                return $matches[1] . 'T';
            }
            return '1T'; // Default to Q1
        } else {
            // Monthly: format as MM
            if (preg_match('/(\d{1,2})/', $period, $matches)) {
                return str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            }
            return '01'; // Default to January
        }
    }

    /**
     * Format period for data section
     */
    private function formatPeriodForData(string $period, string $periodType, string $year): string
    {
        if ($periodType === 'T') {
            // Format: YYYYT Q (e.g., "2025T 1")
            if (preg_match('/T\s*(\d)/', $period, $matches)) {
                return $year . 'T ' . $matches[1];
            }
            return $year . 'T 1';
        } else {
            // Format: YYYYM MM (e.g., "2025M 01")
            if (preg_match('/(\d{1,2})/', $period, $matches)) {
                return $year . 'M ' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            }
            return $year . 'M 01';
        }
    }

    /**
     * Parse OSS key format: "ES|FR - 20.00%"
     * Returns: ['origin_country' => 'ES', 'destination_country' => 'FR', 'vat_rate' => '2000']
     */
    private function parseOssKey(string $key, array $data): ?array
    {
        // Parse key format: "ES|FR - 20.00%"
        if (!preg_match('/^([A-Z]{2})\|([A-Z]{2})\s*-\s*([\d.]+)%/', $key, $matches)) {
            echo "Warning: Could not parse OSS key: {$key}\n";
            return null;
        }

        $originCountry = $matches[1];
        $destinationCountry = $matches[2];
        $vatRatePercent = $matches[3];

        // Convert percentage to 4-digit format (20.00 -> 2000)
        $vatRate = str_pad(str_replace('.', '', $vatRatePercent), 4, '0', STR_PAD_LEFT);

        return [
            'origin_country' => $originCountry,
            'destination_country' => $destinationCountry,
            'vat_rate' => $vatRate
        ];
    }

    /**
     * Check if there's any activity (non-zero amounts)
     */
    private function hasActivity(array $data): bool
    {
        foreach ($data as $entry) {
            if (is_array($entry)) {
                if (($entry['Base (€)'] ?? 0) > 0 || ($entry['IVA (€)'] ?? 0) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Generate empty sections with proper padding
     */
    private function generateT36905(array $ossData, Form369Config $config): string
    {
        // T36905 can contain overflow entries or special cases
        $content = '<T36905>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36905>'));
        $content .= '</T36905>';
        return $content;
    }

    private function generateT36906(Form369Config $config): string
    {
        $content = '<T36906>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36906>'));
        $content .= '</T36906>';
        return $content;
    }

    private function generateT36907(Form369Config $config): string
    {
        $content = '<T36907>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36907>'));
        $content .= '</T36907>';
        return $content;
    }

    private function generateT36908(Form369Config $config): string
    {
        $content = '<T36908>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36908>'));
        $content .= '</T36908>';
        return $content;
    }

    private function generateT36909(): string
    {
        $content = '<T36909>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36909>'));
        $content .= '</T36909>';
        return $content;
    }

    private function generateT36902(Form369Config $config): string
    {
        $content = '<T36902>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36902>'));
        $content .= '</T36902>';
        return $content;
    }

    private function generateT36903(): string
    {
        $content = '<T36903>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36903>'));
        $content .= '</T36903>';
        return $content;
    }

    private function generateT36911(Form369Config $config): string
    {
        $content = '<T36911>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36911>'));
        $content .= '</T36911>';
        return $content;
    }

    private function generateT36912(): string
    {
        $content = '<T36912>';
        $content .= str_repeat(' ', 2100 - strlen($content . '</T36912>'));
        $content .= '</T36912>';
        return $content;
    }

    /**
     * Generate footer with correct format
     */
    private function generateFooter(Form369Config $config): string
    {
        $periodType = $config->isQuarterly ? 'T' : 'M';
        $periodFormatted = $this->formatPeriodForHeader($config->period, $periodType);
        $year = $this->extractYearFromPeriod($config->period);
        $footerTag = "T369{$year}{$periodFormatted}0000";

        return "</{$footerTag}>";
    }

    /**
     * Extract year from period string (e.g., "2025 T 1" -> "2025")
     */
    private function extractYearFromPeriod(string $period): string
    {
        if (preg_match('/(\d{4})/', $period, $matches)) {
            return $matches[1];
        }
        return date('Y'); // Default to current year
    }

    /**
     * Validate OSS/IOSS data before generating Form 369
     */
    public function validateData(array $ossData, array $iossData, Form369Config $config = null): array
    {
        $errors = [];

        // EU country codes for validation (including Spain for OSS)
        $validCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'SE'
        ];

        // Validate OSS data structure (key format: "ES|FR - 20.00%")
        foreach ($ossData as $key => $data) {
            if (!str_contains($key, '|')) {
                $errors[] = "Invalid OSS key format: {$key}";
                continue;
            }

            // Parse the key to extract country codes
            $parsedData = $this->parseOssKey($key, $data);
            if (!$parsedData) {
                $errors[] = "Could not parse OSS key: {$key}";
                continue;
            }

            // Validate destination country code
            if (!in_array($parsedData['destination_country'], $validCountries)) {
                $errors[] = "Invalid country code: {$parsedData['destination_country']} in key: {$key}";
                continue;
            }

            if (!is_array($data)) {
                $errors[] = "Invalid OSS data structure for key: {$key}";
                continue;
            }

            if (!is_numeric($data['Base (€)'] ?? 0)) {
                $errors[] = "Invalid base amount for OSS key: {$key}";
            }
            if (!is_numeric($data['IVA (€)'] ?? 0)) {
                $errors[] = "Invalid VAT amount for OSS key: {$key}";
            }
        }

        // Validate IOSS data structure (similar format expected)
        foreach ($iossData as $key => $data) {
            if (!is_array($data)) {
                $errors[] = "Invalid IOSS data structure for key: {$key}";
                continue;
            }

            if (!is_numeric($data['Base (€)'] ?? 0)) {
                $errors[] = "Invalid IOSS base amount for key: {$key}";
            }
        }

        return $errors;
    }
}
