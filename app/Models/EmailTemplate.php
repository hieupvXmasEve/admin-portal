<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends AuditableModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'subject',
        'html_content',
        'text_content',
        'variables',
        'is_active',
        'version',
        'parent_id',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    /**
     * Template types
     */
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_GRADE_NOTIFICATION = 'grade_notification';
    public const TYPE_COURSE_REGISTRATION = 'course_registration';
    public const TYPE_ACADEMIC_HOLD = 'academic_hold';
    public const TYPE_ENROLLMENT_CONFIRMATION = 'enrollment_confirmation';
    public const TYPE_ASSESSMENT_DEADLINE = 'assessment_deadline';
    public const TYPE_SYSTEM_ANNOUNCEMENT = 'system_announcement';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_CUSTOM = 'custom';

    /**
     * Get all available template types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_WELCOME => 'Welcome Email',
            self::TYPE_GRADE_NOTIFICATION => 'Grade Notification',
            self::TYPE_COURSE_REGISTRATION => 'Course Registration',
            self::TYPE_ACADEMIC_HOLD => 'Academic Hold',
            self::TYPE_ENROLLMENT_CONFIRMATION => 'Enrollment Confirmation',
            self::TYPE_ASSESSMENT_DEADLINE => 'Assessment Deadline',
            self::TYPE_SYSTEM_ANNOUNCEMENT => 'System Announcement',
            self::TYPE_REMINDER => 'Reminder',
            self::TYPE_CUSTOM => 'Custom',
        ];
    }

    /**
     * Parent template relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'parent_id');
    }

    /**
     * Child templates relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(EmailTemplate::class, 'parent_id');
    }

    /**
     * Get active templates by type
     */
    public static function getByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('type', $type)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Get the latest version of a template by name
     */
    public static function getLatestVersion(string $name): ?self
    {
        return static::where('name', $name)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * Render template with variables
     */
    public function render(array $variables = []): array
    {
        $subject = $this->renderContent($this->subject, $variables);
        $htmlContent = $this->renderContent($this->html_content, $variables);
        $textContent = $this->text_content ? $this->renderContent($this->text_content, $variables) : null;

        return [
            'subject' => $subject,
            'html' => $htmlContent,
            'text' => $textContent,
        ];
    }

    /**
     * Render content with variable substitution
     */
    protected function renderContent(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = "{{{$key}}}";
            $content = str_replace($placeholder, (string) $value, $content);
        }

        return $content;
    }

    /**
     * Validate template content for required variables
     */
    public function validateVariables(array $providedVariables): array
    {
        $requiredVariables = $this->extractVariables();
        $missingVariables = [];

        foreach ($requiredVariables as $variable) {
            if (!array_key_exists($variable, $providedVariables)) {
                $missingVariables[] = $variable;
            }
        }

        return $missingVariables;
    }

    /**
     * Extract variables from template content
     */
    public function extractVariables(): array
    {
        $content = $this->subject . ' ' . $this->html_content . ' ' . ($this->text_content ?? '');
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);

        return array_unique($matches[1] ?? []);
    }

    /**
     * Create a new version of this template
     */
    public function createNewVersion(array $attributes): self
    {
        $newVersion = $this->version + 1;

        return static::create(array_merge($attributes, [
            'name' => $this->name,
            'type' => $this->type,
            'version' => $newVersion,
            'parent_id' => $this->id,
        ]));
    }

    /**
     * Validation rules for email template
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', array_keys(static::getTypes())),
            'subject' => 'required|string|max:255',
            'html_content' => 'required|string',
            'text_content' => 'nullable|string',
            'variables' => 'nullable|array',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'required|boolean',
        ];
    }

    /**
     * Custom activity descriptions for email template events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => "Created email template: {$this->name} v{$this->version}",
            'updated' => "Updated email template: {$this->name} v{$this->version}",
            'deleted' => "Deleted email template: {$this->name} v{$this->version}",
            'restored' => "Restored email template: {$this->name} v{$this->version}",
            default => "{$eventName} email template: {$this->name} v{$this->version}",
        };
    }
}
