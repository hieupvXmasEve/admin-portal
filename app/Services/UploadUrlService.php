<?php

namespace App\Services;

use App\Models\UploadRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class UploadUrlService
{
    /**
     * Generate appropriate URL based on upload record configuration.
     */
    public function generateUrl(UploadRecord $uploadRecord, array $options = []): string
    {
        $context = $uploadRecord->context;
        $config = config("uploads.contexts.{$context}", []);

        // Merge default options
        $options = array_merge([
            'expiration_minutes' => 60,
            'force_temporary' => false,
            'include_cdn' => true,
        ], $options);

        // Determine URL type based on configuration and options
        if ($this->shouldUseTemporaryUrl($config, $options)) {
            return $this->generateTemporaryUrl($uploadRecord, $options['expiration_minutes']);
        }

        if ($this->shouldUsePublicUrl($config, $options)) {
            return $this->generatePublicUrl($uploadRecord, $options);
        }

        // Fallback to signed URL for private access
        return $this->generateSignedUrl($uploadRecord, $options['expiration_minutes']);
    }

    /**
     * Generate public URL with CDN support.
     */
    public function generatePublicUrl(UploadRecord $uploadRecord, array $options = []): string
    {
        try {
            if (empty($uploadRecord->disk) || empty($uploadRecord->path)) {
                return '';
            }

            $baseUrl = Storage::disk($uploadRecord->disk)->url($uploadRecord->path);

            // Apply CDN transformation if configured and requested
            if (($options['include_cdn'] ?? true) && $this->hasCdnConfiguration($uploadRecord->disk)) {
                return $this->applyCdnTransformation($baseUrl, $uploadRecord, $options);
            }

            return $baseUrl;
        } catch (\Exception $e) {
            Log::error('Failed to generate public URL', [
                'upload_id' => $uploadRecord->id,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Generate temporary URL for private files.
     */
    public function generateTemporaryUrl(UploadRecord $uploadRecord, int $expirationMinutes = 60): string
    {
        $expiration = now()->addMinutes($expirationMinutes);
        $disk = Storage::disk($uploadRecord->disk);

        // Try native temporary URL if disk supports it (e.g., S3)
        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($uploadRecord->path, $expiration);
            } catch (\Exception $e) {
                // Driver doesn't actually support temporary URLs, fallback to signed route
                Log::debug('Disk temporaryUrl not supported, using signed route', [
                    'upload_id' => $uploadRecord->id,
                    'disk' => $uploadRecord->disk,
                ]);
            }
        }

        // Fallback to signed URL via uploads.serve route
        return $this->generateSignedUrl($uploadRecord, $expirationMinutes);
    }

    /**
     * Generate signed URL for secure access.
     */
    public function generateSignedUrl(UploadRecord $uploadRecord, int $expirationMinutes = 60): string
    {
        try {
            $expiration = now()->addMinutes($expirationMinutes);

            return URL::temporarySignedRoute(
                'uploads.serve',
                $expiration,
                ['id' => $uploadRecord->id]
            );
        } catch (\Exception $e) {
            Log::error('Failed to generate signed URL', [
                'upload_id' => $uploadRecord->id,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Generate thumbnail URL if thumbnails are supported.
     */
    public function generateThumbnailUrl(UploadRecord $uploadRecord, array $dimensions = []): string
    {
        $context = $uploadRecord->context;
        $config = config("uploads.contexts.{$context}", []);

        if (!($config['generate_thumbnails'] ?? false)) {
            return $this->generateUrl($uploadRecord);
        }

        $dimensions = array_merge([
            'width' => 150,
            'height' => 150,
            'quality' => 80,
            'format' => 'webp',
        ], $dimensions);

        try {
            // Check if thumbnail already exists
            $thumbnailPath = $this->getThumbnailPath($uploadRecord, $dimensions);

            if (Storage::disk($uploadRecord->disk)->exists($thumbnailPath)) {
                return Storage::disk($uploadRecord->disk)->url($thumbnailPath);
            }

            // Generate thumbnail on-demand URL
            return route('uploads.thumbnail', [
                'upload' => $uploadRecord->id,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'quality' => $dimensions['quality'],
                'format' => $dimensions['format'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate thumbnail URL', [
                'upload_id' => $uploadRecord->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to original image
            return $this->generateUrl($uploadRecord);
        }
    }

    /**
     * Generate multiple URLs for batch processing.
     */
    public function generateBatchUrls(array $uploadRecords, array $options = []): array
    {
        $urls = [];

        foreach ($uploadRecords as $uploadRecord) {
            $urls[$uploadRecord->id] = [
                'original' => $this->generateUrl($uploadRecord, $options),
                'thumbnail' => $this->generateThumbnailUrl($uploadRecord),
            ];
        }

        return $urls;
    }

    /**
     * Generate responsive image URLs for different screen sizes.
     */
    public function generateResponsiveUrls(UploadRecord $uploadRecord): array
    {
        $sizes = [
            'small' => ['width' => 320, 'height' => 240],
            'medium' => ['width' => 768, 'height' => 576],
            'large' => ['width' => 1024, 'height' => 768],
            'xlarge' => ['width' => 1920, 'height' => 1080],
        ];

        $urls = [
            'original' => $this->generateUrl($uploadRecord),
        ];

        foreach ($sizes as $size => $dimensions) {
            $urls[$size] = $this->generateThumbnailUrl($uploadRecord, $dimensions);
        }

        return $urls;
    }

    /**
     * Check if temporary URL should be used.
     */
    protected function shouldUseTemporaryUrl(array $config, array $options): bool
    {
        return ($options['force_temporary'] ?? false) ||
               !($config['public'] ?? true);
    }

    /**
     * Check if public URL should be used.
     */
    protected function shouldUsePublicUrl(array $config, array $options): bool
    {
        return ($config['public'] ?? true) &&
               !($options['force_temporary'] ?? false);
    }

    /**
     * Check if CDN configuration exists for the disk.
     */
    protected function hasCdnConfiguration(?string $disk): bool
    {
        if (! $disk) {
            return false;
        }

        $config = config("filesystems.disks.{$disk}");
        return isset($config['cdn_url']) || isset($config['url_transformer']);
    }

    /**
     * Apply CDN transformation to URL.
     */
    protected function applyCdnTransformation(string $baseUrl, UploadRecord $uploadRecord, array $options): string
    {
        $config = config("filesystems.disks.{$uploadRecord->disk}");

        // Simple CDN URL replacement
        if (isset($config['cdn_url'])) {
            $originalDomain = parse_url($baseUrl, PHP_URL_HOST);
            $cdnDomain = parse_url($config['cdn_url'], PHP_URL_HOST);

            return str_replace($originalDomain, $cdnDomain, $baseUrl);
        }

        // Custom URL transformer
        if (isset($config['url_transformer']) && is_callable($config['url_transformer'])) {
            return call_user_func($config['url_transformer'], $baseUrl, $uploadRecord, $options);
        }

        return $baseUrl;
    }

    /**
     * Get thumbnail path for an upload record.
     */
    protected function getThumbnailPath(UploadRecord $uploadRecord, array $dimensions): string
    {
        $pathInfo = pathinfo($uploadRecord->path);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        $thumbnailName = sprintf(
            '%s_%dx%d_q%d.%s',
            $filename,
            $dimensions['width'],
            $dimensions['height'],
            $dimensions['quality'],
            $dimensions['format'] ?? $extension
        );

        return $directory . '/thumbnails/' . $thumbnailName;
    }

    /**
     * Validate URL signature for secure access.
     */
    public function validateSignature(string $url): bool
    {
        try {
            return URL::hasValidSignature(request());
        } catch (\Exception $e) {
            Log::error('Failed to validate URL signature', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get URL metadata including expiration and access type.
     */
    public function getUrlMetadata(string $url): array
    {
        try {
            $parsedUrl = parse_url($url);
            $queryParams = [];

            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
            }

            return [
                'url' => $url,
                'is_signed' => isset($queryParams['signature']),
                'expires_at' => isset($queryParams['expires']) ?
                    date('Y-m-d H:i:s', $queryParams['expires']) : null,
                'is_expired' => isset($queryParams['expires']) ?
                    $queryParams['expires'] < time() : false,
                'query_params' => $queryParams,
            ];
        } catch (\Exception $e) {
            return [
                'url' => $url,
                'error' => $e->getMessage(),
            ];
        }
    }
}
