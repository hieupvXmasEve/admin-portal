<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Modules\AI\Support\Tools\EntityProfileResult;
use App\Modules\AI\Support\Tools\EntitySearchResult;
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

    public static function fromResult(QueryMetricsResult|EntitySearchResult|EntityProfileResult $result): self
    {
        $payload = $result->toArray();
        $sourceReport = (string) ($payload['source_references'][0]['source_report'] ?? 'allowlisted source report');
        $status = (string) $payload['status'];
        $content = self::contentForResult($result, $status, $sourceReport, $payload);

        return new self(
            status: $status,
            content: $content,
            payload: $payload,
            safeErrorCode: isset($payload['safe_error_code']) ? (string) $payload['safe_error_code'] : null,
            hiddenSections: $payload['hidden_sections'] ?? [],
            toolExecuted: true,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function contentForResult(
        QueryMetricsResult|EntitySearchResult|EntityProfileResult $result,
        string $status,
        string $sourceReport,
        array $payload,
    ): string {
        if ($result instanceof EntitySearchResult) {
            if (in_array($status, ['completed', 'partial'], true)) {
                $count = (int) ($payload['result_count'] ?? 0);

                return 'Found **'.$count.'** matching candidate(s) in approved '.self::sourceLabel($sourceReport).' data.';
            }

            return 'This entity lookup could not be answered safely from approved directory data.';
        }

        if ($result instanceof EntityProfileResult) {
            return in_array($status, ['completed', 'partial'], true)
                ? 'Retrieved approved profile sections from '.self::sourceLabel($sourceReport).'.'
                : 'This student profile could not be answered safely from approved profile data.';
        }

        return in_array($status, ['completed', 'partial'], true)
            ? self::metricAnswerContent($payload, $sourceReport)
            : 'This question could not be answered safely from approved metric data.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function metricAnswerContent(array $payload, string $sourceReport): string
    {
        $lines = [
            'Answer prepared from approved '.self::sourceLabel($sourceReport).' data.',
        ];

        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];

        foreach (array_slice($summary, 0, 6, true) as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $lines[] = '- '.self::label((string) $key).': **'.self::stringValue($value).'**';
        }

        $groups = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];

        if ($groups !== []) {
            $lines[] = '- Grouped results: **'.count($groups).'** group(s)';
        }

        return implode("\n", $lines);
    }

    private static function label(string $value): string
    {
        return str($value)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    private static function sourceLabel(string $sourceReport): string
    {
        return str($sourceReport)
            ->replace(['.', '-'], ' ')
            ->headline()
            ->toString();
    }

    private static function stringValue(mixed $value): string
    {
        if (is_float($value)) {
            return (string) round($value, 2);
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    public static function unsupported(): self
    {
        return new self(
            status: 'failed',
            content: 'I cannot answer that in Staff Copilot yet. It only supports query-only questions over approved Swinx data.',
            payload: [
                'status' => 'failed',
                'safe_error_code' => 'unsupported_staff_question',
            ],
            safeErrorCode: 'unsupported_staff_question',
            hiddenSections: ['unsupported_staff_question'],
            toolExecuted: false,
        );
    }

    public static function clarification(string $question): self
    {
        return new self(
            status: 'completed',
            content: $question,
            payload: [
                'status' => 'completed',
                'clarification_question' => $question,
                'confidence' => ['level' => 'none', 'basis' => 'clarification_required'],
            ],
            safeErrorCode: null,
            hiddenSections: [],
            toolExecuted: false,
        );
    }

    /**
     * @param  array<string, mixed>  $finalAnswer
     * @param  list<QueryMetricsResult|EntitySearchResult|EntityProfileResult>  $toolResults
     */
    public static function fromLiveFinalAnswer(array $finalAnswer, array $toolResults): self
    {
        $primaryPayload = $toolResults[0]?->toArray() ?? [];
        $status = (string) ($finalAnswer['status'] ?? $primaryPayload['status'] ?? 'failed');
        $safeErrorCode = isset($finalAnswer['safe_error_code'])
            ? (string) $finalAnswer['safe_error_code']
            : (isset($primaryPayload['safe_error_code']) ? (string) $primaryPayload['safe_error_code'] : null);

        return new self(
            status: $status,
            content: (string) ($finalAnswer['answer'] ?? 'The live copilot could not synthesize a safe answer.'),
            payload: array_replace_recursive($primaryPayload, [
                'status' => $status,
                'safe_error_code' => $safeErrorCode,
                'confidence' => is_array($finalAnswer['confidence'] ?? null)
                    ? $finalAnswer['confidence']
                    : ($primaryPayload['confidence'] ?? ['level' => 'none', 'basis' => 'not_available']),
                'source_references' => is_array($finalAnswer['source_references'] ?? null)
                    ? $finalAnswer['source_references']
                    : ($primaryPayload['source_references'] ?? []),
                'limitations' => is_array($finalAnswer['limitations'] ?? null) ? $finalAnswer['limitations'] : [],
                'referenced_tool_call_ids' => is_array($finalAnswer['referenced_tool_call_ids'] ?? null) ? $finalAnswer['referenced_tool_call_ids'] : [],
            ]),
            safeErrorCode: $safeErrorCode,
            hiddenSections: $primaryPayload['hidden_sections'] ?? [],
            toolExecuted: $toolResults !== [],
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
