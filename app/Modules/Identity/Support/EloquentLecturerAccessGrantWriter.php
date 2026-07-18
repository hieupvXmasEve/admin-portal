<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\LecturerAccessEligibilityHandoff;
use App\Modules\Identity\Models\LecturerAccessGrant as LecturerAccessGrantModel;
use App\Shared\Contracts\Identity\DTO\FacultyAccessEligibility;
use App\Shared\Contracts\Identity\DTO\LecturerAccessGrant;
use App\Shared\Contracts\Identity\LecturerAccessGrantWriter;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class EloquentLecturerAccessGrantWriter implements LecturerAccessGrantWriter
{
    public function apply(FacultyAccessEligibility $eligibility): LecturerAccessGrant
    {
        return DB::transaction(function () use ($eligibility): LecturerAccessGrant {
            $handoff = LecturerAccessEligibilityHandoff::query()
                ->where('deduplication_key', $eligibility->deduplicationKey)
                ->lockForUpdate()
                ->first();

            if ($handoff !== null) {
                $existingGrant = LecturerAccessGrantModel::query()
                    ->where('user_id', $eligibility->userId)
                    ->firstOrFail();

                return $this->toDto($existingGrant);
            }

            $grant = LecturerAccessGrantModel::query()
                ->where(function ($query) use ($eligibility): void {
                    $query->where('lecturer_id', $eligibility->lecturerId)
                        ->orWhere('user_id', $eligibility->userId);
                })
                ->lockForUpdate()
                ->firstOrNew();
            $wasActive = $grant->exists && $grant->status === LecturerAccessGrantModel::STATUS_ACTIVE;
            $isAccountTransfer = $grant->exists && (int) $grant->user_id !== $eligibility->userId;
            $status = $eligibility->isEligible
                ? LecturerAccessGrantModel::STATUS_ACTIVE
                : LecturerAccessGrantModel::STATUS_REVOKED;
            $now = now();

            $grant->fill([
                'user_id' => $eligibility->userId,
                'lecturer_id' => $eligibility->lecturerId,
                'token_subject_type' => $eligibility->tokenSubjectType,
                'status' => $status,
                'reason' => $eligibility->reason,
                'eligibility_evaluated_at' => $eligibility->evaluatedAt,
                'granted_at' => $status === LecturerAccessGrantModel::STATUS_ACTIVE
                    ? ($grant->granted_at ?? $now)
                    : $grant->granted_at,
                'revoked_at' => $status === LecturerAccessGrantModel::STATUS_REVOKED
                    ? ($grant->revoked_at ?? $now)
                    : null,
            ]);
            $grant->save();

            $revokedTokenCount = 0;
            if (($status === LecturerAccessGrantModel::STATUS_REVOKED && $wasActive) || $isAccountTransfer) {
                // Sanctum tokens are issued to the legacy lecturer authenticatable.
                // Identity receives that opaque identifier in the command rather than
                // querying Faculty Workforce persistence.
                $revokedTokenCount = PersonalAccessToken::query()
                    ->where('tokenable_type', $eligibility->tokenSubjectType)
                    ->where('tokenable_id', $eligibility->lecturerId)
                    ->delete();
            }

            LecturerAccessEligibilityHandoff::query()->create([
                'deduplication_key' => $eligibility->deduplicationKey,
                'lecturer_id' => $eligibility->lecturerId,
                'user_id' => $eligibility->userId,
                'eligibility_status' => $status,
                'reason' => $eligibility->reason,
                'revoked_token_count' => $revokedTokenCount,
                'payload' => [
                    'evaluated_at' => $eligibility->evaluatedAt,
                    'source' => 'faculty_workforce',
                ],
                'applied_at' => $now,
            ]);

            return $this->toDto($grant);
        });
    }

    private function toDto(LecturerAccessGrantModel $grant): LecturerAccessGrant
    {
        return new LecturerAccessGrant(
            userId: (int) $grant->user_id,
            lecturerId: (int) $grant->lecturer_id,
            status: (string) $grant->status,
            reason: (string) $grant->reason,
        );
    }
}
