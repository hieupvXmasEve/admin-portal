<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Support;

use App\Models\Program;
use App\Shared\Contracts\Academic\ProgramReferenceReader;

final class EloquentProgramReferenceReader implements ProgramReferenceReader
{
    public function all(): array
    {
        return Program::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Program $program): array => $this->map($program))
            ->all();
    }

    public function find(int $programId): ?array
    {
        $program = Program::query()->find($programId, ['id', 'code', 'name']);

        return $program instanceof Program ? $this->map($program) : null;
    }

    /** @return array{id: int, code: string, name: string} */
    private function map(Program $program): array
    {
        return [
            'id' => (int) $program->id,
            'code' => (string) $program->code,
            'name' => (string) $program->name,
        ];
    }
}
