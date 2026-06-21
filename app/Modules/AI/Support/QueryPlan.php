<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

class QueryPlan
{
    private const ALLOWED_TOP_LEVEL_KEYS = ['metric', 'filters', 'group_by', 'options'];

    private const FORBIDDEN_KEYS = [
        'sql',
        'raw_sql',
        'table',
        'tables',
        'column',
        'columns',
        'join',
        'joins',
        'relationship',
        'relationships',
        'include',
        'includes',
        'select',
        'where',
        'order_by',
        'having',
        'raw_filter',
        'student_ids',
        'cross_campus',
        'unbounded_limit',
    ];

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $groupBy
     * @param  array<string, mixed>  $options
     * @param  list<string>  $unknownTopLevelKeys
     * @param  list<string>  $forbiddenKeys
     */
    private function __construct(
        private readonly array $raw,
        private readonly string $metric,
        private readonly array $filters,
        private readonly array $groupBy,
        private readonly array $options,
        private readonly array $unknownTopLevelKeys,
        private readonly array $forbiddenKeys,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $groupBy = $payload['group_by'] ?? [];

        if (! is_array($groupBy)) {
            $groupBy = [$groupBy];
        }

        return new self(
            raw: $payload,
            metric: (string) ($payload['metric'] ?? ''),
            filters: is_array($payload['filters'] ?? null) ? $payload['filters'] : [],
            groupBy: array_values(array_map('strval', $groupBy)),
            options: is_array($payload['options'] ?? null) ? $payload['options'] : [],
            unknownTopLevelKeys: array_values(array_diff(array_keys($payload), self::ALLOWED_TOP_LEVEL_KEYS)),
            forbiddenKeys: self::collectForbiddenKeys($payload),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    public function metric(): string
    {
        return $this->metric;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * @return list<string>
     */
    public function groupBy(): array
    {
        return $this->groupBy;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function hasSchemaViolations(): bool
    {
        return $this->unknownTopLevelKeys !== [] || $this->forbiddenKeys !== [];
    }

    /**
     * @return list<string>
     */
    public function schemaViolations(): array
    {
        return array_values(array_unique(array_merge($this->unknownTopLevelKeys, $this->forbiddenKeys)));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function collectForbiddenKeys(array $payload): array
    {
        $keys = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array($key, self::FORBIDDEN_KEYS, true)) {
                $keys[] = $key;
            }

            if (is_array($value)) {
                array_push($keys, ...self::collectForbiddenKeys($value));
            }
        }

        return array_values(array_unique($keys));
    }
}
