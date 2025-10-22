<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_component_details', function (Blueprint $table) {
            // Canvas grading type: points, percent, letter_grade, gpa_scale, pass_fail, not_graded
            $table->string('grading_type', 20)->nullable()->after('canvas_synced_at');
            
            // Canvas submission types: online_text_entry, online_url, online_upload, etc.
            $table->json('submission_types')->nullable()->after('grading_type');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_component_details', function (Blueprint $table) {
            $table->dropColumn(['grading_type', 'submission_types']);
        });
    }
};
