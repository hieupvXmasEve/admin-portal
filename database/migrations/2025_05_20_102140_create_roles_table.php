<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        
        Schema::create('user_campus_roles', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\User::class, 'user_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Campus::class, 'campus_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Role::class, 'role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
