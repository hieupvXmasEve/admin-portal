<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class FileValidator
{
    /**
     * File signature mappings for validation.
     */
    protected array $fileSignatures = [
        'image/jpeg' => [
            "\xFF\xD8\xFF\xE0", // JPEG JFIF
            "\xFF\xD8\xFF\xE1", // JPEG EXIF
            "\xFF\xD8\xFF\xE2", // JPEG ICC
            "\xFF\xD8\xFF\xE3", // JPEG
            "\xFF\xD8\xFF\xE8", // JPEG SPIFF
            "\xFF\xD8\xFF\xDB", // JPEG
        ],
        'image/png' => [
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", // PNG
        ],
        'image/gif' => [
            'GIF87a', // GIF87a
            'GIF89a', // GIF89a
        ],
        'image/webp' => [
            'RIFF', // WebP (needs additional validation)
        ],
        'image/svg+xml' => [
            '<?xml', // XML declaration
            '<svg',  // SVG root element
        ],
        'image/bmp' => [
            'BM', // BMP
        ],
        'image/tiff' => [
            "II*\x00", // TIFF little-endian
            "MM\x00*", // TIFF big-endian
        ],
    ];

    /**
     * Dangerous file extensions that should be blocked.
     */
    protected array $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'pht',
        'exe', 'com', 'bat', 'cmd', 'scr', 'vbs', 'js',
        'jar', 'app', 'deb', 'rpm', 'dmg', 'pkg',
        'asp', 'aspx', 'jsp', 'cfm', 'cgi', 'pl',
        'sh', 'bash', 'zsh', 'fish', 'ps1', 'psm1',
        'msi', 'dll', 'sys', 'drv', 'ocx', 'cpl',
        'hta', 'wsf', 'wsh', 'reg', 'inf', 'scf',
    ];

    /**
     * Known malicious file signatures and patterns.
     */
    protected array $maliciousSignatures = [
        // PE executable signatures
        'MZ' => 'Portable Executable',
        "\x7fELF" => 'ELF Executable',
        "\xCA\xFE\xBA\xBE" => 'Java Class File',
        "\xFE\xED\xFA\xCE" => 'Mach-O Binary',
        "\xFE\xED\xFA\xCF" => 'Mach-O Binary (64-bit)',

        // Archive signatures that might contain malicious content
        'PK' => 'ZIP Archive',
        "\x1F\x8B" => 'GZIP Archive',
        'Rar!' => 'RAR Archive',
        '7z' => '7-Zip Archive',

        // Script signatures
        '#!/bin/sh' => 'Shell Script',
        '#!/bin/bash' => 'Bash Script',
        '@echo off' => 'Batch Script',
        'powershell' => 'PowerShell Script',
    ];

    /**
     * Virus scanning configuration.
     */
    protected array $virusScanConfig;

    /**
     * Config of the file currently being validated.
     */
    protected array $currentFileConfig = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->virusScanConfig = config('uploads.virus_scanning', [
            'enabled' => env('VIRUS_SCANNING_ENABLED', false),
            'engine' => env('VIRUS_SCANNING_ENGINE', 'clamav'),
            'timeout' => env('VIRUS_SCANNING_TIMEOUT', 30),
            'quarantine_infected' => env('QUARANTINE_INFECTED_FILES', true),
        ]);
    }

    /**
     * Validate file comprehensively.
     */
    public function validateFile(UploadedFile $file, array $config): void
    {
        $this->currentFileConfig = $config;

        try {
            $this->validateBasicFile($file);
            $this->validateFileSize($file, $config);
            $this->validateMimeType($file, $config);
            $this->validateExtension($file, $config);
            $this->validateFileSignature($file);
            $this->validateImageDimensions($file, $config);
            $this->validateFileName($file);
            $this->scanForMaliciousContent($file);
            $this->performAdvancedSecurityChecks($file);
            $this->scanForViruses($file);
        } finally {
            $this->currentFileConfig = [];
        }
    }

    public function canonicalExtension(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidArgumentException('The uploaded file type does not have a canonical extension.'),
        };
    }

    /** @param array<string, mixed> $config */
    private function validateImageDimensions(UploadedFile $file, array $config): void
    {
        if (! isset($config['max_width'], $config['max_height'], $config['max_pixels'])) {
            return;
        }

        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false) {
            throw new InvalidArgumentException('The image content could not be decoded.');
        }

        [$width, $height] = $dimensions;
        if ($width > $config['max_width'] || $height > $config['max_height'] || ($width * $height) > $config['max_pixels']) {
            throw new InvalidArgumentException('The image dimensions exceed the branding limits.');
        }
    }

    /**
     * Validate basic file properties.
     */
    protected function validateBasicFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Invalid file upload: '.$file->getErrorMessage());
        }

        if ($file->getSize() === 0) {
            throw new InvalidArgumentException('Empty file is not allowed');
        }

        if (! is_readable($file->getRealPath())) {
            throw new RuntimeException('Unable to read uploaded file');
        }
    }

    /**
     * Validate file size against configuration.
     */
    protected function validateFileSize(UploadedFile $file, array $config): void
    {
        $maxSize = ($config['max_size'] ?? 10240) * 1024; // Convert KB to bytes

        if ($file->getSize() > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 2);
            $fileSizeMB = round($file->getSize() / (1024 * 1024), 2);

            throw new InvalidArgumentException(
                "File size ({$fileSizeMB}MB) exceeds maximum allowed size of {$maxSizeMB}MB"
            );
        }
    }

    /**
     * Validate MIME type against allowed types.
     */
    protected function validateMimeType(UploadedFile $file, array $config): void
    {
        $allowedTypes = $config['allowed_types'] ?? [];

        if (empty($allowedTypes)) {
            return;
        }

        $mimeType = $file->getMimeType();

        if (! in_array($mimeType, $allowedTypes)) {
            throw new InvalidArgumentException(
                "File type '{$mimeType}' is not allowed. Allowed types: ".implode(', ', $allowedTypes)
            );
        }
    }

    /**
     * Validate file extension against allowed extensions.
     */
    protected function validateExtension(UploadedFile $file, array $config): void
    {
        $allowedExtensions = $config['allowed_extensions'] ?? [];

        if (empty($allowedExtensions)) {
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());

        // Check if extension is in dangerous list
        if (in_array($extension, $this->dangerousExtensions)) {
            throw new InvalidArgumentException(
                "File extension '{$extension}' is not allowed for security reasons"
            );
        }

        if (! in_array($extension, $allowedExtensions)) {
            throw new InvalidArgumentException(
                "File extension '{$extension}' is not allowed. Allowed extensions: ".implode(', ', $allowedExtensions)
            );
        }
    }

    /**
     * Validate file signature matches the declared MIME type.
     */
    protected function validateFileSignature(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();

        if (! isset($this->fileSignatures[$mimeType])) {
            // If we don't have signature validation for this type, skip
            return;
        }

        $filePath = $file->getRealPath();
        $fileHandle = fopen($filePath, 'rb');

        if (! $fileHandle) {
            throw new RuntimeException('Unable to read uploaded file for signature validation');
        }

        $header = fread($fileHandle, 20); // Read more bytes for better validation
        fclose($fileHandle);

        $validSignature = false;
        $signatures = $this->fileSignatures[$mimeType];

        foreach ($signatures as $signature) {
            if (strpos($header, $signature) === 0) {
                $validSignature = true;
                break;
            }
        }

        // Special validation for WebP
        if ($mimeType === 'image/webp' && strpos($header, 'RIFF') === 0) {
            $webpHeader = substr($header, 8, 4);
            $validSignature = ($webpHeader === 'WEBP');
        }

        // Special validation for SVG
        if ($mimeType === 'image/svg+xml') {
            $content = file_get_contents($filePath, false, null, 0, 1024);
            $validSignature = (strpos($content, '<?xml') === 0 || strpos($content, '<svg') !== false);
        }

        if (! $validSignature) {
            throw new InvalidArgumentException(
                "File signature does not match the declared MIME type: {$mimeType}. This may indicate a malicious file."
            );
        }
    }

    /**
     * Validate filename for security issues.
     */
    protected function validateFileName(UploadedFile $file): void
    {
        $filename = $file->getClientOriginalName();

        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            throw new InvalidArgumentException('Filename contains null bytes');
        }

        // Check for directory traversal attempts
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            throw new InvalidArgumentException('Filename contains directory traversal sequences');
        }

        // Check for control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $filename)) {
            throw new InvalidArgumentException('Filename contains control characters');
        }

        // Check filename length
        if (strlen($filename) > 255) {
            throw new InvalidArgumentException('Filename is too long (maximum 255 characters)');
        }

        // Check for reserved names on Windows
        $reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        if (in_array(strtoupper($nameWithoutExt), $reservedNames)) {
            throw new InvalidArgumentException('Filename uses a reserved system name');
        }
    }

    /**
     * Scan file for malicious content.
     */
    protected function scanForMaliciousContent(UploadedFile $file): void
    {
        $filePath = $file->getRealPath();
        $mimeType = $file->getMimeType();

        // For image files, check for embedded scripts
        if (strpos($mimeType, 'image/') === 0) {
            $this->scanImageForScripts($filePath, $mimeType);
        }

        // Check for PHP code in any file
        $this->scanForPhpCode($filePath);

        // Check file size vs content (potential zip bomb detection)
        $this->validateFileIntegrity($file);
    }

    /**
     * Scan image files for embedded scripts.
     */
    protected function scanImageForScripts(string $filePath, string $mimeType): void
    {
        $content = file_get_contents($filePath);

        // Common script patterns to look for
        $dangerousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\(/i',
            '/base64_decode/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new InvalidArgumentException('File contains potentially malicious content');
            }
        }

        // SVG-specific validation
        if ($mimeType === 'image/svg+xml') {
            $this->validateSvgContent($content);
        }
    }

    /**
     * Validate SVG content for security issues.
     */
    protected function validateSvgContent(string $content): void
    {
        // SVG-specific dangerous patterns
        $svgDangerousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/data:image\/svg\+xml/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/<link/i',
            '/<meta/i',
            '/xlink:href/i',
        ];

        foreach ($svgDangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new InvalidArgumentException('SVG file contains potentially dangerous elements');
            }
        }
    }

    /**
     * Scan for PHP code in uploaded files.
     */
    protected function scanForPhpCode(string $filePath): void
    {
        $content = file_get_contents($filePath, false, null, 0, 8192); // Read first 8KB

        if ($content === false) {
            throw new RuntimeException('Unable to read file for PHP code scan');
        }

        // Skip binary blobs (e.g. XLSX/ZIP) to avoid false positives from compressed data.
        if (strpos($content, "\0") !== false) {
            return;
        }

        $phpPatterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<\?(?!xml)/i',
            '/<%/i',
        ];

        foreach ($phpPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new InvalidArgumentException('File contains PHP code and is not allowed');
            }
        }
    }

    /**
     * Validate file integrity and detect potential issues.
     */
    protected function validateFileIntegrity(UploadedFile $file): void
    {
        $fileSize = $file->getSize();
        $filePath = $file->getRealPath();

        // Check if file size matches actual content
        $actualSize = filesize($filePath);

        if ($fileSize !== $actualSize) {
            throw new InvalidArgumentException('File size mismatch detected');
        }

        // Basic zip bomb detection (very large uncompressed vs compressed ratio)
        if ($fileSize > 0) {
            $compressed = gzcompress(file_get_contents($filePath, false, null, 0, 1024));
            if ($compressed !== false) {
                $compressionRatio = strlen($compressed) / min($fileSize, 1024);

                // If compression ratio is suspiciously low, it might be a zip bomb
                if ($compressionRatio < 0.01) {
                    throw new InvalidArgumentException('File appears to contain suspicious compression patterns');
                }
            }
        }
    }

    /**
     * Get file signature information.
     */
    public function getFileSignature(UploadedFile $file): array
    {
        $filePath = $file->getRealPath();
        $fileHandle = fopen($filePath, 'rb');

        if (! $fileHandle) {
            return ['error' => 'Unable to read file'];
        }

        $header = fread($fileHandle, 20);
        fclose($fileHandle);

        return [
            'hex' => bin2hex($header),
            'ascii' => $header,
            'detected_type' => $this->detectTypeFromSignature($header),
        ];
    }

    /**
     * Detect file type from signature.
     */
    protected function detectTypeFromSignature(string $header): ?string
    {
        foreach ($this->fileSignatures as $mimeType => $signatures) {
            foreach ($signatures as $signature) {
                if (strpos($header, $signature) === 0) {
                    return $mimeType;
                }
            }
        }

        return null;
    }

    /**
     * Perform advanced security checks on uploaded files.
     */
    protected function performAdvancedSecurityChecks(UploadedFile $file): void
    {
        $this->checkForMaliciousSignatures($file);
        $this->validateFileEntropy($file);
        $this->checkForPolyglotFiles($file);
        $this->validateImageMetadata($file);
        $this->checkForSteganography($file);
    }

    /**
     * Check for known malicious file signatures.
     */
    protected function checkForMaliciousSignatures(UploadedFile $file): void
    {
        $filePath = $file->getRealPath();
        $fileHandle = fopen($filePath, 'rb');

        if (! $fileHandle) {
            throw new RuntimeException('Unable to read file for malicious signature check');
        }

        $header = fread($fileHandle, 512); // Read more bytes for comprehensive check
        fclose($fileHandle);

        $allowedExtensions = array_map('strtolower', $this->currentFileConfig['allowed_extensions'] ?? []);
        $allowedMimeTypes = array_map('strtolower', $this->currentFileConfig['allowed_types'] ?? []);

        foreach ($this->maliciousSignatures as $signature => $description) {
            if (strpos($header, $signature) === 0) {
                if ($signature === 'PK') {
                    $extension = strtolower($file->getClientOriginalExtension() ?? '');
                    $mimeType = strtolower($file->getMimeType() ?? '');

                    $zipBasedExtensions = ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];
                    $zipBasedMimeTypes = [
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/vnd.oasis.opendocument.text',
                        'application/vnd.oasis.opendocument.spreadsheet',
                        'application/vnd.oasis.opendocument.presentation',
                    ];

                    $zipIsAllowed = in_array($extension, $zipBasedExtensions, true)
                        || in_array($mimeType, $zipBasedMimeTypes, true)
                        || in_array('zip', $allowedExtensions, true)
                        || in_array('application/zip', $allowedMimeTypes, true)
                        || in_array('application/x-zip-compressed', $allowedMimeTypes, true);

                    if ($zipIsAllowed) {
                        continue;
                    }
                }

                Log::warning('Malicious file signature detected', [
                    'filename' => $file->getClientOriginalName(),
                    'signature' => $description,
                    'mime_type' => $file->getMimeType(),
                ]);

                throw new InvalidArgumentException(
                    "File contains a potentially malicious signature: {$description}"
                );
            }
        }

        // Check for embedded executables in images
        if (strpos($file->getMimeType(), 'image/') === 0) {
            $this->checkForEmbeddedExecutables($header, $file);
        }
    }

    /**
     * Check for embedded executables in image files.
     */
    protected function checkForEmbeddedExecutables(string $content, UploadedFile $file): void
    {
        // Look for PE header (Windows executables) embedded in images
        if (strpos($content, 'MZ') !== false && strpos($content, 'PE') !== false) {
            throw new InvalidArgumentException('Image file contains embedded executable code');
        }

        // Look for ELF header (Linux executables)
        if (strpos($content, "\x7fELF") !== false) {
            throw new InvalidArgumentException('Image file contains embedded ELF executable');
        }

        // Check for suspicious patterns that might indicate steganography
        $suspiciousPatterns = [
            'eval(',
            'base64_decode(',
            'gzinflate(',
            'str_rot13(',
            'system(',
            'exec(',
            'shell_exec(',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                Log::warning('Suspicious pattern detected in image', [
                    'filename' => $file->getClientOriginalName(),
                    'pattern' => $pattern,
                ]);

                throw new InvalidArgumentException('Image file contains suspicious code patterns');
            }
        }
    }

    /**
     * Validate file entropy to detect packed or encrypted content.
     */
    protected function validateFileEntropy(UploadedFile $file): void
    {
        $filePath = $file->getRealPath();
        $sampleSize = min(8192, $file->getSize()); // Sample first 8KB or entire file if smaller

        $content = file_get_contents($filePath, false, null, 0, $sampleSize);
        if ($content === false) {
            return; // Skip entropy check if we can't read the file
        }

        $entropy = $this->calculateEntropy($content);

        // High entropy might indicate encrypted/packed content
        // Images typically have moderate entropy, text files have low entropy
        $mimeType = $file->getMimeType();
        $maxEntropy = $this->getMaxEntropyForMimeType($mimeType);

        if ($entropy > $maxEntropy) {
            Log::warning('High entropy detected in uploaded file', [
                'filename' => $file->getClientOriginalName(),
                'entropy' => $entropy,
                'max_allowed' => $maxEntropy,
                'mime_type' => $mimeType,
            ]);

            // Don't block high entropy files by default, just log for monitoring
            // Uncomment the line below to block high entropy files
            // throw new InvalidArgumentException('File has suspiciously high entropy, may be encrypted or packed');
        }
    }

    /**
     * Calculate Shannon entropy of a string.
     */
    protected function calculateEntropy(string $data): float
    {
        $length = strlen($data);
        if ($length === 0) {
            return 0;
        }

        $frequencies = array_count_values(str_split($data));
        $entropy = 0;

        foreach ($frequencies as $frequency) {
            $probability = $frequency / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    /**
     * Get maximum allowed entropy for a MIME type.
     */
    protected function getMaxEntropyForMimeType(string $mimeType): float
    {
        $entropyLimits = [
            'image/jpeg' => 7.5,
            'image/png' => 7.8,
            'image/gif' => 6.5,
            'image/webp' => 7.5,
            'image/svg+xml' => 5.0,
            'image/bmp' => 6.0,
            'image/tiff' => 7.0,
        ];

        return $entropyLimits[$mimeType] ?? 8.0; // Default high limit for unknown types
    }

    /**
     * Check for polyglot files (files that are valid in multiple formats).
     */
    protected function checkForPolyglotFiles(UploadedFile $file): void
    {
        $filePath = $file->getRealPath();
        $content = file_get_contents($filePath, false, null, 0, 1024);

        if ($content === false) {
            return;
        }

        $detectedTypes = [];

        // Check against all known signatures
        foreach ($this->fileSignatures as $mimeType => $signatures) {
            foreach ($signatures as $signature) {
                if (strpos($content, $signature) !== false) {
                    $detectedTypes[] = $mimeType;
                    break;
                }
            }
        }

        // If file matches multiple image formats, it might be a polyglot
        if (count($detectedTypes) > 1) {
            Log::warning('Potential polyglot file detected', [
                'filename' => $file->getClientOriginalName(),
                'detected_types' => $detectedTypes,
                'declared_type' => $file->getMimeType(),
            ]);

            // For now, just log polyglot detection
            // Uncomment to block polyglot files
            // throw new InvalidArgumentException('File appears to be a polyglot (valid in multiple formats)');
        }
    }

    /**
     * Validate image metadata for suspicious content.
     */
    protected function validateImageMetadata(UploadedFile $file): void
    {
        if (strpos($file->getMimeType(), 'image/') !== 0) {
            return; // Only validate image files
        }

        $filePath = $file->getRealPath();

        try {
            // Check EXIF data for JPEG files
            if ($file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
                $exifData = @exif_read_data($filePath);

                if ($exifData !== false) {
                    $this->validateExifData($exifData, $file);
                }
            }

            // Check for suspicious metadata in all image types
            $this->checkImageForSuspiciousMetadata($filePath, $file);

        } catch (\Exception $e) {
            Log::warning('Error validating image metadata', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate EXIF data for suspicious content.
     */
    protected function validateExifData(array $exifData, UploadedFile $file): void
    {
        $suspiciousFields = ['UserComment', 'ImageDescription', 'Artist', 'Copyright'];

        foreach ($suspiciousFields as $field) {
            if (isset($exifData[$field])) {
                $value = $exifData[$field];

                // Check for script tags or suspicious patterns
                if (preg_match('/<script|javascript:|vbscript:|data:|eval\(|base64_decode/i', $value)) {
                    throw new InvalidArgumentException('Image EXIF data contains suspicious content');
                }

                // Check for excessively long metadata (potential buffer overflow attempt)
                if (strlen($value) > 1000) {
                    Log::warning('Excessively long EXIF data detected', [
                        'filename' => $file->getClientOriginalName(),
                        'field' => $field,
                        'length' => strlen($value),
                    ]);
                }
            }
        }
    }

    /**
     * Check image for suspicious metadata patterns.
     */
    protected function checkImageForSuspiciousMetadata(string $filePath, UploadedFile $file): void
    {
        // Read a larger portion of the file to check for embedded data
        $content = file_get_contents($filePath, false, null, 0, 16384); // First 16KB

        if ($content === false) {
            return;
        }

        // Look for suspicious patterns that might be hidden in metadata
        $suspiciousPatterns = [
            '/\$_[A-Z]+\[/',  // PHP superglobals
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/gzinflate\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/<\?php/i',
            '/<script/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new InvalidArgumentException('Image metadata contains suspicious code patterns');
            }
        }
    }

    /**
     * Basic steganography detection.
     */
    protected function checkForSteganography(UploadedFile $file): void
    {
        if (strpos($file->getMimeType(), 'image/') !== 0) {
            return;
        }

        $filePath = $file->getRealPath();
        $fileSize = $file->getSize();

        // Simple heuristic: check if file size is unusually large for its dimensions
        try {
            $imageInfo = @getimagesize($filePath);

            if ($imageInfo !== false) {
                [$width, $height] = $imageInfo;
                $expectedSize = $this->estimateImageSize($width, $height, $file->getMimeType());

                // If actual size is significantly larger than expected, might contain hidden data
                if ($fileSize > $expectedSize * 3) {
                    Log::warning('Potential steganography detected', [
                        'filename' => $file->getClientOriginalName(),
                        'actual_size' => $fileSize,
                        'expected_size' => $expectedSize,
                        'ratio' => $fileSize / $expectedSize,
                        'dimensions' => "{$width}x{$height}",
                    ]);

                    // For now, just log potential steganography
                    // Uncomment to block files with potential hidden data
                    // throw new InvalidArgumentException('Image may contain hidden data (steganography)');
                }
            }
        } catch (\Exception $e) {
            // Ignore errors in steganography detection
        }
    }

    /**
     * Estimate expected file size for an image.
     */
    protected function estimateImageSize(int $width, int $height, string $mimeType): int
    {
        $pixels = $width * $height;

        $bytesPerPixel = match ($mimeType) {
            'image/jpeg' => 0.5,
            'image/png', 'image/bmp' => 3,
            'image/gif' => 1,
            'image/webp' => 0.3,
            default => 2,
        };

        return max(1, (int) ceil($pixels * $bytesPerPixel));
    }

    /**
     * Scan file for viruses using configured antivirus engine.
     */
    protected function scanForViruses(UploadedFile $file): void
    {
        if (! $this->virusScanConfig['enabled']) {
            return;
        }

        $engine = $this->virusScanConfig['engine'];
        $filePath = $file->getRealPath();

        try {
            switch ($engine) {
                case 'clamav':
                    $this->scanWithClamAV($filePath, $file);
                    break;
                case 'custom':
                    $this->scanWithCustomEngine($filePath, $file);
                    break;
                default:
                    Log::warning('Unknown virus scanning engine configured', ['engine' => $engine]);
            }
        } catch (\Exception $e) {
            Log::error('Virus scanning failed', [
                'filename' => $file->getClientOriginalName(),
                'engine' => $engine,
                'error' => $e->getMessage(),
            ]);

            // Decide whether to block uploads when virus scanning fails
            if (config('uploads.virus_scanning.block_on_scan_failure', false)) {
                throw new RuntimeException('Unable to complete virus scan, upload blocked for security');
            }
        }
    }

    /**
     * Scan file with ClamAV antivirus.
     */
    protected function scanWithClamAV(string $filePath, UploadedFile $file): void
    {
        if (! $this->isClamAVAvailable()) {
            Log::warning('ClamAV not available for virus scanning');

            return;
        }

        $timeout = $this->virusScanConfig['timeout'];
        $command = "timeout {$timeout} clamscan --no-summary --infected ".escapeshellarg($filePath);

        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        if ($returnCode === 1) {
            // Virus found
            $this->handleInfectedFile($filePath, $file, implode("\n", $output));
        } elseif ($returnCode === 124) {
            // Timeout
            throw new RuntimeException('Virus scan timed out');
        } elseif ($returnCode !== 0) {
            // Other error
            throw new RuntimeException('Virus scan failed with code: '.$returnCode);
        }

        Log::info('File passed virus scan', [
            'filename' => $file->getClientOriginalName(),
            'engine' => 'clamav',
        ]);
    }

    /**
     * Check if ClamAV is available.
     */
    protected function isClamAVAvailable(): bool
    {
        $cacheKey = 'clamav_available';

        return Cache::remember($cacheKey, 300, function () {
            exec('which clamscan', $output, $returnCode);

            return $returnCode === 0;
        });
    }

    /**
     * Scan file with custom antivirus engine.
     */
    protected function scanWithCustomEngine(string $filePath, UploadedFile $file): void
    {
        // Placeholder for custom virus scanning implementation
        // This could integrate with cloud-based scanning services like:
        // - VirusTotal API
        // - AWS GuardDuty Malware Protection
        // - Microsoft Defender API
        // - Custom ML-based detection

        Log::info('Custom virus scanning not implemented', [
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Handle infected file detection.
     */
    protected function handleInfectedFile(string $filePath, UploadedFile $file, string $scanResult): void
    {
        Log::critical('Infected file detected', [
            'filename' => $file->getClientOriginalName(),
            'scan_result' => $scanResult,
            'file_path' => $filePath,
        ]);

        if ($this->virusScanConfig['quarantine_infected']) {
            $this->quarantineFile($filePath, $file);
        }

        throw new InvalidArgumentException('File is infected with malware and cannot be uploaded');
    }

    /**
     * Quarantine infected file.
     */
    protected function quarantineFile(string $filePath, UploadedFile $file): void
    {
        try {
            $quarantineDir = storage_path('quarantine');

            if (! is_dir($quarantineDir)) {
                mkdir($quarantineDir, 0700, true);
            }

            $quarantinePath = $quarantineDir.'/'.date('Y-m-d_H-i-s').'_'.$file->getClientOriginalName();

            if (copy($filePath, $quarantinePath)) {
                Log::info('Infected file quarantined', [
                    'original_path' => $filePath,
                    'quarantine_path' => $quarantinePath,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to quarantine infected file', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get comprehensive file analysis report.
     */
    public function analyzeFile(UploadedFile $file): array
    {
        $filePath = $file->getRealPath();
        $fileHandle = fopen($filePath, 'rb');

        if (! $fileHandle) {
            return ['error' => 'Unable to read file'];
        }

        $header = fread($fileHandle, 512);
        fclose($fileHandle);

        $analysis = [
            'basic_info' => [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
            ],
            'signature_analysis' => [
                'hex_header' => bin2hex(substr($header, 0, 20)),
                'detected_type' => $this->detectTypeFromSignature($header),
                'signature_matches' => $this->checkSignatureMatches($header),
            ],
            'security_analysis' => [
                'entropy' => $this->calculateEntropy(substr($header, 0, 256)),
                'malicious_patterns' => $this->findMaliciousPatterns($header),
                'dangerous_extension' => in_array(strtolower($file->getClientOriginalExtension()), $this->dangerousExtensions),
            ],
            'metadata_analysis' => [],
        ];

        // Add image-specific analysis
        if (strpos($file->getMimeType(), 'image/') === 0) {
            try {
                $imageInfo = @getimagesize($filePath);
                if ($imageInfo !== false) {
                    $analysis['image_analysis'] = [
                        'dimensions' => $imageInfo[0].'x'.$imageInfo[1],
                        'estimated_size' => $this->estimateImageSize($imageInfo[0], $imageInfo[1], $file->getMimeType()),
                        'size_ratio' => $file->getSize() / $this->estimateImageSize($imageInfo[0], $imageInfo[1], $file->getMimeType()),
                    ];
                }
            } catch (\Exception $e) {
                $analysis['image_analysis'] = ['error' => $e->getMessage()];
            }
        }

        return $analysis;
    }

    /**
     * Check for signature matches against known types.
     */
    protected function checkSignatureMatches(string $header): array
    {
        $matches = [];

        foreach ($this->fileSignatures as $mimeType => $signatures) {
            foreach ($signatures as $signature) {
                if (strpos($header, $signature) === 0) {
                    $matches[] = $mimeType;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Find malicious patterns in file header.
     */
    protected function findMaliciousPatterns(string $header): array
    {
        $patterns = [];

        foreach ($this->maliciousSignatures as $signature => $description) {
            if (strpos($header, $signature) !== false) {
                $patterns[] = $description;
            }
        }

        return $patterns;
    }
}
