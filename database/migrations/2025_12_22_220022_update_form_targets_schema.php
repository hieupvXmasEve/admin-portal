<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_targets', function (Blueprint $table) {
            $table->string('status')->default('active')->after('scope_id');
            $table->boolean('is_mandatory')->default(false);
            $table->string('semester_id')->nullable()->after('campus_id');
            $table->integer('submission_limit_per_user')->nullable()->default(1)->change();
        });

        // Modify scope_type enum
        DB::statement("ALTER TABLE form_targets MODIFY COLUMN scope_type ENUM('section', 'class_session', 'course', 'global', 'department', 'semester') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert scope_type enum
        DB::statement("ALTER TABLE form_targets MODIFY COLUMN scope_type ENUM('section', 'class_session', 'course', 'global') NOT NULL");

        Schema::table('form_targets', function (Blueprint $table) {
            $table->integer('submission_limit_per_user')->nullable(false)->default(1)->change();
            $table->dropColumn(['status', 'is_mandatory', 'semester_id']);
        });
    }
};
