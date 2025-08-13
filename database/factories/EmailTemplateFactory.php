<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = array_keys(EmailTemplate::getTypes());
        $type = $this->faker->randomElement($types);

        return [
            'name' => $this->faker->unique()->slug(2) . '_template',
            'type' => $type,
            'subject' => $this->faker->sentence() . ' {{name}}',
            'html_content' => '<h1>' . $this->faker->sentence() . '</h1><p>Hello {{name}}, your email is {{email}}</p>',
            'text_content' => $this->faker->paragraph() . ' Hello {{name}}, your email is {{email}}',
            'variables' => ['name', 'email'],
            'is_active' => true,
            'version' => 1,
            'parent_id' => null,
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the template is a specific version.
     */
    public function version(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => $version,
        ]);
    }

    /**
     * Indicate that the template has a parent.
     */
    public function withParent(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Create a welcome email template.
     */
    public function welcome(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'welcome_email',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Welcome to {{institution_name}}, {{name}}!',
            'html_content' => '
                <h1>Welcome to {{institution_name}}!</h1>
                <p>Dear {{name}},</p>
                <p>We are excited to welcome you to our academic community. Your student ID is {{student_id}}.</p>
                <p>You can access your account at: {{login_url}}</p>
                <p>Best regards,<br>{{institution_name}} Team</p>
            ',
            'text_content' => 'Welcome to {{institution_name}}! Dear {{name}}, we are excited to welcome you to our academic community. Your student ID is {{student_id}}. You can access your account at: {{login_url}}. Best regards, {{institution_name}} Team',
            'variables' => ['name', 'institution_name', 'student_id', 'login_url'],
        ]);
    }

    /**
     * Create a grade notification template.
     */
    public function gradeNotification(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'grade_notification',
            'type' => EmailTemplate::TYPE_GRADE_NOTIFICATION,
            'subject' => 'Grades Published for {{course_name}}',
            'html_content' => '
                <h1>Grades Published</h1>
                <p>Dear {{student_name}},</p>
                <p>Your grades for <strong>{{course_name}}</strong> have been published.</p>
                <p>Grade: <strong>{{grade}}</strong></p>
                <p>You can view detailed results in your student portal.</p>
                <p>Best regards,<br>Academic Office</p>
            ',
            'text_content' => 'Dear {{student_name}}, your grades for {{course_name}} have been published. Grade: {{grade}}. You can view detailed results in your student portal. Best regards, Academic Office',
            'variables' => ['student_name', 'course_name', 'grade'],
        ]);
    }

    /**
     * Create a course registration template.
     */
    public function courseRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'course_registration',
            'type' => EmailTemplate::TYPE_COURSE_REGISTRATION,
            'subject' => 'Course Registration Confirmation - {{course_name}}',
            'html_content' => '
                <h1>Course Registration Confirmed</h1>
                <p>Dear {{student_name}},</p>
                <p>Your registration for <strong>{{course_name}}</strong> has been confirmed.</p>
                <p>Course Details:</p>
                <ul>
                    <li>Course Code: {{course_code}}</li>
                    <li>Semester: {{semester}}</li>
                    <li>Credits: {{credits}}</li>
                </ul>
                <p>Classes begin on {{start_date}}.</p>
                <p>Best regards,<br>Registration Office</p>
            ',
            'text_content' => 'Dear {{student_name}}, your registration for {{course_name}} has been confirmed. Course Code: {{course_code}}, Semester: {{semester}}, Credits: {{credits}}. Classes begin on {{start_date}}. Best regards, Registration Office',
            'variables' => ['student_name', 'course_name', 'course_code', 'semester', 'credits', 'start_date'],
        ]);
    }

    /**
     * Create an academic hold template.
     */
    public function academicHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'academic_hold',
            'type' => EmailTemplate::TYPE_ACADEMIC_HOLD,
            'subject' => 'Academic Hold Placed - Action Required',
            'html_content' => '
                <h1>Academic Hold Notice</h1>
                <p>Dear {{student_name}},</p>
                <p>An academic hold has been placed on your account.</p>
                <p><strong>Hold Type:</strong> {{hold_type}}</p>
                <p><strong>Reason:</strong> {{hold_reason}}</p>
                <p>Please contact the {{contact_office}} at {{contact_email}} to resolve this hold.</p>
                <p>This hold may prevent registration for future courses.</p>
                <p>Best regards,<br>Academic Office</p>
            ',
            'text_content' => 'Dear {{student_name}}, an academic hold has been placed on your account. Hold Type: {{hold_type}}, Reason: {{hold_reason}}. Please contact the {{contact_office}} at {{contact_email}} to resolve this hold. This hold may prevent registration for future courses. Best regards, Academic Office',
            'variables' => ['student_name', 'hold_type', 'hold_reason', 'contact_office', 'contact_email'],
        ]);
    }

    /**
     * Create a reminder template.
     */
    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'assessment_reminder',
            'type' => EmailTemplate::TYPE_REMINDER,
            'subject' => 'Reminder: {{assessment_name}} Due {{due_date}}',
            'html_content' => '
                <h1>Assessment Reminder</h1>
                <p>Dear {{student_name}},</p>
                <p>This is a reminder that your assessment <strong>{{assessment_name}}</strong> for {{course_name}} is due on {{due_date}}.</p>
                <p>Please ensure you submit your work before the deadline.</p>
                <p>If you have any questions, please contact your lecturer.</p>
                <p>Best regards,<br>Academic Office</p>
            ',
            'text_content' => 'Dear {{student_name}}, this is a reminder that your assessment {{assessment_name}} for {{course_name}} is due on {{due_date}}. Please ensure you submit your work before the deadline. If you have any questions, please contact your lecturer. Best regards, Academic Office',
            'variables' => ['student_name', 'assessment_name', 'course_name', 'due_date'],
        ]);
    }
}
