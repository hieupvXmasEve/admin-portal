<?php

declare(strict_types=1);

namespace App\Modules\Institution\Support;

use App\Models\Campus;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;

class InstitutionCampusReferenceReader implements CampusReferenceReader
{
    public function find(int $campusId): ?CampusReference
    {
        return $this->reference(Campus::query()->find($campusId));
    }

    /**
     * @return list<CampusReference>
     */
    public function all(): array
    {
        return Campus::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Campus $campus): CampusReference => $this->reference($campus))
            ->all();
    }

    private function reference(?Campus $campus): ?CampusReference
    {
        if ($campus === null) {
            return null;
        }

        return new CampusReference(
            id: (int) $campus->id,
            name: (string) $campus->name,
            code: (string) $campus->code,
        );
    }
}
