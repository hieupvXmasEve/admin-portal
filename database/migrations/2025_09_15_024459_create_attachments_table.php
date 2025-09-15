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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('answer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('storage_key', 500);
            $table->string('file_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->dateTime('uploaded_at');
            $table->timestamps();
            
            $table->index(['response_id']);
            $table->index(['answer_id']);
        });
        
        // Add check constraint to ensure either response_id or answer_id is set, but not both
        DB::statement('ALTER TABLE attachments ADD CONSTRAINT check_attachment_parent CHECK ((response_id IS NOT NULL) XOR (answer_id IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
