<?php

use App\Models\User;
use App\Models\Campus;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $campus = Campus::factory()->create();

    // Set current campus in session to bypass campus selection middleware
    session(['current_campus_id' => $campus->id]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});