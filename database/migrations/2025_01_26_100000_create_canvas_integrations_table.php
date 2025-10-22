<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canvas_integrations', function (Blueprint $table) {
            $table->id();
            // Removed campus_id - all campuses share one Canvas instance
            
            // Canvas OAuth credentials
            $table->string('canvas_url'); // https://canvas.instructure.com or custom domain
            $table->string('client_id');
            $table->text('client_secret'); // encrypted
            
            // OAuth tokens
            $table->text('access_token')->nullable(); // encrypted
            $table->text('refresh_token')->nullable(); // encrypted
            $table->timestamp('token_expires_at')->nullable();
            
            // Integration status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->enum('sync_status', ['idle', 'syncing', 'completed', 'failed'])->default('idle');
            $table->text('sync_error')->nullable();
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('is_active');
            $table->index('sync_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canvas_integrations');
    }
};
