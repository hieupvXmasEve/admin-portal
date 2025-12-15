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
        // Only add foreign key if course_offerings table exists
        if (!Schema::hasTable('course_offerings') || !Schema::hasTable('canvas_course_mappings')) {
            return;
        }

        if (!Schema::hasColumn('canvas_course_mappings', 'course_offering_id')) {
            return;
        }

        // Use raw SQL to avoid issues with Blueprint foreign key creation
        try {
            DB::statement('
                ALTER TABLE canvas_course_mappings
                ADD CONSTRAINT canvas_course_mappings_course_offering_id_foreign
                FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
                ON DELETE SET NULL
            ');
        } catch (\Exception $e) {
            // Foreign key might already exist, check error message
            if (
                strpos($e->getMessage(), 'Duplicate key name') === false &&
                strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate foreign key') === false
            ) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('canvas_course_mappings')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE canvas_course_mappings DROP FOREIGN KEY canvas_course_mappings_course_offering_id_foreign');
        } catch (\Exception $e) {
            // Foreign key might not exist, ignore
        }
    }
};
