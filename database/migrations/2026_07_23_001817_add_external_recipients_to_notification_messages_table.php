<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_messages', function (Blueprint $table) {
            $table->string('recipient_key', 320)->nullable()->after('recipient_user_id');
            $table->string('recipient_email', 254)->nullable()->after('recipient_key');
        });

        DB::table('notification_messages')
            ->orderBy('id')
            ->eachById(function (object $message): void {
                DB::table('notification_messages')
                    ->where('id', $message->id)
                    ->update(['recipient_key' => 'user:'.$message->recipient_user_id]);
            });

        Schema::table('notification_messages', function (Blueprint $table) {
            $table->dropUnique('notification_messages_event_type_recipient_unique');
            $table->dropForeign(['recipient_user_id']);
            $table->unsignedBigInteger('recipient_user_id')->nullable()->change();
            $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('recipient_key', 320)->nullable(false)->change();
            $table->unique(['event_id', 'type_key', 'recipient_key'], 'notification_messages_event_type_recipient_key_unique');
            $table->index('recipient_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('notification_messages')->whereNull('recipient_user_id')->exists()) {
            throw new LogicException(
                'Cannot roll back external notification recipients while their delivery history exists.',
            );
        }

        Schema::table('notification_messages', function (Blueprint $table) {
            $table->dropIndex(['recipient_email']);
            $table->dropUnique('notification_messages_event_type_recipient_key_unique');
            $table->dropForeign(['recipient_user_id']);
            $table->unsignedBigInteger('recipient_user_id')->nullable(false)->change();
            $table->foreign('recipient_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['event_id', 'type_key', 'recipient_user_id'], 'notification_messages_event_type_recipient_unique');
            $table->dropColumn(['recipient_key', 'recipient_email']);
        });
    }
};
