<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support;

use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use Illuminate\Support\Facades\DB;

final class ApplicantGuardianManager
{
    /** @param array<string, mixed> $data */
    public function create(StudentApplication $application, array $data): ApplicationGuardian
    {
        return DB::transaction(function () use ($application, $data): ApplicationGuardian {
            $isPrimary = ! $application->guardians()->exists() || (bool) ($data['is_primary'] ?? false);
            if ($isPrimary) {
                $this->demoteCurrentPrimary($application);
            }

            return ApplicationGuardian::query()->create([
                ...$data,
                'student_application_id' => (int) $application->id,
                'is_primary' => $isPrimary,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(ApplicationGuardian $guardian, array $data): ApplicationGuardian
    {
        return DB::transaction(function () use ($guardian, $data): ApplicationGuardian {
            $application = $guardian->studentApplication;
            $successor = null;
            $attributes = $data;

            if (array_key_exists('is_primary', $data)) {
                if ((bool) $data['is_primary']) {
                    $this->demoteCurrentPrimary($application, $guardian->id);
                    $attributes['is_primary'] = true;
                } elseif ($guardian->is_primary) {
                    $successor = $this->oldestOther($application, $guardian->id);
                    $attributes['is_primary'] = $successor === null;
                }
            }

            $guardian->update($attributes);
            if ($successor !== null) {
                $successor->update(['is_primary' => true]);
            }

            return $guardian->refresh();
        });
    }

    public function delete(ApplicationGuardian $guardian): void
    {
        DB::transaction(function () use ($guardian): void {
            $application = $guardian->studentApplication;
            $wasPrimary = $guardian->is_primary;
            $guardian->delete();

            if ($wasPrimary) {
                $this->oldestOther($application, $guardian->id)?->update(['is_primary' => true]);
            }
        });
    }

    private function demoteCurrentPrimary(StudentApplication $application, ?int $exceptId = null): void
    {
        $application->guardians()->where('is_primary', true)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_primary' => false]);
    }

    private function oldestOther(StudentApplication $application, int $excludedId): ?ApplicationGuardian
    {
        return $application->guardians()->where('id', '!=', $excludedId)->orderBy('id')->first();
    }
}
