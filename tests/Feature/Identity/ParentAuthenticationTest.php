<?php

use App\Models\Campus;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\CurriculumVersion;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use App\Shared\Support\Enums\UserType;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create(['campus_id' => $this->campus->id]);
    $this->semester = Semester::factory()->create(['campus_id' => $this->campus->id]);
    $this->curriculum = CurriculumVersion::factory()->create(['program_id' => $this->program->id]);
});

test('parent can login with valid credentials', function () {
    $password = 'password123';
    $user = User::factory()->create([
        'email' => 'parent@test.com',
        'password' => Hash::make($password),
        'type' => UserType::PARENT,
        'status' => 'active',
    ]);

    $parent = ParentProfile::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    $student = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculum->id,
        'intake_semester_id' => $this->semester->id,
    ]);

    $parent->students()->attach($student->id, ['relationship' => 'father', 'is_primary' => true]);

    $response = $this->postJson(route('api.parent.auth.login'), [
        'email' => 'parent@test.com',
        'password' => $password,
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'parent' => [
                    'id',
                    'full_name',
                    'email',
                    'status',
                    'children',
                ],
                'token',
                'token_type',
                'expires_at',
            ],
        ]);

    $this->assertCount(1, $response->json('data.parent.children'));
});

test('parent can get context', function () {
    $user = User::factory()->create(['type' => UserType::PARENT]);
    $parent = ParentProfile::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user, ['parent']);

    $response = $this->getJson(route('api.parent.context'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'user' => [
                    'id',
                    'full_name',
                    'children',
                ],
                'settings',
                'feature_flags',
                'permissions',
                'notifications',
            ],
        ]);
});

test('parent can access student context via proxy', function () {
    $parentUser = User::factory()->create(['type' => UserType::PARENT]);
    $parent = ParentProfile::factory()->create(['user_id' => $parentUser->id]);

    $student = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculum->id,
        'intake_semester_id' => $this->semester->id,
        'status' => 'active',
    ]);

    $parent->students()->attach($student->id, ['relationship' => 'father']);

    Sanctum::actingAs($parentUser, ['parent']);

    // Attempt to access student context with student_id header
    $response = $this->withHeader('X-Student-ID', (string) $student->id)
        ->getJson(route('api.student.context'));

    $response->assertStatus(200)
        ->assertJsonPath('data.student.id', $student->id);
});

test('parent cannot access unauthorized student data', function () {
    $parentUser = User::factory()->create(['type' => UserType::PARENT]);
    ParentProfile::factory()->create(['user_id' => $parentUser->id]);

    $otherStudent = Student::factory()->create();

    Sanctum::actingAs($parentUser, ['parent']);

    $response = $this->withHeader('X-Student-ID', (string) $otherStudent->id)
        ->getJson(route('api.student.context'));

    $response->assertStatus(403);
});

test('parent logout deletes token', function () {
    $user = User::factory()->create(['type' => UserType::PARENT]);
    $parent = ParentProfile::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user, ['parent']);

    $response = $this->postJson(route('api.parent.auth.logout'));

    $response->assertStatus(200);
    $this->assertCount(0, $user->tokens);
});
