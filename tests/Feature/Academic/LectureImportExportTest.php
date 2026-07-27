<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function grantLectureImportExportPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'lecture-io_'.Str::lower(Str::random(10))]);

    RolePermission::create([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);
    CampusUserRole::create([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
    ]);
}

beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
});

it('redirects guests away from the lecture import form', function (): void {
    get(route('lectures.import.form'))->assertRedirect(route('login'));
});

it('forbids users without import_lecturer from the import form', function (): void {
    actingAs(User::factory()->create());

    get(route('lectures.import.form'))->assertForbidden();
});

it('renders the lecture import form', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLectureImportExportPermission($user, $this->campus, 'import_lecturer');

    get(route('lectures.import.form'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Lectures/Import'));
});

it('rejects an upload missing the file field with the flat compat envelope', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLectureImportExportPermission($user, $this->campus, 'import_lecturer');

    post(route('lectures.import.upload'), [])->assertSessionHasErrors('file');
});

it('rejects an unparseable uploaded file with the flat compat envelope', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLectureImportExportPermission($user, $this->campus, 'import_lecturer');

    $file = UploadedFile::fake()->create('lecturers.xlsx', 5);

    post(route('lectures.import.upload'), ['file' => $file])
        ->assertStatus(400)
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'error']);
});

it('forbids users without export_lecturer from exporting lecturers', function (): void {
    actingAs(User::factory()->create());

    get(route('lectures.export.excel'))->assertForbidden();
});

it('downloads the lecturers excel export for an authorized user', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLectureImportExportPermission($user, $this->campus, 'export_lecturer');

    get(route('lectures.export.excel'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
