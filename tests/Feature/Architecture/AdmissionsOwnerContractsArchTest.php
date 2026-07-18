<?php

declare(strict_types=1);

it('orchestrates Admissions approval and revoke only through owner contracts', function (): void {
    $service = file_get_contents(base_path('app/Services/StudentApplicationService.php')) ?: '';

    expect($service)
        ->toContain('StudentIdentityWriter')
        ->toContain('StudentAccessWriter')
        ->toContain('StudentGuardianRelationshipWriter')
        ->toContain('GuardianAccessGrantWriter')
        ->toContain('ProgramEnrollmentWriter')
        ->toContain('BillingAccountRollbackWriter')
        ->toContain('AdmittedStudentIdentity')
        ->not->toContain('Student::create')
        ->not->toContain('User::create')
        ->not->toContain('CampusUserRole::')
        ->not->toContain('->delete();');
});

it('keeps Admissions command contracts scalar and free of Eloquent models', function (): void {
    $contracts = [
        'app/Shared/Contracts/StudentRegistry/StudentIdentityWriter.php',
        'app/Shared/Contracts/StudentRegistry/DTO/AdmittedStudentIdentity.php',
        'app/Shared/Contracts/Identity/StudentAccessWriter.php',
        'app/Shared/Contracts/Academic/ProgramEnrollmentWriter.php',
        'app/Shared/Contracts/Finance/BillingAccountRollbackWriter.php',
    ];

    foreach ($contracts as $contract) {
        expect(file_get_contents(base_path($contract)) ?: '')
            ->not->toContain('App\\Models\\')
            ->not->toContain('Eloquent');
    }

    expect(file_get_contents(base_path('app/Shared/Contracts/StudentRegistry/StudentIdentityWriter.php')) ?: '')
        ->toContain('register(AdmittedStudentIdentity $identity)')
        ->not->toContain('register(array $identity)');
});
