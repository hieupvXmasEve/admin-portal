<?php

declare(strict_types=1);

it('keeps Student Registry Guardian relationships free of Identity access state', function (): void {
    $files = [
        base_path('app/Modules/StudentRegistry/Models/StudentGuardianRelationship.php'),
        base_path('app/Modules/StudentRegistry/Actions/PreserveStudentGuardianRelationshipsAction.php'),
        base_path('app/Modules/StudentRegistry/Support/EloquentStudentGuardianRelationshipReader.php'),
        base_path('app/Modules/StudentRegistry/Support/EloquentStudentGuardianRelationshipWriter.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/access_level|GuardianAccessGrant|ParentProfile|App\\\\Models\\\\User/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Registry relationship code must not own account or access-grant state.');
});

it('keeps Identity grant changes from mutating Registry relationships', function (): void {
    $files = [
        base_path('app/Modules/Identity/Actions/GrantGuardianAccessAction.php'),
        base_path('app/Modules/Identity/Actions/ChangeGuardianAccessLevelAction.php'),
        base_path('app/Modules/Identity/Actions/RevokeGuardianAccessAction.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/student_guardian_relationships|StudentGuardianRelationship::/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Identity access operations must not write Registry relationship persistence.');
});

it('keeps Guardian authentication and Student context decisions on Identity grants', function (): void {
    $files = [
        base_path('app/Modules/Identity/Actions/ParentLoginAction.php'),
        base_path('app/Modules/Identity/Actions/ParentGoogleLoginAction.php'),
        base_path('app/Modules/Identity/Actions/ParentRefreshTokenAction.php'),
        base_path('app/Modules/Identity/Queries/GetParentContextQuery.php'),
        base_path('app/Http/Middleware/ParentStudentAccess.php'),
        base_path('app/Policies/ApiActorPolicy.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (str_contains($contents, 'parent_student')) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Guardian login and Student context must not evaluate the legacy relationship pivot.');
});

it('keeps the Guardian access grant reader off the legacy pivot', function (): void {
    $contents = file_get_contents(base_path('app/Modules/Identity/Support/EloquentGuardianAccessGrantReader.php')) ?: '';

    expect($contents)->not->toContain('parent_student');
});

it('reads the staff edit form primary Guardian through the Registry contract', function (): void {
    $contents = file_get_contents(base_path('app/Modules/StudentRegistry/Http/Web/StudentController.php')) ?: '';

    expect($contents)
        ->toContain('StudentGuardianRelationshipReader')
        ->toContain('guardianRelationshipReader->forStudent')
        ->not->toContain("'parentProfiles.user'")
        ->not->toContain('primaryParentProfile');
});

it('keeps staff Guardian mutations behind Registry and Identity contracts', function (): void {
    $contents = file_get_contents(base_path('app/Services/StudentService.php')) ?: '';

    expect($contents)
        ->toContain('StudentGuardianRelationshipWriter')
        ->toContain('GuardianAccessGrantWriter')
        ->not->toContain('ParentProfile')
        ->not->toContain('parent_student');
});
