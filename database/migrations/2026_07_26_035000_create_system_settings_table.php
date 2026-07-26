<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            'app_name' => 'Swinx',
            'copyright_text' => '© 2026 Asia Vietnam University. All rights reserved.',
            'country' => 'Việt Nam',
            'survey_enabled' => false,
            'default_course_survey' => null,
            'active_query_forms' => [],
            'system_booking_start_time' => '07:00',
            'system_booking_end_time' => '20:00',
            'allow_student_booking' => true,
            'student_booking_limit_per_day' => 2,
            'logo_full_upload_id' => null,
            'logo_text_upload_id' => null,
            'favicon_upload_id' => null,
            'apple_touch_icon_upload_id' => null,
        ];

        DB::table('system_settings')->insert(array_map(
            static fn (string $key, mixed $value): array => [
                'key' => $key,
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_keys($defaults),
            $defaults,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
