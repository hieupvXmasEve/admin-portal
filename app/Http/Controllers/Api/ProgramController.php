<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Program\StoreProgramRequest;
use App\Http\Requests\Program\UpdateProgramRequest;
use App\Http\Resources\Program\ProgramResource;
use App\Models\Program;
use App\Services\ProgramService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProgramController extends Controller
{
    public function __construct(protected ProgramService $programService) {}

    public function index(): AnonymousResourceCollection
    {
        return ProgramResource::collection(Program::with('specializations')->paginate());
    }

    public function store(StoreProgramRequest $request): ProgramResource
    {
        $program = $this->programService->createProgram($request->validated());

        return new ProgramResource($program);
    }

    public function show(Program $program): ProgramResource
    {
        return new ProgramResource($program->load('specializations', 'curriculumVersions'));
    }

    public function update(UpdateProgramRequest $request, Program $program): ProgramResource
    {
        $updatedProgram = $this->programService->updateProgram($program, $request->validated());

        return new ProgramResource($updatedProgram);
    }

    public function destroy(Program $program): \Illuminate\Http\Response
    {
        $this->programService->deleteProgram($program);

        return response()->noContent();
    }
}
