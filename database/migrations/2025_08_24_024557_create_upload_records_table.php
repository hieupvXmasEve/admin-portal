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
        Schema::create('upload_records', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->index();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // File size in bytes
            $table->string('context')->index(); // Upload context (avatar, assignment, etc.)
            $table->string('path'); // Storage path
            $table->string('disk'); // Storage disk name
            $table->text('url')->nullable(); // Public URL if applicable
            $table->string('hash')->nullable()->index(); // File hash for deduplication
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            // Foreign keys for student_id, response_id, answer_id, ticket_id, reply_id 
            // will be added in later migrations after the referenced tables exist
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('response_id')->nullable();
            $table->unsignedBigInteger('answer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('reply_id')->nullable();
            $table->json('metadata')->nullable(); // Additional metadata (dimensions, etc.)
            $table->timestamp('expires_at')->nullable(); // For temporary uploads
            $table->timestamps();

            // Indexes for common queries
            $table->index(['context', 'user_id']);
            $table->index(['context', 'student_id']);
            $table->index(['response_id']);
            $table->index(['answer_id']);
            $table->index(['ticket_id']);
            $table->index(['reply_id']);
            $table->index(['created_at', 'context']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_records');
    }
};
