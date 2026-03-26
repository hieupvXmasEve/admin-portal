<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

class DngChecksumService
{
    private string $hashKey;

    public function __construct()
    {
        $this->hashKey = (string) config('services.dng.hash_key');
    }

    /**
     * Generate a DNG checksum from the given value string.
     *
     * Uses HMAC-SHA1 + base64, then replaces `=` with `%3d` and space with `+`.
     */
    public function generate(string $value): string
    {
        $hash = hash_hmac('sha1', $value, $this->hashKey, true);

        return str_replace(
            ['=', ' '],
            ['%3d', '+'],
            base64_encode($hash),
        );
    }

    /**
     * Verify a DNG checksum matches the expected value.
     */
    public function verify(string $value, string $expectedChecksum): bool
    {
        return hash_equals($this->generate($value), $expectedChecksum);
    }
}
