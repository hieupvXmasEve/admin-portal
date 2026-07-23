<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Actions;

use App\Shared\Contracts\StudentRegistry\StudentProfilePersistenceWriter;

final class UpdateStudentProfileAction
{
    /** @var list<string> */
    private const PROFILE_FIELDS = [
        'full_name', 'phone', 'avatar_url', 'date_of_birth', 'gender', 'nationality', 'ethnicity',
        'national_id', 'address', 'current_address_line', 'current_ward', 'current_province', 'current_country',
        'cccd_address', 'cccd_address_line', 'cccd_ward', 'cccd_province', 'cccd_country',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_email', 'emergency_contact_relationship',
        'emergency_contact_name_1', 'emergency_contact_phone_1', 'emergency_contact_email_1', 'emergency_contact_relationship_1',
        'high_school_name',
    ];

    /** @param array{student_id: int, attributes: array<string, mixed>} $data */
    public static function run(array $data): bool
    {
        $profileAttributes = array_intersect_key($data['attributes'], array_flip(self::PROFILE_FIELDS));

        return app(StudentProfilePersistenceWriter::class)->update($data['student_id'], $profileAttributes);
    }
}
