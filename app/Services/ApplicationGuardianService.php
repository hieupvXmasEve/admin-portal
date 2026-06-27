<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the Guardian list on an Application while preserving the
 * "exactly one primary Guardian" invariant.
 *
 * The DB enforces the *at most one* half (a generated-column unique index); this
 * service keeps the *at least one* half: whenever any Guardian exists, exactly
 * one is primary. Demotion always runs before promotion inside a transaction so
 * two primary rows for the same Application never coexist, even transiently.
 */
class ApplicationGuardianService
{
    /**
     * Add a Guardian to the Application.
     *
     * The first Guardian is always primary; subsequent ones are primary only
     * when explicitly requested (in which case the previous primary is demoted).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(StudentApplication $application, array $data): ApplicationGuardian
    {
        return DB::transaction(function () use ($application, $data): ApplicationGuardian {
            $isFirst = ! $application->guardians()->exists();
            $shouldBePrimary = $isFirst || (bool) ($data['is_primary'] ?? false);

            if ($shouldBePrimary) {
                $this->demoteCurrentPrimary($application);
            }

            return $application->guardians()->create([...$data, 'is_primary' => $shouldBePrimary]);
        });
    }

    /**
     * Update a Guardian, keeping the single-primary invariant intact.
     *
     * Promotion demotes the previous primary. A request to demote the current
     * primary is honoured only when another Guardian can take over; otherwise
     * the Guardian stays primary so the Application never ends up with none.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ApplicationGuardian $guardian, array $data): ApplicationGuardian
    {
        return DB::transaction(function () use ($guardian, $data): ApplicationGuardian {
            $application = $guardian->studentApplication;

            // Resolve a successor to promote *after* this Guardian is demoted, so
            // two primary rows never coexist (the DB unique index forbids it).
            $promoteSuccessor = null;
            $attributes = $data;

            if (array_key_exists('is_primary', $data)) {
                if ((bool) $data['is_primary']) {
                    $this->demoteCurrentPrimary($application, exceptId: $guardian->id);
                    $attributes['is_primary'] = true;
                } elseif ($guardian->is_primary) {
                    // Refuse to leave the Application with no primary: promote the
                    // oldest other Guardian, or keep this one primary if alone.
                    $promoteSuccessor = $this->oldestOtherGuardian($application, $guardian->id);
                    $attributes['is_primary'] = $promoteSuccessor === null;
                }
            }

            $guardian->update($attributes);

            $promoteSuccessor?->update(['is_primary' => true]);

            return $guardian->refresh();
        });
    }

    /**
     * Remove a Guardian. If the primary is removed and others remain, the oldest
     * remaining Guardian is promoted so exactly one primary survives.
     */
    public function delete(ApplicationGuardian $guardian): void
    {
        DB::transaction(function () use ($guardian): void {
            $application = $guardian->studentApplication;
            $wasPrimary = $guardian->is_primary;

            $guardian->delete();

            if ($wasPrimary) {
                $successor = $this->oldestOtherGuardian($application, $guardian->id);
                $successor?->update(['is_primary' => true]);
            }
        });
    }

    /**
     * Demote the Application's current primary Guardian, if any.
     */
    private function demoteCurrentPrimary(StudentApplication $application, ?int $exceptId = null): void
    {
        $application->guardians()
            ->where('is_primary', true)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_primary' => false]);
    }

    /**
     * The oldest Guardian on the Application other than the given one.
     */
    private function oldestOtherGuardian(StudentApplication $application, int $excludeId): ?ApplicationGuardian
    {
        return $application->guardians()
            ->where('id', '!=', $excludeId)
            ->orderBy('id')
            ->first();
    }
}
