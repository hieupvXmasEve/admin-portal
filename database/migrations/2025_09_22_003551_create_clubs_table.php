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
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->date('founded_date')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->json('social_links')->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('achievements')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('campus_id');
            $table->index('status');
            $table->unique(['campus_id', 'name'], 'unique_club_name_per_campus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
