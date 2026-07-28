<?php

declare(strict_types=1);

use App\Shared\Contracts\Academic\StudentDeferLifecycleReader;
use App\Shared\Contracts\Academic\StudentLifecycleActionReader;
use App\Shared\Contracts\Academic\StudentLifecycleCourseRegistrationGateway;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceCommand;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceEvidenceWriter;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceReader;
use Illuminate\Support\Facades\File;

it('keeps Academic lifecycle flows behind Progression and Finance owner boundaries', function (): void {
    $academicLifecycleFiles = [
        'app/Modules/Academic/Progression/Actions/RecordStudentActionAction.php',
        'app/Modules/Academic/Progression/Actions/ImportStudentActionsFromExcelAction.php',
        'app/Modules/Academic/Http/Requests/StoreStudentActionRequest.php',
        'app/Modules/Academic/Progression/Support/LifecycleFormOptions.php',
        'app/Modules/Academic/Support/StudentActionPreservePreviewResolver.php',
    ];

    foreach ($academicLifecycleFiles as $path) {
        $source = file_get_contents(base_path($path)) ?: '';

        expect($source)
            ->not->toContain('App\\Modules\\Finance\\Actions')
            ->not->toContain('App\\Modules\\Finance\\Services')
            ->not->toContain('App\\Modules\\Finance\\Models');
    }

    $recordAction = file_get_contents(base_path('app/Modules/Academic/Progression/Actions/RecordStudentActionAction.php')) ?: '';

    expect($recordAction)->toContain('TransitionProgramEnrollmentAction');

    $directFinanceConsumers = collect(File::allFiles(base_path('app/Modules/Academic')))
        ->filter(static fn (SplFileInfo $file): bool => str_contains(
            $file->getContents(),
            'use App\\Modules\\Finance\\',
        ))
        ->map(static fn (SplFileInfo $file): string => str_replace(
            base_path().DIRECTORY_SEPARATOR,
            '',
            $file->getPathname(),
        ))
        ->values()
        ->all();

    expect($directFinanceConsumers)->toBeEmpty();

    $remediationAction = file_get_contents(base_path('app/Modules/Academic/Delivery/Actions/RemediateEgcAttendanceFailuresAction.php')) ?: '';

    expect($remediationAction)->toContain('EgcBlockResultReconciler');
});

it('binds narrow Finance lifecycle query and command contracts to Finance implementations', function (): void {
    expect(app()->bound(StudentLifecycleFinanceReader::class))->toBeTrue()
        ->and(app()->bound(StudentLifecycleFinanceCommand::class))->toBeTrue()
        ->and(app()->bound(StudentLifecycleFinanceEvidenceWriter::class))->toBeTrue()
        ->and(app()->bound(StudentLifecycleCourseRegistrationGateway::class))->toBeTrue();

    expect(app()->bound(StudentLifecycleActionReader::class))->toBeTrue();
    expect(app()->bound(StudentDeferLifecycleReader::class))->toBeTrue();

    $deferService = file_get_contents(base_path('app/Modules/Finance/Services/DeferCaseService.php')) ?: '';
    $billingExceptionCollector = file_get_contents(base_path('app/Modules/Finance/Support/BillingExceptionCollector.php')) ?: '';
    $fixBillingExceptionAction = file_get_contents(base_path('app/Modules/Finance/Actions/Operations/FixBillingExceptionAction.php')) ?: '';
    $deferredEnrollmentMatcher = file_get_contents(base_path('app/Modules/Finance/Support/BillingExceptionDeferredEnrollmentMatcher.php')) ?: '';

    expect($deferService)
        ->toContain('StudentLifecycleCourseRegistrationGateway')
        ->toContain('StudentDeferLifecycleReader')
        ->not->toContain('StudentActionLog')
        ->not->toContain("DB::table('course_registrations')")
        ->and($billingExceptionCollector)
        ->toContain('StudentDeferLifecycleReader')
        ->not->toContain('StudentActionLog')
        ->and($deferredEnrollmentMatcher)
        ->toContain('StudentDeferLifecycleReader')
        ->not->toContain('student_action_logs');

    foreach ([$billingExceptionCollector, $fixBillingExceptionAction] as $billingExceptionSource) {
        expect($billingExceptionSource)
            ->toContain('AcademicFinanceChargeSourceGateway')
            ->not->toContain("DB::table('course_registrations')")
            ->not->toContain("DB::table('course_offerings')")
            ->not->toContain("DB::table('course_retake_registrations')")
            ->not->toContain("DB::table('students')")
            ->not->toContain("DB::table('units')")
            ->not->toContain("->where('source_type'")
            ->not->toContain("->where('source_id'")
            ->not->toContain('finance_charges.source_type')
            ->not->toContain('finance_charges.source_id');
    }

    $dueExceptionMapper = file_get_contents(base_path('app/Modules/Finance/Support/LifecycleDueExceptionRowMapper.php')) ?: '';
    $dueExceptionQuery = file_get_contents(base_path('app/Modules/Finance/Queries/Operations/ListLifecycleDueExceptionsQuery.php')) ?: '';

    expect($dueExceptionMapper)
        ->not->toContain('StudentActionLog')
        ->and($dueExceptionQuery)
        ->toContain('StudentLifecycleActionReader');
});
