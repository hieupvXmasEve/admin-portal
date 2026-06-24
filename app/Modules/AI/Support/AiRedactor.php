<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

class AiRedactor
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'api_key',
        'apikey',
        'access_token',
        'authorization',
        'authorization_header',
        'bearer_token',
        'column',
        'columns',
        'client_secret',
        'cross_campus_records',
        'encrypted_api_key',
        'finance_data_without_permission_snapshot',
        'academic_data_without_permission_snapshot',
        'lecturer_data_without_permission_snapshot',
        'national_id',
        'password',
        'provider_request_body',
        'provider_response_body',
        'raw_business_prompt_with_secrets',
        'raw_provider_request',
        'raw_provider_response',
        'raw_rows',
        'raw_sql',
        'refresh_token',
        'relationship',
        'relationships',
        'secret',
        'sql',
        'table',
        'tables',
        'student_data_without_permission_snapshot',
        'token',
        'unbounded_source_rows',
        'unredacted_tool_arguments',
        'unredacted_tool_result',
    ];

    public function redact(mixed $payload): mixed
    {
        if (is_array($payload)) {
            return $this->redactArray($payload);
        }

        if (is_string($payload)) {
            return $this->redactText($payload);
        }

        return $payload;
    }

    public function redactText(string $text): string
    {
        $patterns = [
            '/Bearer\s+[A-Za-z0-9._\-]+/i',
            '/sk-[A-Za-z0-9._\-]+/i',
            '/sk_live_[A-Za-z0-9._\-]+/i',
            '/sk_test_[A-Za-z0-9._\-]+/i',
        ];

        return (string) preg_replace($patterns, self::REDACTED, $text);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    private function redactArray(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = $this->redact($value);
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $key));

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (['api_key', 'authorization', 'secret', 'token'] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
