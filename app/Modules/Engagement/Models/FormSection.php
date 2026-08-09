<?php

namespace App\Modules\Engagement\Models;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSection extends Model
{
    use HasFactory;

    protected $table = 'form_sections';

    protected $fillable = [
        'form_version_id',
        'title',
        'description',
        'order_index',
    ];

    /**
     * Get the form version that owns the section.
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * Get the questions in this section.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'section_id')->orderBy('order_index');
    }
}
