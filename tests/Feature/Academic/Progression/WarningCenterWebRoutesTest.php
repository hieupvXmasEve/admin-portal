<?php

declare(strict_types=1);

use App\Models\AcademicWarningSetting;
use App\Models\Campus;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentWarningLog;
use App\Models\User;
use App\Modules\Academic\Support\WarningDedupe;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for the warning centre listing, its settings page,
 * and manual academic-standing sending before those classes move into owned
 * Academic Progression.
 */
beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
        Authorize::class,
    ]);

    // The controller and the settings FormRequest both gate on
    // `send_manual_notification`, which `withoutMiddleware(Authorize)` does not
    // bypass because it is an in-body `abort_unless` / `authorize()` check.
    Gate::before(fn (): AccessResponse => AccessResponse::allow());

    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    actingAs(User::factory()->create());

    $this->semester = Semester::factory()->create([
        'code' => '2026SP',
        'name' => 'Spring 2026',
        'is_active' => false,
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function warningCenterGpa(array $attributes): GpaCalculation
{
    return GpaCalculation::query()->create(array_merge([
        'semester_gpa' => 40,
        'cumulative_gpa' => 40,
        'semester_quality_points' => 0,
        'cumulative_quality_points' => 0,
        'semester_credit_points' => 0,
        'cumulative_credit_points' => 0,
        'semester_credit_points_earned' => 0,
        'cumulative_credit_points_earned' => 0,
    ], $attributes));
}

function warningCenterStudent(Campus $campus, Semester $semester): Student
{
    return Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => (int) $semester->start_date->format('Y'),
        'intake_semester_id' => $semester->id,
    ]);
}

describe('warning centre index', function (): void {
    it('lists current warning-standing GPA calculations below the 50 cutoff', function (): void {
        $student = warningCenterStudent($this->campus, $this->semester);

        $calculation = warningCenterGpa([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'is_current' => true,
            'academic_standing' => 'warning',
            'cumulative_gpa' => 42.5,
        ]);

        get(route('academic.warnings.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Academic/Warnings/Index')
                ->where('academic_warnings.data.0.id', $calculation->id)
                ->where('academic_warnings.data.0.student.id', $student->id)
                ->where('academic_warnings.data.0.current_cumulative_gpa', 42.5)
                ->where('academic_warnings.data.0.warning_sent_at', null)
                ->has('settings')
                ->where('attendance_subjects', [])
            );
    });

    it('omits calculations that are not in warning standing', function (): void {
        $student = warningCenterStudent($this->campus, $this->semester);

        warningCenterGpa([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'is_current' => true,
            'academic_standing' => 'good',
            'cumulative_gpa' => 42.5,
        ]);

        get(route('academic.warnings.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Academic/Warnings/Index')
                ->has('academic_warnings.data', 0)
            );
    });

    it('reports the send state from a matching warning log', function (): void {
        $student = warningCenterStudent($this->campus, $this->semester);

        $calculation = warningCenterGpa([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'is_current' => true,
            'academic_standing' => 'warning',
            'cumulative_gpa' => 30,
        ]);

        StudentWarningLog::query()->create([
            'student_id' => $student->id,
            'campus_id' => $this->campus->id,
            'warning_type' => StudentWarningLog::TYPE_ACADEMIC_STANDING,
            'dedupe_key' => WarningDedupe::academicStanding($student->id, $calculation->id),
            'status' => 'sent',
            'sent_at' => now(),
            'message_title' => 'Academic warning',
            'message_body' => 'Body',
            'channels' => ['realtime'],
        ]);

        get(route('academic.warnings.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('academic_warnings.data.0.warning_status', 'sent')
            );
    });
});

describe('warning centre settings', function (): void {
    it('renders the campus settings payload', function (): void {
        get(route('academic.warnings.settings'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Academic/Warnings/Settings')
                ->has('settings.id')
                ->has('settings.attendance_warning_ratio')
                ->has('settings.channels')
            );
    });

    it('persists updated settings and redirects back to the settings page', function (): void {
        put(route('academic.warnings.settings.update'), [
            'attendance_warning_ratio' => 0.6,
            'channels' => ['realtime', 'email'],
            'academic_warning_title' => 'Academic warning',
            'academic_warning_body' => 'Academic body',
            'attendance_warning_title' => 'Attendance warning',
            'attendance_warning_body' => 'Attendance body',
            'attendance_exceeded_title' => 'Attendance exceeded',
            'attendance_exceeded_body' => 'Exceeded body',
        ])->assertRedirect(route('academic.warnings.settings'));

        expect((float) AcademicWarningSetting::forCampus($this->campus->id)->attendance_warning_ratio)
            ->toBe(0.6);
    });

    it('rejects a settings update with an out-of-range ratio', function (): void {
        put(route('academic.warnings.settings.update'), [
            'attendance_warning_ratio' => 2,
            'channels' => ['realtime'],
            'academic_warning_title' => 'Academic warning',
            'academic_warning_body' => 'Academic body',
            'attendance_warning_title' => 'Attendance warning',
            'attendance_warning_body' => 'Attendance body',
            'attendance_exceeded_title' => 'Attendance exceeded',
            'attendance_exceeded_body' => 'Exceeded body',
        ])->assertSessionHasErrors('attendance_warning_ratio');
    });
});

describe('manual academic-standing send', function (): void {
    it('records a warning log for the selected calculation', function (): void {
        $student = warningCenterStudent($this->campus, $this->semester);

        $calculation = warningCenterGpa([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'is_current' => true,
            'academic_standing' => 'warning',
            'cumulative_gpa' => 20,
        ]);

        post(route('academic.warnings.academic-standing.send', $calculation))
            ->assertRedirect();

        expect(StudentWarningLog::query()->where('student_id', $student->id)->count())->toBe(1);
    });

    it('forbids sending for a student outside the selected campus', function (): void {
        $otherCampus = Campus::factory()->create();
        $student = warningCenterStudent($otherCampus, $this->semester);

        $calculation = warningCenterGpa([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'is_current' => true,
            'academic_standing' => 'warning',
            'cumulative_gpa' => 20,
        ]);

        post(route('academic.warnings.academic-standing.send', $calculation))
            ->assertForbidden();
    });
});
