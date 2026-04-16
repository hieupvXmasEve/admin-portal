<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Models\Campus;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DngCampusCodeResolver
{
    public function currentCampusCode(): ?string
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        return $campus instanceof Campus ? $campus->getDngCode() : null;
    }

    public function requireCurrentCampusCode(): string
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        return $this->requireForCampus($campus instanceof Campus ? $campus : null);
    }

    public function requireForStudent(Student $student): string
    {
        $student->loadMissing('campus');

        return $this->requireForCampus($student->campus);
    }

    /**
     * @return array<int, string>
     */
    public function allConfiguredCampusCodes(): array
    {
        /** @var Collection<int, string> $codes */
        $codes = Campus::query()
            ->whereNotNull('dng_code')
            ->pluck('dng_code');

        return $codes
            ->map(fn (string $code) => trim($code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function requireForCampus(?Campus $campus): string
    {
        $dngCode = $campus?->getDngCode();
        if ($dngCode !== null) {
            return $dngCode;
        }

        throw ValidationException::withMessages([
            'campus_code' => 'DNG code is not configured for the selected campus.',
        ]);
    }
}
