<?php

declare(strict_types=1);

use App\Models\AssessmentComponentDetailScore;
use App\Services\AssessmentManagementService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new AssessmentManagementService;
});

test('calculateLatePenalty returns zero for on-time submission', function () {
    $score = new AssessmentComponentDetailScore;
    $submissionTime = Carbon::parse('2024-01-15 10:00:00');
    $deadline = Carbon::parse('2024-01-15 12:00:00');

    $penalty = $score->calculateLatePenalty($submissionTime, $deadline);

    expect($penalty)->toBe(0.0);
});

test('calculateLatePenalty calculates per-day penalty correctly', function () {
    $score = new AssessmentComponentDetailScore;
    $submissionTime = Carbon::parse('2024-01-16 10:00:00'); // 1 day late
    $deadline = Carbon::parse('2024-01-15 12:00:00');

    $penaltyRules = [
        'type' => 'per_day',
        'percentage' => 10.0,
        'max_penalty' => 100.0,
        'grace_period_minutes' => 0,
    ];

    $penalty = $score->calculateLatePenalty($submissionTime, $deadline, $penaltyRules);

    expect($penalty)->toBe(10.0);
});

test('calculateLatePenalty calculates per-hour penalty correctly', function () {
    $score = new AssessmentComponentDetailScore;
    $submissionTime = Carbon::parse('2024-01-15 14:00:00'); // 2 hours late
    $deadline = Carbon::parse('2024-01-15 12:00:00');

    $penaltyRules = [
        'type' => 'per_hour',
        'percentage' => 5.0,
        'max_penalty' => 100.0,
        'grace_period_minutes' => 0,
    ];

    $penalty = $score->calculateLatePenalty($submissionTime, $deadline, $penaltyRules);

    expect($penalty)->toBe(10.0); // 2 hours * 5% = 10%
});

test('calculateLatePenalty respects grace period', function () {
    $score = new AssessmentComponentDetailScore;
    $submissionTime = Carbon::parse('2024-01-15 12:05:00'); // 5 minutes late
    $deadline = Carbon::parse('2024-01-15 12:00:00');

    $penaltyRules = [
        'type' => 'per_hour',
        'percentage' => 5.0,
        'max_penalty' => 100.0,
        'grace_period_minutes' => 10, // 10 minute grace period
    ];

    $penalty = $score->calculateLatePenalty($submissionTime, $deadline, $penaltyRules);

    expect($penalty)->toBe(0.0);
});

test('calculateLatePenalty respects maximum penalty', function () {
    $score = new AssessmentComponentDetailScore;
    $submissionTime = Carbon::parse('2024-01-20 12:00:00'); // 5 days late
    $deadline = Carbon::parse('2024-01-15 12:00:00');

    $penaltyRules = [
        'type' => 'per_day',
        'percentage' => 30.0,
        'max_penalty' => 50.0, // Max 50%
        'grace_period_minutes' => 0,
    ];

    $penalty = $score->calculateLatePenalty($submissionTime, $deadline, $penaltyRules);

    expect($penalty)->toBe(50.0); // Should be capped at 50%
});

test('applyLatePenalty updates score correctly', function () {
    $score = AssessmentComponentDetailScore::factory()->create([
        'is_late' => false,
        'late_penalty_applied' => 0.0,
        'score_history' => [],
    ]);

    $score->applyLatePenalty(15.0, 'Test penalty');

    expect($score->is_late)->toBe(true);
    expect($score->late_penalty_applied)->toBe(15.0);
    expect($score->score_history)->toHaveCount(1);
    expect($score->score_history[0]['action'])->toBe('late_penalty_applied');
    expect($score->score_history[0]['penalty_percentage'])->toBe(15.0);
});

test('processLateExcuse approves excuse and removes penalty', function () {
    $score = AssessmentComponentDetailScore::factory()->create([
        'is_late' => true,
        'late_penalty_applied' => 10.0,
        'late_excuse' => 'Medical emergency',
        'late_excuse_approved' => false,
        'score_history' => [],
    ]);

    $score->processLateExcuse(true, 'Approved due to medical documentation');

    expect($score->late_excuse_approved)->toBe(true);
    expect($score->is_late)->toBe(false);
    expect($score->late_penalty_applied)->toBe(0.0);
    expect($score->score_history)->toHaveCount(1);
    expect($score->score_history[0]['action'])->toBe('late_excuse_approved');
});

test('processLateExcuse denies excuse and keeps penalty', function () {
    $score = AssessmentComponentDetailScore::factory()->create([
        'is_late' => true,
        'late_penalty_applied' => 10.0,
        'late_excuse' => 'Overslept',
        'late_excuse_approved' => false,
        'score_history' => [],
    ]);

    $score->processLateExcuse(false, 'Insufficient justification');

    expect($score->late_excuse_approved)->toBe(false);
    expect($score->is_late)->toBe(true);
    expect($score->late_penalty_applied)->toBe(10.0);
    expect($score->score_history)->toHaveCount(1);
    expect($score->score_history[0]['action'])->toBe('late_excuse_denied');
});

test('canRequestLateExcuse returns correct status', function () {
    // Can request excuse
    $score1 = AssessmentComponentDetailScore::factory()->create([
        'is_late' => true,
        'late_excuse' => null,
        'late_excuse_approved' => false,
    ]);

    expect($score1->canRequestLateExcuse())->toBe(true);

    // Cannot request - not late
    $score2 = AssessmentComponentDetailScore::factory()->create([
        'is_late' => false,
        'late_excuse' => null,
        'late_excuse_approved' => false,
    ]);

    expect($score2->canRequestLateExcuse())->toBe(false);

    // Cannot request - already has excuse
    $score3 = AssessmentComponentDetailScore::factory()->create([
        'is_late' => true,
        'late_excuse' => 'Already requested',
        'late_excuse_approved' => false,
    ]);

    expect($score3->canRequestLateExcuse())->toBe(false);
});

test('getLateSubmissionStatus returns complete status', function () {
    $score = AssessmentComponentDetailScore::factory()->create([
        'is_late' => true,
        'minutes_late' => 120,
        'late_penalty_applied' => 15.0,
        'late_excuse' => 'Traffic jam',
        'late_excuse_approved' => false,
    ]);

    $status = $score->getLateSubmissionStatus();

    expect($status)->toMatchArray([
        'is_late' => true,
        'minutes_late' => 120,
        'penalty_applied' => 15.0,
        'has_excuse' => true,
        'excuse_approved' => false,
        'excuse_text' => 'Traffic jam',
        'can_request_excuse' => false,
        'excuse_pending' => true,
    ]);
});
