<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use League\Csv\Reader;
use League\Csv\Exception;

class CsvPeriodAnalyzer
{
    /**
     * Analyze CSV file to detect ACTIVITY_PERIOD information
     *
     * @param UploadedFile|string $file
     * @return array{
     *   is_valid: bool,
     *   periods: array,
     *   period_count: int,
     *   required_credits: int,
     *   error_message: string|null,
     *   has_activity_period_column: bool
     * }
     */
    public function analyzePeriods(UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getPathname() : $file;

        try {
            // Basic file validation
            $this->validateFile($file);

            $csv = Reader::createFromPath($filePath, 'r');

            // Detect and set encoding
            $encoding = $this->detectEncoding($filePath);
            if ($encoding !== 'UTF-8') {
                $csv->addStreamFilter('convert.iconv.' . $encoding . '/UTF-8');
            }

            // Set delimiter automatically
            $csv->setDelimiter($this->detectDelimiter($csv));

            // Get headers
            $header = $csv->fetchOne(0);
            if (!$header) {
                return $this->createErrorResponse('El archivo CSV está vacío o no se puede leer');
            }

            // Check for ACTIVITY_PERIOD column
            if (!in_array('ACTIVITY_PERIOD', $header)) {
                return $this->createErrorResponse(
                    'El archivo CSV debe contener la columna "ACTIVITY_PERIOD" requerida para el procesamiento'
                );
            }

                        // Get all records to analyze periods
            $records = iterator_to_array($csv->getRecords($header));

            if (empty($records)) {
                return $this->createErrorResponse('El archivo CSV no contiene datos');
            }

            // Extract and analyze ACTIVITY_PERIOD values
            $periods = [];
            foreach ($records as $record) {
                $period = trim($record['ACTIVITY_PERIOD'] ?? '');
                // Skip empty periods and the header value itself
                if (!empty($period) && $period !== 'ACTIVITY_PERIOD') {
                    $periods[] = $period;
                }
            }

            if (empty($periods)) {
                return $this->createErrorResponse(
                    'No se encontraron valores válidos en la columna ACTIVITY_PERIOD'
                );
            }

            // Get unique periods
            $uniquePeriods = array_unique($periods);
            $periodCount = count($uniquePeriods);

            // Validate period count (max 3 as per transformer logic)
            if ($periodCount > 3) {
                return $this->createErrorResponse(
                    "El archivo contiene más de 3 períodos distintos ({$periodCount}): " .
                    implode(', ', $uniquePeriods) . ". El máximo permitido es 3."
                );
            }

            // Calculate required credits (1 credit per period/month)
            $requiredCredits = $periodCount;

            return [
                'is_valid' => true,
                'periods' => array_values($uniquePeriods),
                'period_count' => $periodCount,
                'required_credits' => $requiredCredits,
                'error_message' => null,
                'has_activity_period_column' => true,
            ];

        } catch (Exception $e) {
            return $this->createErrorResponse(
                'Error al analizar el archivo CSV: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            return $this->createErrorResponse(
                'Error inesperado al procesar el archivo: ' . $e->getMessage()
            );
        }
    }

    /**
     * Create error response array
     */
    private function createErrorResponse(string $message): array
    {
        return [
            'is_valid' => false,
            'periods' => [],
            'period_count' => 0,
            'required_credits' => 0,
            'error_message' => $message,
            'has_activity_period_column' => false,
        ];
    }

    /**
     * Validate uploaded file before processing
     */
    private function validateFile(UploadedFile|string $file): void
    {
        if ($file instanceof UploadedFile) {
            if (!$file->isValid()) {
                throw new InvalidArgumentException('El archivo subido no es válido');
            }

            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, ['text/csv', 'text/plain', 'application/csv'], true)) {
                throw new InvalidArgumentException('El archivo debe ser un CSV válido');
            }
        } else {
            if (!file_exists($file) || !is_readable($file)) {
                throw new InvalidArgumentException('El archivo no existe o no se puede leer');
            }
        }
    }

    /**
     * Detect file encoding
     */
    private function detectEncoding(string $filePath): string
    {
        $sample = file_get_contents($filePath, false, null, 0, 8192);
        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252'];

        foreach ($encodings as $encoding) {
            if (mb_check_encoding($sample, $encoding)) {
                return $encoding;
            }
        }

        return 'UTF-8'; // Default fallback
    }

    /**
     * Detect CSV delimiter
     */
    private function detectDelimiter(Reader $csv): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $filePath = $csv->getPathname();

        $bestDelimiter = ',';
        $maxFields = 0;

                foreach ($delimiters as $delimiter) {
            try {
                $testCsv = Reader::createFromPath($filePath, 'r');
                $testCsv->setDelimiter($delimiter);
                $fields = $testCsv->nth(0);

                if (is_array($fields) && count($fields) > $maxFields) {
                    $maxFields = count($fields);
                    $bestDelimiter = $delimiter;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $bestDelimiter;
    }
}
