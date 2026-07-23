<?php

declare(strict_types=1);

it('keeps the student v1 profile mutation on the Registry contract', function (): void {
    $profileService = file_get_contents(base_path('app/Services/V1/Student/ProfileService.php')) ?: '';

    expect($profileService)
        ->toContain('StudentProfileWriter')
        ->toContain('studentProfileWriter->update')
        ->not->toContain('DB::transaction(function () use ($student, $data)');

});

it('reads Registry-owned profile data through the Registry contract', function (): void {
    $profileService = file_get_contents(base_path('app/Services/V1/Student/ProfileService.php')) ?: '';

    expect($profileService)
        ->toContain('StudentProfileReader')
        ->toContain('studentProfileReader->findProfile');
});

it('keeps staff profile edits on Registry and Identity contracts', function (): void {
    $studentService = file_get_contents(base_path('app/Services/StudentService.php')) ?: '';

    expect($studentService)
        ->toContain('StudentProfileWriter')
        ->toContain('StudentAccessWriter')
        ->toContain('studentProfileWriter->update')
        ->toContain('studentAccessWriter->updateEmail')
        ->not->toContain('$student->update($filteredData)')
        ->not->toContain('$student->user->update');
});
