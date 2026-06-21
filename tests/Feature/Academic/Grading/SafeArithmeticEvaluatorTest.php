<?php

declare(strict_types=1);

use App\Modules\Academic\Support\Grading\SafeArithmeticEvaluator;

it('evaluates arithmetic with precedence and parentheses', function () {
    $evaluator = new SafeArithmeticEvaluator;

    expect($evaluator->evaluate('1 + 2 * 3', []))->toBe(7.0)
        ->and($evaluator->evaluate('(1 + 2) * 3', []))->toBe(9.0)
        ->and($evaluator->evaluate('10 / 4', []))->toBe(2.5)
        ->and($evaluator->evaluate('-5 + 2', []))->toBe(-3.0);
});

it('resolves component variables from the supplied map', function () {
    $evaluator = new SafeArithmeticEvaluator;

    $value = $evaluator->evaluate('(LAB + QUIZ + EXAM / 2 - 40) / 10', [
        'LAB' => 30.0,
        'QUIZ' => 30.0,
        'EXAM' => 30.0,
    ]);

    expect($value)->toBe(3.5);
});

it('supports underscores and decimals in the formula', function () {
    $evaluator = new SafeArithmeticEvaluator;

    $value = $evaluator->evaluate('HTML_CSS * 2 / 100 + EXAM * 3 / 100', [
        'HTML_CSS' => 50.0,
        'EXAM' => 100.0,
    ]);

    expect($value)->toBe(4.0);
});

it('extracts the identifiers referenced by a formula', function () {
    $evaluator = new SafeArithmeticEvaluator;

    expect($evaluator->identifiers('(LAB + QUIZ + EXAM / 2 - 40) / 10'))
        ->toBe(['LAB', 'QUIZ', 'EXAM']);
});

it('throws on an unknown variable', function () {
    $evaluator = new SafeArithmeticEvaluator;

    expect(fn () => $evaluator->evaluate('GHOST + 1', ['LAB' => 1.0]))
        ->toThrow(InvalidArgumentException::class);
});

it('throws on division by zero', function () {
    $evaluator = new SafeArithmeticEvaluator;

    expect(fn () => $evaluator->evaluate('1 / 0', []))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects any non-arithmetic characters to block code injection', function () {
    $evaluator = new SafeArithmeticEvaluator;

    // Function calls, PHP, shell, and comparison operators must never parse.
    foreach (['phpinfo()', 'LAB ** 2', 'system("ls")', '1 ; 2', 'a && b', '`ls`', 'LAB % 2'] as $malicious) {
        expect(fn () => $evaluator->evaluate($malicious, ['LAB' => 1.0, 'a' => 1.0, 'b' => 1.0]))
            ->toThrow(InvalidArgumentException::class);
    }
});

it('rejects malformed expressions', function () {
    $evaluator = new SafeArithmeticEvaluator;

    foreach (['1 +', '(1 + 2', '1 2', '* 3', ''] as $malformed) {
        expect(fn () => $evaluator->evaluate($malformed, []))
            ->toThrow(InvalidArgumentException::class);
    }
});
