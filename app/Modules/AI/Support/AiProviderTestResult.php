<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

final readonly class AiProviderTestResult
{
    private function __construct(
        public string $status,
        public ?string $errorCode,
        public int $durationMs,
    ) {}

    public static function success(int $durationMs): self
    {
        return new self('success', null, $durationMs);
    }

    public static function failed(string $errorCode, int $durationMs): self
    {
        return new self('failed', $errorCode, $durationMs);
    }
}
