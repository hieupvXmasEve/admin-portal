<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
});

it('marks a student with an in-flight scholarship dossier as ineligible in the preview', function () {
    grantFinance($this->user, ['create_finance_charges', 'view_finance_all_campus'], $this->campus);

    $student = makeBatchHpStudent($this->campus, $this->semester, 'BS'.random_int(100000000, 999999999), 'intake_major');
    $maker = User::factory()->create();
    ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $student->id,
        'campus_id' => $this->campus->id,
        'source_semester_id' => $this->semester->id,
        'target_semester_id' => $this->semester->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => 'SCH-01',
        'original_type' => 'percentage',
        'original_amount' => 10,
        'created_by_user_id' => $maker->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => ['search' => $student->student_id]],
        ])
        ->assertOk();

    $line = collect($response->json('data.lines'))
        ->firstWhere('display.student_id', $student->student_id);

    expect($line['display']['diff'])->toBe('skip')
        ->and($line['display']['reason'])->toBe('scholarship_review_pending');
});
