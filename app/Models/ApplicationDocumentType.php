<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ApplicationDocumentTypeSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A category of admission document (e.g. CCCD, transcript), mirrored from the
 * admissions CRM's catalog (ADR-0004). The CRM owns this data; Swinx keeps a
 * read-mostly mirror, synced idempotently by `code` via
 * {@see ApplicationDocumentTypeSyncService}, so it can label documents, order
 * them, and decide which are required.
 *
 * A type is required for everyone via `required`, or only for international
 * applicants via `int_required` — see {@see self::isRequiredFor()}.
 */
class ApplicationDocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'required',
        'int_required',
        'active',
        'order',
        'step',
    ];

    protected $casts = [
        'required' => 'boolean',
        'int_required' => 'boolean',
        'active' => 'boolean',
        'order' => 'integer',
    ];

    protected $attributes = [
        'required' => false,
        'int_required' => false,
        'active' => true,
        'order' => 0,
    ];

    /**
     * Whether a document of this type is required for the given Application.
     *
     * Required for everyone (`required`), plus international applicants must also
     * supply types flagged `int_required`.
     */
    public function isRequiredFor(StudentApplication $application): bool
    {
        return $this->required
            || ($application->is_international_applicant && $this->int_required);
    }

    /**
     * Only active catalog entries, in the CRM-defined display order.
     */
    public function scopeActiveOrdered(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('order')->orderBy('id');
    }
}
