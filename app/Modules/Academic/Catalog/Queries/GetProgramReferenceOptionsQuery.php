<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Program;
use Illuminate\Support\Collection;

/**
 * Program reference list for Academic reporting pickers, so those consumers
 * reach Program through a Catalog-owned seam instead of importing the shared
 * model directly.
 */
class GetProgramReferenceOptionsQuery
{
    /**
     * Name-ordered `id`/`name` rows for filter dropdowns.
     *
     * @return Collection<int, Program>
     */
    public function options(): Collection
    {
        return Program::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }
}
