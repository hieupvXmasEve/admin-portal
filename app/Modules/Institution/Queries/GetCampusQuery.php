<?php

declare(strict_types=1);

namespace App\Modules\Institution\Queries;

use App\Models\Campus;

class GetCampusQuery
{
    /**
     * @return array{id: int, name: string, code: string, address: string}
     */
    public function forEdit(int|string $campusId): array
    {
        $campus = Campus::query()->findOrFail($campusId);

        return [
            'id' => (int) $campus->id,
            'name' => (string) $campus->name,
            'code' => (string) $campus->code,
            'address' => (string) $campus->address,
        ];
    }
}
