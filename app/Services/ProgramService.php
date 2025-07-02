<?php

namespace App\Services;

use App\Models\Program;
use Illuminate\Support\Facades\Log;
use Exception;

class ProgramService
{
    /**
     * Create a new program.
     *
     * @param array $data Validated data.
     * @return Program
     */
    public function createProgram(array $data): Program
    {
        Log::info('Creating a new program', $data);
        return Program::create($data);
    }

    /**
     * Update an existing program.
     *
     * @param Program $program The program to update.
     * @param array $data Validated data.
     * @return Program
     */
    public function updateProgram(Program $program, array $data): Program
    {
        Log::info("Updating program {$program->id}", $data);
        $program->update($data);
        return $program;
    }

    /**
     * Delete a program.
     *
     * @param Program $program The program to delete.
     * @return void
     */
    public function deleteProgram(Program $program): void
    {
        if ($program->specializations()->exists()) {
            throw new Exception('Cannot delete program with existing specializations.');
        }

        if ($program->curriculumVersions()->exists()) {
            throw new Exception('Cannot delete program with existing curriculum versions.');
        }

        Log::warning("Deleting program {$program->id}");
        $program->delete();
    }
}
