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
        Schema::create('query_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('queries_tickets')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('author_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->longText('message');
            $table->boolean('is_official_answer')->default(false);
            $table->foreignId('upload_record_id')->nullable()->constrained('upload_records')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_replies');
    }
};
