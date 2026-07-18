<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngCampusMapping;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DngCampusCodeResolver
{
    public function currentCampusCode(): ?string
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        return $campus instanceof Campus ? $this->mappingCodeForCampus($campus) : null;
    }

    public function requireCurrentCampusCode(): string
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        return $this->requireForCampus($campus instanceof Campus ? $campus : null);
    }

    public function requireForCampusId(int $campusId): string
    {
        $providerCode = DngCampusMapping::query()
            ->where('campus_id', $campusId)
            ->value('provider_code');

        $providerCode = trim((string) $providerCode);
        if ($providerCode !== '') {
            return $providerCode;
        }

        throw ValidationException::withMessages([
            'campus_code' => 'DNG code is not configured for the Student campus. Configure it in Finance → DNG Campus Mapping before creating payment requests.',
        ]);
    }

    /**
     * Transitional adapter for flows not yet migrated to StudentReferenceReader.
     * New collection paths must use requireForCampusId().
     */
    public function requireForStudent(object $student): string
    {
        $campusId = isset($student->campus_id) ? (int) $student->campus_id : 0;
        if ($campusId <= 0) {
            throw ValidationException::withMessages([
                'campus_code' => 'DNG code cannot be resolved because the Student campus reference is missing.',
            ]);
        }

        return $this->requireForCampusId($campusId);
    }

    /**
     * @return array<int, string>
     */
    public function allConfiguredCampusCodes(): array
    {
        /** @var Collection<int, string> $codes */
        $codes = DngCampusMapping::query()->pluck('provider_code');

        return $codes
            ->map(fn (string $code) => trim($code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function requireForCampus(?Campus $campus): string
    {
        $dngCode = $campus instanceof Campus ? $this->mappingCodeForCampus($campus) : null;
        if ($dngCode !== null) {
            return $dngCode;
        }

        throw ValidationException::withMessages([
            'campus_code' => 'DNG code is not configured for the selected campus. Configure it in Finance → DNG Campus Mapping before creating or reconciling payment requests.',
        ]);
    }

    private function mappingCodeForCampus(Campus $campus): ?string
    {
        $providerCode = DngCampusMapping::query()
            ->where('campus_id', $campus->id)
            ->value('provider_code');

        $providerCode = trim((string) $providerCode);

        return $providerCode !== '' ? $providerCode : null;
    }
}
