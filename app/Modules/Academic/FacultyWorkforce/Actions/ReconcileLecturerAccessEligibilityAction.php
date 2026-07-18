<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Actions;

use App\Models\Lecture;
use App\Modules\Academic\FacultyWorkforce\Models\FacultyAccessEligibilityOutbox;
use App\Modules\Academic\FacultyWorkforce\Support\FacultyAccessEligibilityResolver;

final class ReconcileLecturerAccessEligibilityAction
{
    /** @param array{lecturer_id?: int|null} $data */
    public static function run(array $data = []): int
    {
        $resolver = app(FacultyAccessEligibilityResolver::class);
        FacultyAccessEligibilityOutbox::query()
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('id')
            ->each(fn (FacultyAccessEligibilityOutbox $outbox) => DispatchFacultyAccessEligibilityOutboxAction::run([
                'outbox_id' => (int) $outbox->id,
            ]));
        $query = Lecture::query()->whereNotNull('user_id')->orderBy('id');

        if (($data['lecturer_id'] ?? null) !== null) {
            $query->whereKey($data['lecturer_id']);
        }

        $count = 0;
        $query->each(function (Lecture $lecturer) use ($resolver, &$count): void {
            $outbox = DispatchFacultyAccessEligibilityOutboxAction::enqueue($resolver->resolve(
                $lecturer,
                'reconciliation:'.(string) ($lecturer->updated_at ?? $lecturer->id),
            ));
            DispatchFacultyAccessEligibilityOutboxAction::run(['outbox_id' => (int) $outbox->id]);
            $count++;
        });

        return $count;
    }
}
