<?php

declare(strict_types=1);

use App\Models\GoldTransaction;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Engagement\Actions\EventParticipationOperations;
use App\Modules\Engagement\Models\Event;
use App\Modules\Engagement\Models\EventParticipant;
use App\Services\GoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Keep the test on the money math; the reclaim notification itself is
    // covered by the notification suite.
    Notification::fake();

    $semester = Semester::factory()->create();
    $this->student = Student::factory()->create([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);
    $this->gold = app(GoldService::class);
    $this->ops = app(EventParticipationOperations::class);
});

it('clamps an over-balance reclaim to zero and writes off the shortfall', function () {
    // Student was awarded 50 but only has 10 left (spent the rest elsewhere).
    $this->gold->adjustBalance($this->student, 10, 'seed balance');

    $event = Event::factory()->create([
        'gold_reward_amount' => 50,
        'end_time' => now()->subDay(),
    ]);
    $participant = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'student_id' => $this->student->id,
        'gold_awarded' => true,
        'awarded_at' => now()->subHours(2),
    ]);

    $this->ops->reclaimGoldReward($participant);

    expect($this->gold->getBalance($this->student))->toBe(0);

    $reclaim = GoldTransaction::where('student_id', $this->student->id)
        ->where('type', GoldTransaction::TYPE_SPEND)->sole();
    expect($reclaim->amount)->toBe(-10);

    $writeOff = GoldTransaction::where('student_id', $this->student->id)
        ->where('type', GoldTransaction::TYPE_WRITE_OFF)->sole();
    expect($writeOff->amount)->toBe(0)
        ->and($writeOff->notes)->toContain('40');

    // Invariant still holds: the write-off does not shift the ledger sum.
    expect((int) GoldTransaction::where('student_id', $this->student->id)->sum('amount'))->toBe(0);
});
