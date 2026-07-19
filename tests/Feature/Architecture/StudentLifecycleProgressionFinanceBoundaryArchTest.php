<?php

declare(strict_types=1);

use App\Shared\Contracts\Academic\StudentLifecycleCourseRegistrationGateway;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceCommand;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceEvidenceWriter;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceReader;
use Illuminate\Support\Facades\File;

it('keeps Academic lifecycle flows behind Progression and Finance owner boundaries', function (): void {
    $academicLifecycleFiles = [
        'app/Modules/Academic/Progression/Actions/RecordStudentActionAction.php',
        'app/Modules/Academic/Actions/ImportStudentActionsFromExcelAction.php',
        'app/Modules/Academic/Http/Requests/StoreStudentActionRequest.php',
        'app/Modules/Academic/Support/LifecycleFormOptions.php',
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

    $remediationAction = file_get_contents(base_path('app/Modules/Academic/Actions/RemediateEgcAttendanceFailuresAction.php')) ?: '';

    expect($remediationAction)->toContain('EgcBlockResultReconciler');
});

it('binds narrow Finance lifecycle query and command contracts to Finance implementations', function (): void {
    expect(app()->bound(StudentLifecycleFinanceReader::class))->toBeTrue()
        ->and(app()->bound(StudentLifecycleFinanceCommand::class))->toBeTrue()
        ->and(app()->bound(StudentLifecycleFinanceEvidenceWriter::class))->toBeTrue()
        ->and(app()->bound(StudentLifecycleCourseRegistrationGateway::class))->toBeTrue();

    $deferService = file_get_contents(base_path('app/Modules/Finance/Services/DeferCaseService.php')) ?: '';

    expect($deferService)
        ->toContain('StudentLifecycleCourseRegistrationGateway')
        ->not->toContain("DB::table('course_registrations')");
});
