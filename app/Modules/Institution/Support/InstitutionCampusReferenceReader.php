<?php

declare(strict_types=1);

namespace App\Modules\Institution\Support;

use App\Models\Campus;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;

class InstitutionCampusReferenceReader implements CampusReferenceReader
{
    /**
     * @return list<CampusReference>
     */
    public function all(): array
    {
        return Campus::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Campus $campus): CampusReference => new CampusReference(
                id: (int) $campus->id,
                name: (string) $campus->name,
                code: (string) $campus->code,
            ))
            ->all();
    }
}
