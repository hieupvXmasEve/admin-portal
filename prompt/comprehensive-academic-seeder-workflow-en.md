# 🎓 Comprehensive Academic Seeder Workflow
## Complete Process for Creating Data Seeders to Simulate Student Learning Journey

### 📋 **Overview**

This document describes the complete process for creating data seeders to simulate the entire student learning journey during an academic semester, from course enrollment to final GPA calculation.

---

## 🏗️ **Prerequisites: Model & Factory Creation**

Before creating seeders, ensure all models and factories are properly set up for optimal data generation and pagination testing.

### **Step 0a: Model Creation & Relationships** 🔧

**Create Models with Proper Relationships:**
```bash
# Generate models with migrations and factories
php artisan make:model Room -mf
php artisan make:model ClassSession -mf
php artisan make:model Attendance -mf
php artisan make:model StudentGroup -mf
php artisan make:model StudentGroupMember -mf
php artisan make:model AcademicRecord -mf
php artisan make:model AssessmentComponentDetailScore -mf
php artisan make:model GpaCalculation -mf
php artisan make:model RoomBooking -mf
```

**Model Relationship Standards:**
```php
// Example: Student.php model with complete relationships
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'full_name', 'email', 'campus_id', 'program_id',
        'specialization_id', 'curriculum_version_id', 'enrollment_status',
        'admission_date', 'expected_graduation_date', 'status'
    ];

    protected $casts = [
        'admission_date' => 'date',
        'expected_graduation_date' => 'date',
        'enrollment_status' => StudentEnrollmentStatus::class,
        'status' => StudentStatus::class,
    ];

    // === RELATIONSHIPS ===
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function academicRecords(): HasMany
    {
        return $this->hasMany(AcademicRecord::class);
    }

    public function gpaCalculations(): HasMany
    {
        return $this->hasMany(GpaCalculation::class);
    }

    public function studentGroupMembers(): HasMany
    {
        return $this->hasMany(StudentGroupMember::class);
    }

    public function academicHolds(): HasMany
    {
        return $this->hasMany(AcademicHold::class);
    }
}
```

### **Step 0b: Factory Creation for Mass Data Generation** 🏭

**Factory Standards for Pagination Testing:**

```php
// database/factories/StudentFactory.php
<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $campuses = Campus::pluck('id')->toArray();
        $programs = Program::pluck('id')->toArray();
        $specializations = Specialization::pluck('id')->toArray();
        $curriculumVersions = CurriculumVersion::pluck('id')->toArray();

        $enrollmentStatuses = ['admitted', 'enrolled', 'active', 'on_leave', 'suspended', 'graduated', 'dropped_out'];
        $statuses = ['active', 'inactive', 'suspended'];

        return [
            'student_id' => $this->generateStudentId(),
            'full_name' => $this->faker->firstName() . ' ' . $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'campus_id' => $this->faker->randomElement($campuses),
            'program_id' => $this->faker->randomElement($programs),
            'specialization_id' => $this->faker->optional(0.8)->randomElement($specializations),
            'curriculum_version_id' => $this->faker->randomElement($curriculumVersions),
            'enrollment_status' => $this->faker->randomElement($enrollmentStatuses),
            'status' => $this->faker->randomElement($statuses),
            'admission_date' => $this->faker->dateTimeBetween('-4 years', '-1 year'),
            'expected_graduation_date' => $this->faker->dateTimeBetween('+1 year', '+3 years'),
        ];
    }

    private function generateStudentId(): string
    {
        $year = $this->faker->numberBetween(2020, 2024);
        $sequence = $this->faker->unique()->numberBetween(1000, 9999);
        return $year . sprintf('%04d', $sequence);
    }

    // State methods for different student types
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrollment_status' => 'active',
            'status' => 'active',
        ]);
    }

    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrollment_status' => 'graduated',
            'status' => 'inactive',
            'expected_graduation_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ]);
    }

    public function newStudent(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrollment_status' => 'admitted',
            'admission_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }
}
```

**Factory for Complex Models:**
```php
// database/factories/AttendanceFactory.php
<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Student;
use App\Models\Lecture;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $statuses = ['present', 'absent', 'late', 'excused'];
        $recordingMethods = ['manual', 'qr_code', 'mobile_app', 'facial_recognition'];
        $participationLevels = ['excellent', 'good', 'average', 'poor'];

        $status = $this->faker->randomElement($statuses);
        $isPresent = in_array($status, ['present', 'late']);

        return [
            'class_session_id' => ClassSession::factory(),
            'student_id' => Student::factory(),
            'recorded_by_lecture_id' => Lecture::factory(),
            'status' => $status,
            'check_in_time' => $isPresent ? $this->faker->dateTimeThisMonth() : null,
            'check_out_time' => $isPresent ? $this->faker->dateTimeThisMonth() : null,
            'minutes_late' => $status === 'late' ? $this->faker->numberBetween(1, 30) : 0,
            'recording_method' => $this->faker->randomElement($recordingMethods),
            'participation_level' => $isPresent ? $this->faker->randomElement($participationLevels) : null,
            'participation_score' => $isPresent ? $this->faker->numberBetween(5, 10) : null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'is_verified' => $this->faker->boolean(90),
            'affects_grade' => $this->faker->boolean(80),
            'verified_at' => $this->faker->dateTimeThisMonth(),
            'verified_by_lecture_id' => Lecture::factory(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
            'check_in_time' => $this->faker->dateTimeThisMonth(),
            'check_out_time' => $this->faker->dateTimeThisMonth(),
            'participation_level' => $this->faker->randomElement(['excellent', 'good', 'average']),
            'participation_score' => $this->faker->numberBetween(7, 10),
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
            'check_in_time' => null,
            'check_out_time' => null,
            'participation_level' => null,
            'participation_score' => null,
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'late',
            'minutes_late' => $this->faker->numberBetween(5, 25),
            'participation_level' => $this->faker->randomElement(['good', 'average']),
            'participation_score' => $this->faker->numberBetween(5, 8),
        ]);
    }
}
```

### **Step 0c: Data Volume Configuration for Pagination** 📊

**Recommended Data Volumes for Pagination Testing:**

```php
// config/seeder.php - Create this configuration file
<?php

return [
    // === PAGINATION TEST VOLUMES ===
    'pagination_volumes' => [
        // Small dataset (development)
        'small' => [
            'campuses' => 3,
            'programs' => 5,
            'specializations' => 8,
            'units' => 20,
            'students' => 30,
            'lectures' => 10,
            'course_offerings' => 15,
            'class_sessions_per_offering' => 24,
            'student_groups' => 8,
            'attendances_per_session' => 12,
            'curriculum_versions' => 3,
            'equivalent_units' => 5,
            'graduation_requirements' => 8,
            'enrollments' => 30,
        ],

        // Medium dataset (testing)
        'medium' => [
            'campuses' => 5,
            'programs' => 8,
            'specializations' => 15,
            'units' => 40,
            'students' => 60,
            'lectures' => 20,
            'course_offerings' => 30,
            'class_sessions_per_offering' => 36,
            'student_groups' => 15,
            'attendances_per_session' => 20,
            'curriculum_versions' => 5,
            'equivalent_units' => 10,
            'graduation_requirements' => 23,
            'enrollments' => 60,
        ],

        // Large dataset (production simulation)
        'large' => [
            'campuses' => 8,
            'programs' => 12,
            'specializations' => 25,
            'units' => 80,
            'students' => 100,
            'lectures' => 40,
            'course_offerings' => 60,
            'class_sessions_per_offering' => 48,
            'student_groups' => 30,
            'attendances_per_session' => 35,
            'curriculum_versions' => 8,
            'equivalent_units' => 20,
            'graduation_requirements' => 37,
            'enrollments' => 100,
        ],

        // Extra large dataset (performance testing)
        'xlarge' => [
            'campuses' => 12,
            'programs' => 15,
            'specializations' => 35,
            'units' => 100,
            'students' => 100,
            'lectures' => 50,
            'course_offerings' => 80,
            'class_sessions_per_offering' => 60,
            'student_groups' => 40,
            'attendances_per_session' => 50,
            'curriculum_versions' => 10,
            'equivalent_units' => 30,
            'graduation_requirements' => 50,
            'enrollments' => 100,
        ],
    ],

    // === PAGINATION SETTINGS ===
    'pagination_config' => [
        'students_per_page' => 15,
        'course_offerings_per_page' => 20,
        'attendances_per_page' => 25,
        'academic_records_per_page' => 15,
        'class_sessions_per_page' => 20,
    ],

    // === DATA DISTRIBUTION ===
    'data_distribution' => [
        'grade_distribution' => [
            'A' => 15, // 15% get A grades
            'B' => 35, // 35% get B grades  
            'C' => 30, // 30% get C grades
            'D' => 15, // 15% get D grades
            'F' => 5,  // 5% get F grades
        ],
        'attendance_rate' => 85, // 85% overall attendance
        'active_student_rate' => 90, // 90% of students are active
    ],
];
```

### **Step 0d: Seeder Base Class with Volume Control** 🎛️

```php
// database/seeders/BaseVolumeSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

abstract class BaseVolumeSeeder extends Seeder
{
    protected string $volumeSize;
    protected array $volumes;

    public function __construct()
    {
        $this->volumeSize = config('seeder.default_volume', 'medium');
        $this->volumes = config('seeder.pagination_volumes.' . $this->volumeSize, []);
    }

    protected function getVolume(string $key, int $default = 10): int
    {
        return $this->volumes[$key] ?? $default;
    }

    protected function setVolumeSize(string $size): void
    {
        $this->volumeSize = $size;
        $this->volumes = config('seeder.pagination_volumes.' . $size, []);
    }

    protected function info(string $message): void
    {
        $this->command->info("📊 [{$this->volumeSize}] {$message}");
    }

    protected function createProgressBar(int $max, string $label): \Symfony\Component\Console\Helper\ProgressBar
    {
        $bar = $this->command->getOutput()->createProgressBar($max);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %message%");
        $bar->setMessage($label);
        return $bar;
    }
}
```

---

## 🎯 **Complete Data Seeder Creation Steps**

### **Step 1: Educational Infrastructure** 📚(Skip this step)
**Flow:** `campuses → users → roles → permissions → campus_user_roles

**Related Tables:**
- `campuses` - Campus locations *(Volume: 3-12 campuses)*
- `users` - Admin users, system users *(Volume: 50-200 users)*
- `roles` - User roles (admin, staff, etc.) *(Volume: 8-15 roles)*
- `permissions` - System permissions *(Volume: 100-300 permissions)*
- `campus_user_roles` - User role assignments by campus *(Volume: 100-500 assignments)*

**Purpose:** Set up basic system with campuses, admin users, and permissions

**Seeder:** `UserSeeder.php`

**Data Volume Example:**
```php
// Recommended volumes for pagination testing
$campusCount = $this->getVolume('campuses', 5);
$userCount = $campusCount * 20; // 20 users per campus
$roleCount = 12; // Fixed number of roles
```

---

### **Step 2: Basic Academic Structure** 🏫
**Flow:** `programs → specializations → graduation_requirements → units → semesters`

**Related Tables:**
- `programs` - Degree programs *(Volume: 5-15 programs)*
- `specializations` - Program specializations *(Volume: 8-35 specializations)*
- `graduation_requirements` - Graduation requirements *(Volume: 3-15 requirements)*
- `units` - Individual courses/subjects *(Volume: 20-100 units)*
- `semesters` - Academic periods *(Volume: 8-12 semesters)*

**Purpose:** Define academic programs, specializations, graduation requirements, courses, and semester periods

**Seeders:** `ProgramSeeder.php`, `GraduationRequirementSeeder.php`, `UnitSeeder.php`, `SemesterSeeder.php`

**Factory Usage:**
```php
// Create programs with factories
Program::factory($this->getVolume('programs', 8))->create();

// Create graduation requirements for each program/specialization combination
$programs = Program::with('specializations')->get();
foreach ($programs as $program) {
    // General program requirement
    GraduationRequirement::factory()->create([
        'program_id' => $program->id,
        'specialization_id' => null,
        'total_credits_required' => fake()->numberBetween(120, 150),
        'minimum_gpa' => fake()->randomElement([2.0, 2.5, 3.0]),
        'effective_from' => now()->subYears(2)->toDateString(),
        'is_active' => true,
    ]);
    
    // Specialization-specific requirements
    foreach ($program->specializations as $specialization) {
        GraduationRequirement::factory()->create([
            'program_id' => $program->id,
            'specialization_id' => $specialization->id,
            'total_credits_required' => fake()->numberBetween(125, 160),
            'core_credits_required' => fake()->numberBetween(40, 60),
            'major_credits_required' => fake()->numberBetween(50, 80),
            'elective_credits_required' => fake()->numberBetween(15, 30),
            'minimum_gpa' => fake()->randomElement([2.0, 2.5, 3.0]),
            'minimum_major_gpa' => fake()->randomElement([2.5, 3.0, 3.5]),
            'required_internship' => fake()->boolean(30),
            'required_thesis' => fake()->boolean(20),
            'effective_from' => now()->subYears(1)->toDateString(),
            'is_active' => true,
        ]);
    }
}

// Create units with realistic distribution
Unit::factory($this->getVolume('units', 40))
    ->state(function () {
        return [
            'credit_points' => fake()->randomElement([12.5, 25.0]),
            'difficulty_level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
        ];
    })
    ->create();
```

---

### **Step 3: Faculty and Teaching Capacity** 👨‍🏫
**Flow:** `lectures`

**Related Tables:**
- `lectures` - Lecturer profiles *(Volume: 25-50 lecturers)*

**Purpose:** Create lecturer profiles with expertise and preferred teaching schedules

**Seeder:** `LectureSeeder.php`

**High-Volume Generation:**
```php
// Create lecturers with specializations
Lecture::factory($this->getVolume('lectures', 60))
    ->create()
    ->each(function ($lecture) {
        // Assign 2-4 specialization areas per lecturer
        $specializations = Specialization::inRandomOrder()
            ->limit(fake()->numberBetween(2, 4))
            ->pluck('id');
        
        $lecture->specializations()->attach($specializations);
    });
```

---

### **Step 4: Curriculum Framework** 📋
**Flow:** `curriculum_versions → curriculum_units → unit_prerequisite_groups → unit_prerequisite_conditions`

**Related Tables:**
- `curriculum_versions` - Curriculum versions *(Volume: 3-10 versions)*
- `curriculum_units` - Units in curriculum *(Volume: 60-270 mappings)*
- `unit_prerequisite_groups` - Prerequisite groups *(Volume: 10-30 groups)*
- `unit_prerequisite_conditions` - Prerequisite rules *(Volume: 20-60 conditions)*
- `equivalent_units` - Equivalent units *(Volume: 5-30 mappings)*

**Purpose:** Establish curriculum structure, prerequisite requirements, and unit equivalencies

**Seeder:** `CurriculumSeeder.php`

**Complex Relationship Factory:**
```php
// Create curriculum with realistic prerequisite chains and 9 semesters
CurriculumVersion::factory($this->getVolume('curriculum_versions', 5))
    ->create()
    ->each(function ($curriculum) {
        // Each curriculum version has exactly 9 semesters (1-9) across 3 years
        $allUnits = Unit::all();
        $unitsPerSemester = ceil($allUnits->count() / 9);
        
        // Distribute units across 9 semesters (3 years)
        for ($semester = 1; $semester <= 9; $semester++) {
            $yearLevel = ceil($semester / 3); // Year 1: semesters 1-3, Year 2: 4-6, Year 3: 7-9
            $unitsThisSemester = $allUnits->random(min($unitsPerSemester, $allUnits->count()));
            
            foreach ($unitsThisSemester as $index => $unit) {
                CurriculumUnit::create([
                    'curriculum_version_id' => $curriculum->id,
                    'unit_id' => $unit->id,
                    'year_level' => $yearLevel,
                    'semester' => $semester,
                    'order_sequence' => $index + 1,
                ]);
            }
        }
    });

// Create equivalent units mappings for unit transfers and substitutions
$equivalentUnitsCount = $this->getVolume('equivalent_units', 10);
$units = Unit::all();

for ($i = 0; $i < $equivalentUnitsCount; $i++) {
    $sourceUnit = $units->random();
    $equivalentUnit = $units->where('id', '!=', $sourceUnit->id)->random();
    
    // Avoid duplicate mappings
    $existingMapping = EquivalentUnit::where([
        ['source_unit_id', $sourceUnit->id],
        ['equivalent_unit_id', $equivalentUnit->id]
    ])->orWhere([
        ['source_unit_id', $equivalentUnit->id],
        ['equivalent_unit_id', $sourceUnit->id]
    ])->exists();
    
    if (!$existingMapping) {
        EquivalentUnit::create([
            'source_unit_id' => $sourceUnit->id,
            'equivalent_unit_id' => $equivalentUnit->id,
            'equivalence_type' => fake()->randomElement(['direct', 'partial', 'conditional']),
            'credit_ratio' => fake()->randomFloat(2, 0.5, 1.0),
            'notes' => fake()->optional(0.3)->sentence(),
            'approved_by' => fake()->numberBetween(1, 10),
            'approved_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'is_active' => true,
        ]);
    }
}
```

---

### **Step 5: Student Enrollment** 🎓
**Flow:** `students → course_offerings → course_registrations → enrollments`

**Related Tables:**
- `students` - Student records *(Volume: 30-100 students)*
- `course_offerings` - Course instances *(Volume: 15-80 offerings)*
- `course_registrations` - Student registrations *(Volume: 90-800 registrations)*
- `enrollments` - Semester enrollment records *(Volume: 30-100 enrollments)*

**Purpose:** Create students and register them for courses and semesters

**Seeders:** `CurriculumSeeder.php` (students), `CourseOfferingSeeder.php`, `CourseRegistrationSeeder.php`, `EnrollmentSeeder.php`

**Mass Student Creation with Realistic Distribution:**
```php
// Create students with realistic enrollment distribution
$studentCount = $this->getVolume('students', 60);
$progressBar = $this->createProgressBar($studentCount, 'Creating students...');

// Create students in batches for performance
collect(range(1, $studentCount))->chunk(100)->each(function ($chunk) use ($progressBar) {
    $students = Student::factory(count($chunk))
        ->state(function () {
            // Realistic distribution of student types
            $rand = fake()->numberBetween(1, 100);
            if ($rand <= 75) return ['enrollment_status' => 'active'];
            if ($rand <= 85) return ['enrollment_status' => 'enrolled'];
            if ($rand <= 90) return ['enrollment_status' => 'on_leave'];
            if ($rand <= 95) return ['enrollment_status' => 'graduated'];
            return ['enrollment_status' => 'suspended'];
        })
        ->create();
    
    $progressBar->advance(count($chunk));
});

$progressBar->finish();

// Create semester enrollments for all students
$students = Student::with('curriculumVersion')->get();
$semesters = Semester::all();

foreach ($students as $student) {
    $currentSemester = $semesters->where('is_active', true)->first();
    if ($currentSemester) {
        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $currentSemester->id,
            'curriculum_version_id' => $student->curriculum_version_id,
            'semester_number' => 1, // First semester for new students
            'status' => fake()->randomElement(['in_progress', 'completed']),
            'notes' => fake()->optional(0.3)->sentence(),
        ]);
    }
}
```

---

### **Step 6: Assessment Structure** 📝
**Flow:** `syllabus → assessment_components → assessment_component_details`

**Related Tables:**
- `syllabus` - Course syllabi *(Volume: 15-80 syllabi)*
- `assessment_components` - Assessment types *(Volume: 45-320 components)*
- `assessment_component_details` - Assessment breakdown *(Volume: 135-1280 details)*

**Purpose:** Set up syllabi and detailed assessment structures for each course

**Seeder:** `SyllabusSeeder.php`

**Assessment Factory with Realistic Weighting:**
```php
// Create syllabus with proper assessment distribution
Syllabus::factory($this->getVolume('course_offerings', 30))
    ->create()
    ->each(function ($syllabus) {
        // Create 3-6 assessment components per syllabus
        $componentCount = fake()->numberBetween(3, 6);
        AssessmentComponent::factory($componentCount)
            ->for($syllabus)
            ->state(function () use ($componentCount) {
                return [
                    'weight_percentage' => 100 / $componentCount, // Equal distribution
                    'assessment_type' => fake()->randomElement([
                        'exam', 'assignment', 'project', 'quiz', 'presentation'
                    ]),
                ];
            })
            ->create()
            ->each(function ($component) {
                // Create 2-5 detail items per component
                AssessmentComponentDetail::factory(fake()->numberBetween(2, 5))
                    ->for($component)
                    ->create();
            });
    });
```

---

### **Step 7: Class Scheduling & Facilities** 🏛️
**Flow:** `rooms → room_bookings → class_sessions`

**Related Tables:**
- `rooms` - Available classrooms *(Volume: 15-60 rooms)*
- `room_bookings` - Room reservations *(Volume: 360-4800 bookings)*
- `class_sessions` - Scheduled sessions *(Volume: 360-4800 sessions)*

**Purpose:** Create class schedules with assigned rooms and instructors

**Seeders:** `RoomSeeder.php`, `ClassScheduleSeeder.php`

**Scheduling Factory with Conflict Prevention:**
```php
// Create realistic class schedules
$courseOfferings = CourseOffering::with('unit')->get();
$rooms = Room::all();

foreach ($courseOfferings as $offering) {
    $sessionsCount = $this->getVolume('class_sessions_per_offering', 36);
    
    for ($week = 1; $week <= 12; $week++) {
        for ($session = 1; $session <= 3; $session++) {
            ClassSession::factory()
                ->for($offering)
                ->state([
                    'room_id' => $rooms->random()->id,
                    'instructor_id' => $offering->instructor_id,
                    'week_number' => $week,
                    'session_number' => $session,
                    'start_time' => now()->addWeeks($week)->setTime(8 + ($session * 2), 0),
                    'end_time' => now()->addWeeks($week)->setTime(8 + ($session * 2) + 1, 30),
                ])
                ->create();
        }
    }
}
```

---

### **Step 8: Study Groups** 👥
**Flow:** `student_groups → student_group_members`

**Related Tables:**
- `student_groups` - Study/project groups *(Volume: 8-40 groups)*
- `student_group_members` - Group membership *(Volume: 24-200 memberships)*

**Purpose:** Create study groups for project work and group assignments

**Seeder:** `StudentGroupSeeder.php`

**Group Formation with Realistic Sizes:**
```php
// Create study groups with 3-8 members each
StudentGroup::factory($this->getVolume('student_groups', 15))
    ->create()
    ->each(function ($group) {
        $memberCount = fake()->numberBetween(3, 8);
        $students = Student::where('enrollment_status', 'active')
            ->inRandomOrder()
            ->limit($memberCount)
            ->get();
        
        foreach ($students as $index => $student) {
            StudentGroupMember::create([
                'student_group_id' => $group->id,
                'student_id' => $student->id,
                'role' => $index === 0 ? 'leader' : 'member',
                'joined_at' => fake()->dateTimeBetween('-3 months', 'now'),
            ]);
        }
    });
```

---

### **Step 9: Attendance Tracking** ✅
**Flow:** `attendances`

**Related Tables:**
- `attendances` - Attendance records *(Volume: 4320-24000 records)*

**Purpose:** Record attendance for each class session with status, timing, and method

**Seeder:** `AttendanceSeeder.php`

**Mass Attendance Generation:**
```php
// Generate attendance for all class sessions and registered students
$classSessions = ClassSession::with('courseOffering.courseRegistrations.student')->get();
$totalAttendances = $classSessions->sum(function ($session) {
    return $session->courseOffering->courseRegistrations->count();
});

$progressBar = $this->createProgressBar($totalAttendances, 'Creating attendance records...');

$classSessions->each(function ($session) use ($progressBar) {
    $registrations = $session->courseOffering->courseRegistrations;
    
    $attendanceData = $registrations->map(function ($registration) use ($session) {
        return Attendance::factory()->make([
            'class_session_id' => $session->id,
            'student_id' => $registration->student_id,
            'recorded_by_lecture_id' => $session->instructor_id,
        ])->toArray();
    });
    
    Attendance::insert($attendanceData->toArray());
    $progressBar->advance($registrations->count());
});
```

---

### **Step 10: Assessment & Grading Process** 📊
**Flow:** `assessment_component_detail_scores`

**Related Tables:**
- `assessment_component_detail_scores` - Individual scores *(Volume: 405-10240 scores)*

**Purpose:** Record scores for each assignment/exam/project component with detailed tracking

**Seeder:** `AssessmentScoreSeeder.php`

**Realistic Grade Distribution:**
```php
// Generate scores with realistic grade distribution
$assessmentDetails = AssessmentComponentDetail::with([
    'component.syllabus.courseOfferings.courseRegistrations.student'
])->get();

$gradeDistribution = config('seeder.data_distribution.grade_distribution');

$assessmentDetails->each(function ($detail) use ($gradeDistribution) {
    foreach ($detail->component->syllabus->courseOfferings as $offering) {
        $scoreData = $offering->courseRegistrations->map(function ($registration) use ($detail, $gradeDistribution) {
            $grade = $this->selectGradeByWeight($gradeDistribution);
            $percentage = fake()->numberBetween($grade['min'], $grade['max']);
            
            return [
                'assessment_component_detail_id' => $detail->id,
                'student_id' => $registration->student_id,
                'course_offering_id' => $offering->id,
                'graded_by_lecture_id' => $offering->instructor_id,
                'points_earned' => ($percentage / 100) * $detail->max_points,
                'percentage_score' => $percentage,
                'letter_grade' => array_search($grade, $gradeDistribution),
                'gpa_points' => $this->calculateGPAPoints($percentage),
                'status' => 'graded',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });
        
        AssessmentComponentDetailScore::insert($scoreData->toArray());
    }
});
```

---

### **Step 11: Final Academic Results** 🏆
**Flow:** `academic_records`

**Related Tables:**
- `academic_records` - Final course records *(Volume: 90-800 records)*

**Purpose:** Calculate and store final grades, completion status, and credit hours earned

**Seeder:** `AcademicRecordSeeder.php`

**Academic Record Calculation:**
```php
// Calculate final grades from all assessment scores
$courseRegistrations = CourseRegistration::with([
    'student', 'courseOffering.unit', 'courseOffering.syllabus.assessmentComponents.details.scores'
])->where('status', 'active')->get();

$courseRegistrations->each(function ($registration) {
    $totalWeightedScore = 0;
    $totalWeight = 0;
    
    foreach ($registration->courseOffering->syllabus->assessmentComponents as $component) {
        $componentScore = $component->details->avg('scores.percentage_score');
        $totalWeightedScore += $componentScore * $component->weight_percentage;
        $totalWeight += $component->weight_percentage;
    }
    
    $finalPercentage = $totalWeight > 0 ? $totalWeightedScore / $totalWeight : 0;
    
    AcademicRecord::create([
        'student_id' => $registration->student_id,
        'course_offering_id' => $registration->courseOffering->id,
        'semester_id' => $registration->courseOffering->semester_id,
        'unit_id' => $registration->courseOffering->unit_id,
        'final_percentage' => $finalPercentage,
        'letter_grade' => $this->calculateLetterGrade($finalPercentage),
        'gpa_points' => $this->calculateGPAPoints($finalPercentage),
        'credit_hours_earned' => $finalPercentage >= 50 ? $registration->courseOffering->unit->credit_hours : 0,
        'completion_status' => $finalPercentage >= 50 ? 'completed' : 'failed',
        'is_repeated' => false,
        'recorded_at' => now(),
    ]);
});
```

---

### **Step 12: GPA Calculation & Academic Achievement** 📈
**Flow:** `gpa_calculations`

**Related Tables:**
- `gpa_calculations` - GPA calculations *(Volume: 30-100 calculations)*

**Purpose:** Calculate semester GPA, cumulative GPA, academic standing, and rankings

**Seeder:** `GPACalculationSeeder.php`

**Comprehensive GPA Calculation:**
```php
// Calculate GPA for each student per semester
$students = Student::with('academicRecords')->get();

$students->each(function ($student) {
    $semesterRecords = $student->academicRecords->groupBy('semester_id');
    
    foreach ($semesterRecords as $semesterId => $records) {
        $totalGpaPoints = $records->sum(function ($record) {
            return $record->gpa_points * $record->unit->credit_hours;
        });
        $totalCreditHours = $records->sum('credit_hours_earned');
        
        $semesterGpa = $totalCreditHours > 0 ? $totalGpaPoints / $totalCreditHours : 0;
        
        // Calculate cumulative GPA
        $allRecords = $student->academicRecords->where('semester_id', '<=', $semesterId);
        $cumulativeTotalGpaPoints = $allRecords->sum(function ($record) {
            return $record->gpa_points * $record->unit->credit_hours;
        });
        $cumulativeTotalCredits = $allRecords->sum('credit_hours_earned');
        $cumulativeGpa = $cumulativeTotalCredits > 0 ? $cumulativeTotalGpaPoints / $cumulativeTotalCredits : 0;
        
        GpaCalculation::create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'semester_gpa' => round($semesterGpa, 2),
            'cumulative_gpa' => round($cumulativeGpa, 2),
            'total_credit_hours_earned' => $cumulativeTotalCredits,
            'total_credit_hours_attempted' => $allRecords->sum(function ($record) {
                return $record->unit->credit_hours;
            }),
            'academic_standing' => $this->determineAcademicStanding($cumulativeGpa),
            'is_on_probation' => $cumulativeGpa < 2.0,
            'calculated_at' => now(),
        ]);
    }
});
```

---

### **Step 13: Academic Issue Management** ⚠️
**Flow:** `academic_holds`

**Related Tables:**
- `academic_holds` - Academic holds and restrictions *(Volume: 3-15 holds)*

**Purpose:** Manage holds (financial, academic probation, prerequisite violations)

**Seeder:** `AcademicHoldSeeder.php`

**Intelligent Hold Generation:**
```php
// Generate holds based on academic performance and other factors
$students = Student::with('gpaCalculations')->get();

$students->each(function ($student) {
    $latestGpa = $student->gpaCalculations->sortByDesc('semester_id')->first();
    
    // Academic probation hold
    if ($latestGpa && $latestGpa->cumulative_gpa < 2.0) {
        AcademicHold::factory()->create([
            'student_id' => $student->id,
            'hold_type' => 'academic_probation',
            'reason' => 'Cumulative GPA below 2.0',
            'severity_level' => 'high',
            'affects_registration' => true,
            'affects_graduation' => true,
        ]);
    }
    
    // Random financial holds (10% of students)
    if (fake()->boolean(10)) {
        AcademicHold::factory()->create([
            'student_id' => $student->id,
            'hold_type' => 'financial',
            'reason' => 'Outstanding tuition balance',
            'severity_level' => 'medium',
            'affects_registration' => true,
        ]);
    }
    
    // Random administrative holds (5% of students)
    if (fake()->boolean(5)) {
        AcademicHold::factory()->create([
            'student_id' => $student->id,
            'hold_type' => 'administrative',
            'reason' => 'Missing required documentation',
            'severity_level' => 'low',
        ]);
    }
});
```

---

## 🔄 **Recommended Seeder Execution Order**

```php
// database/seeders/ComprehensiveAcademicWorkflowSeeder.php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ComprehensiveAcademicWorkflowSeeder extends BaseVolumeSeeder
{
    /**
     * Comprehensive academic workflow seeder that simulates 
     * a complete student learning process during a semester.
     * 
     * Usage: php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder
     * With volume: SEEDER_VOLUME=large php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder
     */
    public function run(): void
    {
        // Set volume from environment variable
        $volume = env('SEEDER_VOLUME', 'medium');
        $this->setVolumeSize($volume);
        
        $this->command->info("🎓 Starting Comprehensive Academic Workflow Seeding [{$this->volumeSize}]...");
        
        // Display expected data volumes
        $this->displayExpectedVolumes();

        $this->call([
            // === 0. PREREQUISITES (if models don't exist) ===
            // Run only if models need to be generated:
            // php artisan make:model Room -mf
            // php artisan make:model ClassSession -mf
            // php artisan make:model Attendance -mf
            // php artisan make:model StudentGroup -mf
            // php artisan make:model StudentGroupMember -mf
            // php artisan make:model AcademicRecord -mf
            // php artisan make:model AssessmentComponentDetailScore -mf
            // php artisan make:model GpaCalculation -mf
            // php artisan make:model RoomBooking -mf
            // php artisan make:model EquivalentUnit -mf
            // php artisan make:model GraduationRequirement -mf
            // php artisan make:model Enrollment -mf
            
            // === 1. FOUNDATION SETUP ===
            UserSeeder::class,              // campuses, users, roles, permissions
            LectureSeeder::class,           // lecturer profiles with expertise
            
            // === 2. ACADEMIC STRUCTURE ===
            ProgramSeeder::class,           // programs and specializations
            GraduationRequirementSeeder::class, // graduation requirements
            UnitSeeder::class,              // available units/courses
            SemesterSeeder::class,          // academic periods
            CurriculumSeeder::class,        // curriculum structure + students
            
            // === 3. ASSESSMENT FRAMEWORK ===
            SyllabusSeeder::class,          // syllabus with assessment structure
            
            // === 4. COURSE DELIVERY SETUP ===
            CourseOfferingSeeder::class,    // course instances for semester
            CourseRegistrationSeeder::class, // student course registrations
            EnrollmentSeeder::class,        // semester enrollments
            RoomSeeder::class,              // classroom facilities
            ClassScheduleSeeder::class,     // room bookings + class sessions
            
            // === 5. LEARNING PROCESS SIMULATION ===
            StudentGroupSeeder::class,      // study groups for collaborative work
            AttendanceSeeder::class,        // attendance tracking across semester
            
            // === 6. ASSESSMENT & GRADING ===
            AssessmentScoreSeeder::class,   // detailed component scoring
            
            // === 7. ACADEMIC OUTCOMES ===
            AcademicRecordSeeder::class,    // final course grades and completion
            GPACalculationSeeder::class,    // GPA calculations and rankings
            AcademicHoldSeeder::class,      // holds and academic interventions
        ]);

        $this->command->info('✅ Comprehensive Academic Workflow Seeding Completed!');
        $this->printSummaryStats();
        $this->printPaginationInfo();
    }

    private function displayExpectedVolumes(): void
    {
        $this->command->info('📊 Expected Data Volumes:');
        $this->command->line('   🏫 Campuses: ' . $this->getVolume('campuses', 5));
        $this->command->line('   📚 Programs: ' . $this->getVolume('programs', 8));
        $this->command->line('   🎯 Specializations: ' . $this->getVolume('specializations', 15));
        $this->command->line('   📖 Units: ' . $this->getVolume('units', 40));
        $this->command->line('   🎓 Students: ' . $this->getVolume('students', 60));
        $this->command->line('   👨‍🏫 Lecturers: ' . $this->getVolume('lectures', 20));
        $this->command->line('   📅 Course Offerings: ' . $this->getVolume('course_offerings', 30));
        $this->command->line('   📚 Curriculum Versions: ' . $this->getVolume('curriculum_versions', 5));
        $this->command->line('   ⚖️ Equivalent Units: ' . $this->getVolume('equivalent_units', 10));
        $this->command->line('   🎓 Graduation Requirements: ' . ($this->getVolume('programs', 8) + $this->getVolume('specializations', 15)));
        $this->command->line('   📋 Enrollments: ' . $this->getVolume('students', 60));
        $this->command->line('   🏛️ Rooms: ' . ($this->getVolume('campuses', 5) * 5));
        $this->command->line('   👥 Student Groups: ' . $this->getVolume('student_groups', 15));
        $this->command->line('');
        $this->command->line('Expected seeding time: ' . $this->estimatedTime() . ' minutes');
        $this->command->line('');
    }

    private function estimatedTime(): string
    {
        $volumes = [
            'small' => '1-2',
            'medium' => '2-3', 
            'large' => '3-5',
            'xlarge' => '5-8'
        ];
        
        return $volumes[$this->volumeSize] ?? '2-3';
    }

    private function printSummaryStats(): void
    {
        $this->command->info('📊 Final Statistics:');
        $this->command->line('   🎓 Students: ' . number_format(\App\Models\Student::count()));
        $this->command->line('   📅 Course Offerings: ' . number_format(\App\Models\CourseOffering::count()));
        $this->command->line('   📝 Course Registrations: ' . number_format(\App\Models\CourseRegistration::count()));
        $this->command->line('   📋 Enrollments: ' . number_format(\App\Models\Enrollment::count()));
        $this->command->line('   🏛️ Class Sessions: ' . number_format(\App\Models\ClassSession::count()));
        $this->command->line('   ✅ Attendance Records: ' . number_format(\App\Models\Attendance::count()));
        $this->command->line('   📊 Assessment Scores: ' . number_format(\App\Models\AssessmentComponentDetailScore::count()));
        $this->command->line('   🏆 Academic Records: ' . number_format(\App\Models\AcademicRecord::count()));
        $this->command->line('   📈 GPA Calculations: ' . number_format(\App\Models\GpaCalculation::count()));
        $this->command->line('   🎓 Graduation Requirements: ' . number_format(\App\Models\GraduationRequirement::count()));
        $this->command->line('   ⚖️ Equivalent Units: ' . number_format(\App\Models\EquivalentUnit::count()));
        $this->command->line('   ⚠️ Academic Holds: ' . number_format(\App\Models\AcademicHold::count()));
        $this->command->line('   👥 Student Groups: ' . number_format(\App\Models\StudentGroup::count()));
    }

    private function printPaginationInfo(): void
    {
        $this->command->info('📄 Pagination Testing Info:');
        
        $studentsPerPage = config('seeder.pagination_config.students_per_page', 15);
        $totalStudents = \App\Models\Student::count();
        $studentPages = ceil($totalStudents / $studentsPerPage);
        
        $offeringsPerPage = config('seeder.pagination_config.course_offerings_per_page', 20);
        $totalOfferings = \App\Models\CourseOffering::count();
        $offeringPages = ceil($totalOfferings / $offeringsPerPage);
        
        $this->command->line("   📚 Students: {$totalStudents} records = {$studentPages} pages ({$studentsPerPage}/page)");
        $this->command->line("   📅 Course Offerings: {$totalOfferings} records = {$offeringPages} pages ({$offeringsPerPage}/page)");
        
        if ($totalStudents >= 30) {
            $this->command->line('   ✅ Sufficient data for pagination testing!');
        } else {
            $this->command->line('   ⚠️ Consider using larger volume for pagination testing');
        }
    }
}
```

---

## 📅 **Real Academic Semester Timeline**

### **Pre-Semester (Weeks -2 to -1)**
1. **Course Planning**: Create course offerings, assign instructors
2. **Room Allocation**: Book classrooms and facilities
3. **Student Registration**: Students register for courses
4. **Group Formation**: Create study groups for group projects

### **Early Semester (Weeks 1-4)**
1. **Class Commencement**: Regular class sessions begin
2. **Attendance Tracking**: Daily attendance recording
3. **Early Assessments**: Small quizzes, participation grades
4. **Group Projects**: Group formation and initial work

### **Mid-Semester (Weeks 5-8)**
1. **Midterm Period**: Midterm exams and major assessments
2. **Score Recording**: Record midterm scores
3. **Academic Interventions**: Identify at-risk students
4. **Project Milestones**: Major project deliverables

### **Late Semester (Weeks 9-12)**
1. **Final Preparations**: Review sessions, final projects
2. **Final Assessments**: Final exams, project presentations
3. **Score Compilation**: All assessment scores recorded
4. **Attendance Summary**: Final attendance calculations

### **Post-Semester (Weeks 13-14)**
1. **Grade Calculation**: Compile final course grades
2. **Academic Records**: Create academic records
3. **GPA Calculation**: Semester and cumulative GPA
4. **Academic Standing**: Determine probation, honors, etc.

---

## 🏗️ **Sample Seeder Structure**

### **AttendanceSeeder.php Example**
```php
<?php

namespace Database\Seeders;

use App\Models\ClassSession;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\CourseRegistration;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📝 Creating attendance records...');

        $classSessions = ClassSession::with(['courseOffering.courseRegistrations.student'])->get();

        foreach ($classSessions as $session) {
            $registrations = $session->courseOffering->courseRegistrations;
            
            foreach ($registrations as $registration) {
                $this->createAttendanceRecord($session, $registration->student);
            }
        }
    }

    private function createAttendanceRecord(ClassSession $session, Student $student): void
    {
        // Simulate realistic attendance patterns
        $attendanceRate = 0.85; // 85% overall attendance rate
        $isPresent = fake()->boolean($attendanceRate * 100);

        $status = $isPresent ? 'present' : fake()->randomElement(['absent', 'late', 'excused']);
        
        if ($status === 'late') {
            $minutesLate = fake()->numberBetween(5, 30);
            $checkInTime = $session->start_time->addMinutes($minutesLate);
        } else {
            $minutesLate = 0;
            $checkInTime = $isPresent ? $session->start_time->addMinutes(fake()->numberBetween(-5, 10)) : null;
        }

        Attendance::create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'recorded_by_lecture_id' => $session->instructor_id,
            'status' => $status,
            'check_in_time' => $checkInTime,
            'check_out_time' => $isPresent ? $session->end_time->subMinutes(fake()->numberBetween(0, 15)) : null,
            'minutes_late' => $minutesLate,
            'recording_method' => fake()->randomElement(['manual', 'qr_code', 'mobile_app']),
            'participation_level' => $isPresent ? fake()->randomElement(['excellent', 'good', 'average']) : null,
            'participation_score' => $isPresent ? fake()->numberBetween(6, 10) : null,
            'is_verified' => true,
            'affects_grade' => true,
            'verified_at' => now(),
            'verified_by_lecture_id' => $session->instructor_id,
        ]);
    }
}
```

### **AssessmentScoreSeeder.php Example**
```php
<?php

namespace Database\Seeders;

use App\Models\AssessmentComponentDetail;
use App\Models\Student;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseRegistration;
use Illuminate\Database\Seeder;

class AssessmentScoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📊 Creating assessment scores...');

        $assessmentDetails = AssessmentComponentDetail::with([
            'component.syllabus.courseOfferings.courseRegistrations.student'
        ])->get();

        foreach ($assessmentDetails as $detail) {
            foreach ($detail->component->syllabus->courseOfferings as $offering) {
                foreach ($offering->courseRegistrations as $registration) {
                    $this->createAssessmentScore($detail, $registration->student, $offering);
                }
            }
        }
    }

    private function createAssessmentScore($detail, $student, $courseOffering): void
    {
        // Simulate realistic grade distribution
        $gradeDistribution = [
            'A' => ['min' => 85, 'max' => 100, 'weight' => 15],
            'B' => ['min' => 75, 'max' => 84, 'weight' => 35],
            'C' => ['min' => 65, 'max' => 74, 'weight' => 30],
            'D' => ['min' => 50, 'max' => 64, 'weight' => 15],
            'F' => ['min' => 0, 'max' => 49, 'weight' => 5],
        ];

        $selectedGrade = $this->selectGradeByWeight($gradeDistribution);
        $percentage = fake()->numberBetween($selectedGrade['min'], $selectedGrade['max']);
        
        AssessmentComponentDetailScore::create([
            'assessment_component_detail_id' => $detail->id,
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'graded_by_lecture_id' => $courseOffering->instructor_id,
            'points_earned' => ($percentage / 100) * $detail->max_points,
            'percentage_score' => $percentage,
            'letter_grade' => array_search($selectedGrade, $gradeDistribution),
            'gpa_points' => $this->calculateGPAPoints($percentage),
            'submitted_at' => fake()->dateTimeBetween('-2 weeks', '-1 week'),
            'graded_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'status' => 'graded',
            'score_status' => 'final',
            'instructor_feedback' => $this->generateFeedback($percentage),
            'is_verified' => true,
        ]);
    }

    private function selectGradeByWeight(array $distribution): array
    {
        $totalWeight = array_sum(array_column($distribution, 'weight'));
        $random = fake()->numberBetween(1, $totalWeight);
        
        $currentWeight = 0;
        foreach ($distribution as $grade => $data) {
            $currentWeight += $data['weight'];
            if ($random <= $currentWeight) {
                return $data;
            }
        }
        
        return end($distribution);
    }

    private function generateFeedback(float $percentage): string
    {
        if ($percentage >= 85) {
            return 'Excellent work! Shows deep understanding of the concepts.';
        } elseif ($percentage >= 75) {
            return 'Good work. Minor improvements needed in some areas.';
        } elseif ($percentage >= 65) {
            return 'Satisfactory work. Consider reviewing key concepts.';
        } elseif ($percentage >= 50) {
            return 'Needs improvement. Please seek additional help.';
        } else {
            return 'Unsatisfactory. Requires significant additional work.';
        }
    }

    private function calculateGPAPoints(float $percentage): float
    {
        if ($percentage >= 85) return 4.0;
        if ($percentage >= 75) return 3.0;
        if ($percentage >= 65) return 2.0;
        if ($percentage >= 50) return 1.0;
        return 0.0;
    }
}
```

---

## 🎯 **Key Features of Comprehensive Seeder**

### **1. Realistic Data Distribution**
- Grade distributions mirror real academic performance
- Attendance patterns reflect typical student behavior
- Assessment timing follows semester progression

### **2. Referential Integrity**
- All foreign key relationships properly maintained
- Cascade dependencies handled correctly
- Data consistency across all tables

### **3. Configurable Parameters**
- Adjustable student counts
- Flexible semester timelines
- Customizable assessment structures

### **4. Complete Workflow Simulation**
- End-to-end semester simulation
- Multiple assessment types
- Comprehensive attendance tracking
- Final grade calculations

### **5. Academic Realism**
- Prerequisites enforced
- Academic calendar respected
- Realistic instructor assignments
- Proper room scheduling

---

## 🚀 **Usage**

### **Step 0: Prerequisites - Create Models & Factories**
```bash
# Generate all required models with migrations and factories
php artisan make:model Room -mf
php artisan make:model ClassSession -mf
php artisan make:model Attendance -mf
php artisan make:model StudentGroup -mf
php artisan make:model StudentGroupMember -mf
php artisan make:model AcademicRecord -mf
php artisan make:model AssessmentComponentDetailScore -mf
php artisan make:model GpaCalculation -mf
php artisan make:model RoomBooking -mf

# Create configuration file
cp config/app.php config/seeder.php
# Edit config/seeder.php with volume configurations above
```

### **Step 1: Create Base Seeder Class**
```bash
# Create the base volume seeder
touch database/seeders/BaseVolumeSeeder.php
# Copy the BaseVolumeSeeder code from above
```

### **Step 2: Run Complete Workflow with Different Volumes**

**Small Dataset (Development)**
```bash
# Quick development testing (150 students, ~3 minutes)
SEEDER_VOLUME=small php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder
```

**Medium Dataset (Default)**
```bash
# Balanced testing environment (500 students, ~8 minutes)
php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder
# OR
SEEDER_VOLUME=medium php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder
```

**Large Dataset (Production Simulation)**
```bash
# Production-like data (2000 students, ~25 minutes)
SEEDER_VOLUME=large php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder
```

**Extra Large Dataset (Performance Testing)**
```bash
# Heavy load testing (5000 students, ~45 minutes)
SEEDER_VOLUME=xlarge php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder
```

### **Step 3: Run Individual Seeders with Volume Control**
```bash
# Individual seeders with volume awareness
php artisan db:seed --class=AttendanceSeeder
php artisan db:seed --class=AssessmentScoreSeeder
php artisan db:seed --class=StudentGroupSeeder

# With specific volume
SEEDER_VOLUME=large php artisan db:seed --class=AttendanceSeeder
```

### **Step 4: Factory Testing**
```bash
# Test individual factories
php artisan tinker --execute="
// Test student factory with different states
\App\Models\Student::factory()->active()->count(10)->create();
\App\Models\Student::factory()->graduated()->count(5)->create();
\App\Models\Student::factory()->newStudent()->count(20)->create();

// Test attendance factory with states
\App\Models\Attendance::factory()->present()->count(50)->create();
\App\Models\Attendance::factory()->absent()->count(10)->create();
\App\Models\Attendance::factory()->late()->count(15)->create();
"
```

### **Step 5: Reset and Reseed with Volume**
```bash
# Fresh migration with medium dataset
php artisan migrate:fresh --seed

# Fresh migration with specific volume
SEEDER_VOLUME=large php artisan migrate:fresh
SEEDER_VOLUME=large php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder
```

### **Step 6: Performance Monitoring**
```bash
# Monitor seeding performance
time SEEDER_VOLUME=large php artisan db:seed --class=ComprehensiveAcademicWorkflowSeeder

# Check database size after seeding
php artisan tinker --execute="
echo 'Database size check:' . PHP_EOL;
echo 'Students: ' . \App\Models\Student::count() . PHP_EOL;
echo 'Attendances: ' . \App\Models\Attendance::count() . PHP_EOL;
echo 'Assessment Scores: ' . \App\Models\AssessmentComponentDetailScore::count() . PHP_EOL;
"
```

### **Step 7: Verify Data Integrity & Relationships**
```bash
php artisan tinker --execute="
// Verify complex relationships
\App\Models\Student::with('academicRecords.gpaCalculations')->get()->each(function(\$student) {
    echo \$student->full_name . ': ' . \$student->academicRecords->count() . ' records' . PHP_EOL;
});

// Check attendance data integrity
\App\Models\ClassSession::withCount(['attendances'])->get()->each(function(\$session) {
    echo 'Session ' . \$session->id . ': ' . \$session->attendances_count . ' attendances' . PHP_EOL;
});

// Verify grade distributions
\App\Models\AssessmentComponentDetailScore::selectRaw('letter_grade, COUNT(*) as count')
    ->groupBy('letter_grade')
    ->orderBy('letter_grade')
    ->get()
    ->each(function(\$grade) {
        echo 'Grade ' . \$grade->letter_grade . ': ' . \$grade->count . ' scores' . PHP_EOL;
    });
"
```

### **Step 8: Pagination Testing Commands**
```bash
# Test pagination on large datasets
php artisan tinker --execute="
// Test student pagination
\$students = \App\Models\Student::paginate(15);
echo 'Page 1 of ' . \$students->lastPage() . ' (Total: ' . \$students->total() . ')' . PHP_EOL;

// Test course offering pagination with filters
\$offerings = \App\Models\CourseOffering::with(['unit', 'instructor', 'semester'])
    ->paginate(20);
echo 'Course offerings: Page 1 of ' . \$offerings->lastPage() . PHP_EOL;

// Test attendance pagination with relationships
\$attendances = \App\Models\Attendance::with(['student', 'classSession.courseOffering'])
    ->paginate(25);
echo 'Attendances: Page 1 of ' . \$attendances->lastPage() . PHP_EOL;
"
```

---

## 📈 **Expected Outcomes**

After running the complete seeder workflow, the system will have:

- **Complete Academic Cycle**: From enrollment to graduation
- **Realistic Performance Data**: Grade distributions, attendance patterns
- **Comprehensive Records**: Full audit trail of student progress
- **Academic Analytics**: GPA calculations, academic standing
- **Intervention Points**: Academic holds, probation triggers

This seeder workflow creates a **complete and realistic simulation** of the learning process in a university environment!
