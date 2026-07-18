<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Observers;

use App\Models\Lecture;
use App\Modules\Academic\FacultyWorkforce\Actions\DispatchFacultyAccessEligibilityOutboxAction;
use App\Modules\Academic\FacultyWorkforce\Support\FacultyAccessEligibilityResolver;
use App\Shared\Contracts\Identity\DTO\FacultyAccessEligibility;

final class SyncLecturerAccessEligibility
{
    /** @var array<int, int> */
    private array $previousUserIds = [];

    public function __construct(
        private readonly FacultyAccessEligibilityResolver $resolver,
    ) {}

    public function created(Lecture $lecturer): void
    {
        if ($lecturer->user_id === null) {
            return;
        }

        $this->dispatch($this->resolver->resolve($lecturer, $this->sourceVersion($lecturer)));
    }

    public function updated(Lecture $lecturer): void
    {
        if (! $lecturer->wasChanged([
            'user_id',
            'is_active',
            'employment_status',
            'employment_type',
            'contract_end_date',
        ])) {
            return;
        }

        $sourceVersion = $this->sourceVersion($lecturer);
        $previousUserId = $this->previousUserIds[(int) $lecturer->id] ?? null;
        unset($this->previousUserIds[(int) $lecturer->id]);

        if ($previousUserId !== null && $previousUserId !== (int) $lecturer->user_id) {
            $this->dispatch($this->resolver->revokeForUnlinkedAccount($lecturer, $previousUserId, $sourceVersion));
        }

        if ($lecturer->user_id !== null) {
            $this->dispatch($this->resolver->resolve($lecturer, $sourceVersion));
        }
    }

    public function deleting(Lecture $lecturer): void
    {
        if ($lecturer->user_id === null) {
            return;
        }

        $this->dispatch($this->resolver->revokeForUnlinkedAccount(
            $lecturer,
            (int) $lecturer->user_id,
            'deleting:'.(string) $lecturer->updated_at,
        ));
    }

    public function updating(Lecture $lecturer): void
    {
        if ($lecturer->isDirty([
            'user_id',
            'is_active',
            'employment_status',
            'employment_type',
            'contract_end_date',
        ]) && $lecturer->user_id !== null) {
            // Persist the source event before the Workforce write. Dispatch only
            // happens after persistence; a crash in between leaves a retryable row.
            DispatchFacultyAccessEligibilityOutboxAction::enqueue(
                $this->resolver->resolve($lecturer, $this->sourceVersion($lecturer)),
            );
        }

        if ($lecturer->isDirty('user_id') && $lecturer->getOriginal('user_id') !== null) {
            $this->previousUserIds[(int) $lecturer->id] = (int) $lecturer->getOriginal('user_id');
        }
    }

    private function sourceVersion(Lecture $lecturer): string
    {
        return (string) ($lecturer->updated_at ?? now());
    }

    private function dispatch(FacultyAccessEligibility $eligibility): void
    {
        $outbox = DispatchFacultyAccessEligibilityOutboxAction::enqueue($eligibility);
        DispatchFacultyAccessEligibilityOutboxAction::run(['outbox_id' => (int) $outbox->id]);
    }
}
