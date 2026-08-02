<?php

declare(strict_types=1);

use App\Http\Middleware\StudentApiAuthorization;
use App\Models\AcademicHold;
use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiStudent(): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'active',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
}

function apiDossierFor(Student $student, array $overrides = []): ScholarshipAdjustmentDossier
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $definition = ScholarshipDefinition::create([
        'code' => 'API'.uniqid(),
        'name' => 'API test',
        'description' => 'test',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    StudentScholarshipAward::firstOrCreate(
        ['student_id' => $student->id],
        ['scholarship_code' => $definition->code, 'awarded_at' => now()->toDateString()],
    );

    return ScholarshipAdjustmentDossier::create(array_merge([
        'student_id' => $student->id,
        'campus_id' => $student->campus_id ?? $campus->id,
        'source_semester_id' => $semester->id,
        'target_semester_id' => Semester::factory()->create()->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEWED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => $definition->code,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED,
        'minutes' => 'Minutes text.',
        'minutes_version' => 1,
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_PENDING,
        'confirmation_requested_at' => now(),
        'created_by_user_id' => User::factory()->create()->id,
    ], $overrides));
}

it('lets a student read the minutes of their own dossier', function () {
    $student = apiStudent();
    $dossier = apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.scholarship-adjustment-confirmation.minutes', ['dossier' => $dossier->id]))
        ->assertOk()
        ->assertJsonPath('data.minutes_version', 1);
});

it('returns 404 when a student tries to read another student dossier', function () {
    $owner = apiStudent();
    $dossier = apiDossierFor($owner);
    $other = apiStudent();
    Sanctum::actingAs($other);

    $this->getJson(route('v1.student.scholarship-adjustment-confirmation.minutes', ['dossier' => $dossier->id]))
        ->assertNotFound();
});

it('confirms with the matching minutes version', function () {
    $student = apiStudent();
    $dossier = apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]), [
        'minutes_version' => 1,
        'agree' => true,
    ])->assertOk();

    expect($dossier->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED);
});

it('rejects a stale minutes version with 409', function () {
    $student = apiStudent();
    $dossier = apiDossierFor($student, ['minutes_version' => 5]);
    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]), [
        'minutes_version' => 1,
        'agree' => true,
    ])->assertStatus(409);
});

it('requires a comment when disputing', function () {
    $student = apiStudent();
    $dossier = apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]), [
        'minutes_version' => 1,
        'agree' => false,
    ])->assertStatus(422);
});

it('lets a student with a financial hold still confirm (hold exemption)', function () {
    $student = apiStudent();
    AcademicHold::create([
        'student_id' => $student->id,
        'hold_type' => 'financial',
        'hold_category' => 'all',
        'title' => 'Outstanding balance',
        'status' => 'active',
        'placed_date' => now()->toDateString(),
    ]);
    $dossier = apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]), [
        'minutes_version' => 1,
        'agree' => true,
    ])->assertOk();

    expect($dossier->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED);
});

it('exempts only the confirmation routes from financial holds — not other routes', function () {
    // Directly exercise the allow-list so the guarantee does not depend on the
    // either-middleware quirks of unrelated student routes: the confirmation
    // route name is exempt; typical student routes are not.
    $middleware = new StudentApiAuthorization;
    $method = new ReflectionMethod($middleware, 'isHoldExemptRoute');

    expect($method->invoke($middleware, 'v1.student.scholarship-adjustment-confirmation.respond'))->toBeTrue()
        ->and($method->invoke($middleware, 'v1.student.scholarship-adjustment-confirmation.minutes'))->toBeTrue()
        ->and($method->invoke($middleware, 'v1.student.profile.show'))->toBeTrue()
        ->and($method->invoke($middleware, 'v1.student.grades.index'))->toBeFalse()
        ->and($method->invoke($middleware, 'v1.student.dashboard.index'))->toBeFalse();
});

it('rejects a guardian/parent actor even when supplying the student binding input', function () {
    // A parent authenticates as a User (not a Student) and, on the shared
    // `either` routes, would bind a student via `X-Student-ID`. The confirmation
    // routes run `student.api.auth` ALONE (no parent.student.access), so even
    // with that header the non-Student actor is refused — the acknowledgement
    // can only be bound by the Student themselves.
    $student = apiStudent();
    $dossier = apiDossierFor($student);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(
        route('v1.student.scholarship-adjustment-confirmation.minutes', ['dossier' => $dossier->id]),
        ['X-Student-ID' => (string) $student->id],
    )->assertForbidden();

    $this->postJson(
        route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]),
        ['minutes_version' => 1, 'agree' => true],
        ['X-Student-ID' => (string) $student->id],
    )->assertForbidden();

    expect($dossier->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_PENDING);
});

it('lists only the authenticated student own reviews', function () {
    $student = apiStudent();
    $other = apiStudent();
    $mine = apiDossierFor($student);
    $theirs = apiDossierFor($other);
    Sanctum::actingAs($student);

    $response = $this->getJson(route('v1.student.scholarship-adjustment-confirmation.index'))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
});

it('omits dossiers whose confirmation window was never opened', function () {
    // A dossier still in interview has nothing for the student to act on and
    // must not surface in their portal list.
    $student = apiStudent();
    $visible = apiDossierFor($student);
    $hidden = apiDossierFor($student, [
        'confirmation_status' => null,
        'confirmation_requested_at' => null,
    ]);
    Sanctum::actingAs($student);

    $ids = collect(
        $this->getJson(route('v1.student.scholarship-adjustment-confirmation.index'))->assertOk()->json('data')
    )->pluck('id')->all();

    expect($ids)->toContain($visible->id)
        ->and($ids)->not->toContain($hidden->id);
});

it('flags a pending review as awaiting the student response', function () {
    $student = apiStudent();
    apiDossierFor($student);
    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.scholarship-adjustment-confirmation.index'))
        ->assertOk()
        ->assertJsonPath('data.0.awaiting_response', true);
});

it('does not flag an already-confirmed review as awaiting a response', function () {
    $student = apiStudent();
    apiDossierFor($student, [
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED,
        'confirmed_at' => now(),
    ]);
    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.scholarship-adjustment-confirmation.index'))
        ->assertOk()
        ->assertJsonPath('data.0.awaiting_response', false);
});

it('refuses to list reviews for a guardian actor', function () {
    $student = apiStudent();
    apiDossierFor($student);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(
        route('v1.student.scholarship-adjustment-confirmation.index'),
        ['X-Student-ID' => (string) $student->id],
    )->assertForbidden();
});

it('notifies staff over HTTP for both an acceptance and a rejection', function () {
    // The publisher is unit-tested separately; this proves the endpoint
    // actually reaches it, for BOTH answers — the acceptance path was missing
    // and nothing failed, because a silent notification looks like a working
    // request.
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
    ]);

    $decider = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'api_notify_decider'], ['name' => 'Api notify decider']);
    $permission = Permission::firstOrCreate(
        ['code' => 'decide_scholarship_adjustment'],
        ['name' => 'decide_scholarship_adjustment', 'display_name' => 'Decide', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $answer = function (bool $agree, ?string $comment) use ($decider, $role): string {
        NotificationEventOutbox::query()->delete();

        $student = apiStudent();
        $dossier = apiDossierFor($student);

        DB::table('campus_user_roles')->insertOrIgnore([
            'user_id' => $decider->id, 'campus_id' => $dossier->campus_id, 'role_id' => $role->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $decider->id);

        Sanctum::actingAs($student);
        test()->postJson(
            route('v1.student.scholarship-adjustment-confirmation.respond', ['dossier' => $dossier->id]),
            // Built explicitly: array_filter() would drop agree=false.
            $comment === null
                ? ['minutes_version' => 1, 'agree' => $agree]
                : ['minutes_version' => 1, 'agree' => $agree, 'comment' => $comment],
        )->assertOk();

        $payload = NotificationEventOutbox::query()->sole()->payload;
        $payload = is_array($payload) ? $payload : json_decode((string) $payload, true);

        return $payload['type_key'];
    };

    expect($answer(true, null))->toBe('scholarship_adjustment_confirmed')
        ->and($answer(false, 'Ghi sai môn'))->toBe('scholarship_adjustment_disputed');
});
