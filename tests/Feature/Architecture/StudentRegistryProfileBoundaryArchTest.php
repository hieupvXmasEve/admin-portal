<?php

declare(strict_types=1);

it('keeps the student v1 profile mutation on the Registry contract', function (): void {
    $updateAction = file_get_contents(base_path('app/Modules/StudentRegistry/Actions/UpdateStudentProfileAction.php')) ?: '';

    expect($updateAction)
        ->toContain('StudentProfilePersistenceWriter')
        ->toContain('app(StudentProfilePersistenceWriter::class)->update')
        ->not->toContain('DB::transaction(function () use ($student, $data)');

});

it('reads Registry-owned profile data through the Registry contract', function (): void {
    $profileProjection = file_get_contents(base_path('app/Modules/StudentRegistry/Support/EloquentStudentPortalProfileReader.php')) ?: '';
    $profileController = file_get_contents(base_path('app/Modules/StudentRegistry/Http/Api/Student/ProfileController.php')) ?: '';

    expect($profileProjection)
        ->toContain('StudentProfileReader')
        ->toContain('$this->profiles->findProfile')
        ->and($profileController)
        ->toContain('StudentPortalProfileReader');
});

it('keeps staff profile edits on Registry and Identity contracts', function (): void {
    $studentService = file_get_contents(base_path('app/Services/StudentService.php')) ?: '';

    expect($studentService)
        ->toContain('StudentProfileWriter')
        ->toContain('StudentIdentityWriter')
        ->toContain('studentProfileWriter->update')
        ->toContain('studentIdentityWriter->updateEmail')
        ->toContain('$student->update($academicBackgroundAttributes)')
        ->not->toContain('$student->update($filteredData)')
        ->not->toContain('$student->user->update');
});
