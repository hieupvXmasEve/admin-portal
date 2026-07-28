<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Unit picker rows for non-Catalog Academic screens, so those consumers reach
 * Unit through a Catalog-owned seam instead of importing the shared model
 * directly.
 */
class GetUnitReferenceOptionsQuery
{
    /**
     * Code-ordered units.
     *
     * @return Collection<int, Unit>
     */
    public function options(): Collection
    {
        return Unit::orderBy('code')->get(['id', 'code', 'name']);
    }
}
