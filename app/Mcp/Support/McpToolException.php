<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use RuntimeException;

/**
 * Identity/scope refusal raised by the MCP actor and campus resolvers and caught by the
 * MCP tool wrapper, which maps it to a safe MCP result and one audit row (ADR-0010/0011).
 *
 * Carrying the safe error code, the permission result, and the campus-scope snapshot lets
 * the wrapper persist uniform evidence and return a stable, leak-free code to the client.
 * A clarification (e.g. an ambiguous campus) is flagged separately because it is a
 * "tell me which campus" prompt, not a denial.
 */
class McpToolException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $campusScopeSnapshot  backward-compatible {campus_ids: int[]} shape
     * @param  array<string, mixed>  $meta  extra, non-sensitive context surfaced to the client (e.g. candidate campuses)
     */
    public function __construct(
        private readonly string $safeErrorCode,
        string $message,
        private readonly string $permissionResult = 'not_evaluated',
        private readonly array $campusScopeSnapshot = ['campus_ids' => []],
        private readonly bool $clarification = false,
        private readonly array $meta = [],
    ) {
        parent::__construct($message);
    }

    public function safeErrorCode(): string
    {
        return $this->safeErrorCode;
    }

    public function permissionResult(): string
    {
        return $this->permissionResult;
    }

    /**
     * @return array<string, mixed>
     */
    public function campusScopeSnapshot(): array
    {
        return $this->campusScopeSnapshot;
    }

    public function isClarification(): bool
    {
        return $this->clarification;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }
}
