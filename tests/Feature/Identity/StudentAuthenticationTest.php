<?php

use App\Models\Campus;
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

test('student can login with valid credentials', function () {
    $password = 'password123';
    $user = User::factory()->create([
        'email' => 'student@test.com',
        'password' => Hash::make($password),
        'type' => UserType::STUDENT,
        'status' => 'active',
    ]);

    $student = Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculum->id,
        'intake_semester_id' => $this->semester->id,
        'status' => 'active',
    ]);

    $response = $this->postJson(route('api.student.auth.login'), [
        'email' => 'student@test.com',
        'password' => $password,
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'student' => [
                    'id',
                    'student_id',
                    'full_name',
                    'email',
                ],
                'token',
                'token_type',
                'expires_at',
            ],
            'message',
        ]);

    $this->assertDatabaseHas('students', [
        'id' => $student->id,
    ]);
});

test('student cannot login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'student@test.com',
        'password' => Hash::make('password123'),
        'type' => UserType::STUDENT,
    ]);

    $response = $this->postJson(route('api.student.auth.login'), [
        'email' => 'student@test.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
});

test('student cannot login if account is inactive', function () {
    $user = User::factory()->create([
        'email' => 'student@test.com',
        'password' => Hash::make('password123'),
        'type' => UserType::STUDENT,
        'status' => 'inactive',
    ]);

    Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'status' => 'active',
    ]);

    $response = $this->postJson(route('api.student.auth.login'), [
        'email' => 'student@test.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401)
        ->assertJsonFragment(['success' => false]);
});

test('student can logout', function () {
    $user = User::factory()->create(['type' => UserType::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

    Sanctum::actingAs($student, ['student']);

    $response = $this->postJson(route('api.student.auth.logout'));

    $response->assertStatus(200);
});

test('student can refresh token', function () {
    $user = User::factory()->create(['type' => UserType::STUDENT]);
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

    Sanctum::actingAs($student, ['student']);

    $response = $this->postJson(route('api.student.auth.refresh'), [
        'device_name' => 'New Device',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'token_type',
                'expires_at',
            ],
        ]);
});
