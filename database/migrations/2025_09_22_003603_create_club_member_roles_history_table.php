<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('club_member_roles_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->enum('old_role', ['president', 'vice_president', 'secretary', 'treasurer', 'member'])->nullable();
            $table->enum('new_role', ['president', 'vice_president', 'secretary', 'treasurer', 'member']);
            $table->foreignId('changed_by')->nullable()->constrained('students')->nullOnDelete();
            $table->text('change_reason')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('club_member_id');
            $table->index('changed_by');
            $table->index('started_at');
            $table->index(['club_member_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_member_roles_history');
    }
};
