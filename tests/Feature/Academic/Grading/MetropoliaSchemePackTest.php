<?php

declare(strict_types=1);

use App\Modules\Academic\Delivery\Support\Grading\GradingCalculatorResolver;
use App\Modules\Academic\Delivery\Support\Grading\GradingSchemeValidator;
use App\Modules\Academic\Delivery\Support\Grading\MetropoliaSchemeCatalog;

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
    $resolver = app(GradingCalculatorResolver::class);

    foreach ($catalog->keys() as $key) {
        $scheme = $catalog->get($key);
        expect($scheme['engine'])->toBeIn(['metropolia_v1', 'metropolia_v2'])
            ->and($scheme['scale'])->toBeIn(['0-5', 'pass_fail']);

        // The resolver must accept every scheme and the resolved calculator must
        // run it without error, tagging the breakdown with the declared engine.
        $result = $resolver->resolve($scheme)->calculate([], $scheme);
        expect($result->gradeBreakdown['engine'])->toBe($scheme['engine']);
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
    $resolver = app(GradingCalculatorResolver::class);

    $calculate = function (string $key, array $scores) use ($catalog, $resolver) {
        $scheme = $catalog->get($key);

        return $resolver->resolve($scheme)->calculate($scores, $scheme);
    };

    // Programming (metropolia_v1): exam at 88% with the assignment gate met => grade 5.
    expect($calculate('software_1.programming', ['ASSIGNMENT' => 90, 'EXAM' => 88])->finalGrade)->toBe('5');
    // Failing the assignment gate fails the course regardless of exam.
    expect($calculate('software_1.programming', ['ASSIGNMENT' => 30, 'EXAM' => 88])->passed)->toBeFalse();

    // Database (metropolia_v1): pass/fail at the 80% threshold.
    expect($calculate('software_1.database', ['ASSIGNMENT' => 80])->finalGrade)->toBe('P')
        ->and($calculate('software_1.database', ['ASSIGNMENT' => 79])->finalGrade)->toBe('F');

    // Maths & Physics (metropolia_v1): assignment grade + exam grade (2 + 3 = 5).
    expect($calculate('software_1.maths_physics', ['ASSIGNMENT' => 70, 'EXAM' => 85])->finalGrade)->toBe('5');

    // Cloud Computing (metropolia_v2): exact source formula, both balanced and skewed.
    expect($calculate('hardware_2.cloud_computing', ['LAB' => 30, 'QUIZ' => 30, 'EXAM' => 30])->finalGrade)->toBe('4')
        ->and($calculate('hardware_2.cloud_computing', ['LAB' => 0, 'QUIZ' => 0, 'EXAM' => 100])->finalGrade)->toBe('1');

    // Health Technology (metropolia_v2): (assignment - 40) / 10.
    expect($calculate('hardware_1.health_technology', ['ASSIGNMENT' => 90])->finalGrade)->toBe('5');

    // Web Development (metropolia_v2): JS assignment gate unmet fails the course.
    expect($calculate('software_2.web_development', ['HTML_CSS' => 100, 'JS_ASSIGNMENT' => 10, 'EXAM' => 100])->passed)->toBeFalse();
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
