<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use tbQuar\Facades\Quar;

class QRCodeService
{
    protected const QR_CODE_LENGTH = 32;
    protected const QR_CODE_PREFIX = 'EVT';
    protected const MAX_GENERATION_ATTEMPTS = 10;
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Parse student QR code to extract participation data
     */
    public function parseStudentQRCode(string $qrCode): ?array
    {
        // Parse format: "STU_456_EVT_789_PRT_123_abc12345"
        $pattern = '/^STU_(\d+)_EVT_(\d+)_PRT_(\d+)_([a-f0-9]{8})$/';

        if (!preg_match($pattern, $qrCode, $matches)) {
            return null;
        }

        return [
            'student_id' => (int) $matches[1],
            'event_id' => (int) $matches[2],
            'participation_id' => (int) $matches[3],
            'hash' => $matches[4]
        ];
    }

    /**
     * Validate student QR code format
     */
    public function isValidStudentQRCodeFormat(string $qrCode): bool
    {
        return $this->parseStudentQRCode($qrCode) !== null;
    }
    /**
     * Generate a unique QR code for an event
     */
    public function generateEventQRCode(): string
    {
        $attempts = 0;

        do {
            $qrCode = $this->generateUniqueCode();
            $attempts++;

            if ($attempts >= self::MAX_GENERATION_ATTEMPTS) {
                throw new \RuntimeException('Failed to generate unique QR code after maximum attempts');
            }
        } while ($this->qrCodeExists($qrCode));

        Log::info('QR code generated', [
            'qr_code' => $qrCode,
            'attempts' => $attempts
        ]);

        return $qrCode;
    }

    /**
     * Validate a QR code and return the associated event
     */
    public function validateQRCode(string $qrCode): ?Event
    {
        // Check cache first for performance
        $cacheKey = "qr_code_validation:{$qrCode}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($qrCode) {
            $event = Event::where('qr_code', $qrCode)->first();

            if ($event) {
                Log::info('QR code validated', [
                    'qr_code' => $qrCode,
                    'event_id' => $event->id,
                    'event_title' => $event->title
                ]);
            } else {
                Log::warning('Invalid QR code scanned', [
                    'qr_code' => $qrCode
                ]);
            }

            return $event;
        });
    }

    /**
     * Generate QR code image as base64 string
     */
    public function generateQRCodeImage(string $qrCode, int $size = 200): string
    {
        try {
            $qrCodeSvg = Quar::size($size)
                ->eye('rounded')
                ->color('#1f2937')
                ->backgroundColor('#ffffff')
                ->generate($qrCode);

            return 'data:image/svg+xml;base64,' . base64_encode($qrCodeSvg);
        } catch (\Exception $e) {
            Log::error('Failed to generate QR code image', [
                'qr_code' => $qrCode,
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException('Failed to generate QR code image: ' . $e->getMessage());
        }
    }

    /**
     * Generate QR code image as SVG string
     */
    public function generateQRCodeSVG(string $qrCode, int $size = 200): string
    {
        try {
            return Quar::size($size)
                ->eye('rounded')
                ->color('#1f2937')
                ->backgroundColor('#ffffff')
                ->generate($qrCode);
        } catch (\Exception $e) {
            Log::error('Failed to generate QR code SVG', [
                'qr_code' => $qrCode,
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException('Failed to generate QR code SVG: ' . $e->getMessage());
        }
    }

    /**
     * Generate QR code as PNG file and return file path
     */
    public function generateQRCodeFile(string $qrCode, int $size = 200): string
    {
        try {
            $fileName = 'qr_' . md5($qrCode . time()) . '.png';
            $directory = storage_path('app/public/qr-codes/');

            // Ensure directory exists
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filePath = $directory . $fileName;

            Quar::format('png')
                ->size($size)
                ->eye('rounded')
                ->color('#1f2937')
                ->backgroundColor('#ffffff')
                ->generate($qrCode, $filePath);

            return 'storage/qr-codes/' . $fileName;
        } catch (\Exception $e) {
            Log::error('Failed to generate QR code file', [
                'qr_code' => $qrCode,
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException('Failed to generate QR code file: ' . $e->getMessage());
        }
    }

    /**
     * Verify QR code format is valid
     */
    public function isValidQRCodeFormat(string $qrCode): bool
    {
        // Check if it starts with our prefix and has correct length
        return Str::startsWith($qrCode, self::QR_CODE_PREFIX) &&
            strlen($qrCode) === strlen(self::QR_CODE_PREFIX) + self::QR_CODE_LENGTH;
    }

    /**
     * Get QR code statistics for monitoring
     */
    public function getQRCodeStatistics(): array
    {
        $totalEvents = Event::count();
        $eventsWithQRCodes = Event::whereNotNull('qr_code')->count();
        $publishedEventsWithQRCodes = Event::published()->whereNotNull('qr_code')->count();

        return [
            'total_events' => $totalEvents,
            'events_with_qr_codes' => $eventsWithQRCodes,
            'published_events_with_qr_codes' => $publishedEventsWithQRCodes,
            'qr_code_coverage' => $totalEvents > 0 ? ($eventsWithQRCodes / $totalEvents) * 100 : 0,
        ];
    }

    /**
     * Regenerate QR code for an event (in case of compromise)
     */
    public function regenerateQRCode(Event $event): string
    {
        $oldQRCode = $event->qr_code;
        $newQRCode = $this->generateEventQRCode();

        $event->update(['qr_code' => $newQRCode]);

        // Clear cache for old QR code
        Cache::forget("qr_code_validation:{$oldQRCode}");

        Log::info('QR code regenerated', [
            'event_id' => $event->id,
            'old_qr_code' => $oldQRCode,
            'new_qr_code' => $newQRCode
        ]);

        return $newQRCode;
    }

    /**
     * Bulk validate multiple QR codes
     */
    public function validateMultipleQRCodes(array $qrCodes): array
    {
        $results = [];

        foreach ($qrCodes as $qrCode) {
            $results[$qrCode] = $this->validateQRCode($qrCode);
        }

        return $results;
    }

    /**
     * Clear QR code validation cache
     */
    public function clearValidationCache(string $qrCode = null): void
    {
        if ($qrCode) {
            Cache::forget("qr_code_validation:{$qrCode}");
        } else {
            // Clear all QR code validation cache
            $events = Event::whereNotNull('qr_code')->pluck('qr_code');
            foreach ($events as $code) {
                Cache::forget("qr_code_validation:{$code}");
            }
        }
    }

    /**
     * Generate a unique code string
     */
    protected function generateUniqueCode(): string
    {
        $randomString = Str::upper(Str::random(self::QR_CODE_LENGTH));
        return self::QR_CODE_PREFIX . $randomString;
    }

    /**
     * Check if QR code already exists in database
     */
    protected function qrCodeExists(string $qrCode): bool
    {
        return Event::where('qr_code', $qrCode)->exists();
    }

    /**
     * Generate QR code with custom data payload
     */
    public function generateCustomQRCode(array $data, int $size = 200): string
    {
        try {
            $jsonData = json_encode($data);

            return Quar::size($size)
                ->eye('rounded')
                ->color('#1f2937')
                ->backgroundColor('#ffffff')
                ->generate($jsonData);
        } catch (\Exception $e) {
            Log::error('Failed to generate custom QR code', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException('Failed to generate custom QR code: ' . $e->getMessage());
        }
    }

    /**
     * Validate QR code security (rate limiting, fraud detection)
     */
    public function validateQRCodeSecurity(string $qrCode, string $ipAddress, int $userId = null): bool
    {
        $cacheKey = "qr_scan_rate_limit:{$ipAddress}";
        $scanCount = Cache::get($cacheKey, 0);

        // Rate limiting: max 10 scans per minute per IP
        if ($scanCount >= 10) {
            Log::warning('QR code scan rate limit exceeded', [
                'ip_address' => $ipAddress,
                'user_id' => $userId,
                'qr_code' => $qrCode
            ]);
            return false;
        }

        // Increment scan count
        Cache::put($cacheKey, $scanCount + 1, 60); // 1 minute TTL

        return true;
    }

    /**
     * Generate styled QR code with custom colors and patterns
     */
    public function generateStyledQRCode(string $qrCode, array $options = []): string
    {
        try {
            $quar = Quar::size($options['size'] ?? 200);

            // Set eye style
            if (isset($options['eye_style'])) {
                $quar->eye($options['eye_style']);
            }

            // Set body pattern
            if (isset($options['body_pattern'])) {
                $smoothness = $options['smoothness'] ?? 0.5;
                $quar->style($options['body_pattern'], $smoothness);
            }

            // Set colors
            if (isset($options['color'])) {
                if (is_string($options['color'])) {
                    $quar->color($options['color']);
                } else {
                    $quar->color($options['color']['r'], $options['color']['g'], $options['color']['b']);
                }
            }

            if (isset($options['background_color'])) {
                if (is_string($options['background_color'])) {
                    $quar->backgroundColor($options['background_color']);
                } else {
                    $quar->backgroundColor(
                        $options['background_color']['r'],
                        $options['background_color']['g'],
                        $options['background_color']['b']
                    );
                }
            }

            // Set gradient if specified
            if (isset($options['gradient'])) {
                $gradient = $options['gradient'];
                $quar->gradient(
                    $gradient['start_r'],
                    $gradient['start_g'],
                    $gradient['start_b'],
                    $gradient['end_r'],
                    $gradient['end_g'],
                    $gradient['end_b'],
                    $gradient['type'] ?? 'vertical'
                );
            }

            return $quar->generate($qrCode);
        } catch (\Exception $e) {
            Log::error('Failed to generate styled QR code', [
                'qr_code' => $qrCode,
                'options' => $options,
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException('Failed to generate styled QR code: ' . $e->getMessage());
        }
    }
}
