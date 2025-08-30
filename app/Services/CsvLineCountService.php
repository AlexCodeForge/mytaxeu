<?php

declare(strict_types=1);

namespace App\Services;

use League\Csv\Reader;
use League\Csv\Exception;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class CsvLineCountService
{
    public const MAX_FILE_SIZE_MB = 10;
    public const SUPPORTED_ENCODINGS = ['UTF-8', 'ISO-8859-1', 'Windows-1252'];

    /**
     * Count lines in a CSV file efficiently without loading it entirely into memory
     *
     * @param UploadedFile|string $file File path or UploadedFile instance
     * @return int Number of data lines (excluding header)
     * @throws InvalidArgumentException If file is invalid or too large
     * @throws Exception If CSV parsing fails
     */
    public function countLines(UploadedFile|string $file): int
    {
        $filePath = $file instanceof UploadedFile ? $file->getPathname() : $file;
        
        $this->validateFile($file);
        
        try {
            $csv = Reader::createFromPath($filePath, 'r');
            
            // Detect and set encoding
            $encoding = $this->detectEncoding($filePath);
            if ($encoding !== 'UTF-8') {
                $csv->addStreamFilter('convert.iconv.' . $encoding . '/UTF-8');
            }
            
            // Set delimiter automatically
            $csv->setDelimiter($this->detectDelimiter($csv));
            
            // Count records efficiently using iterator
            $count = 0;
            foreach ($csv->getRecords() as $record) {
                $count++;
            }
            
            return $count;
            
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                'Invalid CSV file: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get detailed CSV information including line count, headers, and validation
     *
     * @param UploadedFile|string $file
     * @return array{line_count: int, has_header: bool, headers: array, delimiter: string, encoding: string}
     * @throws InvalidArgumentException
     */
    public function analyzeFile(UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getPathname() : $file;
        
        $this->validateFile($file);
        
        try {
            $csv = Reader::createFromPath($filePath, 'r');
            
            $encoding = $this->detectEncoding($filePath);
            if ($encoding !== 'UTF-8') {
                $csv->addStreamFilter('convert.iconv.' . $encoding . '/UTF-8');
            }
            
            $delimiter = $this->detectDelimiter($csv);
            $csv->setDelimiter($delimiter);
            
            // Get headers and determine if file has header row
            $records = iterator_to_array($csv->getRecords());
            $firstRecord = $records[0] ?? [];
            $hasHeader = $this->detectHeader($records);
            
            $lineCount = count($records);
            if ($hasHeader && $lineCount > 0) {
                $lineCount--; // Subtract header row
            }
            
            return [
                'line_count' => $lineCount,
                'has_header' => $hasHeader,
                'headers' => $hasHeader ? $firstRecord : [],
                'delimiter' => $delimiter,
                'encoding' => $encoding,
            ];
            
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                'Invalid CSV file: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Validate uploaded file before processing
     */
    private function validateFile(UploadedFile|string $file): void
    {
        if ($file instanceof UploadedFile) {
            if (!$file->isValid()) {
                throw new InvalidArgumentException('Uploaded file is invalid');
            }
            
            if ($file->getSize() > self::MAX_FILE_SIZE_MB * 1024 * 1024) {
                throw new InvalidArgumentException(
                    'File size exceeds maximum allowed: ' . self::MAX_FILE_SIZE_MB . 'MB'
                );
            }
            
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, ['text/csv', 'text/plain', 'application/csv'], true)) {
                throw new InvalidArgumentException('File must be a CSV file');
            }
        } else {
            if (!file_exists($file) || !is_readable($file)) {
                throw new InvalidArgumentException('File does not exist or is not readable');
            }
            
            if (filesize($file) > self::MAX_FILE_SIZE_MB * 1024 * 1024) {
                throw new InvalidArgumentException(
                    'File size exceeds maximum allowed: ' . self::MAX_FILE_SIZE_MB . 'MB'
                );
            }
        }
    }

    /**
     * Detect file encoding
     */
    private function detectEncoding(string $filePath): string
    {
        $sample = file_get_contents($filePath, false, null, 0, 8192);
        
        foreach (self::SUPPORTED_ENCODINGS as $encoding) {
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
        $delimiters = [',', ';', '\t', '|'];
        $sample = $csv->fetchOne(0);
        
        if (empty($sample)) {
            return ','; // Default fallback
        }
        
        $bestDelimiter = ',';
        $maxFields = 0;
        
        foreach ($delimiters as $delimiter) {
            $testCsv = clone $csv;
            $testCsv->setDelimiter($delimiter);
            $fields = $testCsv->fetchOne(0);
            
            if (is_array($fields) && count($fields) > $maxFields) {
                $maxFields = count($fields);
                $bestDelimiter = $delimiter;
            }
        }
        
        return $bestDelimiter;
    }

    /**
     * Detect if CSV has header row
     */
    private function detectHeader(array $records): bool
    {
        if (count($records) < 2) {
            return false;
        }
        
        $firstRow = $records[0];
        $secondRow = $records[1];
        
        // If first row has string values and second row has numeric/different types
        // it's likely a header
        foreach ($firstRow as $index => $value) {
            if (isset($secondRow[$index])) {
                if (is_numeric($secondRow[$index]) && !is_numeric($value)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}

