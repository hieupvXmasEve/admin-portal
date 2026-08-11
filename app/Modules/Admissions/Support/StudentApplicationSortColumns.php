<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support;

/**
 * The single sortable-column allow-list for `/student-applications`, shared by
 * `ListApplicationsRequest` and `ExportApplicationsRequest`. Duplicating this
 * list let the two drift — sorting by a newly-allowed column then exporting
 * 422ed because only one FormRequest had been extended.
 */
final class StudentApplicationSortColumns
{
    public const ALLOWED = [
        'created_at', 'full_name', 'student_code', 'email', 'intake', 'status',
        'crm_campus', 'crm_major', 'gpa', 'school', 'graduation_year', 'province',
        'nationality', 'crm_paid_amount', 'last_synced_at', 'overall', 'gender',
    ];

    public static function rule(): string
    {
        return 'in:'.implode(',', self::ALLOWED);
    }
}
