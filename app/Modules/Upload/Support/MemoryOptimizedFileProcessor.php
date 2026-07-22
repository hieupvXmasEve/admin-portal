<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MemoryOptimizedFileProcessor
{
    /**
     * Buffer size for file operations (default 8KB).
     */
    protected int $bufferSize;

    /**
     * Memory limit threshold (80% of available memory).
     */
    protected int $memoryThreshold;

    /**
     * Maximum file size for in-memory processing.
     */
    protected int $maxInMemorySize;

    public function __construct()
    {
        $this->bufferSize = config('uploads.performance.buffer_size', 8192); // 8KB
        $this->memoryThreshold = $this->calculateMemoryThreshold();
        $this->maxInMemorySize = config('uploads.performance.max_in_memory_size', 10 * 1024 * 1024); // 10MB
    }

    /**
     * Process file with memory optimization.
     */
    public function processFile(UploadedFile $file, callable $processor): mixed
    {
        $fileSize = $file->getSize();
        $initialMemory = memory_get_usage(true);

        Log::debug('Starting memory-optimized file processing', [
            'filename' => $file->getClientOriginalName(),
            'file_size' => $fileSize,
            'initial_memory' => $initialMemory,
            'memory_limit' => ini_get('memory_limit'),
        ]);

        try {
            if ($fileSize <= $this->maxInMemorySize && $this->hasAvailableMemory($fileSize)) {
                return $this->processInMemory($file, $processor);
            } else {
                return $this->processStreaming($file, $processor);
            }
        } finally {
            $finalMemory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);

            Log::debug('File processing completed', [
                'filename' => $file->getClientOriginalName(),
                'initial_memory' => $initialMemory,
                'final_memory' => $finalMemory,
                'peak_memory' => $peakMemory,
                'memory_used' => $finalMemory - $initialMemory,
            ]);

            // Force garbage collection if memory usage is high
            if ($peakMemory > $this->memoryThreshold) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Process file in memory (for small files).
     */
    protected function processInMemory(UploadedFile $file, callable $processor): mixed
    {
        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            throw new RuntimeException('Unable to read file content');
        }

        return $processor($content);
    }

    /**
     * Process file using streaming (for large files).
     */
    protected function processStreaming(UploadedFile $file, callable $processor): mixed
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if (! $handle) {
            throw new RuntimeException('Unable to open file for streaming');
        }

        try {
            return $processor($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Copy file with memory optimization.
     */
    public function copyFile(string $source, string $destination): bool
    {
        $sourceHandle = fopen($source, 'rb');
        $destHandle = fopen($destination, 'wb');

        if (! $sourceHandle || ! $destHandle) {
            if ($sourceHandle) {
                fclose($sourceHandle);
            }
            if ($destHandle) {
                fclose($destHandle);
            }

            return false;
        }

        try {
            while (! feof($sourceHandle)) {
                $chunk = fread($sourceHandle, $this->bufferSize);
                if ($chunk === false) {
                    return false;
                }

                if (fwrite($destHandle, $chunk) === false) {
                    return false;
                }

                // Check memory usage periodically
                if (memory_get_usage(true) > $this->memoryThreshold) {
                    gc_collect_cycles();
                }
            }

            return true;

        } finally {
            fclose($sourceHandle);
            fclose($destHandle);
        }
    }

    /**
     * Calculate file hash with memory optimization.
     */
    public function calculateHash(string $filePath, string $algorithm = 'sha256'): string
    {
        $context = hash_init($algorithm);
        $handle = fopen($filePath, 'rb');

        if (! $handle) {
            throw new RuntimeException('Unable to open file for hashing');
        }

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, $this->bufferSize);
                if ($chunk === false) {
                    throw new RuntimeException('Error reading file for hashing');
                }

                hash_update($context, $chunk);

                // Periodic memory check
                if (memory_get_usage(true) > $this->memoryThreshold) {
                    gc_collect_cycles();
                }
            }

            return hash_final($context);

        } finally {
            fclose($handle);
        }
    }

    /**
     * Validate file integrity with memory optimization.
     */
    public function validateFileIntegrity(UploadedFile $file): array
    {
        $filePath = $file->getRealPath();
        $fileSize = $file->getSize();

        $results = [
            'valid' => true,
            'errors' => [],
            'file_size' => $fileSize,
            'calculated_hash' => null,
            'memory_usage' => [],
        ];

        $initialMemory = memory_get_usage(true);
        $results['memory_usage']['initial'] = $initialMemory;

        try {
            // Calculate hash using streaming
            $results['calculated_hash'] = $this->calculateHash($filePath);

            // Verify file can be read completely
            $this->processFile($file, function ($content) use (&$results) {
                if (is_resource($content)) {
                    // Streaming mode - verify we can read the entire file
                    $bytesRead = 0;
                    while (! feof($content)) {
                        $chunk = fread($content, $this->bufferSize);
                        if ($chunk === false) {
                            $results['valid'] = false;
                            $results['errors'][] = 'Error reading file content';

                            return;
                        }
                        $bytesRead += strlen($chunk);
                    }

                    if ($bytesRead !== $results['file_size']) {
                        $results['valid'] = false;
                        $results['errors'][] = "File size mismatch: expected {$results['file_size']}, read {$bytesRead}";
                    }
                } else {
                    // In-memory mode - verify content length
                    if (strlen($content) !== $results['file_size']) {
                        $results['valid'] = false;
                        $results['errors'][] = 'Content length mismatch';
                    }
                }
            });

        } catch (\Exception $e) {
            $results['valid'] = false;
            $results['errors'][] = $e->getMessage();
        }

        $results['memory_usage']['peak'] = memory_get_peak_usage(true);
        $results['memory_usage']['final'] = memory_get_usage(true);
        $results['memory_usage']['used'] = $results['memory_usage']['final'] - $initialMemory;

        return $results;
    }

    /**
     * Compress file content with memory optimization.
     */
    public function compressFile(string $sourcePath, string $destinationPath, int $compressionLevel = 6): bool
    {
        $sourceHandle = fopen($sourcePath, 'rb');
        $destHandle = gzopen($destinationPath, "wb{$compressionLevel}");

        if (! $sourceHandle || ! $destHandle) {
            if ($sourceHandle) {
                fclose($sourceHandle);
            }
            if ($destHandle) {
                gzclose($destHandle);
            }

            return false;
        }

        try {
            while (! feof($sourceHandle)) {
                $chunk = fread($sourceHandle, $this->bufferSize);
                if ($chunk === false) {
                    return false;
                }

                if (gzwrite($destHandle, $chunk) === false) {
                    return false;
                }

                // Memory management
                if (memory_get_usage(true) > $this->memoryThreshold) {
                    gc_collect_cycles();
                }
            }

            return true;

        } finally {
            fclose($sourceHandle);
            gzclose($destHandle);
        }
    }

    /**
     * Decompress file with memory optimization.
     */
    public function decompressFile(string $sourcePath, string $destinationPath): bool
    {
        $sourceHandle = gzopen($sourcePath, 'rb');
        $destHandle = fopen($destinationPath, 'wb');

        if (! $sourceHandle || ! $destHandle) {
            if ($sourceHandle) {
                gzclose($sourceHandle);
            }
            if ($destHandle) {
                fclose($destHandle);
            }

            return false;
        }

        try {
            while (! gzeof($sourceHandle)) {
                $chunk = gzread($sourceHandle, $this->bufferSize);
                if ($chunk === false) {
                    return false;
                }

                if (fwrite($destHandle, $chunk) === false) {
                    return false;
                }

                // Memory management
                if (memory_get_usage(true) > $this->memoryThreshold) {
                    gc_collect_cycles();
                }
            }

            return true;

        } finally {
            gzclose($sourceHandle);
            fclose($destHandle);
        }
    }

    /**
     * Check if there's enough available memory for operation.
     */
    protected function hasAvailableMemory(int $requiredBytes): bool
    {
        $currentUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));

        if ($memoryLimit === -1) {
            return true; // No memory limit
        }

        $availableMemory = $memoryLimit - $currentUsage;
        $requiredMemory = $requiredBytes * 2; // Account for processing overhead

        return $availableMemory > $requiredMemory;
    }

    /**
     * Calculate memory threshold (80% of available memory).
     */
    protected function calculateMemoryThreshold(): int
    {
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));

        if ($memoryLimit === -1) {
            return PHP_INT_MAX; // No limit
        }

        return (int) ($memoryLimit * 0.8);
    }

    /**
     * Parse memory limit string to bytes.
     */
    protected function parseMemoryLimit(string $memoryLimit): int
    {
        if ($memoryLimit === '-1') {
            return -1;
        }

        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Get current memory usage statistics.
     */
    public function getMemoryStats(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'memory_limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
            'memory_threshold' => $this->memoryThreshold,
            'buffer_size' => $this->bufferSize,
            'max_in_memory_size' => $this->maxInMemorySize,
        ];
    }

    /**
     * Optimize memory usage by forcing garbage collection.
     */
    public function optimizeMemory(): array
    {
        $beforeMemory = memory_get_usage(true);
        $beforePeak = memory_get_peak_usage(true);

        // Force garbage collection
        gc_collect_cycles();

        $afterMemory = memory_get_usage(true);
        $freed = $beforeMemory - $afterMemory;

        return [
            'memory_before' => $beforeMemory,
            'memory_after' => $afterMemory,
            'memory_freed' => $freed,
            'peak_usage' => $beforePeak,
        ];
    }

    /**
     * Set buffer size for file operations.
     */
    public function setBufferSize(int $bufferSize): void
    {
        if ($bufferSize < 1024) {
            throw new \InvalidArgumentException('Buffer size must be at least 1KB');
        }

        $this->bufferSize = $bufferSize;
    }

    /**
     * Set maximum file size for in-memory processing.
     */
    public function setMaxInMemorySize(int $maxSize): void
    {
        if ($maxSize < 0) {
            throw new \InvalidArgumentException('Max in-memory size must be non-negative');
        }

        $this->maxInMemorySize = $maxSize;
    }
}
