<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redemption order header. `status`/`previous_status`/`method` are backend
 * allow-lists (see App\Modules\Merchandise\Models\RedemptionOrder) rather than DB enums, matching
 * the Merchandise catalog convention.
 *
 * `campus_id` is a SNAPSHOT of the student's campus at order time, not a live
 * lookup — if the student transfers campus afterwards, the order (and the
 * staff queue that owns it) stays anchored to the campus it was placed at.
 *
 * `idempotency_key` + the unique (student_id, idempotency_key) pair let a
 * client retry a checkout POST after a dropped response without double
 * charging Gold: a second insert with the same key collides at the DB and the
 * service replays the existing order instead.
 *
 * `cancellation_requested_by` intentionally has NO foreign key: cancellation
 * requests always originate from the order's own student (a Student row, not
 * a `users` row), so it is a plain nullable id column for audit display, kept
 * separate from `student_id` only in case a future staff-initiated-on-behalf
 * path needs to distinguish the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemption_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses');
            $table->string('code')->unique();
            $table->string('status', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('method', 16);
            $table->unsignedInteger('total_gold');
            $table->string('idempotency_key', 64)->nullable();

            // Pickup
            $table->string('collection_location')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('collection_deadline')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('collection_note')->nullable();

            // Shipping
            $table->text('shipping_address')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('shipping_note')->nullable();

            // Reject / cancel
            $table->text('reject_reason')->nullable();
            $table->unsignedBigInteger('cancellation_requested_by')->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancellation_handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancellation_handled_at')->nullable();
            $table->string('cancellation_result', 16)->nullable();
            $table->text('cancellation_note')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // student_id/campus_id are already indexed via their FK constraints;
            // status is the one additional lookup path (staff queues).
            $table->index('status');
            $table->unique(['student_id', 'idempotency_key'], 'redemption_orders_student_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemption_orders');
    }
};
