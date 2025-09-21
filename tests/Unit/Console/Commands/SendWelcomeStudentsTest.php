<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\SendWelcomeStudents;
use App\Enums\NotificationCategory;
use App\Models\Notification;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendWelcomeStudentsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_identify_new_students_with_default_days()
    {
        // Create a student admitted within the last 30 days
        $recentStudent = Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(15),
            'status' => 'intake_pre_uni_gc',
        ]);

        // Create a student admitted more than 30 days ago
        $oldStudent = Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(45),
            'status' => 'intake_pre_uni_gc',
        ]);

        $this->artisan('notify:welcome-students --dry-run')
            ->expectsOutput('Found 1 new student(s) to notify.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_respects_since_option()
    {
        $sinceDate = '2025-08-20';
        
        // Create student admitted after the since date
        $newStudent = Student::factory()->create([
            'admission_date' => Carbon::createFromFormat('Y-m-d', $sinceDate)->addDay(),
            'status' => 'intake_pre_uni_gc',
        ]);

        // Create student admitted before the since date
        $oldStudent = Student::factory()->create([
            'admission_date' => Carbon::createFromFormat('Y-m-d', $sinceDate)->subDay(),
            'status' => 'intake_pre_uni_gc',
        ]);

        $this->artisan("notify:welcome-students --dry-run --since={$sinceDate}")
            ->expectsOutput('Found 1 new student(s) to notify.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_only_includes_valid_statuses()
    {
        // Create students with different statuses
        Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(5),
            'status' => 'intake_pre_uni_gc', // Should be included
        ]);

        Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(5),
            'status' => 'active', // Should be included
        ]);

        Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(5),
            'status' => 'graduated', // Should be excluded
        ]);

        Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(5),
            'status' => 'inactive', // Should be excluded
        ]);

        $this->artisan('notify:welcome-students --dry-run --days=10')
            ->expectsOutput('Found 2 new student(s) to notify.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_excludes_students_who_already_received_welcome_notification()
    {
        $student = Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(5),
            'status' => 'intake_pre_uni_gc',
        ]);

        // Create existing welcome notification
        Notification::create([
            'notifiable_type' => Student::class,
            'notifiable_id' => $student->id,
            'category' => NotificationCategory::SYSTEM,
            'title' => 'Welcome to Swinburne!',
            'message' => 'Welcome message',
            'data' => [],
            'channels' => ['database'],
        ]);

        $this->artisan('notify:welcome-students --dry-run --days=10')
            ->expectsOutput('No new students found matching the criteria.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_invalid_since_date_format()
    {
        $this->artisan('notify:welcome-students --since=invalid-date')
            ->expectsOutput('Invalid date format. Please use YYYY-MM-DD format for --since option.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_creates_notification_when_not_in_dry_run_mode()
    {
        $student = Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(5),
            'status' => 'intake_pre_uni_gc',
        ]);

        $this->assertDatabaseCount('notifications', 0);

        $this->artisan('notify:welcome-students --days=10')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 1);
        
        $notification = Notification::first();
        $this->assertEquals(Student::class, $notification->notifiable_type);
        $this->assertEquals($student->id, $notification->notifiable_id);
        $this->assertEquals(NotificationCategory::SYSTEM, $notification->category);
        $this->assertEquals('Welcome to Swinburne!', $notification->title);
        $this->assertStringContainsString($student->full_name, $notification->message);
    }

    /** @test */
    public function it_prevents_duplicate_notifications_idempotency()
    {
        $student = Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(5),
            'status' => 'intake_pre_uni_gc',
        ]);

        // Run command twice
        $this->artisan('notify:welcome-students --days=10')
            ->assertExitCode(0);

        $this->artisan('notify:welcome-students --days=10')
            ->assertExitCode(0);

        // Should still only have one notification
        $this->assertDatabaseCount('notifications', 1);
    }

    /** @test */
    public function it_shows_no_students_found_when_none_match_criteria()
    {
        // Create student outside date range
        Student::factory()->create([
            'admission_date' => Carbon::now()->subDays(50),
            'status' => 'intake_pre_uni_gc',
        ]);

        $this->artisan('notify:welcome-students --dry-run --days=30')
            ->expectsOutput('No new students found matching the criteria.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_includes_correct_data_in_notification()
    {
        $student = Student::factory()->create([
            'student_id' => 'TEST001',
            'admission_date' => Carbon::now()->subDays(5),
            'status' => 'intake_pre_uni_gc',
        ]);

        $this->artisan('notify:welcome-students --days=10')
            ->assertExitCode(0);

        $notification = Notification::first();
        $this->assertEquals('TEST001', $notification->data['student_id']);
        $this->assertEquals($student->admission_date->toDateString(), $notification->data['admission_date']);
        $this->assertEquals('new_student_welcome', $notification->data['welcome_type']);
        $this->assertEquals(['database', 'broadcast'], $notification->channels);
        $this->assertFalse($notification->is_important);
        $this->assertNull($notification->expires_at);
    }
}