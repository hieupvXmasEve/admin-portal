<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\ParentProfile;
use App\Models\User;
use App\Modules\Identity\Models\GuardianAccessGrant;
use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant as GuardianAccessGrantDto;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipReader;
use App\Shared\Support\Enums\UserType;
use DomainException;
use Illuminate\Support\Facades\DB;

final class GrantGuardianAccessAction
{
    /**
     * @param  array{guardian_relationship_id: int, full_name: string, email: string, phone?: string|null, relationship_type?: string|null, access_level?: string, is_primary_portal_account?: bool}  $data
     */
    public static function run(array $data): GuardianAccessGrantDto
    {
        return DB::transaction(function () use ($data): GuardianAccessGrantDto {
            $email = strtolower(trim($data['email']));

            if ($email === '') {
                throw new DomainException('A Guardian Access Grant requires an account email.');
            }

            $relationship = app(StudentGuardianRelationshipReader::class)->find($data['guardian_relationship_id']);

            if ($relationship === null) {
                throw new DomainException('The Guardian relationship does not exist.');
            }

            $user = User::query()->where('email', $email)->first();

            if ($user !== null) {
                self::ensureUserCanActAsGuardian($user);
                $user->update([
                    'name' => $data['full_name'],
                    'type' => UserType::PARENT,
                ]);
            } else {
                $user = User::query()->create([
                    'name' => $data['full_name'],
                    'email' => $email,
                    'type' => UserType::PARENT,
                    'status' => User::STATUS_ACTIVE,
                ]);
            }

            $parentProfile = ParentProfile::withTrashed()->firstOrNew(['user_id' => $user->id]);
            $parentProfile->fill([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'email_snapshot' => $email,
                'status' => 'active',
            ]);
            $parentProfile->save();

            if ($parentProfile->trashed()) {
                $parentProfile->restore();
            }

            $legacyRelationshipExists = DB::table('parent_student')
                ->where('parent_id', $parentProfile->id)
                ->where('student_id', '!=', $relationship->studentId)
                ->exists();

            if ($legacyRelationshipExists) {
                throw new DomainException('This Guardian account already has a Student access grant.');
            }

            $conflictingGrant = GuardianAccessGrant::query()
                ->where('parent_id', $parentProfile->id)
                ->where('guardian_relationship_id', '!=', $data['guardian_relationship_id'])
                ->first();

            if ($conflictingGrant !== null) {
                throw new DomainException('This Guardian account already has a Student access grant.');
            }

            $grant = GuardianAccessGrant::query()->updateOrCreate(
                ['guardian_relationship_id' => $data['guardian_relationship_id']],
                [
                    'parent_id' => $parentProfile->id,
                    'student_id' => $relationship->studentId,
                    'access_level' => $data['access_level'] ?? 'read_only',
                    'status' => GuardianAccessGrant::STATUS_ACTIVE,
                    'granted_at' => now(),
                    'revoked_at' => null,
                ],
            );

            // Transitional compatibility projection. Runtime access decisions use
            // guardian_access_grants; this row remains for unmigrated consumers.
            if ((bool) ($data['is_primary_portal_account'] ?? false)) {
                DB::table('parent_student')
                    ->where('student_id', $relationship->studentId)
                    ->where('parent_id', '!=', $parentProfile->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false, 'updated_at' => now()]);
            }

            DB::table('parent_student')->updateOrInsert(
                [
                    'parent_id' => $parentProfile->id,
                    'student_id' => $relationship->studentId,
                ],
                [
                    'relationship' => $data['relationship_type'] ?? null,
                    'is_primary' => (bool) ($data['is_primary_portal_account'] ?? false),
                    'access_level' => $grant->access_level,
                    'created_at' => $grant->created_at,
                    'updated_at' => now(),
                ],
            );

            return self::toDto($grant);
        });
    }

    private static function ensureUserCanActAsGuardian(User $user): void
    {
        if ($user->isStudent() || $user->isLecturer() || $user->isStaff() || $user->isService()) {
            throw new DomainException('This account cannot be used for Guardian portal access.');
        }
    }

    private static function toDto(GuardianAccessGrant $grant): GuardianAccessGrantDto
    {
        return new GuardianAccessGrantDto(
            id: (int) $grant->id,
            guardianRelationshipId: (int) $grant->guardian_relationship_id,
            parentId: (int) $grant->parent_id,
            studentId: (int) $grant->student_id,
            accessLevel: (string) $grant->access_level,
            status: (string) $grant->status,
        );
    }
}
