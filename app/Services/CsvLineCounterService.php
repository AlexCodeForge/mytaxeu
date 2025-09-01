<?php

declare(strict_types=1);

namespace App\Services;

use League\Csv\Reader;
use SplFileInfo;

class CsvLineCounterService
{
    /**
     * Count the number of lines in a CSV file efficiently using league/csv.
     */
    public function countLines(string $filePath): int
    {
        $fileInfo = new SplFileInfo($filePath);

        if (!$fileInfo->isFile() || !$fileInfo->isReadable()) {
            throw new \InvalidArgumentException("File does not exist or is not readable: {$filePath}");
        }

        // Handle empty files
        if ($fileInfo->getSize() === 0) {
            return 0;
        }

        try {
            $reader = Reader::createFromPath($filePath, 'r');

            // Count all rows including header
            $count = 0;
            foreach ($reader as $record) {
                $count++;
            }

            return $count;

        } catch (\Exception $e) {
            // Fallback to simple line counting if CSV parsing fails
            return $this->countLinesSimple($filePath);
        }
    }

    /**
     * Simple line counting fallback method.
     */
    private function countLinesSimple(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Unable to open file: {$filePath}");
        }

        $count = 0;
        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $count++;
            }
        }

        fclose($handle);
        return $count;
    }

    /**
     * Count lines with memory-efficient streaming for very large files.
     */
    public function countLinesStream(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Unable to open file: {$filePath}");
        }

        $count = 0;
        $buffer = 8192; // 8KB buffer

        while (!feof($handle)) {
            $chunk = fread($handle, $buffer);
            if ($chunk !== false) {
                $count += substr_count($chunk, "\n");
            }
        }

        fclose($handle);

        // Check if file ends with newline
        $handle = fopen($filePath, 'r');
        if ($handle) {
            fseek($handle, -1, SEEK_END);
            $lastChar = fgetc($handle);
            if ($lastChar !== "\n" && ftell($handle) > 0) {
                $count++; // Add 1 if file doesn't end with newline
            }
            fclose($handle);
        }

        return $count;
    }

    /**
     * Get CSV file information including line count and basic statistics.
     */
    public function getFileInfo(string $filePath): array
    {
        $fileInfo = new SplFileInfo($filePath);

        if (!$fileInfo->isFile()) {
            throw new \InvalidArgumentException("File does not exist: {$filePath}");
        }

        $lineCount = $this->countLines($filePath);
        $fileSize = $fileInfo->getSize();

        $info = [
            'line_count' => $lineCount,
            'file_size_bytes' => $fileSize,
            'file_size_mb' => round($fileSize / 1024 / 1024, 2),
            'estimated_memory_usage_mb' => round(($fileSize * 1.5) / 1024 / 1024, 2), // Rough estimate
        ];

        // Try to get column information if it's a valid CSV
        try {
            if ($lineCount > 0) {
                $reader = Reader::createFromPath($filePath, 'r');
                $reader->setHeaderOffset(0);

                $headers = $reader->getHeader();
                $info['column_count'] = count($headers);
                $info['headers'] = $headers;
                $info['data_rows'] = max(0, $lineCount - 1); // Subtract header row
            }
        } catch (\Exception $e) {
            // CSV parsing failed, basic info is still available
            $info['column_count'] = null;
            $info['headers'] = [];
            $info['data_rows'] = $lineCount;
        }

        return $info;
    }
}
