<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\StudentApplication;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;
use App\Shared\Contracts\Academic\ProgramEnrollmentWriter;
use App\Shared\Contracts\Finance\BillingAccountRollbackWriter;
use App\Shared\Contracts\Identity\StudentAccessWriter;
use App\Shared\Contracts\StudentRegistry\StudentIdentityWriter;
use Illuminate\Support\Facades\DB;

final class RevokeApplicationAction
{
    public function __construct(
        private readonly StudentIdentityWriter $studentIdentityWriter,
        private readonly StudentAccessWriter $studentAccessWriter,
        private readonly ProgramEnrollmentWriter $programEnrollmentWriter,
        private readonly BillingAccountRollbackWriter $billingAccountRollbackWriter,
    ) {}

    /** @param array{application: StudentApplication, actor_id: int} $data */
    public static function run(array $data): StudentApplication
    {
        return app(self::class)->handle($data['application'], $data['actor_id']);
    }

    public function handle(StudentApplication $application, int $actorId): StudentApplication
    {
        if (! $application->isEnrolled()) {
            throw new ApplicationLifecycleException('Only an enrolled application can be revoked.');
        }
        if ($application->student_id === null) {
            throw new ApplicationLifecycleException('This application has no linked student to revoke.');
        }

        return DB::transaction(function () use ($application, $actorId): StudentApplication {
            $student = $this->studentIdentityWriter->requireRevocable((int) $application->student_id);
            $this->programEnrollmentWriter->removeUnstarted($student->id);
            $this->billingAccountRollbackWriter->removeEmptyForStudent($student->id);
            $application->update([
                'status' => StudentApplication::STATUS_PENDING,
                'student_id' => null,
                'approved_by' => null,
                'approved_at' => null,
                'revoked_by' => $actorId,
                'revoked_at' => now(),
            ]);
            $this->studentIdentityWriter->revoke($student->id);
            if ($student->userId !== null) {
                $this->studentAccessWriter->revoke($student->userId);
            }

            return $application;
        });
    }
}
