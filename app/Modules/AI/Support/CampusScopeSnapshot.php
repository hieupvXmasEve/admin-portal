<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

/**
 * Multi-campus-capable snapshot of the campus scope applied to an AI tool call.
 *
 * Serializes backward-compatibly to `['campus_ids' => int[]]` (the historical chat
 * shape) while being able to represent a span across multiple campuses — including
 * an All-campus AI scope holder spanning every permitted campus (ADR-0010). The
 * `scope` discriminator is carried for in-process consumers (the later MCP campus
 * resolver and the audit recorder) but is intentionally NOT serialized, so existing
 * chat-channel audit rows stay byte-identical and web-chat behavior is unchanged.
 */
final class CampusScopeSnapshot
{
    public const SCOPE_NONE = 'none';

    public const SCOPE_SINGLE = 'single';

    public const SCOPE_SPAN = 'span';

    public const SCOPE_ALL_CAMPUS = 'all_campus';

    /**
     * @param  list<int>  $campusIds
     */
    private function __construct(
        private readonly array $campusIds,
        private readonly string $scope,
    ) {}

    /**
     * A single resolved campus (or none when null) — the web-chat default.
     */
    public static function single(?int $campusId): self
    {
        if ($campusId === null) {
            return self::none();
        }

        return new self([$campusId], self::SCOPE_SINGLE);
    }

    /**
     * An explicit span across several permitted campuses.
     *
     * @param  list<int>  $campusIds
     */
    public static function span(array $campusIds): self
    {
        $ids = self::normalize($campusIds);

        return new self($ids, $ids === [] ? self::SCOPE_NONE : self::SCOPE_SPAN);
    }

    /**
     * A span produced by an All-campus AI scope holder over every permitted campus.
     *
     * @param  list<int>  $campusIds
     */
    public static function allCampus(array $campusIds): self
    {
        return new self(self::normalize($campusIds), self::SCOPE_ALL_CAMPUS);
    }

    public static function none(): self
    {
        return new self([], self::SCOPE_NONE);
    }

    /**
     * @return list<int>
     */
    public function campusIds(): array
    {
        return $this->campusIds;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function spansMultipleCampuses(): bool
    {
        return count($this->campusIds) > 1;
    }

    /**
     * Backward-compatible serialization consumed by the audit recorder and surfaced
     * in result objects' `campus_scope_snapshot`.
     *
     * @return array{campus_ids: list<int>}
     */
    public function toArray(): array
    {
        return ['campus_ids' => $this->campusIds];
    }

    /**
     * @param  list<int>  $campusIds
     * @return list<int>
     */
    private static function normalize(array $campusIds): array
    {
        return array_values(array_unique(array_map('intval', $campusIds)));
    }
}
