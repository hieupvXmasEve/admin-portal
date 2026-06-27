<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guardians (1-n) on Applications (slice 05).
 *
 * Replaces the thin `parent_phone` / `parent_email` pair with a proper 1-n list
 * of Guardians. `relationship` is a BE-validated allow-list (NOT a DB enum), so
 * new relationship values need no migration. Exactly one Guardian is primary per
 * Application; the "at most one" half is enforced here at the DB level, and the
 * service layer keeps "at least one" whenever any Guardian exists.
 *
 * The legacy `parent_*` columns stay in place until the migration slice (08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_application_id')
                ->constrained('student_applications')
                ->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('student_application_id');
        });

        // At-most-one primary Guardian per Application, enforced at the DB level.
        // The generated column carries the application id only for the primary
        // row and NULL otherwise; NULLs do not collide in a unique index, so any
        // number of non-primary Guardians coexist while a second primary for the
        // same Application is rejected.
        Schema::table('application_guardians', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_guardian_key')
                ->nullable()
                ->storedAs('CASE WHEN is_primary = 1 THEN student_application_id ELSE NULL END')
                ->after('is_primary');
            $table->unique('primary_guardian_key', 'application_guardians_one_primary_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_guardians');
    }
};
