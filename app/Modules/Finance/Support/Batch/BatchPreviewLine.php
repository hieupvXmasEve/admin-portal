<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Batch;

/**
 * One canonical preview line shared by every Batch Studio job.
 *
 * - $key         stable per-line identity (re-derivable at commit time)
 * - $hashPayload canonical resolved fields fed to BatchPreviewLineHasher
 * - $display     UI-only fields (label, diff bucket, amounts, reason, warning_codes)
 */
final class BatchPreviewLine
{
    /**
     * @param  array<string, mixed>  $hashPayload
     * @param  array<string, mixed>  $display
     */
    public function __construct(
        public readonly string $key,
        public readonly array $hashPayload,
        public readonly array $display,
    ) {}

    public function hash(): string
    {
        return BatchPreviewLineHasher::hashLine($this->hashPayload);
    }

    /**
     * @return array{key: string, display: array<string, mixed>}
     */
    public function toClientArray(): array
    {
        return ['key' => $this->key, 'display' => $this->display];
    }

    /**
     * @param  list<self>  $lines
     * @return array<string, string>  key => sha256
     */
    public static function toTokenPayload(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $out[$line->key] = $line->hash();
        }

        return $out;
    }
}