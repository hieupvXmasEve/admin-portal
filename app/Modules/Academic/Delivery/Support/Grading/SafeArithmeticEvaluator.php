<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support\Grading;

use InvalidArgumentException;

/**
 * Safely evaluates operator-authored arithmetic formulas for the metropolia_v2
 * grading engine.
 *
 * Grammar (recursive descent):
 *   expr   := term (('+' | '-') term)*
 *   term   := factor (('*' | '/') factor)*
 *   factor := NUMBER | IDENTIFIER | '(' expr ')' | '-' factor
 *
 * Only numbers, identifiers ([A-Za-z_][A-Za-z0-9_]*), the four binary operators,
 * unary minus, and parentheses are accepted. Anything else — function calls,
 * back-ticks, comparison/logical operators, statement separators, exponentiation,
 * modulo — fails tokenisation. This is a whitelist parser, never PHP `eval()`, so
 * scheme JSON authored by operators cannot execute arbitrary code.
 */
final class SafeArithmeticEvaluator
{
    /** @var array<int, array{type: string, value: string}> */
    private array $tokens = [];

    private int $position = 0;

    /**
     * @param  array<string, float|int>  $variables
     */
    public function evaluate(string $expression, array $variables): float
    {
        $this->tokens = $this->tokenize($expression);
        $this->position = 0;

        if ($this->tokens === []) {
            throw new InvalidArgumentException('Formula is empty.');
        }

        $result = $this->parseExpression($variables);

        if ($this->position !== count($this->tokens)) {
            throw new InvalidArgumentException("Unexpected token in formula [{$expression}].");
        }

        return $result;
    }

    /**
     * Identifiers referenced by the formula, in first-seen order. Used by the
     * scheme validator to confirm every variable maps to a declared component.
     *
     * @return array<int, string>
     */
    public function identifiers(string $expression): array
    {
        $identifiers = [];

        foreach ($this->tokenize($expression) as $token) {
            if ($token['type'] === 'identifier' && ! in_array($token['value'], $identifiers, true)) {
                $identifiers[] = $token['value'];
            }
        }

        return $identifiers;
    }

    /**
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                $i++;

                continue;
            }

            if (str_contains('+-*/()', $char)) {
                $tokens[] = ['type' => 'operator', 'value' => $char];
                $i++;

                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $number = '';
                while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $number .= $expression[$i];
                    $i++;
                }

                if (! is_numeric($number)) {
                    throw new InvalidArgumentException("Invalid number [{$number}] in formula.");
                }

                $tokens[] = ['type' => 'number', 'value' => $number];

                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $identifier = '';
                while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                    $identifier .= $expression[$i];
                    $i++;
                }

                $tokens[] = ['type' => 'identifier', 'value' => $identifier];

                continue;
            }

            throw new InvalidArgumentException("Illegal character [{$char}] in formula.");
        }

        return $tokens;
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function parseExpression(array $variables): float
    {
        $value = $this->parseTerm($variables);

        while ($this->isOperator('+') || $this->isOperator('-')) {
            $operator = $this->consume()['value'];
            $right = $this->parseTerm($variables);
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function parseTerm(array $variables): float
    {
        $value = $this->parseFactor($variables);

        while ($this->isOperator('*') || $this->isOperator('/')) {
            $operator = $this->consume()['value'];
            $right = $this->parseFactor($variables);

            if ($operator === '/') {
                if ($right === 0.0) {
                    throw new InvalidArgumentException('Division by zero in formula.');
                }

                $value /= $right;
            } else {
                $value *= $right;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function parseFactor(array $variables): float
    {
        if ($this->isOperator('-')) {
            $this->consume();

            return -$this->parseFactor($variables);
        }

        if ($this->isOperator('(')) {
            $this->consume();
            $value = $this->parseExpression($variables);
            $this->expectOperator(')');

            return $value;
        }

        $token = $this->consume();

        if ($token['type'] === 'number') {
            return (float) $token['value'];
        }

        if ($token['type'] === 'identifier') {
            if (! array_key_exists($token['value'], $variables)) {
                throw new InvalidArgumentException("Unknown variable [{$token['value']}] in formula.");
            }

            return (float) $variables[$token['value']];
        }

        throw new InvalidArgumentException("Unexpected token [{$token['value']}] in formula.");
    }

    /**
     * @return array{type: string, value: string}
     */
    private function consume(): array
    {
        if ($this->position >= count($this->tokens)) {
            throw new InvalidArgumentException('Unexpected end of formula.');
        }

        return $this->tokens[$this->position++];
    }

    private function isOperator(string $operator): bool
    {
        $token = $this->tokens[$this->position] ?? null;

        return $token !== null && $token['type'] === 'operator' && $token['value'] === $operator;
    }

    private function expectOperator(string $operator): void
    {
        if (! $this->isOperator($operator)) {
            throw new InvalidArgumentException("Expected [{$operator}] in formula.");
        }

        $this->consume();
    }
}
