<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Engagement\Actions\EventParticipationOperations;
use App\Modules\Engagement\Models\Event;
use App\Modules\Engagement\Models\EventParticipant;
use App\Modules\Merchandise\Models\GoldTransaction;
use App\Services\GoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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

it('detects a pending gold transaction created after the given timestamp', function () {
    $event = Event::factory()->create(['gold_reward_amount' => 20]);
    $awardedAt = now()->subHour();

    // created_at is not mass-assignable on this model — force it after
    // create() so the fixture actually lands at the intended offset.
    GoldTransaction::create([
        'student_id' => $this->student->id,
        'amount' => 5,
        'balance_before' => 0,
        'balance_after' => 5,
        'type' => GoldTransaction::TYPE_EARN,
        'source_type' => GoldService::SOURCE_EVENT,
        'source_id' => $event->id,
    ])->forceFill(['created_at' => $awardedAt->copy()->addMinutes(10)])->save();

    expect($this->gold->hasPendingEventTransaction((int) $this->student->id, (int) $event->id, $awardedAt))->toBeTrue()
        ->and($this->gold->hasPendingEventTransaction((int) $this->student->id, (int) $event->id, $awardedAt->copy()->addMinutes(20)))->toBeFalse();
});

it('treats a null $since as having no pending transaction', function () {
    $event = Event::factory()->create(['gold_reward_amount' => 20]);

    GoldTransaction::create([
        'student_id' => $this->student->id,
        'amount' => 5,
        'balance_before' => 0,
        'balance_after' => 5,
        'type' => GoldTransaction::TYPE_EARN,
        'source_type' => GoldService::SOURCE_EVENT,
        'source_id' => $event->id,
    ]);

    expect($this->gold->hasPendingEventTransaction((int) $this->student->id, (int) $event->id, null))->toBeFalse();
});

it('still succeeds reclaiming gold when a pending transaction exists (warn-only guard)', function () {
    $this->gold->adjustBalance($this->student, 20, 'seed balance');

    $event = Event::factory()->create([
        'gold_reward_amount' => 20,
        'end_time' => now()->subDay(),
    ]);
    $participant = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'student_id' => $this->student->id,
        'gold_awarded' => true,
        'awarded_at' => now()->subHours(2),
    ]);

    // A transaction for the same event source, created after the award —
    // the guard only logs a warning, it never blocks the reclaim.
    GoldTransaction::create([
        'student_id' => $this->student->id,
        'amount' => 3,
        'balance_before' => 20,
        'balance_after' => 23,
        'type' => GoldTransaction::TYPE_EARN,
        'source_type' => GoldService::SOURCE_EVENT,
        'source_id' => $event->id,
    ])->forceFill(['created_at' => now()->subHour()])->save();

    Log::spy();

    $result = $this->ops->reclaimGoldReward($participant);

    expect($result)->toBeTrue()
        ->and($participant->fresh()->gold_awarded)->toBeFalse();

    // Proves the guard actually fired (not just that reclaim happened to
    // succeed regardless) — a warning, never a block.
    Log::shouldHaveReceived('warning')
        ->with('Pending transactions found during gold reclaim', \Mockery::type('array'))
        ->once();
});
