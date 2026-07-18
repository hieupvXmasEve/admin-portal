<?php

declare(strict_types=1);

namespace App\Modules\Institution\Actions;

use App\Models\Campus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateCampusAction
{
    /**
     * @param  array{campus_id: int, name: string, code: string, address: string}  $data
     */
    public static function run(array $data): Campus
    {
        $campus = Campus::query()->findOrFail($data['campus_id']);

        Log::info('Updating institution campus', [
            'campus_id' => $campus->id,
            'campus_code' => $data['code'],
        ]);

        DB::transaction(function () use ($campus, $data): void {
            $campus->update([
                'name' => $data['name'],
                'code' => $data['code'],
                'address' => $data['address'],
            ]);
        });

        self::invalidateCaches();

        return $campus;
    }

    private static function invalidateCaches(): void
    {
        Cache::tags(['campuses'])->flush();
        Cache::tags(['campuses.index'])->flush();
        Cache::tags(['campuses.show'])->flush();
        Cache::tags(['campuses.api'])->flush();
    }
}
