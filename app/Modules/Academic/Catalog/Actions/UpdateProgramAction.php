<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Program;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateProgramAction
{
    /**
     * @param  array{name: string, code: string, description?: string|null}  $data
     */
    public static function run(Program $program, array $data): Program
    {
        DB::transaction(function () use ($program, $data): void {
            $program->update($data);
        });

        Log::info('Updated Academic Catalog program', [
            'program_id' => $program->id,
            'program_code' => $program->code,
        ]);
        self::flushCache();

        return $program;
    }

    private static function flushCache(): void
    {
        Cache::tags(['programs'])->flush();
    }
}
