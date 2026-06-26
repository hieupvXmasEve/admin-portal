<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('no longer registers the Excel import routes', function (string $routeName): void {
    expect(Route::has($routeName))->toBeFalse();
})->with([
    'student-applications.import',
    'student-applications.import.preview',
    'student-applications.import.process',
    'student-applications.import.template',
]);

it('still registers the surviving student-application routes', function (string $routeName): void {
    expect(Route::has($routeName))->toBeTrue();
})->with([
    'student-applications.index',
    'student-applications.create',
    'student-applications.store',
    'student-applications.show',
    'student-applications.export',
]);

it('drops the import permission from the permission catalog', function (): void {
    $permissions = config('permission.access.student_applications');

    expect($permissions)->not->toHaveKey('import_student_application');
});
