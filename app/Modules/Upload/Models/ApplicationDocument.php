<?php

declare(strict_types=1);

namespace App\Modules\Upload\Models;

use App\Models\StudentApplication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document linked to an Application (1-n) as an external link reference.
 *
 * Stores the CRM-owned URL plus metadata — never the file bytes, and never an
 * `upload_records` row (ADR-0004). Multiple documents may share a
 * `file_type_code` (e.g. a multi-page transcript). `file_type_name` is
 * denormalized for display so the list renders without joining the catalog.
 */
class ApplicationDocument extends Model
{
    use HasFactory;

    protected $table = 'application_documents';

    protected $fillable = [
        'student_application_id',
        'crm_file_id',
        'file_type_code',
        'file_type_name',
        'page_index',
        'original_name',
        'link',
        'mime_type',
        'size',
        'status',
    ];

    protected $casts = [
        'page_index' => 'integer',
        'size' => 'integer',
    ];

    protected $attributes = [
        'page_index' => 0,
    ];

    /**
     * The Application this document belongs to.
     */
    public function studentApplication(): BelongsTo
    {
        return $this->belongsTo(StudentApplication::class);
    }
}
