<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Engagement\Models\Club;
use App\Modules\Engagement\Models\ClubMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * Pilot for plan 260811-0012 phase 1: proves the strategy-A backfill
 * migration on ClubMember before the module sweeps apply it elsewhere.
 * Simulates a pre-260809-1557 activity_log row (subject_type still the old
 * `App\Models\ClubMember` shim FQCN) and verifies the migration rewrites it
 * to the canonical namespace, that the morph relation still resolves, and
 * that down() reverses it.
 */
function loadClubMemberBackfillMigration(): Migration
{
    return require database_path('migrations/2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php');
}

// StudentFactory has no default for the required `intake` column, and the
// students table's `intake_semester_id` FK defaults to id 1 when created
// against an empty test DB with no active semester — both pre-existing gaps
// unrelated to this migration. Set both explicitly so member creation works.
function createClubMemberForTest(Club $club): ClubMember
{
    $semester = Semester::factory()->create();

    return ClubMember::factory()
        ->for($club)
        ->for(Student::factory()->state([
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]), 'student')
        ->create();
}

it('rewrites legacy App\Models\ClubMember activity_log rows to the canonical namespace', function (): void {
    $club = Club::factory()->create();
    $member = createClubMemberForTest($club);

    $activityId = DB::table('activity_log')->insertGetId([
        'log_name' => 'test',
        'description' => 'legacy pre-migration log entry',
        'subject_type' => 'App\Models\ClubMember',
        'subject_id' => $member->id,
        'event' => 'updated',
        'properties' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = loadClubMemberBackfillMigration();
    $migration->up();

    expect(DB::table('activity_log')->where('id', $activityId)->value('subject_type'))
        ->toBe('App\Modules\Engagement\Models\ClubMember');

    $activity = Activity::find($activityId);
    expect($activity->subject)->toBeInstanceOf(ClubMember::class)
        ->and($activity->subject->id)->toBe($member->id);
});

it('reverses the backfill on down()', function (): void {
    $club = Club::factory()->create();
    $member = createClubMemberForTest($club);

    $activityId = DB::table('activity_log')->insertGetId([
        'log_name' => 'test',
        'description' => 'canonical log entry',
        'subject_type' => 'App\Modules\Engagement\Models\ClubMember',
        'subject_id' => $member->id,
        'event' => 'updated',
        'properties' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = loadClubMemberBackfillMigration();
    $migration->down();

    expect(DB::table('activity_log')->where('id', $activityId)->value('subject_type'))
        ->toBe('App\Models\ClubMember');
});

it('leaves other subject_type values untouched', function (): void {
    $club = Club::factory()->create();

    $activityId = DB::table('activity_log')->insertGetId([
        'log_name' => 'test',
        'description' => 'unrelated log entry',
        'subject_type' => 'App\Models\Club',
        'subject_id' => $club->id,
        'event' => 'updated',
        'properties' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = loadClubMemberBackfillMigration();
    $migration->up();

    expect(DB::table('activity_log')->where('id', $activityId)->value('subject_type'))
        ->toBe('App\Models\Club');
});
