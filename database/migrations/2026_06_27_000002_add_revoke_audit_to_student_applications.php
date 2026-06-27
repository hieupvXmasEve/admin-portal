<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revoke audit columns (slice 04).
 *
 * Records who revoked a mistaken approval and when. Revoke tears the created
 * Student/User/roles down within a safe window and returns the Application to
 * `pending`; these columns make "who revoked, and when" a first-class fact
 * (full transition history still lives in the activity log — ADR-0001/0002).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->foreignId('revoked_by')->nullable()->after('rejected_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn('revoked_at');
        });
    }
};
