<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('notifications');
    }

    public function down(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->morphs('notifiable');
            $table->enum('category', ['academic', 'system', 'finance', 'personal', 'event', 'club', 'administrative', 'admin']);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->json('channels');
            $table->boolean('is_important')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }
};
