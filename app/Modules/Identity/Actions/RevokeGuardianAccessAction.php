<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\ParentProfile;
use App\Modules\Identity\Models\GuardianAccessGrant;
use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant as GuardianAccessGrantDto;
use Illuminate\Support\Facades\DB;

final class RevokeGuardianAccessAction
{
    public static function revokeLegacyPrimaryForStudent(int $studentId): void
    {
        DB::transaction(function () use ($studentId): void {
            $projection = DB::table('parent_student')
                ->where('student_id', $studentId)
                ->where('is_primary', true)
                ->first(['id', 'parent_id']);

            if ($projection === null) {
                return;
            }

            DB::table('parent_student')->where('id', $projection->id)->delete();
            $parentProfile = ParentProfile::query()->with('user')->find($projection->parent_id);

            if ($parentProfile === null || $parentProfile->students()->exists()) {
                return;
            }

            $parentProfile->user?->tokens()->delete();
            $parentProfile->update(['status' => 'inactive']);
        });
    }

    /** @param array{guardian_relationship_id: int} $data */
    public static function run(array $data): GuardianAccessGrantDto
    {
        return DB::transaction(function () use ($data): GuardianAccessGrantDto {
            $grant = GuardianAccessGrant::query()
                ->where('guardian_relationship_id', $data['guardian_relationship_id'])
                ->firstOrFail();

            $grant->update([
                'status' => GuardianAccessGrant::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

            $parentProfile = ParentProfile::query()->with('user')->find($grant->parent_id);
            $parentProfile?->user?->tokens()->delete();

            $hasOtherGrant = GuardianAccessGrant::query()
                ->where('parent_id', $grant->parent_id)
                ->where('status', GuardianAccessGrant::STATUS_ACTIVE)
                ->exists();

            if (! $hasOtherGrant) {
                $parentProfile?->update(['status' => 'inactive']);
            }

            return new GuardianAccessGrantDto(
                id: (int) $grant->id,
                guardianRelationshipId: (int) $grant->guardian_relationship_id,
                parentId: (int) $grant->parent_id,
                studentId: (int) $grant->student_id,
                accessLevel: (string) $grant->access_level,
                status: (string) $grant->status,
            );
        });
    }
}
