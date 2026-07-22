<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\StudentApplication;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;

final class RejectApplicationAction
{
    /** @param array{application: StudentApplication, actor_id: int, reason: string} $data */
    public static function run(array $data): StudentApplication
    {
        return app(self::class)->handle($data['application'], $data['actor_id'], $data['reason']);
    }

    public function handle(StudentApplication $application, int $actorId, string $reason): StudentApplication
    {
        if (! $application->isPending()) {
            throw new ApplicationLifecycleException('Only a pending application can be rejected.');
        }

        $application->update([
            'status' => StudentApplication::STATUS_REJECTED,
            'rejected_by' => $actorId,
            'rejected_at' => now(),
            'rejected_reason' => $reason,
        ]);

        return $application;
    }
}
