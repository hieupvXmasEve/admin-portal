<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Welcome Email',
                'type' => EmailTemplate::TYPE_WELCOME,
                'subject' => 'Welcome to {{system_name}} - {{course_name}}',
                'html_content' => $this->getWelcomeEmailHtml(),
                'text_content' => $this->getWelcomeEmailText(),
                'description' => 'Sent to new students upon enrollment confirmation',
                'is_active' => true,
                'version' => 1,
            ],
            [
                'name' => 'Grade Notification',
                'type' => EmailTemplate::TYPE_GRADE_NOTIFICATION,
                'subject' => 'Grade Posted: {{assessment_name}} - {{course_name}}',
                'html_content' => $this->getGradeNotificationHtml(),
                'text_content' => $this->getGradeNotificationText(),
                'description' => 'Sent to students when grades are published',
                'is_active' => true,
                'version' => 1,
            ],
            [
                'name' => 'Course Registration Confirmation',
                'type' => EmailTemplate::TYPE_COURSE_REGISTRATION,
                'subject' => 'Course Registration Confirmed: {{course_name}}',
                'html_content' => $this->getCourseRegistrationHtml(),
                'text_content' => $this->getCourseRegistrationText(),
                'description' => 'Sent upon successful course registration',
                'is_active' => true,
                'version' => 1,
            ],
            [
                'name' => 'Academic Hold Notification',
                'type' => EmailTemplate::TYPE_ACADEMIC_HOLD,
                'subject' => 'Important: Academic Hold Placed on Your Account',
                'html_content' => $this->getAcademicHoldHtml(),
                'text_content' => $this->getAcademicHoldText(),
                'description' => 'Sent when an academic hold is placed on student account',
                'is_active' => true,
                'version' => 1,
            ],
            [
                'name' => 'Assessment Deadline Reminder',
                'type' => EmailTemplate::TYPE_ASSESSMENT_DEADLINE,
                'subject' => 'Reminder: {{assessment_name}} Due {{deadline}}',
                'html_content' => $this->getAssessmentDeadlineHtml(),
                'text_content' => $this->getAssessmentDeadlineText(),
                'description' => 'Sent as reminder for upcoming assessment deadlines',
                'is_active' => true,
                'version' => 1,
            ],
            [
                'name' => 'System Announcement',
                'type' => EmailTemplate::TYPE_SYSTEM_ANNOUNCEMENT,
                'subject' => '{{announcement_title}}',
                'html_content' => $this->getSystemAnnouncementHtml(),
                'text_content' => $this->getSystemAnnouncementText(),
                'description' => 'Used for important system-wide announcements',
                'is_active' => true,
                'version' => 1,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['name' => $template['name'], 'version' => $template['version']],
                $template
            );
        }
    }

    private function getWelcomeEmailHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to {{system_name}}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
            Welcome to {{system_name}}!
        </h1>
        
        <p>Dear {{user_name}},</p>
        
        <p>Welcome to <strong>{{course_name}}</strong> for the {{semester}} semester!</p>
        
        <p>Your enrollment has been confirmed, and you now have access to all course materials and resources.</p>
        
        <div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #3498db; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #2c3e50;">Getting Started</h3>
            <ul>
                <li>Access your course materials at <a href="{{login_url}}">{{login_url}}</a></li>
                <li>Review the course syllabus and schedule</li>
                <li>Connect with your instructors and classmates</li>
                <li>Set up your student profile</li>
            </ul>
        </div>
        
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
        
        <p>We wish you a successful semester!</p>
        
        <p>Best regards,<br>
        The {{system_name}} Team</p>
        
        <hr style="margin-top: 30px; border: none; border-top: 1px solid #ddd;">
        <p style="font-size: 12px; color: #666; text-align: center;">
            This is an automated message from {{system_name}}. Please do not reply to this email.
        </p>
    </div>
</body>
</html>
HTML;
    }

    private function getWelcomeEmailText(): string
    {
        return <<<TEXT
Welcome to {{system_name}}!

Dear {{user_name}},

Welcome to {{course_name}} for the {{semester}} semester!

Your enrollment has been confirmed, and you now have access to all course materials and resources.

Getting Started:
- Access your course materials at {{login_url}}
- Review the course syllabus and schedule
- Connect with your instructors and classmates
- Set up your student profile

If you have any questions or need assistance, please don't hesitate to contact our support team.

We wish you a successful semester!

Best regards,
The {{system_name}} Team

---
This is an automated message from {{system_name}}. Please do not reply to this email.
TEXT;
    }

    private function getGradeNotificationHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Grade Posted</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50; border-bottom: 2px solid #27ae60; padding-bottom: 10px;">
            Grade Posted
        </h1>
        
        <p>Dear {{student_name}},</p>
        
        <p>Your grade for <strong>{{assessment_name}}</strong> in <strong>{{course_name}}</strong> has been posted.</p>
        
        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h2 style="color: #27ae60; margin-top: 0;">Grade Details</h2>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0;"><strong>Assessment:</strong></td>
                    <td>{{assessment_name}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Course:</strong></td>
                    <td>{{course_name}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Grade:</strong></td>
                    <td style="font-size: 18px; color: #27ae60;"><strong>{{grade}}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Points:</strong></td>
                    <td>{{total_points}}</td>
                </tr>
            </table>
        </div>
        
        <p>You can view detailed feedback and comments by logging into your student portal.</p>
        
        <p>If you have questions about your grade, please contact your instructor.</p>
        
        <p>Best regards,<br>
        The {{system_name}} Team</p>
    </div>
</body>
</html>
HTML;
    }

    private function getGradeNotificationText(): string
    {
        return <<<TEXT
Grade Posted

Dear {{student_name}},

Your grade for {{assessment_name}} in {{course_name}} has been posted.

Grade Details:
- Assessment: {{assessment_name}}
- Course: {{course_name}}
- Grade: {{grade}}
- Points: {{total_points}}

You can view detailed feedback and comments by logging into your student portal.

If you have questions about your grade, please contact your instructor.

Best regards,
The {{system_name}} Team
TEXT;
    }

    private function getCourseRegistrationHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Course Registration Confirmed</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
            Course Registration Confirmed
        </h1>
        
        <p>Dear {{student_name}},</p>
        
        <p>Your registration for the following course has been confirmed:</p>
        
        <div style="background-color: #e8f4fd; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: #2c3e50; margin-top: 0;">Course Details</h3>
            <p><strong>Course Name:</strong> {{course_name}}<br>
            <strong>Course Code:</strong> {{course_code}}<br>
            <strong>Semester:</strong> {{semester}}<br>
            <strong>Registration Date:</strong> {{registration_date}}</p>
        </div>
        
        <p>Please make note of important dates and deadlines for this course.</p>
        
        <p>Best regards,<br>
        The {{system_name}} Team</p>
    </div>
</body>
</html>
HTML;
    }

    private function getCourseRegistrationText(): string
    {
        return <<<TEXT
Course Registration Confirmed

Dear {{student_name}},

Your registration for the following course has been confirmed:

Course Details:
- Course Name: {{course_name}}
- Course Code: {{course_code}}
- Semester: {{semester}}
- Registration Date: {{registration_date}}

Please make note of important dates and deadlines for this course.

Best regards,
The {{system_name}} Team
TEXT;
    }

    private function getAcademicHoldHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Academic Hold Notification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
            <h1 style="color: #856404; margin-top: 0;">Important: Academic Hold</h1>
        </div>
        
        <p>Dear {{student_name}},</p>
        
        <p>An academic hold has been placed on your account. This requires your immediate attention.</p>
        
        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: #dc3545; margin-top: 0;">Hold Information</h3>
            <p><strong>Hold Type:</strong> {{hold_type}}<br>
            <strong>Reason:</strong> {{hold_reason}}</p>
        </div>
        
        <p><strong>What this means:</strong> While this hold is active, you may be unable to register for courses, receive transcripts, or access certain services.</p>
        
        <p><strong>Next Steps:</strong> Please contact {{contact_info}} to resolve this hold.</p>
        
        <p>Best regards,<br>
        The {{system_name}} Team</p>
    </div>
</body>
</html>
HTML;
    }

    private function getAcademicHoldText(): string
    {
        return <<<TEXT
Important: Academic Hold

Dear {{student_name}},

An academic hold has been placed on your account. This requires your immediate attention.

Hold Information:
- Hold Type: {{hold_type}}
- Reason: {{hold_reason}}

What this means: While this hold is active, you may be unable to register for courses, receive transcripts, or access certain services.

Next Steps: Please contact {{contact_info}} to resolve this hold.

Best regards,
The {{system_name}} Team
TEXT;
    }

    private function getAssessmentDeadlineHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Assessment Deadline Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50; border-bottom: 2px solid #e74c3c; padding-bottom: 10px;">
            Assessment Deadline Reminder
        </h1>
        
        <p>Dear Student,</p>
        
        <p>This is a reminder that you have an upcoming assessment deadline:</p>
        
        <div style="background-color: #fee; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: #e74c3c; margin-top: 0;">⏰ Deadline Approaching</h3>
            <p><strong>Assessment:</strong> {{assessment_name}}<br>
            <strong>Course:</strong> {{course_name}}<br>
            <strong>Due Date:</strong> <span style="color: #e74c3c; font-weight: bold;">{{deadline}}</span></p>
        </div>
        
        <p>Submit your work here: <a href="{{submission_link}}">{{submission_link}}</a></p>
        
        <p>Don't forget to review the assessment requirements and submission guidelines.</p>
        
        <p>Best regards,<br>
        The {{system_name}} Team</p>
    </div>
</body>
</html>
HTML;
    }

    private function getAssessmentDeadlineText(): string
    {
        return <<<TEXT
Assessment Deadline Reminder

Dear Student,

This is a reminder that you have an upcoming assessment deadline:

Assessment: {{assessment_name}}
Course: {{course_name}}
Due Date: {{deadline}}

Submit your work here: {{submission_link}}

Don't forget to review the assessment requirements and submission guidelines.

Best regards,
The {{system_name}} Team
TEXT;
    }

    private function getSystemAnnouncementHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{announcement_title}}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50; border-bottom: 2px solid #9b59b6; padding-bottom: 10px;">
            {{announcement_title}}
        </h1>
        
        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
            {{announcement_body}}
        </div>
        
        <p><strong>Effective Date:</strong> {{effective_date}}</p>
        
        <p>If you have any questions, please contact our support team.</p>
        
        <p>Best regards,<br>
        The {{system_name}} Team</p>
    </div>
</body>
</html>
HTML;
    }

    private function getSystemAnnouncementText(): string
    {
        return <<<TEXT
{{announcement_title}}

{{announcement_body}}

Effective Date: {{effective_date}}

If you have any questions, please contact our support team.

Best regards,
The {{system_name}} Team
TEXT;
    }
}
