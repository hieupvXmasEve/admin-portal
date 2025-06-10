<?php

use App\Models\User;
use App\Models\Campus;

test('returns a successful response', function () {
    $user = User::factory()->create();
    $campus = Campus::factory()->create();

    // Set current campus in session to bypass campus selection middleware
    session(['current_campus_id' => $campus->id]);

    $response = $this->actingAs($user)->get('/');

    // Root route redirects to dashboard
    $response->assertRedirect('/dashboard');
});