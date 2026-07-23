<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Console;

use App\Modules\Engagement\Actions\EventNotificationPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEventReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications for upcoming events';

    /**
     * Execute the console command.
     */
    public function handle(EventNotificationPublisher $eventNotificationPublisher): int
    {
        $this->info('Sending event reminders...');

        try {
            $remindersSent = $eventNotificationPublisher->sendEventReminders();

            $this->info("Successfully sent {$remindersSent} event reminders.");

            Log::info('Event reminders command completed', [
                'reminders_sent' => $remindersSent,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to send event reminders: '.$e->getMessage());

            Log::error('Event reminders command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
