<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class BulkDeleteUnitsAction
{
    public function __construct(private readonly DeleteUnitAction $deleteUnit) {}

    /**
     * @param  list<int>  $unitIds
     * @return array{deleted: list<string>, failed: list<array{code: string, reason: string|null}>}
     */
    public function handle(array $unitIds): array
    {
        return DB::transaction(function () use ($unitIds): array {
            $deleted = [];
            $failed = [];

            foreach (Unit::query()->whereIn('id', $unitIds)->get() as $unit) {
                $eligibility = $this->deleteUnit->eligibility($unit);
                if (! $eligibility['allowed']) {
                    $failed[] = ['code' => $unit->code, 'reason' => $eligibility['reason']];

                    continue;
                }

                $this->deleteUnit->handle($unit);
                $deleted[] = $unit->code;
            }

            return compact('deleted', 'failed');
        });
    }
}
