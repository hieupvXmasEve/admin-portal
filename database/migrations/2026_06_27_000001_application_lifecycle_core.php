<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application lifecycle core (slice 02).
 *
 * Gives the Application a real, accountable lifecycle:
 *  - status becomes a BE-validated string (allow-list: pending|enrolled|rejected),
 *    no longer a DB enum, defaulting to `pending` (auto-approve removed).
 *  - audit columns record who approved/rejected an Application and when.
 *
 * Schema change is purely additive plus a column-type relaxation; existing rows
 * keep their current status values (backfill is handled in slice 09).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Relax `status` from a DB enum to a BE-validated string and stop
        // defaulting to `approved` — applications are now born `pending`.
        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });

        Schema::table('student_applications', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('student_code')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->foreignId('rejected_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejected_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejected_reason']);
        });

        Schema::table('student_applications', function (Blueprint $table) {
            $table->enum('status', ['pending', 'reviewed', 'approved', 'rejected'])
                ->default('approved')->change();
        });
    }
};
