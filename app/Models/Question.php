<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'section_id',
        'code',
        'text',
        'type',
        'is_required',
        'help_text',
        'order_index',
        'validation_json',
        'visibility_condition_json',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_json' => 'array',
        'visibility_condition_json' => 'array',
        'type' => 'string',
    ];

    const TYPES = [
        'short_text' => 'Short Text',
        'long_text' => 'Long Text',
        'single_choice' => 'Single Choice',
        'multi_choice' => 'Multiple Choice',
        'likert' => 'Likert Scale',
        'rating' => 'Rating',
        'date' => 'Date',
        'number' => 'Number',
        'file' => 'File Upload',
        'matrix' => 'Matrix',
        'yes_no' => 'Yes/No',
    ];

    /**
     * Get the form version that owns the question.
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * Get the section that contains the question.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'section_id');
    }

    /**
     * Get the options for the question.
     */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('order_index');
    }

    /**
     * Get the answers for the question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * Check if the question requires options.
     */
    public function requiresOptions(): bool
    {
        return in_array($this->type, ['single_choice', 'multi_choice', 'likert', 'rating']);
    }

    /**
     * Check if the question accepts text answer.
     */
    public function acceptsTextAnswer(): bool
    {
        return in_array($this->type, ['short_text', 'long_text']);
    }

    /**
     * Check if the question accepts numeric answer.
     */
    public function acceptsNumericAnswer(): bool
    {
        return in_array($this->type, ['number', 'rating']);
    }

    /**
     * Check if the question accepts date answer.
     */
    public function acceptsDateAnswer(): bool
    {
        return $this->type === 'date';
    }

    /**
     * Check if the question accepts file upload.
     */
    public function acceptsFileUpload(): bool
    {
        return $this->type === 'file';
    }

    /**
     * Validate an answer value against the question's validation rules.
     */
    public function validateAnswer($value): bool
    {
        if ($this->is_required && empty($value)) {
            return false;
        }

        if (!$this->validation_json) {
            return true;
        }

        $rules = $this->validation_json;

        switch ($this->type) {
            case 'short_text':
            case 'long_text':
                if (isset($rules['maxLength']) && strlen($value) > $rules['maxLength']) {
                    return false;
                }
                if (isset($rules['minLength']) && strlen($value) < $rules['minLength']) {
                    return false;
                }
                break;

            case 'number':
                if (isset($rules['min']) && $value < $rules['min']) {
                    return false;
                }
                if (isset($rules['max']) && $value > $rules['max']) {
                    return false;
                }
                break;
        }

        return true;
    }
}