<?php

declare(strict_types=1);

use App\Modules\Academic\Support\StudentCodeParser;

it('normalizes pasted student codes and removes duplicates', function (): void {
    expect(StudentCodeParser::tokens(" se900001\nSE900002, se900001;SE900003  "))
        ->toBe(['SE900001', 'SE900002', 'SE900001', 'SE900003'])
        ->and(StudentCodeParser::unique(" se900001\nSE900002, se900001;SE900003  "))
        ->toBe(['SE900001', 'SE900002', 'SE900003']);
});
