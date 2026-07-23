<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Services\V1\Student\ProfileService;
use App\Shared\Contracts\StudentRegistry\DTO\StudentProfile;
use App\Shared\Contracts\StudentRegistry\StudentProfileReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publishes only Registry-owned identity, profile, and contact fields', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->state([
        'intake' => 1,
        'intake_semester_id' => $semester->id,
        'student_id' => 'REG-2001',
        'full_name' => 'Registry Profile Student',
        'email' => 'profile.reader@example.test',
        'phone' => '+84 900 123 456',
        'date_of_birth' => '2001-01-02',
        'ethnicity' => 'Kinh',
        'current_address_line' => '1 Registry Street',
        'current_ward' => 'Ward 1',
        'current_province' => 'Ho Chi Minh City',
        'current_country' => 'Vietnam',
        'cccd_address' => 'Citizen address',
        'cccd_address_line' => '2 Citizen Street',
        'cccd_ward' => 'Ward 2',
        'cccd_province' => 'Da Nang',
        'cccd_country' => 'Vietnam',
        'emergency_contact_email' => 'guardian@example.test',
        'emergency_contact_name_1' => 'Second Guardian',
        'emergency_contact_email_1' => 'second.guardian@example.test',
        'emergency_contact_phone_1' => '+84 911 111 111',
        'emergency_contact_relationship_1' => 'Parent',
        'status' => 'deferred',
        'expected_graduation_date' => '2027-06-30',
    ])->create();

    $profile = app(StudentProfileReader::class)->findProfile((int) $student->id);

    expect($profile)
        ->toBeInstanceOf(StudentProfile::class)
        ->and($profile?->id)->toBe($student->id)
        ->and($profile?->studentCode)->toBe('REG-2001')
        ->and($profile?->fullName)->toBe($student->fresh()->full_name)
        ->and($profile?->campusId)->toBe($campus->id)
        ->and($profile?->dateOfBirth)->toBe('2001-01-02')
        ->and($profile?->currentAddressLine)->toBe('1 Registry Street')
        ->and($profile?->cccdAddressLine)->toBe('2 Citizen Street')
        ->and($profile?->emergencyContactEmail)->toBe('guardian@example.test')
        ->and($profile?->emergencyContactName1)->toBe('Second Guardian')
        ->and($profile?->highSchoolName)->toBe($student->high_school_name)
        ->and(property_exists($profile, 'status'))->toBeFalse()
        ->and(property_exists($profile, 'expectedGraduationDate'))->toBeFalse()
        ->and(app(StudentProfileReader::class)->findProfile(999_999))->toBeNull();
});

it('uses the Registry profile snapshot in the v1 profile response', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->state([
        'intake' => 1,
        'intake_semester_id' => $semester->id,
        'full_name' => 'Persisted Registry Profile',
        'email' => 'persisted.profile@example.test',
    ])->create();
    $staleStudent = $student->fresh();
    $staleStudent->full_name = 'Stale Legacy Profile';
    $staleStudent->email = 'stale.profile@example.test';

    $profile = app(ProfileService::class)->getProfile($staleStudent);

    expect($profile['info']['full_name'])->toBe($student->fresh()->full_name)
        ->and($profile['info']['email'])->toBe('persisted.profile@example.test');
});
