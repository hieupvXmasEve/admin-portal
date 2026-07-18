<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Campus;
use App\Shared\Contracts\Finance\LegacyDngCampusMappingWriter as LegacyDngCampusMappingWriterContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Transitional adapter for the legacy campuses.dng_code column.
 *
 * Issue 05 replaces this compatibility writer with Finance-owned mapping
 * records. Institution must not interpret provider metadata in the meantime.
 */
class LegacyDngCampusMappingWriter implements LegacyDngCampusMappingWriterContract
{
    public function replace(int $campusId, ?string $dngCode): void
    {
        Validator::make(
            ['dng_code' => $dngCode],
            ['dng_code' => ['nullable', 'string', 'max:255', Rule::unique('campuses', 'dng_code')->ignore($campusId)]],
            [
                'dng_code.max' => 'DNG code must not exceed 255 characters',
                'dng_code.unique' => 'DNG code already exists',
            ],
        )->validate();

        Campus::query()
            ->whereKey($campusId)
            ->update(['dng_code' => $dngCode]);
    }
}
