<?php

declare(strict_types=1);

final class GetCheckSum
{
    private const HASH_KEY = '2CabGHY9XaBCyeTOXU48tlajCC5NrLE32G7pWoW3Jrtsw7FFGX7hMqFQC1IdMlRmFJL2hE2J';

    public static function getCheckSum(string $value): string
    {
        $hash = hash_hmac('sha1', $value, self::HASH_KEY, true);

        return str_replace(
            ['=', ' '],
            ['%3d', '+'],
            base64_encode($hash),
        );
    }
}
