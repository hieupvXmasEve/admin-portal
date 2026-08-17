<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A raw CRM value (campus, major, specialization, scholarship, pathway_gateway,
 * uu_dai_gc) mapped to a local code, plus the single target-intake row
 * (`kind='intake', crm_value='__default__'`). `local_code === null` means
 * discovered but not yet mapped by staff.
 *
 * `kind` is a BE-validated allow-list, not a DB enum.
 */
class CrmValueMapping extends Model
{
    public const KIND_CAMPUS = 'campus';

    public const KIND_MAJOR = 'major';

    public const KIND_SPECIALIZATION = 'specialization';

    public const KIND_INTAKE = 'intake';

    public const KIND_INTAKE_COHORT = 'intake_cohort';

    public const KIND_SCHOLARSHIP = 'scholarship';

    public const KIND_PATHWAY_GATEWAY = 'pathway_gateway';

    public const KIND_UU_DAI_GC = 'uu_dai_gc';

    public const INTAKE_DEFAULT_KEY = '__default__';

    protected $fillable = [
        'kind',
        'crm_value',
        'local_code',
    ];
}
