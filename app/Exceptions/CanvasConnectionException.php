<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CanvasConnectionException extends Exception
{
    public function __construct(
        string $message = 'Canvas connection failed',
        public readonly ?int $integrationId = null,
        public readonly bool $shouldDeactivate = false,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for timeout error
     */
    public static function timeout(int $integrationId, string $endpoint, int $timeoutSeconds): self
    {
        return new self(
            message: "Canvas API request timed out after {$timeoutSeconds} seconds for endpoint: {$endpoint}",
            integrationId: $integrationId,
            shouldDeactivate: true,
            code: 28 // cURL timeout error code
        );
    }

    /**
     * Create exception for token refresh failure
     */
    public static function tokenRefreshFailed(int $integrationId, string $reason): self
    {
        return new self(
            message: "Failed to refresh Canvas access token: {$reason}",
            integrationId: $integrationId,
            shouldDeactivate: true,
            code: 401
        );
    }

    /**
     * Create exception for integration being inactive
     */
    public static function integrationInactive(int $integrationId): self
    {
        return new self(
            message: "Canvas integration is inactive",
            integrationId: $integrationId,
            shouldDeactivate: false,
            code: 0
        );
    }
}
