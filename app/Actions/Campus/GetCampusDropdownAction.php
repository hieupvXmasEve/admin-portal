<?php

declare(strict_types=1);

namespace App\Actions\Campus;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class GetCampusDropdownAction
{
    public function execute(?string $search): Collection
    {
        $limit = 50;
        $cacheKey = 'campuses:api:' . md5(json_encode([
            'search' => $search,
            'limit' => $limit,
        ]));

        return Cache::tags(['campuses', 'campuses.api'])->rememberForever($cacheKey, function () use ($search, $limit) {
            return Campus::select('id', 'name', 'code')
                ->when($search, function ($query, $term) {
                    $query->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%");
                    });
                })
                ->orderBy('name')
                ->limit($limit)
                ->get();
        });
    }
}
