<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Actions;

use App\Models\Lecture;
use App\Modules\Academic\FacultyWorkforce\Models\FacultyAccessEligibilityOutbox;
use App\Modules\Academic\FacultyWorkforce\Support\FacultyAccessEligibilityResolver;
use App\Shared\Contracts\Identity\DTO\FacultyAccessEligibility;
use App\Shared\Contracts\Identity\LecturerAccessGrantWriter;

final class DispatchFacultyAccessEligibilityOutboxAction
{
    public static function enqueue(FacultyAccessEligibility $eligibility): FacultyAccessEligibilityOutbox
    {
        return FacultyAccessEligibilityOutbox::query()->firstOrCreate(
            ['deduplication_key' => $eligibility->deduplicationKey],
            [
                'lecturer_id' => $eligibility->lecturerId,
                'user_id' => $eligibility->userId,
                'token_subject_type' => $eligibility->tokenSubjectType,
                'is_eligible' => $eligibility->isEligible,
                'reason' => $eligibility->reason,
                'evaluated_at' => $eligibility->evaluatedAt,
                'status' => 'pending',
            ],
        );
    }

    /** @param array{outbox_id: int} $data */
    public static function run(array $data): void
    {
        $outbox = FacultyAccessEligibilityOutbox::query()->findOrFail($data['outbox_id']);
        if ($outbox->status === 'dispatched') {
            return;
        }

        $lecturer = Lecture::query()->find($outbox->lecturer_id);
        $currentEligibility = $lecturer === null
            ? null
            : app(FacultyAccessEligibilityResolver::class)->resolve($lecturer, 'outbox-verification');
        if ($currentEligibility === null
            || $currentEligibility->userId !== (int) $outbox->user_id
            || $currentEligibility->isEligible !== (bool) $outbox->is_eligible
            || $currentEligibility->reason !== $outbox->reason) {
            $outbox->update(['status' => 'discarded', 'last_error' => 'Source transition did not commit.']);

            return;
        }

        try {
            app(LecturerAccessGrantWriter::class)->apply(new FacultyAccessEligibility(
                lecturerId: (int) $outbox->lecturer_id,
                userId: (int) $outbox->user_id,
                tokenSubjectType: (string) $outbox->token_subject_type,
                isEligible: (bool) $outbox->is_eligible,
                reason: (string) $outbox->reason,
                deduplicationKey: (string) $outbox->deduplication_key,
                evaluatedAt: $outbox->evaluated_at->toISOString(),
            ));
            $outbox->update(['status' => 'dispatched', 'dispatched_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            $outbox->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);

            throw $exception;
        }
    }
}
