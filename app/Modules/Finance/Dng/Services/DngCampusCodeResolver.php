<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Modules\Finance\Dng\Models\DngCampusMapping;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DngCampusCodeResolver
{
    public function __construct(
        private readonly CampusReferenceReader $campusReferences,
    ) {}

    public function currentCampusCode(): ?string
    {
        $campusId = $this->currentCampusId();

        return $campusId !== null ? $this->mappingCodeForCampusId($campusId) : null;
    }

    public function requireCurrentCampusCode(): string
    {
        $campusId = $this->currentCampusId();
        $dngCode = $campusId !== null ? $this->mappingCodeForCampusId($campusId) : null;

        if ($dngCode !== null) {
            return $dngCode;
        }

        throw ValidationException::withMessages([
            'campus_code' => 'DNG code is not configured for the selected campus. Configure it in Finance → DNG Campus Mapping before creating or reconciling payment requests.',
        ]);
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

    private function currentCampusId(): ?int
    {
        if (! app()->bound('campus')) {
            return null;
        }

        $campus = app('campus');
        $campusId = is_object($campus) && isset($campus->id) ? (int) $campus->id : 0;

        return $campusId > 0 ? $this->campusReferences->find($campusId)?->id : null;
    }

    private function mappingCodeForCampusId(int $campusId): ?string
    {
        $providerCode = DngCampusMapping::query()
            ->where('campus_id', $campusId)
            ->value('provider_code');

        $providerCode = trim((string) $providerCode);

        return $providerCode !== '' ? $providerCode : null;
    }
}
