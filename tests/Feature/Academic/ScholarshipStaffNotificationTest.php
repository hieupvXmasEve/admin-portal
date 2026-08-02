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
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\ScholarshipStaffNotificationPublisher;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
    ]);
});

/** A user holding one permission AT one campus — the rule the publisher uses. */
function staffNotifyUser(Campus $campus, string $permissionCode): User
{
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'staff_notify_'.$permissionCode], ['name' => 'Staff notify '.$permissionCode]);
    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode, 'display_name' => $permissionCode, 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );

    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);

    return $user;
}

function staffNotifyDossier(Campus $campus): ScholarshipAdjustmentDossier
{
    $source = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $target = Semester::factory()->create(['start_date' => now()]);
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 1,
        'intake_semester_id' => $source->id,
        'full_name' => 'Nguyen Van Test',
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'STAFFN'.uniqid(),
        'name' => 'Staff notify test',
        'description' => 'test',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    return ScholarshipAdjustmentDossier::create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'source_semester_id' => $source->id,
        'target_semester_id' => $target->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEWED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => $definition->code,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED,
        'minutes' => 'Discussed.',
        'minutes_version' => 1,
        'created_by_user_id' => User::factory()->create()->id,
    ]);
}

/** @return array<string, mixed> */
function soleOutboxPayload(): array
{
    $event = NotificationEventOutbox::query()->sole();

    return is_array($event->payload) ? $event->payload : json_decode((string) $event->payload, true);
}

it('tells the deciders that a student disputed the minutes', function () {
    $campus = Campus::factory()->create();
    $decider = staffNotifyUser($campus, 'decide_scholarship_adjustment');
    $dossier = staffNotifyDossier($campus);

    app(ScholarshipStaffNotificationPublisher::class)->disputed($dossier, 'Ghi sai môn của tôi');

    $payload = soleOutboxPayload();

    expect($payload['type_key'])->toBe('scholarship_adjustment_disputed')
        ->and($payload['recipient_targets'])->toBe([['type' => 'user', 'id' => $decider->id]])
        // The student's own words travel with it, so staff can judge without
        // opening the dossier first.
        ->and($payload['data']['body'])->toContain('Ghi sai môn của tôi')
        ->and($payload['channels'])->toBe(['realtime']);
});

it('tells the deciders when a confirmation goes overdue', function () {
    $campus = Campus::factory()->create();
    $decider = staffNotifyUser($campus, 'decide_scholarship_adjustment');
    $dossier = staffNotifyDossier($campus);

    app(ScholarshipStaffNotificationPublisher::class)->confirmationOverdue($dossier);

    $payload = soleOutboxPayload();

    expect($payload['type_key'])->toBe('scholarship_adjustment_confirmation_overdue')
        ->and($payload['recipient_targets'])->toBe([['type' => 'user', 'id' => $decider->id]]);
});

it('tells the approvers that a decision is waiting, not the deciders', function () {
    $campus = Campus::factory()->create();
    $approver = staffNotifyUser($campus, 'approve_scholarship_adjustment');
    $decider = staffNotifyUser($campus, 'decide_scholarship_adjustment');
    $dossier = staffNotifyDossier($campus);

    app(ScholarshipStaffNotificationPublisher::class)->readyForDecision($dossier);

    $payload = soleOutboxPayload();
    $ids = array_column($payload['recipient_targets'], 'id');

    expect($payload['type_key'])->toBe('scholarship_adjustment_ready_for_decision')
        ->and($ids)->toContain($approver->id)
        ->and($ids)->not->toContain($decider->id);
});

it('never notifies a permission holder at another campus', function () {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $mine = staffNotifyUser($campus, 'decide_scholarship_adjustment');
    $theirs = staffNotifyUser($otherCampus, 'decide_scholarship_adjustment');
    $dossier = staffNotifyDossier($campus);

    app(ScholarshipStaffNotificationPublisher::class)->disputed($dossier, null);

    $ids = array_column(soleOutboxPayload()['recipient_targets'], 'id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
});

it('publishes nothing when nobody holds the permission at that campus', function () {
    $campus = Campus::factory()->create();
    $dossier = staffNotifyDossier($campus);

    app(ScholarshipStaffNotificationPublisher::class)->disputed($dossier, null);

    expect(NotificationEventOutbox::query()->count())->toBe(0);
});

it('reaches whoever holds the permission, whatever their role is', function () {
    // The point of resolving recipients from permissions: granting an ordinary
    // staff role the permission is enough to have them notified — no code change
    // and no per-feature recipient list.
    $campus = Campus::factory()->create();
    $role = Role::firstOrCreate(['code' => 'academic_officer_test'], ['name' => 'Academic Officer Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'decide_scholarship_adjustment'],
        ['name' => 'decide_scholarship_adjustment', 'display_name' => 'Decide', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $officer = User::factory()->create();
    DB::table('campus_user_roles')->insert([
        'user_id' => $officer->id, 'campus_id' => $campus->id, 'role_id' => $role->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $dossier = staffNotifyDossier($campus);

    app(ScholarshipStaffNotificationPublisher::class)->disputed($dossier, null);

    $ids = array_column(soleOutboxPayload()['recipient_targets'], 'id');

    expect($ids)->toContain($officer->id);
});

it('gives every staff notification a clickable link to the dossier', function () {
    // NotificationPayloadBuilder OVERWRITES action_url with whatever
    // NotificationUrlRegistry resolves from action_type — passing a literal url
    // in the data array does not survive. An unregistered action_type therefore
    // yields a notification nobody can click, silently.
    $campus = Campus::factory()->create();
    staffNotifyUser($campus, 'decide_scholarship_adjustment');
    $dossier = staffNotifyDossier($campus);

    app(ScholarshipStaffNotificationPublisher::class)->disputed($dossier, null);

    $data = soleOutboxPayload()['data'];

    expect($data['action_url'])->toBe("/scholarship-adjustments/{$dossier->id}")
        ->and($data['action_type'])->toBe('academic.scholarship_adjustment');
});

it('tells the deciders that a student accepted the minutes', function () {
    // Acceptance is what unblocks a fee-increasing decision, so it is as much a
    // work signal as a rejection — a confirmed dossier must not sit waiting on
    // staff who still think the student has not answered.
    $campus = Campus::factory()->create();
    $decider = staffNotifyUser($campus, 'decide_scholarship_adjustment');
    $dossier = staffNotifyDossier($campus);

    app(ScholarshipStaffNotificationPublisher::class)->confirmed($dossier, 'Tôi đồng ý');

    $payload = soleOutboxPayload();

    expect($payload['type_key'])->toBe('scholarship_adjustment_confirmed')
        ->and($payload['recipient_targets'])->toBe([['type' => 'user', 'id' => $decider->id]])
        ->and($payload['data']['body'])->toContain('Tôi đồng ý')
        ->and($payload['data']['action_url'])->toBe("/scholarship-adjustments/{$dossier->id}")
        ->and($payload['channels'])->toBe(['realtime']);
});
