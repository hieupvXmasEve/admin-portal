<?php

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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('location');
            $table->decimal('gold_reward_amount', 10, 2)->default(0);
            $table->unsignedInteger('max_participants')->nullable();
            $table->string('qr_code')->unique();
            $table->enum('organizer_type', ['school', 'club'])->default(value: 'school');
            $table->unsignedBigInteger('organizer_id'); // campus_id for school events, club_id for club events
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            // Indexes for performance
            $table->index(['campus_id', 'status']);
            $table->index('start_time');
            $table->index('qr_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
