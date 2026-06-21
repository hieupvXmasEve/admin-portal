<?php

declare(strict_types=1);

use App\Modules\Academic\Support\Grading\GradingSchemeValidator;
use App\Modules\Academic\Support\Grading\MetropoliaSchemeCatalog;
use App\Modules\Academic\Support\Grading\MetropoliaV1Calculator;

it('loads every metropolia scheme key from the canonical pack', function () {
    $catalog = app(MetropoliaSchemeCatalog::class);

    expect($catalog->keys())->toBe([
        'software_1.programming',
        'software_1.database',
        'software_1.maths_physics',
        'software_1.project',
        'software_2.programming',
        'software_2.web_development',
        'software_2.maths_physics',
        'software_2.project',
        'hardware_1.digital_systems',
        'hardware_1.networking',
        'hardware_1.linux',
        'hardware_1.health_technology',
        'hardware_1.maths_physics',
        'hardware_2.electronics',
        'hardware_2.maths_physics',
        'hardware_2.cloud_computing',
        'hardware_2.project',
    ]);
});

it('returns metropolia_v1 schemes with engine-executable shape and audit labels', function () {
    $scheme = app(MetropoliaSchemeCatalog::class)->get('software_1.programming');

    expect($scheme['engine'])->toBe('metropolia_v1')
        ->and($scheme['version'])->toBe(1)
        ->and($scheme['source_reference'])->toBe('Software 1 / Programming')
        ->and($scheme['scale'])->toBe('0-5')
        ->and($scheme['components'])->toHaveCount(2);
});

it('throws for an unknown scheme key', function () {
    expect(fn () => app(MetropoliaSchemeCatalog::class)->get('does.not_exist'))
        ->toThrow(RuntimeException::class);
});

it('exposes only engine values the resolver and calculator can execute', function () {
    $catalog = app(MetropoliaSchemeCatalog::class);

    foreach ($catalog->keys() as $key) {
        $scheme = $catalog->get($key);
        expect($scheme['engine'])->toBe('metropolia_v1')
            ->and($scheme['scale'])->toBeIn(['0-5', 'pass_fail']);

        // Every scheme must run through the real calculator without error.
        $result = app(MetropoliaV1Calculator::class)->calculate([], $scheme);
        expect($result->gradeBreakdown['engine'])->toBe('metropolia_v1');
    }
});

it('validates every canonical metropolia scheme', function () {
    $catalog = app(MetropoliaSchemeCatalog::class);
    $validator = app(GradingSchemeValidator::class);

    foreach ($catalog->keys() as $key) {
        expect($validator->validate($catalog->get($key)))->toBe([]);
    }
});

it('computes faithful grades for representative catalog schemes', function () {
    $catalog = app(MetropoliaSchemeCatalog::class);
    $calculator = app(MetropoliaV1Calculator::class);

    // Programming: exam at 88% with the assignment gate met => top grade 5.
    $programming = $calculator->calculate(
        ['ASSIGNMENT' => 90, 'EXAM' => 88],
        $catalog->get('software_1.programming'),
    );
    expect($programming->finalGrade)->toBe('5')
        ->and($programming->passed)->toBeTrue();

    // Programming: failing the assignment gate fails the course regardless of exam.
    $gated = $calculator->calculate(
        ['ASSIGNMENT' => 30, 'EXAM' => 88],
        $catalog->get('software_1.programming'),
    );
    expect($gated->passed)->toBeFalse();

    // Database: pass/fail at the 80% threshold.
    $dbPass = $calculator->calculate(['ASSIGNMENT' => 80], $catalog->get('software_1.database'));
    $dbFail = $calculator->calculate(['ASSIGNMENT' => 79], $catalog->get('software_1.database'));
    expect($dbPass->finalGrade)->toBe('P')
        ->and($dbFail->finalGrade)->toBe('F');

    // Maths & Physics: assignment grade + exam grade (2 + 3 = 5).
    $maths = $calculator->calculate(
        ['ASSIGNMENT' => 70, 'EXAM' => 85],
        $catalog->get('software_1.maths_physics'),
    );
    expect($maths->finalGrade)->toBe('5');

    // Cloud Computing: balanced 30% profile reproduces the source formula (grade 4).
    $cloud = $calculator->calculate(
        ['LAB' => 30, 'QUIZ' => 30, 'EXAM' => 30],
        $catalog->get('hardware_2.cloud_computing'),
    );
    expect($cloud->gradeBreakdown['fg_sum_raw'])->toEqualWithDelta(3.5, 0.01);
});

it('reports concrete validation errors for malformed schemes', function () {
    $errors = app(GradingSchemeValidator::class)->validate([
        'engine' => 'metropolia_v1',
        'scale' => '0-5',
        'components' => [],
    ]);

    expect($errors)->toContain('version is required')
        ->and($errors)->toContain('components must contain at least one component');
});

it('rejects unknown conversion types and missing component codes', function () {
    $errors = app(GradingSchemeValidator::class)->validate([
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => '0-5',
        'components' => [
            ['conversion' => ['type' => 'magic']],
        ],
    ]);

    expect($errors)->toContain('components.0.code is required')
        ->and($errors)->toContain('components.0.conversion.type must be one of linear, threshold, direct, pass_fail');
});
