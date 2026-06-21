<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Modules\AI\Support\Tools\QueryMetricsResult;

class StaffCopilotAnswer
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $hiddenSections
     */
    public function __construct(
        private readonly string $status,
        private readonly string $content,
        private readonly array $payload,
        private readonly ?string $safeErrorCode,
        private readonly array $hiddenSections,
        private readonly bool $toolExecuted,
    ) {}

    public static function fromResult(QueryMetricsResult $result): self
    {
        $payload = $result->toArray();
        $sourceReport = (string) ($payload['source_references'][0]['source_report'] ?? 'allowlisted source report');
        $status = (string) $payload['status'];
        $content = in_array($status, ['completed', 'partial'], true)
            ? "Answer generated from {$sourceReport}."
            : 'This question could not be answered safely from the allowlisted metric catalog.';

        return new self(
            status: $status,
            content: $content,
            payload: $payload,
            safeErrorCode: isset($payload['safe_error_code']) ? (string) $payload['safe_error_code'] : null,
            hiddenSections: $payload['hidden_sections'] ?? [],
            toolExecuted: true,
        );
    }

    public static function unsupported(): self
    {
        return new self(
            status: 'failed',
            content: 'This question is outside the staff copilot MVP metric catalog.',
            payload: [
                'status' => 'failed',
                'safe_error_code' => 'unsupported_staff_question',
            ],
            safeErrorCode: 'unsupported_staff_question',
            hiddenSections: ['unsupported_staff_question'],
            toolExecuted: false,
        );
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['completed', 'partial'], true);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function safeErrorCode(): ?string
    {
        return $this->safeErrorCode;
    }

    /**
     * @return list<string>
     */
    public function hiddenSections(): array
    {
        return $this->hiddenSections;
    }

    public function toolExecuted(): bool
    {
        return $this->toolExecuted;
    }
}
