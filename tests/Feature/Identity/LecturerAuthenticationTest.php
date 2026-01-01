<?php

use App\Models\Campus;
use App\Models\Lecture;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
});

test('lecturer can login with valid credentials', function () {
    $password = 'password123';
    $user = User::factory()->create([
        'email' => 'lecturer@test.com',
        'password' => Hash::make($password),
        'type' => UserType::LECTURER,
        'status' => 'active',
    ]);

    $lecturer = Lecture::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'campus_id' => $this->campus->id,
        'is_active' => true,
        'employment_status' => 'active',
    ]);

    $response = $this->postJson(route('api.lecturer.auth.login'), [
        'email' => 'lecturer@test.com',
        'password' => $password,
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'token_type',
                'expires_in',
            ],
            'message',
        ]);
});

test('lecturer cannot login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'lecturer@test.com',
        'password' => Hash::make('password123'),
        'type' => UserType::LECTURER,
    ]);

    $response = $this->postJson(route('api.lecturer.auth.login'), [
        'email' => 'lecturer@test.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
});

test('lecturer can get own profile', function () {
    $user = User::factory()->create(['type' => UserType::LECTURER]);
    $lecturer = Lecture::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'campus_id' => $this->campus->id
    ]);

    Sanctum::actingAs($lecturer, ['lecturer:access']);

    $response = $this->getJson(route('api.lecturer.auth.me'));

    $response->assertStatus(200)
        ->assertJsonFragment(['email' => $lecturer->email]);
});

test('lecturer can logout', function () {
    $user = User::factory()->create(['type' => UserType::LECTURER]);
    $lecturer = Lecture::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

    Sanctum::actingAs($lecturer, ['lecturer:access']);

    $response = $this->postJson(route('api.lecturer.auth.logout'));

    $response->assertStatus(200);
});
