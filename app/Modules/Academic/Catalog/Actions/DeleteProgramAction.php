<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Program;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteProgramAction
{
    public static function run(Program $program): void
    {
        DB::transaction(function () use ($program): void {
            if ($program->specializations()->exists()) {
                throw new DomainException('Cannot delete program with existing specializations.');
            }

            if ($program->curriculumVersions()->exists()) {
                throw new DomainException('Cannot delete program with existing curriculum versions.');
            }

            $program->delete();
        });

        Log::warning('Deleted Academic Catalog program', [
            'program_id' => $program->id,
            'program_code' => $program->code,
        ]);
        Cache::tags(['programs'])->flush();
    }
}
