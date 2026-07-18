<?php

declare(strict_types=1);

namespace App\Modules\Institution\Actions;

use App\Models\Campus;
use App\Shared\Contracts\Finance\LegacyDngCampusMappingWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateCampusAction
{
    /**
     * @param  array{name: string, code: string, dng_code?: string|null, address: string}  $data
     */
    public static function run(array $data): Campus
    {
        Log::info('Creating a new institution campus', [
            'campus_code' => $data['code'],
        ]);

        $campus = DB::transaction(function () use ($data): Campus {
            $campus = Campus::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'address' => $data['address'],
            ]);

            app(LegacyDngCampusMappingWriter::class)->replace(
                (int) $campus->id,
                $data['dng_code'] ?? null,
            );

            return $campus;
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
