<?php

declare(strict_types=1);

use App\Models\Program;
use App\Models\Specialization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('SpecializationController', function () {
    describe('index', function () {
        it('displays specializations index page', function () {
            $program = Program::factory()->create();
            $specializations = Specialization::factory()
                ->for($program)
                ->count(3)
                ->create();

            $response = $this->actingAs($this->user)->get('/specializations');

            $response->assertOk();
            $response->assertInertia(
                fn($page) =>
                $page->component('specializations/Index')
                    ->has('specializations.data', 3)
                    ->has('programs')
                    ->has('statistics')
            );
        });

        it('can search specializations', function () {
            $program = Program::factory()->create();
            Specialization::factory()
                ->for($program)
                ->create(['name' => 'Software Development']);
            Specialization::factory()
                ->for($program)
                ->create(['name' => 'Cybersecurity']);

            $response = $this->actingAs($this->user)
                ->get('/specializations?search=Software');

            $response->assertOk();
            $response->assertInertia(
                fn($page) =>
                $page->has('specializations.data', 1)
                    ->where('specializations.data.0.name', 'Software Development')
            );
        });

        it('can filter by program', function () {
            $program1 = Program::factory()->create();
            $program2 = Program::factory()->create();

            Specialization::factory()->for($program1)->create();
            Specialization::factory()->for($program2)->create();

            $response = $this->actingAs($this->user)
                ->get("/specializations?program_id={$program1->id}");

            $response->assertOk();
            $response->assertInertia(
                fn($page) =>
                $page->has('specializations.data', 1)
                    ->where('specializations.data.0.program_id', $program1->id)
            );
        });
    });

    describe('create', function () {
        it('displays create specialization page', function () {
            Program::factory()->count(2)->create();

            $response = $this->actingAs($this->user)->get('/specializations/create');

            $response->assertOk();
            $response->assertInertia(
                fn($page) =>
                $page->component('specializations/Create')
                    ->has('programs', 2)
            );
        });

        it('can preselect program from query parameter', function () {
            $program = Program::factory()->create();

            $response = $this->actingAs($this->user)
                ->get("/specializations/create?program_id={$program->id}");

            $response->assertOk();
            $response->assertInertia(
                fn($page) =>
                $page->where('selectedProgramId', $program->id)
            );
        });
    });

    describe('store', function () {
        it('creates a new specialization', function () {
            $program = Program::factory()->create();
            $specializationData = [
                'program_id' => $program->id,
                'name' => 'Software Development',
                'code' => 'IT-SD',
                'description' => 'Focus on software engineering and development',
                'is_active' => true,
            ];

            $response = $this->actingAs($this->user)
                ->post('/specializations', $specializationData);

            $response->assertRedirect('/specializations');
            $response->assertSessionHas('success', 'Specialization created successfully.');

            $this->assertDatabaseHas('specializations', [
                'program_id' => $program->id,
                'name' => 'Software Development',
                'code' => 'IT-SD',
                'is_active' => true,
            ]);
        });

        it('validates required fields', function () {
            $response = $this->actingAs($this->user)
                ->post('/specializations', []);

            $response->assertSessionHasErrors(['program_id', 'name', 'code']);
        });

        it('validates unique specialization code', function () {
            $program = Program::factory()->create();
            Specialization::factory()
                ->for($program)
                ->create(['code' => 'IT-SD']);

            $response = $this->actingAs($this->user)
                ->post('/specializations', [
                    'program_id' => $program->id,
                    'name' => 'Another Specialization',
                    'code' => 'IT-SD',
                ]);

            $response->assertSessionHasErrors(['code']);
        });
    });

    describe('show', function () {
        it('displays specialization details', function () {
            $program = Program::factory()->create();
            $specialization = Specialization::factory()
                ->for($program)
                ->create();

            $response = $this->actingAs($this->user)
                ->get("/specializations/{$specialization->id}");

            $response->assertOk();
            $response->assertInertia(
                fn($page) =>
                $page->component('specializations/Show')
                    ->where('specialization.id', $specialization->id)
                    ->has('statistics')
            );
        });
    });

    describe('edit', function () {
        it('displays edit specialization page', function () {
            $program = Program::factory()->create();
            $specialization = Specialization::factory()
                ->for($program)
                ->create();

            $response = $this->actingAs($this->user)
                ->get("/specializations/{$specialization->id}/edit");

            $response->assertOk();
            $response->assertInertia(
                fn($page) =>
                $page->component('specializations/Edit')
                    ->where('specialization.id', $specialization->id)
                    ->has('programs')
            );
        });
    });

    describe('update', function () {
        it('updates an existing specialization', function () {
            $program = Program::factory()->create();
            $specialization = Specialization::factory()
                ->for($program)
                ->create();

            $updateData = [
                'program_id' => $program->id,
                'name' => 'Updated Specialization',
                'code' => $specialization->code,
                'description' => 'Updated description',
                'is_active' => false,
            ];

            $response = $this->actingAs($this->user)
                ->put("/specializations/{$specialization->id}", $updateData);

            $response->assertRedirect('/specializations');
            $response->assertSessionHas('success', 'Specialization updated successfully.');

            $this->assertDatabaseHas('specializations', [
                'id' => $specialization->id,
                'name' => 'Updated Specialization',
                'is_active' => false,
            ]);
        });

        it('validates unique code when updating', function () {
            $program = Program::factory()->create();
            $specialization1 = Specialization::factory()
                ->for($program)
                ->create(['code' => 'IT-SD']);
            $specialization2 = Specialization::factory()
                ->for($program)
                ->create(['code' => 'IT-CS']);

            $response = $this->actingAs($this->user)
                ->put("/specializations/{$specialization2->id}", [
                    'program_id' => $program->id,
                    'name' => $specialization2->name,
                    'code' => 'IT-SD', // Try to use existing code
                ]);

            $response->assertSessionHasErrors(['code']);
        });
    });

    describe('destroy', function () {
        it('deletes a specialization without curriculum versions', function () {
            $program = Program::factory()->create();
            $specialization = Specialization::factory()
                ->for($program)
                ->create(['name' => 'Test Specialization']);

            $response = $this->actingAs($this->user)
                ->delete("/specializations/{$specialization->id}");

            $response->assertRedirect('/specializations');
            $response->assertSessionHas('success', "Specialization 'Test Specialization' deleted successfully.");

            $this->assertDatabaseMissing('specializations', [
                'id' => $specialization->id,
            ]);
        });

        it('prevents deletion of specialization with curriculum versions', function () {
            $program = Program::factory()->create();
            $specialization = Specialization::factory()
                ->for($program)
                ->hasCurriculumVersions(1)
                ->create();

            $response = $this->actingAs($this->user)
                ->delete("/specializations/{$specialization->id}");

            $response->assertSessionHasErrors(['error']);
            $this->assertDatabaseHas('specializations', [
                'id' => $specialization->id,
            ]);
        });
    });

    describe('bulk delete', function () {
        it('deletes multiple specializations without curriculum versions', function () {
            $program = Program::factory()->create();
            $specializations = Specialization::factory()
                ->for($program)
                ->count(3)
                ->create();

            $response = $this->actingAs($this->user)
                ->delete('/api/specializations/bulk-delete', [
                    'specialization_ids' => $specializations->pluck('id')->toArray(),
                ]);

            $response->assertOk();
            $response->assertJson([
                'success' => true,
                'deleted' => $specializations->pluck('name')->toArray(),
            ]);

            foreach ($specializations as $specialization) {
                $this->assertDatabaseMissing('specializations', [
                    'id' => $specialization->id,
                ]);
            }
        });
    });
});
