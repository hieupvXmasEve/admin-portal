<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `upload_records.reply_id`/`ticket_id` exist since the table's creation but
 * were only populated going forward once
 * UploadFileGateway::linkToQueryReply() started writing them — rows attached
 * to a query reply before that (via the always-authoritative
 * `query_replies.upload_record_id` FK) were left with reply_id/ticket_id
 * NULL. Any reader that queries upload_records.reply_id directly needs the
 * same value the forward path already writes today.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('query_replies')
            ->whereNotNull('upload_record_id')
            ->select('id', 'upload_record_id', 'ticket_id')
            ->chunkById(500, function ($replies): void {
                foreach ($replies as $reply) {
                    DB::table('upload_records')
                        ->where('id', $reply->upload_record_id)
                        ->whereNull('reply_id')
                        ->update([
                            'reply_id' => $reply->id,
                            'ticket_id' => $reply->ticket_id,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally left blank — this only fills a gap in historical
        // data (rows the forward-writing path would have set anyway);
        // reverting to NULL would just reopen the bug it fixes.
    }
};
