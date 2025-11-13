<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\UploadRecord;

class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'response_id',
        'question_id',
        'answer_text',
        'answer_number',
        'answer_date',
        'comment',
    ];

    protected $casts = [
        'answer_number' => 'decimal:1',
        'answer_date' => 'date',
    ];

    /**
     * Get the response that owns the answer.
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'response_id');
    }

    /**
     * Get the question that the answer belongs to.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Get the selected options for the answer.
     */
    public function selectedOptions(): BelongsToMany
    {
        return $this->belongsToMany(Option::class, 'answer_options')
            ->withPivot('free_text')
            ->withTimestamps();
    }

    /**
     * Get the attachments for the answer.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(UploadRecord::class, 'answer_id');
    }

    /**
     * Get the formatted value based on question type.
     */
    public function getFormattedValueAttribute()
    {
        switch ($this->question->type) {
            case 'short_text':
            case 'long_text':
                return $this->answer_text;

            case 'number':
            case 'rating':
                return $this->answer_number;

            case 'date':
                return $this->answer_date;

            case 'single_choice':
            case 'multi_choice':
            case 'likert':
                return $this->selectedOptions->pluck('label')->join(', ');

            case 'yes_no':
                return $this->answer_text === '1' ? 'Yes' : 'No';

            default:
                return $this->answer_text;
        }
    }
}
