<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Notification\TuitionNoticeDeliveryReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Contracts\View\View;

class TuitionNoticeController extends Controller
{
    public function __construct(
        private readonly StudentReferenceReader $studentReferences,
    ) {}

    public function print(
        int $student,
        int $messageId,
        TuitionNoticeDeliveryReader $notices,
        GuardianAccessGrantReader $guardians,
    ): View {
        $studentReference = $this->assertCampusVisible($student);
        abort_unless($this->userCanPrint(), 403);

        $copy = $notices->renderedCopy($messageId);
        abort_if($copy === null, 404);
        abort_unless($copy->campusId === $studentReference->campusId, 404);

        $parentAccounts = $guardians->accountsForStudent($studentReference->id);
        $allowedUserIds = array_values(array_filter([
            $studentReference->userId,
            ...array_map(static fn ($account): int => (int) $account->id, $parentAccounts),
        ], static fn (?int $id): bool => $id !== null && $id > 0));
        $allowedEmails = array_map(
            static fn ($account): string => mb_strtolower(trim($account->email)),
            $parentAccounts,
        );

        $matchesUser = $copy->recipientUserId !== null && in_array($copy->recipientUserId, $allowedUserIds, true);
        $matchesEmail = $copy->recipientEmail !== null && in_array($copy->recipientEmail, $allowedEmails, true);
        abort_unless($matchesUser || $matchesEmail, 404);

        return view('print.tuition-notice', [
            'campus' => Campus::query()->find($studentReference->campusId),
            'renderedHtml' => $copy->renderedHtml,
        ]);
    }

    protected function assertCampusVisible(int $studentId): StudentReference
    {
        $campusId = app()->bound('campus') ? (app('campus')?->id !== null ? (int) app('campus')->id : null) : null;
        $studentReference = $this->studentReferences->find($studentId);
        abort_if($studentReference === null, 404);

        $ownerCampusId = $studentReference->campusId;
        $visible = $campusId !== null && $ownerCampusId === $campusId;
        if (! $visible && ! (request()->user()?->can('view_finance_all_campus') ?? false)) {
            abort(404);
        }

        return $studentReference;
    }

    private function userCanPrint(): bool
    {
        $user = request()->user();

        return (bool) (
            $user?->can('view_finance_student_overview')
            || $user?->can('view_finance_operations_due_calendar')
        );
    }
}
