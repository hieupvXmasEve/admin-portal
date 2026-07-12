<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use App\Shared\Contracts\Finance\SettlementPositionReader;

final class SettlementPositionWorklistReader
{
    public function __construct(
        private readonly SettlementPositionReader $reader,
    ) {}

    /**
     * Read one canonical aggregate for every arbitrary worklist group.
     *
     * The caller owns the business filtering and supplies exact payable-line
     * ids. The Finance reader owns aggregation, validation, and issue routing.
     *
     * @param  array<int|string, list<int>>  $lineIdsByGroup
     * @return array<int|string, SettlementPosition>
     */
    public function forLineGroups(array $lineIdsByGroup): array
    {
        $groups = [];

        foreach ($lineIdsByGroup as $group => $lineIds) {
            $lineIds = $this->positiveUniqueIds($lineIds);

            if ($lineIds === []) {
                continue;
            }

            $groups[$group] = $lineIds;
        }

        if ($groups === []) {
            return [];
        }

        $positions = $this->reader->batch(array_map(
            static fn (array $lineIds): SettlementPositionScope => SettlementPositionScope::payableLines($lineIds),
            array_values($groups),
        ));

        $result = [];
        $index = 0;

        foreach ($groups as $group => $unusedLineIds) {
            $result[$group] = $positions[$index];
            $index++;
        }

        return $result;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function positiveUniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }
}
