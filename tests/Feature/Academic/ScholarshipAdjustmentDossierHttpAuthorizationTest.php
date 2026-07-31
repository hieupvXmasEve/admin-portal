<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const DOSSIER_HTTP_TEST_CSRF = 'dossier-http-test-csrf';

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * A user granted `manage_scholarship_adjustment_candidate` and
 * `view_scholarship_adjustment` AT the given campus only.
 */
function dossierHttpGrantedUser(Campus $campus): User
{
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'dossier_http_test_role'], ['name' => 'Dossier HTTP Test Role']);

    foreach (['manage_scholarship_adjustment_candidate', 'view_scholarship_adjustment'] as $code) {
        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'display_name' => $code, 'module' => 'scholarship_adjustments', 'description' => 'test'],
        );

        DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);

    return $user;
}

it('rejects identify when campus_id is a campus the actor is not granted at', function () {
    $grantedCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $source = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $target = Semester::factory()->create(['start_date' => now()]);

    $user = dossierHttpGrantedUser($grantedCampus);

    session(['_token' => DOSSIER_HTTP_TEST_CSRF, 'current_campus_id' => $grantedCampus->id]);
    app()->instance('campus', $grantedCampus);

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', DOSSIER_HTTP_TEST_CSRF)
        ->post(route('scholarship-adjustments.identify'), [
            '_token' => DOSSIER_HTTP_TEST_CSRF,
            'campus_id' => $otherCampus->id,
            'source_semester_id' => $source->id,
            'target_semester_id' => $target->id,
        ]);

    $response->assertForbidden();
});

it('rejects manual add for a student at a campus the actor is not granted at', function () {
    $grantedCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $source = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $target = Semester::factory()->create(['start_date' => now()]);

    $student = Student::factory()->create([
        'campus_id' => $otherCampus->id,
        'intake' => 1,
        'intake_semester_id' => $source->id,
    ]);

    $user = dossierHttpGrantedUser($grantedCampus);

    session(['_token' => DOSSIER_HTTP_TEST_CSRF, 'current_campus_id' => $grantedCampus->id]);
    app()->instance('campus', $grantedCampus);

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', DOSSIER_HTTP_TEST_CSRF)
        ->post(route('scholarship-adjustments.add-manually'), [
            '_token' => DOSSIER_HTTP_TEST_CSRF,
            'student_code' => $student->student_id,
            'source_semester_id' => $source->id,
            'target_semester_id' => $target->id,
            'exception_reason' => 'Test exception',
        ]);

    $response->assertForbidden();
});

it('excludes dossiers from other campuses in the index listing', function () {
    $grantedCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $source = Semester::factory()->create();
    $target = Semester::factory()->create();

    $otherStudent = Student::factory()->create([
        'campus_id' => $otherCampus->id,
        'intake' => 1,
        'intake_semester_id' => $source->id,
    ]);
    $definition = ScholarshipDefinition::create([
        'code' => 'HTTP'.uniqid(),
        'name' => 'HTTP test scholarship',
        'description' => 'test',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    StudentScholarshipAward::create([
        'student_id' => $otherStudent->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $creator = User::factory()->create();
    ScholarshipAdjustmentDossier::create([
        'student_id' => $otherStudent->id,
        'campus_id' => $otherCampus->id,
        'source_semester_id' => $source->id,
        'target_semester_id' => $target->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_IDENTIFIED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => $definition->code,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'created_by_user_id' => $creator->id,
    ]);

    $user = dossierHttpGrantedUser($grantedCampus);

    session(['_token' => DOSSIER_HTTP_TEST_CSRF, 'current_campus_id' => $grantedCampus->id]);
    app()->instance('campus', $grantedCampus);

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', DOSSIER_HTTP_TEST_CSRF)
        ->get(route('scholarship-adjustments.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('dossiers.data', []));
});
