<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Academic\Progression\Queries\GetDeferReturnWatchlistQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->intakeSemester = Semester::factory()->create();
});

function watchlistStudent(Campus $campus, Program $program, string $code, string $status): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $code,
            'status' => $status,
            'intake' => 1,
            'intake_semester_id' => test()->intakeSemester->id,
        ]);
}

function watchlistDefer(Student $student, User $user, Semester $fromSemester, Semester $returnSemester): StudentActionLog
{
    return StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'from_semester_id' => $fromSemester->id,
        'return_semester_id' => $returnSemester->id,
        'reason' => 'Watchlist fixture',
        'changed_by_user_id' => $user->id,
    ]);
}

function watchlistWaiting(Student $student, User $user, Semester $fromSemester): StudentActionLog
{
    return StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::WAITING_COURSE_OPENING->value,
        'from_semester_id' => $fromSemester->id,
        'egc_defer_from_block_number' => 1,
        'reason' => 'Watchlist fixture',
        'changed_by_user_id' => $user->id,
    ]);
}

it('buckets a defer as overdue once the return semester has started and is still in progress', function () {
    $student = watchlistStudent($this->campus, $this->program, 'SE800001', 'deferred');
    $from = Semester::factory()->create(['start_date' => now()->subMonths(6), 'end_date' => now()->subMonths(3)]);
    $return = Semester::factory()->create(['start_date' => now()->subWeek(), 'end_date' => now()->addMonths(2)]);
    watchlistDefer($student, $this->user, $from, $return);

    $result = (new GetDeferReturnWatchlistQuery)->handle([], $this->campus->id);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]['bucket'])->toBe('overdue')
        ->and($result->items()[0]['severity'])->toBe('in_semester');
});

it('buckets a defer as overdue with semester_ended severity once the return semester has finished', function () {
    $student = watchlistStudent($this->campus, $this->program, 'SE800002', 'deferred');
    $from = Semester::factory()->create(['start_date' => now()->subMonths(9), 'end_date' => now()->subMonths(6)]);
    $return = Semester::factory()->create(['start_date' => now()->subMonths(5), 'end_date' => now()->subMonths(2)]);
    watchlistDefer($student, $this->user, $from, $return);

    $result = (new GetDeferReturnWatchlistQuery)->handle([], $this->campus->id);

    expect($result->items()[0]['bucket'])->toBe('overdue')
        ->and($result->items()[0]['severity'])->toBe('semester_ended');
});

it('buckets a defer with a future return semester as upcoming', function () {
    $student = watchlistStudent($this->campus, $this->program, 'SE800003', 'deferred');
    $from = Semester::factory()->create(['start_date' => now()->subMonths(2), 'end_date' => now()->addMonth()]);
    $return = Semester::factory()->create(['start_date' => now()->addMonths(3), 'end_date' => now()->addMonths(6)]);
    watchlistDefer($student, $this->user, $from, $return);

    $result = (new GetDeferReturnWatchlistQuery)->handle([], $this->campus->id);

    expect($result->items()[0]['bucket'])->toBe('upcoming')
        ->and($result->items()[0]['severity'])->toBeNull();
});

it('drops a defer row once the student status has moved off deferred', function () {
    $student = watchlistStudent($this->campus, $this->program, 'SE800004', 'intake_course');
    $from = Semester::factory()->create(['start_date' => now()->subMonths(6), 'end_date' => now()->subMonths(3)]);
    $return = Semester::factory()->create(['start_date' => now()->subWeek(), 'end_date' => now()->addMonths(2)]);
    watchlistDefer($student, $this->user, $from, $return);

    $result = (new GetDeferReturnWatchlistQuery)->handle([], $this->campus->id);

    expect($result->total())->toBe(0);
});

it('buckets waiting-for-course-opening students by age on hold, measured from the from semester', function () {
    $student = watchlistStudent($this->campus, $this->program, 'SE800005', 'pending_course_opening');
    $from = Semester::factory()->create(['start_date' => now()->subMonths(2), 'end_date' => now()->addMonth()]);
    watchlistWaiting($student, $this->user, $from);

    $result = (new GetDeferReturnWatchlistQuery)->handle([], $this->campus->id);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]['bucket'])->toBe('waiting')
        ->and($result->items()[0]['days_elapsed'])->toBeGreaterThan(0);
});

it('keeps only the latest defer log when a student has been deferred more than once', function () {
    $student = watchlistStudent($this->campus, $this->program, 'SE800006', 'deferred');
    $from = Semester::factory()->create(['start_date' => now()->subYear(), 'end_date' => now()->subMonths(9)]);
    $oldReturn = Semester::factory()->create(['start_date' => now()->subMonths(8), 'end_date' => now()->subMonths(5)]);
    $latestReturn = Semester::factory()->create(['start_date' => now()->addMonth(), 'end_date' => now()->addMonths(4)]);
    watchlistDefer($student, $this->user, $from, $oldReturn);
    $latest = watchlistDefer($student, $this->user, $from, $latestReturn);

    $result = (new GetDeferReturnWatchlistQuery)->handle([], $this->campus->id);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]['action_id'])->toBe($latest->id)
        ->and($result->items()[0]['bucket'])->toBe('upcoming');
});

it('excludes students from another campus', function () {
    $student = watchlistStudent($this->otherCampus, $this->program, 'SE800007', 'deferred');
    $from = Semester::factory()->create(['start_date' => now()->subMonths(6), 'end_date' => now()->subMonths(3)]);
    $return = Semester::factory()->create(['start_date' => now()->subWeek(), 'end_date' => now()->addMonths(2)]);
    watchlistDefer($student, $this->user, $from, $return);

    $result = (new GetDeferReturnWatchlistQuery)->handle([], $this->campus->id);

    expect($result->total())->toBe(0);
});

it('filters by search on student name and student code', function () {
    $matching = watchlistStudent($this->campus, $this->program, 'SE800008', 'deferred');
    $other = watchlistStudent($this->campus, $this->program, 'SE800009', 'deferred');
    $from = Semester::factory()->create(['start_date' => now()->subMonths(6), 'end_date' => now()->subMonths(3)]);
    $return = Semester::factory()->create(['start_date' => now()->subWeek(), 'end_date' => now()->addMonths(2)]);
    watchlistDefer($matching, $this->user, $from, $return);
    watchlistDefer($other, $this->user, $from, $return);

    $result = (new GetDeferReturnWatchlistQuery)->handle(['search' => 'SE800008'], $this->campus->id);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]['student_code'])->toBe('SE800008');
});

it('returns bucket counts unaffected by the bucket filter and by pagination', function () {
    $from = Semester::factory()->create(['start_date' => now()->subMonths(6), 'end_date' => now()->subMonths(3)]);
    $overdueReturn = Semester::factory()->create(['start_date' => now()->subWeek(), 'end_date' => now()->addMonths(2)]);
    $upcomingReturn = Semester::factory()->create(['start_date' => now()->addMonths(3), 'end_date' => now()->addMonths(6)]);

    $overdueStudent = watchlistStudent($this->campus, $this->program, 'SE800010', 'deferred');
    $upcomingStudent = watchlistStudent($this->campus, $this->program, 'SE800011', 'deferred');
    $waitingStudent = watchlistStudent($this->campus, $this->program, 'SE800012', 'pending_course_opening');
    watchlistDefer($overdueStudent, $this->user, $from, $overdueReturn);
    watchlistDefer($upcomingStudent, $this->user, $from, $upcomingReturn);
    watchlistWaiting($waitingStudent, $this->user, $from);

    $query = new GetDeferReturnWatchlistQuery;

    $counts = $query->counts($this->campus->id);
    $filtered = $query->handle(['bucket' => 'overdue', 'per_page' => 1], $this->campus->id);

    expect($counts)->toBe(['overdue' => 1, 'upcoming' => 1, 'waiting' => 1])
        ->and($filtered->total())->toBe(1);
});
